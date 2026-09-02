<?php

declare(strict_types=1);

use App\Enums\SupplierInvoiceStatus;
use App\Enums\SupplierPayableAdjustmentType;
use App\Models\AccountingPeriod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Payables\SupplierInvoiceService;
use App\Services\Payables\SupplierPayableAdjustmentService;
use App\Services\Payables\SupplierPaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function enforcementInvoice(array $attributes = []): SupplierInvoice
{
    return SupplierInvoice::create(array_merge([
        'supplier_id' => Supplier::factory()->create()->id,
        'number' => 'INV-'.fake()->unique()->numerify('######'),
        'invoice_date' => '2026-08-15',
        'subtotal' => 1000,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'grand_total' => 1000,
        'paid_amount' => 0,
        'status' => SupplierInvoiceStatus::DRAFT,
    ], $attributes));
}

function openAugust2026Period(): AccountingPeriod
{
    return app(AccountingPeriodService::class)->open(
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-31'),
    );
}

function authorizedAccountingPeriodUser(): User
{
    $user = User::factory()->create();
    $role = Role::create([
        'name' => 'accounting-test',
        'display_name' => 'Accounting Test',
    ]);
    $permission = Permission::create([
        'name' => 'accounting.period.reopen',
        'display_name' => 'Reopen Accounting Period',
        'group' => 'accounting',
    ]);

    $role->permissions()->attach($permission);
    $user->roles()->attach($role);

    return $user;
}

test('invoice posting is allowed in an open accounting period', function () {
    openAugust2026Period();
    $invoice = enforcementInvoice();

    $result = app(SupplierInvoiceService::class)->post($invoice);

    expect($result->status)->toBe(SupplierInvoiceStatus::POSTED);
});

test('invoice posting is rejected in a closed accounting period', function () {
    $period = openAugust2026Period();
    app(AccountingPeriodService::class)->close($period);
    $invoice = enforcementInvoice();

    expect(fn () => app(SupplierInvoiceService::class)->post($invoice))
        ->toThrow(ValidationException::class, 'The accounting period for the supplied date is closed.');
});

test('payment is allowed in an open accounting period', function () {
    openAugust2026Period();
    $invoice = enforcementInvoice();
    app(SupplierInvoiceService::class)->post($invoice);

    $payment = app(SupplierPaymentService::class)->record(
        $invoice,
        'PAY-OPEN-001',
        250,
        paidAt: Carbon::parse('2026-08-20'),
    );

    expect((float) $payment->amount)->toBe(250.0);
});

test('payment is rejected in a closed accounting period', function () {
    $period = openAugust2026Period();
    $invoice = enforcementInvoice();
    app(SupplierInvoiceService::class)->post($invoice);
    app(AccountingPeriodService::class)->close($period);

    expect(fn () => app(SupplierPaymentService::class)->record(
        $invoice,
        'PAY-CLOSED-001',
        250,
        paidAt: Carbon::parse('2026-08-20'),
    ))->toThrow(ValidationException::class, 'The accounting period for the supplied date is closed.');
});

test('payable adjustment is allowed in an open accounting period', function () {
    openAugust2026Period();
    $invoice = enforcementInvoice();
    app(SupplierInvoiceService::class)->post($invoice);

    $adjustment = app(SupplierPayableAdjustmentService::class)->record(
        $invoice,
        SupplierPayableAdjustmentType::CREDIT,
        100,
        'ADJ-OPEN-001',
        'Open period credit',
        Carbon::parse('2026-08-21'),
    );

    expect((float) $adjustment->amount)->toBe(100.0);
});

test('payable adjustment is rejected in a closed accounting period', function () {
    $period = openAugust2026Period();
    $invoice = enforcementInvoice();
    app(SupplierInvoiceService::class)->post($invoice);
    app(AccountingPeriodService::class)->close($period);

    expect(fn () => app(SupplierPayableAdjustmentService::class)->record(
        $invoice,
        SupplierPayableAdjustmentType::CREDIT,
        100,
        'ADJ-CLOSED-001',
        'Closed period credit',
        Carbon::parse('2026-08-21'),
    ))->toThrow(ValidationException::class, 'The accounting period for the supplied date is closed.');
});

test('reversal is allowed after its accounting period is reopened', function () {
    $period = openAugust2026Period();
    $invoice = enforcementInvoice();
    app(SupplierInvoiceService::class)->post($invoice);

    $adjustment = app(SupplierPayableAdjustmentService::class)->record(
        $invoice,
        SupplierPayableAdjustmentType::CREDIT,
        100,
        'ADJ-REOPEN-001',
        'Credit before close',
        Carbon::parse('2026-08-21'),
    );

    $authorizedUser = authorizedAccountingPeriodUser();

    app(AccountingPeriodService::class)->close($period);
    app(AccountingPeriodService::class)->reopen(
        $period,
        $authorizedUser->id,
        'Period reopened for controlled correction',
    );

    $reversal = app(SupplierPayableAdjustmentService::class)->reverse(
        $adjustment,
        'REV-REOPEN-001',
        'Period reopened for controlled correction',
        reversalDate: Carbon::parse('2026-08-25'),
    );

    expect($reversal->reversal_of_id)->toBe($adjustment->id);
});

test('reversal is rejected when its accounting period is closed', function () {
    $period = openAugust2026Period();
    $invoice = enforcementInvoice();
    app(SupplierInvoiceService::class)->post($invoice);

    $adjustment = app(SupplierPayableAdjustmentService::class)->record(
        $invoice,
        SupplierPayableAdjustmentType::CREDIT,
        100,
        'ADJ-CLOSED-REV-001',
        'Credit before close',
        Carbon::parse('2026-08-21'),
    );

    app(AccountingPeriodService::class)->close($period);

    expect(fn () => app(SupplierPayableAdjustmentService::class)->reverse(
        $adjustment,
        'REV-CLOSED-001',
        'Attempted correction in closed period',
        reversalDate: Carbon::parse('2026-08-25'),
    ))->toThrow(ValidationException::class, 'The accounting period for the supplied date is closed.');
});

test('accounting period boundaries select adjacent periods deterministically', function () {
    $periodService = app(AccountingPeriodService::class);
    $august = $periodService->open(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'));
    $september = $periodService->open(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));

    expect($periodService->forDate(Carbon::parse('2026-08-31'))?->id)->toBe($august->id)
        ->and($periodService->forDate(Carbon::parse('2026-09-01'))?->id)->toBe($september->id);
});
