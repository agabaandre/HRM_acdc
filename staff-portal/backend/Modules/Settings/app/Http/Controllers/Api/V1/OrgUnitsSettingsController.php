<?php

namespace Modules\Settings\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\StaffPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Modules\Core\Support\PortalPermission;
use Modules\Core\Support\PortalTable;

/**
 * Full CI3-parity CRUD for divisions and directorates (settings UI).
 */
class OrgUnitsSettingsController extends Controller
{
    public function staffOptions(Request $request): JsonResponse
    {
        PortalPermission::authorize(15);

        $q = trim((string) $request->query('q', ''));
        $hasPhoto = Schema::hasColumn('staff', 'photo');
        $select = ['staff_id', 'fname', 'lname', 'work_email'];
        if ($hasPhoto) {
            $select[] = 'photo';
        }

        $query = DB::table('staff')
            ->select($select)
            ->orderBy('lname')
            ->orderBy('fname');

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($w) use ($like): void {
                $w->where('fname', 'like', $like)
                    ->orWhere('lname', 'like', $like)
                    ->orWhere('work_email', 'like', $like)
                    ->orWhereRaw("CONCAT(IFNULL(lname,''),' ',IFNULL(fname,'')) LIKE ?", [$like])
                    ->orWhereRaw("CONCAT(IFNULL(fname,''),' ',IFNULL(lname,'')) LIKE ?", [$like]);
            });
        }

        $rows = $query->limit(2000)->get()->map(function ($s) use ($hasPhoto) {
            $photo = $hasPhoto ? trim((string) ($s->photo ?? '')) : '';

            return [
                'staff_id' => (int) $s->staff_id,
                'name' => trim(($s->lname ?? '').' '.($s->fname ?? '')) ?: ('#'.$s->staff_id),
                'email' => $s->work_email,
                'photo_url' => $photo !== '' ? StaffPhoto::url($photo) : null,
            ];
        });

        return response()->json(['data' => $rows]);
    }

    public function divisionsIndex(Request $request): JsonResponse
    {
        PortalPermission::authorize(15);

        if (! Schema::hasTable('divisions')) {
            return response()->json(['data' => [], 'meta' => $this->emptyMeta()]);
        }

        $perPage = min(100, max(5, (int) $request->query('per_page', 20)));
        $page = max(1, (int) $request->query('page', 1));
        $search = trim((string) $request->query('q', ''));

        $q = DB::table('divisions as d')
            ->leftJoin('staff as head', 'head.staff_id', '=', 'd.division_head')
            ->leftJoin('staff as focal', 'focal.staff_id', '=', 'd.focal_person')
            ->leftJoin('staff as finance', 'finance.staff_id', '=', 'd.finance_officer')
            ->leftJoin('staff as admin', 'admin.staff_id', '=', 'd.admin_assistant')
            ->leftJoin('staff as director', 'director.staff_id', '=', 'd.director_id')
            ->leftJoin('staff as head_oic', 'head_oic.staff_id', '=', 'd.head_oic_id')
            ->leftJoin('staff as dir_oic', 'dir_oic.staff_id', '=', 'd.director_oic_id')
            ->leftJoin('directorates as dir', 'dir.id', '=', 'd.directorate_id')
            ->select([
                'd.*',
                'dir.name as directorate_name',
                'head.fname as head_fname',
                'head.lname as head_lname',
                'focal.fname as focal_fname',
                'focal.lname as focal_lname',
                'finance.fname as finance_fname',
                'finance.lname as finance_lname',
                'admin.fname as admin_fname',
                'admin.lname as admin_lname',
                'director.fname as director_fname',
                'director.lname as director_lname',
                'head_oic.fname as head_oic_fname',
                'head_oic.lname as head_oic_lname',
                'dir_oic.fname as dir_oic_fname',
                'dir_oic.lname as dir_oic_lname',
            ])
            ->orderBy('d.division_name');

        if ($search !== '') {
            $like = '%'.$search.'%';
            $q->where(function ($w) use ($like): void {
                $w->where('d.division_name', 'like', $like)
                    ->orWhere('d.division_short_name', 'like', $like)
                    ->orWhere('d.category', 'like', $like)
                    ->orWhere('dir.name', 'like', $like);
            });
        }

        $paginator = PortalTable::paginateDistinct($q, 'd.division_id', $perPage, $page);
        $items = collect($paginator->items())->map(fn ($row) => $this->mapDivisionRow($row));

        return response()->json([
            'data' => $items,
            'meta' => [
                'label' => 'Divisions',
                'pk' => 'division_id',
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'directorates' => $this->directorateOptions(),
                'categories' => ['Programs', 'Operations', 'Other'],
            ],
        ]);
    }

    public function divisionsStore(Request $request): JsonResponse
    {
        PortalPermission::authorize(15);
        $payload = $this->validatedDivisionPayload($request);
        $id = DB::table('divisions')->insertGetId($payload);

        return response()->json([
            'message' => 'Division created.',
            'data' => ['division_id' => (int) $id],
        ], 201);
    }

    public function divisionsUpdate(Request $request, int $id): JsonResponse
    {
        PortalPermission::authorize(15);
        if (! DB::table('divisions')->where('division_id', $id)->exists()) {
            return response()->json(['message' => 'Division not found.'], 404);
        }

        $payload = $this->validatedDivisionPayload($request);
        DB::table('divisions')->where('division_id', $id)->update($payload);

        return response()->json(['message' => 'Division updated.']);
    }

    public function divisionsDestroy(int $id): JsonResponse
    {
        PortalPermission::authorize(15);
        $deleted = DB::table('divisions')->where('division_id', $id)->delete();
        if (! $deleted) {
            return response()->json(['message' => 'Division not found.'], 404);
        }

        return response()->json(['message' => 'Division deleted.']);
    }

    public function directoratesIndex(Request $request): JsonResponse
    {
        PortalPermission::authorize(15);

        if (! Schema::hasTable('directorates')) {
            return response()->json(['data' => [], 'meta' => $this->emptyMeta()]);
        }

        $perPage = min(100, max(5, (int) $request->query('per_page', 50)));
        $page = max(1, (int) $request->query('page', 1));
        $search = trim((string) $request->query('q', ''));

        $q = DB::table('directorates as dir')
            ->leftJoin('staff as s', 's.staff_id', '=', 'dir.director_id')
            ->select([
                'dir.id',
                'dir.name',
                'dir.is_active',
                'dir.director_id',
                'dir.created_at',
                'dir.updated_at',
                's.fname',
                's.lname',
            ])
            ->orderBy('dir.name');

        if ($search !== '') {
            $like = '%'.$search.'%';
            $q->where(function ($w) use ($like): void {
                $w->where('dir.name', 'like', $like)
                    ->orWhere('s.fname', 'like', $like)
                    ->orWhere('s.lname', 'like', $like);
            });
        }

        $paginator = PortalTable::paginateDistinct($q, 'dir.id', $perPage, $page);
        $items = collect($paginator->items())->map(function ($row) {
            $directorId = isset($row->director_id) ? (int) $row->director_id : 0;

            return [
                'id' => (int) $row->id,
                'name' => $row->name,
                'is_active' => (int) ($row->is_active ?? 0) === 1,
                'director_id' => $directorId > 0 ? $directorId : null,
                'director_name' => $directorId > 0
                    ? (trim(($row->lname ?? '').' '.($row->fname ?? '')) ?: null)
                    : null,
                'created_at' => $row->created_at ?? null,
                'updated_at' => $row->updated_at ?? null,
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'label' => 'Directorates',
                'pk' => 'id',
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function directoratesStore(Request $request): JsonResponse
    {
        PortalPermission::authorize(15);
        $payload = $this->validatedDirectoratePayload($request);
        if (Schema::hasColumn('directorates', 'created_at')) {
            $payload['created_at'] = now();
        }
        if (Schema::hasColumn('directorates', 'updated_at')) {
            $payload['updated_at'] = now();
        }
        $id = DB::table('directorates')->insertGetId($payload);

        return response()->json([
            'message' => 'Directorate created.',
            'data' => ['id' => (int) $id],
        ], 201);
    }

    public function directoratesUpdate(Request $request, int $id): JsonResponse
    {
        PortalPermission::authorize(15);
        if (! DB::table('directorates')->where('id', $id)->exists()) {
            return response()->json(['message' => 'Directorate not found.'], 404);
        }

        $payload = $this->validatedDirectoratePayload($request);
        if (Schema::hasColumn('directorates', 'updated_at')) {
            $payload['updated_at'] = now();
        }
        DB::table('directorates')->where('id', $id)->update($payload);

        return response()->json(['message' => 'Directorate updated.']);
    }

    public function directoratesDestroy(int $id): JsonResponse
    {
        PortalPermission::authorize(15);
        $deleted = DB::table('directorates')->where('id', $id)->delete();
        if (! $deleted) {
            return response()->json(['message' => 'Directorate not found.'], 404);
        }

        return response()->json(['message' => 'Directorate deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedDivisionPayload(Request $request): array
    {
        $staffExists = Rule::exists('staff', 'staff_id');
        $validated = $request->validate([
            'division_name' => ['required', 'string', 'max:255'],
            'division_short_name' => ['nullable', 'string', 'max:50'],
            'category' => ['required', 'string', Rule::in(['Programs', 'Operations', 'Other'])],
            'division_head' => ['required', 'integer', $staffExists],
            'focal_person' => ['required', 'integer', $staffExists],
            'finance_officer' => ['required', 'integer', $staffExists],
            'admin_assistant' => ['required', 'integer', $staffExists],
            'director_id' => ['nullable', 'integer', $staffExists],
            'head_oic_id' => ['nullable', 'integer', $staffExists],
            'head_oic_start_date' => ['nullable', 'date'],
            'head_oic_end_date' => ['nullable', 'date', 'after_or_equal:head_oic_start_date'],
            'director_oic_id' => ['nullable', 'integer', $staffExists],
            'director_oic_start_date' => ['nullable', 'date'],
            'director_oic_end_date' => ['nullable', 'date', 'after_or_equal:director_oic_start_date'],
            // Optional; when director_id is set, directorate is resolved from directorates.director_id.
            'directorate_id' => ['nullable', 'integer', Rule::exists('directorates', 'id')],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $directorId = $this->nullableInt($validated['director_id'] ?? null);
        $directorateId = $this->resolveDirectorateIdFromDirector(
            $directorId,
            $this->nullableInt($validated['directorate_id'] ?? null),
        );

        return [
            'division_name' => trim((string) $validated['division_name']),
            'division_short_name' => $this->nullableString($validated['division_short_name'] ?? null),
            'category' => $validated['category'],
            'division_head' => (int) $validated['division_head'],
            'focal_person' => (int) $validated['focal_person'],
            'finance_officer' => (int) $validated['finance_officer'],
            'admin_assistant' => (int) $validated['admin_assistant'],
            'director_id' => $directorId,
            'head_oic_id' => $this->nullableInt($validated['head_oic_id'] ?? null),
            'head_oic_start_date' => $this->nullableDate($validated['head_oic_start_date'] ?? null),
            'head_oic_end_date' => $this->nullableDate($validated['head_oic_end_date'] ?? null),
            'director_oic_id' => $this->nullableInt($validated['director_oic_id'] ?? null),
            'director_oic_start_date' => $this->nullableDate($validated['director_oic_start_date'] ?? null),
            'director_oic_end_date' => $this->nullableDate($validated['director_oic_end_date'] ?? null),
            'directorate_id' => $directorateId,
            'is_active' => array_key_exists('is_active', $validated)
                ? (! empty($validated['is_active']) ? 1 : 0)
                : 1,
        ];
    }

    /**
     * Prefer the directorate that already lists this staff as director.
     * Divisions without a director stay unlinked (directorate optional).
     */
    protected function resolveDirectorateIdFromDirector(?int $directorId, ?int $fallbackDirectorateId): ?int
    {
        if ($directorId === null) {
            return null;
        }

        if (
            Schema::hasTable('directorates')
            && Schema::hasColumn('directorates', 'director_id')
        ) {
            $resolved = DB::table('directorates')
                ->where('director_id', $directorId)
                ->orderBy('id')
                ->value('id');

            if ($resolved) {
                return (int) $resolved;
            }
        }

        // Director not assigned on any directorate yet — keep optional explicit link if provided.
        return $fallbackDirectorateId;
    }

    /**
     * @return list<array{id: int, name: string, director_id: int|null}>
     */
    protected function directorateOptions(): array
    {
        if (! Schema::hasTable('directorates')) {
            return [];
        }

        $hasDirector = Schema::hasColumn('directorates', 'director_id');
        $cols = $hasDirector ? ['id', 'name', 'director_id'] : ['id', 'name'];

        return DB::table('directorates')
            ->orderBy('name')
            ->get($cols)
            ->map(function ($d) use ($hasDirector) {
                $directorId = $hasDirector && isset($d->director_id) ? (int) $d->director_id : 0;

                return [
                    'id' => (int) $d->id,
                    'name' => (string) $d->name,
                    'director_id' => $directorId > 0 ? $directorId : null,
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedDirectoratePayload(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'director_id' => ['nullable', 'integer', Rule::exists('staff', 'staff_id')],
        ]);

        return [
            'name' => trim((string) $validated['name']),
            'is_active' => ! empty($validated['is_active']) ? 1 : 0,
            'director_id' => $this->nullableInt($validated['director_id'] ?? null),
        ];
    }

    /**
     * @param  object  $row
     * @return array<string, mixed>
     */
    protected function mapDivisionRow(object $row): array
    {
        return [
            'division_id' => (int) $row->division_id,
            'division_name' => $row->division_name,
            'division_short_name' => $row->division_short_name,
            'category' => $row->category,
            'is_active' => (int) ($row->is_active ?? 1) === 1,
            'directorate_id' => $row->directorate_id ? (int) $row->directorate_id : null,
            'directorate_name' => $row->directorate_name ?? null,
            'division_head' => $row->division_head ? (int) $row->division_head : null,
            'division_head_name' => $this->staffName($row->head_lname ?? null, $row->head_fname ?? null),
            'focal_person' => $row->focal_person ? (int) $row->focal_person : null,
            'focal_person_name' => $this->staffName($row->focal_lname ?? null, $row->focal_fname ?? null),
            'finance_officer' => $row->finance_officer ? (int) $row->finance_officer : null,
            'finance_officer_name' => $this->staffName($row->finance_lname ?? null, $row->finance_fname ?? null),
            'admin_assistant' => $row->admin_assistant ? (int) $row->admin_assistant : null,
            'admin_assistant_name' => $this->staffName($row->admin_lname ?? null, $row->admin_fname ?? null),
            'director_id' => $row->director_id ? (int) $row->director_id : null,
            'director_name' => $this->staffName($row->director_lname ?? null, $row->director_fname ?? null),
            'head_oic_id' => $row->head_oic_id ? (int) $row->head_oic_id : null,
            'head_oic_name' => $this->staffName($row->head_oic_lname ?? null, $row->head_oic_fname ?? null),
            'head_oic_start_date' => $this->nullableDate($row->head_oic_start_date ?? null),
            'head_oic_end_date' => $this->nullableDate($row->head_oic_end_date ?? null),
            'director_oic_id' => $row->director_oic_id ? (int) $row->director_oic_id : null,
            'director_oic_name' => $this->staffName($row->dir_oic_lname ?? null, $row->dir_oic_fname ?? null),
            'director_oic_start_date' => $this->nullableDate($row->director_oic_start_date ?? null),
            'director_oic_end_date' => $this->nullableDate($row->director_oic_end_date ?? null),
        ];
    }

    protected function staffName(?string $lname, ?string $fname): ?string
    {
        $name = trim(($lname ?? '').' '.($fname ?? ''));

        return $name !== '' ? $name : null;
    }

    protected function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || (int) $value < 1) {
            return null;
        }

        return (int) $value;
    }

    protected function nullableString(mixed $value): ?string
    {
        $s = trim((string) ($value ?? ''));

        return $s !== '' ? $s : null;
    }

    protected function nullableDate(mixed $value): ?string
    {
        $s = trim((string) ($value ?? ''));
        if ($s === '' || str_starts_with($s, '0000-00-00')) {
            return null;
        }

        return substr($s, 0, 10);
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyMeta(): array
    {
        return [
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 20,
            'total' => 0,
        ];
    }
}
