<?php

namespace Modules\Staff\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Staff\Services\StaffContractService;
use Modules\Staff\Services\StaffCreateService;
use Modules\Staff\Services\StaffDirectoryService;
use Modules\Staff\Services\StaffProfileService;
use Modules\Staff\Support\StaffAccess;

class StaffApiController extends Controller
{
    public function formLookups(StaffContractService $contracts): JsonResponse
    {
        if (! StaffAccess::canManageStaff()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json([
            'data' => $contracts->formLookups(),
        ]);
    }

    public function store(Request $request, StaffCreateService $creator): JsonResponse
    {
        if (! StaffAccess::canManageStaff()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $created = $creator->create($this->validatedStorePayload($request));

        return response()->json([
            'data' => $created,
            'message' => 'Staff created successfully.',
        ], 201);
    }

    public function index(Request $request, StaffDirectoryService $directory): JsonResponse
    {
        if (! StaffAccess::canViewDirectory()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $preset = (string) $request->query('preset', 'active');
        $statusId = match ($preset) {
            'due' => 2,
            'expired' => 3,
            'former' => 4,
            'renewal' => 7,
            'all' => null,
            default => [1, 2],
        };

        $search = (string) $request->query('q', '');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(10, (int) $request->query('per_page', 20)));
        $category = $this->normalizeCategory((string) $request->query('category', 'main_staff'));

        $paginator = $directory->paginate($search, $statusId, $page, $perPage, $category);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'filter_counts' => $directory->filterCounts($search, $category),
                'preset' => $preset,
                'category' => $category,
            ],
        ]);
    }

    public function show(int $staff, StaffProfileService $profiles): JsonResponse
    {
        if (! StaffAccess::canViewProfile($staff)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $row = $profiles->find($staff);
        if (! $row) {
            return response()->json(['message' => 'Staff not found.'], 404);
        }

        return response()->json([
            'data' => [
                'staff' => $row,
                'contracts' => $profiles->contracts($staff),
                'can_manage' => StaffAccess::canManageStaff(),
                'can_manage_contracts' => StaffAccess::canManageContracts(),
            ],
        ]);
    }

    public function storeContract(int $staff, Request $request, StaffContractService $contracts): JsonResponse
    {
        if (! StaffAccess::canManageContracts()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (! DB::table('staff')->where('staff_id', $staff)->exists()) {
            return response()->json(['message' => 'Staff not found.'], 404);
        }

        try {
            $contractId = $contracts->create($staff, $this->validatedContractPayload($request));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        }

        return response()->json([
            'data' => [
                'contract_id' => $contractId,
            ],
            'message' => 'Contract created successfully.',
        ], 201);
    }

    public function updateContract(int $staff, int $contractId, Request $request, StaffContractService $contracts): JsonResponse
    {
        if (! StaffAccess::canManageContracts()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (! DB::table('staff')->where('staff_id', $staff)->exists()) {
            return response()->json(['message' => 'Staff not found.'], 404);
        }

        try {
            $updated = $contracts->update($contractId, $staff, $this->validatedContractPayload($request));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        }

        if (! $updated) {
            return response()->json(['message' => 'Contract not found.'], 404);
        }

        return response()->json([
            'data' => [
                'contract_id' => $contractId,
            ],
            'message' => 'Contract updated successfully.',
        ]);
    }

    public function birthdays(): JsonResponse
    {
        if (! StaffAccess::canViewDirectory() && ! \Modules\Core\Support\PortalPermission::can(41)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $month = (int) date('n');
        $rows = DB::table('staff as s')
            ->leftJoin('staff_contracts as sc', function ($j): void {
                $j->on('sc.staff_id', '=', 's.staff_id')
                    ->whereIn('sc.status_id', [1, 2]);
            })
            ->leftJoin('divisions as d', 'd.division_id', '=', 'sc.division_id')
            ->whereNotNull('s.date_of_birth')
            ->whereRaw('MONTH(s.date_of_birth) = ?', [$month])
            ->orderByRaw('DAY(s.date_of_birth)')
            ->orderBy('s.lname')
            ->select(
                's.staff_id',
                's.fname',
                's.lname',
                's.date_of_birth',
                's.work_email',
                'd.division_name'
            )
            ->distinct()
            ->limit(200)
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function dataQuality(): JsonResponse
    {
        if (! StaffAccess::canManageStaff()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $missingEmail = DB::table('staff')->where(function ($q): void {
            $q->whereNull('work_email')->orWhere('work_email', '');
        })->count();
        $missingDob = DB::table('staff')->whereNull('date_of_birth')->count();
        $missingSap = DB::table('staff')->where(function ($q): void {
            $q->whereNull('sap_number')->orWhere('sap_number', '');
        })->count();

        $sample = DB::table('staff as s')
            ->where(function ($q): void {
                $q->whereNull('s.work_email')->orWhere('s.work_email', '')
                    ->orWhereNull('s.date_of_birth')
                    ->orWhereNull('s.sap_number')->orWhere('s.sap_number', '');
            })
            ->orderBy('s.lname')
            ->limit(50)
            ->get(['s.staff_id', 's.fname', 's.lname', 's.work_email', 's.date_of_birth', 's.sap_number']);

        return response()->json([
            'data' => [
                'counts' => [
                    'missing_email' => $missingEmail,
                    'missing_dob' => $missingDob,
                    'missing_sap' => $missingSap,
                ],
                'sample' => $sample,
            ],
        ]);
    }

    public function exportCsv(Request $request, StaffDirectoryService $directory, \Modules\Core\Services\CsvExportService $csv): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (! StaffAccess::canViewDirectory()) {
            abort(403);
        }

        $preset = (string) $request->query('preset', 'active');
        $statusId = match ($preset) {
            'due' => 2,
            'expired' => 3,
            'former' => 4,
            'renewal' => 7,
            'all' => null,
            default => [1, 2],
        };
        $search = (string) $request->query('q', '');
        $category = $this->normalizeCategory((string) $request->query('category', 'main_staff'));
        $exported = $directory->exportRows($search, $statusId, $category);
        $selectedColumns = $this->selectedExportColumns($request);
        $rows = [[
            'Staff ID',
            ...array_map(
                fn (string $column): string => $this->csvColumnDefinitions()[$column]['label'],
                $selectedColumns
            ),
        ]];

        foreach ($exported as $item) {
            $r = (array) $item;
            $row = [$r['staff_id'] ?? ''];

            foreach ($selectedColumns as $column) {
                $row[] = $this->csvColumnValue($column, $r);
            }

            $rows[] = $row;
        }

        return $csv->stream('staff-directory.csv', $rows);
    }

    protected function normalizeCategory(string $category): string
    {
        return in_array($category, ['main_staff', 'other_staff', 'all'], true)
            ? $category
            : 'main_staff';
    }

    protected function humanizeCategory(string $category): string
    {
        return match ($category) {
            'main_staff' => 'Main staff',
            'other_staff' => 'Other staff',
            default => $category,
        };
    }

    /**
     * @return list<string>
     */
    protected function selectedExportColumns(Request $request): array
    {
        $defaults = ['photo', 'name', 'work_email', 'job', 'division', 'duty_station', 'contract_type', 'status', 'end_date'];
        $requested = array_values(array_filter(array_map(
            static fn (string $column): string => trim($column),
            explode(',', (string) $request->query('columns', ''))
        )));

        if ($requested === []) {
            return $defaults;
        }

        $definitions = $this->csvColumnDefinitions();
        $selected = [];
        foreach ($requested as $column) {
            if (array_key_exists($column, $definitions) && ! in_array($column, $selected, true)) {
                $selected[] = $column;
            }
        }

        return $selected !== [] ? $selected : $defaults;
    }

    /**
     * @return array<string, array{label: string}>
     */
    protected function csvColumnDefinitions(): array
    {
        return [
            'photo' => ['label' => 'Photo'],
            'name' => ['label' => 'Name'],
            'work_email' => ['label' => 'Work email'],
            'sap_number' => ['label' => 'SAP'],
            'job' => ['label' => 'Job'],
            'division' => ['label' => 'Division'],
            'duty_station' => ['label' => 'Duty station'],
            'contract_type' => ['label' => 'Contract type'],
            'category' => ['label' => 'Category'],
            'status' => ['label' => 'Status'],
            'grade' => ['label' => 'Grade'],
            'start_date' => ['label' => 'Start date'],
            'end_date' => ['label' => 'End date'],
            'funder' => ['label' => 'Funder'],
            'nationality' => ['label' => 'Nationality'],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function csvColumnValue(string $column, array $row): string
    {
        return match ($column) {
            'photo' => (string) ($row['photo'] ?? ''),
            'name' => $this->csvPersonName($row),
            'work_email' => (string) ($row['work_email'] ?? ''),
            'sap_number' => (string) ($row['SAPNO'] ?? ''),
            'job' => (string) (($row['job_name'] ?? '') ?: ($row['job_acting'] ?? '')),
            'division' => (string) ($row['division_name'] ?? ''),
            'duty_station' => (string) ($row['duty_station_name'] ?? ''),
            'contract_type' => (string) ($row['contract_type'] ?? ''),
            'category' => $this->humanizeCategory((string) ($row['category'] ?? '')),
            'status' => (string) ($row['contract_status'] ?? ''),
            'grade' => (string) ($row['grade'] ?? ''),
            'start_date' => (string) ($row['start_date'] ?? ''),
            'end_date' => (string) ($row['end_date'] ?? ''),
            'funder' => (string) ($row['funder'] ?? ''),
            'nationality' => (string) ($row['nationality'] ?? ''),
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function csvPersonName(array $row): string
    {
        $lastName = trim((string) ($row['lname'] ?? ''));
        $firstName = trim((string) ($row['fname'] ?? ''));

        if ($lastName !== '' && $firstName !== '') {
            return $lastName.', '.$firstName;
        }

        return $lastName !== '' ? $lastName : $firstName;
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedStorePayload(Request $request): array
    {
        $validated = $request->validate([
            'SAPNO' => ['nullable', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:20'],
            'fname' => ['required', 'string', 'max:100'],
            'lname' => ['required', 'string', 'max:100'],
            'oname' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => [
                'required',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    try {
                        $birthDate = Carbon::createFromFormat('Y-m-d', (string) $value)->startOfDay();
                    } catch (\Throwable) {
                        return;
                    }

                    $adultThreshold = now()->subYears(18)->startOfDay();
                    if ($birthDate->gt($adultThreshold)) {
                        $fail('Staff must be at least 18 years old.');
                    }
                },
            ],
            'gender' => ['required', 'string', 'in:Male,Female,Other'],
            'nationality_id' => ['required', 'integer', 'min:1', 'exists:nationalities,nationality_id'],
            'initiation_date' => ['required', 'date_format:Y-m-d'],
            'tel_1' => ['required', 'string', 'max:50'],
            'tel_2' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'work_email' => ['required', 'email:rfc', 'max:255', 'unique:staff,work_email'],
            'private_email' => ['nullable', 'email:rfc', 'max:255'],
            'physical_location' => ['nullable', 'string', 'max:1000'],
            'job_id' => ['required', 'integer', 'min:1', 'exists:jobs,job_id'],
            'job_acting_id' => ['nullable', 'integer', 'min:1', 'exists:jobs_acting,job_acting_id'],
            'grade_id' => ['required', 'string', 'max:20', 'exists:grades,grade_id'],
            'contracting_institution_id' => ['required', 'integer', 'min:1', 'exists:contracting_institutions,contracting_institution_id'],
            'funder_id' => ['required', 'integer', 'min:1', 'exists:funders,funder_id'],
            'first_supervisor' => ['required', 'integer', 'min:1', 'exists:staff,staff_id'],
            'second_supervisor' => ['nullable', 'integer', 'min:1', 'exists:staff,staff_id'],
            'contract_type_id' => ['required', 'integer', 'min:1', 'exists:contract_types,contract_type_id'],
            'duty_station_id' => ['required', 'integer', 'min:1', 'exists:duty_stations,duty_station_id'],
            'division_id' => ['required', 'integer', 'min:1', 'exists:divisions,division_id'],
            'unit_id' => ['nullable', 'integer', 'min:1', 'exists:units,unit_id'],
            'other_associated_divisions' => ['nullable', 'array'],
            'other_associated_divisions.*' => ['integer', 'distinct', 'min:1', 'exists:divisions,division_id'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date'],
            'comments' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['other_associated_divisions'] = array_values(
            array_map('intval', (array) ($validated['other_associated_divisions'] ?? []))
        );

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedContractPayload(Request $request): array
    {
        $validated = $request->validate([
            'job_id' => ['required', 'integer', 'min:1', 'exists:jobs,job_id'],
            'job_acting_id' => ['nullable', 'integer', 'min:1', 'exists:jobs_acting,job_acting_id'],
            'grade_id' => ['required', 'string', 'max:20', 'exists:grades,grade_id'],
            'contracting_institution_id' => ['required', 'integer', 'min:1', 'exists:contracting_institutions,contracting_institution_id'],
            'funder_id' => ['required', 'integer', 'min:1', 'exists:funders,funder_id'],
            'first_supervisor' => ['required', 'integer', 'min:1', 'exists:staff,staff_id'],
            'second_supervisor' => ['nullable', 'integer', 'min:1', 'exists:staff,staff_id'],
            'contract_type_id' => ['required', 'integer', 'min:1', 'exists:contract_types,contract_type_id'],
            'duty_station_id' => ['required', 'integer', 'min:1', 'exists:duty_stations,duty_station_id'],
            'division_id' => ['required', 'integer', 'min:1', 'exists:divisions,division_id'],
            'unit_id' => ['nullable', 'integer', 'min:1', 'exists:units,unit_id'],
            'other_associated_divisions' => ['nullable', 'array'],
            'other_associated_divisions.*' => ['integer', 'distinct', 'min:1', 'exists:divisions,division_id'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date'],
            'status_id' => ['required', 'integer', 'min:1', 'exists:status,status_id'],
            'comments' => ['nullable', 'string', 'max:2000'],
        ]);

        $validated['other_associated_divisions'] = array_values(
            array_map('intval', (array) ($validated['other_associated_divisions'] ?? []))
        );

        return $validated;
    }

    protected function validationErrorResponse(ValidationException $e): JsonResponse
    {
        return response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $e->errors(),
        ], 422);
    }
}
