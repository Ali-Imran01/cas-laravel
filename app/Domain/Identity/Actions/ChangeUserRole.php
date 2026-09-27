<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Apps\Actions\RevokeAppTokens;
use App\Domain\Audit\Audit;
use App\Domain\Identity\Models\User;
use Illuminate\Validation\ValidationException;

/** Sets a person's single CAS role (null = no role), with the guards every path to a role change must share. */
class ChangeUserRole
{
    public function __construct(private readonly EnsureAdminRemains $ensureAdminRemains, private readonly RevokeAppTokens $tokens) {}

    /**
     * @param  bool  $audit  false when the caller records a combined audit entry itself
     *
     * @throws ValidationException
     */
    public function __invoke(User $actor, User $target, ?string $role, bool $audit = true): bool
    {
        $new = $role === null || $role === '' ? [] : [$role];
        $old = $target->getRoleNames()->all();

        if ($old === $new) {
            return false;
        }

        // Nobody edits their own roles, and the last super admin cannot lose the role.
        if ($actor->is($target)) {
            throw ValidationException::withMessages(['role' => __('cas.users.self_action')]);
        }
        if (! in_array('super_admin', $new, true)) {
            ($this->ensureAdminRemains)($target);
        }

        $target->syncRoles($new);
        // A different role can mean losing access to some apps: cut those sessions now.
        $this->tokens->forLostAccess($target);

        if ($audit) {
            Audit::record('UPDATE', "Changed role of {$target->staff_id}", $target, ['role' => $old[0] ?? null], ['role' => $new[0] ?? null]);
        }

        return true;
    }
}
