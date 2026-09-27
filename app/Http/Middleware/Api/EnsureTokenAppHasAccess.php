<?php

namespace App\Http\Middleware\Api;

use App\Domain\Apps\Actions\AppAccessCheck;
use App\Domain\Apps\Models\Application;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A bearer token is only as good as the access behind it: if the app was disabled or the person lost the
 * right to use it, the token stops working even before it expires or is revoked.
 */
class EnsureTokenAppHasAccess
{
    public function __construct(private readonly AppAccessCheck $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api');
        $token = $user?->currentAccessToken();
        $app = Application::query()->where('oauth_client_id', $token->client_id ?? null)->first();

        if ($user === null || $app === null || $this->access->denial($user, $app) !== null) {
            return response()->json(['error' => 'invalid_token'], 401)->withHeaders(['WWW-Authenticate' => 'Bearer error="invalid_token"']);
        }

        // Controllers and rate limiting can tell which app is calling.
        $request->attributes->set('cas.app', $app);

        return $next($request);
    }
}
