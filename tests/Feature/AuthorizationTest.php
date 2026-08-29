<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_every_permission(): void
    {
        $permission = Permission::create([
            'name' => 'test.anything',
            'display_name' => 'Test Anything',
            'group' => 'test',
        ]);

        $role = Role::create([
            'name' => 'super-admin',
            'display_name' => 'Super Admin',
        ]);
        $role->permissions()->attach($permission);

        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->assertTrue($user->hasPermission('test.anything'));
    }

    public function test_inactive_super_admin_is_denied_by_gate(): void
    {
        $role = Role::create([
            'name' => 'super-admin',
            'display_name' => 'Super Admin',
        ]);

        $user = User::factory()->inactive()->create();
        $user->roles()->attach($role);

        $this->assertFalse(Gate::forUser($user)->allows('permission', 'test.anything'));
    }

    public function test_user_without_permission_is_denied(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->hasPermission('products.delete'));
    }

    public function test_inactive_user_is_not_active(): void
    {
        $user = User::factory()->inactive()->create();

        $this->assertFalse($user->is_active);
    }
}
