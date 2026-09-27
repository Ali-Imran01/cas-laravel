<?php

use App\Http\Middleware\Api\EnsureTokenAppHasAccess;
use App\Http\Middleware\ApplySecuritySettings;
use App\Http\Middleware\EnsurePasswordIsCurrent;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\OAuth\AsJson;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Passport\Http\Middleware\CheckToken;
use Laravel\Passport\Http\Middleware\CheckTokenForAnyScope;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            require __DIR__.'/../routes/oauth.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The language switch sets this cookie from JavaScript, so it must not be encrypted.
        $middleware->encryptCookies(except: ['cas_locale']);
        $middleware->web(prepend: [ApplySecuritySettings::class], append: [SetLocale::class, HandleInertiaRequests::class]);
        // First in line, so even an unauthenticated API call is answered as JSON rather than redirected to the login page.
        $middleware->prependToGroup('api', AsJson::class);
        $middleware->alias([
            'password.current' => EnsurePasswordIsCurrent::class,
            'scopes' => CheckToken::class, // token must carry all listed scopes
            'scope' => CheckTokenForAnyScope::class, // token must carry at least one
            'app.access' => EnsureTokenAppHasAccess::class,
        ]);
        // Bearer-token endpoints answer 401, never a redirect to the login page.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*', 'oauth/userinfo') || ($request->is('oauth/logout') && $request->isMethod('POST')) ? null : route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
