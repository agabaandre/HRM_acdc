<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Division;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApmDivisionController extends Controller
{
    /**
     * List divisions. Optional: ?directorate_id=1, ?per_page=20
     */
    public function index(Request $request): JsonResponse
    {
        $query = Division::query()->orderBy('division_name');

        if ($request->filled('directorate_id')) {
            $query->where('directorate_id', (int) $request->query('directorate_id'));
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
     * Single division.
     */
    public function show(int $id): JsonResponse
    {
        $division = Division::find($id);
        if (!$division) {
            return response()->json(['success' => false, 'message' => 'Division not found.'], 404);
        }
        return response()->json(['success' => true, 'data' => $division]);
    }

    /**
     * Create a division.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'division_name' => 'required|string|max:150',
            'division_short_name' => 'nullable|string|max:100',
            'division_head' => 'nullable|exists:staff,staff_id',
            'focal_person' => 'nullable|exists:staff,staff_id',
            'admin_assistant' => 'nullable|exists:staff,staff_id',
            'finance_officer' => 'nullable|exists:staff,staff_id',
            'directorate_id' => 'nullable|exists:directorates,id',
            'head_oic_id' => 'nullable|exists:staff,staff_id',
            'head_oic_start_date' => 'nullable|date',
            'head_oic_end_date' => 'nullable|date|after_or_equal:head_oic_start_date',
            'director_id' => 'nullable|exists:staff,staff_id',
            'director_oic_id' => 'nullable|exists:staff,staff_id',
            'director_oic_start_date' => 'nullable|date',
            'director_oic_end_date' => 'nullable|date|after_or_equal:director_oic_start_date',
            'category' => 'nullable|in:Programs,Operations,Other,',
        ]);

        $division = Division::create($validated);
        return response()->json([
            'success' => true,
            'message' => 'Division created.',
            'data' => $division,
        ], 201);
    }

    /**
     * Update a division.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $division = Division::find($id);
        if (!$division) {
            return response()->json(['success' => false, 'message' => 'Division not found.'], 404);
        }
        $validated = $request->validate([
            'division_name' => 'sometimes|string|max:150',
            'division_short_name' => 'nullable|string|max:100',
            'division_head' => 'nullable|exists:staff,staff_id',
            'focal_person' => 'nullable|exists:staff,staff_id',
            'admin_assistant' => 'nullable|exists:staff,staff_id',
            'finance_officer' => 'nullable|exists:staff,staff_id',
            'directorate_id' => 'nullable|exists:directorates,id',
            'head_oic_id' => 'nullable|exists:staff,staff_id',
            'head_oic_start_date' => 'nullable|date',
            'head_oic_end_date' => 'nullable|date',
            'director_id' => 'nullable|exists:staff,staff_id',
            'director_oic_id' => 'nullable|exists:staff,staff_id',
            'director_oic_start_date' => 'nullable|date',
            'director_oic_end_date' => 'nullable|date',
            'category' => 'nullable|in:Programs,Operations,Other,',
        ]);
        $division->update($validated);
        return response()->json([
            'success' => true,
            'message' => 'Division updated.',
            'data' => $division,
        ]);
    }
}
