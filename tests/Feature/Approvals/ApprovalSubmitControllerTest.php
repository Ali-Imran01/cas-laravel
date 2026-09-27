<?php

use App\Domain\Approvals\Models\ApprovalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed_approvals();
});

it('offers the six built-in workflows and marks which ones this person could plausibly request', function () {
    $w = approval_world();

    $this->actingAs($w['target'])->get('/approvals/new')->assertOk()->assertInertia(fn (Assert $p) => $p
        ->component('Approvals/New')->has('workflows', 6)
        ->where('workflows.2.code', 'app_access')->where('workflows.2.requestable', true) // anyone, for themselves
        ->where('workflows.0.requestable', false)); // role_change needs users.view, which plain staff lacks
});

it('lets anyone ask for app access to a connected app for themselves', function () {
    $w = approval_world();
    [$app] = sso_app();

    $this->actingAs($w['target'])->post('/approvals', [
        'workflow' => 'app_access',
        'payload' => ['application_id' => $app->id, 'user_id' => $w['target']->id, 'app_role' => 'Viewer'],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $request = ApprovalRequest::sole();
    expect($request->requester_id)->toBe($w['target']->id)->and($request->payload['app_role'])->toBe('Viewer');
});

it('refuses a request the handler says this person cannot make', function () {
    $w = approval_world();

    $this->actingAs($w['target'])->post('/approvals', [
        'workflow' => 'role_change',
        'payload' => ['user_id' => $w['target']->id, 'to_role' => 'staff'],
    ])->assertSessionHasErrors('workflow');

    expect(ApprovalRequest::count())->toBe(0);
});

it('rejects a workflow code that has no built-in handler', function () {
    $w = approval_world();

    $this->actingAs($w['target'])->post('/approvals', ['workflow' => 'not_a_real_workflow', 'payload' => []])
        ->assertSessionHasErrors('workflow');
});

it('reports per-field payload problems under payload.*', function () {
    $w = approval_world();

    $this->actingAs($w['divHead'])->post('/approvals', ['workflow' => 'reactivation', 'payload' => ['user_id' => 999999]])
        ->assertSessionHasErrors('payload.user_id');
});
