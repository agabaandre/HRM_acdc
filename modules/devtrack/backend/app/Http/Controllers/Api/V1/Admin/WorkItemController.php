<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\WorkItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreWorkItemRequest;
use App\Models\WorkItem;
use App\Support\DevTrackReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = WorkItem::query()
            ->with(['project:id,code,name', 'assignee:id,name,email', 'reporter:id,name', 'sprint:id,name'])
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->query('project_id')))
            ->when($request->filled('enhancement_id'), fn ($q) => $q->where('enhancement_id', $request->query('enhancement_id')))
            ->when($request->filled('project_module_id'), fn ($q) => $q->where('project_module_id', $request->query('project_module_id')))
            ->when($request->filled('sprint_id'), fn ($q) => $q->where('sprint_id', $request->query('sprint_id')))
            ->when($request->filled('assignee_id'), fn ($q) => $q->where('assignee_id', $request->query('assignee_id')))
            ->when($request->boolean('mine'), fn ($q) => $q->where('assignee_id', $request->user()?->id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->query('priority')))
            ->when($request->boolean('open_only'), function ($q) {
                $open = collect(WorkItemStatus::cases())
                    ->filter(fn (WorkItemStatus $status) => $status->isOpen())
                    ->map(fn (WorkItemStatus $status) => $status->value)
                    ->all();
                $q->whereIn('status', $open);
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->query('q').'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('title', 'like', $term)->orWhere('key', 'like', $term);
                });
            })
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'not_started' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE priority WHEN 'must_do' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->orderBy('due_date')
            ->get()
            ->map->toApiArray()
            ->values();

        return response()->json(['data' => $items]);
    }

    public function store(StoreWorkItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['key'] = DevTrackReference::next('WI', 'work_items', 'key');
        $data['reporter_id'] = $request->user()?->id;
        $data['status'] = $data['status'] ?? WorkItemStatus::NotStarted->value;

        $item = WorkItem::query()->create($data);
        $item->load(['project:id,code,name', 'assignee:id,name,email', 'sprint:id,name']);

        return response()->json(['data' => $item->toApiArray()], 201);
    }

    public function show(WorkItem $workItem): JsonResponse
    {
        $workItem->load([
            'project:id,code,name',
            'assignee:id,name,email',
            'reporter:id,name',
            'sprint:id,name',
            'children',
        ]);

        return response()->json(['data' => $workItem->toApiArray()]);
    }

    public function update(StoreWorkItemRequest $request, WorkItem $workItem): JsonResponse
    {
        $workItem->update($request->validated());
        $workItem->load(['project:id,code,name', 'assignee:id,name,email', 'sprint:id,name']);

        return response()->json(['data' => $workItem->toApiArray()]);
    }

    public function destroy(WorkItem $workItem): JsonResponse
    {
        $workItem->delete();

        return response()->json(['message' => 'Work item deleted.']);
    }
}
