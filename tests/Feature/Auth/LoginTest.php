<?php

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const PW = 'Old-Passw0rd!x1';

function member(array $attrs = []): User
{
    return User::factory()->create($attrs + ['staff_id' => 'STF-20001', 'email' => 'ada@x.test', 'password' => PW]);
}

it('signs in by staff id or by email, ignoring case', function (string $identifier) {
    $user = member();

    $this->post('/login', ['identifier' => $identifier, 'password' => PW])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->last_login_at)->not->toBeNull();
})->with(['stf-20001', 'STF-20001', 'ADA@X.TEST']);

it('gives the same error for a wrong password and an unknown account', function () {
    member();

    $wrong = $this->post('/login', ['identifier' => 'STF-20001', 'password' => 'nope-nope-nope'])->assertSessionHasErrors('identifier');
    $unknown = $this->post('/login', ['identifier' => 'ghost@x.test', 'password' => 'nope-nope-nope'])->assertSessionHasErrors('identifier');

    expect($wrong->getSession()->get('errors')->first('identifier'))->toBe($unknown->getSession()->get('errors')->first('identifier'));
    $this->assertGuest();
});

it('locks the account for 15 minutes after 5 failed attempts, even for the right password', function () {
    $user = member();

    foreach (range(1, 5) as $_) {
        $this->post('/login', ['identifier' => 'STF-20001', 'password' => 'wrong-wrong-1']);
    }

    expect($user->refresh()->locked_until)->not->toBeNull()->and($user->failed_login_count)->toBe(0);

    $this->post('/login', ['identifier' => 'STF-20001', 'password' => PW])->assertSessionHasErrors('identifier');
    $this->assertGuest();

    $this->travel(16)->minutes();

    $this->post('/login', ['identifier' => 'STF-20001', 'password' => PW])->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();
});

it('resets the failure counter after a successful sign-in', function () {
    $user = member();
    $this->post('/login', ['identifier' => 'STF-20001', 'password' => 'wrong-wrong-1']);
    expect($user->refresh()->failed_login_count)->toBe(1);

    $this->post('/login', ['identifier' => 'STF-20001', 'password' => PW]);

    expect($user->refresh()->failed_login_count)->toBe(0);
});

it('refuses non-active accounts only after a correct password', function (UserStatus $status) {
    member(['status' => $status]);

    $this->post('/login', ['identifier' => 'STF-20001', 'password' => 'wrong-wrong-1'])
        ->assertSessionHasErrors(['identifier' => __('cas.auth.failed')]);
    $this->post('/login', ['identifier' => 'STF-20001', 'password' => PW])
        ->assertSessionHasErrors(['identifier' => __('cas.auth.inactive')]);
    $this->assertGuest();
})->with([UserStatus::Pending, UserStatus::Locked, UserStatus::Inactive]);

it('never signs in an account without a password (invited or SSO-only)', function () {
    User::factory()->pending()->create(['staff_id' => 'STF-20002', 'email' => 'inv@x.test']);

    $this->post('/login', ['identifier' => 'inv@x.test', 'password' => ''])->assertSessionHasErrors('password');
    $this->post('/login', ['identifier' => 'inv@x.test', 'password' => 'anything-at-all'])->assertSessionHasErrors('identifier');
    $this->assertGuest();
});

it('throttles repeated attempts from one client for one identifier', function () {
    foreach (range(1, 10) as $_) {
        $this->post('/login', ['identifier' => 'ghost@x.test', 'password' => 'nope-nope-nope']);
    }

    $this->post('/login', ['identifier' => 'ghost@x.test', 'password' => 'nope-nope-nope'])
        ->assertSessionHasErrors(['identifier' => __('cas.auth.throttle', ['seconds' => 60])]);
});

it('signs out and protects the dashboard', function () {
    $user = member();

    $this->get('/')->assertRedirect(route('login'));
    $this->actingAs($user)->get('/')->assertOk();
    $this->post('/logout')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('follows the language cookie for server messages', function () {
    member();

    $this->withUnencryptedCookie('cas_locale', 'ms')
        ->post('/login', ['identifier' => 'STF-20001', 'password' => 'wrong-wrong-1'])
        ->assertSessionHasErrors(['identifier' => trans('cas.auth.failed', [], 'ms')]);
});
