<?php

use App\Enums\SupplierInvoiceStatus;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Services\Payables\SupplierPayablePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('payment service records a partial payment and updates invoice balance', function () {
    $invoice = SupplierInvoice::factory()->for(Supplier::factory())->posted()->create([
        'grand_total' => 1000,
        'paid_amount' => 0,
    ]);

    $payment = app(SupplierPayablePaymentService::class)->record(
        invoice: $invoice,
        amount: 250,
        paymentNumber: 'PAY-001',
    );

    $invoice->refresh();

    expect($payment)->toBeInstanceOf(SupplierPayment::class)
        ->and((float) $payment->amount)->toBe(250.0)
        ->and((float) $invoice->paid_amount)->toBe(250.0)
        ->and($invoice->status)->toBe(SupplierInvoiceStatus::PARTIALLY_PAID)
        ->and((float) $invoice->grand_total - (float) $invoice->paid_amount)->toBe(750.0);
});

test('payment service marks invoice paid after exact final payment', function () {
    $invoice = SupplierInvoice::factory()->for(Supplier::factory())->partiallyPaid(750)->create([
        'grand_total' => 1000,
        'paid_amount' => 750,
    ]);

    app(SupplierPayablePaymentService::class)->record(
        invoice: $invoice,
        amount: 250,
        paymentNumber: 'PAY-002',
    );

    $invoice->refresh();

    expect((float) $invoice->paid_amount)->toBe(1000.0)
        ->and($invoice->status)->toBe(SupplierInvoiceStatus::PAID)
        ->and($invoice->payments()->count())->toBe(1);
});

test('payment service rejects overpayment', function () {
    $invoice = SupplierInvoice::factory()->for(Supplier::factory())->posted()->create([
        'grand_total' => 1000,
        'paid_amount' => 600,
    ]);

    expect(fn () => app(SupplierPayablePaymentService::class)->record(
        invoice: $invoice,
        amount: 400.01,
        paymentNumber: 'PAY-OVER',
    ))->toThrow(ValidationException::class);

    $invoice->refresh();

    expect((float) $invoice->paid_amount)->toBe(600.0)
        ->and($invoice->payments()->count())->toBe(0);
});

test('payment service rejects zero and negative payments', function () {
    $invoice = SupplierInvoice::factory()->for(Supplier::factory())->posted()->create([
        'grand_total' => 1000,
    ]);

    expect(fn () => app(SupplierPayablePaymentService::class)->record($invoice, 0, 'PAY-ZERO'))
        ->toThrow(ValidationException::class);
    expect(fn () => app(SupplierPayablePaymentService::class)->record($invoice, -1, 'PAY-NEG'))
        ->toThrow(ValidationException::class);
});

test('payment service rejects payments for invalid invoice states', function () {
    $draft = SupplierInvoice::factory()->for(Supplier::factory())->create([
        'grand_total' => 1000,
    ]);
    $void = SupplierInvoice::factory()->for(Supplier::factory())->void()->create([
        'grand_total' => 1000,
    ]);
    $paid = SupplierInvoice::factory()->for(Supplier::factory())->paid()->create([
        'grand_total' => 1000,
    ]);

    foreach ([$draft, $void, $paid] as $invoice) {
        expect(fn () => app(SupplierPayablePaymentService::class)->record($invoice, 100, 'PAY-'.str()->random(8)))
            ->toThrow(ValidationException::class);
    }
});

test('payment service rejects duplicate payment numbers', function () {
    $supplier = Supplier::factory()->create();
    $invoiceA = SupplierInvoice::factory()->for($supplier)->posted()->create(['grand_total' => 1000]);
    $invoiceB = SupplierInvoice::factory()->for($supplier)->posted()->create(['grand_total' => 1000]);

    app(SupplierPayablePaymentService::class)->record($invoiceA, 100, 'PAY-DUP');

    expect(fn () => app(SupplierPayablePaymentService::class)->record($invoiceB, 100, 'PAY-DUP'))
        ->toThrow(ValidationException::class);
});

test('payment service supports multiple sequential payments without exceeding balance', function () {
    $invoice = SupplierInvoice::factory()->for(Supplier::factory())->posted()->create([
        'grand_total' => 1000,
        'paid_amount' => 0,
    ]);

    $service = app(SupplierPayablePaymentService::class);
    $service->record($invoice, 300, 'PAY-MULTI-1');
    $service->record($invoice, 450, 'PAY-MULTI-2');
    $service->record($invoice, 250, 'PAY-MULTI-3');

    $invoice->refresh();

    expect((float) $invoice->paid_amount)->toBe(1000.0)
        ->and($invoice->status)->toBe(SupplierInvoiceStatus::PAID)
        ->and($invoice->payments()->count())->toBe(3)
        ->and((float) $invoice->payments()->sum('amount'))->toBe(1000.0);
});

test('payment service rolls back payment when invoice validation fails', function () {
    $invoice = SupplierInvoice::factory()->for(Supplier::factory())->posted()->create([
        'grand_total' => 500,
        'paid_amount' => 500,
        'status' => SupplierInvoiceStatus::PAID,
    ]);

    try {
        app(SupplierPayablePaymentService::class)->record($invoice, 100, 'PAY-ROLLBACK');
    } catch (ValidationException) {
        // Expected validation failure.
    }

    expect(SupplierPayment::query()->where('payment_number', 'PAY-ROLLBACK')->exists())->toBeFalse();
});
