<?php

declare(strict_types=1);

use App\Enums\AccountingPeriodStatus;
use App\Enums\SupplierInvoiceStatus;
use App\Models\AccountingPeriod;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\Accounting\AccountingPeriodService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function closingGatePeriod(string $startDate, string $endDate): AccountingPeriod
{
    return app(AccountingPeriodService::class)->open(
        Carbon::parse($startDate),
        Carbon::parse($endDate),
    );
}

function closingGateInvoice(
    Supplier $supplier,
    string $invoiceDate,
    float $paidAmount = 0,
): SupplierInvoice {
    return SupplierInvoice::create([
        'supplier_id' => $supplier->id,
        'number' => 'INV-'.fake()->unique()->numerify('######'),
        'invoice_date' => $invoiceDate,
        'subtotal' => 1000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'grand_total' => 1000,
        'paid_amount' => $paidAmount,
        'status' => SupplierInvoiceStatus::POSTED,
    ]);
}

test('closing gate passes when AP reconciliation is matched through period end', function () {
    $period = closingGatePeriod('2026-08-01', '2026-08-31');
    closingGateInvoice(Supplier::factory()->create(), '2026-08-15');

    $gate = app(AccountingPeriodService::class)->closingGate($period);

    expect($gate['can_close'])->toBeTrue()
        ->and($gate['checks']['ap_reconciliation']['status'])->toBe('passed')
        ->and($gate['checks']['ap_reconciliation']['discrepancy_count'])->toBe(0);
});

test('closing gate fails when AP reconciliation has a discrepancy before period end', function () {
    $period = closingGatePeriod('2026-08-01', '2026-08-31');
    closingGateInvoice(Supplier::factory()->create(), '2026-08-15', paidAmount: 100);

    $gate = app(AccountingPeriodService::class)->closingGate($period);

    expect($gate['can_close'])->toBeFalse()
        ->and($gate['checks']['ap_reconciliation']['status'])->toBe('failed')
        ->and($gate['checks']['ap_reconciliation']['discrepancy_count'])->toBe(1);

    expect(fn () => app(AccountingPeriodService::class)->close($period))
        ->toThrow(ValidationException::class, 'Accounting period cannot be closed while AP reconciliation has discrepancies.');
});

test('closing an earlier period is not blocked by AP transactions dated after its end date', function () {
    $service = app(AccountingPeriodService::class);
    $august = closingGatePeriod('2026-08-01', '2026-08-31');
    closingGatePeriod('2026-09-01', '2026-09-30');

    $supplier = Supplier::factory()->create();
    closingGateInvoice($supplier, '2026-08-15');
    closingGateInvoice($supplier, '2026-09-15', paidAmount: 100);

    $closed = $service->close($august);

    expect($closed->status)->toBe(AccountingPeriodStatus::CLOSED);
});
