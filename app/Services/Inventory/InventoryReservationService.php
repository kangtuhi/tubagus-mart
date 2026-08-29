<?php

namespace App\Services\Inventory;

use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Product;
use DomainException;
use Illuminate\Database\DatabaseManager;

class InventoryReservationService
{
    public function __construct(
        private readonly DatabaseManager $database,
    ) {}

    public function reserve(Product $product, InventoryLocation $location, float $quantity): InventoryStock
    {
        $this->validateQuantity($quantity);

        return $this->database->transaction(function () use ($product, $location, $quantity) {
            if (! $product->track_stock) {
                throw new DomainException('This product does not track stock.');
            }

            $stock = InventoryStock::query()
                ->where('product_id', $product->id)
                ->where('location_id', $location->id)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                throw new DomainException('No inventory stock record exists for this product and location.');
            }

            $available = (float) $stock->quantity - (float) $stock->reserved_quantity;

            if ($quantity > $available) {
                throw new DomainException('Insufficient available stock for reservation.');
            }

            $stock->increment('reserved_quantity', $quantity);
            $stock->refresh();

            return $stock;
        });
    }

    public function release(Product $product, InventoryLocation $location, float $quantity): InventoryStock
    {
        $this->validateQuantity($quantity);

        return $this->database->transaction(function () use ($product, $location, $quantity) {
            $stock = InventoryStock::query()
                ->where('product_id', $product->id)
                ->where('location_id', $location->id)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                throw new DomainException('No inventory stock record exists for this product and location.');
            }

            if ($quantity > (float) $stock->reserved_quantity) {
                throw new DomainException('Release quantity exceeds reserved stock.');
            }

            $stock->decrement('reserved_quantity', $quantity);
            $stock->refresh();

            return $stock;
        });
    }

    private function validateQuantity(float $quantity): void
    {
        if ($quantity <= 0) {
            throw new DomainException('Reservation quantity must be greater than zero.');
        }
    }
}
