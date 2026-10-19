<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreEnhancementRequest;
use App\Models\Enhancement;
use App\Support\DevTrackReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnhancementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Enhancement::query()
            ->with(['module:id,code,name', 'businessOwner', 'responsibleDeveloper'])
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->query('project_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderByDesc('id')
            ->get()
            ->map(fn (Enhancement $enhancement) => $enhancement->toApiArray())
            ->values();

        return response()->json(['data' => $items]);
    }

    public function store(StoreEnhancementRequest $request): JsonResponse
    {
        $data = $request->validated();
        $releaseIds = $data['release_ids'] ?? [];
        unset($data['release_ids']);

        $data['reference'] = DevTrackReference::next('ENH', 'enhancements', 'reference');
        $data['created_by'] = $request->user()?->id;

        $enhancement = Enhancement::query()->create($data);
        if ($releaseIds !== []) {
            $enhancement->releases()->sync($releaseIds);
        }

        return response()->json(['data' => $enhancement->fresh()->toApiArray(true)], 201);
    }

    public function show(Enhancement $enhancement): JsonResponse
    {
        return response()->json(['data' => $enhancement->toApiArray(true)]);
    }

    public function update(StoreEnhancementRequest $request, Enhancement $enhancement): JsonResponse
    {
        $data = $request->validated();
        $releaseIds = array_key_exists('release_ids', $data) ? ($data['release_ids'] ?? []) : null;
        unset($data['release_ids']);

        $enhancement->update($data);
        if (is_array($releaseIds)) {
            $enhancement->releases()->sync($releaseIds);
        }

        return response()->json(['data' => $enhancement->fresh()->toApiArray(true)]);
    }
}
