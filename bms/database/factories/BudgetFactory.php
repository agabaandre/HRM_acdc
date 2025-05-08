<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Budget;
use App\Models\Division;

class BudgetFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Budget::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'division_id' => Division::factory(),
            'code' => fake()->word(),
            'year' => fake()->year(),
            'amount' => fake()->randomFloat(2, 0, 99999999999999.99),
            'balance' => fake()->randomFloat(2, 0, 99999999999999.99),
        ];
    }
}
