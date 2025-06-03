<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Activity;
use App\Models\ForwardWorkflow;
use App\Models\Matrix;
use App\Models\RequestType;
use App\Models\ReverseWorkflow;
use App\Models\Staff;

class ActivityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Activity::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'forward_workflow_id' => ForwardWorkflow::factory(),
            'reverse_workflow_id' => ReverseWorkflow::factory(),
            'workplan_activity_code' => fake()->word(),
            'matrix_id' => Matrix::factory(),
            'staff_id' => Staff::factory(),
            'date_from' => fake()->date(),
            'date_to' => fake()->date(),
            'location_id' => '{}',
            'total_participants' => fake()->numberBetween(-10000, 10000),
            'internal_participants' => '{}',
            'budget_id' => '{}',
            'key_result_area' => fake()->text(),
            'request_type_id' => RequestType::factory(),
            'activity_title' => fake()->word(),
            'background' => fake()->text(),
            'activity_request_remarks' => fake()->text(),
            'is_sepecial_memo' => fake()->boolean(),
            'budget' => '{}',
            'attachment' => '{}',
        ];
    }
}
