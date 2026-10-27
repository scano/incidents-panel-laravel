<?php

namespace Database\Factories;

use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'status' => IncidentStatus::Open,
            'priority' => IncidentPriority::Medium,
            'requester_name' => fake()->name(),
            'assigned_to' => null,
        ];
    }
}
