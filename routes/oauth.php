<?php

use App\Http\Controllers\Sso\DiscoveryController;
use App\Http\Controllers\Sso\JwksController;
use App\Http\Controllers\Sso\LogoutController;
use App\Http\Controllers\Sso\OidcTokenController;
use App\Http\Controllers\Sso\UserInfoController;
use App\Http\Middleware\OAuth\AsJson;
use App\Http\Middleware\OAuth\EnforceAppAccess;
use App\Http\Middleware\OAuth\RequirePkce;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Http\Controllers\AuthorizationController;

// OAuth 2.0 authorization code + PKCE, with OpenID Connect on top. Only the endpoints that flow needs are
// registered; Passport's own route set (device flow, transient tokens, JSON client API) is switched off on purpose.

// --- Called by apps' servers and libraries: bearer tokens or client credentials, no session, no CSRF, so no `web` group.
Route::post('/oauth/token', [OidcTokenController::class, 'issueToken'])->middleware('throttle:60,1')->name('passport.token');
Route::get('/.well-known/openid-configuration', DiscoveryController::class)->name('oidc.discovery');
Route::get('/oauth/jwks', JwksController::class)->name('oidc.jwks');
Route::match(['get', 'post'], '/oauth/userinfo', UserInfoController::class)->middleware([AsJson::class, 'auth:api', 'throttle:120,1'])->name('oidc.userinfo');
Route::post('/oauth/logout', [LogoutController::class, 'api'])->middleware([AsJson::class, 'auth:api', 'throttle:30,1'])->name('oidc.logout.api');

// --- In the browser.
// Every registered app skips the consent screen, so this either sends the user straight back to the app
// with a code, or shows a "no access" page.
Route::middleware(['web', 'auth', 'password.current', RequirePkce::class, EnforceAppAccess::class])
    ->get('/oauth/authorize', [AuthorizationController::class, 'authorize'])
    ->name('passport.authorizations.authorize');

Route::middleware('web')->get('/oauth/logout', [LogoutController::class, 'browser'])->name('oidc.logout');
