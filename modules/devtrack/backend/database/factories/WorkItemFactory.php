<?php

namespace Database\Factories;

use App\Enums\WorkItemPriority;
use App\Enums\WorkItemStatus;
use App\Enums\WorkItemType;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkItem>
 */
class WorkItemFactory extends Factory
{
    protected $model = WorkItem::class;

    public function definition(): array
    {
        return [
            'key' => 'WI-'.fake()->unique()->numerify('2026-####'),
            'project_id' => Project::factory(),
            'title' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'type' => WorkItemType::Task,
            'status' => WorkItemStatus::NotStarted,
            'priority' => WorkItemPriority::Medium,
            'reporter_id' => User::factory(),
            'estimate_hours' => 4,
        ];
    }
}
