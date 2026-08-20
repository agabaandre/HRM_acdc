<?php

namespace Modules\Settings\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\PortalPermission;
use Modules\Performance\Services\PpaSettingsService;
use Modules\Performance\Support\PerformanceMonth;
use Modules\Performance\Support\PerformanceSettingsAccess;
use Modules\Settings\Services\PortalModulesService;
use Modules\Settings\Services\SettingsLookupService;

class SettingsApiController extends Controller
{
    public function __construct(
        protected SettingsLookupService $lookups,
        protected PpaSettingsService $performance,
        protected PortalModulesService $portalModules,
    ) {}

    public function hub(): JsonResponse
    {
        PortalPermission::authorize(15);

        $cards = [
            ['to' => '/settings/portal-modules', 'label' => 'Portal modules', 'icon' => 'bx-toggle-left', 'special' => true],
            ['to' => '/settings/shared-storage', 'label' => 'Shared storage', 'icon' => 'bx-hdd', 'special' => true],
            ['to' => '/settings/staff-jobs', 'label' => 'Staff jobs', 'icon' => 'bx-timer', 'special' => true],
            ['to' => '/settings/email-servers', 'label' => 'Email servers', 'icon' => 'bx-envelope', 'special' => true],
            ['to' => '/settings/lookup/cbp_modules', 'label' => 'CBP modules', 'icon' => 'bx-grid-alt', 'special' => true],
            ['to' => '/settings/lookup/nationalities', 'label' => 'Nationalities', 'icon' => 'bx-globe'],
            ['to' => '/settings/lookup/duty_stations', 'label' => 'Duty Stations', 'icon' => 'bx-map'],
            ['to' => '/settings/lookup/contracting_institutions', 'label' => 'Contracting Institutions', 'icon' => 'bx-network-chart'],
            ['to' => '/settings/lookup/contract_types', 'label' => 'Contract Types', 'icon' => 'bx-group'],
            ['to' => '/settings/lookup/directorates', 'label' => 'Directorates', 'icon' => 'bx-git-branch'],
            ['to' => '/settings/lookup/divisions', 'label' => 'Divisions', 'icon' => 'bx-sitemap', 'special' => true],
            ['to' => '/settings/org-structure', 'label' => 'Organizational structure', 'icon' => 'bx-git-repo-forked', 'special' => true],
            ['to' => '/settings/lookup/kin_relationship_types', 'label' => 'Next of kin relationships', 'icon' => 'bx-group'],
            ['to' => '/settings/lookup/grades', 'label' => 'Grades', 'icon' => 'bx-bar-chart-alt-2'],
            ['to' => '/settings/lookup/jobs', 'label' => 'Jobs', 'icon' => 'bx-briefcase'],
            ['to' => '/settings/lookup/funders', 'label' => 'Funders', 'icon' => 'bx-dollar'],
            ['to' => '/settings/leave', 'label' => 'Leave policy & types', 'icon' => 'bx-time-five'],
            ['to' => '/leave/admin/balances', 'label' => 'Leave balances (all staff)', 'icon' => 'bx-calendar-check'],
            ['to' => '/settings/performance', 'label' => 'PPA / Performance deadlines', 'icon' => 'bx-line-chart'],
            ['to' => '/settings/lookup/regions', 'label' => 'Regions', 'icon' => 'bx-compass'],
            ['to' => '/settings/lookup/units', 'label' => 'Units', 'icon' => 'bx-building'],
            ['to' => '/settings/lookup/training_skills', 'label' => 'Training Skills', 'icon' => 'bx-book'],
            ['to' => '/settings/lookup/au_values', 'label' => 'AU Values', 'icon' => 'bx-star'],
        ];

        return response()->json(['data' => $cards]);
    }

    public function showPortalModules(): JsonResponse
    {
        PortalPermission::authorize(15);

        return response()->json([
            'data' => [
                'modules' => $this->portalModules->adminList(),
                'enabled' => $this->portalModules->enabledMap(),
            ],
        ]);
    }

    public function updatePortalModules(Request $request): JsonResponse
    {
        PortalPermission::authorize(15);

        $data = $request->validate([
            'modules' => 'required|array',
            'modules.*' => 'boolean',
        ]);

        $list = $this->portalModules->save($data['modules']);

        return response()->json([
            'message' => 'Portal modules updated.',
            'data' => [
                'modules' => $list,
                'enabled' => $this->portalModules->enabledMap(),
            ],
        ]);
    }

