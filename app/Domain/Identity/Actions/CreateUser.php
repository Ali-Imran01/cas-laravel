<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Audit\Audit;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/** Creates an invited account: pending, no password, first assignment opened, invite mailed. */
class CreateUser
{
    public function __construct(private readonly TransferUser $transfer, private readonly SendInvite $invite) {}

    /** @param array{staff_id: string, name: string, email: string, org_unit_id?: int|null, position_id?: int|null, role?: string|null} $data */
    public function __invoke(User $actor, array $data): User
    {
        $user = DB::transaction(function () use ($actor, $data) {
            $user = new User(['staff_id' => $data['staff_id'], 'name' => $data['name'], 'email' => $data['email']]);
            $user->forceFill(['status' => UserStatus::Pending, 'created_by' => $actor->id])->save();

            if (! empty($data['role'])) {
                $user->syncRoles([$data['role']]);
            }

            if (! empty($data['org_unit_id'])) {
                ($this->transfer)($user, $data['org_unit_id'], $data['position_id'] ?? null);
            }

            return $user;
        });

        Audit::record('CREATE', "Created user {$user->staff_id}", $user, [], [
            'staff_id' => $user->staff_id, 'name' => $user->name, 'email' => $user->email,
            'role' => $data['role'] ?? null, 'org_unit_id' => $data['org_unit_id'] ?? null, 'position_id' => $data['position_id'] ?? null,
        ]);

        ($this->invite)($user);

        return $user;
    }
}
