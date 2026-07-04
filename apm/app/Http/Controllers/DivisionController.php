<?php

namespace App\Http\Controllers;

use App\Exports\DivisionsExport;
use App\Models\Division;
use App\Models\Staff;
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

        return view('divisions.show', [
            'pageConfig' => $this->buildDivisionShowPageConfig($division),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDivisionShowPageConfig(Division $division): array
    {
        $formatDate = static fn ($value) => $value
            ? \Carbon\Carbon::parse($value)->format('M d, Y')
            : null;

        $staffPayload = static function (?Staff $staff): ?array {
            if (! $staff) {
                return null;
            }

            return [
                'staff_id' => $staff->staff_id,
                'name' => trim(collect([$staff->fname, $staff->lname])->filter()->implode(' ')),
                'meta' => $staff->title ?? $staff->job_name ?? 'Staff',
                'show_url' => route('staff.show', $staff->staff_id),
            ];
        };

        return [
            'division' => [
                'id' => $division->id,
                'division_name' => $division->division_name,
                'division_short_name' => $division->division_short_name ?? null,
                'category' => $division->category ?? null,
                'is_active' => $division->is_active ?? null,
                'directorate_id' => $division->directorate_id,
                'director_id' => $division->director_id,
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
            ],
            'directorate' => $division->directorate ? [
                'id' => $division->directorate->id,
                'name' => $division->directorate->name,
                'show_url' => route('directorates.show', $division->directorate->id),
            ] : null,
            'director' => $staffPayload($division->directorate?->director),
            'staffRoles' => [
                ['key' => 'divisionHead', 'label' => 'Division Head', 'icon' => 'mdi-account-tie', 'color' => 'primary', 'staff' => $staffPayload($division->divisionHead)],
                ['key' => 'focalPerson', 'label' => 'Focal Person', 'icon' => 'mdi-account-voice', 'color' => 'info', 'staff' => $staffPayload($division->focalPerson)],
                ['key' => 'adminAssistant', 'label' => 'Admin Assistant', 'icon' => 'mdi-headset', 'color' => 'success', 'staff' => $staffPayload($division->adminAssistant)],
                ['key' => 'financeOfficer', 'label' => 'Finance Officer', 'icon' => 'mdi-currency-usd', 'color' => 'warning', 'staff' => $staffPayload($division->financeOfficer)],
            ],
            'routes' => [
                'index' => route('divisions.index'),
            ],
            'flash' => [
                'success' => session('success'),
            ],
        ];
    }

}