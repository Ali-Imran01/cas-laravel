<?php

use App\Http\Controllers\Api\V1\OrgUnitController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Middleware\OAuth\AsJson;
use Illuminate\Support\Facades\Route;

// Read-only directory API for connected apps. Bearer token from the sign-in flow; every route needs its own
// scope, the token's app must still be enabled, and the person behind it must still have access to that app.
Route::prefix('v1')->name('api.v1.')->middleware([AsJson::class, 'auth:api', 'app.access', 'throttle:api'])->group(function () {
    Route::middleware('scopes:users.read')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/{id}', [UserController::class, 'show'])->whereNumber('id')->name('users.show');
    });

    Route::middleware('scopes:org.read')->group(function () {
        Route::get('/org-units', [OrgUnitController::class, 'index'])->name('org-units.index');
        Route::get('/org-units/{id}', [OrgUnitController::class, 'show'])->whereNumber('id')->name('org-units.show');
    });
});
