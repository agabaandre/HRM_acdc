<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreRaidItemRequest;
use App\Models\RaidItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RaidItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = RaidItem::query()
            ->with(['project:id,code,name', 'owner:id,name'])
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->query('project_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->latest()
            ->get()
            ->map->toApiArray()
            ->values();

        return response()->json(['data' => $items]);
    }

    public function store(StoreRaidItemRequest $request): JsonResponse
    {
        $item = RaidItem::query()->create($request->validated());
        $item->load(['project:id,code,name', 'owner:id,name']);

        return response()->json(['data' => $item->toApiArray()], 201);
    }

    public function update(StoreRaidItemRequest $request, RaidItem $raidItem): JsonResponse
    {
        $raidItem->update($request->validated());
        $raidItem->load(['project:id,code,name', 'owner:id,name']);

        return response()->json(['data' => $raidItem->toApiArray()]);
    }

    public function destroy(RaidItem $raidItem): JsonResponse
    {
        $raidItem->delete();

        return response()->json(['message' => 'RAID item deleted.']);
    }
}
