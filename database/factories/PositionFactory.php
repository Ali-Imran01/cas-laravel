<?php

namespace Database\Factories;

use App\Domain\Organization\Models\OrgUnit;
use App\Domain\Organization\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'org_unit_id' => OrgUnit::factory(),
            'title' => fake()->jobTitle(),
            'grade' => fake()->randomElement(['F41', 'F44', 'FA29']),
        ];
    }
}
