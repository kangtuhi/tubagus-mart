<?php

use App\Enums\SupplierInvoiceStatus;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\Payables\SupplierInvoiceService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function supplierInvoiceForService(array $attributes = []): SupplierInvoice
{
    return SupplierInvoice::create(array_merge([
        'supplier_id' => Supplier::factory()->create()->id,
        'number' => 'INV-'.fake()->unique()->numerify('######'),
        'invoice_date' => '2026-08-29',
        'subtotal' => 10000,
        'discount_amount' => 500,
        'tax_amount' => 950,
        'grand_total' => 10450,
        'paid_amount' => 0,
        'status' => SupplierInvoiceStatus::DRAFT,
    ], $attributes));
}

test('draft supplier invoice can be posted', function () {
    $invoice = supplierInvoiceForService();

    $result = app(SupplierInvoiceService::class)->post($invoice);

    expect($result->status)->toBe(SupplierInvoiceStatus::POSTED);
});

test('supplier invoice with inconsistent grand total cannot be posted', function () {
    $invoice = supplierInvoiceForService(['grand_total' => 1]);

    expect(fn () => app(SupplierInvoiceService::class)->post($invoice))
        ->toThrow(DomainException::class, 'Supplier invoice grand total does not match its financial components.');
});

test('supplier invoice payment changes status and outstanding balance', function () {
    $invoice = supplierInvoiceForService();
    $service = app(SupplierInvoiceService::class);
    $service->post($invoice);

    $result = $service->recordPayment($invoice, 4000);

    expect($result->status)->toBe(SupplierInvoiceStatus::PARTIALLY_PAID)
        ->and($result->paid_amount)->toBe('4000.00')
        ->and($service->outstandingBalance($result))->toBe(6450.0);
});

test('final supplier invoice payment marks invoice paid', function () {
    $invoice = supplierInvoiceForService();
    $service = app(SupplierInvoiceService::class);
    $service->post($invoice);
    $service->recordPayment($invoice, 4000);

    $result = $service->recordPayment($invoice, 6450);

    expect($result->status)->toBe(SupplierInvoiceStatus::PAID)
        ->and($result->paid_amount)->toBe('10450.00')
        ->and($service->outstandingBalance($result))->toBe(0.0);
});

test('payment cannot exceed outstanding balance', function () {
    $invoice = supplierInvoiceForService();
    $service = app(SupplierInvoiceService::class);
    $service->post($invoice);

    expect(fn () => $service->recordPayment($invoice, 10450.01))
        ->toThrow(DomainException::class, 'Payment amount cannot exceed the outstanding supplier invoice balance.');
});

test('paid supplier invoice cannot be voided', function () {
    $invoice = supplierInvoiceForService();
    $service = app(SupplierInvoiceService::class);
    $service->post($invoice);
    $service->recordPayment($invoice, 10450);

    expect(fn () => $service->void($invoice))
        ->toThrow(DomainException::class, 'Paid supplier invoices cannot be voided.');
});
