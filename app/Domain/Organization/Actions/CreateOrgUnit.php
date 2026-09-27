<?php

namespace App\Domain\Organization\Actions;

use App\Domain\Audit\Audit;
use App\Domain\Organization\Enums\OrgUnitType;
use App\Domain\Organization\Models\OrgUnit;

class CreateOrgUnit
{
    public function __construct(private readonly EnsureValidPlacement $placement) {}

    /** @param array{parent_id?: int|null, type: string, code: string, name: string, cost_centre?: string|null} $data */
    public function __invoke(array $data): OrgUnit
    {
        $parent = isset($data['parent_id']) ? OrgUnit::query()->findOrFail($data['parent_id']) : null;
        ($this->placement)(OrgUnitType::from($data['type']), $parent);

        $unit = OrgUnit::create([
            'parent_id' => $parent?->id,
            'type' => $data['type'],
            'code' => $data['code'],
            'name' => $data['name'],
            'cost_centre' => $data['cost_centre'] ?? null,
        ]);

        Audit::record('CREATE', "Created unit {$unit->code}", $unit, [], [
            'code' => $unit->code, 'name' => $unit->name, 'type' => $data['type'], 'parent_id' => $parent?->id, 'cost_centre' => $unit->cost_centre,
        ]);

        return $unit;
    }
}
