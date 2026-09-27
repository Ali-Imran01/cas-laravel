<?php

namespace App\Domain\Settings\Policies;

use App\Domain\Identity\Models\User;

class SettingsPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->checkPermissionTo('settings.view');
    }

    public function update(User $actor): bool
    {
        return $actor->checkPermissionTo('settings.edit');
    }
}
