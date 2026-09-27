<?php

namespace App\Http\Middleware\OAuth;

use App\Domain\Apps\Actions\AppAccessCheck;
use App\Domain\Apps\Models\Application;
use App\Domain\Audit\Audit;
use App\Domain\Audit\Enums\AuthResult;
use App\Domain\Audit\Enums\LoginMethod;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs before Passport issues a code: is this a real, enabled app, are the scopes ones it may ask for, and
 * may this person use it? Every outcome lands in the sign-in history against the app.
 */
class EnforceAppAccess
{
    public function __construct(private readonly AppAccessCheck $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $app = Application::query()->where('oauth_client_id', (string) $request->query('client_id'))->first();

        if ($app === null) {
            return $this->deny($request, 'unknown_app', null, 400);
        }

        $requested = array_filter(explode(' ', (string) $request->query('scope')));
        if (array_diff($requested, $app->allowed_scopes) !== []) {
            Audit::loginAttempt($user, $user->staff_id, LoginMethod::Sso, AuthResult::Blocked, 'invalid_scope', $app->id);

            return $this->deny($request, 'invalid_scope', $app, 400);
        }

        if ($reason = $this->access->denial($user, $app)) {
            Audit::loginAttempt($user, $user->staff_id, LoginMethod::Sso, AuthResult::Blocked, $reason, $app->id);

            return $this->deny($request, $reason, $app);
        }

        $response = $next($request);

        // Passport answers a valid request with a redirect back to the app carrying the code.
        if ($response->isRedirection() && str_contains((string) $response->headers->get('Location'), 'code=')) {
            Audit::loginAttempt($user, $user->staff_id, LoginMethod::Sso, AuthResult::Success, null, $app->id);
        }

        return $response;
    }

    private function deny(Request $request, string $reason, ?Application $app, int $status = 403): Response
    {
        return Inertia::render('OAuth/Denied', ['reason' => $reason, 'app' => $app?->name])->toResponse($request)->setStatusCode($status);
    }
}
