<?php

use App\Domain\Approvals\Actions\ApproverResolver;
use App\Domain\Approvals\Actions\DecideRequest;
use App\Domain\Approvals\Actions\SubmitRequest;
use App\Domain\Approvals\Enums\ApprovalDecision;
use App\Domain\Approvals\Enums\ApprovalStatus;
use App\Domain\Approvals\Events\ApprovalCompleted;
use App\Domain\Approvals\Models\ApprovalWorkflow;
use App\Domain\Approvals\Notifications\ApprovalNeeded;
use App\Domain\Approvals\Notifications\ApprovalOutcome;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\OrgUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed_approvals();
    Notification::fake();
});

function decide(): DecideRequest
{
    return app(DecideRequest::class);
}

it('submits a request with a reference, a step snapshot, a deadline and a first-level notification', function () {
    $w = approval_world();

    $request = submit($w['hr'], 'role_change', ['user_id' => $w['target']->id, 'to_role' => 'hr_officer']);

    expect($request->reference)->toBe('REQ-'.str_pad((string) $request->id, 4, '0', STR_PAD_LEFT))
        ->and($request->status)->toBe(ApprovalStatus::Pending)->and($request->current_level)->toBe(1)
        ->and($request->requester_id)->toBe($w['hr']->id)->and($request->subject_type)->toBe(User::class)->and($request->subject_id)->toBe($w['target']->id)
        ->and($request->payload)->toBe(['user_id' => $w['target']->id, 'to_role' => 'hr_officer'])
        ->and(array_column($request->steps, 'name'))->toBe(['Head of unit', 'Administrator'])
        ->and($request->due_at->diffInHours(now(), true))->toBeGreaterThan(47)->toBeLessThan(49)
        ->and($request->actions()->pluck('decision')->all())->toBe([ApprovalDecision::Submitted]);

    Notification::assertSentTo($w['head'], ApprovalNeeded::class);
    Notification::assertNotSentTo([$w['hr'], $w['target'], $w['admin']], ApprovalNeeded::class);
    expect(AuditLog::where('action', 'SUBMIT')->count())->toBe(1);
});

it('validates payloads, reporting field errors under payload.*', function (array $payload, string $field) {
    $w = approval_world();

    expect(fn () => submit($w['hr'], 'role_change', $payload))->toThrow(function (ValidationException $e) use ($field) {
        expect($e->errors())->toHaveKey($field);
    });
})->with([
    'unknown person' => [['user_id' => 999999, 'to_role' => 'staff'], 'payload.user_id'],
    'unknown role' => [['user_id' => 1, 'to_role' => 'wizard'], 'payload.to_role'],
    'missing role' => [['user_id' => 1], 'payload.to_role'],
]);

it('only lets people who may ask, ask', function () {
    $w = approval_world();
    $staff = user_with('staff');

    expect(fn () => submit($staff, 'role_change', ['user_id' => $w['target']->id, 'to_role' => 'staff']))->toThrow(ValidationException::class);
    expect(fn () => submit($staff, 'new_role', ['name' => 'x_role', 'display_name' => 'X', 'permissions' => []]))->toThrow(ValidationException::class);
});

it('refuses requests no one can approve, inactive workflows and workflows without steps', function () {
    $w = approval_world();
    $payload = ['user_id' => $w['target']->id, 'to_role' => 'hr_officer'];

    workflow('role_change')->steps()->where('level', 1)->update(['approver_type' => 'role', 'approver_role_id' => null]);
    expect(fn () => submit($w['hr'], 'role_change', $payload))->toThrow(ValidationException::class, 'Nobody can approve');

    workflow('role_change')->update(['is_active' => false]);
    expect(fn () => submit($w['hr'], 'role_change', $payload))->toThrow(ValidationException::class);

    workflow('role_change')->update(['is_active' => true]);
    workflow('role_change')->steps()->delete();
    expect(fn () => submit($w['hr'], 'role_change', $payload))->toThrow(ValidationException::class, 'no approval steps');
});

