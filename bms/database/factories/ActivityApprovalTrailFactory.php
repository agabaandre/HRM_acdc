<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Activity;
use App\Models\ActivityApprovalTrail;
use App\Models\Matrix;

class ActivityApprovalTrailFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ActivityApprovalTrail::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'matrix_id' => Matrix::factory(),
            'activity_id' => Activity::factory(),
            'action' => fake()->word(),
            'remarks' => fake()->text(),
        ];
    }
}
