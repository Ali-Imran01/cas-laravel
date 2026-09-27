<?php

use App\Domain\Approvals\Actions\DecideRequest;
use App\Domain\Approvals\Enums\ApprovalStatus;
use App\Domain\Apps\Models\Application;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\InviteUser;
use App\Domain\Organization\Models\OrgUnit;
use App\Domain\Organization\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed_approvals();
    Notification::fake();
});

/** Approves every level in order, each by the person given. */
function approve_all($request, User ...$approvers): void
{
    foreach ($approvers as $approver) {
        app(DecideRequest::class)->approve($approver, $request);
    }
}

it('creates the account when a new-account request is fully approved, attributed to the last approver', function () {
    $w = approval_world();
    $unit = $w['unit'];
    $position = Position::factory()->create(['org_unit_id' => $unit->id, 'title' => 'Analyst']);

    $request = submit($w['head'], 'new_account', [
        'staff_id' => ' stf-777 ', 'name' => ' New Hire ', 'email' => 'NEW.Hire@X.test', 'role' => 'staff',
        'org_unit_id' => $unit->id, 'position_id' => $position->id,
    ]);
    expect($request->payload['staff_id'])->toBe('STF-777')->and($request->payload['email'])->toBe('new.hire@x.test'); // normalized before it is stored
    expect(User::where('staff_id', 'STF-777')->exists())->toBeFalse(); // nothing happens until the end

    approve_all($request, $w['divHead'], $w['hr']);

    $user = User::where('staff_id', 'STF-777')->firstOrFail();
    expect($request->refresh()->status)->toBe(ApprovalStatus::Approved)
        ->and($user->status)->toBe(UserStatus::Pending)->and($user->created_by)->toBe($w['hr']->id)
        ->and($user->org_unit_id)->toBe($unit->id)->and($user->position_id)->toBe($position->id)->and($user->getRoleNames()->all())->toBe(['staff']);
    Notification::assertSentTo($user, InviteUser::class);
    expect(AuditLog::where('action', 'CREATE')->where('auditable_id', $user->id)->first()->actor_id)->toBe($w['hr']->id);
});

it('validates new-account requests like the create form does', function (array $over, string $field) {
    $w = approval_world();
    User::factory()->create(['staff_id' => 'STF-1', 'email' => 'taken@x.test']);
    $good = ['staff_id' => 'STF-9', 'name' => 'A', 'email' => 'a@x.test'];

    expect(fn () => submit($w['head'], 'new_account', $over + $good))->toThrow(function (ValidationException $e) use ($field) {
        expect($e->errors())->toHaveKey("payload.$field");
    });
})->with([
    'staff id taken' => [['staff_id' => 'stf-1'], 'staff_id'],
    'email taken' => [['email' => 'taken@x.test'], 'email'],
    'bad email' => [['email' => 'nope'], 'email'],
    'bad staff id' => [['staff_id' => 'bad id!'], 'staff_id'],
    'unknown role' => [['role' => 'wizard'], 'role'],
    'position from another unit' => [['org_unit_id' => 1, 'position_id' => 999999], 'position_id'],
]);

it('grants app access when approved, and lets people ask for themselves but not for others', function () {
    $w = approval_world();
    [$app] = sso_app();

    $request = submit($w['target'], 'app_access', ['application_id' => $app->id, 'user_id' => $w['target']->id, 'app_role' => 'Viewer']);
    expect($request->subject_type)->toBe(Application::class)->and($request->subject_id)->toBe($app->id);
    approve_all($request, $w['head'], $w['admin']);

    expect($app->users()->whereKey($w['target']->id)->first()->getRelation('pivot')->getAttribute('app_role'))->toBe('Viewer');
    expect(fn () => submit($w['target'], 'app_access', ['application_id' => $app->id, 'user_id' => $w['head']->id, 'app_role' => 'Owner']))->toThrow(ValidationException::class, 'cannot make');
    expect(fn () => submit($w['target'], 'app_access', ['application_id' => $app->id, 'user_id' => $w['target']->id, 'app_role' => '<b>x</b>']))->toThrow(ValidationException::class);
});

