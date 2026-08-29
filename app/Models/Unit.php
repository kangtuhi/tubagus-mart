<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Casts;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'symbol', 'decimal_places', 'is_active'])]
#[Casts(['decimal_places' => 'integer', 'is_active' => 'boolean'])]
class Unit extends Model
{
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'base_unit_id');
    }
}
