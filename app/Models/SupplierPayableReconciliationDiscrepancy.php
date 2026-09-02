<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'accounting_period_id',
    'supplier_id',
    'type',
    'status',
    'expected_amount',
    'actual_amount',
    'difference',
    'detected_at',
    'opened_by',
    'last_reconciled_at',
    'resolved_by',
    'resolved_at',
    'resolution_reason',
    'resolution_notes',
])]
class SupplierPayableReconciliationDiscrepancy extends Model
{
    protected function casts(): array
    {
        return [
            'expected_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'difference' => 'decimal:2',
            'detected_at' => 'datetime',
            'last_reconciled_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpen(): bool
    {
        return $this->status !== 'resolved';
    }
}
