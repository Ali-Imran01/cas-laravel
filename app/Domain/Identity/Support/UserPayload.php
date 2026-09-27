<?php

namespace App\Domain\Identity\Support;

use App\Domain\Identity\Models\User;

/** The JSON shape the users screens receive. `can` is computed for the signed-in actor. */
class UserPayload
{
    /** @return array<string, mixed> */
    public static function make(User $user, User $actor): array
    {
        return [
            'id' => $user->id,
            'staff_id' => $user->staff_id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status->value,
            'auto_locked' => (bool) $user->locked_until?->isFuture(),
            'mfa_enabled' => $user->mfa_enabled,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'org_unit' => $user->orgUnit?->name,
            'org_unit_id' => $user->org_unit_id,
            'position' => $user->position?->title,
            'position_id' => $user->position_id,
            'role' => $user->getRoleNames()->first(),
            'can' => [
                'update' => $actor->can('update', $user),
                'lock' => $actor->can('lock', $user),
                'delete' => $actor->can('delete', $user),
            ],
        ];
    }
}
