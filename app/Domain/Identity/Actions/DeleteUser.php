<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Organization\Models\OrgUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Soft delete: keeps the row for audit and transfer history, frees nothing (staff ID and email stay reserved). */
class DeleteUser
{
    public function __construct(private readonly EnsureAdminRemains $ensureAdminRemains, private readonly RevokeAccess $revoke) {}

    /** @throws ValidationException */
    public function __invoke(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw ValidationException::withMessages(['user' => __('cas.users.self_action')]);
        }

        ($this->ensureAdminRemains)($target);

        DB::transaction(function () use ($target) {
            OrgUnit::where('head_user_id', $target->id)->update(['head_user_id' => null]);
            ($this->revoke)($target);
            $target->delete();
        });
    }
}
