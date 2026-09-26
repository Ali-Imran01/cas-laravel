<?php

use App\Http\Middleware\EnsurePasswordIsCurrent;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The language switch sets this cookie from JavaScript, so it must not be encrypted.
        $middleware->encryptCookies(except: ['cas_locale']);
        $middleware->web(append: [SetLocale::class, HandleInertiaRequests::class]);
        $middleware->alias(['password.current' => EnsurePasswordIsCurrent::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
