<?php

use App\Enums\SupplierInvoiceStatus;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Reports\Payables\SupplierPayablesAgingReport;
use App\Services\Payables\SupplierPayablesAgingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('payables aging classifies outstanding invoices into due date buckets', function () {
    $supplier = Supplier::factory()->create([
        'code' => 'SUP-AGING',
        'name' => 'Aging Supplier',
    ]);
    $asOf = now()->startOfDay();

    SupplierInvoice::factory()->for($supplier)->posted()->create([
        'number' => 'INV-CURRENT',
        'invoice_date' => $asOf->toDateString(),
        'due_date' => $asOf->toDateString(),
        'subtotal' => 1000,
        'grand_total' => 1000,
    ]);
    SupplierInvoice::factory()->for($supplier)->posted()->create([
        'number' => 'INV-1-30',
        'invoice_date' => $asOf->copy()->subDays(10)->toDateString(),
        'due_date' => $asOf->copy()->subDays(10)->toDateString(),
        'subtotal' => 2000,
        'grand_total' => 2000,
    ]);
    SupplierInvoice::factory()->for($supplier)->posted()->create([
        'number' => 'INV-31-60',
        'invoice_date' => $asOf->copy()->subDays(40)->toDateString(),
        'due_date' => $asOf->copy()->subDays(40)->toDateString(),
        'subtotal' => 3000,
        'grand_total' => 3000,
    ]);
    SupplierInvoice::factory()->for($supplier)->posted()->create([
        'number' => 'INV-61-90',
        'invoice_date' => $asOf->copy()->subDays(70)->toDateString(),
        'due_date' => $asOf->copy()->subDays(70)->toDateString(),
        'subtotal' => 4000,
        'grand_total' => 4000,
    ]);
    SupplierInvoice::factory()->for($supplier)->posted()->create([
        'number' => 'INV-91-PLUS',
        'invoice_date' => $asOf->copy()->subDays(100)->toDateString(),
        'due_date' => $asOf->copy()->subDays(100)->toDateString(),
        'subtotal' => 5000,
        'grand_total' => 5000,
    ]);

    $report = app(SupplierPayablesAgingService::class)->report($asOf);

    expect($report)->toBeInstanceOf(SupplierPayablesAgingReport::class)
        ->and($report->totalOutstanding())->toBe(15000.0)
        ->and($report->current())->toBe(1000.0)
        ->and($report->bucket('1_30'))->toBe(2000.0)
        ->and($report->bucket('31_60'))->toBe(3000.0)
        ->and($report->bucket('61_90'))->toBe(4000.0)
        ->and($report->bucket('91_plus'))->toBe(5000.0)
        ->and($report->overdue())->toBe(14000.0)
        ->and($report->invoices)->toHaveCount(5)
        ->and($report->suppliers->first())->toMatchArray([
            'supplier_id' => $supplier->id,
            'supplier_code' => 'SUP-AGING',
            'supplier_name' => 'Aging Supplier',
            'outstanding' => 15000.0,
        ]);
});

test('payables aging excludes paid, draft, and void invoices', function () {
    $supplier = Supplier::factory()->create();

    $paidInvoice = SupplierInvoice::factory()->for($supplier)->paid()->create();
    $draftInvoice = SupplierInvoice::factory()->for($supplier)->create();
    $voidInvoice = SupplierInvoice::factory()->for($supplier)->void()->create();

    expect($paidInvoice->exists)->toBeTrue()
        ->and($draftInvoice->status)->toBe(SupplierInvoiceStatus::DRAFT)
        ->and($voidInvoice->status)->toBe(SupplierInvoiceStatus::VOID)
        ->and(app(SupplierPayablesAgingService::class)->outstandingInvoices())->toHaveCount(0);
});

test('payables aging keeps invoices without due dates in current bucket', function () {
    $supplier = Supplier::factory()->create();
    $invoice = SupplierInvoice::factory()->for($supplier)->partiallyPaid(250)->create([
        'invoice_date' => '2026-08-01',
        'due_date' => null,
        'subtotal' => 1250,
        'grand_total' => 1250,
    ]);

    $report = app(SupplierPayablesAgingService::class)->report(now()->startOfDay());

    expect($report->totalOutstanding())->toBe(1000.0)
        ->and($report->current())->toBe(1000.0)
        ->and($report->invoices->first()['invoice']->is($invoice))->toBeTrue()
        ->and($report->invoices->first()['outstanding'])->toBe(1000.0);
});

test('supplier invoice factory provides useful payables lifecycle states', function () {
    $supplier = Supplier::factory()->create();

    $posted = SupplierInvoice::factory()->for($supplier)->posted()->create();
    $partial = SupplierInvoice::factory()->for($supplier)->partiallyPaid(250)->create([
        'subtotal' => 1000,
        'grand_total' => 1000,
    ]);
    $paid = SupplierInvoice::factory()->for($supplier)->paid()->create();
    $void = SupplierInvoice::factory()->for($supplier)->void()->create();
    $overdue = SupplierInvoice::factory()->for($supplier)->posted()->overdue(45)->create();
    $upcoming = SupplierInvoice::factory()->for($supplier)->posted()->dueIn(15)->create();

    expect($posted->status)->toBe(SupplierInvoiceStatus::POSTED)
        ->and((float) $posted->paid_amount)->toBe(0.0)
        ->and($partial->status)->toBe(SupplierInvoiceStatus::PARTIALLY_PAID)
        ->and((float) $partial->paid_amount)->toBe(250.0)
        ->and($paid->status)->toBe(SupplierInvoiceStatus::PAID)
        ->and((float) $paid->paid_amount)->toBe((float) $paid->grand_total)
        ->and($void->status)->toBe(SupplierInvoiceStatus::VOID)
        ->and($overdue->due_date->isPast())->toBeTrue()
        ->and($upcoming->due_date->isFuture())->toBeTrue();
});
