<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ReleaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreReleaseRequest;
use App\Models\Release;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReleaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = Release::query()
            ->with('enhancements:id,reference,name')
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->query('project_id')))
            ->orderBy('version')
            ->get()
            ->map->toApiArray()
            ->values();

        return response()->json(['data' => $items]);
    }

    public function store(StoreReleaseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $enhancementIds = $data['enhancement_ids'] ?? [];
        unset($data['enhancement_ids']);
        $data['created_by'] = $request->user()?->id;

        if (($data['status'] ?? null) === ReleaseStatus::Released->value && empty($data['released_at'])) {
            $data['released_at'] = now()->toDateString();
        }

        $release = Release::query()->create($data);
        if ($enhancementIds !== []) {
            $release->enhancements()->sync($enhancementIds);
        }

        return response()->json(['data' => $release->fresh()->load('enhancements')->toApiArray()], 201);
    }

    public function show(Release $release): JsonResponse
    {
        return response()->json(['data' => $release->load('enhancements')->toApiArray()]);
    }

    public function update(StoreReleaseRequest $request, Release $release): JsonResponse
    {
        $data = $request->validated();
        $enhancementIds = array_key_exists('enhancement_ids', $data) ? ($data['enhancement_ids'] ?? []) : null;
        unset($data['enhancement_ids']);

        if (($data['status'] ?? null) === ReleaseStatus::Released->value && empty($data['released_at']) && $release->released_at === null) {
            $data['released_at'] = now()->toDateString();
        }

        $release->update($data);
        if (is_array($enhancementIds)) {
            $release->enhancements()->sync($enhancementIds);
        }

        return response()->json(['data' => $release->fresh()->load('enhancements')->toApiArray()]);
    }
}
