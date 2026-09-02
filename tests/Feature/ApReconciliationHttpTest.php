<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApReconciliationHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_ap_reconciliation_console(): void
    {
        $this->get(route('admin.accounting.ap-reconciliation.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_without_accounting_period_view_permission_is_forbidden(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user)
            ->get(route('admin.accounting.ap-reconciliation.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_open_ap_reconciliation_console(): void
    {
        $user = $this->authorizedUser();
        SupplierInvoice::factory()->create([
            'supplier_id' => Supplier::factory()->create()->id,
            'status' => 'posted',
            'grand_total' => 150000,
            'paid_amount' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('admin.accounting.ap-reconciliation.index'))
            ->assertOk()
            ->assertSee('AP Reconciliation Console')
            ->assertSee('Supplier control matrix');
    }

    public function test_supplier_filter_is_applied_to_reconciliation_console(): void
    {
        $user = $this->authorizedUser();
        $supplier = Supplier::factory()->create(['name' => 'Selected Supplier']);
        $other = Supplier::factory()->create(['name' => 'Other Supplier']);

        SupplierInvoice::factory()->create(['supplier_id' => $supplier->id, 'status' => 'posted']);
        SupplierInvoice::factory()->create(['supplier_id' => $other->id, 'status' => 'posted']);

        $this->actingAs($user)
            ->get(route('admin.accounting.ap-reconciliation.index', ['supplier_id' => $supplier->id]))
            ->assertOk()
            ->assertSee('Selected Supplier')
            ->assertDontSee('Other Supplier');
    }

    private function authorizedUser(): User
    {
        $permission = Permission::create([
            'name' => 'accounting.period.view',
            'display_name' => 'View Accounting Periods',
            'group' => 'accounting',
        ]);
        $role = Role::create([
            'name' => 'ap-reconciliation-http',
            'display_name' => 'AP Reconciliation HTTP',
        ]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create(['is_active' => true]);
        $user->roles()->attach($role);

        return $user;
    }
}
