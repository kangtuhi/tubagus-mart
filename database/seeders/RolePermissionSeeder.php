<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'dashboard.view', 'display_name' => 'View Dashboard', 'group' => 'dashboard'],
            ['name' => 'users.view', 'display_name' => 'View Users', 'group' => 'users'],
            ['name' => 'users.create', 'display_name' => 'Create Users', 'group' => 'users'],
            ['name' => 'users.update', 'display_name' => 'Update Users', 'group' => 'users'],
            ['name' => 'users.delete', 'display_name' => 'Delete Users', 'group' => 'users'],
            ['name' => 'roles.view', 'display_name' => 'View Roles', 'group' => 'roles'],
            ['name' => 'roles.manage', 'display_name' => 'Manage Roles', 'group' => 'roles'],
            ['name' => 'permissions.view', 'display_name' => 'View Permissions', 'group' => 'permissions'],
            ['name' => 'products.view', 'display_name' => 'View Products', 'group' => 'products'],
            ['name' => 'products.create', 'display_name' => 'Create Products', 'group' => 'products'],
            ['name' => 'products.update', 'display_name' => 'Update Products', 'group' => 'products'],
            ['name' => 'products.delete', 'display_name' => 'Delete Products', 'group' => 'products'],
            ['name' => 'inventory.view', 'display_name' => 'View Inventory', 'group' => 'inventory'],
            ['name' => 'inventory.adjust', 'display_name' => 'Adjust Inventory', 'group' => 'inventory'],
            ['name' => 'sales.view', 'display_name' => 'View Sales', 'group' => 'sales'],
            ['name' => 'sales.create', 'display_name' => 'Create Sales', 'group' => 'sales'],
            ['name' => 'sales.cancel', 'display_name' => 'Cancel Sales', 'group' => 'sales'],
            ['name' => 'purchases.view', 'display_name' => 'View Purchases', 'group' => 'purchases'],
            ['name' => 'purchases.create', 'display_name' => 'Create Purchases', 'group' => 'purchases'],
            ['name' => 'purchases.approve', 'display_name' => 'Approve Purchases', 'group' => 'purchases'],
            ['name' => 'reports.view', 'display_name' => 'View Reports', 'group' => 'reports'],
            ['name' => 'settings.manage', 'display_name' => 'Manage Settings', 'group' => 'settings'],
            ['name' => 'accounting.period.view', 'display_name' => 'View Accounting Periods', 'group' => 'accounting'],
            ['name' => 'accounting.period.close', 'display_name' => 'Close Accounting Periods', 'group' => 'accounting'],
            ['name' => 'accounting.period.reopen', 'display_name' => 'Reopen Accounting Periods', 'group' => 'accounting'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name']],
                $permission,
            );
        }

        $allPermissions = Permission::pluck('id');

        $superAdmin = Role::updateOrCreate(
            ['name' => 'super-admin'],
            [
                'display_name' => 'Super Admin',
                'description' => 'Full access to the Tubagus Mart application.',
            ],
        );
        $superAdmin->permissions()->sync($allPermissions);

        $manager = Role::updateOrCreate(
            ['name' => 'manager'],
            [
                'display_name' => 'Manager',
                'description' => 'Manages daily supermarket operations.',
            ],
        );
        $manager->permissions()->sync(Permission::whereIn('name', [
            'dashboard.view',
            'users.view',
            'products.view', 'products.create', 'products.update',
            'inventory.view', 'inventory.adjust',
            'sales.view',
            'purchases.view', 'purchases.create', 'purchases.approve',
            'reports.view',
            'accounting.period.view',
            'accounting.period.close',
            'accounting.period.reopen',
        ])->pluck('id'));

        $cashier = Role::updateOrCreate(
            ['name' => 'cashier'],
            [
                'display_name' => 'Cashier',
                'description' => 'Handles point-of-sale transactions.',
            ],
        );
        $cashier->permissions()->sync(Permission::whereIn('name', [
            'dashboard.view',
            'products.view',
            'sales.view', 'sales.create',
        ])->pluck('id'));

        $inventory = Role::updateOrCreate(
            ['name' => 'inventory-staff'],
            [
                'display_name' => 'Inventory Staff',
                'description' => 'Handles stock and inventory operations.',
            ],
        );
        $inventory->permissions()->sync(Permission::whereIn('name', [
            'dashboard.view',
            'products.view',
            'inventory.view', 'inventory.adjust',
        ])->pluck('id'));
    }
}
