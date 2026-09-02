<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            BusinessFoundationSeeder::class,
            ProductFoundationSeeder::class,
        ]);

        $testUser = User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'email_verified_at' => now(),
                'password' => 'password',
                'remember_token' => null,
                'is_active' => true,
            ],
        );

        $testUser->roles()->syncWithoutDetaching([
            Role::where('name', 'super-admin')->value('id'),
        ]);
    }
}
