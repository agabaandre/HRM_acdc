<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Directorate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApmDirectorateController extends Controller
{
    /**
     * List directorates. Optional: ?is_active=1
     */
    public function index(Request $request): JsonResponse
    {
        $query = Directorate::query()->orderBy('name');

        if ($request->has('is_active')) {
            $query->where('is_active', (bool) $request->query('is_active'));
        }

        $perPage = min((int) $request->query('per_page', 50), 100);
        $items = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $items->items(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    /**
     * Single directorate.
     */
    public function show(int $id): JsonResponse
    {
        $directorate = Directorate::find($id);
        if (!$directorate) {
            return response()->json(['success' => false, 'message' => 'Directorate not found.'], 404);
        }
        return response()->json(['success' => true, 'data' => $directorate]);
    }

    /**
     * Create a directorate.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);
        $validated['is_active'] = $validated['is_active'] ?? true;
        $directorate = Directorate::create($validated);
        return response()->json([
            'success' => true,
            'message' => 'Directorate created.',
            'data' => $directorate,
        ], 201);
    }

    /**
     * Update a directorate.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $directorate = Directorate::find($id);
        if (!$directorate) {
            return response()->json(['success' => false, 'message' => 'Directorate not found.'], 404);
        }
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);
        $directorate->update($validated);
        return response()->json([
            'success' => true,
            'message' => 'Directorate updated.',
            'data' => $directorate,
        ]);
    }
}
