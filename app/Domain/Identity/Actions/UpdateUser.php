<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Audit;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateUser
{
    public function __construct(private readonly EnsureAdminRemains $ensureAdminRemains) {}

    /**
     * @param  array{staff_id: string, name: string, email: string, role?: string|null}  $data  `role` absent = leave roles alone
     *
     * @throws ValidationException
     */
    public function __invoke(User $actor, User $target, array $data): User
    {
        return DB::transaction(function () use ($actor, $target, $data) {
            $before = $this->snapshot($target);
            $target->fill(['staff_id' => $data['staff_id'], 'name' => $data['name'], 'email' => $data['email']])->save();

            if (array_key_exists('role', $data)) {
                $new = $data['role'] === null || $data['role'] === '' ? [] : [$data['role']];

                if ($target->getRoleNames()->all() !== $new) {
                    // Nobody edits their own roles, and the last super admin cannot lose the role.
                    if ($actor->is($target)) {
                        throw ValidationException::withMessages(['role' => __('cas.users.self_action')]);
                    }
                    if (! in_array('super_admin', $new, true)) {
                        ($this->ensureAdminRemains)($target);
                    }
                    $target->syncRoles($new);
                }
            }

            [$old, $new] = Audit::diff($before, $this->snapshot($target));
            if ($new !== []) {
                Audit::record('UPDATE', "Updated user {$target->staff_id}", $target, $old, $new);
            }

            return $target;
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(User $user): array
    {
        return ['staff_id' => $user->staff_id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->getRoleNames()->first()];
    }
}
