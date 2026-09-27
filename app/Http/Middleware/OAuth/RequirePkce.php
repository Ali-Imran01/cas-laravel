<?php

namespace App\Http\Middleware\OAuth;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every client must use PKCE with S256, confidential or not. With a code challenge on the authorization
 * request, the token endpoint then refuses to redeem the code without the matching verifier, so a stolen
 * code is useless on its own.
 */
class RequirePkce
{
    public function handle(Request $request, Closure $next): Response
    {
        $challenge = (string) $request->query('code_challenge');

        if ($request->query('code_challenge_method') !== 'S256' || ! preg_match('/^[A-Za-z0-9_-]{43,128}$/', $challenge)) {
            return Inertia::render('OAuth/Denied', ['reason' => 'pkce_required', 'app' => null])->toResponse($request)->setStatusCode(400);
        }

        return $next($request);
    }
}
