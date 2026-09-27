<?php

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\InviteUser;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(AccessSeeder::class));

const NEW_PW = 'Fresh-Passw0rd!x9';

/** @return array{0: User, 1: string} the invited user and the token from the latest invitation */
function invited(): array
{
    Notification::fake();
    $user = User::factory()->pending()->create(['staff_id' => 'STF-80001', 'email' => 'invitee@x.test']);
    $hr = User::factory()->create();
    $hr->assignRole('hr_officer');

    test()->actingAs($hr)->post("/users/$user->id/invite")->assertSessionHasNoErrors();

    return [$user, latestInviteToken($user)];
}

function latestInviteToken(User $user): string
{
    $token = '';
    Notification::assertSentTo($user, InviteUser::class, function (InviteUser $n) use (&$token) {
        $token = $n->token;

        return true;
    });

    return $token;
}

it('activates the account when the invitee sets a valid password', function () {
    [$user, $token] = invited();
    auth()->logout();

    $this->get("/accept-invite/$token?email=invitee@x.test")->assertOk()->assertInertia(fn ($p) => $p->component('AcceptInvite')->where('token', $token));
    $this->post('/accept-invite', ['token' => $token, 'email' => 'invitee@x.test', 'password' => NEW_PW, 'password_confirmation' => NEW_PW])
        ->assertRedirect(route('login'));

    $user->refresh();
    expect($user->status)->toBe(UserStatus::Active)->and($user->email_verified_at)->not->toBeNull()->and($user->passwordHistories)->toHaveCount(1);

    $this->post('/login', ['identifier' => 'STF-80001', 'password' => NEW_PW])->assertRedirect(route('dashboard'));
});

it('rejects weak passwords and wrong emails without spending the token', function () {
    [$user, $token] = invited();
    auth()->logout();

    $this->post('/accept-invite', ['token' => $token, 'email' => 'invitee@x.test', 'password' => 'weak', 'password_confirmation' => 'weak'])->assertSessionHasErrors('password');
    $this->post('/accept-invite', ['token' => $token, 'email' => 'someone@else.test', 'password' => NEW_PW, 'password_confirmation' => NEW_PW])->assertSessionHasErrors('email');
    expect($user->refresh()->status)->toBe(UserStatus::Pending);

    $this->post('/accept-invite', ['token' => $token, 'email' => 'invitee@x.test', 'password' => NEW_PW, 'password_confirmation' => NEW_PW])->assertSessionHasNoErrors();
});

it('works only once', function () {
    [, $token] = invited();
    auth()->logout();
    $payload = ['token' => $token, 'email' => 'invitee@x.test', 'password' => NEW_PW, 'password_confirmation' => NEW_PW];

    $this->post('/accept-invite', $payload)->assertSessionHasNoErrors();
    $this->post('/accept-invite', $payload)->assertSessionHasErrors('email');
});

it('lasts 3 days and no longer', function () {
    [, $token] = invited();
    auth()->logout();
    $payload = ['token' => $token, 'email' => 'invitee@x.test', 'password' => NEW_PW, 'password_confirmation' => NEW_PW];

    $this->travel(4)->days();
    $this->post('/accept-invite', $payload)->assertSessionHasErrors('email');
});

it('lets a resent invitation replace the earlier link', function () {
    [$user, $first] = invited();
    $this->post("/users/$user->id/invite")->assertSessionHasNoErrors();
    Notification::assertSentToTimes($user, InviteUser::class, 2);
    auth()->logout();

    $this->post('/accept-invite', ['token' => $first, 'email' => 'invitee@x.test', 'password' => NEW_PW, 'password_confirmation' => NEW_PW])
        ->assertSessionHasErrors('email');
});

it('refuses an invitation for an account that is no longer pending', function () {
    [$user, $token] = invited();
    $user->forceFill(['status' => UserStatus::Inactive])->save();
    auth()->logout();

    $this->post('/accept-invite', ['token' => $token, 'email' => 'invitee@x.test', 'password' => NEW_PW, 'password_confirmation' => NEW_PW])
        ->assertSessionHasErrors('email');
    expect($user->refresh()->status)->toBe(UserStatus::Inactive);
});

it('only resends to accounts that are still waiting', function () {
    $active = User::factory()->create();
    $hr = User::factory()->create();
    $hr->assignRole('hr_officer');

    $this->actingAs($hr)->post("/users/$active->id/invite")->assertSessionHasErrors('user');
});

it('does not let the reset-password broker honour an invitation token after an hour, nor vice versa', function () {
    [$user, $token] = invited();
    auth()->logout();

    // A 3-day invitation token is not accepted by the 60-minute password-reset endpoint.
    $this->travel(2)->hours();
    $this->post('/reset-password', ['token' => $token, 'email' => 'invitee@x.test', 'password' => NEW_PW, 'password_confirmation' => NEW_PW])
        ->assertSessionHasErrors('email');
    expect($user->refresh()->status)->toBe(UserStatus::Pending);
});
