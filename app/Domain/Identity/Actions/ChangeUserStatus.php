<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Audit;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use Illuminate\Validation\ValidationException;

/** Lock, unlock, deactivate and reactivate: the only place an admin changes another account's status. */
class ChangeUserStatus
{
    public function __construct(private readonly EnsureAdminRemains $ensureAdminRemains, private readonly RevokeAccess $revoke) {}

    /** @throws ValidationException */
    public function __invoke(User $actor, User $target, UserStatus $to): void
    {
        if ($actor->is($target)) {
            throw $this->refuse('self_action');
        }

        $from = $target->status;
        $locked = $from === UserStatus::Locked || $target->locked_until?->isFuture();

        match ($to) {
            UserStatus::Locked => $from === UserStatus::Active ? null : throw $this->refuse('invalid_transition'),
            UserStatus::Active => $locked || $from === UserStatus::Inactive ? null : throw $this->refuse('invalid_transition'),
            UserStatus::Inactive => $from !== UserStatus::Inactive ? null : throw $this->refuse('invalid_transition'),
            UserStatus::Pending => throw $this->refuse('invalid_transition'),
        };

        if ($to === UserStatus::Locked || $to === UserStatus::Inactive) {
            ($this->ensureAdminRemains)($target);
        }

        // An account that never set a password goes back to "invited", not straight to active.
        $status = $to === UserStatus::Active && $target->password === null ? UserStatus::Pending : $to;

        $target->forceFill(['status' => $status, 'locked_until' => null, 'failed_login_count' => 0])->save();

        if ($to !== UserStatus::Active) {
            ($this->revoke)($target);
        }

        $action = match (true) {
            $to === UserStatus::Locked => 'LOCK',
            $to === UserStatus::Inactive => 'DEACTIVATE',
            $from === UserStatus::Inactive => 'REACTIVATE',
            default => 'UNLOCK',
        };
        Audit::record($action, ucfirst(strtolower($action))." account {$target->staff_id}", $target, ['status' => $from->value], ['status' => $status->value]);
    }

    private function refuse(string $key): ValidationException
    {
        return ValidationException::withMessages(['user' => __("cas.users.$key")]);
    }
}