it('walks a request through its levels and puts the change into effect on the last approval', function () {
    Event::fake([ApprovalCompleted::class]);
    $w = approval_world();
    $request = submit($w['hr'], 'role_change', ['user_id' => $w['target']->id, 'to_role' => 'hr_officer']);

    decide()->approve($w['head'], $request, 'Fine by me');
    $request->refresh();
    expect($request->current_level)->toBe(2)->and($request->status)->toBe(ApprovalStatus::Pending)->and($w['target']->refresh()->hasRole('staff'))->toBeTrue();
    Notification::assertSentTo($w['admin'], ApprovalNeeded::class);
    Notification::assertNotSentTo($w['head'], ApprovalOutcome::class);

    decide()->approve($w['admin'], $request);
    $request->refresh();
    expect($request->status)->toBe(ApprovalStatus::Approved)->and($request->completed_at)->not->toBeNull()->and($request->due_at)->toBeNull()
        ->and($w['target']->refresh()->getRoleNames()->all())->toBe(['hr_officer'])
        ->and($request->actions()->pluck('decision')->all())->toBe([ApprovalDecision::Submitted, ApprovalDecision::Approved, ApprovalDecision::Approved]);
    Notification::assertSentTo($w['hr'], ApprovalOutcome::class, fn ($n) => $n->outcome === 'approved');
    Event::assertDispatched(ApprovalCompleted::class, fn ($e) => $e->request->id === $request->id);
    expect(AuditLog::where('action', 'APPROVE')->count())->toBe(2)->and(AuditLog::where('action', 'UPDATE')->latest('id')->first()->actor_id)->toBe($w['admin']->id);
});

it('restarts the deadline at each level', function () {
    $w = approval_world();
    workflow('role_change')->steps()->where('level', 2)->update(['sla_hours' => 10]);
    $request = submit($w['hr'], 'role_change', ['user_id' => $w['target']->id, 'to_role' => 'staff']);

    $this->travel(5)->hours();
    decide()->approve($w['head'], $request);

    expect($request->refresh()->due_at->diffInHours(now(), true))->toBeGreaterThan(9)->toBeLessThan(11);
});

it('never lets anyone approve their own request or a change to their own account', function () {
    $w = approval_world();

    // The requester is also the person the change is about, and also the closest head: all of them are skipped.
    $own = submit($w['head'], 'role_change', ['user_id' => $w['head']->id, 'to_role' => 'staff']);
    expect(decide()->approvers($own)->pluck('id')->all())->toBe([$w['divHead']->id]); // next head up, not the head himself
    expect(fn () => decide()->approve($w['head'], $own))->toThrow(ValidationException::class, 'not an approver');

    // Nobody above the requester means nobody can be asked at all.
    expect(fn () => submit($w['divHead'], 'role_change', ['user_id' => $w['divHead']->id, 'to_role' => 'staff']))->toThrow(ValidationException::class);
});

it('refuses decisions from people who are not approvers for the current level', function () {
    $w = approval_world();
    $request = submit($w['hr'], 'role_change', ['user_id' => $w['target']->id, 'to_role' => 'staff']);

    foreach ([$w['hr'], $w['admin'], $w['target'], $w['divHead'], user_with('staff')] as $outsider) {
        expect(fn () => decide()->approve($outsider, $request))->toThrow(ValidationException::class);
    }
    expect($request->refresh()->current_level)->toBe(1);

    decide()->approve($w['head'], $request);
    expect(fn () => decide()->approve($w['head'], $request))->toThrow(ValidationException::class); // level 2 belongs to the admin
});

it('rejects with a required reason and ends the request without touching anything', function () {
    Event::fake([ApprovalCompleted::class]);
    $w = approval_world();
    $request = submit($w['hr'], 'role_change', ['user_id' => $w['target']->id, 'to_role' => 'hr_officer']);

    expect(fn () => decide()->reject($w['head'], $request, '  '))->toThrow(ValidationException::class);
    expect($request->refresh()->status)->toBe(ApprovalStatus::Pending);

    decide()->reject($w['head'], $request, 'Not this quarter');
    $request->refresh();

    expect($request->status)->toBe(ApprovalStatus::Rejected)->and($request->completed_at)->not->toBeNull()
        ->and($w['target']->refresh()->getRoleNames()->all())->toBe(['staff'])
        ->and($request->actions()->reorder()->latest('id')->first()->comment)->toBe('Not this quarter');
    Notification::assertSentTo($w['hr'], ApprovalOutcome::class, fn ($n) => $n->outcome === 'rejected' && $n->comment === 'Not this quarter');
    Event::assertDispatched(ApprovalCompleted::class);
    expect(fn () => decide()->approve($w['head'], $request))->toThrow(ValidationException::class, 'not waiting');
});

