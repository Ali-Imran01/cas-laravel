<?php

use App\Domain\Identity\Actions\ChangeUserStatus;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\InviteUser;
use App\Domain\Organization\Models\OrgUnit;
use App\Domain\Organization\Models\Position;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(AccessSeeder::class));

function as_role(string $role, array $attrs = []): User
{
    $user = User::factory()->create($attrs);
    $user->assignRole($role);

    return $user;
}

function newUserPayload(array $over = []): array
{
    return $over + ['staff_id' => 'stf-50001', 'name' => 'New Person', 'email' => 'New.Person@X.test', 'role' => 'staff'];
}

it('creates an invited account with an open assignment and mails the invitation', function () {
    Notification::fake();
    $hr = as_role('hr_officer');
    $unit = OrgUnit::factory()->create();
    $position = Position::factory()->create(['org_unit_id' => $unit->id]);

    $this->actingAs($hr)->post('/users', newUserPayload(['org_unit_id' => $unit->id, 'position_id' => $position->id]))
        ->assertSessionHasNoErrors();

    $user = User::where('email', 'new.person@x.test')->firstOrFail();
    expect($user->staff_id)->toBe('STF-50001')
        ->and($user->status)->toBe(UserStatus::Pending)
        ->and($user->password)->toBeNull()
        ->and($user->created_by)->toBe($hr->id)
        ->and($user->getRoleNames()->all())->toBe(['staff'])
        ->and($user->org_unit_id)->toBe($unit->id)
        ->and($user->assignments()->whereNull('ended_at')->count())->toBe(1);
    Notification::assertSentTo($user, InviteUser::class);
});

it('validates the new user, reserving staff ids and emails even of deleted users', function () {
    $hr = as_role('hr_officer');
    $gone = User::factory()->create(['staff_id' => 'STF-50001', 'email' => 'taken@x.test']);
    $gone->delete();
    $other = OrgUnit::factory()->create();
    $position = Position::factory()->create();

    $this->actingAs($hr)->post('/users', newUserPayload(['email' => 'taken@x.test']))->assertSessionHasErrors(['staff_id', 'email']);
    $this->post('/users', newUserPayload(['staff_id' => 'bad id!', 'email' => 'nope']))->assertSessionHasErrors(['staff_id', 'email']);
    $this->post('/users', newUserPayload(['staff_id' => 'STF-1', 'email' => 'a@x.test', 'org_unit_id' => $other->id, 'position_id' => $position->id]))
        ->assertSessionHasErrors('position_id'); // position belongs to a different unit
});

it('forbids creating users without users.create', function (string $role) {
    $this->actingAs(as_role($role))->post('/users', newUserPayload())->assertForbidden();
    $this->get('/users/create')->assertForbidden();
})->with(['dept_head', 'staff']);

it('stops a non-admin handing out roles above their own', function () {
    Notification::fake();
    $this->actingAs(as_role('hr_officer'));

    foreach (['super_admin', 'dept_head'] as $role) { // dept_head has permissions hr_officer lacks
        $this->post('/users', newUserPayload(['role' => $role]))->assertSessionHasErrors('role');
    }
    $this->post('/users', newUserPayload(['role' => 'staff']))->assertSessionHasNoErrors();
    expect(User::role('super_admin')->count())->toBe(0);
});

it('lets a super admin hand out any role', function () {
    Notification::fake();

    $this->actingAs(as_role('super_admin'))->post('/users', newUserPayload(['role' => 'super_admin']))->assertSessionHasNoErrors();

    expect(User::where('email', 'new.person@x.test')->first()->hasRole('super_admin'))->toBeTrue();
});

it('edits a user, and lets an unchanged role through even if the actor could not assign it', function () {
    $target = as_role('dept_head', ['staff_id' => 'STF-60001']);

    $this->actingAs(as_role('hr_officer'))->put("/users/$target->id", [
        'staff_id' => 'STF-60001', 'name' => 'Renamed', 'email' => $target->email, 'role' => 'dept_head',
    ])->assertSessionHasNoErrors();

    expect($target->refresh()->name)->toBe('Renamed')->and($target->hasRole('dept_head'))->toBeTrue();
});

