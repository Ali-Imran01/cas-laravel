<?php

use App\Domain\Identity\Actions\ChangePassword;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

const CURRENT = 'Old-Passw0rd!x1';

function pwUser(): User
{
    return User::factory()->create(['staff_id' => 'STF-40001', 'email' => 'pw@x.test', 'password' => CURRENT]);
}

it('changes the password when the current one and a strong new one are given', function () {
    $user = pwUser();

    $this->actingAs($user)->put('/password/change', [
        'current_password' => CURRENT,
        'password' => 'New-Passw0rd!x2',
        'password_confirmation' => 'New-Passw0rd!x2',
    ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));

    $user->refresh();
    expect(Hash::check('New-Passw0rd!x2', $user->password))->toBeTrue()
        ->and($user->passwordHistories)->toHaveCount(1);
});

it('rejects a wrong current password and weak new passwords', function (string $weak) {
    $this->actingAs(pwUser())->put('/password/change', [
        'current_password' => CURRENT,
        'password' => $weak,
        'password_confirmation' => $weak,
    ])->assertSessionHasErrors('password');
})->with(['short' => 'Ab1!', 'no symbol' => 'Abcdefghijk12345', 'no digit' => 'Abcdefghijk!!!!!', 'no upper' => 'abcdefghijk1234!']);

it('requires the current password', function () {
    $this->actingAs(pwUser())->put('/password/change', [
        'current_password' => 'wrong',
        'password' => 'New-Passw0rd!x2',
        'password_confirmation' => 'New-Passw0rd!x2',
    ])->assertSessionHasErrors('current_password');
});

it('refuses the current password and the last 5, then allows one that fell out of history', function () {
    $user = pwUser();
    $change = app(ChangePassword::class);
    $passwords = array_map(fn ($i) => "Rotate-Passw0rd!$i", range(1, 6));

    foreach ($passwords as $plain) {
        $change($user, $plain);
    }
    $user->refresh();

    expect($user->passwordHistories()->count())->toBe(5);

    foreach (array_slice($passwords, 1) as $reused) { // the last 5, including the current one
        $this->actingAs($user)->put('/password/change', [
            'current_password' => 'Rotate-Passw0rd!6',
            'password' => $reused,
            'password_confirmation' => $reused,
        ])->assertSessionHasErrors('password');
    }

    $this->actingAs($user)->put('/password/change', [
        'current_password' => 'Rotate-Passw0rd!6',
        'password' => $passwords[0], // 6th most recent: outside the window
        'password_confirmation' => $passwords[0],
    ])->assertSessionHasNoErrors();
});

it('forces a password change when flagged or expired', function () {
    $user = pwUser();
    $this->actingAs($user)->get('/')->assertOk();

    $user->forceFill(['must_change_password' => true])->save();
    $this->get('/')->assertRedirect(route('password.change'));
    $this->get('/password/change')->assertOk()->assertInertia(fn ($p) => $p->where('forced', true));

    $user->forceFill(['must_change_password' => false, 'password_changed_at' => now()->subDays(91)])->save();
    $this->get('/')->assertRedirect(route('password.change'));

    $user->forceFill(['password_changed_at' => now()->subDays(89)])->save();
    $this->get('/')->assertOk();
});

it('signs out the account\'s other sessions when the password changes', function () {
    $user = pwUser();
    DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($user)->put('/password/change', [
        'current_password' => CURRENT,
        'password' => 'New-Passw0rd!x2',
        'password_confirmation' => 'New-Passw0rd!x2',
    ]);

    expect(DB::table('sessions')->where('id', 'other-device')->exists())->toBeFalse();
});

it('resets a forgotten password through the emailed link', function () {
    Notification::fake();
    $user = pwUser();
    DB::table('sessions')->insert(['id' => 'old-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    $user->forceFill(['failed_login_count' => 3, 'locked_until' => now()->addMinutes(10)])->save();

    $this->post('/forgot-password', ['email' => 'pw@x.test'])->assertSessionHas('status');
    $token = null;
    Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$token) {
        $token = $n->token;

        return true;
    });

    $this->post('/reset-password', [
        'token' => $token, 'email' => 'pw@x.test',
        'password' => 'Reset-Passw0rd!x3', 'password_confirmation' => 'Reset-Passw0rd!x3',
    ])->assertRedirect(route('login'));

    $user->refresh();
    expect(Hash::check('Reset-Passw0rd!x3', $user->password))->toBeTrue()
        ->and($user->locked_until)->toBeNull()
        ->and(DB::table('sessions')->where('id', 'old-device')->exists())->toBeFalse();
});

it('answers the same for unknown emails and refuses reused passwords on reset', function () {
    Notification::fake();
    $user = pwUser();

    $known = $this->post('/forgot-password', ['email' => 'pw@x.test'])->getSession()->get('status');
    $unknown = $this->post('/forgot-password', ['email' => 'ghost@x.test'])->getSession()->get('status');
    expect($known)->toBe($unknown);
    Notification::assertNotSentTo(User::factory()->make(), ResetPassword::class);

    $token = null;
    Notification::assertSentTo($user, ResetPassword::class, function ($n) use (&$token) {
        $token = $n->token;

        return true;
    });
    $this->post('/reset-password', [
        'token' => $token, 'email' => 'pw@x.test',
        'password' => CURRENT, 'password_confirmation' => CURRENT,
    ])->assertSessionHasErrors('password');
});

it('rejects an invalid reset token', function () {
    pwUser();

    $this->post('/reset-password', [
        'token' => 'bad-token', 'email' => 'pw@x.test',
        'password' => 'Reset-Passw0rd!x3', 'password_confirmation' => 'Reset-Passw0rd!x3',
    ])->assertSessionHasErrors('email');
});
