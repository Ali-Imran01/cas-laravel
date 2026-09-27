<?php

namespace App\Domain\Approvals\Policies;

use App\Domain\Approvals\Actions\DecideRequest;
use App\Domain\Approvals\Models\ApprovalRequest;
use App\Domain\Identity\Models\User;

/**
 * Approvals are self-service: everyone may open the inbox and see their own requests. Seeing someone
 * else's request needs a reason: being (or having been) an approver on it, or holding the oversight
 * permission. Deciding a request is checked dynamically by the engine, not by a permission.
 */
class ApprovalPolicy
{
    public function __construct(private readonly DecideRequest $decide) {}

    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, ApprovalRequest $request): bool
    {
        return $actor->id === $request->requester_id
            || $actor->checkPermissionTo('approvals.view')
            || $this->decide->canDecide($actor, $request)
            || $request->actions()->where('actor_id', $actor->id)->exists();
    }
}
