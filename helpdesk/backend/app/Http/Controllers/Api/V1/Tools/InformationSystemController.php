<?php

namespace App\Http\Controllers\Api\V1\Tools;

use App\Exports\InformationSystemsExport;
use App\Http\Controllers\Controller;
use App\Models\HelpdeskInformationSystem;
use App\Models\HelpdeskInformationSystemLanguage;
use App\Models\HelpdeskInformationSystemModule;
use App\Models\HelpdeskInformationSystemStatusEvent;
use App\Services\InformationSystemStatusRecorder;
use App\Services\StaffDirectoryLookupService;
use App\Support\InformationSystemLanguageNormalizer;
use App\Support\InformationSystemStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InformationSystemController extends Controller
{
    use AuthorizesHelpdeskTools;

    public function __construct(
        private readonly InformationSystemStatusRecorder $recorder,
        private readonly StaffDirectoryLookupService $directory,
    ) {}

    public function languages(Request $request): JsonResponse
    {
        $this->ensureInformationSystemsManager($request);

        $rows = HelpdeskInformationSystemLanguage::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return response()->json(['data' => $rows]);
    }

    public function storeLanguage(Request $request): JsonResponse
    {
        $this->ensureInformationSystemsManager($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
        ]);
        $name = InformationSystemLanguageNormalizer::normalizeToken($validated['name']) ?? trim($validated['name']);
        $slug = InformationSystemLanguageNormalizer::slugFor($name);
        abort_if($slug === '', 422, 'Invalid language name.');

        $row = HelpdeskInformationSystemLanguage::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'is_active' => true],
        );

        return response()->json(['data' => $row], 201);
    }

    public function summary(Request $request): JsonResponse
    {
        $this->ensureInformationSystemsManager($request);

        $systems = HelpdeskInformationSystem::query()->get(['id', 'status', 'division_id', 'focal_staff_id', 'focal_name_raw', 'mis_focal_staff_id', 'mis_focal_name_raw']);
        $modules = HelpdeskInformationSystemModule::query()->get(['id', 'status']);

        $systemsByStatus = [];
        foreach (InformationSystemStatus::all() as $s) {
            $systemsByStatus[$s] = 0;
        }
        $modulesByStatus = $systemsByStatus;
        $byDivision = ['All' => 0];
        $divNames = $this->divisionNameMap();

        foreach ($systems as $sys) {
            $st = (string) $sys->status;
            $systemsByStatus[$st] = ($systemsByStatus[$st] ?? 0) + 1;
            $divId = (int) ($sys->division_id ?? 0);
            $label = $divId > 0 ? ($divNames[$divId] ?? ('Division #'.$divId)) : 'All';
            $byDivision[$label] = ($byDivision[$label] ?? 0) + 1;
        }
        foreach ($modules as $mod) {
            $st = (string) $mod->status;
            $modulesByStatus[$st] = ($modulesByStatus[$st] ?? 0) + 1;
        }

        $missingFocal = $systems->filter(fn ($s) => ! $s->focal_staff_id && ! $s->focal_name_raw)->count();
        $missingMis = $systems->filter(fn ($s) => ! $s->mis_focal_staff_id && ! $s->mis_focal_name_raw)->count();

        return response()->json([
            'data' => [
                'systems_total' => $systems->count(),
                'systems_by_status' => $systemsByStatus,
                'modules_total' => $modules->count(),
                'modules_by_status' => $modulesByStatus,
                'missing_focal' => $missingFocal,
                'missing_mis_focal' => $missingMis,
                'by_division' => $byDivision,
            ],
        ]);
    }

    public function trends(Request $request): JsonResponse
    {
        $this->ensureInformationSystemsManager($request);

        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $bucket = $request->query('bucket', 'day') === 'week' ? 'week' : 'day';

        $q = HelpdeskInformationSystemStatusEvent::query()->orderBy('changed_at');
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $q->where('changed_at', '>=', $dateFrom.' 00:00:00');
        }
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $q->where('changed_at', '<=', $dateTo.' 23:59:59');
        }

        $expr = $bucket === 'week'
            ? "DATE_FORMAT(changed_at, '%x-W%v')"
            : 'DATE(changed_at)';

        $rows = $q->selectRaw("{$expr} as bucket, to_status, COUNT(*) as c")
            ->groupBy('bucket', 'to_status')
            ->get()
            ->map(fn ($r) => [
                'date' => (string) $r->bucket,
                'to_status' => (string) $r->to_status,
                'count' => (int) $r->c,
            ]);

        return response()->json(['data' => $rows]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->ensureInformationSystemsManager($request);

        $rows = HelpdeskInformationSystem::query()
            ->with('languages')
            ->withCount('modules')
            ->orderBy('name')
            ->get();

        return Excel::download(
            new InformationSystemsExport($rows, $this->divisionNameMap()),
            'information-systems-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    public function index(Request $request): JsonResponse
    {
        $this->ensureInformationSystemsManager($request);

        $query = HelpdeskInformationSystem::query()
            ->with(['languages:id,name,slug'])
            ->withCount('modules');

        if ($request->filled('q')) {
            $q = '%'.$request->input('q').'%';
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', $q)
                    ->orWhere('description', 'like', $q)
                    ->orWhere('host', 'like', $q)
                    ->orWhere('domain', 'like', $q);
            });
        }
        if ($request->filled('status') && InformationSystemStatus::isValid((string) $request->input('status'))) {
            $query->where('status', $request->input('status'));
        }
        if ($request->has('division_id')) {
            $div = $request->input('division_id');
            if ($div === null || $div === '' || $div === 'all') {
                $query->whereNull('division_id');
            } else {
                $query->where('division_id', (int) $div);
            }
        }

        $rows = $query->orderBy('name')
            ->paginate(min(100, max(10, (int) $request->input('per_page', 25))));

        $divNames = $this->divisionNameMap();
        $rows->getCollection()->transform(function (HelpdeskInformationSystem $row) use ($divNames) {
            $divId = (int) ($row->division_id ?? 0);

            return array_merge($row->toArray(), [
                'division_label' => $divId > 0 ? ($divNames[$divId] ?? ('#'.$divId)) : 'All',
                'status_label' => InformationSystemStatus::label((string) $row->status),
            ]);
        });

        return response()->json($rows);
    }

    public function show(Request $request, HelpdeskInformationSystem $informationSystem): JsonResponse
    {
        $this->ensureInformationSystemsManager($request);

        $informationSystem->load(['languages', 'modules']);
        $divId = (int) ($informationSystem->division_id ?? 0);
        $divNames = $this->divisionNameMap();

        return response()->json([
            'data' => array_merge($informationSystem->toArray(), [
                'division_label' => $divId > 0 ? ($divNames[$divId] ?? ('#'.$divId)) : 'All',
                'status_label' => InformationSystemStatus::label((string) $informationSystem->status),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureInformationSystemsManager($request);
        $validated = $this->validateSystem($request);
        $languageIds = $validated['language_ids'] ?? [];
        unset($validated['language_ids']);

        if (empty($validated['version'])) {
            $validated['version'] = '1.0';
        }

        $row = HelpdeskInformationSystem::query()->create(array_merge($validated, [
            'created_by_user_id' => $request->user()?->id,
        ]));
        $row->languages()->sync($languageIds);
        $this->recorder->record('system', (int) $row->id, null, (string) $row->status, $request->user()?->id);

        return response()->json(['data' => $row->load('languages')->loadCount('modules')], 201);
    }

    public function update(Request $request, HelpdeskInformationSystem $informationSystem): JsonResponse
    {
        $this->ensureInformationSystemsManager($request);
        $validated = $this->validateSystem($request, $informationSystem->id);
        $languageIds = $validated['language_ids'] ?? null;
        unset($validated['language_ids']);

        $from = (string) $informationSystem->status;
        $informationSystem->fill($validated)->save();
        if (is_array($languageIds)) {
            $informationSystem->languages()->sync($languageIds);
        }
        if (isset($validated['status']) && $from !== (string) $validated['status']) {
            $this->recorder->record('system', (int) $informationSystem->id, $from, (string) $validated['status'], $request->user()?->id);
        }

        return response()->json(['data' => $informationSystem->fresh(['languages'])->loadCount('modules')]);
    }

    public function destroy(Request $request, HelpdeskInformationSystem $informationSystem): JsonResponse
    {
        $this->ensureInformationSystemsManager($request);
        $informationSystem->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function storeModule(Request $request, HelpdeskInformationSystem $informationSystem): JsonResponse
    {
        $this->ensureInformationSystemsManager($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(InformationSystemStatus::all())],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $module = $informationSystem->modules()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);
        $this->recorder->record('module', (int) $module->id, null, (string) $module->status, $request->user()?->id);

        return response()->json(['data' => $module], 201);
    }

    public function updateModule(
        Request $request,
        HelpdeskInformationSystem $informationSystem,
        HelpdeskInformationSystemModule $module,
    ): JsonResponse {
        $this->ensureInformationSystemsManager($request);
        abort_unless((int) $module->information_system_id === (int) $informationSystem->id, 404);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(InformationSystemStatus::all())],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $from = (string) $module->status;
        $module->fill($validated)->save();
        if (isset($validated['status']) && $from !== (string) $validated['status']) {
            $this->recorder->record('module', (int) $module->id, $from, (string) $validated['status'], $request->user()?->id);
        }

        return response()->json(['data' => $module]);
    }

    public function destroyModule(
        Request $request,
        HelpdeskInformationSystem $informationSystem,
        HelpdeskInformationSystemModule $module,
    ): JsonResponse {
        $this->ensureInformationSystemsManager($request);
        abort_unless((int) $module->information_system_id === (int) $informationSystem->id, 404);
        $module->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSystem(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:191',
                Rule::unique('helpdesk_information_systems', 'name')->ignore($ignoreId),
            ],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(InformationSystemStatus::all())],
            'host' => ['nullable', 'string', 'max:191'],
            'host_name' => ['nullable', 'string', 'max:191'],
            'ip' => ['nullable', 'string', 'max:64'],
            'domain' => ['nullable', 'string', 'max:191'],
            'os' => ['nullable', 'string', 'max:191'],
            'version' => ['nullable', 'string', 'max:64'],
            'last_update_on' => ['nullable', 'date'],
            'division_id' => ['nullable', 'integer', 'min:1'],
            'focal_staff_id' => ['nullable', 'integer', 'min:1'],
            'focal_name_raw' => ['nullable', 'string', 'max:191'],
            'mis_focal_staff_id' => ['nullable', 'integer', 'min:1'],
            'mis_focal_name_raw' => ['nullable', 'string', 'max:191'],
            'system_profile_url' => ['nullable', 'string', 'max:2048'],
            'user_manual_users_url' => ['nullable', 'string', 'max:2048'],
            'user_manual_managers_url' => ['nullable', 'string', 'max:2048'],
            'user_manual_technical_url' => ['nullable', 'string', 'max:2048'],
            'faqs' => ['nullable', 'string'],
            'sops' => ['nullable', 'string'],
            'total_users' => ['nullable', 'integer', 'min:0'],
            'estimated_annual_hosting_cost' => ['nullable', 'numeric', 'min:0'],
            'language_ids' => ['sometimes', 'array'],
            'language_ids.*' => ['integer', 'exists:helpdesk_information_system_languages,id'],
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function divisionNameMap(): array
    {
        $map = [];
        foreach ($this->directory->divisionsForSelect() as $row) {
            $map[(int) $row['id']] = (string) $row['name'];
        }

        return $map;
    }
}
