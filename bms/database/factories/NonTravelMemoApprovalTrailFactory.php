<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\NonTravelMemo;
use App\Models\NonTravelMemoApprovalTrail;

class NonTravelMemoApprovalTrailFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = NonTravelMemoApprovalTrail::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'non_travel_memo_id' => NonTravelMemo::factory(),
            'action' => fake()->word(),
            'remarks' => fake()->text(),
        ];
    }
}
