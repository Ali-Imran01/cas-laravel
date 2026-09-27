<?php

use App\Http\Controllers\Access\RoleController;
use App\Http\Controllers\Approvals\ApprovalController;
use App\Http\Controllers\Approvals\ApprovalDecisionController;
use App\Http\Controllers\Approvals\ApprovalSubmitController;
use App\Http\Controllers\Approvals\ApprovalWorkflowController;
use App\Http\Controllers\Apps\ApplicationController;
use App\Http\Controllers\Audit\AuditController;
use App\Http\Controllers\Auth\AcceptInviteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MfaChallengeController;
use App\Http\Controllers\Auth\MfaSettingsController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Organization\OrgUnitController;
use App\Http\Controllers\Organization\PositionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Users\UserBulkController;
use App\Http\Controllers\Users\UserController;
use App\Http\Controllers\Users\UserImportController;
use App\Http\Controllers\Users\UserInviteController;
use App\Http\Controllers\Users\UserStatusController;
use App\Http\Controllers\Users\UserTransferController;
use Illuminate\Support\Facades\Route;

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
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('/apps', [ApplicationController::class, 'index'])->name('apps.index');
        Route::post('/apps', [ApplicationController::class, 'store'])->name('apps.store');
        Route::put('/apps/{app}', [ApplicationController::class, 'update'])->name('apps.update');
        Route::delete('/apps/{app}', [ApplicationController::class, 'destroy'])->name('apps.destroy');
        Route::post('/apps/{app}/secret', [ApplicationController::class, 'rotateSecret'])->name('apps.secret');
        Route::post('/apps/{app}/disable', [ApplicationController::class, 'disable'])->name('apps.disable');
        Route::post('/apps/{app}/enable', [ApplicationController::class, 'enable'])->name('apps.enable');
        Route::post('/apps/{app}/roles', [ApplicationController::class, 'mapRole'])->name('apps.roles.map');
        Route::delete('/apps/{app}/roles/{role}', [ApplicationController::class, 'unmapRole'])->name('apps.roles.unmap');
        Route::post('/apps/{app}/users', [ApplicationController::class, 'grantUser'])->name('apps.users.grant');
        Route::delete('/apps/{app}/users/{user}', [ApplicationController::class, 'revokeUser'])->name('apps.users.revoke');
        Route::put('/apps/{app}/webhook', [ApplicationController::class, 'updateWebhook'])->name('apps.webhook.update');
        Route::post('/apps/{app}/webhook/secret', [ApplicationController::class, 'rotateWebhookSecret'])->name('apps.webhook.secret');

        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
        Route::get('/audit/export', [AuditController::class, 'export'])->name('audit.export');

        // Static approvals routes come before the {approval} one so "new" and "workflows" are not read as an id.
        Route::get('/approvals/new', [ApprovalSubmitController::class, 'create'])->name('approvals.create');
        Route::post('/approvals', [ApprovalSubmitController::class, 'store'])->name('approvals.store');
        Route::get('/approvals/workflows', [ApprovalWorkflowController::class, 'index'])->name('approvals.workflows.index');
        Route::put('/approvals/workflows/{workflow}', [ApprovalWorkflowController::class, 'update'])->name('approvals.workflows.update');
        Route::put('/approvals/workflows/{workflow}/steps/{step}', [ApprovalWorkflowController::class, 'updateStep'])->name('approvals.workflows.steps.update');
        Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::post('/approvals/{approval}/approve', [ApprovalDecisionController::class, 'approve'])->name('approvals.approve');
        Route::post('/approvals/{approval}/reject', [ApprovalDecisionController::class, 'reject'])->name('approvals.reject');
        Route::post('/approvals/{approval}/request-info', [ApprovalDecisionController::class, 'requestInfo'])->name('approvals.requestInfo');
        Route::post('/approvals/{approval}/resubmit', [ApprovalDecisionController::class, 'resubmit'])->name('approvals.resubmit');
        Route::post('/approvals/{approval}/comment', [ApprovalDecisionController::class, 'comment'])->name('approvals.comment');
        Route::post('/approvals/{approval}/cancel', [ApprovalDecisionController::class, 'cancel'])->name('approvals.cancel');

        Route::get('/mfa', [MfaSettingsController::class, 'show'])->name('mfa.setup');
        Route::post('/mfa', [MfaSettingsController::class, 'store'])->name('mfa.enable');
        Route::delete('/mfa', [MfaSettingsController::class, 'destroy'])->name('mfa.disable');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings/policies', [SettingsController::class, 'updatePolicies'])->name('settings.policies.update');
        Route::get('/settings/email', [SettingsController::class, 'emailTemplates'])->name('settings.email.index');
        Route::put('/settings/email/{key}', [SettingsController::class, 'updateEmailTemplate'])->name('settings.email.update');

        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::put('/roles/{role}/permissions', [RoleController::class, 'permissions'])->name('roles.permissions');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

        Route::get('/organization', [OrgUnitController::class, 'index'])->name('organization.index');
        Route::post('/organization/units', [OrgUnitController::class, 'store'])->name('organization.units.store');
        Route::put('/organization/units/{unit}', [OrgUnitController::class, 'update'])->name('organization.units.update');
        Route::post('/organization/units/{unit}/move', [OrgUnitController::class, 'move'])->name('organization.units.move');
        Route::delete('/organization/units/{unit}', [OrgUnitController::class, 'destroy'])->name('organization.units.destroy');
        Route::post('/organization/units/{unit}/positions', [PositionController::class, 'store'])->name('organization.positions.store');
        Route::put('/organization/positions/{position}', [PositionController::class, 'update'])->name('organization.positions.update');
        Route::delete('/organization/positions/{position}', [PositionController::class, 'destroy'])->name('organization.positions.destroy');

        // Import routes come before the users resource so "import" is not read as a user id.
        Route::get('/users/import', [UserImportController::class, 'create'])->name('users.import.create');
        Route::post('/users/import', [UserImportController::class, 'store'])->name('users.import.store');
        Route::get('/users/import/template', [UserImportController::class, 'template'])->name('users.import.template');
        Route::get('/users/imports/{import}', [UserImportController::class, 'show'])->name('users.imports.show');
        Route::get('/users/imports/{import}/errors.csv', [UserImportController::class, 'errors'])->name('users.imports.errors');

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
