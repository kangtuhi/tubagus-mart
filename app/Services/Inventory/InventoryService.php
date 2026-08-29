<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Product;
use DomainException;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function recordMovement(
        Product $product,
        int $locationId,
        InventoryMovementType $type,
        float $quantity,
        ?float $unitCost = null,
        ?string $notes = null,
        ?int $createdBy = null,
    ): InventoryMovement {
        if (! $product->track_stock) {
            throw new DomainException('This product does not track stock.');
        }

        if ($quantity <= 0) {
            throw new DomainException('Movement quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($product, $locationId, $type, $quantity, $unitCost, $notes, $createdBy) {
            $stock = InventoryStock::query()
                ->where('product_id', $product->id)
                ->where('location_id', $locationId)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                $stock = new InventoryStock([
                    'product_id' => $product->id,
                    'location_id' => $locationId,
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                ]);
                $stock->save();
                $stock->refresh();
            }

            $delta = $this->signedQuantity($type, $quantity);
            $newQuantity = (float) $stock->quantity + $delta;

            if ($newQuantity < 0) {
                throw new DomainException('Insufficient stock for this movement.');
            }

            $stock->quantity = $newQuantity;
            $stock->save();

            return InventoryMovement::query()->create([
                'product_id' => $product->id,
                'location_id' => $locationId,
                'movement_type' => $type,
                'quantity' => $delta,
                'unit_cost' => $unitCost,
                'balance_after' => $newQuantity,
                'notes' => $notes,
                'created_by' => $createdBy,
            ]);
        });
    }

    private function signedQuantity(InventoryMovementType $type, float $quantity): float
    {
        return match ($type) {
            InventoryMovementType::OPENING_BALANCE,
            InventoryMovementType::PURCHASE,
            InventoryMovementType::SALE_RETURN,
            InventoryMovementType::ADJUSTMENT_IN => $quantity,
            InventoryMovementType::PURCHASE_RETURN,
            InventoryMovementType::SALE,
            InventoryMovementType::ADJUSTMENT_OUT => -$quantity,
        };
    }
}
