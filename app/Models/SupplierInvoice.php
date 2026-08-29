<?php

namespace App\Models;

use App\Enums\SupplierInvoiceStatus;
use Illuminate\Database\Eloquent\Attributes\Casts;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'supplier_id',
    'purchase_order_id',
    'number',
    'invoice_date',
    'due_date',
    'status',
    'subtotal',
    'discount_amount',
    'tax_amount',
    'grand_total',
    'paid_amount',
    'notes',
])]
#[Casts([
    'status' => SupplierInvoiceStatus::class,
    'invoice_date' => 'date',
    'due_date' => 'date',
    'subtotal' => 'decimal:2',
    'discount_amount' => 'decimal:2',
    'tax_amount' => 'decimal:2',
    'grand_total' => 'decimal:2',
    'paid_amount' => 'decimal:2',
])]
class SupplierInvoice extends Model
{
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
