<?php

use App\Http\Controllers\Auth\AcceptInviteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MfaChallengeController;
use App\Http\Controllers\Auth\MfaSettingsController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Organization\OrgUnitController;
use App\Http\Controllers\Organization\PositionController;
use App\Http\Controllers\Users\UserBulkController;
use App\Http\Controllers\Users\UserController;
use App\Http\Controllers\Users\UserInviteController;
use App\Http\Controllers\Users\UserStatusController;
use App\Http\Controllers\Users\UserTransferController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');

    Route::get('/accept-invite/{token}', [AcceptInviteController::class, 'create'])->name('invite.accept');
    Route::post('/accept-invite', [AcceptInviteController::class, 'store'])->name('invite.store');
});

// Second sign-in step: not authenticated yet, only a pending user id in the session.
Route::middleware('guest')->prefix('login/mfa')->group(function () {
    Route::get('/', [MfaChallengeController::class, 'create'])->name('mfa.challenge');
    Route::post('/', [MfaChallengeController::class, 'store']);
    Route::post('/email', [MfaChallengeController::class, 'sendEmail'])->name('mfa.email');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Reachable while the password is expired, otherwise the user could never fix it.
    Route::get('/password/change', [PasswordController::class, 'edit'])->name('password.change');
    Route::put('/password/change', [PasswordController::class, 'update']);

    Route::middleware('password.current')->group(function () {
        // Phase 0 placeholder props: real data arrives with each module.
        Route::get('/', fn () => Inertia::render('Dashboard', [
            'kpis' => ['users' => 0, 'apps' => 0, 'pendingApprovals' => 0, 'signInsToday' => 0],
            'signIns' => [],
        ]))->name('dashboard');

        Route::get('/mfa', [MfaSettingsController::class, 'show'])->name('mfa.setup');
        Route::post('/mfa', [MfaSettingsController::class, 'store'])->name('mfa.enable');
        Route::delete('/mfa', [MfaSettingsController::class, 'destroy'])->name('mfa.disable');

        Route::get('/organization', [OrgUnitController::class, 'index'])->name('organization.index');
        Route::post('/organization/units', [OrgUnitController::class, 'store'])->name('organization.units.store');
        Route::put('/organization/units/{unit}', [OrgUnitController::class, 'update'])->name('organization.units.update');
        Route::post('/organization/units/{unit}/move', [OrgUnitController::class, 'move'])->name('organization.units.move');
        Route::delete('/organization/units/{unit}', [OrgUnitController::class, 'destroy'])->name('organization.units.destroy');
        Route::post('/organization/units/{unit}/positions', [PositionController::class, 'store'])->name('organization.positions.store');
        Route::put('/organization/positions/{position}', [PositionController::class, 'update'])->name('organization.positions.update');
        Route::delete('/organization/positions/{position}', [PositionController::class, 'destroy'])->name('organization.positions.destroy');

        Route::post('/users/bulk', [UserBulkController::class, 'store'])->name('users.bulk');
        Route::resource('users', UserController::class);
        Route::post('/users/{user}/lock', [UserStatusController::class, 'lock'])->name('users.lock');
        Route::post('/users/{user}/unlock', [UserStatusController::class, 'unlock'])->name('users.unlock');
        Route::post('/users/{user}/deactivate', [UserStatusController::class, 'deactivate'])->name('users.deactivate');
        Route::post('/users/{user}/reactivate', [UserStatusController::class, 'reactivate'])->name('users.reactivate');
        Route::post('/users/{user}/transfer', [UserTransferController::class, 'store'])->name('users.transfer');
        Route::post('/users/{user}/invite', [UserInviteController::class, 'store'])->name('users.invite');
    });
});
