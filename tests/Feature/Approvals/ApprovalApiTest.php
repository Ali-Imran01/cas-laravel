<?php

use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Approvals\Models\ApprovalWorkflow;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(AccessSeeder::class));

/** A custom workflow with no built-in handler (the only kind an app may ever submit to), approved by any super admin. */
function external_workflow(array $attrs = []): ApprovalWorkflow
{
    user_with('super_admin'); // someone has to be able to approve it, or submitting would refuse for lack of an approver

    $workflow = ApprovalWorkflow::create($attrs + ['code' => 'ticket_escalation', 'name' => 'Ticket escalation', 'is_active' => true, 'allow_api' => true]);
    $workflow->steps()->create([
        'level' => 1, 'name' => 'Administrator', 'approver_type' => 'role',
        'approver_role_id' => Role::findByName('super_admin', 'web')->id, 'sla_hours' => 48,
    ]);

    return $workflow;
}

/** An app allowed to submit approvals, a person with access to it, and a bearer token carrying the approvals scope. */
function approvals_api_token(array $appAttrs = []): array
{
    [$app, $secret] = sso_app($appAttrs + ['allowed_scopes' => ['openid', 'profile', 'email', 'approvals']]);
    $user = staff_with_access($app);
    $tokens = oidc_login($app, $secret, $user, ['scope' => 'openid approvals']);

    return [$tokens['access_token'], $app, $user];
}

it('submits an external request on behalf of the signed-in person', function () {
    external_workflow();
    [$token, $app, $user] = approvals_api_token();

    bearer($token)->postJson('/api/v1/approval-requests', [
        'workflow' => 'ticket_escalation', 'payload' => ['ticket_id' => 42], 'justification' => 'SLA breach', 'subject_type' => 'Ticket', 'subject_id' => 42,
    ])->assertCreated()->assertJsonPath('data.workflow', 'ticket_escalation')->assertJsonPath('data.status', 'pending');

    $request = ApprovalRequest::sole();
    expect($request->requester_id)->toBe($user->id)->and($request->source_application_id)->toBe($app->id)
        ->and($request->subject_type)->toBe('Ticket')->and($request->subject_id)->toBe(42)->and($request->payload)->toBe(['ticket_id' => 42]);
});

it('refuses a workflow that apps may not submit to, is inactive, or does not exist', function () {
    external_workflow(['allow_api' => false]);
    [$token] = approvals_api_token();

    bearer($token)->postJson('/api/v1/approval-requests', ['workflow' => 'ticket_escalation', 'payload' => []])->assertStatus(422)->assertJson(['error' => 'unknown_workflow']);
    bearer($token)->postJson('/api/v1/approval-requests', ['workflow' => 'no_such_workflow', 'payload' => []])->assertStatus(422)->assertJson(['error' => 'unknown_workflow']);
});

it('never accepts a built-in workflow, even if its allow_api flag was forced on directly', function () {
    ApprovalWorkflow::where('code', 'reactivation')->update(['allow_api' => true]); // bypassing UpdateWorkflow's own guard
    [$token] = approvals_api_token();

    bearer($token)->postJson('/api/v1/approval-requests', ['workflow' => 'reactivation', 'payload' => ['user_id' => 1]])
        ->assertStatus(422)->assertJson(['error' => 'unknown_workflow']);
});

it('needs the approvals scope', function () {
    [$app, $secret] = sso_app(['allowed_scopes' => ['openid', 'profile', 'email']]);
    $user = staff_with_access($app);
    $tokens = oidc_login($app, $secret, $user, ['scope' => 'openid']);

    bearer($tokens['access_token'])->postJson('/api/v1/approval-requests', ['workflow' => 'x', 'payload' => []])->assertStatus(403);
});

it('lets an app check on a request it submitted, but not one submitted by another app', function () {
    external_workflow();
    [$token] = approvals_api_token();
    [$otherToken] = approvals_api_token(['code' => 'other']);

    $created = bearer($token)->postJson('/api/v1/approval-requests', ['workflow' => 'ticket_escalation', 'payload' => ['ticket_id' => 1]])->json('data');

    bearer($token)->getJson("/api/v1/approval-requests/{$created['reference']}")->assertOk()->assertJsonPath('data.reference', $created['reference']);
    bearer($otherToken)->getJson("/api/v1/approval-requests/{$created['reference']}")->assertStatus(404);
});

it('rejects an oversized external payload', function () {
    external_workflow();
    [$token] = approvals_api_token();

    bearer($token)->postJson('/api/v1/approval-requests', ['workflow' => 'ticket_escalation', 'payload' => ['blob' => str_repeat('x', 11000)]])
        ->assertStatus(422)->assertJsonValidationErrors('payload');
});
