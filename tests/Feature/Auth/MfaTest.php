<?php

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\MfaCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

const SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
const PASSWORD = 'Old-Passw0rd!x1';

function mfaUser(array $codes = ['aaaaa-11111', 'bbbbb-22222']): User
{
    $user = User::factory()->create(['staff_id' => 'STF-30001', 'email' => 'mfa@x.test', 'password' => PASSWORD]);
    $user->forceFill(['mfa_enabled' => true, 'mfa_secret' => SECRET, 'mfa_recovery_codes' => $codes])->save();

    return $user;
}

function passwordStep($test): void
{
    $test->post('/login', ['identifier' => 'STF-30001', 'password' => PASSWORD])->assertRedirect(route('mfa.challenge'));
}

it('holds the session at the challenge until a second factor passes', function () {
    mfaUser();

    passwordStep($this);

    $this->assertGuest();
    $this->get('/')->assertRedirect(route('login'));
    $this->get('/login/mfa')->assertOk();
});

it('signs in with a valid authenticator code', function () {
    $user = mfaUser();
    passwordStep($this);

    $this->post('/login/mfa', ['method' => 'totp', 'code' => (new Google2FA)->getCurrentOtp(SECRET)])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong or replayed authenticator code', function () {
    mfaUser();
    passwordStep($this);
    $good = (new Google2FA)->getCurrentOtp(SECRET);

    $this->post('/login/mfa', ['method' => 'totp', 'code' => '000000'])->assertSessionHasErrors('code');
    $this->assertGuest();

    $this->post('/login/mfa', ['method' => 'totp', 'code' => $good])->assertRedirect(route('dashboard'));
    $this->post('/logout');

    passwordStep($this);
    $this->post('/login/mfa', ['method' => 'totp', 'code' => $good])->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('accepts each recovery code once', function () {
    $user = mfaUser();
    passwordStep($this);

    $this->post('/login/mfa', ['method' => 'recovery', 'code' => 'AAAAA-11111'])->assertRedirect(route('dashboard'));
    expect($user->refresh()->mfa_recovery_codes)->toBe(['bbbbb-22222']);

    $this->post('/logout');
    passwordStep($this);
    $this->post('/login/mfa', ['method' => 'recovery', 'code' => 'aaaaa-11111'])->assertSessionHasErrors('code');
    $this->assertGuest();
});

it('signs in with an emailed code and cannot reuse it', function () {
    Notification::fake();
    $user = mfaUser();
    passwordStep($this);

    $this->post('/login/mfa/email')->assertRedirect();
    $code = null;
    Notification::assertSentTo($user, MfaCode::class, function (MfaCode $n) use (&$code) {
        $code = $n->code;

        return true;
    });

    $this->post('/login/mfa', ['method' => 'email', 'code' => $code])->assertRedirect(route('dashboard'));

    $this->post('/logout');
    passwordStep($this);
    $this->post('/login/mfa', ['method' => 'email', 'code' => $code])->assertSessionHasErrors('code');
});

it('sends at most one emailed code per minute', function () {
    Notification::fake();
    mfaUser();
    passwordStep($this);

    $this->post('/login/mfa/email')->assertSessionHasNoErrors();
    $this->post('/login/mfa/email')->assertSessionHasErrors('code');

    Notification::assertCount(1);
});

it('drops the pending sign-in after 5 wrong codes', function () {
    mfaUser();
    passwordStep($this);

    foreach (range(1, 5) as $_) {
        $this->post('/login/mfa', ['method' => 'totp', 'code' => '000000'])->assertSessionHasErrors('code');
    }

    $this->post('/login/mfa', ['method' => 'totp', 'code' => (new Google2FA)->getCurrentOtp(SECRET)])
        ->assertRedirect(route('login'));
    $this->assertGuest();
    $this->get('/login/mfa')->assertRedirect(route('login'));
});

it('expires the pending sign-in', function () {
    mfaUser();
    passwordStep($this);

    $this->travel(11)->minutes();

    $this->get('/login/mfa')->assertRedirect(route('login'));
});

it('enrols an authenticator app and returns recovery codes once', function () {
    $user = User::factory()->create(['password' => PASSWORD]);

    $this->actingAs($user)->get('/mfa')->assertOk()->assertInertia(fn ($page) => $page->component('MfaSetup')->has('secret')->has('uri'));
    $secret = session('mfa_setup_secret');

    $this->post('/mfa', ['code' => '000000'])->assertSessionHasErrors('code');
    expect($user->refresh()->mfa_enabled)->toBeFalse();

    $this->post('/mfa', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertRedirect(route('mfa.setup'))
        ->assertSessionHas('recoveryCodes');

    $user->refresh();
    expect($user->mfa_enabled)->toBeTrue()->and($user->mfa_secret)->toBe($secret)->and($user->mfa_recovery_codes)->toHaveCount(8);
});

it('needs the current password to turn MFA off', function () {
    $user = mfaUser();

    $this->actingAs($user)->delete('/mfa', ['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
    expect($user->refresh()->mfa_enabled)->toBeTrue();

    $this->delete('/mfa', ['current_password' => PASSWORD])->assertSessionHasNoErrors();
    expect($user->refresh()->mfa_enabled)->toBeFalse()->and($user->mfa_secret)->toBeNull();
});
