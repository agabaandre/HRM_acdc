<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestApprovalTrail;

class ServiceRequestApprovalTrailFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ServiceRequestApprovalTrail::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'service_request_id' => ServiceRequest::factory(),
            'action' => fake()->word(),
            'remarks' => fake()->text(),
        ];
    }
}
