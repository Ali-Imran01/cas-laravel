<?php

namespace App\Providers;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserImport;
use App\Domain\Identity\Policies\UserImportPolicy;
use App\Domain\Identity\Policies\UserPolicy;
use App\Domain\Organization\Models\OrgUnit;
use App\Domain\Organization\Policies\OrgUnitPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(OrgUnit::class, OrgUnitPolicy::class);
        Gate::policy(UserImport::class, UserImportPolicy::class);
        // Super admins pass every permission check. Self-protection lives in the actions, not the policies.
        Gate::before(fn (User $user) => $user->hasRole('super_admin') ? true : null);

        Password::defaults(function () {
            $rule = Password::min((int) config('cas.auth.password_min_length'))->letters()->mixedCase()->numbers()->symbols();

            // The breach lookup calls an external API, so it only runs in production.
            return app()->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
