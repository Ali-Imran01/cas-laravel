<?php

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\OrgUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Re-parents a unit together with everything below it. */
class MoveOrgUnit
{
    public function __construct(private readonly EnsureValidPlacement $placement) {}

    /** @throws ValidationException */
    public function __invoke(OrgUnit $unit, ?int $parentId): void
    {
        $parent = $parentId === null ? null : OrgUnit::query()->findOrFail($parentId);

        if ($parent !== null && OrgUnit::treeIds($unit->id)->contains($parent->id)) {
            throw ValidationException::withMessages(['parent_id' => __('cas.org.move_into_self')]);
        }

        ($this->placement)($unit->type, $parent);

        DB::transaction(function () use ($unit, $parent) {
            $parent === null ? $unit->saveAsRoot() : $unit->appendToNode($parent)->save();
        });
    }
}
