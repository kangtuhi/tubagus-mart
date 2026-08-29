<?php

namespace App\Models;

use App\Enums\InventoryMovementType;
use Illuminate\Database\Eloquent\Attributes\Casts;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'location_id',
    'movement_type',
    'quantity',
    'unit_cost',
    'balance_after',
    'reference_type',
    'reference_id',
    'notes',
    'created_by',
])]
#[Casts([
    'movement_type' => InventoryMovementType::class,
    'quantity' => 'decimal:3',
    'unit_cost' => 'decimal:2',
    'balance_after' => 'decimal:3',
])]
class InventoryMovement extends Model
{
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
