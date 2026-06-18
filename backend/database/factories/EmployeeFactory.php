<?php

namespace Database\Factories;

use App\Models\Room;
use App\Support\PlanningEngine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'type' => 'rotation',
            'offset' => 0,
            'binome' => 1,
            'day_spec' => null,
            'alt_parity' => null,
        ];
    }

    public function rotation(int $offset = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'rotation',
            'offset' => $offset,
            'binome' => $offset + 1,
            'day_spec' => null,
            'alt_parity' => null,
        ]);
    }

    public function fixedDay(?array $daySpec = null, int $altParity = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'fixed_day',
            'offset' => null,
            'binome' => null,
            'day_spec' => $daySpec ?? PlanningEngine::defaultSpec(),
            'alt_parity' => $altParity,
        ]);
    }
}
