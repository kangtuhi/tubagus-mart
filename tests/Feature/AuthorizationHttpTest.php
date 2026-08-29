<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthorizationHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'active', 'permission:products.view'])
            ->get('/_test/permission', fn () => 'allowed');

        Route::middleware(['web', 'auth', 'active', 'role:manager'])
            ->get('/_test/role', fn () => 'allowed');
    }

    public function test_user_with_permission_can_access_protected_route(): void
    {
        $permission = Permission::create([
            'name' => 'products.view',
            'display_name' => 'View Products',
            'group' => 'products',
        ]);

        $role = Role::create([
            'name' => 'cashier',
            'display_name' => 'Cashier',
        ]);
        $role->permissions()->attach($permission);

        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get('/_test/permission')
            ->assertOk();
    }

    public function test_user_without_permission_receives_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/_test/permission')
            ->assertForbidden();
    }

    public function test_user_with_role_can_access_role_protected_route(): void
    {
        $role = Role::create([
            'name' => 'manager',
            'display_name' => 'Manager',
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get('/_test/role')
            ->assertOk();
    }

    public function test_user_with_wrong_role_receives_forbidden(): void
    {
        $role = Role::create([
            'name' => 'cashier',
            'display_name' => 'Cashier',
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get('/_test/role')
            ->assertForbidden();
    }

    public function test_inactive_user_receives_forbidden(): void
    {
        $permission = Permission::create([
            'name' => 'products.view',
            'display_name' => 'View Products',
            'group' => 'products',
        ]);

        $role = Role::create([
            'name' => 'manager',
            'display_name' => 'Manager',
        ]);
        $role->permissions()->attach($permission);

        $user = User::factory()->create([
            'is_active' => false,
        ]);
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get('/_test/permission')
            ->assertForbidden();
    }
}
