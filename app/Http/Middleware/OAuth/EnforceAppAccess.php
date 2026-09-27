<?php

namespace App\Http\Middleware\OAuth;

use App\Domain\Apps\Actions\AppAccessCheck;
use App\Domain\Apps\Models\Application;
use App\Domain\Audit\Audit;
use App\Domain\Audit\Enums\AuthResult;
use App\Domain\Audit\Enums\LoginMethod;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        if ($response->isRedirection()) {
            parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $back);

            if (isset($back['code']) && is_string($back['code'])) {
                Audit::loginAttempt($user, $user->staff_id, LoginMethod::Sso, AuthResult::Success, null, $app->id);

                // OpenID Connect: the nonce belongs to this code and goes into the ID token issued when it is redeemed.
                $nonce = $request->query('nonce');
                if (is_string($nonce) && $nonce !== '' && strlen($nonce) <= 255) {
                    Cache::put('oidc:nonce:'.hash('sha256', $back['code']), $nonce, now()->addMinutes(10));
                }
            }
        }

        return $response;
    }

    private function deny(Request $request, string $reason, ?Application $app, int $status = 403): Response
    {
        return Inertia::render('OAuth/Denied', ['reason' => $reason, 'app' => $app?->name])->toResponse($request)->setStatusCode($status);
    }
}
