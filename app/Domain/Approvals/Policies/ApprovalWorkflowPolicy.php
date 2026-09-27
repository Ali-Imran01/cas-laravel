<?php

namespace App\Domain\Approvals\Policies;

use App\Domain\Identity\Models\User;

/** Configuring who approves what (steps, SLA hours, API access) is an administrative act, kept separate from deciding requests. */
class ApprovalWorkflowPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->checkPermissionTo('approvals.edit');
    }

    public function update(User $actor): bool
    {
        return $actor->checkPermissionTo('approvals.edit');
    }
}
