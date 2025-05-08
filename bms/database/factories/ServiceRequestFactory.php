<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Activity;
use App\Models\ReverseWorkflow;
use App\Models\ServiceRequest;
use App\Models\Workflow;

class ServiceRequestFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ServiceRequest::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'workflow_id' => Workflow::factory(),
            'reverse_workflow_id' => ReverseWorkflow::factory(),
            'approval_status' => fake()->randomElement(["pending","approved","rejected"]),
        ];
    }
}
