<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpdeskCategory;
use App\Models\HelpdeskRiskMatrixEntry;
use App\Services\StaffDirectoryLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminHelpdeskRiskMatrixController extends Controller
{
    use AuthorizesHelpdeskAdmin;

    public function index(Request $request, StaffDirectoryLookupService $directory): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $rows = HelpdeskRiskMatrixEntry::query()
            ->with('category:id,name')
            ->orderByDesc('is_active')
            ->orderBy('staff_id')
            ->orderByRaw('category_id is null desc')
            ->get();

        $data = $rows->map(function (HelpdeskRiskMatrixEntry $row) use ($directory) {
            $staff = $directory->resolveByStaffId((int) $row->staff_id);

            return [
                'id' => $row->id,
                'staff_id' => (int) $row->staff_id,
                'staff_name' => $staff['name'] ?? null,
                'staff_email' => $staff['work_email'] ?? null,
                'duty_station_name' => $staff['duty_station_name'] ?? null,
                'priority' => $row->priority,
                'category_id' => $row->category_id,
                'category' => $row->category ? [
                    'id' => $row->category->id,
                    'name' => $row->category->name,
                ] : null,
                'notes' => $row->notes,
                'is_active' => (bool) $row->is_active,
                'created_at' => $row->created_at?->toIso8601String(),
                'updated_at' => $row->updated_at?->toIso8601String(),
            ];
        });

        $active = $rows->where('is_active', true);
        $summary = [
            'total' => $rows->count(),
            'active' => $active->count(),
            'by_priority' => [
                'low' => $active->where('priority', 'low')->count(),
                'medium' => $active->where('priority', 'medium')->count(),
                'high' => $active->where('priority', 'high')->count(),
                'critical' => $active->where('priority', 'critical')->count(),
            ],
        ];

        return response()->json([
            'data' => $data,
            'meta' => ['summary' => $summary],
        ]);
    }

    public function store(Request $request, StaffDirectoryLookupService $directory): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'min:1'],
            'priority' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'category_id' => ['nullable', 'integer', 'exists:helpdesk_categories,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $staffId = (int) $validated['staff_id'];
        if ($directory->resolveByStaffId($staffId) === null) {
            throw ValidationException::withMessages([
                'staff_id' => 'Staff member not found in the directory. Run directory sync in Settings → Jobs.',
            ]);
        }

        $categoryId = isset($validated['category_id']) ? (int) $validated['category_id'] : null;
        $this->assertUniqueScope($staffId, $categoryId);

        $row = HelpdeskRiskMatrixEntry::query()->create([
            'staff_id' => $staffId,
            'priority' => $validated['priority'],
            'category_id' => $categoryId,
            'notes' => isset($validated['notes']) ? trim((string) $validated['notes']) : null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['data' => $row->load('category:id,name')], 201);
    }

    public function bulkStore(Request $request, StaffDirectoryLookupService $directory): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $validated = $request->validate([
            'staff_ids' => ['required', 'array', 'min:1'],
            'staff_ids.*' => ['integer', 'min:1'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', 'min:0'],
            'priority' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $staffIds = array_values(array_unique(array_map('intval', $validated['staff_ids'])));
        $scopeRaw = array_values(array_unique(array_map('intval', $validated['category_ids'])));
        $categoryIds = [];
        foreach ($scopeRaw as $raw) {
            if ($raw === 0) {
                $categoryIds[] = null;

                continue;
            }
            if (! HelpdeskCategory::query()->whereKey($raw)->exists()) {
                throw ValidationException::withMessages([
                    'category_ids' => "Category #{$raw} is invalid.",
                ]);
            }
            $categoryIds[] = $raw;
        }

        $notes = isset($validated['notes']) ? trim((string) $validated['notes']) : null;
        $notes = $notes !== '' ? $notes : null;
        $isActive = $validated['is_active'] ?? true;
        $priority = $validated['priority'];

        $created = 0;
        $skipped = 0;
        $skippedDetails = [];

        foreach ($staffIds as $staffId) {
            if ($directory->resolveByStaffId($staffId) === null) {
                throw ValidationException::withMessages([
                    'staff_ids' => "Staff member #{$staffId} not found in the directory. Run directory sync in Settings → Jobs.",
                ]);
            }

            foreach ($categoryIds as $categoryId) {
                $duplicate = HelpdeskRiskMatrixEntry::query()
                    ->where('staff_id', $staffId)
                    ->when($categoryId === null, fn ($q) => $q->whereNull('category_id'), fn ($q) => $q->where('category_id', $categoryId))
                    ->exists();

                if ($duplicate) {
                    $skipped++;
                    $skippedDetails[] = [
                        'staff_id' => $staffId,
                        'category_id' => $categoryId,
                    ];

                    continue;
                }

                HelpdeskRiskMatrixEntry::query()->create([
                    'staff_id' => $staffId,
                    'priority' => $priority,
                    'category_id' => $categoryId,
                    'notes' => $notes,
                    'is_active' => $isActive,
                ]);
                $created++;
            }
        }

        return response()->json([
            'data' => [
                'created' => $created,
                'skipped' => $skipped,
                'skipped_details' => $skippedDetails,
            ],
            'message' => $created > 0
                ? "Added {$created} priority matrix ".($created === 1 ? 'entry' : 'entries').($skipped > 0 ? "; {$skipped} already existed." : '.')
                : 'No new entries were added — all selected combinations already exist.',
        ], $created > 0 ? 201 : 200);
    }

    public function update(Request $request, HelpdeskRiskMatrixEntry $riskMatrixEntry, StaffDirectoryLookupService $directory): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);

        $validated = $request->validate([
            'staff_id' => ['sometimes', 'integer', 'min:1'],
            'priority' => ['sometimes', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'category_id' => ['nullable', 'integer', 'exists:helpdesk_categories,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $staffId = (int) ($validated['staff_id'] ?? $riskMatrixEntry->staff_id);
        if (array_key_exists('staff_id', $validated) && $directory->resolveByStaffId($staffId) === null) {
            throw ValidationException::withMessages([
                'staff_id' => 'Staff member not found in the directory.',
            ]);
        }

        $categoryId = array_key_exists('category_id', $validated)
            ? ($validated['category_id'] !== null ? (int) $validated['category_id'] : null)
            : $riskMatrixEntry->category_id;

        if (array_key_exists('staff_id', $validated) || array_key_exists('category_id', $validated)) {
            $this->assertUniqueScope($staffId, $categoryId, $riskMatrixEntry->id);
        }

        $riskMatrixEntry->fill([
            'staff_id' => $staffId,
            'priority' => $validated['priority'] ?? $riskMatrixEntry->priority,
            'category_id' => $categoryId,
            'notes' => array_key_exists('notes', $validated)
                ? (trim((string) ($validated['notes'] ?? '')) ?: null)
                : $riskMatrixEntry->notes,
            'is_active' => $validated['is_active'] ?? $riskMatrixEntry->is_active,
        ]);
        $riskMatrixEntry->save();

        return response()->json(['data' => $riskMatrixEntry->fresh()->load('category:id,name')]);
    }

    public function destroy(Request $request, HelpdeskRiskMatrixEntry $riskMatrixEntry): JsonResponse
    {
        $this->ensureHelpdeskAdmin($request);
        $riskMatrixEntry->delete();

        return response()->json(['message' => 'Deleted']);
    }

    private function assertUniqueScope(int $staffId, ?int $categoryId, ?int $ignoreId = null): void
    {
        $exists = HelpdeskRiskMatrixEntry::query()
            ->where('staff_id', $staffId)
            ->when($categoryId === null, fn ($q) => $q->whereNull('category_id'), fn ($q) => $q->where('category_id', $categoryId))
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            $message = $categoryId === null
                ? 'This staff member already has a global priority matrix entry.'
                : 'This staff member already has a priority matrix entry for that category.';

            throw ValidationException::withMessages(['staff_id' => $message]);
        }
    }
}
