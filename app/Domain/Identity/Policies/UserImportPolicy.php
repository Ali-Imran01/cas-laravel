<?php

namespace App\Domain\Identity\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserImport;

/** An import (and its error report) belongs to whoever started it; super admins see all. */
class UserImportPolicy
{
    public function view(User $actor, UserImport $import): bool
    {
        return $actor->hasPermissionTo('users.create') && $actor->id === $import->created_by;
    }
}
