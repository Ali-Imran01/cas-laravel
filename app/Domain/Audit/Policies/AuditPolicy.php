<?php

namespace App\Domain\Audit\Policies;

use App\Domain\Identity\Models\User;

/** One permission covers the audit log, the sign-in history and the CSV export. */
class AuditPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->checkPermissionTo('audit.view');
    }
}
