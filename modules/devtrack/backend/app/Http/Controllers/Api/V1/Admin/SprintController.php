<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreSprintRequest;
use App\Models\Sprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SprintController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Sprint::query()
            ->with('project:id,code,name')
            ->withCount([
                'workItems',
                'workItems as work_items_done_count' => fn ($q) => $q->where('status', 'completed'),
            ])
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->query('project_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderByDesc('start_date')
            ->get()
            ->map->toApiArray()
            ->values();

        return response()->json(['data' => $items]);
    }

    public function store(StoreSprintRequest $request): JsonResponse
    {
        $sprint = Sprint::query()->create($request->validated());
        $sprint->load('project:id,code,name');

        return response()->json(['data' => $sprint->toApiArray()], 201);
    }

    public function show(Sprint $sprint): JsonResponse
    {
        $sprint->load('project:id,code,name');
        $sprint->loadCount([
            'workItems',
            'workItems as work_items_done_count' => fn ($q) => $q->where('status', 'completed'),
        ]);

        return response()->json(['data' => $sprint->toApiArray()]);
    }

    public function update(StoreSprintRequest $request, Sprint $sprint): JsonResponse
    {
        $sprint->update($request->validated());
        $sprint->load('project:id,code,name');

        return response()->json(['data' => $sprint->toApiArray()]);
    }
}
