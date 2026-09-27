<?php

namespace App\Http\Controllers\Approvals;

use App\Domain\Approvals\Actions\DecideRequest;
use App\Domain\Approvals\Models\ApprovalRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApprovalDecisionController extends Controller
{
    public function __construct(private readonly DecideRequest $decide) {}

    public function approve(Request $request, ApprovalRequest $approval): RedirectResponse
    {
        $this->authorize('view', $approval);
        $this->decide->approve($request->user(), $approval, $request->string('comment')->trim()->value() ?: null);

        return back()->with('status', __('cas.approvals.approved'));
    }

    public function reject(Request $request, ApprovalRequest $approval): RedirectResponse
    {
        $this->authorize('view', $approval);
        $data = $request->validate(['comment' => ['required', 'string', 'max:1000']]);
        $this->decide->reject($request->user(), $approval, $data['comment']);

        return back()->with('status', __('cas.approvals.rejected'));
    }

    public function requestInfo(Request $request, ApprovalRequest $approval): RedirectResponse
    {
        $this->authorize('view', $approval);
        $data = $request->validate(['comment' => ['required', 'string', 'max:1000']]);
        $this->decide->requestInfo($request->user(), $approval, $data['comment']);

        return back()->with('status', __('cas.approvals.info_requested'));
    }

    public function resubmit(Request $request, ApprovalRequest $approval): RedirectResponse
    {
        $this->authorize('view', $approval);
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:1000'], 'payload' => ['nullable', 'array']]);
        $this->decide->resubmit($request->user(), $approval, $data['comment'] ?? null, $data['payload'] ?? null);

        return back()->with('status', __('cas.approvals.resubmitted'));
    }

    public function comment(Request $request, ApprovalRequest $approval): RedirectResponse
    {
        $this->authorize('view', $approval);
        $data = $request->validate(['comment' => ['required', 'string', 'max:1000']]);
        $this->decide->comment($request->user(), $approval, $data['comment']);

        return back()->with('status', __('cas.approvals.commented'));
    }

    public function cancel(Request $request, ApprovalRequest $approval): RedirectResponse
    {
        $this->authorize('view', $approval);
        $this->decide->cancel($request->user(), $approval, $request->string('comment')->trim()->value() ?: null);

        return back()->with('status', __('cas.approvals.cancelled'));
    }
}
