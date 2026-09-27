<?php

namespace App\Providers;

use App\Domain\Access\Policies\RolePolicy;
use App\Domain\Approvals\Events\ApprovalCompleted;
use App\Domain\Approvals\Listeners\DispatchApprovalWebhook;
use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Approvals\Models\ApprovalWorkflow;
use App\Domain\Approvals\Policies\ApprovalPolicy;
use App\Domain\Approvals\Policies\ApprovalWorkflowPolicy;
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
use App\Domain\Settings\Models\Setting;
use App\Domain\Settings\Policies\SettingsPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        // API rate limit: per app and person, so one busy app cannot starve another and NAT does not merge callers.
        RateLimiter::for('api', function (Request $request) {
            $token = $request->user('api')?->currentAccessToken();

            return Limit::perMinute((int) config('cas.api.rate_limit'))
                ->by($token ? data_get($token, 'client_id').'|'.$request->user('api')->id : (string) $request->ip());
        });

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
        Gate::policy(ApprovalRequest::class, ApprovalPolicy::class);
        Gate::policy(ApprovalWorkflow::class, ApprovalWorkflowPolicy::class);
        Gate::policy(Setting::class, SettingsPolicy::class);
        // Super admins pass every permission check. Self-protection lives in the actions, not the policies.
        Gate::before(fn (User $user) => $user->hasRole('super_admin') ? true : null);

        Event::listen(ApprovalCompleted::class, DispatchApprovalWebhook::class);

        Password::defaults(function () {
            $rule = Password::min((int) config('cas.auth.password_min_length'))->letters()->mixedCase()->numbers()->symbols();

            // The breach lookup calls an external API, so it only runs in production.
            return app()->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
