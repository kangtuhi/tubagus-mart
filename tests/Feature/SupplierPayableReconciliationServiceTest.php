<?php

declare(strict_types=1);

use App\Enums\SupplierInvoiceStatus;
use App\Enums\SupplierPayableAdjustmentType;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\Payables\SupplierPayableAdjustmentService;
use App\Services\Payables\SupplierPayablePaymentService;
use App\Services\Payables\SupplierPayableReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('payable reconciliation summarizes invoice and paid totals per supplier', function () {
    $supplierA = Supplier::factory()->create(['code' => 'SUP-A', 'name' => 'Supplier A']);
    $supplierB = Supplier::factory()->create(['code' => 'SUP-B', 'name' => 'Supplier B']);

    SupplierInvoice::factory()->for($supplierA)->posted()->create(['grand_total' => 1000, 'paid_amount' => 250]);
    SupplierInvoice::factory()->for($supplierA)->paid()->create(['grand_total' => 500, 'paid_amount' => 500]);
    SupplierInvoice::factory()->for($supplierB)->posted()->create(['grand_total' => 2000, 'paid_amount' => 0]);
    SupplierInvoice::factory()->for($supplierB)->void()->create(['grand_total' => 9000, 'paid_amount' => 0]);

    $result = app(SupplierPayableReconciliationService::class)->reconcile();

    expect($result)->toHaveCount(2)
        ->and($result->firstWhere('supplier_id', $supplierA->id))->toMatchArray([
            'supplier_id' => $supplierA->id,
            'supplier_code' => 'SUP-A',
            'supplier_name' => 'Supplier A',
            'invoice_count' => 2,
            'invoice_total' => 1500.0,
            'paid_total' => 750.0,
            'outstanding' => 750.0,
            'is_reconciled' => false,
        ])
        ->and($result->firstWhere('supplier_id', $supplierB->id))->toMatchArray([
            'supplier_id' => $supplierB->id,
            'supplier_code' => 'SUP-B',
            'supplier_name' => 'Supplier B',
            'invoice_count' => 1,
            'invoice_total' => 2000.0,
            'paid_total' => 0.0,
            'outstanding' => 2000.0,
            'is_reconciled' => false,
        ]);
});

test('payable reconciliation marks fully paid supplier as reconciled', function () {
    $supplier = Supplier::factory()->create();
    SupplierInvoice::factory()->for($supplier)->paid()->create(['grand_total' => 1250, 'paid_amount' => 1250]);

    $result = app(SupplierPayableReconciliationService::class)->supplier($supplier->id);

    expect($result)->not->toBeNull()
        ->and($result['invoice_total'])->toBe(1250.0)
        ->and($result['paid_total'])->toBe(1250.0)
        ->and($result['outstanding'])->toBe(0.0)
        ->and($result['is_reconciled'])->toBeTrue();
});

test('payable reconciliation can be scoped to one supplier', function () {
    $supplierA = Supplier::factory()->create();
    $supplierB = Supplier::factory()->create();

    SupplierInvoice::factory()->for($supplierA)->posted()->create(['grand_total' => 800, 'paid_amount' => 100]);
    SupplierInvoice::factory()->for($supplierB)->posted()->create(['grand_total' => 2000, 'paid_amount' => 500]);

    $result = app(SupplierPayableReconciliationService::class)->reconcile($supplierA->id);

    expect($result)->toHaveCount(1)
        ->and($result->first())->toMatchArray([
            'supplier_id' => $supplierA->id,
            'invoice_total' => 800.0,
            'paid_total' => 100.0,
            'outstanding' => 700.0,
        ]);
});

test('payable reconciliation ignores draft and void invoices', function () {
    $supplier = Supplier::factory()->create();
    SupplierInvoice::factory()->for($supplier)->create(['status' => SupplierInvoiceStatus::DRAFT, 'grand_total' => 1000]);
    SupplierInvoice::factory()->for($supplier)->void()->create(['grand_total' => 2000]);

    expect(app(SupplierPayableReconciliationService::class)->reconcile())->toHaveCount(0);
});

test('payable reconciliation never reports a negative outstanding balance', function () {
    $supplier = Supplier::factory()->create();
    SupplierInvoice::factory()->for($supplier)->posted()->create(['grand_total' => 1000, 'paid_amount' => 1000]);

    $result = app(SupplierPayableReconciliationService::class)->supplier($supplier->id);

    expect($result['outstanding'])->toBe(0.0)
        ->and($result['is_reconciled'])->toBeTrue();
});

test('payable reconciliation matches the supplier statement when payment ledger is consistent', function () {
    $supplier = Supplier::factory()->create();
    $invoice = SupplierInvoice::factory()->for($supplier)->posted()->create(['grand_total' => 1000]);

    app(SupplierPayablePaymentService::class)->record($invoice, 250, 'PAY-RECON-001');

    $result = app(SupplierPayableReconciliationService::class)->supplier($supplier->id);

    expect($result['statement_balance'])->toBe(750.0)
        ->and($result['operational_balance'])->toBe(750.0)
        ->and($result['reconciliation_difference'])->toBe(0.0)
        ->and($result['is_statement_reconciled'])->toBeTrue()
        ->and($result['reconciliation_status'])->toBe('matched');
});

test('payable reconciliation detects a payment ledger mismatch without mutating transactions', function () {
    $supplier = Supplier::factory()->create();
    $invoice = SupplierInvoice::factory()->for($supplier)->posted()->create(['grand_total' => 1000, 'paid_amount' => 250]);

    SupplierInvoice::query()->whereKey($invoice->id)->update(['paid_amount' => 300]);

    $result = app(SupplierPayableReconciliationService::class)->supplier($supplier->id);

    expect($result['statement_balance'])->toBe(1000.0)
        ->and($result['operational_balance'])->toBe(700.0)
        ->and($result['reconciliation_difference'])->toBe(300.0)
        ->and($result['is_statement_reconciled'])->toBeFalse()
        ->and($result['reconciliation_status'])->toBe('discrepancy')
        ->and($invoice->refresh()->paid_amount)->toBe('300.00');
});

test('payable reconciliation remains matched across adjustment and reversal audit entries', function () {
    $supplier = Supplier::factory()->create();
    $invoice = SupplierInvoice::factory()->for($supplier)->posted()->create(['grand_total' => 1000]);

    $adjustment = app(SupplierPayableAdjustmentService::class)->record(
        $invoice,
        SupplierPayableAdjustmentType::CREDIT,
        200,
        'CN-RECON-001',
        'Incorrect rebate',
    );

    app(SupplierPayableAdjustmentService::class)->reverse($adjustment, 'RV-RECON-001', 'Rebate was not authorized');

    $result = app(SupplierPayableReconciliationService::class)->supplier($supplier->id);

    expect($result['statement_balance'])->toBe(1000.0)
        ->and($result['operational_balance'])->toBe(1000.0)
        ->and($result['reconciliation_difference'])->toBe(0.0)
        ->and($result['is_statement_reconciled'])->toBeTrue()
        ->and($result['reconciliation_status'])->toBe('matched');
});
