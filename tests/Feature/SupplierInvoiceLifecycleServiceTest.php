<?php

use App\Enums\SupplierInvoiceStatus;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\Payables\SupplierInvoiceLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('invoice lifecycle allows the supported forward transitions', function () {
    $service = app(SupplierInvoiceLifecycleService::class);

    expect($service->canTransition(SupplierInvoiceStatus::DRAFT, SupplierInvoiceStatus::POSTED))->toBeTrue()
        ->and($service->canTransition(SupplierInvoiceStatus::DRAFT, SupplierInvoiceStatus::VOID))->toBeTrue()
        ->and($service->canTransition(SupplierInvoiceStatus::POSTED, SupplierInvoiceStatus::PARTIALLY_PAID))->toBeTrue()
        ->and($service->canTransition(SupplierInvoiceStatus::POSTED, SupplierInvoiceStatus::PAID))->toBeTrue()
        ->and($service->canTransition(SupplierInvoiceStatus::POSTED, SupplierInvoiceStatus::VOID))->toBeTrue()
        ->and($service->canTransition(SupplierInvoiceStatus::PARTIALLY_PAID, SupplierInvoiceStatus::PAID))->toBeTrue();
});

test('invoice lifecycle rejects invalid backward transitions', function () {
    $service = app(SupplierInvoiceLifecycleService::class);

    expect($service->canTransition(SupplierInvoiceStatus::POSTED, SupplierInvoiceStatus::DRAFT))->toBeFalse()
        ->and($service->canTransition(SupplierInvoiceStatus::PARTIALLY_PAID, SupplierInvoiceStatus::POSTED))->toBeFalse()
        ->and($service->canTransition(SupplierInvoiceStatus::PAID, SupplierInvoiceStatus::POSTED))->toBeFalse()
        ->and($service->canTransition(SupplierInvoiceStatus::PAID, SupplierInvoiceStatus::VOID))->toBeFalse()
        ->and($service->canTransition(SupplierInvoiceStatus::VOID, SupplierInvoiceStatus::POSTED))->toBeFalse()
        ->and($service->canTransition(SupplierInvoiceStatus::VOID, SupplierInvoiceStatus::DRAFT))->toBeFalse();
});

test('invoice lifecycle transition updates the invoice status', function () {
    $invoice = SupplierInvoice::factory()->for(Supplier::factory())->create([
        'status' => SupplierInvoiceStatus::DRAFT,
    ]);

    $result = app(SupplierInvoiceLifecycleService::class)->post($invoice);

    expect($result->status)->toBe(SupplierInvoiceStatus::POSTED)
        ->and($invoice->refresh()->status)->toBe(SupplierInvoiceStatus::POSTED);
});

test('invoice lifecycle rejects invalid transition with validation error', function () {
    $invoice = SupplierInvoice::factory()->for(Supplier::factory())->paid()->create();

    expect(fn () => app(SupplierInvoiceLifecycleService::class)->post($invoice))
        ->toThrow(ValidationException::class);

    expect($invoice->refresh()->status)->toBe(SupplierInvoiceStatus::PAID);
});

test('invoice lifecycle allows voiding an unpaid posted invoice', function () {
    $invoice = SupplierInvoice::factory()->for(Supplier::factory())->posted()->create([
        'paid_amount' => 0,
    ]);

    $result = app(SupplierInvoiceLifecycleService::class)->void($invoice);

    expect($result->status)->toBe(SupplierInvoiceStatus::VOID);
});

test('invoice lifecycle rejects voiding an invoice with payments', function () {
    $invoice = SupplierInvoice::factory()->for(Supplier::factory())->posted()->create([
        'grand_total' => 1000,
        'paid_amount' => 250,
    ]);

    $invoice->payments()->create([
        'payment_number' => 'PAY-LIFECYCLE-001',
        'paid_at' => now(),
        'amount' => 250,
    ]);

    expect(fn () => app(SupplierInvoiceLifecycleService::class)->void($invoice))
        ->toThrow(ValidationException::class);

    expect($invoice->refresh()->status)->toBe(SupplierInvoiceStatus::POSTED);
});

test('invoice lifecycle treats same status as a no-op', function () {
    $invoice = SupplierInvoice::factory()->for(Supplier::factory())->posted()->create();

    $result = app(SupplierInvoiceLifecycleService::class)->transition(
        $invoice,
        SupplierInvoiceStatus::POSTED,
    );

    expect($result->status)->toBe(SupplierInvoiceStatus::POSTED);
});
