<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Audit;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateUser
{
    public function __construct(private readonly ChangeUserRole $changeRole) {}

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
                ($this->changeRole)($actor, $target, $data['role'], audit: false); // logged below, together with the profile changes
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
