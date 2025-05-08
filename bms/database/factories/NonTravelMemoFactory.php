<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\ForwardWorkflow;
use App\Models\NonTravelMemo;
use App\Models\NonTravelMemoCategory;
use App\Models\ReverseWorkflow;
use App\Models\Staff;

class NonTravelMemoFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = NonTravelMemo::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'forward_workflow_id' => ForwardWorkflow::factory(),
            'reverse_workflow_id' => ReverseWorkflow::factory(),
            'workplan_activity_code' => fake()->word(),
            'staff_id' => Staff::factory(),
            'memo_date' => fake()->date(),
            'location_id' => '{}',
            'non_travel_memo_category_id' => NonTravelMemoCategory::factory(),
            'budget_id' => '{}',
            'activity_title' => fake()->word(),
            'background' => fake()->text(),
            'activity_request_remarks' => fake()->text(),
            'justification' => fake()->text(),
            'budget_breakdown' => '{}',
            'attachment' => '{}',
        ];
    }
}
