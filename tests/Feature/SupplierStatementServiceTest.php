<?php

use App\Enums\SupplierPayableAdjustmentType;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\Payables\SupplierPayableAdjustmentService;
use App\Services\Payables\SupplierPayablePaymentService;
use App\Services\Payables\SupplierStatementService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('supplier statement produces a chronological AP ledger with a running balance', function () {
    $supplier = Supplier::factory()->create();
    $invoice = SupplierInvoice::factory()->for($supplier)->posted()->create([
        'number' => 'INV-STAT-001',
        'invoice_date' => Carbon::parse('2026-08-01'),
        'grand_total' => 1000,
        'paid_amount' => 0,
    ]);

    app(SupplierPayablePaymentService::class)->record(
        $invoice,
        250,
        'PAY-STAT-001',
        Carbon::parse('2026-08-05'),
    );

    app(SupplierPayableAdjustmentService::class)->record(
        $invoice->refresh(),
        SupplierPayableAdjustmentType::CREDIT,
        100,
        'CN-STAT-001',
        'Supplier rebate',
        Carbon::parse('2026-08-10'),
    );

    $statement = app(SupplierStatementService::class)->statement($supplier->id);

    expect($statement['opening_balance'])->toBe(0.0)
        ->and($statement['debit_total'])->toBe(1000.0)
        ->and($statement['credit_total'])->toBe(350.0)
        ->and($statement['closing_balance'])->toBe(650.0)
        ->and($statement['entries'])->toHaveCount(3)
        ->and($statement['entries']->pluck('type')->all())->toBe([
            'invoice',
            'payment',
            'adjustment',
        ])
        ->and($statement['entries']->pluck('running_balance')->all())->toBe([
            1000.0,
            750.0,
            650.0,
        ]);
});

test('supplier statement calculates opening balance before the reporting period', function () {
    $supplier = Supplier::factory()->create();

    SupplierInvoice::factory()->for($supplier)->posted()->create([
        'number' => 'INV-STAT-002',
        'invoice_date' => Carbon::parse('2026-07-15'),
        'grand_total' => 500,
        'paid_amount' => 0,
    ]);
    SupplierInvoice::factory()->for($supplier)->posted()->create([
        'number' => 'INV-STAT-003',
        'invoice_date' => Carbon::parse('2026-08-15'),
        'grand_total' => 800,
        'paid_amount' => 0,
    ]);

    $statement = app(SupplierStatementService::class)->statement(
        $supplier->id,
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-31 23:59:59'),
    );

    expect($statement['opening_balance'])->toBe(500.0)
        ->and($statement['entries'])->toHaveCount(1)
        ->and($statement['entries']->first()['reference'])->toBe('INV-STAT-003')
        ->and($statement['entries']->first()['running_balance'])->toBe(1300.0)
        ->and($statement['closing_balance'])->toBe(1300.0);
});

test('supplier statement preserves adjustment reversals as separate audit entries', function () {
    $supplier = Supplier::factory()->create();
    $invoice = SupplierInvoice::factory()->for($supplier)->posted()->create([
        'number' => 'INV-STAT-004',
        'invoice_date' => Carbon::parse('2026-08-01'),
        'grand_total' => 1000,
    ]);

    $adjustment = app(SupplierPayableAdjustmentService::class)->record(
        $invoice,
        SupplierPayableAdjustmentType::CREDIT,
        200,
        'CN-STAT-002',
        'Incorrect rebate',
        Carbon::parse('2026-08-05'),
    );

    app(SupplierPayableAdjustmentService::class)->reverse(
        $adjustment,
        'RV-STAT-001',
        'Rebate was not authorized',
        null,
        Carbon::parse('2026-08-06'),
    );

    $statement = app(SupplierStatementService::class)->statement($supplier->id);

    expect($statement['entries']->pluck('type')->all())->toBe([
        'invoice',
        'adjustment',
        'adjustment_reversal',
    ])
        ->and($statement['entries']->pluck('signed_amount')->all())->toBe([
            1000.0,
            -200.0,
            200.0,
        ])
        ->and($statement['closing_balance'])->toBe(1000.0);
});

test('supplier statement ignores draft and void invoices', function () {
    $supplier = Supplier::factory()->create();

    SupplierInvoice::factory()->for($supplier)->create([
        'status' => \App\Enums\SupplierInvoiceStatus::DRAFT,
        'number' => 'INV-STAT-005',
        'invoice_date' => Carbon::parse('2026-08-01'),
        'grand_total' => 500,
    ]);
    SupplierInvoice::factory()->for($supplier)->void()->create([
        'number' => 'INV-STAT-006',
        'invoice_date' => Carbon::parse('2026-08-02'),
        'grand_total' => 700,
    ]);

    $statement = app(SupplierStatementService::class)->statement($supplier->id);

    expect($statement['entries'])->toBeEmpty()
        ->and($statement['closing_balance'])->toBe(0.0);
});