it('reactivates a deactivated account when approved', function () {
    $w = approval_world();
    $gone = user_with('staff', ['org_unit_id' => $w['unit']->id, 'status' => UserStatus::Inactive]);
    expect(fn () => submit($w['divHead'], 'reactivation', ['user_id' => $w['target']->id]))->toThrow(ValidationException::class); // not inactive

    $request = submit($w['divHead'], 'reactivation', ['user_id' => $gone->id]);
    approve_all($request, $w['head'], $w['hr']);

    expect($gone->refresh()->status)->toBe(UserStatus::Active)->and($request->refresh()->status)->toBe(ApprovalStatus::Approved);
});

it('performs a transfer when approved and records which request authorised it', function () {
    $w = approval_world();
    $newUnit = OrgUnit::factory()->create(['parent_id' => $w['div']->id]);
    $position = Position::factory()->create(['org_unit_id' => $newUnit->id]);

    $request = submit($w['divHead'], 'transfer', ['user_id' => $w['target']->id, 'org_unit_id' => $newUnit->id, 'position_id' => $position->id, 'effective' => '2026-01-15']);
    approve_all($request, $w['head'], $w['hr']);

    $target = $w['target']->refresh();
    $assignment = $target->assignments()->whereNull('ended_at')->firstOrFail();
    expect($target->org_unit_id)->toBe($newUnit->id)->and($target->position_id)->toBe($position->id)
        ->and($assignment->approval_request_id)->toBe($request->id)->and($assignment->started_at->toDateString())->toBe('2026-01-15');
    expect(fn () => submit($w['divHead'], 'transfer', ['user_id' => $w['target']->id, 'org_unit_id' => $w['unit']->id, 'position_id' => $position->id]))->toThrow(ValidationException::class); // position is in another unit
});

it('creates a role with its permissions when approved by an administrator', function () {
    $w = approval_world();
    $second = user_with('super_admin');

    $request = submit($w['admin'], 'new_role', ['name' => 'service_desk', 'display_name' => 'Service desk', 'permissions' => ['users.view', 'audit.view']]);
    approve_all($request, $second);

    $role = Role::findByName('service_desk', 'web');
    expect($role->permissions->pluck('name')->sort()->values()->all())->toBe(['audit.view', 'users.view'])
        ->and($role->getAttribute('is_system'))->toBeFalse()->and($request->refresh()->status)->toBe(ApprovalStatus::Approved);
    expect(fn () => submit($w['admin'], 'new_role', ['name' => 'service_desk', 'display_name' => 'Again', 'permissions' => []]))->toThrow(ValidationException::class); // key taken
    expect(fn () => submit($w['admin'], 'new_role', ['name' => 'Bad Key', 'display_name' => 'x', 'permissions' => ['nope.nope']]))->toThrow(ValidationException::class);
});

it('will not let an approver grant permissions they do not hold, or a role they cannot assign', function () {
    $w = approval_world();
    // Weaken the workflows so HR becomes the last approver of things HR has no authority over.
    $hrRole = Role::findByName('hr_officer', 'web');
    workflow('new_role')->steps()->update(['approver_role_id' => $hrRole->id]);
    workflow('role_change')->steps()->where('level', 2)->update(['approver_role_id' => $hrRole->id]);

    $role = submit($w['admin'], 'new_role', ['name' => 'escalated', 'display_name' => 'Escalated', 'permissions' => ['users.view']]);
    expect(fn () => app(DecideRequest::class)->approve($w['hr'], $role))->toThrow(ValidationException::class, 'authority'); // HR cannot create roles at all

    $change = submit($w['divHead'], 'role_change', ['user_id' => $w['target']->id, 'to_role' => 'super_admin']);
    app(DecideRequest::class)->approve($w['head'], $change);
    expect(fn () => app(DecideRequest::class)->approve($w['hr'], $change))->toThrow(ValidationException::class, 'authority');

    expect(Role::where('name', 'escalated')->exists())->toBeFalse()->and($w['target']->refresh()->hasRole('super_admin'))->toBeFalse();
});

it('leaves the request untouched when applying the change fails at the last step', function () {
    $w = approval_world();
    $request = submit($w['head'], 'new_account', ['staff_id' => 'STF-800', 'name' => 'Late', 'email' => 'late@x.test']);
    approve_all($request, $w['divHead']);
    User::factory()->create(['staff_id' => 'STF-800']); // someone took the staff ID in the meantime

    expect(fn () => app(DecideRequest::class)->approve($w['hr'], $request))->toThrow(ValidationException::class, 'no longer valid');

    expect($request->refresh()->status)->toBe(ApprovalStatus::Pending)->and($request->current_level)->toBe(2);
});