    public function lookupCatalog(): JsonResponse
    {
        PortalPermission::authorize(15);
        $tables = config('settings.lookup-tables', []);

        $data = [];
        foreach ($tables as $key => $cfg) {
            $data[] = [
                'key' => $key,
                'label' => $cfg['label'] ?? $key,
                'pk' => $cfg['pk'] ?? 'id',
                'columns' => $cfg['columns'] ?? [],
                'order' => $cfg['order'] ?? null,
                'read_only' => false,
            ];
        }

        $data[] = [
            'key' => 'cbp_modules',
            'label' => 'CBP modules',
            'pk' => 'id',
            'columns' => [],
            'order' => 'sort_order',
            'read_only' => true,
        ];

        return response()->json(['data' => $data]);
    }

    public function lookupIndex(Request $request, string $table): JsonResponse
    {
        PortalPermission::authorize(15);

        if ($table === 'cbp_modules') {
            $rows = DB::table('cbp_modules')
                ->orderBy('sort_order')
                ->get(['id', 'sort_order', 'system_name', 'module_key', 'is_enabled', 'is_production']);

            return response()->json([
                'data' => $rows,
                'meta' => [
                    'read_only' => true,
                    'label' => 'CBP modules',
                    'pk' => 'id',
                ],
            ]);
        }

        $cfg = $this->lookups->config($table);
        if ($cfg === null) {
            return response()->json(['message' => 'Unknown lookup table.'], 404);
        }

        $perPage = min(100, max(5, (int) $request->query('per_page', 20)));
        $page = max(1, (int) $request->query('page', 1));
        $paginator = $this->lookups->paginate(
            $table,
            (string) $request->query('q', ''),
            $perPage,
            $page
        );

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'read_only' => false,
                'label' => $cfg['label'] ?? $table,
                'pk' => $cfg['pk'],
                'columns' => $cfg['columns'],
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function lookupStore(Request $request, string $table): JsonResponse
    {
        PortalPermission::authorize(15);
        $cfg = $this->lookups->config($table);
        if ($cfg === null) {
            return response()->json(['message' => 'Unknown lookup table.'], 404);
        }

        if ($invalid = $this->invalidSelectResponse($cfg, $request->all())) {
            return $invalid;
        }

        if (! $this->lookups->create($table, $request->all())) {
            return response()->json(['message' => 'Could not create record.'], 422);
        }

        return response()->json(['message' => 'Record created.'], 201);
    }

    public function lookupUpdate(Request $request, string $table, int|string $id): JsonResponse
    {
        PortalPermission::authorize(15);
        $cfg = $this->lookups->config($table);
        if ($cfg === null) {
            return response()->json(['message' => 'Unknown lookup table.'], 404);
        }

        if ($invalid = $this->invalidSelectResponse($cfg, $request->all())) {
            return $invalid;
        }

        if (! $this->lookups->update($table, $id, $request->all())) {
            return response()->json(['message' => 'Could not update record.'], 422);
        }

        return response()->json(['message' => 'Record updated.']);
    }

    public function lookupDestroy(string $table, int|string $id): JsonResponse
    {
        PortalPermission::authorize(15);
        $cfg = $this->lookups->config($table);
        if ($cfg === null) {
            return response()->json(['message' => 'Unknown lookup table.'], 404);
        }

        if (! $this->lookups->delete($table, $id)) {
            return response()->json(['message' => 'Could not delete record.'], 422);
        }

        return response()->json(['message' => 'Record deleted.']);
    }

    public function showPerformance(): JsonResponse
    {
        PerformanceSettingsAccess::authorize();
        $s = $this->performance->settings();
        $year = (int) date('Y');

        return response()->json([
            'data' => [
                'settings' => [
                    'allow_supervisor_return' => (bool) ($s->allow_supervisor_return ?? true),
                    'allow_supervisor_comments' => (bool) ($s->allow_supervisor_comments ?? false),
                    'allow_supervisor_ppa_edit' => (bool) ($s->allow_supervisor_ppa_edit ?? true),
                    'allow_employee_comments' => (bool) ($s->allow_employee_comments ?? false),
                    'ppa_requires_second_supervisor' => (bool) ($s->ppa_requires_second_supervisor ?? false),
                    'midterm_requires_second_supervisor' => (bool) ($s->midterm_requires_second_supervisor ?? false),
                    'endterm_requires_second_supervisor' => (bool) ($s->endterm_requires_second_supervisor ?? true),
                    'endterm_requires_employee_consent' => (bool) ($s->endterm_requires_employee_consent ?? true),
                    // PPA always opens from January each year unless overridden.
                    'ppa_start' => PerformanceMonth::normalize($s->ppa_start ?? null) ?? 1,
                    'ppa_deadline' => PerformanceMonth::normalize($s->ppa_deadline ?? null),
                    'ppa_deadline_override_days' => max(0, (int) ($s->ppa_deadline_override_days ?? 0)),
                    'mid_term_start' => PerformanceMonth::normalize($s->mid_term_start ?? null),
                    'mid_term_deadline' => PerformanceMonth::normalize($s->mid_term_deadline ?? null),
                    'mid_term_deadline_override_days' => max(0, (int) ($s->mid_term_deadline_override_days ?? 0)),
                    'end_term_start' => PerformanceMonth::normalize($s->end_term_start ?? null),
                    'end_term_deadline' => PerformanceMonth::normalize($s->end_term_deadline ?? null),
                    'end_term_deadline_override_days' => max(0, (int) ($s->end_term_deadline_override_days ?? 0)),
                ],
                'workflow_preview' => $this->performance->workflowDescriptions(),
                'window_statuses' => $this->performance->allSubmissionWindowStatuses(),
                'month_options' => PerformanceMonth::options(),
                'current_month_label' => PerformanceMonth::label((int) date('n')),
                'current_year' => $year,
                'help' => [
                    'ppa' => "PPA opens each year from January through the deadline month in {$year}.",
                    'midterm' => 'Midterm uses months in the current year. If start is after end (e.g. Dec–Jul), the window wraps into the next year.',
                    'endterm' => "Endterm starts in {$year} and ends in ".($year + 1).' (e.g. December → March).',
                    'override' => 'Override days keep the window open past the last day of the deadline month.',
                ],
            ],
        ]);
    }

    public function updatePerformance(Request $request): JsonResponse
    {
        PerformanceSettingsAccess::authorize();
        $validated = $request->validate([
            'allow_supervisor_return' => 'boolean',
            'allow_supervisor_comments' => 'boolean',
            'allow_supervisor_ppa_edit' => 'boolean',
            'allow_employee_comments' => 'boolean',
            'ppa_requires_second_supervisor' => 'boolean',
            'midterm_requires_second_supervisor' => 'boolean',
            'endterm_requires_second_supervisor' => 'boolean',
            'endterm_requires_employee_consent' => 'boolean',
            'ppa_start' => 'nullable|integer|min:1|max:12',
            'ppa_deadline' => 'nullable|integer|min:1|max:12',
            'ppa_deadline_override_days' => 'nullable|integer|min:0|max:365',
            'mid_term_start' => 'nullable|integer|min:1|max:12',
            'mid_term_deadline' => 'nullable|integer|min:1|max:12',
            'mid_term_deadline_override_days' => 'nullable|integer|min:0|max:365',
            'end_term_start' => 'nullable|integer|min:1|max:12',
            'end_term_deadline' => 'nullable|integer|min:1|max:12',
            'end_term_deadline_override_days' => 'nullable|integer|min:0|max:365',
        ]);

        $this->performance->save([
            'allow_supervisor_return' => ! empty($validated['allow_supervisor_return']) ? 1 : 0,
            'allow_supervisor_comments' => ! empty($validated['allow_supervisor_comments']) ? 1 : 0,
            'allow_supervisor_ppa_edit' => ! empty($validated['allow_supervisor_ppa_edit']) ? 1 : 0,
            'allow_employee_comments' => ! empty($validated['allow_employee_comments']) ? 1 : 0,
            'ppa_requires_second_supervisor' => ! empty($validated['ppa_requires_second_supervisor']) ? 1 : 0,
            'midterm_requires_second_supervisor' => ! empty($validated['midterm_requires_second_supervisor']) ? 1 : 0,
            'endterm_requires_second_supervisor' => ! empty($validated['endterm_requires_second_supervisor']) ? 1 : 0,
            'endterm_requires_employee_consent' => ! empty($validated['endterm_requires_employee_consent']) ? 1 : 0,
            'ppa_start' => $validated['ppa_start'] ?? 1,
            'ppa_deadline' => $validated['ppa_deadline'] ?? null,
            'ppa_deadline_override_days' => max(0, (int) ($validated['ppa_deadline_override_days'] ?? 0)),
            'mid_term_start' => $validated['mid_term_start'] ?? null,
            'mid_term_deadline' => $validated['mid_term_deadline'] ?? null,
            'mid_term_deadline_override_days' => max(0, (int) ($validated['mid_term_deadline_override_days'] ?? 0)),
            'end_term_start' => $validated['end_term_start'] ?? null,
            'end_term_deadline' => $validated['end_term_deadline'] ?? null,
            'end_term_deadline_override_days' => max(0, (int) ($validated['end_term_deadline_override_days'] ?? 0)),
        ]);

        return response()->json(['message' => 'Performance & workflow settings saved.']);
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @param  array<string, mixed>  $data
     */
    protected function invalidSelectResponse(array $cfg, array $data): ?JsonResponse
    {
        $errors = SettingsLookupService::selectValueErrors($cfg['columns'] ?? [], $data);
        if ($errors === []) {
            return null;
        }

        return response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $errors,
        ], 422);
    }
}
