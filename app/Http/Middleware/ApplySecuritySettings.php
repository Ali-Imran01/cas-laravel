<?php

namespace App\Http\Middleware;

use App\Domain\Settings\Support\PolicySettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Layers any admin-overridden security policy onto config('cas.auth') before the rest of the request
 * runs, so every existing config('cas.auth.*') call site picks it up without change. Middleware, not a
 * one-off boot() call, because a real request is the unit of "read the current settings" here: PHP tears
 * the application down between requests in production, and tests reuse one application across several
 * simulated requests, so a setting saved mid-test must still take effect on the next one.
 */
class ApplySecuritySettings
{
    public function handle(Request $request, Closure $next): Response
    {
        app(PolicySettings::class)->applyOverrides();

        return $next($request);
    }
}
