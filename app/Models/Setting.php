<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Casts;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value', 'type', 'group', 'is_public'])]
#[Casts(['is_public' => 'boolean'])]
class Setting extends Model
{
    public function typedValue(): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => $this->value === null ? null : (int) $this->value,
            'float' => $this->value === null ? null : (float) $this->value,
            'json' => $this->value === null ? null : json_decode($this->value, true, 512, JSON_THROW_ON_ERROR),
            default => $this->value,
        };
    }
}
