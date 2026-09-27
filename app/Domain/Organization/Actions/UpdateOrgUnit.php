<?php

namespace App\Domain\Organization\Actions;

use App\Domain\Audit\Audit;
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

        $before = $this->snapshot($unit);

        $unit->fill([
            'type' => $data['type'],
            'code' => $data['code'],
            'name' => $data['name'],
            'cost_centre' => $data['cost_centre'] ?? null,
            'is_active' => $data['is_active'],
            'head_user_id' => $data['head_user_id'] ?? null,
        ])->save();

        [$old, $new] = Audit::diff($before, $this->snapshot($unit));
        if ($new !== []) {
            Audit::record('UPDATE', "Updated unit {$unit->code}", $unit, $old, $new);
        }

        return $unit;
    }

    /** @return array<string, mixed> */
    private function snapshot(OrgUnit $unit): array
    {
        return [
            'type' => $unit->type->value, 'code' => $unit->code, 'name' => $unit->name, 'cost_centre' => $unit->cost_centre,
            'is_active' => $unit->is_active, 'head_user_id' => $unit->head_user_id,
        ];
    }
}
