<?php

namespace Modules\Staff\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\PortalReadCache;
use App\Support\PortalReferenceCache;
use App\Support\StaffContractFile;
use App\Support\StaffPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Models\PortalUser;
use Modules\Core\Services\PdfService;
use Modules\Staff\Services\StaffAuditTrailService;
use Modules\Staff\Services\StaffContractService;
use Modules\Staff\Services\StaffCreateService;
use Modules\Staff\Services\StaffDirectoryService;
use Modules\Staff\Services\StaffProfileService;
use Modules\Staff\Support\StaffAccess;

class StaffApiController extends Controller
{
    public function formLookups(StaffContractService $contracts, StaffProfileService $profiles): JsonResponse
    {
        if (! StaffAccess::canManageStaff()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $contracts->formLookups();
        $data['kin_relationship_types'] = $profiles->kinRelationshipTypes();

        return response()->json([
            'data' => $data,
        ]);
    }

    public function store(Request $request, StaffCreateService $creator): JsonResponse
    {
        if (! StaffAccess::canManageStaff()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($request->has('pay') && is_string($request->input('pay'))) {
            $decoded = json_decode((string) $request->input('pay'), true);
            $request->merge(['pay' => is_array($decoded) ? $decoded : null]);
        }

        $payInput = $request->input('pay');
        if (is_array($payInput)) {
            $hasBasic = array_key_exists('basic_salary', $payInput)
                && $payInput['basic_salary'] !== null
                && $payInput['basic_salary'] !== '';
            if (! $hasBasic) {
                $request->request->remove('pay');
            }
        }

        $created = $creator->create(
            $this->validatedStorePayload($request),
            $this->optionalContractPdf($request),
            $this->optionalPassportFile($request),
        );
        PortalReferenceCache::bustFormLookups();
        PortalReadCache::bust('staff');

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
        $filters = $directory->normalizeFilters($this->staffFilterInput($request));

        $user = auth()->user();
        $userId = $user instanceof PortalUser ? (int) $user->getAuthIdentifier() : 0;
        $cacheKey = PortalReadCache::key('staff', 'directory', $userId, [
            'preset' => $preset,
            'q' => $search,
            'page' => $page,
            'per_page' => $perPage,
            'category' => $category,
            'filters' => $filters,
        ]);

        $payload = PortalReadCache::remember($cacheKey, function () use (
            $directory,
            $search,
            $statusId,
            $page,
            $perPage,
            $category,
            $preset,
            $filters,
        ): array {
            $paginator = $directory->paginate($search, $statusId, $page, $perPage, $category, $filters);

            return [
                'data' => $paginator->items(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'filter_counts' => $directory->filterCounts($search, $category, $filters),
                    'preset' => $preset,
                    'category' => $category,
                    'filters' => $filters,
                ],
            ];
        });

        return response()->json($payload);
    }

    public function filterOptions(StaffDirectoryService $directory): JsonResponse
    {
        if (! StaffAccess::canViewDirectory()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json(['data' => $directory->filterOptions()]);
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

        $contracts = array_map(function (object $contract): object {
            $file = trim((string) ($contract->file_name ?? ''));
            $contract->contract_file_url = $file !== '' ? StaffContractFile::url($file) : null;
            $contract->has_signed_contract = StaffContractFile::exists($file !== '' ? $file : null);

            return $contract;
        }, $profiles->contracts($staff));

        $staffPayload = (array) $row;
        $this->enrichStaffMedia($staffPayload, $profiles);
        $dob = trim((string) ($staffPayload['date_of_birth'] ?? ''));
        $staffPayload['age'] = $this->ageFromDob($dob);
        $nok = json_decode((string) ($staffPayload['next_of_kin_json'] ?? '[]'), true);
        $staffPayload['next_of_kin'] = $this->presentNextOfKin(is_array($nok) ? $nok : []);

        return response()->json([
            'data' => [
                'staff' => $staffPayload,
                'contracts' => $contracts,
                'can_manage' => StaffAccess::canManageStaff(),
                'can_manage_contracts' => StaffAccess::canManageContracts(),
            ],
        ]);
    }

    public function auditTrail(int $staff, Request $request, StaffAuditTrailService $audit, StaffProfileService $profiles): JsonResponse
    {
        if (! StaffAccess::canViewProfile($staff)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (! $profiles->find($staff)) {
            return response()->json(['message' => 'Staff not found.'], 404);
        }

        $limit = min(200, max(1, (int) $request->query('limit', 100)));

        return response()->json([
            'data' => $audit->trailForStaff($staff, $limit),
            'meta' => [
                'structured_columns' => $audit->structuredColumnsActive(),
                'limit' => $limit,
            ],
        ]);
    }

    public function updateBiodata(int $staff, Request $request, StaffProfileService $profiles): JsonResponse
    {
        if (! StaffAccess::canManageStaff()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (! $profiles->find($staff)) {
            return response()->json(['message' => 'Staff not found.'], 404);
        }

        $data = $request->validate([
            'SAPNO' => ['nullable', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:20'],
            'fname' => ['required', 'string', 'max:100'],
            'lname' => ['required', 'string', 'max:100'],
            'oname' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date'],
            'gender' => ['required', 'string', 'in:Male,Female,Other'],
            'nationality_id' => ['required', 'integer', 'min:1', 'exists:nationalities,nationality_id'],
            'initiation_date' => ['required', 'date'],
            'tel_1' => ['required', 'string', 'max:50'],
            'tel_2' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'work_email' => ['required', 'email', 'max:150'],
            'private_email' => ['nullable', 'email', 'max:150'],
            'physical_location' => ['nullable', 'string', 'max:500'],
            'next_of_kin' => ['nullable', 'array', 'max:2'],
            'next_of_kin.*.name' => ['nullable', 'string', 'max:190'],
            'next_of_kin.*.relationship_id' => ['nullable', 'integer', 'min:0'],
            'next_of_kin.*.phone' => ['nullable', 'string', 'max:50'],
            'next_of_kin.*.email' => ['nullable', 'email', 'max:190'],
        ]);

        if (! $profiles->updateBiodata($staff, $data)) {
            return response()->json(['message' => 'Could not update biodata.'], 500);
        }

        PortalReadCache::bust('staff');

        $row = $profiles->find($staff);
        $staffPayload = (array) $row;
        $this->enrichStaffMedia($staffPayload, $profiles);
        $dob = trim((string) ($staffPayload['date_of_birth'] ?? ''));
        $staffPayload['age'] = $this->ageFromDob($dob);
        $nok = json_decode((string) ($staffPayload['next_of_kin_json'] ?? '[]'), true);
        $staffPayload['next_of_kin'] = $this->presentNextOfKin(is_array($nok) ? $nok : []);

        return response()->json([
            'data' => ['staff' => $staffPayload],
            'message' => 'Biodata updated successfully.',
        ]);
    }

    public function uploadPassport(int $staff, Request $request, StaffProfileService $profiles): JsonResponse
    {
        if (! StaffAccess::canManageStaff()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (! $profiles->find($staff)) {
            return response()->json(['message' => 'Staff not found.'], 404);
        }

        $request->validate([
            'passport' => ['required', 'file', 'max:4096', 'mimes:jpg,jpeg,png,gif,webp,pdf'],
        ]);

        $media = $profiles->storePassport($staff, $request->file('passport'));
        PortalReadCache::bust('staff');

        return response()->json([
            'data' => $media,
            'message' => 'Passport biodata updated.',
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
            $contractId = $contracts->create(
                $staff,
                $this->validatedContractPayload($request),
                $this->optionalContractPdf($request)
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        }

        PortalReadCache::bust('staff');

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
            $updated = $contracts->update(
                $contractId,
                $staff,
                $this->validatedContractPayload($request),
                $this->optionalContractPdf($request)
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        }

        if (! $updated) {
            return response()->json(['message' => 'Contract not found.'], 404);
        }

        PortalReadCache::bust('staff');

        return response()->json([
            'data' => [
                'contract_id' => $contractId,
            ],
            'message' => 'Contract updated successfully.',
        ]);
    }

    public function birthdays(Request $request): JsonResponse
    {
        if (! StaffAccess::canViewDirectory() && ! \Modules\Core\Support\PortalPermission::can(41)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $range = strtolower(trim((string) $request->query('range', 'today')));
        if (! in_array($range, ['today', 'tomorrow', 'next_7', 'next_30'], true)) {
            $range = 'today';
        }

        $today = new \DateTimeImmutable('today');
        $from = $today;
        $to = match ($range) {
            'tomorrow' => $today->modify('+1 day'),
            'next_7' => $today->modify('+7 days'),
            'next_30' => $today->modify('+30 days'),
            default => $today,
        };
        $fromMmdd = $from->format('m-d');
        $toMmdd = $to->format('m-d');
        // MySQL DATE_FORMAT; tolerate legacy YYYY-MM-DD / datetime DOB values.
        $dobMmdd = "DATE_FORMAT(s.date_of_birth, '%m-%d')";

        $latestContractSub = DB::table('staff_contracts')
            ->selectRaw('staff_id, MAX(staff_contract_id) as cid')
            ->groupBy('staff_id');

        $q = DB::table('staff as s')
            ->joinSub($latestContractSub, 'lc', 'lc.staff_id', '=', 's.staff_id')
            ->join('staff_contracts as sc', 'sc.staff_contract_id', '=', 'lc.cid')
            ->leftJoin('divisions as d', 'd.division_id', '=', 'sc.division_id')
            ->leftJoin('jobs as j', 'j.job_id', '=', 'sc.job_id')
            ->leftJoin('grades as g', 'g.grade_id', '=', 'sc.grade_id')
            ->whereIn('sc.status_id', [1, 2, 7])
            ->whereNotNull('s.date_of_birth')
            ->where('s.date_of_birth', '!=', '0000-00-00')
            ->where('s.date_of_birth', 'not like', '0000-00-00%');

        if ($range === 'today' || $range === 'tomorrow') {
            $q->whereRaw("{$dobMmdd} = ?", [$range === 'today' ? $fromMmdd : $toMmdd]);
        } elseif ($fromMmdd <= $toMmdd) {
            $q->whereRaw("{$dobMmdd} BETWEEN ? AND ?", [$fromMmdd, $toMmdd]);
        } else {
            // Year wrap (e.g. Dec 20 → Jan 19).
            $q->where(function ($w) use ($dobMmdd, $fromMmdd, $toMmdd): void {
                $w->whereRaw("{$dobMmdd} >= ?", [$fromMmdd])
                    ->orWhereRaw("{$dobMmdd} <= ?", [$toMmdd]);
            });
        }

        $rows = $q
            ->orderByRaw($dobMmdd)
            ->orderBy('s.lname')
            ->orderBy('s.fname')
            ->select(
                's.staff_id',
                's.title',
                's.fname',
                's.lname',
                's.oname',
                's.date_of_birth',
                's.gender',
                's.work_email',
                's.photo',
                'd.division_name',
                'j.job_name',
                'g.grade',
            )
            ->limit(500)
            ->get()
            ->map(function ($row) use ($today) {
                $dob = (string) ($row->date_of_birth ?? '');
                $age = null;
                $nextBirthday = null;
                try {
                    $dobObj = new \DateTimeImmutable($dob);
                    $next = $dobObj->setDate(
                        (int) $today->format('Y'),
                        (int) $dobObj->format('m'),
                        (int) $dobObj->format('d'),
                    );
                    if ($next < $today) {
                        $next = $next->modify('+1 year');
                    }
                    $nextBirthday = $next->format('Y-m-d');
                    // Age they turn / turned on that birthday date.
                    $age = (int) $next->format('Y') - (int) $dobObj->format('Y');
                } catch (\Throwable) {
                    // leave age null
                }

                $arr = (array) $row;
                $arr['age'] = $age;
                $arr['next_birthday'] = $nextBirthday;

                return $arr;
            })
            ->values()
            ->all();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'range' => $range,
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'total' => count($rows),
            ],
        ]);
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
            $q->whereNull('SAPNO')->orWhere('SAPNO', '');
        })->count();

        $sample = DB::table('staff as s')
            ->where(function ($q): void {
                $q->whereNull('s.work_email')->orWhere('s.work_email', '')
                    ->orWhereNull('s.date_of_birth')
                    ->orWhereNull('s.SAPNO')->orWhere('s.SAPNO', '');
            })
            ->orderBy('s.lname')
            ->limit(50)
            ->get([
                's.staff_id',
                's.fname',
                's.lname',
                's.work_email',
                's.date_of_birth',
                DB::raw('s.SAPNO as sap_number'),
            ]);

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
        $filters = $directory->normalizeFilters($this->staffFilterInput($request));
        $exported = $directory->exportRows($search, $statusId, $category, 5000, $filters);
        $selectedColumns = $this->selectedExportColumns($request);
        $definitions = $this->csvColumnDefinitions();
        $rows = [[
            '#',
            ...array_map(
                static fn (string $column): string => $definitions[$column]['label'],
                $selectedColumns
            ),
        ]];

        $n = 0;
        foreach ($exported as $item) {
            $r = (array) $item;
            $n++;
            $row = [$n];

            foreach ($selectedColumns as $column) {
                $row[] = $this->csvColumnValue($column, $r);
            }

            $rows[] = $row;
        }

        return $csv->stream('staff-directory.csv', $rows);
    }

    public function exportPdf(Request $request, StaffDirectoryService $directory, PdfService $pdf): Response
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
        $filters = $directory->normalizeFilters($this->staffFilterInput($request));
        $exported = $directory->exportRows($search, $statusId, $category, 2000, $filters);
        $selectedColumns = $this->selectedExportColumns($request);
        $definitions = $this->csvColumnDefinitions();
        $colCount = count($selectedColumns) + 1;
        $fontSize = $colCount > 18 ? '7px' : ($colCount > 12 ? '8px' : '9px');

        $headerCells = '<th>#</th>';
        foreach ($selectedColumns as $column) {
            $headerCells .= '<th>'.e($definitions[$column]['label']).'</th>';
        }

        $rowsHtml = '';
        $n = 0;
        foreach ($exported as $item) {
            $r = (array) $item;
            $n++;
            $rowsHtml .= '<tr><td>'.$n.'</td>';
            foreach ($selectedColumns as $column) {
                $rowsHtml .= '<td>'.e($this->csvColumnValue($column, $r)).'</td>';
            }
            $rowsHtml .= '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="'.$colCount.'" align="center">No staff found for the selected filters.</td></tr>';
        }

        $html = '<h2 style="margin:0 0 8px;color:#2c3e50;">Staff directory</h2>
            <p style="margin:0 0 12px;color:#768B9E;font-size:11px;">Preset: '.e($preset)
            .' · Category: '.e($this->humanizeCategory($category))
            .' · '.$n.' record(s)</p>
            <table width="100%" cellpadding="3" cellspacing="0" border="1" style="border-collapse:collapse;font-size:'.$fontSize.';">
              <thead>
                <tr style="background:#f8fafc;">'.$headerCells.'</tr>
              </thead>
              <tbody>'.$rowsHtml.'</tbody>
            </table>';

        return $pdf->inline($html, 'staff-directory.pdf', [
            'title' => 'Staff Directory',
            'document_url' => url('/staff/staff-portal/staff'),
            'landscape' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function staffFilterInput(Request $request): array
    {
        return [
            'name' => $request->query('name', $request->query('lname')),
            'sapno' => $request->query('sapno', $request->query('SAPNO')),
            'gender' => $request->query('gender'),
            'region_id' => $request->query('region_id'),
            'nationality_id' => $request->query('nationality_id'),
            'division_id' => $request->query('division_id'),
            'duty_station_id' => $request->query('duty_station_id'),
            'funder_id' => $request->query('funder_id'),
            'job_id' => $request->query('job_id'),
            'grade_id' => $request->query('grade_id'),
        ];
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
        $defaults = [
            'sap_number',
            'title',
            'photo',
            'name',
            'gender',
            'date_of_birth',
            'age',
            'nationality',
            'region',
            'duty_station',
            'division',
            'grade',
            'job',
            'initiation_date',
            'start_date',
            'end_date',
            'years_of_tenure',
            'job_acting',
            'first_supervisor',
            'second_supervisor',
            'funder',
            'work_email',
            'telephone',
            'whatsapp',
        ];
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
            'sap_number' => ['label' => 'SAPNO'],
            'title' => ['label' => 'Title'],
            'photo' => ['label' => 'Passport Photo'],
            'name' => ['label' => 'Name'],
            'gender' => ['label' => 'Gender'],
            'date_of_birth' => ['label' => 'Date of Birth'],
            'age' => ['label' => 'Age'],
            'nationality' => ['label' => 'Nationality'],
            'region' => ['label' => 'Region'],
            'duty_station' => ['label' => 'Duty Station'],
            'division' => ['label' => 'Division'],
            'grade' => ['label' => 'Grade'],
            'job' => ['label' => 'Job'],
            'initiation_date' => ['label' => 'Initiation Date'],
            'start_date' => ['label' => 'Current Contract Start Date'],
            'end_date' => ['label' => 'Current Contract End Date'],
            'years_of_tenure' => ['label' => 'Years of Tenure'],
            'job_acting' => ['label' => 'Acting Job'],
            'first_supervisor' => ['label' => 'First Supervisor'],
            'second_supervisor' => ['label' => 'Second Supervisor'],
            'funder' => ['label' => 'Funder'],
            'work_email' => ['label' => 'Email'],
            'telephone' => ['label' => 'Telephone'],
            'whatsapp' => ['label' => 'WhatsApp'],
            'contract_type' => ['label' => 'Contract type'],
            'category' => ['label' => 'Category'],
            'status' => ['label' => 'Status'],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function csvColumnValue(string $column, array $row): string
    {
        return match ($column) {
            'sap_number' => (string) ($row['SAPNO'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'photo' => (string) ($row['photo'] ?? ''),
            'name' => $this->csvPersonName($row),
            'gender' => (string) ($row['gender'] ?? ''),
            'date_of_birth' => (string) ($row['date_of_birth'] ?? ''),
            'age' => $this->yearsFromDate($row['date_of_birth'] ?? null),
            'nationality' => (string) ($row['nationality'] ?? ''),
            'region' => (string) ($row['region_name'] ?? ''),
            'duty_station' => (string) ($row['duty_station_name'] ?? ''),
            'division' => (string) ($row['division_name'] ?? ''),
            'grade' => (string) ($row['grade'] ?? ''),
            'job' => (string) ($row['job_name'] ?? ''),
            'initiation_date' => (string) ($row['initiation_date'] ?? ''),
            'start_date' => (string) ($row['start_date'] ?? ''),
            'end_date' => (string) ($row['end_date'] ?? ''),
            'years_of_tenure' => $this->yearsFromDate($row['initiation_date'] ?? null),
            'job_acting' => (string) ($row['job_acting'] ?? ''),
            'first_supervisor' => trim((string) ($row['first_supervisor_name'] ?? '')),
            'second_supervisor' => trim((string) ($row['second_supervisor_name'] ?? '')),
            'funder' => (string) ($row['funder'] ?? ''),
            'work_email' => (string) ($row['work_email'] ?? ''),
            'telephone' => trim(trim((string) ($row['tel_1'] ?? '')).' '.trim((string) ($row['tel_2'] ?? ''))),
            'whatsapp' => (string) ($row['whatsapp'] ?? ''),
            'contract_type' => (string) ($row['contract_type'] ?? ''),
            'category' => $this->humanizeCategory((string) ($row['category'] ?? '')),
            'status' => (string) ($row['contract_status'] ?? ''),
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function csvPersonName(array $row): string
    {
        $parts = array_filter([
            trim((string) ($row['lname'] ?? '')),
            trim((string) ($row['fname'] ?? '')),
            trim((string) ($row['oname'] ?? '')),
        ], static fn (string $part): bool => $part !== '');

        return implode(' ', $parts);
    }

    protected function yearsFromDate(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        try {
            $start = new \DateTimeImmutable(substr($raw, 0, 10));
            $today = new \DateTimeImmutable('today');

            return (string) $start->diff($today)->y;
        } catch (\Throwable) {
            return '';
        }
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
            'next_of_kin' => ['nullable', 'array', 'max:2'],
            'next_of_kin.*.name' => ['nullable', 'string', 'max:190'],
            'next_of_kin.*.relationship_id' => ['nullable', 'integer', 'min:0'],
            'next_of_kin.*.phone' => ['nullable', 'string', 'max:50'],
            'next_of_kin.*.email' => ['nullable', 'email', 'max:190'],
            'pay' => ['nullable', 'array'],
            'pay.currency' => ['nullable', 'string', 'size:3'],
            'pay.basic_salary' => ['required_with:pay', 'numeric', 'min:0'],
            'pay.bank_name' => ['nullable', 'string', 'max:120'],
            'pay.bank_account' => ['nullable', 'string', 'max:80'],
            'pay.bank_branch' => ['nullable', 'string', 'max:120'],
            'pay.tax_identifier' => ['nullable', 'string', 'max:80'],
            'pay.pay_status' => ['nullable', 'in:active,held,terminated'],
            'pay.notes' => ['nullable', 'string'],
            'pay.wage_items' => ['nullable', 'array'],
            'pay.wage_items.*.wage_type_id' => ['required', 'integer'],
            'pay.wage_items.*.amount' => ['nullable', 'numeric'],
            'pay.wage_items.*.percent' => ['nullable', 'numeric'],
        ]);

        $validated['other_associated_divisions'] = array_values(
            array_map('intval', (array) ($validated['other_associated_divisions'] ?? []))
        );

        if (array_key_exists('next_of_kin', $validated)) {
            app(StaffProfileService::class)->assertOptionalNextOfKin((array) ($validated['next_of_kin'] ?? []));
        }

        if (array_key_exists('pay', $validated) && is_array($validated['pay'])) {
            $pay = $validated['pay'];
            $hasMeaningful = array_key_exists('basic_salary', $pay)
                && $pay['basic_salary'] !== null
                && $pay['basic_salary'] !== '';
            if (! $hasMeaningful) {
                unset($validated['pay']);
            }
        }

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

    protected function optionalContractPdf(Request $request): ?UploadedFile
    {
        $request->validate([
            'contract_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $file = $request->file('contract_file');

        return $file instanceof UploadedFile ? $file : null;
    }

    protected function optionalPassportFile(Request $request): ?UploadedFile
    {
        $request->validate([
            'passport' => ['nullable', 'file', 'max:4096', 'mimes:jpg,jpeg,png,gif,webp,pdf'],
        ]);

        $file = $request->file('passport');

        return $file instanceof UploadedFile ? $file : null;
    }

    /**
     * @param  array<string, mixed>  $staffPayload
     */
    protected function enrichStaffMedia(array &$staffPayload, StaffProfileService $profiles): void
    {
        $photo = trim((string) ($staffPayload['photo'] ?? ''));
        $staffPayload['photo_url'] = StaffPhoto::url($photo !== '' ? $photo : null);
        $passport = trim((string) ($staffPayload['passport_biodata_page'] ?? ''));
        $staffPayload['passport_url'] = $profiles->passportUrl($passport !== '' ? $passport : null);
        $staffPayload['passport_is_pdf'] = $passport !== ''
            && strtolower(pathinfo($passport, PATHINFO_EXTENSION)) === 'pdf';
    }

    protected function ageFromDob(string $dob): ?int
    {
        if ($dob === '' || strtotime($dob) === false) {
            return null;
        }
        try {
            $born = new \DateTimeImmutable($dob);
            $today = new \DateTimeImmutable('today');

            return (int) $born->diff($today)->y;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  list<mixed>  $rows
     * @return list<array<string, mixed>>
     */
    protected function presentNextOfKin(array $rows): array
    {
        $names = [];
        if (DB::getSchemaBuilder()->hasTable('kin_relationship_types')) {
            $names = DB::table('kin_relationship_types')
                ->pluck('relationship_name', 'kin_relationship_id')
                ->map(fn ($n) => (string) $n)
                ->all();
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $relId = (int) ($row['relationship_id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            $phone = trim((string) ($row['phone'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            if ($name === '' && $phone === '' && $email === '' && $relId < 1) {
                continue;
            }
            $out[] = [
                'name' => $name,
                'relationship_id' => $relId,
                'relationship_name' => $relId > 0 ? ($names[$relId] ?? '') : '',
                'phone' => $phone,
                'email' => $email,
            ];
        }

        return $out;
    }

    protected function validationErrorResponse(ValidationException $e): JsonResponse
    {
        return response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $e->errors(),
        ], 422);
    }
}
