<?php

use App\Enums\SupplierInvoiceStatus;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\Payables\SupplierPayableReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('payable reconciliation summarizes invoice and paid totals per supplier', function () {
    $supplierA = Supplier::factory()->create([
        'code' => 'SUP-A',
        'name' => 'Supplier A',
    ]);
    $supplierB = Supplier::factory()->create([
        'code' => 'SUP-B',
        'name' => 'Supplier B',
    ]);

    SupplierInvoice::factory()->for($supplierA)->posted()->create([
        'grand_total' => 1000,
        'paid_amount' => 250,
    ]);
    SupplierInvoice::factory()->for($supplierA)->paid()->create([
        'grand_total' => 500,
        'paid_amount' => 500,
    ]);
    SupplierInvoice::factory()->for($supplierB)->posted()->create([
        'grand_total' => 2000,
        'paid_amount' => 0,
    ]);
    SupplierInvoice::factory()->for($supplierB)->void()->create([
        'grand_total' => 9000,
        'paid_amount' => 0,
    ]);

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

    SupplierInvoice::factory()->for($supplier)->paid()->create([
        'grand_total' => 1250,
        'paid_amount' => 1250,
    ]);

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

    SupplierInvoice::factory()->for($supplierA)->posted()->create([
        'grand_total' => 800,
        'paid_amount' => 100,
    ]);
    SupplierInvoice::factory()->for($supplierB)->posted()->create([
        'grand_total' => 2000,
        'paid_amount' => 500,
    ]);

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

    SupplierInvoice::factory()->for($supplier)->create([
        'status' => SupplierInvoiceStatus::DRAFT,
        'grand_total' => 1000,
    ]);
    SupplierInvoice::factory()->for($supplier)->void()->create([
        'grand_total' => 2000,
    ]);

    expect(app(SupplierPayableReconciliationService::class)->reconcile())->toHaveCount(0);
});

test('payable reconciliation never reports a negative outstanding balance', function () {
    $supplier = Supplier::factory()->create();

    SupplierInvoice::factory()->for($supplier)->posted()->create([
        'grand_total' => 1000,
        'paid_amount' => 1000,
    ]);

    $result = app(SupplierPayableReconciliationService::class)->supplier($supplier->id);

    expect($result['outstanding'])->toBe(0.0)
        ->and($result['is_reconciled'])->toBeTrue();
});