it('keeps other admins and the own role off limits', function () {
    $hr = as_role('hr_officer');
    $admin = as_role('super_admin');
    $this->actingAs($hr);

    $this->get("/users/$admin->id/edit")->assertForbidden();
    $this->put("/users/$admin->id", ['staff_id' => $admin->staff_id, 'name' => 'x', 'email' => $admin->email])->assertForbidden();
    $this->post("/users/$admin->id/lock")->assertForbidden();
    $this->delete("/users/$admin->id")->assertForbidden();

    $this->actingAs(as_role('super_admin'))->put("/users/$hr->id", ['staff_id' => $hr->staff_id, 'name' => 'HR', 'email' => $hr->email, 'role' => 'staff'])
        ->assertSessionHasNoErrors();
    $me = as_role('super_admin');
    $this->actingAs($me)->put("/users/$me->id", ['staff_id' => $me->staff_id, 'name' => 'Me', 'email' => $me->email, 'role' => 'staff'])
        ->assertSessionHasErrors('role');
    expect($me->refresh()->hasRole('super_admin'))->toBeTrue();
});

it('locks, unlocks, deactivates and reactivates, cutting off sessions', function () {
    $hr = as_role('hr_officer');
    $target = User::factory()->create(['staff_id' => 'STF-70001', 'password' => 'Old-Passw0rd!x1']);
    DB::table('sessions')->insert(['id' => 'device-1', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()]);
    $this->actingAs($hr);

    $this->post("/users/$target->id/lock")->assertSessionHasNoErrors();
    expect($target->refresh()->status)->toBe(UserStatus::Locked)->and(DB::table('sessions')->where('user_id', $target->id)->exists())->toBeFalse();

    auth()->logout();
    $this->post('/login', ['identifier' => 'STF-70001', 'password' => 'Old-Passw0rd!x1'])->assertSessionHasErrors('identifier');
    $this->assertGuest();

    $this->actingAs($hr)->post("/users/$target->id/unlock");
    expect($target->refresh()->status)->toBe(UserStatus::Active);

    $this->post("/users/$target->id/deactivate");
    expect($target->refresh()->status)->toBe(UserStatus::Inactive);

    $this->post("/users/$target->id/reactivate");
    expect($target->refresh()->status)->toBe(UserStatus::Active);
});

it('also unlocks an automatic lockout and clears the failure counter', function () {
    $target = User::factory()->create();
    $target->forceFill(['locked_until' => now()->addMinutes(10), 'failed_login_count' => 3])->save();

    $this->actingAs(as_role('hr_officer'))->post("/users/$target->id/unlock")->assertSessionHasNoErrors();

    expect($target->refresh()->locked_until)->toBeNull()->and($target->failed_login_count)->toBe(0);
});

it('refuses status changes that make no sense', function () {
    $hr = as_role('hr_officer');
    $target = User::factory()->create();
    $this->actingAs($hr);

    $this->post("/users/$target->id/unlock")->assertSessionHasErrors('user'); // not locked
    $this->post("/users/$target->id/reactivate")->assertSessionHasErrors('user'); // already active
    $this->post("/users/$hr->id/lock")->assertSessionHasErrors('user'); // yourself
    $this->post("/users/$hr->id/deactivate")->assertSessionHasErrors('user');
    expect($hr->refresh()->status)->toBe(UserStatus::Active);
});

it('sends a reactivated account that never set a password back to invited', function () {
    $target = User::factory()->pending()->create();
    $target->forceFill(['status' => UserStatus::Inactive])->save();

    $this->actingAs(as_role('hr_officer'))->post("/users/$target->id/reactivate");

    expect($target->refresh()->status)->toBe(UserStatus::Pending);
});

it('never removes the last active super admin', function () {
    $last = as_role('super_admin');
    $actor = as_role('staff'); // policy is bypassed here: the rule must live in the action itself

    foreach ([UserStatus::Locked, UserStatus::Inactive] as $to) {
        expect(fn () => app(ChangeUserStatus::class)($actor, $last, $to))->toThrow(ValidationException::class);
    }

    $spare = as_role('super_admin');
    app(ChangeUserStatus::class)($actor, $last, UserStatus::Inactive); // now someone else remains
    expect($last->refresh()->status)->toBe(UserStatus::Inactive)->and($spare->refresh()->status)->toBe(UserStatus::Active);
});

it('needs users.edit for status changes', function () {
    $target = User::factory()->create();

    $this->actingAs(as_role('dept_head'))->post("/users/$target->id/lock")->assertForbidden();
    expect($target->refresh()->status)->toBe(UserStatus::Active);
});

