<?php

use App\Http\Middleware\EnsurePasswordIsCurrent;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            require __DIR__.'/../routes/oauth.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The language switch sets this cookie from JavaScript, so it must not be encrypted.
        $middleware->encryptCookies(except: ['cas_locale']);
        $middleware->web(append: [SetLocale::class, HandleInertiaRequests::class]);
        $middleware->alias(['password.current' => EnsurePasswordIsCurrent::class]);
        // Bearer-token endpoints answer 401, never a redirect to the login page.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('oauth/userinfo') || ($request->is('oauth/logout') && $request->isMethod('POST')) ? null : route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
