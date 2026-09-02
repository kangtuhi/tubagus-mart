<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'accounting_period_id',
    'action',
    'performed_by',
    'performed_at',
    'reason',
])]
#[Immutable(['performed_at'])]
class AccountingPeriodEvent extends Model
{
    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
        ];
    }

    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
