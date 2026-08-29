<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Casts;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'is_active'])]
#[Casts(['is_active' => 'boolean'])]
class Brand extends Model
{
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
