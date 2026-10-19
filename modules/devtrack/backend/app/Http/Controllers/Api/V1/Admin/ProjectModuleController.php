<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreProjectModuleRequest;
use App\Models\Project;
use App\Models\ProjectModule;
use App\Support\DevTrackReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectModuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = ProjectModule::query()
            ->with(['project:id,code,name', 'businessOwner', 'responsibleDeveloper'])
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->query('project_id')))
            ->orderBy('name')
            ->get()
            ->map(fn (ProjectModule $module) => $module->toApiArray())
            ->values();

        return response()->json(['data' => $items]);
    }

    public function store(StoreProjectModuleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['code'] = DevTrackReference::next('MOD', 'project_modules', 'code');
        $data['created_by'] = $request->user()?->id;

        $module = ProjectModule::query()->create($data);
        $this->syncProjectModuleNames($module->project);

        return response()->json(['data' => $module->fresh()->toApiArray(true)], 201);
    }

    public function show(ProjectModule $projectModule): JsonResponse
    {
        return response()->json(['data' => $projectModule->toApiArray(true)]);
    }

    public function update(StoreProjectModuleRequest $request, ProjectModule $projectModule): JsonResponse
    {
        $projectModule->update($request->validated());
        $this->syncProjectModuleNames($projectModule->project);

        return response()->json(['data' => $projectModule->fresh()->toApiArray(true)]);
    }

    private function syncProjectModuleNames(Project $project): void
    {
        $names = $project->projectModules()->orderBy('name')->pluck('name')->all();
        $project->update(['modules' => $names]);
    }
}
