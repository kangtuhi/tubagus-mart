<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::before(function (User $user): ?bool {
            if (! $user->is_active) {
                return false;
            }

            return $user->hasRole('super-admin') ? true : null;
        });

        Gate::define('permission', function (User $user, string $permission): bool {
            return $user->is_active && $user->hasPermission($permission);
        });

        Gate::define('role', function (User $user, string $role): bool {
            return $user->is_active && $user->hasRole($role);
        });
    }
}
