<?php

declare(strict_types=1);

namespace App\Services\Payables;

use App\Models\AccountingPeriod;
use App\Models\SupplierPayableReconciliationDiscrepancy;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SupplierPayableDiscrepancyService
{
    private readonly SupplierPayableReconciliationService $reconciliation;

    public function __construct(SupplierPayableReconciliationService $reconciliation)
    {
        $this->reconciliation = $reconciliation;
    }

    public function sync(AccountingPeriod $period, ?User $actor = null): Collection
    {
        $rows = $this->reconciliation->reconcile(
            from: $period->start_date,
            to: $period->end_date,
        );

        $discrepancies = collect();

        foreach ($rows as $row) {
            foreach ($this->typesFor($row) as $type) {
                $discrepancy = SupplierPayableReconciliationDiscrepancy::query()
                    ->where('accounting_period_id', $period->id)
                    ->where('supplier_id', $row['supplier_id'])
                    ->where('type', $type['type'])
                    ->where('status', '!=', 'resolved')
                    ->latest('id')
                    ->first();

                $payload = [
                    'expected_amount' => $type['expected_amount'],
                    'actual_amount' => $type['actual_amount'],
                    'difference' => $type['difference'],
                    'last_reconciled_at' => now(),
                ];

                if ($discrepancy === null) {
                    $discrepancy = SupplierPayableReconciliationDiscrepancy::create([
                        'accounting_period_id' => $period->id,
                        'supplier_id' => $row['supplier_id'],
                        'type' => $type['type'],
                        'status' => 'open',
                        'detected_at' => now(),
                        'opened_by' => $actor?->id,
                        ...$payload,
                    ]);
                } else {
                    $discrepancy->update($payload);
                }

                $discrepancies->push($discrepancy->fresh());
            }
        }

        return $discrepancies->values();
    }

    public function investigate(
        SupplierPayableReconciliationDiscrepancy $discrepancy,
        User $actor,
    ): SupplierPayableReconciliationDiscrepancy {
        $this->authorize($actor, 'accounting.ap.discrepancy.investigate');

        if ($discrepancy->status === 'resolved') {
            throw ValidationException::withMessages([
                'status' => 'A resolved discrepancy cannot be moved back to investigation.',
            ]);
        }

        $discrepancy->update(['status' => 'investigating']);

        return $discrepancy->fresh();
    }

    public function resolve(
        SupplierPayableReconciliationDiscrepancy $discrepancy,
        User $actor,
        string $reason,
        ?string $notes = null,
    ): SupplierPayableReconciliationDiscrepancy {
        $this->authorize($actor, 'accounting.ap.discrepancy.resolve');

        if ($discrepancy->status === 'resolved') {
            throw ValidationException::withMessages([
                'status' => 'The discrepancy is already resolved.',
            ]);
        }

        $period = $discrepancy->accountingPeriod()->firstOrFail();
        $row = $this->reconciliation->reconcile(
            supplierId: $discrepancy->supplier_id,
            from: $period->start_date,
            to: $period->end_date,
        )->first();

        if ($row === null || $this->hasUnresolvedDifference($row, $discrepancy->type)) {
            throw ValidationException::withMessages([
                'resolution_reason' => 'The discrepancy must be re-reconciled successfully before it can be resolved.',
            ]);
        }

        $discrepancy->update([
            'status' => 'resolved',
            'resolved_by' => $actor->id,
            'resolved_at' => now(),
            'resolution_reason' => $reason,
            'resolution_notes' => $notes,
            'last_reconciled_at' => now(),
        ]);

        return $discrepancy->fresh();
    }

    private function typesFor(array $row): array
    {
        $types = [];

        if ((float) $row['reconciliation_difference'] !== 0.0) {
            $types[] = [
                'type' => 'statement_mismatch',
                'expected_amount' => $row['operational_balance'],
                'actual_amount' => $row['statement_balance'],
                'difference' => $row['reconciliation_difference'],
            ];
        }

        if ((float) $row['payment_ledger_difference'] !== 0.0) {
            $types[] = [
                'type' => 'payment_mismatch',
                'expected_amount' => 0,
                'actual_amount' => $row['payment_ledger_difference'],
                'difference' => $row['payment_ledger_difference'],
            ];
        }

        return $types;
    }

    private function hasUnresolvedDifference(array $row, string $type): bool
    {
        return match ($type) {
            'statement_mismatch' => (float) $row['reconciliation_difference'] !== 0.0,
            'payment_mismatch' => (float) $row['payment_ledger_difference'] !== 0.0,
            default => true,
        };
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $actor->is_active || ! $actor->hasPermission($permission)) {
            throw ValidationException::withMessages([
                'user' => 'The user is not authorized for AP discrepancy control.',
            ]);
        }
    }
}
