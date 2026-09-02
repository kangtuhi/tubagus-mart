<?php

declare(strict_types=1);

use App\Enums\AccountingPeriodStatus;
use App\Enums\SupplierInvoiceStatus;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Payables\SupplierInvoiceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('accounting period can be opened for a non-overlapping date range', function () {
    $period = app(AccountingPeriodService::class)->open(
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-31'),
    );

    expect($period->status)->toBe(AccountingPeriodStatus::OPEN)
        ->and($period->start_date->toDateString())->toBe('2026-08-01')
        ->and($period->end_date->toDateString())->toBe('2026-08-31');
});

test('overlapping accounting periods are rejected', function () {
    $service = app(AccountingPeriodService::class);

    $service->open(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

    expect(fn () => $service->open(Carbon::parse('2026-08-15'), Carbon::parse('2026-09-15')))
        ->toThrow(ValidationException::class);
});

test('closed accounting period records audit metadata and blocks posting dates', function () {
    $service = app(AccountingPeriodService::class);
    $user = User::factory()->create();
    $period = $service->open(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

    $closed = $service->close($period, $user->id, 'Month-end close');

    expect($closed->status)->toBe(AccountingPeriodStatus::CLOSED)
        ->and($closed->closed_by)->toBe($user->id)
        ->and($closed->closing_reason)->toBe('Month-end close')
        ->and(fn () => $service->assertOpen(Carbon::parse('2026-08-31')))
        ->toThrow(ValidationException::class);
});

test('period close is rejected while AP reconciliation has a discrepancy', function () {
    $service = app(AccountingPeriodService::class);
    $period = $service->open(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));
    $invoice = SupplierInvoice::factory()->create([
        'supplier_id' => Supplier::factory()->create()->id,
        'invoice_date' => '2026-08-15',
        'status' => SupplierInvoiceStatus::DRAFT,
        'grand_total' => 1000,
        'paid_amount' => 0,
    ]);

    app(SupplierInvoiceService::class)->post($invoice);
    $invoice->update(['paid_amount' => 100]);

    expect(fn () => $service->close($period, reason: 'Month-end close'))
        ->toThrow(ValidationException::class, 'Accounting period cannot be closed while AP reconciliation has discrepancies.')
        ->and($period->refresh()->status)->toBe(AccountingPeriodStatus::OPEN);
});

test('period close succeeds when AP reconciliation is balanced', function () {
    $service = app(AccountingPeriodService::class);
    $period = $service->open(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

    $closed = $service->close($period, reason: 'Month-end close');

    expect($closed->status)->toBe(AccountingPeriodStatus::CLOSED);
});

test('closed accounting period can be explicitly reopened', function () {
    $service = app(AccountingPeriodService::class);
    $period = $service->open(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

    $service->close($period);
    $reopened = $service->reopen($period);

    expect($reopened->status)->toBe(AccountingPeriodStatus::OPEN)
        ->and($reopened->closed_at)->toBeNull()
        ->and($reopened->closed_by)->toBeNull()
        ->and($reopened->closing_reason)->toBeNull();
});

test('reopening an open period and closing an already closed period are rejected', function () {
    $service = app(AccountingPeriodService::class);
    $period = $service->open(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));

    expect(fn () => $service->reopen($period))->toThrow(ValidationException::class);

    $service->close($period);

    expect(fn () => $service->close($period))->toThrow(ValidationException::class);
});

test('invalid accounting period date ranges are rejected', function () {
    expect(fn () => app(AccountingPeriodService::class)->open(
        Carbon::parse('2026-09-01'),
        Carbon::parse('2026-08-31'),
    ))->toThrow(ValidationException::class);
});

test('a date without an accounting period is rejected', function () {
    expect(fn () => app(AccountingPeriodService::class)->assertOpen(Carbon::parse('2026-08-31')))
        ->toThrow(ValidationException::class);
});
