<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\AdminAssistant;
use App\Models\Directorate;
use App\Models\Division;
use App\Models\FinanceOfficer;
use App\Models\FocalPerson;

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
            'focal_person' => FocalPerson::factory(),
            'finance_officer' => FinanceOfficer::factory(),
            'division_head' => fake()->word(),
            'admin_assistant' => AdminAssistant::factory(),
            'is_external' => fake()->boolean(),
            'directorate_id' => Directorate::factory(),
            'is_active' => fake()->boolean(),
        ];
    }
}
