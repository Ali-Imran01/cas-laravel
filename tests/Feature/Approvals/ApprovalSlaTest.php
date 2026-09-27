<?php

use App\Domain\Approvals\Notifications\ApprovalNeeded;
use App\Domain\Identity\Enums\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    seed_approvals();
    Notification::fake();
});

it('reminds the current approver once a request runs past its SLA, and never twice', function () {
    $w = approval_world();
    $inactive = user_with('staff', ['org_unit_id' => $w['unit']->id, 'status' => UserStatus::Inactive]);
    $request = submit($w['divHead'], 'reactivation', ['user_id' => $inactive->id]); // due in 48h, approver is $w['head']
    Notification::assertSentToTimes($w['head'], ApprovalNeeded::class, 1); // the initial "approval needed" mail

    $this->artisan('approvals:check-sla')->assertSuccessful();
    Notification::assertSentToTimes($w['head'], ApprovalNeeded::class, 1); // not overdue yet
    expect($request->refresh()->overdue_notified_at)->toBeNull();

    $this->travel(49)->hours();
    $this->artisan('approvals:check-sla')->assertSuccessful();

    Notification::assertSentTo($w['head'], ApprovalNeeded::class, fn (ApprovalNeeded $n) => $n->overdue === true && $n->request->id === $request->id);
    Notification::assertSentToTimes($w['head'], ApprovalNeeded::class, 2); // the original plus one overdue reminder
    expect($request->refresh()->overdue_notified_at)->not->toBeNull();

    $this->artisan('approvals:check-sla')->assertSuccessful();
    Notification::assertSentToTimes($w['head'], ApprovalNeeded::class, 2); // still just the one reminder
});

it('leaves a request alone until its SLA has actually passed', function () {
    $w = approval_world();
    $inactive = user_with('staff', ['org_unit_id' => $w['unit']->id, 'status' => UserStatus::Inactive]);
    submit($w['divHead'], 'reactivation', ['user_id' => $inactive->id]);
    Notification::assertSentToTimes($w['head'], ApprovalNeeded::class, 1);

    $this->travel(1)->hours();
    $this->artisan('approvals:check-sla');

    Notification::assertSentToTimes($w['head'], ApprovalNeeded::class, 1); // no additional reminder yet
});
