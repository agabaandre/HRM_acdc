<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreWorkplanIndicatorRequest;
use App\Models\WorkplanIndicator;
use Illuminate\Http\JsonResponse;

class WorkplanIndicatorController extends Controller
{
    public function index(): JsonResponse
    {
        $items = WorkplanIndicator::query()
            ->withCount('projects')
            ->orderByDesc('year')
            ->orderBy('code')
            ->get()
            ->map->toApiArray()
            ->values();

        return response()->json(['data' => $items]);
    }

    public function store(StoreWorkplanIndicatorRequest $request): JsonResponse
    {
        $indicator = WorkplanIndicator::query()->create($request->validated());

        return response()->json(['data' => $indicator->toApiArray()], 201);
    }

    public function show(WorkplanIndicator $workplanIndicator): JsonResponse
    {
        $workplanIndicator->loadCount('projects');

        return response()->json(['data' => $workplanIndicator->toApiArray()]);
    }

    public function update(StoreWorkplanIndicatorRequest $request, WorkplanIndicator $workplanIndicator): JsonResponse
    {
        $workplanIndicator->update($request->validated());
        $workplanIndicator->loadCount('projects');

        return response()->json(['data' => $workplanIndicator->toApiArray()]);
    }

    public function destroy(WorkplanIndicator $workplanIndicator): JsonResponse
    {
        if ($workplanIndicator->projects()->exists()) {
            return response()->json([
                'message' => 'Cannot delete an indicator that is linked to projects. Archive it instead.',
            ], 422);
        }

        $workplanIndicator->delete();

        return response()->json(['message' => 'Indicator deleted.']);
    }
}
