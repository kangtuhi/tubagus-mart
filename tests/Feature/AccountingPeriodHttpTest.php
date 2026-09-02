<?php

declare(strict_types=1);

use App\Enums\AccountingPeriodStatus;
use App\Models\AccountingPeriod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountingPeriodService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function accountingHttpUser(array $permissions): User
{
    $permissionIds = collect($permissions)->map(function (string $name): int {
        return Permission::create([
            'name' => $name,
            'display_name' => $name,
            'group' => 'accounting',
        ])->id;
    });

    $role = Role::create([
        'name' => 'accounting-http-controller',
        'display_name' => 'Accounting HTTP Controller',
    ]);
    $role->permissions()->attach($permissionIds);

    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}

test('accounting period index requires authentication', function () {
    $this->get(route('admin.accounting.periods.index'))
        ->assertRedirect(route('login'));
});

test('accounting period index requires accounting period view permission', function () {
    $user = accountingHttpUser(['dashboard.view']);

    $this->actingAs($user)
        ->get(route('admin.accounting.periods.index'))
        ->assertForbidden();
});

test('authorized accounting user can view period control and closing gate', function () {
    $user = accountingHttpUser([
        'dashboard.view',
        'accounting.period.view',
        'accounting.period.close',
    ]);
    $period = app(AccountingPeriodService::class)->open(
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-31'),
    );

    $this->actingAs($user)
        ->get(route('admin.accounting.periods.index'))
        ->assertOk()
        ->assertSee('Accounting Period Control')
        ->assertSee('01 Aug 2026 — 31 Aug 2026');

    $this->actingAs($user)
        ->get(route('admin.accounting.periods.gate', $period))
        ->assertOk()
        ->assertSee('Reconciliation gate')
        ->assertSee('GATE PASSED');
});

test('authorized user can close an accounting period through the protected endpoint', function () {
    $user = accountingHttpUser([
        'dashboard.view',
        'accounting.period.view',
        'accounting.period.close',
    ]);
    $period = app(AccountingPeriodService::class)->open(
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-31'),
    );

    $this->actingAs($user)
        ->post(route('admin.accounting.periods.close', $period), [
            'reason' => 'Month-end close',
        ])
        ->assertRedirect(route('admin.accounting.periods.index'))
        ->assertSessionHas('success', 'Accounting period closed successfully.');

    expect($period->refresh()->status)->toBe(AccountingPeriodStatus::CLOSED)
        ->and($period->closed_by)->toBe($user->id)
        ->and($period->closing_reason)->toBe('Month-end close');
});

test('reopen endpoint requires reopen permission and a reason', function () {
    $user = accountingHttpUser([
        'dashboard.view',
        'accounting.period.view',
        'accounting.period.close',
        'accounting.period.reopen',
    ]);
    $period = app(AccountingPeriodService::class)->open(
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-31'),
    );
    app(AccountingPeriodService::class)->close($period, $user->id, 'Month-end close');

    $this->actingAs($user)
        ->post(route('admin.accounting.periods.reopen', $period))
        ->assertSessionHasErrors('reason');

    $this->actingAs($user)
        ->post(route('admin.accounting.periods.reopen', $period), [
            'reason' => 'Correct AP posting',
        ])
        ->assertRedirect(route('admin.accounting.periods.index'))
        ->assertSessionHas('success', 'Accounting period reopened successfully.');
});
