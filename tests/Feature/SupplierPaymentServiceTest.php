<?php

use App\Enums\SupplierInvoiceStatus;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Payables\SupplierPaymentService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function postedSupplierInvoiceForPayment(): SupplierInvoice
{
    return SupplierInvoice::factory()->posted()->create([
        'supplier_id' => Supplier::factory()->create()->id,
        'invoice_date' => '2026-08-29',
        'due_date' => '2026-09-28',
        'subtotal' => 10000,
        'discount_amount' => 500,
        'tax_amount' => 950,
        'grand_total' => 10450,
        'paid_amount' => 0,
    ]);
}

test('recording payments creates ledger entries and marks invoice paid', function () {
    $invoice = postedSupplierInvoiceForPayment();
    $service = app(SupplierPaymentService::class);
    $user = User::factory()->create();

    $first = $service->record($invoice, 'PAY-0001', 4000, $user->id, 'BANK-001');
    $second = $service->record($invoice, 'PAY-0002', 6450, $user->id, 'BANK-002');

    expect($first)->toBeInstanceOf(SupplierPayment::class)
        ->and($second)->toBeInstanceOf(SupplierPayment::class)
        ->and($invoice->refresh()->status)->toBe(SupplierInvoiceStatus::PAID)
        ->and((float) $invoice->paid_amount)->toBe(10450.0)
        ->and($service->outstandingBalance($invoice))->toBe(0.0)
        ->and($invoice->payments()->count())->toBe(2)
        ->and($invoice->payments()->sum('amount'))->toBe(10450.0);
});

test('payment cannot exceed outstanding balance', function () {
    $invoice = postedSupplierInvoiceForPayment();

    expect(fn () => app(SupplierPaymentService::class)->record($invoice, 'PAY-OVER', 10450.01))
        ->toThrow(DomainException::class, 'Payment amount cannot exceed the outstanding supplier invoice balance.')
        ->and(SupplierPayment::count())->toBe(0);
});

test('draft and void invoices cannot receive payments', function () {
    $draftInvoice = SupplierInvoice::factory()->create([
        'status' => SupplierInvoiceStatus::DRAFT,
        'grand_total' => 1000,
        'paid_amount' => 0,
    ]);

    expect(fn () => app(SupplierPaymentService::class)->record($draftInvoice, 'PAY-DRAFT', 100))
        ->toThrow(DomainException::class, 'Only posted or partially paid supplier invoices can receive payments.');

    $voidInvoice = SupplierInvoice::factory()->void()->create();

    expect(fn () => app(SupplierPaymentService::class)->record($voidInvoice, 'PAY-VOID', 100))
        ->toThrow(DomainException::class, 'Only posted or partially paid supplier invoices can receive payments.');
});
