<?php

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Enums\OrgUnitType;
use App\Domain\Organization\Models\OrgUnit;
use Illuminate\Validation\ValidationException;

/** Headquarters is the only root; every other kind of unit hangs under something. */
class EnsureValidPlacement
{
    /** @throws ValidationException */
    public function __invoke(OrgUnitType $type, ?OrgUnit $parent, string $field = 'parent_id'): void
    {
        if ($type === OrgUnitType::Headquarters && $parent !== null) {
            throw ValidationException::withMessages([$field => __('cas.org.hq_is_root')]);
        }

        if ($type !== OrgUnitType::Headquarters && $parent === null) {
            throw ValidationException::withMessages([$field => __('cas.org.needs_parent')]);
        }
    }
}
