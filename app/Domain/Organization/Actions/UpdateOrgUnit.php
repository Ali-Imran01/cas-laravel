<?php

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Enums\OrgUnitType;
use App\Domain\Organization\Models\OrgUnit;

class UpdateOrgUnit
{
    public function __construct(private readonly EnsureValidPlacement $placement) {}

    /** @param array{type: string, code: string, name: string, cost_centre?: string|null, is_active: bool, head_user_id?: int|null} $data */
    public function __invoke(OrgUnit $unit, array $data): OrgUnit
    {
        $parent = $unit->parent_id === null ? null : OrgUnit::query()->find($unit->parent_id);
        ($this->placement)(OrgUnitType::from($data['type']), $parent, 'type');

        $unit->fill([
            'type' => $data['type'],
            'code' => $data['code'],
            'name' => $data['name'],
            'cost_centre' => $data['cost_centre'] ?? null,
            'is_active' => $data['is_active'],
            'head_user_id' => $data['head_user_id'] ?? null,
        ])->save();

        return $unit;
    }
}
