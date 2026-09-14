<?php

namespace App\Http\Controllers;

use App\Exports\DivisionsExport;
use App\Models\Division;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DivisionController extends Controller
{
    /**
     * Display a listing of the divisions (view with AJAX table).
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        return view('divisions.index', [
            'pageConfig' => [
                'routes' => [
                    'ajax' => route('divisions.ajax'),
                    'show' => url('divisions'),
                    'exportExcel' => route('divisions.export.excel'),
                ],
                'flash' => [
                    'success' => session('success'),
                    'error' => session('error'),
                ],
            ],
        ]);
    }

    /**
     * Get divisions data for AJAX (server-side table with search, sort, pagination).
     */
    public function getDivisionsAjax(Request $request)
    {
        $search = trim((string) ($request->get('search') ?? ''));
        $page = (int) $request->get('page', 1);
        $pageSize = (int) $request->get('pageSize', 15);
        $pageSize = max(5, min(100, $pageSize));
        $sortBy = $request->get('sort_by', 'division_name');
        $sortDirection = $request->get('sort_direction', 'asc');

        $allowedSortColumns = ['id', 'division_name', 'division_short_name', 'category', 'created_at'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'division_name';
        }
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'asc';
        }

        $query = Division::with(['divisionHead', 'focalPerson', 'adminAssistant', 'financeOfficer']);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('division_name', 'like', '%' . $search . '%')
                  ->orWhere('division_short_name', 'like', '%' . $search . '%')
                  ->orWhere('category', 'like', '%' . $search . '%');
            });
        }

        $query->orderBy($sortBy, $sortDirection);
        $recordsTotal = $query->count();
        $totalPages = $pageSize > 0 ? (int) ceil($recordsTotal / $pageSize) : 0;
        $skip = ($page - 1) * $pageSize;
        $data = $query->skip($skip)->take($pageSize)->get();

        $totalDivisions = Division::count();

        return response()->json([
            'data' => $data,
            'recordsTotal' => $recordsTotal,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'summary' => [
                'total_divisions' => $totalDivisions,
                'filtered_divisions' => $recordsTotal,
            ],
        ]);
    }

    /**
     * Export divisions to Excel (respects current search and sort).
     */
    public function exportExcel(Request $request): BinaryFileResponse
    {
        $query = Division::with(['divisionHead', 'focalPerson', 'adminAssistant', 'financeOfficer']);

        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('division_name', 'like', "%{$search}%")
                  ->orWhere('division_short_name', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $sortBy = $request->get('sort_by', 'division_name');
        $sortDirection = $request->get('sort_direction', 'asc');
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'asc';
        }
        $allowedSortColumns = ['id', 'division_name', 'division_short_name', 'category', 'created_at'];
        if (in_array($sortBy, $allowedSortColumns)) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('division_name', 'asc');
        }

        $divisions = $query->get();
        $filename = 'divisions_' . date('Y-m-d_H-i-s') . '.xlsx';

        return Excel::download(new DivisionsExport($divisions), $filename);
    }

    /**
     * Display the specified division.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        $division = Division::with([
            'divisionHead',
            'focalPerson',
            'adminAssistant',
            'financeOfficer',
            'directorate.director',
        ])->findOrFail($id);

        $directorateDirector = $division->directorate?->director;

        return view('divisions.show', [
            'pageConfig' => $this->buildDivisionShowPageConfig($division, $directorateDirector),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildDivisionShowPageConfig(Division $division, $directorateDirector): array
    {
        $mapStaff = static function ($staff): ?array {
            if (! $staff) {
                return null;
            }

            return [
                'staff_id' => (int) ($staff->staff_id ?? 0),
                'name' => trim(($staff->fname ?? '').' '.($staff->lname ?? '')),
                'title' => $staff->title ?? $staff->job_name ?? 'Staff',
            ];
        };

        $formatDate = static function ($value): ?string {
            if (empty($value)) {
                return null;
            }

            try {
                return \Carbon\Carbon::parse($value)->format('M d, Y');
            } catch (\Throwable) {
                return null;
            }
        };

        return [
            'division' => [
                'id' => (int) $division->id,
                'division_name' => $division->division_name,
                'division_short_name' => $division->division_short_name,
                'category' => $division->category,
                'is_active' => $division->is_active,
                'directorate' => $division->directorate ? [
                    'id' => (int) $division->directorate->id,
                    'name' => $division->directorate->name,
                ] : null,
                'director' => $mapStaff($directorateDirector),
                'head_oic' => [
                    'staff_id' => $division->head_oic_id,
                    'start' => $formatDate($division->head_oic_start_date),
                    'end' => $formatDate($division->head_oic_end_date),
                ],
                'director_oic' => [
                    'staff_id' => $division->director_oic_id,
                    'start' => $formatDate($division->director_oic_start_date),
                    'end' => $formatDate($division->director_oic_end_date),
                ],
                'staff_roles' => [
                    ['key' => 'division_head', 'label' => 'Division Head', 'icon' => 'mdi-account-tie', 'color' => 'primary', 'staff' => $mapStaff($division->divisionHead)],
                    ['key' => 'focal_person', 'label' => 'Focal Person', 'icon' => 'mdi-account-voice', 'color' => 'info', 'staff' => $mapStaff($division->focalPerson)],
                    ['key' => 'admin_assistant', 'label' => 'Admin Assistant', 'icon' => 'mdi-headset', 'color' => 'success', 'staff' => $mapStaff($division->adminAssistant)],
                    ['key' => 'finance_officer', 'label' => 'Finance Officer', 'icon' => 'mdi-currency-usd', 'color' => 'warning', 'staff' => $mapStaff($division->financeOfficer)],
                ],
            ],
            'routes' => [
                'index' => route('divisions.index'),
                'directoratesShow' => url('directorates'),
            ],
            'flash' => [
                'success' => session('success'),
            ],
        ];
    }

}