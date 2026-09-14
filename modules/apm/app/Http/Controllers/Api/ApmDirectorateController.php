<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Directorate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ApmDirectorateController extends Controller
{
    private function directorateApiArray(Directorate $d): array
    {
        return [
            'id' => $d->id,
            'name' => $d->name,
            'is_active' => (bool) $d->is_active,
            'director_id' => $d->director_id,
            'director' => ($d->relationLoaded('director') && $d->director) ? [
                'id' => (int) $d->director->staff_id,
                'name' => $d->director->name,
            ] : null,
            'created_at' => $d->created_at,
            'updated_at' => $d->updated_at,
        ];
    }

    /**
     * @param  Collection<int, Directorate>  $collection
     * @return array<int, array<string, mixed>>
     */
    private function directoratesCollectionToApiList(Collection $collection): array
    {
        $collection->load(['director' => fn ($q) => $q->select('staff_id', 'title', 'fname', 'lname', 'oname')]);

        return $collection->map(fn (Directorate $d) => $this->directorateApiArray($d))->values()->all();
    }

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
            'data' => $this->directoratesCollectionToApiList($items->getCollection()),
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
        $directorate->load(['director' => fn ($q) => $q->select('staff_id', 'title', 'fname', 'lname', 'oname')]);

        return response()->json(['success' => true, 'data' => $this->directorateApiArray($directorate)]);
    }

    /**
     * Create a directorate.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'nullable|boolean',
            'director_id' => 'nullable|integer|exists:staff,staff_id',
        ]);
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['director_id'] = ! empty($validated['director_id']) ? (int) $validated['director_id'] : null;
        $directorate = Directorate::create($validated);
        $directorate->load(['director' => fn ($q) => $q->select('staff_id', 'title', 'fname', 'lname', 'oname')]);

        return response()->json([
            'success' => true,
            'message' => 'Directorate created.',
            'data' => $this->directorateApiArray($directorate),
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
            'director_id' => 'nullable|integer|exists:staff,staff_id',
        ]);
        if (array_key_exists('director_id', $validated)) {
            $validated['director_id'] = ! empty($validated['director_id']) ? (int) $validated['director_id'] : null;
        }
        $directorate->update($validated);
        $directorate->load(['director' => fn ($q) => $q->select('staff_id', 'title', 'fname', 'lname', 'oname')]);

        return response()->json([
            'success' => true,
            'message' => 'Directorate updated.',
            'data' => $this->directorateApiArray($directorate),
        ]);
    }
}
