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

            $this->assertPayablesReconciled();

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

    private function assertPayablesReconciled(): void
    {
        $discrepancies = app(SupplierPayableReconciliationService::class)
            ->reconcile()
            ->filter(fn (array $result): bool => $result['reconciliation_status'] !== 'matched');

        if ($discrepancies->isNotEmpty()) {
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
