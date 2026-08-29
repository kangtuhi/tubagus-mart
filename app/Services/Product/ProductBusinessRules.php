<?php

namespace App\Services\Product;

use App\Enums\ProductType;
use App\Models\Product;
use DomainException;

class ProductBusinessRules
{
    public function validate(Product $product): void
    {
        $this->validateTypeAndStockTracking($product);
        $this->validatePrices($product);
        $this->validateTax($product);
        $this->validateUnit($product);
    }

    private function validateTypeAndStockTracking(Product $product): void
    {
        if ($product->product_type === ProductType::SERVICE && $product->track_stock) {
            throw new DomainException('Service products cannot track stock.');
        }
    }

    private function validatePrices(Product $product): void
    {
        if ($product->cost_price < 0 || $product->selling_price < 0) {
            throw new DomainException('Product prices cannot be negative.');
        }

        if ($product->selling_price < $product->cost_price) {
            throw new DomainException('Selling price cannot be lower than cost price.');
        }
    }

    private function validateTax(Product $product): void
    {
        if ($product->tax_rate < 0 || $product->tax_rate > 100) {
            throw new DomainException('Tax rate must be between 0 and 100.');
        }

        if (! $product->is_taxable && $product->tax_rate != 0.0) {
            throw new DomainException('A non-taxable product must have a zero tax rate.');
        }
    }

    private function validateUnit(Product $product): void
    {
        if (! $product->baseUnit) {
            throw new DomainException('A product must have a base unit.');
        }
    }
}
