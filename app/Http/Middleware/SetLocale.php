<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Follows the language switch made in the UI (it stores the choice in the `cas_locale` cookie). */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie('cas_locale');
        app()->setLocale(in_array($locale, ['en', 'ms'], true) ? $locale : config('app.locale'));

        return $next($request);
    }
}
