<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserAssignment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** Moves a user to another unit/position and keeps the assignment history (transfer trail). */
class TransferUser
{
    public function __invoke(User $user, int $orgUnitId, ?int $positionId, ?CarbonInterface $startedAt = null): UserAssignment
    {
        $startedAt ??= today();

        return DB::transaction(function () use ($user, $orgUnitId, $positionId, $startedAt) {
            $user->assignments()->whereNull('ended_at')->update(['ended_at' => $startedAt->toDateString()]);

            $assignment = $user->assignments()->create([
                'org_unit_id' => $orgUnitId,
                'position_id' => $positionId,
                'started_at' => $startedAt->toDateString(),
            ]);

            $user->forceFill(['org_unit_id' => $orgUnitId, 'position_id' => $positionId])->save();

            return $assignment;
        });
    }
}
