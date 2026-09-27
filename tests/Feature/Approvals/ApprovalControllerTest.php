<?php

use App\Domain\Approvals\Actions\DecideRequest;
use App\Domain\Identity\Enums\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed_approvals();
});

/** A reactivation request against a fresh inactive account in the given unit; needs only users.view to submit. */
function reactivation_request($w, $requester)
{
    $inactive = user_with('staff', ['org_unit_id' => $w['unit']->id, 'status' => UserStatus::Inactive]);

    return submit($requester, 'reactivation', ['user_id' => $inactive->id]);
}

it('is open to everyone, even someone with no permissions at all', function () {
    $w = approval_world();

    $this->actingAs($w['target'])->get('/approvals')->assertOk()->assertInertia(fn (Assert $p) => $p
        ->component('Approvals/Index')->where('tab', 'decide')->where('can.oversee', false)->where('can.manageWorkflows', false));
});

it('shows only the requests the signed-in person can decide on the default tab', function () {
    $w = approval_world();
    // The requester must not be the unit head themselves, or they would be excluded from their own approver list.
    $request = reactivation_request($w, $w['divHead']);

    $this->actingAs($w['head'])->get('/approvals')
        ->assertInertia(fn (Assert $p) => $p->has('requests', 1)->where('requests.0.id', $request->id));
    $this->actingAs($w['hr'])->get('/approvals')->assertInertia(fn (Assert $p) => $p->has('requests', 0)); // not their turn yet
});

it("shows only the requester's own requests on the mine tab", function () {
    $w = approval_world();
    $mine = reactivation_request($w, $w['divHead']);
    reactivation_request($w, $w['hr']);

    $this->actingAs($w['divHead'])->get('/approvals?tab=mine')
        ->assertInertia(fn (Assert $p) => $p->has('requests', 1)->where('requests.0.id', $mine->id));
});

it('keeps the "all" tab for people with the oversight permission only', function () {
    $w = approval_world();
    reactivation_request($w, $w['divHead']);

    $this->actingAs($w['target'])->get('/approvals?tab=all')->assertInertia(fn (Assert $p) => $p->where('tab', 'decide')); // staff: no approvals.view
    $this->actingAs($w['head'])->get('/approvals?tab=all')->assertInertia(fn (Assert $p) => $p->where('tab', 'all')->where('can.oversee', true)->has('requests', 1)); // dept_head has approvals.view
});

it('shows the detail only to people entitled to see the request', function () {
    $w = approval_world();
    $request = reactivation_request($w, $w['admin']);

    $this->actingAs($w['admin'])->get("/approvals?tab=mine&request=$request->id")
        ->assertInertia(fn (Assert $p) => $p->where('selected.id', $request->id)->where('selected.can.requester', true)->has('selected.actions', 1));

    // Not the requester, not an approver at this level, and holds no oversight permission: no detail leaks.
    $this->actingAs($w['target'])->get("/approvals?tab=all&request=$request->id")->assertInertia(fn (Assert $p) => $p->where('selected', null));
    // The division head is not involved either, but holds approvals.view.
    $this->actingAs($w['divHead'])->get("/approvals?tab=all&request=$request->id")->assertInertia(fn (Assert $p) => $p->where('selected.id', $request->id));
});

it('lets a current approver decide straight from the detail can flags', function () {
    $w = approval_world();
    $request = reactivation_request($w, $w['divHead']);
    app(DecideRequest::class)->approve($w['head'], $request); // now waiting on HR

    $this->actingAs($w['hr'])->get("/approvals?tab=decide&request=$request->id")
        ->assertInertia(fn (Assert $p) => $p->where('selected.can.decide', true)->where('selected.status', 'pending')->where('selected.current_level', 2));
});
