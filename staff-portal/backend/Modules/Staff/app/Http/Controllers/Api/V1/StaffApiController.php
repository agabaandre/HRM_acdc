<?php

namespace Modules\Staff\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Staff\Services\StaffDirectoryService;
use Modules\Staff\Services\StaffProfileService;
use Modules\Staff\Support\StaffAccess;

class StaffApiController extends Controller
{
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
        $rows = [['Staff ID', 'First name', 'Last name', 'Email', 'Division', 'Job', 'Status', 'Category']];
        foreach ($exported as $item) {
            $r = (array) $item;
            $rows[] = [
                $r['staff_id'] ?? '',
                $r['fname'] ?? '',
                $r['lname'] ?? '',
                $r['work_email'] ?? '',
                $r['division_name'] ?? '',
                $r['job_name'] ?? ($r['job_acting'] ?? ''),
                $r['contract_status'] ?? '',
                $this->humanizeCategory((string) ($r['category'] ?? '')),
            ];
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
}
