<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\User;
use App\Domain\Settings\Models\Setting;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(AccessSeeder::class));

it('is closed to everyone without settings permissions, dept_head included', function (string $role) {
    $this->actingAs(user_with($role))->get('/settings')->assertForbidden();
    $this->actingAs(user_with($role))->put('/settings/policies', [])->assertForbidden();
})->with(['staff', 'hr_officer', 'dept_head']);

it('shows the shipped defaults until an administrator overrides them', function () {
    $this->actingAs(user_with('super_admin'))->get('/settings')->assertOk()->assertInertia(fn (Assert $p) => $p
        ->component('Settings/Index')
        ->where('policies.max_attempts', config('cas.auth.max_attempts'))
        ->where('policies.password_min_length', config('cas.auth.password_min_length'))
        ->where('can.update', true));
});

it('saves an override for every policy field and audits the change', function () {
    $admin = user_with('super_admin');
    $shippedDefault = config('cas.auth.max_attempts'); // captured before anything overrides it for the rest of this test
    $data = [
        'max_attempts' => 3, 'lockout_minutes' => 30, 'login_throttle_per_minute' => 5,
        'password_min_length' => 14, 'password_history' => 8, 'password_expiry_days' => 60,
        'mfa_pending_minutes' => 5, 'mfa_max_attempts' => 4, 'mfa_email_otp_minutes' => 5, 'recovery_codes' => 10,
    ];

    $this->actingAs($admin)->put('/settings/policies', $data)->assertSessionHasNoErrors();

    expect(Setting::find('policies')->value)->toEqual($data);
    $this->actingAs($admin)->get('/settings')->assertInertia(fn (Assert $p) => $p->where('policies', $data));

    $log = AuditLog::where('action', 'UPDATE')->where('description', 'Updated security policy settings')->sole();
    expect($log->actor_id)->toBe($admin->id)->and($log->old_values['max_attempts'])->toBe($shippedDefault)
        ->and($log->new_values['max_attempts'])->toBe(3);
});

it('rejects values outside each field\'s sane range', function (array $over, string $field) {
    $good = [
        'max_attempts' => 5, 'lockout_minutes' => 15, 'login_throttle_per_minute' => 10,
        'password_min_length' => 12, 'password_history' => 5, 'password_expiry_days' => 90,
        'mfa_pending_minutes' => 10, 'mfa_max_attempts' => 5, 'mfa_email_otp_minutes' => 10, 'recovery_codes' => 8,
    ];

    $this->actingAs(user_with('super_admin'))->put('/settings/policies', $over + $good)->assertSessionHasErrors($field);
})->with([
    'too few attempts' => [['max_attempts' => 1], 'max_attempts'],
    'too short a password' => [['password_min_length' => 4], 'password_min_length'],
    'missing field' => [['recovery_codes' => null], 'recovery_codes'],
]);

it('applies a saved override to real sign-in behaviour on the very next request', function () {
    $admin = user_with('super_admin');
    $member = User::factory()->create(['staff_id' => 'STF-30001', 'password' => 'Old-Passw0rd!x1']);

    $this->actingAs($admin)->put('/settings/policies', [
        'max_attempts' => 3, 'lockout_minutes' => 15, 'login_throttle_per_minute' => 10,
        'password_min_length' => 12, 'password_history' => 5, 'password_expiry_days' => 90,
        'mfa_pending_minutes' => 10, 'mfa_max_attempts' => 5, 'mfa_email_otp_minutes' => 10, 'recovery_codes' => 8,
    ])->assertSessionHasNoErrors();

    $this->post('/logout');

    foreach (range(1, 3) as $_) {
        $this->post('/login', ['identifier' => 'STF-30001', 'password' => 'wrong-wrong-1']);
    }

    expect($member->refresh()->locked_until)->not->toBeNull(); // locked after 3, not the shipped default of 5
});
