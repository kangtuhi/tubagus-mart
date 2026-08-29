<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Attributes\Casts;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'supplier_id',
    'number',
    'ordered_at',
    'expected_at',
    'status',
    'subtotal',
    'discount_amount',
    'tax_amount',
    'grand_total',
    'created_by',
    'approved_by',
    'approved_at',
    'notes',
])]
#[Casts([
    'status' => PurchaseOrderStatus::class,
    'ordered_at' => 'date',
    'expected_at' => 'date',
    'approved_at' => 'datetime',
    'subtotal' => 'decimal:2',
    'discount_amount' => 'decimal:2',
    'tax_amount' => 'decimal:2',
    'grand_total' => 'decimal:2',
])]
class PurchaseOrder extends Model
{
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
