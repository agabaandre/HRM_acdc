<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectModule;
use App\Models\WorkItem;
use App\Support\DevTrackReference;
use Illuminate\Validation\ValidationException;

class ProjectModuleService
{
    /**
     * The earliest module is the primary module. Create one when the project has none.
     */
    public function ensurePrimaryModule(Project $project): ProjectModule
    {
        $first = $project->projectModules()->orderBy('id')->first();

        if ($first === null) {
            $first = $project->projectModules()->create([
                'code' => DevTrackReference::next('MOD', 'project_modules', 'code'),
                'name' => $project->name,
                'description' => $project->description,
                'is_primary' => true,
                'created_by' => $project->created_by,
            ]);
            $project->update(['modules' => [$first->name]]);

            return $first;
        }

        $project->projectModules()->where('id', '!=', $first->id)->update(['is_primary' => false]);
        if (! $first->is_primary) {
            $first->forceFill(['is_primary' => true])->save();
        }

        return $first->fresh() ?? $first;
    }

    public function moveProjectTasksToPrimary(Project $project): ProjectModule
    {
        $primary = $this->ensurePrimaryModule($project);

        WorkItem::query()
            ->where('project_id', $project->id)
            ->whereNull('project_module_id')
            ->update(['project_module_id' => $primary->id]);

        return $primary;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function placeOnModule(array $data, ?int $fallbackProjectId = null): array
    {
        $projectId = (int) ($data['project_id'] ?? $fallbackProjectId ?? 0);
        $project = $projectId > 0 ? Project::query()->find($projectId) : null;
        if ($project === null) {
            throw ValidationException::withMessages([
                'project_id' => ['Choose a project before adding a task.'],
            ]);
        }

        $data['project_id'] = $project->id;

        if (! empty($data['project_module_id'])) {
            $owns = ProjectModule::query()
                ->whereKey($data['project_module_id'])
                ->where('project_id', $project->id)
                ->exists();
            if (! $owns) {
                throw ValidationException::withMessages([
                    'project_module_id' => ['Choose a module from this project.'],
                ]);
            }

            return $data;
        }

        $data['project_module_id'] = $this->ensurePrimaryModule($project)->id;

        return $data;
    }
}
