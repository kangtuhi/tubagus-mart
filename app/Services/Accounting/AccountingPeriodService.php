<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\AccountingPeriodStatus;
use App\Models\AccountingPeriod;
use App\Services\Payables\SupplierPayableReconciliationService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingPeriodService
{
    public function open(CarbonInterface $startDate, CarbonInterface $endDate): AccountingPeriod
    {
        if ($startDate->gt($endDate)) {
            throw ValidationException::withMessages([
                'start_date' => 'Accounting period start date must be before or equal to the end date.',
            ]);
        }

        if ($this->overlaps($startDate, $endDate)) {
            throw ValidationException::withMessages([
                'period' => 'Accounting period overlaps an existing period.',
            ]);
        }

        return AccountingPeriod::query()->create([
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'status' => AccountingPeriodStatus::OPEN,
        ]);
    }

    public function close(AccountingPeriod $period, ?int $closedBy = null, ?string $reason = null): AccountingPeriod
    {
        return DB::transaction(function () use ($period, $closedBy, $reason): AccountingPeriod {
            $period = AccountingPeriod::query()->lockForUpdate()->findOrFail($period->getKey());

            if ($period->isClosed()) {
                throw ValidationException::withMessages([
                    'period' => 'Accounting period is already closed.',
                ]);
            }

            $this->assertClosingGate($period);

            $period->update([
                'status' => AccountingPeriodStatus::CLOSED,
                'closed_at' => now(),
                'closed_by' => $closedBy,
                'closing_reason' => $reason,
            ]);

            return $period->refresh();
        });
    }

    public function reopen(AccountingPeriod $period): AccountingPeriod
    {
        return DB::transaction(function () use ($period): AccountingPeriod {
            $period = AccountingPeriod::query()->lockForUpdate()->findOrFail($period->getKey());

            if ($period->isOpen()) {
                throw ValidationException::withMessages([
                    'period' => 'Accounting period is already open.',
                ]);
            }

            $period->update([
                'status' => AccountingPeriodStatus::OPEN,
                'closed_at' => null,
                'closed_by' => null,
                'closing_reason' => null,
            ]);

            return $period->refresh();
        });
    }

    public function forDate(CarbonInterface $date): ?AccountingPeriod
    {
        return AccountingPeriod::query()
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }

    public function assertOpen(CarbonInterface $date): AccountingPeriod
    {
        $period = $this->forDate($date);

        if ($period === null) {
            throw ValidationException::withMessages([
                'date' => 'No accounting period exists for the supplied date.',
            ]);
        }

        if ($period->isClosed()) {
            throw ValidationException::withMessages([
                'date' => 'The accounting period for the supplied date is closed.',
            ]);
        }

        return $period;
    }

    public function assertOpenIfDefined(CarbonInterface $date): ?AccountingPeriod
    {
        $period = $this->forDate($date);

        if ($period === null) {
            return null;
        }

        if ($period->isClosed()) {
            throw ValidationException::withMessages([
                'date' => 'The accounting period for the supplied date is closed.',
            ]);
        }

        return $period;
    }

    public function closingGate(AccountingPeriod $period): array
    {
        $reconciliation = app(SupplierPayableReconciliationService::class)
            ->reconcile(to: $period->end_date);
        $discrepancies = $reconciliation
            ->filter(fn (array $result): bool => $result['reconciliation_status'] !== 'matched')
            ->values();

        return [
            'period_id' => $period->id,
            'period_start_date' => $period->start_date->toDateString(),
            'period_end_date' => $period->end_date->toDateString(),
            'period_status' => $period->status->value,
            'can_close' => $discrepancies->isEmpty(),
            'checks' => [
                'ap_reconciliation' => [
                    'status' => $discrepancies->isEmpty() ? 'passed' : 'failed',
                    'supplier_count' => $reconciliation->count(),
                    'discrepancy_count' => $discrepancies->count(),
                ],
            ],
            'discrepancies' => $discrepancies->all(),
        ];
    }

    private function assertClosingGate(AccountingPeriod $period): void
    {
        $gate = $this->closingGate($period);

        if (! $gate['can_close']) {
            throw ValidationException::withMessages([
                'period' => 'Accounting period cannot be closed while AP reconciliation has discrepancies.',
            ]);
        }
    }

    private function overlaps(CarbonInterface $startDate, CarbonInterface $endDate): bool
    {
        return AccountingPeriod::query()
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();
    }
}
