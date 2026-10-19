<?php

namespace Database\Factories;

use App\Models\WorkplanIndicator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkplanIndicator>
 */
class WorkplanIndicatorFactory extends Factory
{
    protected $model = WorkplanIndicator::class;

    public function definition(): array
    {
        return [
            'code' => 'WP-'.fake()->unique()->numerify('2026-##'),
            'title' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'directorate' => 'MIS',
            'programme' => 'Digital Health',
            'year' => 2026,
            'target' => '100%',
            'unit' => 'percent',
            'baseline' => '0%',
            'q1' => '25%',
            'q2' => '50%',
            'q3' => '75%',
            'q4' => '100%',
            'is_active' => true,
        ];
    }
}
