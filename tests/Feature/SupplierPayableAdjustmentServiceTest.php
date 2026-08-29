<?php

use App\Enums\SupplierInvoiceStatus;
use App\Enums\SupplierPayableAdjustmentType;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\Payables\SupplierPayableAdjustmentService;
use App\Services\Payables\SupplierPayablePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('credit adjustment reduces outstanding payable without changing original invoice total', function () {
    $supplier = Supplier::factory()->create();
    $invoice = SupplierInvoice::factory()->for($supplier)->posted()->create([
        'grand_total' => 1000,
        'paid_amount' => 0,
    ]);

    $adjustment = app(SupplierPayableAdjustmentService::class)->record(
        $invoice,
        SupplierPayableAdjustmentType::CREDIT,
        150,
        'CN-001',
        'Damaged goods credit note',
    );

    $invoice->refresh();

    expect($adjustment->type)->toBe(SupplierPayableAdjustmentType::CREDIT)
        ->and((float) $adjustment->amount)->toBe(150.0)
        ->and((float) $invoice->grand_total)->toBe(1000.0)
        ->and(app(SupplierPayableAdjustmentService::class)->balance($invoice))->toBe(850.0)
        ->and($invoice->status)->toBe(SupplierInvoiceStatus::PARTIALLY_PAID);
});

test('debit adjustment increases outstanding payable and preserves posted status', function () {
    $supplier = Supplier::factory()->create();
    $invoice = SupplierInvoice::factory()->for($supplier)->posted()->create([
        'grand_total' => 1000,
        'paid_amount' => 0,
    ]);

    app(SupplierPayableAdjustmentService::class)->record(
        $invoice,
        SupplierPayableAdjustmentType::DEBIT,
        125,
        'DN-001',
        'Additional freight charge',
    );

    $invoice->refresh();

    expect(app(SupplierPayableAdjustmentService::class)->balance($invoice))->toBe(1125.0)
        ->and($invoice->status)->toBe(SupplierInvoiceStatus::POSTED);
});

test('exact credit adjustment settles the invoice without altering invoice total', function () {
    $supplier = Supplier::factory()->create();
    $invoice = SupplierInvoice::factory()->for($supplier)->posted()->create([
        'grand_total' => 1000,
        'paid_amount' => 250,
    ]);

    app(SupplierPayableAdjustmentService::class)->record(
        $invoice,
        SupplierPayableAdjustmentType::CREDIT,
        750,
        'CN-002',
        'Final supplier credit',
    );

    $invoice->refresh();

    expect((float) $invoice->grand_total)->toBe(1000.0)
        ->and((float) $invoice->paid_amount)->toBe(250.0)
        ->and($invoice->status)->toBe(SupplierInvoiceStatus::PAID)
        ->and(app(SupplierPayableAdjustmentService::class)->balance($invoice))->toBe(0.0);
});

test('credit adjustment cannot exceed outstanding balance', function () {
    $supplier = Supplier::factory()->create();
    $invoice = SupplierInvoice::factory()->for($supplier)->posted()->create([
        'grand_total' => 1000,
        'paid_amount' => 400,
    ]);

    expect(fn () => app(SupplierPayableAdjustmentService::class)->record(
        $invoice,
        SupplierPayableAdjustmentType::CREDIT,
        601,
        'CN-003',
        'Excess credit',
    ))->toThrow(ValidationException::class);

    expect($invoice->adjustments()->count())->toBe(0)
        ->and($invoice->refresh()->status)->toBe(SupplierInvoiceStatus::POSTED);
});

test('adjustment number must be unique', function () {
    $supplier = Supplier::factory()->create();
    $invoice = SupplierInvoice::factory()->for($supplier)->posted()->create([
        'grand_total' => 2000,
    ]);

    $service = app(SupplierPayableAdjustmentService::class);
    $service->record($invoice, SupplierPayableAdjustmentType::DEBIT, 100, 'ADJ-001', 'Freight');

    expect(fn () => $service->record(
        $invoice,
        SupplierPayableAdjustmentType::DEBIT,
        50,
        'ADJ-001',
        'Second charge',
    ))->toThrow(ValidationException::class);
});

test('paid invoices cannot receive adjustments', function () {
    $supplier = Supplier::factory()->create();
    $invoice = SupplierInvoice::factory()->for($supplier)->paid()->create([
        'grand_total' => 1000,
        'paid_amount' => 1000,
    ]);

    expect(fn () => app(SupplierPayableAdjustmentService::class)->record(
        $invoice,
        SupplierPayableAdjustmentType::DEBIT,
        100,
        'ADJ-PAID',
        'Late charge',
    ))->toThrow(ValidationException::class);
});

test('adjusted balance is used when recording a later payment', function () {
    $supplier = Supplier::factory()->create();
    $invoice = SupplierInvoice::factory()->for($supplier)->posted()->create([
        'grand_total' => 1000,
        'paid_amount' => 0,
    ]);

    app(SupplierPayableAdjustmentService::class)->record(
        $invoice,
        SupplierPayableAdjustmentType::CREDIT,
        200,
        'CN-004',
        'Supplier rebate',
    );

    app(SupplierPayablePaymentService::class)->record(
        $invoice->refresh(),
        800,
        'PAY-ADJ-001',
    );

    expect($invoice->refresh()->status)->toBe(SupplierInvoiceStatus::PAID)
        ->and((float) $invoice->paid_amount)->toBe(800.0)
        ->and(app(SupplierPayablePaymentService::class)->outstanding($invoice))->toBe(0.0);
});
