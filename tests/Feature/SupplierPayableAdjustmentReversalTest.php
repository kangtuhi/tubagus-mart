<?php

use App\Enums\SupplierInvoiceStatus;
use App\Enums\SupplierPayableAdjustmentType;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\Payables\SupplierPayableAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('adjustment reversal creates an opposite compensating ledger entry and preserves the original', function () {
    $supplier = Supplier::factory()->create();
    $invoice = SupplierInvoice::factory()->for($supplier)->posted()->create([
        'grand_total' => 1000,
        'paid_amount' => 0,
    ]);

    $service = app(SupplierPayableAdjustmentService::class);
    $adjustment = $service->record(
        $invoice,
        SupplierPayableAdjustmentType::CREDIT,
        200,
        'CN-REV-001',
        'Supplier rebate',
    );

    $reversal = $service->reverse(
        $adjustment,
        'RV-REV-001',
        'Original credit note entered in error',
    );

    $invoice->refresh();
    $adjustment->refresh();

    expect($reversal->type)->toBe(SupplierPayableAdjustmentType::DEBIT)
        ->and((float) $reversal->amount)->toBe(200.0)
        ->and($reversal->reversal_of_id)->toBe($adjustment->id)
        ->and($adjustment->isReversed())->toBeTrue()
        ->and($adjustment->reversal_reason)->toBe('Original credit note entered in error')
        ->and($adjustment->reversal()->is($reversal))->toBeTrue()
        ->and($service->balance($invoice))->toBe(1000.0)
        ->and($invoice->status)->toBe(SupplierInvoiceStatus::POSTED);
});

test('reversed adjustment cannot be reversed twice', function () {
    $supplier = Supplier::factory()->create();
    $invoice = SupplierInvoice::factory()->for($supplier)->posted()->create([
        'grand_total' => 1000,
    ]);

    $service = app(SupplierPayableAdjustmentService::class);
    $adjustment = $service->record(
        $invoice,
        SupplierPayableAdjustmentType::DEBIT,
        100,
        'DN-REV-001',
        'Freight correction',
    );

    $service->reverse($adjustment, 'RV-REV-002', 'Correction');

    expect(fn () => $service->reverse(
        $adjustment->refresh(),
        'RV-REV-003',
        'Second correction',
    ))->toThrow(ValidationException::class);

    expect($invoice->adjustments()->count())->toBe(2);
});

test('a reversal adjustment cannot itself be reversed', function () {
    $supplier = Supplier::factory()->create();
    $invoice = SupplierInvoice::factory()->for($supplier)->posted()->create([
        'grand_total' => 1000,
    ]);

    $service = app(SupplierPayableAdjustmentService::class);
    $adjustment = $service->record(
        $invoice,
        SupplierPayableAdjustmentType::CREDIT,
        100,
        'CN-REV-002',
        'Supplier rebate',
    );
    $reversal = $service->reverse($adjustment, 'RV-REV-004', 'Entered in error');

    expect(fn () => $service->reverse(
        $reversal,
        'RV-REV-005',
        'Attempted reversal of reversal',
    ))->toThrow(ValidationException::class);

    expect($invoice->adjustments()->count())->toBe(2);
});

test('reversing a settling credit reopens a paid invoice to partially paid', function () {
    $supplier = Supplier::factory()->create();
    $invoice = SupplierInvoice::factory()->for($supplier)->posted()->create([
        'grand_total' => 1000,
        'paid_amount' => 800,
    ]);

    $service = app(SupplierPayableAdjustmentService::class);
    $adjustment = $service->record(
        $invoice,
        SupplierPayableAdjustmentType::CREDIT,
        200,
        'CN-REV-003',
        'Final credit',
    );

    expect($invoice->refresh()->status)->toBe(SupplierInvoiceStatus::PAID);

    $service->reverse($adjustment, 'RV-REV-006', 'Credit was not authorized');

    expect($invoice->refresh()->status)->toBe(SupplierInvoiceStatus::PARTIALLY_PAID)
        ->and($service->balance($invoice))->toBe(200.0);
});

test('reversal number must be unique', function () {
    $supplier = Supplier::factory()->create();
    $invoice = SupplierInvoice::factory()->for($supplier)->posted()->create([
        'grand_total' => 1000,
    ]);

    $service = app(SupplierPayableAdjustmentService::class);
    $first = $service->record(
        $invoice,
        SupplierPayableAdjustmentType::DEBIT,
        100,
        'DN-REV-004',
        'Freight',
    );

    $second = $service->record(
        $invoice,
        SupplierPayableAdjustmentType::DEBIT,
        50,
        'DN-REV-005',
        'Handling',
    );

    $service->reverse($first, 'RV-REV-007', 'Correction');

    expect(fn () => $service->reverse(
        $second,
        'RV-REV-007',
        'Duplicate reversal number',
    ))->toThrow(ValidationException::class);
});
