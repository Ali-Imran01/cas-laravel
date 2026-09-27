<?php

use App\Http\Middleware\OAuth\EnforceAppAccess;
use App\Http\Middleware\OAuth\RequirePkce;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Controllers\AuthorizationController;

// Only the two endpoints an authorization-code + PKCE flow needs. Passport's own route set (device flow,
// transient tokens, JSON client API) is switched off on purpose: it would widen the attack surface for nothing.

// Called by apps' servers, not browsers: no session, no CSRF token, so no `web` group here.
Route::post('/oauth/token', [AccessTokenController::class, 'issueToken'])
    ->middleware('throttle:60,1')
    ->name('passport.token');

// Called in the browser. Every registered app skips the consent screen, so this either sends the user
// straight back to the app with a code, or shows a "no access" page.
Route::middleware(['web', 'auth', 'password.current', RequirePkce::class, EnforceAppAccess::class])
    ->get('/oauth/authorize', [AuthorizationController::class, 'authorize'])
    ->name('passport.authorizations.authorize');
