<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Directorate;
use App\Models\Division;

class DivisionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Division::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'staff_ids' => '{}',
            'is_external' => fake()->boolean(),
            'directorate_id' => Directorate::factory(),
            'is_active' => fake()->boolean(),
        ];
    }
}
