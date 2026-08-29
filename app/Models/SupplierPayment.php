<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Casts;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'supplier_invoice_id',
    'payment_number',
    'paid_at',
    'amount',
    'reference',
    'notes',
    'paid_by',
])]
#[Casts([
    'paid_at' => 'datetime',
    'amount' => 'decimal:2',
])]
class SupplierPayment extends Model
{
    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
