<?php

namespace App\Models;

use App\Enums\SupplierPayableAdjustmentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'supplier_invoice_id',
    'number',
    'type',
    'adjustment_date',
    'amount',
    'reason',
    'notes',
    'created_by',
])]
class SupplierPayableAdjustment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => SupplierPayableAdjustmentType::class,
            'adjustment_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
