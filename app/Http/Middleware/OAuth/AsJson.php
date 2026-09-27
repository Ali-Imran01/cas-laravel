<?php

namespace App\Http\Middleware\OAuth;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** API-style endpoints answer 401/403 as JSON, never with a redirect to the login page. */
class AsJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
