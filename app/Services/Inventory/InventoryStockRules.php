<?php

namespace App\Services\Inventory;

use App\Models\InventoryStock;
use DomainException;

class InventoryStockRules
{
    public function validate(InventoryStock $stock): void
    {
        $quantity = (float) $stock->quantity;
        $reserved = (float) $stock->reserved_quantity;

        if ($quantity < 0) {
            throw new DomainException('Stock quantity cannot be negative.');
        }

        if ($reserved < 0) {
            throw new DomainException('Reserved stock cannot be negative.');
        }

        if ($reserved > $quantity) {
            throw new DomainException('Reserved stock cannot exceed physical stock.');
        }
    }

    public function availableQuantity(InventoryStock $stock): float
    {
        $this->validate($stock);

        return (float) $stock->quantity - (float) $stock->reserved_quantity;
    }
}
