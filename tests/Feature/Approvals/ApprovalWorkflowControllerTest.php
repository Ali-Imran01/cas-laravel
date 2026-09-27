<?php

use App\Domain\Approvals\Models\ApprovalWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed_approvals();
});

it('is closed to everyone without approvals.edit, dept_head included', function (string $role) {
    $w = approval_world();
    $workflow = workflow('reactivation');

    $this->actingAs(user_with($role))->get('/approvals/workflows')->assertForbidden();
    $this->actingAs($w['head'])->put("/approvals/workflows/$workflow->id", ['is_active' => true, 'allow_api' => false])->assertForbidden();
})->with(['staff', 'hr_officer', 'dept_head']);

it('lists every workflow with its steps', function () {
    $w = approval_world();

    $this->actingAs($w['admin'])->get('/approvals/workflows')->assertOk()->assertInertia(fn (Assert $p) => $p
        ->component('Approvals/Workflows')->has('workflows', 6)
        ->has('workflows.0.steps')->where('workflows.0.has_handler', true));
});

it('toggles a workflow active or inactive', function () {
    $w = approval_world();
    $workflow = workflow('reactivation');

    $this->actingAs($w['admin'])->put("/approvals/workflows/$workflow->id", ['is_active' => false, 'allow_api' => false])->assertSessionHasNoErrors();

    expect($workflow->refresh()->is_active)->toBeFalse();
});

it('never lets a built-in workflow be opened to connected apps, whoever asks', function () {
    $w = approval_world();
    $workflow = workflow('reactivation');

    $this->actingAs($w['admin'])->put("/approvals/workflows/$workflow->id", ['is_active' => true, 'allow_api' => true])->assertSessionHasErrors('allow_api');

    expect($workflow->refresh()->allow_api)->toBeFalse();
});

it('lets a custom (non-built-in) workflow be opened to connected apps', function () {
    $w = approval_world();
    $custom = ApprovalWorkflow::create(['code' => 'ticket_escalation', 'name' => 'Ticket escalation', 'is_active' => true]);

    $this->actingAs($w['admin'])->put("/approvals/workflows/$custom->id", ['is_active' => true, 'allow_api' => true])->assertSessionHasNoErrors();

    expect($custom->refresh()->allow_api)->toBeTrue();
});

it("changes a step's approver and SLA, clearing whichever id does not apply", function () {
    $w = approval_world();
    $workflow = workflow('reactivation');
    $step = $workflow->steps()->where('level', 1)->sole();
    $hrRoleId = Role::findByName('hr_officer', 'web')->id;

    $this->actingAs($w['admin'])->put("/approvals/workflows/$workflow->id/steps/$step->id", [
        'approver_type' => 'role', 'approver_role_id' => $hrRoleId, 'sla_hours' => 24,
    ])->assertSessionHasNoErrors();

    $step->refresh();
    expect($step->approver_type->value)->toBe('role')->and($step->approver_role_id)->toBe($hrRoleId)
        ->and($step->approver_user_id)->toBeNull()->and($step->sla_hours)->toBe(24);

    $this->actingAs($w['admin'])->put("/approvals/workflows/$workflow->id/steps/$step->id", [
        'approver_type' => 'unit_head', 'sla_hours' => 48,
    ])->assertSessionHasNoErrors();
    expect($step->refresh()->approver_role_id)->toBeNull();
});

it('requires the matching id when the approver type needs one', function () {
    $w = approval_world();
    $workflow = workflow('reactivation');
    $step = $workflow->steps()->where('level', 1)->sole();

    $this->actingAs($w['admin'])->put("/approvals/workflows/$workflow->id/steps/$step->id", ['approver_type' => 'role', 'sla_hours' => 24])
        ->assertSessionHasErrors('approver_role_id');
    $this->actingAs($w['admin'])->put("/approvals/workflows/$workflow->id/steps/$step->id", ['approver_type' => 'user', 'sla_hours' => 24])
        ->assertSessionHasErrors('approver_user_id');
});

it('refuses a step that does not belong to the workflow in the URL', function () {
    $w = approval_world();
    $other = workflow('reactivation')->steps()->where('level', 1)->sole();

    $this->actingAs($w['admin'])->put('/approvals/workflows/'.workflow('transfer')->id."/steps/$other->id", ['approver_type' => 'unit_head', 'sla_hours' => 24])
        ->assertNotFound();
});
