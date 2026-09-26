<?php

namespace Database\Factories;

use App\Domain\Organization\Enums\OrgUnitType;
use App\Domain\Organization\Models\OrgUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrgUnit>
 */
class OrgUnitFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'type' => OrgUnitType::Unit,
            'code' => strtoupper(fake()->unique()->lexify('???-???')),
            'name' => fake()->unique()->company(),
        ];
    }
}
