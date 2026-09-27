<?php

namespace App\Domain\Approvals\Actions;

use App\Domain\Approvals\Enums\ApproverType;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Organization\Enums\OrgUnitType;
use App\Domain\Organization\Models\OrgUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Turns a workflow step ("Head of Unit", "HR Officer") into the actual people who can decide right now.
 * Nobody approves their own request or a change to their own account, so those people are never returned.
 */
class ApproverResolver
{
    /**
     * @param  array{approver_type: string, approver_role_id: int|null, approver_user_id: int|null}  $step
     * @param  array<string, mixed>  $payload  the request payload: a `user_id` in it names the person the change is about
     * @return Collection<int, User>
     */
    public function resolve(array $step, User $requester, array $payload): Collection
    {
        $subjectId = isset($payload['user_id']) ? (int) $payload['user_id'] : null;
        $excluded = array_filter([$requester->id, $subjectId]);

        $candidates = match (ApproverType::from($step['approver_type'])) {
            ApproverType::Role => $this->byRole($step['approver_role_id'] ?? null),
            ApproverType::User => $this->activeUsers()->whereKey($step['approver_user_id'] ?? 0)->get(),
            ApproverType::UnitHead => $this->headAbove($this->contextUnit($requester, $subjectId), null, $excluded),
            ApproverType::DivisionHead => $this->headAbove($this->contextUnit($requester, $subjectId), OrgUnitType::Division, $excluded),
        };

        return $candidates->reject(fn (User $u) => in_array($u->id, $excluded, true))->values();
    }

    /** @return Collection<int, User> */
    private function byRole(?int $roleId): Collection
    {
        return $roleId === null ? new Collection : $this->activeUsers()->whereHas('roles', fn ($q) => $q->whereKey($roleId))->get();
    }

    /** The unit the change concerns: the affected person's unit if there is one, otherwise the requester's. */
    private function contextUnit(User $requester, ?int $subjectId): ?int
    {
        $subjectUnit = $subjectId ? User::query()->whereKey($subjectId)->value('org_unit_id') : null;

        return $subjectUnit ?? $requester->org_unit_id;
    }

    /**
     * The nearest head at or above a unit (optionally of a given unit type), skipping anyone excluded and
     * anyone inactive, so a request never stalls because the closest head is the requester.
     *
     * @param  list<int>  $excluded
     * @return Collection<int, User>
     */
    private function headAbove(?int $unitId, ?OrgUnitType $type, array $excluded): Collection
    {
        $unit = $unitId ? OrgUnit::query()->find($unitId) : null;

        if ($unit === null) {
            return new Collection;
        }

        $chain = OrgUnit::query()->where('_lft', '<=', $unit->_lft)->where('_rgt', '>=', $unit->_rgt)->orderByDesc('_lft')->get();

        foreach ($chain as $candidate) {
            if (($type !== null && $candidate->type !== $type) || $candidate->head_user_id === null || in_array($candidate->head_user_id, $excluded, true)) {
                continue;
            }

            $head = $this->activeUsers()->find($candidate->head_user_id);
            if ($head !== null) {
                return new Collection([$head]);
            }
        }

        return new Collection;
    }

    /** @return Builder<User> */
    private function activeUsers()
    {
        return User::query()->where('status', UserStatus::Active->value);
    }
}
