<?php

namespace Database\Factories;

use App\Enums\SupplierInvoiceStatus;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierInvoice>
 */
class SupplierInvoiceFactory extends Factory
{
    protected $model = SupplierInvoice::class;

    public function definition(): array
    {
        $invoiceDate = fake()->dateTimeBetween('-60 days', 'today');
        $subtotal = fake()->randomFloat(2, 100, 10000);

        return [
            'supplier_id' => Supplier::factory(),
            'purchase_order_id' => null,
            'number' => 'INV-'.fake()->unique()->numerify('########'),
            'invoice_date' => $invoiceDate,
            'due_date' => fake()->dateTimeBetween($invoiceDate, '+60 days'),
            'status' => SupplierInvoiceStatus::DRAFT,
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'grand_total' => $subtotal,
            'paid_amount' => 0,
            'notes' => null,
        ];
    }

    public function posted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SupplierInvoiceStatus::POSTED,
            'paid_amount' => 0,
        ]);
    }

    public function partiallyPaid(float $paidAmount = 100): static
    {
        return $this->state(function (array $attributes) use ($paidAmount) {
            $grandTotal = (float) $attributes['grand_total'];

            return [
                'status' => SupplierInvoiceStatus::PARTIALLY_PAID,
                'paid_amount' => min(max(0, $paidAmount), max(0, $grandTotal - 0.01)),
            ];
        });
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SupplierInvoiceStatus::PAID,
            'paid_amount' => $attributes['grand_total'],
        ]);
    }

    public function void(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SupplierInvoiceStatus::VOID,
        ]);
    }

    public function overdue(int $days = 30): static
    {
        return $this->state(function () use ($days) {
            $dueDate = now()->subDays($days);

            return [
                'invoice_date' => $dueDate->copy()->subDays(30),
                'due_date' => $dueDate,
            ];
        });
    }

    public function dueIn(int $days = 30): static
    {
        return $this->state(function () use ($days) {
            $dueDate = now()->addDays($days);

            return [
                'invoice_date' => now(),
                'due_date' => $dueDate,
            ];
        });
    }
}
