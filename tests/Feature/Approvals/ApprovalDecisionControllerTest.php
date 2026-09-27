<?php

use App\Domain\Approvals\Enums\ApprovalStatus;
use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Identity\Enums\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed_approvals();
});

/** A reactivation request (unit_head then hr_officer) with a requester who is not the unit head. */
function decidable_request($w)
{
    $inactive = user_with('staff', ['org_unit_id' => $w['unit']->id, 'status' => UserStatus::Inactive]);

    return [submit($w['divHead'], 'reactivation', ['user_id' => $inactive->id]), $inactive];
}

it('is out of bounds for someone with no relationship to the request at all', function () {
    $w = approval_world();
    [$request] = decidable_request($w);
    $bystander = user_with('staff', ['org_unit_id' => $w['unit']->id]);

    $this->actingAs($bystander)->post("/approvals/$request->id/approve")->assertForbidden();
});

it('turns down a decision from someone entitled to look but not to decide right now', function () {
    $w = approval_world();
    [$request] = decidable_request($w);

    // super_admin holds approvals.view (oversight) but is not the unit head at level 1.
    $this->actingAs($w['admin'])->post("/approvals/$request->id/approve")->assertSessionHasErrors('request');
    expect($request->refresh()->status)->toBe(ApprovalStatus::Pending);
});

it('advances a request one level per approval, and applies it on the last', function () {
    $w = approval_world();
    [$request, $inactive] = decidable_request($w);

    $this->actingAs($w['head'])->post("/approvals/$request->id/approve")->assertSessionHasNoErrors()->assertRedirect();
    expect($request->refresh()->current_level)->toBe(2)->and($request->status)->toBe(ApprovalStatus::Pending);

    $this->actingAs($w['hr'])->post("/approvals/$request->id/approve")->assertSessionHasNoErrors();
    expect($request->refresh()->status)->toBe(ApprovalStatus::Approved)->and($inactive->refresh()->status->value)->toBe('active');
});

it('requires a reason to reject or ask for more information', function () {
    $w = approval_world();
    [$request] = decidable_request($w);

    $this->actingAs($w['head'])->post("/approvals/$request->id/reject", [])->assertSessionHasErrors('comment');
    $this->actingAs($w['head'])->post("/approvals/$request->id/request-info", ['comment' => ''])->assertSessionHasErrors('comment');

    $this->actingAs($w['head'])->post("/approvals/$request->id/reject", ['comment' => 'Not eligible'])->assertSessionHasNoErrors();
    expect($request->refresh()->status)->toBe(ApprovalStatus::Rejected);
});

it('lets the requester resubmit once information is provided, and cancel while it is open', function () {
    $w = approval_world();
    [$request] = decidable_request($w);

    $this->actingAs($w['head'])->post("/approvals/$request->id/request-info", ['comment' => 'Since when?']);
    expect($request->refresh()->status)->toBe(ApprovalStatus::InfoRequested);

    // Only the requester may answer.
    $this->actingAs($w['head'])->post("/approvals/$request->id/resubmit", ['comment' => 'not me'])->assertSessionHasErrors('request');

    $this->actingAs($w['divHead'])->post("/approvals/$request->id/resubmit", ['comment' => 'Since last week'])->assertSessionHasNoErrors();
    expect($request->refresh()->status)->toBe(ApprovalStatus::Pending);

    $this->actingAs($w['divHead'])->post("/approvals/$request->id/cancel")->assertSessionHasNoErrors();
    expect($request->refresh()->status)->toBe(ApprovalStatus::Cancelled);
});

it('lets the requester or a current approver comment while the request is open', function () {
    $w = approval_world();
    [$request] = decidable_request($w);

    $this->actingAs($w['divHead'])->post("/approvals/$request->id/comment", ['comment' => 'For the record'])->assertSessionHasNoErrors();
    $this->actingAs($w['head'])->post("/approvals/$request->id/comment", ['comment' => 'Noted'])->assertSessionHasNoErrors();

    expect(ApprovalRequest::find($request->id)->actions()->where('decision', 'commented')->count())->toBe(2);
});