it('asks for more information, stops the clock, and resumes when the requester answers', function () {
    $w = approval_world();
    $request = submit($w['hr'], 'role_change', ['user_id' => $w['target']->id, 'to_role' => 'hr_officer']);

    decide()->requestInfo($w['head'], $request, 'Why hr_officer?');
    $request->refresh();
    expect($request->status)->toBe(ApprovalStatus::InfoRequested)->and($request->due_at)->toBeNull();
    Notification::assertSentTo($w['hr'], ApprovalOutcome::class, fn ($n) => $n->outcome === 'info_requested');
    expect(fn () => decide()->approve($w['head'], $request))->toThrow(ValidationException::class);
    expect(fn () => decide()->resubmit($w['head'], $request, 'me?'))->toThrow(ValidationException::class); // only the requester answers

    $this->travel(3)->days();
    decide()->resubmit($w['hr'], $request, 'They run the rota now', ['user_id' => $w['target']->id, 'to_role' => 'staff']);
    $request->refresh();

    expect($request->status)->toBe(ApprovalStatus::Pending)->and($request->current_level)->toBe(1)
        ->and($request->payload['to_role'])->toBe('staff')
        ->and($request->due_at->isFuture())->toBeTrue()->and($request->isOverdue())->toBeFalse()
        ->and($request->actions()->pluck('decision')->all())->toContain(ApprovalDecision::InfoRequested, ApprovalDecision::Resubmitted);
    expect(fn () => decide()->resubmit($w['hr'], $request))->toThrow(ValidationException::class); // nothing left to answer

    // A corrected payload is validated again.
    decide()->requestInfo($w['head'], $request, 'Again?');
    expect(fn () => decide()->resubmit($w['hr'], $request, null, ['user_id' => 999999, 'to_role' => 'staff']))->toThrow(ValidationException::class);
    expect($request->refresh()->status)->toBe(ApprovalStatus::InfoRequested);
});

it('lets only the requester withdraw an open request', function () {
    Event::fake([ApprovalCompleted::class]);
    $w = approval_world();
    $request = submit($w['hr'], 'role_change', ['user_id' => $w['target']->id, 'to_role' => 'staff']);

    expect(fn () => decide()->cancel($w['head'], $request))->toThrow(ValidationException::class);
    expect(fn () => decide()->cancel($w['admin'], $request))->toThrow(ValidationException::class);

    decide()->cancel($w['hr'], $request, 'Changed my mind');
    expect($request->refresh()->status)->toBe(ApprovalStatus::Cancelled)->and($request->completed_at)->not->toBeNull();
    Event::assertDispatched(ApprovalCompleted::class);
    expect(fn () => decide()->cancel($w['hr'], $request))->toThrow(ValidationException::class);
    expect(fn () => decide()->approve($w['head'], $request))->toThrow(ValidationException::class);

    $done = submit($w['hr'], 'role_change', ['user_id' => $w['target']->id, 'to_role' => 'staff']);
    decide()->approve($w['head'], $done);
    decide()->approve($w['admin'], $done);
    expect(fn () => decide()->cancel($w['hr'], $done))->toThrow(ValidationException::class); // too late
});

it('lets the requester and current approvers comment while open, and nobody else', function () {
    $w = approval_world();
    $request = submit($w['hr'], 'role_change', ['user_id' => $w['target']->id, 'to_role' => 'staff']);

    decide()->comment($w['hr'], $request, 'Context');
    decide()->comment($w['head'], $request, 'Question');
    expect($request->actions()->where('decision', ApprovalDecision::Commented)->count())->toBe(2);

    foreach ([$w['admin'], $w['target'], user_with('staff')] as $outsider) {
        expect(fn () => decide()->comment($outsider, $request, 'hi'))->toThrow(ValidationException::class);
    }
    expect(fn () => decide()->comment($w['hr'], $request, ''))->toThrow(ValidationException::class);
});

it('checks at the last level that the approver may make the change, and leaves the request untouched if not', function () {
    $w = approval_world();
    // HR can approve at the last level here, but cannot hand out the dept_head role (it holds permissions HR lacks).
    workflow('role_change')->steps()->where('level', 2)->update(['approver_role_id' => $w['hr']->roles->first()->id]);
    $other = user_with('hr_officer');
    $request = submit($other, 'role_change', ['user_id' => $w['target']->id, 'to_role' => 'dept_head']);
    decide()->approve($w['head'], $request);

    expect(fn () => decide()->approve($w['hr'], $request))->toThrow(ValidationException::class, 'authority');

    $request->refresh();
    expect($request->status)->toBe(ApprovalStatus::Pending)->and($request->current_level)->toBe(2)
        ->and($w['target']->refresh()->getRoleNames()->all())->toBe(['staff'])
        ->and($request->actions()->where('decision', ApprovalDecision::Approved)->count())->toBe(1); // the failed approval left no trace
});

