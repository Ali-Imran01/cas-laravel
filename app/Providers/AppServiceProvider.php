<?php

namespace App\Providers;

use App\Domain\Access\Policies\RolePolicy;
use App\Domain\Apps\Models\Application;
use App\Domain\Apps\Models\OAuthClient;
use App\Domain\Apps\Policies\ApplicationPolicy;
use App\Domain\Audit\AuditContext;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\Policies\AuditPolicy;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserImport;
use App\Domain\Identity\Policies\UserImportPolicy;
use App\Domain\Identity\Policies\UserPolicy;
use App\Domain\Organization\Models\OrgUnit;
use App\Domain\Organization\Policies\OrgUnitPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(AuditContext::class);
        Passport::ignoreRoutes(); // routes/oauth.php defines the few endpoints CAS actually uses
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::useClientModel(OAuthClient::class);
        // Passport needs a consent view registered even though no CAS app ever shows one (all are first-party).
        Passport::authorizationView(fn () => abort(403));
        Passport::tokensCan(config('cas.sso.scopes'));
        Passport::tokensExpireIn(now()->addMinutes(config('cas.sso.access_token_minutes')));
        Passport::refreshTokensExpireIn(now()->addDays(config('cas.sso.refresh_token_days')));

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Application::class, ApplicationPolicy::class);
        Gate::policy(OrgUnit::class, OrgUnitPolicy::class);
        Gate::policy(UserImport::class, UserImportPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(AuditLog::class, AuditPolicy::class);
        // Super admins pass every permission check. Self-protection lives in the actions, not the policies.
        Gate::before(fn (User $user) => $user->hasRole('super_admin') ? true : null);

        Password::defaults(function () {
            $rule = Password::min((int) config('cas.auth.password_min_length'))->letters()->mixedCase()->numbers()->symbols();

            // The breach lookup calls an external API, so it only runs in production.
            return app()->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
