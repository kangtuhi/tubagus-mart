<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountingPeriodStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'start_date',
    'end_date',
    'status',
    'closed_at',
    'closed_by',
    'closing_reason',
])]
class AccountingPeriod extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => AccountingPeriodStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AccountingPeriodEvent::class);
    }

    public function isOpen(): bool
    {
        return $this->status === AccountingPeriodStatus::OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === AccountingPeriodStatus::CLOSED;
    }
}
