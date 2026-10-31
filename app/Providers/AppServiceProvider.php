<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Deactivated accounts can do nothing; the super admin can do everything.
        Gate::before(function (User $user) {
            // Strict check: a freshly created model has no is_active until the column default is read back.
            if ($user->is_active === false) {
                return false;
            }

            return $user->isSuperAdmin() ? true : null;
        });

        foreach (Role::allPermissions() as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }

        // Compiled to a runtime call, so the version updates without clearing compiled views.
        Blade::directive('asset', fn (string $path) => "<?php echo e(\\App\\Support\\Asset::url({$path})); ?>");
    }
}