it('soft deletes with users.delete only, clears unit headship and ends sessions', function () {
    $target = User::factory()->create();
    $unit = OrgUnit::factory()->create(['head_user_id' => $target->id]);
    DB::table('sessions')->insert(['id' => 'device-9', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs(as_role('hr_officer'))->delete("/users/$target->id")->assertForbidden();

    $admin = as_role('super_admin');
    $this->actingAs($admin)->delete("/users/$target->id")->assertRedirect(route('users.index'));

    expect(User::find($target->id))->toBeNull()->and(User::withTrashed()->find($target->id))->not->toBeNull()
        ->and($unit->refresh()->head_user_id)->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $target->id)->exists())->toBeFalse();

    $this->delete("/users/$admin->id")->assertSessionHasErrors('user'); // not yourself
});

it('records transfers as history and moves the user', function () {
    $user = User::factory()->create();
    $a = OrgUnit::factory()->create();
    $b = OrgUnit::factory()->create();
    $bPosition = Position::factory()->create(['org_unit_id' => $b->id]);
    $hr = as_role('hr_officer');
    $this->actingAs($hr);

    $this->post("/users/$user->id/transfer", ['org_unit_id' => $a->id, 'started_at' => '2026-01-01'])->assertSessionHasNoErrors();
    $this->post("/users/$user->id/transfer", ['org_unit_id' => $b->id, 'position_id' => $bPosition->id, 'started_at' => '2026-06-01'])->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->org_unit_id)->toBe($b->id)->and($user->position_id)->toBe($bPosition->id);
    $history = $user->assignments()->orderBy('id')->get();
    expect($history)->toHaveCount(2)
        ->and($history[0]->ended_at?->toDateString())->toBe('2026-06-01')
        ->and($history[1]->ended_at)->toBeNull();

    $this->get("/users/$user->id")->assertInertia(fn ($p) => $p->component('Users/Show')->has('assignments', 2)->where('assignments.0.org_unit', $b->name)->where('can.transfer', true));
});

it('rejects a transfer to the same place, a mismatched position or a future date', function () {
    $unit = OrgUnit::factory()->create();
    $user = User::factory()->create(['org_unit_id' => $unit->id]);
    $stray = Position::factory()->create();
    $this->actingAs(as_role('hr_officer'));

    $this->post("/users/$user->id/transfer", ['org_unit_id' => $unit->id])->assertSessionHasErrors('org_unit_id');
    $this->post("/users/$user->id/transfer", ['org_unit_id' => $unit->id, 'position_id' => $stray->id])->assertSessionHasErrors('position_id');
    $this->post("/users/$user->id/transfer", ['org_unit_id' => OrgUnit::factory()->create()->id, 'started_at' => now()->addDay()->toDateString()])->assertSessionHasErrors('started_at');
});

it('applies bulk actions one account at a time and reports skips', function () {
    $hr = as_role('hr_officer');
    $admin = as_role('super_admin');
    $a = User::factory()->create();
    $b = User::factory()->create();
    $inactive = User::factory()->create(['status' => UserStatus::Inactive]);

    $response = $this->actingAs($hr)->post('/users/bulk', ['action' => 'lock', 'ids' => [$a->id, $b->id, $hr->id, $admin->id, $inactive->id]]);

    expect($a->refresh()->status)->toBe(UserStatus::Locked)->and($b->refresh()->status)->toBe(UserStatus::Locked)
        ->and($admin->refresh()->status)->toBe(UserStatus::Active) // hr may not touch super admins
        ->and($hr->refresh()->status)->toBe(UserStatus::Active); // nor themselves
    $response->assertSessionHas('status', __('cas.users.bulk_result', ['done' => 2, 'skipped' => 3]));
});

it('validates bulk input', function () {
    $this->actingAs(as_role('hr_officer'));

    $this->post('/users/bulk', ['action' => 'delete', 'ids' => [1]])->assertSessionHasErrors('action');
    $this->post('/users/bulk', ['action' => 'lock', 'ids' => []])->assertSessionHasErrors('ids');
    $this->post('/users/bulk', ['action' => 'lock', 'ids' => range(1, 101)])->assertSessionHasErrors('ids');
    $target = User::factory()->create();
    $this->actingAs(as_role('staff'))->post('/users/bulk', ['action' => 'lock', 'ids' => [$target->id]])
        ->assertSessionHas('status', __('cas.users.bulk_result', ['done' => 0, 'skipped' => 1]));
});
