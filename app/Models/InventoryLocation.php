<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Casts;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'description', 'is_active'])]
#[Casts(['is_active' => 'boolean'])]
class InventoryLocation extends Model
{
    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class, 'location_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'location_id');
    }
}
