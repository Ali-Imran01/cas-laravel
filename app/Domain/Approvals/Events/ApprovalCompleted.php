<?php

namespace App\Domain\Approvals\Events;

use App\Domain\Approvals\Models\ApprovalRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** A request reached its end: approved (and applied), rejected or cancelled. Fired only once the transaction has committed. */
class ApprovalCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly ApprovalRequest $request) {}
}
