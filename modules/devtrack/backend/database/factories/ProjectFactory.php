<?php

namespace Database\Factories;

use App\Enums\DataClassification;
use App\Enums\DeploymentReadiness;
use App\Enums\PriorityCategory;
use App\Enums\ProjectStage;
use App\Enums\ProjectStatus;
use App\Enums\RagStatus;
use App\Enums\WorkClass;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkplanIndicator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'code' => 'PRJ-'.fake()->unique()->numerify('2026-####'),
            'name' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'work_class' => WorkClass::MediumProject,
            'workplan_indicator_id' => WorkplanIndicator::factory(),
            'stage' => ProjectStage::Development,
            'rag_status' => RagStatus::Green,
            'priority_category' => PriorityCategory::HighPriority,
            'status' => ProjectStatus::InProgress,
            'start_date' => now()->subWeeks(4)->toDateString(),
            'target_end_date' => now()->addMonths(3)->toDateString(),
            'deployment_readiness' => DeploymentReadiness::NotReady,
            'data_classification' => DataClassification::Internal,
            'modules' => [fake()->words(2, true)],
            'created_by' => User::factory(),
        ];
    }
}
