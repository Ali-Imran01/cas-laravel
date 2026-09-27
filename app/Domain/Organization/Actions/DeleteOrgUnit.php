<?php

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\OrgUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Soft delete, refused while anything still depends on the unit. */
class DeleteOrgUnit
{
    /** @throws ValidationException */
    public function __invoke(OrgUnit $unit): void
    {
        if ($unit->children()->exists()) {
            throw ValidationException::withMessages(['unit' => __('cas.org.has_children')]);
        }

        if ($unit->users()->exists()) {
            throw ValidationException::withMessages(['unit' => __('cas.org.has_users')]);
        }

        DB::transaction(function () use ($unit) {
            $unit->positions()->delete(); // nobody holds them: the users check above covers every position
            $unit->delete();
        });
    }
}
