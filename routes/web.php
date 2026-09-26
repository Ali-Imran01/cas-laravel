<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MfaChallengeController;
use App\Http\Controllers\Auth\MfaSettingsController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
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
    });
});
