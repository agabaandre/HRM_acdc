<?php

namespace Database\Factories;

use App\Enums\DataClassification;
use App\Enums\UrgencyLevel;
use App\Enums\WorkClass;
use App\Enums\WorkRequestStatus;
use App\Models\User;
use App\Models\WorkplanIndicator;
use App\Models\WorkRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkRequest>
 */
class WorkRequestFactory extends Factory
{
    protected $model = WorkRequest::class;

    public function definition(): array
    {
        return [
            'reference' => 'REQ-'.fake()->unique()->numerify('2026-####'),
            'title' => fake()->sentence(5),
            'requesting_unit' => 'Surveillance',
            'requester_name' => fake()->name(),
            'requester_email' => fake()->safeEmail(),
            'problem_statement' => fake()->paragraph(),
            'urgency' => UrgencyLevel::Medium,
            'data_classification' => DataClassification::Internal,
            'work_class' => WorkClass::MediumProject,
            'status' => WorkRequestStatus::Submitted,
            'workplan_indicator_id' => WorkplanIndicator::factory(),
            'created_by' => User::factory(),
            'score_strategic_alignment' => 70,
            'score_impact' => 70,
            'score_urgency_risk' => 50,
            'score_user_reach' => 40,
            'score_feasibility' => 60,
            'score_cost_effort' => 50,
            'score_security' => 40,
            'weighted_score' => 61,
        ];
    }
}
