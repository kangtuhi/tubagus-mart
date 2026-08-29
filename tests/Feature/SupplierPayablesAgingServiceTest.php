<?php

use App\Enums\SupplierInvoiceStatus;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\Payables\SupplierInvoiceService;
use App\Services\Payables\SupplierPayablesAgingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('payables aging classifies outstanding invoices into due date buckets', function () {
    $supplier = Supplier::factory()->create([
        'code' => 'SUP-AGING',
        'name' => 'Aging Supplier',
    ]);
    $invoiceService = app(SupplierInvoiceService::class);
    $asOf = now()->startOfDay();

    $createInvoice = function (string $number, int $daysOverdue, float $total) use ($supplier, $invoiceService): SupplierInvoice {
        $invoice = SupplierInvoice::create([
            'supplier_id' => $supplier->id,
            'number' => $number,
            'invoice_date' => now()->subDays(max(1, $daysOverdue))->toDateString(),
            'due_date' => now()->subDays($daysOverdue)->toDateString(),
            'subtotal' => $total,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'grand_total' => $total,
            'paid_amount' => 0,
            'status' => SupplierInvoiceStatus::DRAFT,
        ]);

        return $invoiceService->post($invoice);
    };

    $createInvoice('INV-CURRENT', 0, 1000);
    $createInvoice('INV-1-30', 10, 2000);
    $createInvoice('INV-31-60', 40, 3000);
    $createInvoice('INV-61-90', 70, 4000);
    $createInvoice('INV-91-PLUS', 100, 5000);

    $report = app(SupplierPayablesAgingService::class)->report($asOf);

    expect($report['total_outstanding'])->toBe(15000.0)
        ->and($report['buckets'])->toBe([
            'current' => 1000.0,
            '1_30' => 2000.0,
            '31_60' => 3000.0,
            '61_90' => 4000.0,
            '91_plus' => 5000.0,
        ])
        ->and($report['invoices'])->toHaveCount(5)
        ->and($report['suppliers']->first())->toMatchArray([
            'supplier_id' => $supplier->id,
            'supplier_code' => 'SUP-AGING',
            'supplier_name' => 'Aging Supplier',
            'outstanding' => 15000.0,
        ]);
});

test('payables aging excludes paid, draft, and void invoices', function () {
    $supplier = Supplier::factory()->create();
    $invoiceService = app(SupplierInvoiceService::class);

    $paidInvoice = SupplierInvoice::create([
        'supplier_id' => $supplier->id,
        'number' => 'INV-PAID-AGING',
        'invoice_date' => '2026-08-01',
        'due_date' => '2026-08-10',
        'subtotal' => 1000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'grand_total' => 1000,
        'paid_amount' => 1000,
        'status' => SupplierInvoiceStatus::PAID,
    ]);

    $draftInvoice = SupplierInvoice::create([
        'supplier_id' => $supplier->id,
        'number' => 'INV-DRAFT-AGING',
        'invoice_date' => '2026-08-01',
        'due_date' => '2026-08-10',
        'subtotal' => 2000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'grand_total' => 2000,
        'paid_amount' => 0,
        'status' => SupplierInvoiceStatus::DRAFT,
    ]);

    $voidInvoice = SupplierInvoice::create([
        'supplier_id' => $supplier->id,
        'number' => 'INV-VOID-AGING',
        'invoice_date' => '2026-08-01',
        'due_date' => '2026-08-10',
        'subtotal' => 3000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'grand_total' => 3000,
        'paid_amount' => 0,
        'status' => SupplierInvoiceStatus::DRAFT,
    ]);

    $invoiceService->void($voidInvoice);

    expect($paidInvoice->exists)->toBeTrue()
        ->and($draftInvoice->status)->toBe(SupplierInvoiceStatus::DRAFT)
        ->and($voidInvoice->refresh()->status)->toBe(SupplierInvoiceStatus::VOID)
        ->and(app(SupplierPayablesAgingService::class)->outstandingInvoices())->toHaveCount(0);
});

test('payables aging keeps invoices without due dates in current bucket', function () {
    $supplier = Supplier::factory()->create();
    $invoice = SupplierInvoice::create([
        'supplier_id' => $supplier->id,
        'number' => 'INV-NODUE-AGING',
        'invoice_date' => '2026-08-01',
        'due_date' => null,
        'subtotal' => 1250,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'grand_total' => 1250,
        'paid_amount' => 250,
        'status' => SupplierInvoiceStatus::PARTIALLY_PAID,
    ]);

    $report = app(SupplierPayablesAgingService::class)->report(now()->startOfDay());

    expect($report['total_outstanding'])->toBe(1000.0)
        ->and($report['buckets']['current'])->toBe(1000.0)
        ->and($report['invoices']->first()['invoice']->is($invoice))->toBeTrue()
        ->and($report['invoices']->first()['outstanding'])->toBe(1000.0);
});

test('supplier invoice factory provides useful payables lifecycle states', function () {
    $supplier = Supplier::factory()->create();

    $posted = SupplierInvoice::factory()->for($supplier)->posted()->create();
    $partial = SupplierInvoice::factory()->for($supplier)->partiallyPaid(250)->create();
    $paid = SupplierInvoice::factory()->for($supplier)->paid()->create();
    $void = SupplierInvoice::factory()->for($supplier)->void()->create();
    $overdue = SupplierInvoice::factory()->for($supplier)->posted()->overdue(45)->create();
    $upcoming = SupplierInvoice::factory()->for($supplier)->posted()->dueIn(15)->create();

    expect($posted->status)->toBe(SupplierInvoiceStatus::POSTED)
        ->and($posted->paid_amount)->toBe('0.00')
        ->and($partial->status)->toBe(SupplierInvoiceStatus::PARTIALLY_PAID)
        ->and((float) $partial->paid_amount)->toBe(250.0)
        ->and($paid->status)->toBe(SupplierInvoiceStatus::PAID)
        ->and((float) $paid->paid_amount)->toBe((float) $paid->grand_total)
        ->and($void->status)->toBe(SupplierInvoiceStatus::VOID)
        ->and($overdue->due_date->isPast())->toBeTrue()
        ->and($upcoming->due_date->isFuture())->toBeTrue();
});