it('does not advance to a level nobody can take', function () {
    $w = approval_world();
    $request = submit($w['hr'], 'role_change', ['user_id' => $w['target']->id, 'to_role' => 'staff']);
    $w['admin']->forceFill(['status' => UserStatus::Inactive])->save(); // the only administrator

    expect(fn () => decide()->approve($w['head'], $request))->toThrow(ValidationException::class, 'Nobody can approve');

    $request->refresh();
    expect($request->current_level)->toBe(1)->and($request->actions()->where('decision', ApprovalDecision::Approved)->count())->toBe(0);
});

it('keeps in-flight requests on the steps they were submitted with', function () {
    $w = approval_world();
    $request = submit($w['hr'], 'role_change', ['user_id' => $w['target']->id, 'to_role' => 'staff']);

    workflow('role_change')->steps()->delete(); // an admin rebuilds the workflow while the request is open

    decide()->approve($w['head'], $request);
    decide()->approve($w['admin'], $request);
    expect($request->refresh()->status)->toBe(ApprovalStatus::Approved);
});

it('resolves unit heads upwards, division heads by type, named people and roles, skipping inactive ones', function () {
    $w = approval_world();
    $resolve = fn (array $step, User $requester, array $payload = []) => app(ApproverResolver::class)->resolve($step + ['approver_role_id' => null, 'approver_user_id' => null], $requester, $payload)->pluck('id')->all();
    $bare = OrgUnit::factory()->create(['parent_id' => $w['unit']->id]); // no head of its own
    $member = user_with('staff', ['org_unit_id' => $bare->id]);

    expect($resolve(['approver_type' => 'unit_head'], $member))->toBe([$w['head']->id]) // walks up to UNIT
        ->and($resolve(['approver_type' => 'division_head'], $member))->toBe([$w['divHead']->id])
        ->and($resolve(['approver_type' => 'unit_head'], $member, ['user_id' => $w['target']->id]))->toBe([$w['head']->id]) // the subject's unit decides
        ->and($resolve(['approver_type' => 'user', 'approver_user_id' => $w['admin']->id], $member))->toBe([$w['admin']->id])
        ->and($resolve(['approver_type' => 'role', 'approver_role_id' => $w['admin']->roles->first()->id], $member))->toBe([$w['admin']->id]);

    $w['head']->forceFill(['status' => UserStatus::Inactive])->save();
    expect($resolve(['approver_type' => 'unit_head'], $member))->toBe([$w['divHead']->id]); // inactive heads are skipped
    $w['admin']->forceFill(['status' => UserStatus::Locked])->save();
    expect($resolve(['approver_type' => 'user', 'approver_user_id' => $w['admin']->id], $member))->toBe([])
        ->and($resolve(['approver_type' => 'unit_head'], user_with('staff')))->toBe([]); // requester without a unit
});

it('carries and reports on requests for workflows that have no built-in effect', function () {
    Event::fake([ApprovalCompleted::class]);
    $w = approval_world();
    $wf = ApprovalWorkflow::create(['code' => 'asset_disposal', 'name' => 'Asset disposal', 'is_active' => true]);
    $wf->steps()->create(['level' => 1, 'name' => 'Head', 'approver_type' => 'unit_head', 'sla_hours' => 24]);

    $request = app(SubmitRequest::class)($w['target'], $wf, ['asset' => 'LAPTOP-9', 'value' => 1200], 'End of life', null, ['type' => 'Asset', 'id' => 9]);
    expect($request->subject_type)->toBe('Asset')->and($request->subject_id)->toBe(9)->and($request->payload['asset'])->toBe('LAPTOP-9');

    decide()->approve($w['head'], $request);

    expect($request->refresh()->status)->toBe(ApprovalStatus::Approved);
    Event::assertDispatched(ApprovalCompleted::class);
    expect(fn () => app(SubmitRequest::class)($w['target'], $wf, ['blob' => str_repeat('x', 11000)]))->toThrow(ValidationException::class, 'too large');
});
