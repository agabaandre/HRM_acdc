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
        $initialSearch = $request->get('search', '');
        $initialPage = (int) $request->get('page', 1);
        $initialPageSize = (int) $request->get('per_page', 15);
        $initialSortBy = $request->get('sort_by', 'division_name');
        $initialSortDirection = $request->get('sort_direction', 'asc');

        return view('divisions.index', [
            'initialSearch' => $initialSearch,
            'initialPage' => $initialPage,
            'initialPageSize' => min(max($initialPageSize, 5), 100),
            'initialSortBy' => $initialSortBy,
            'initialSortDirection' => $initialSortDirection,
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

        return response()->json([
            'data' => $data,
            'recordsTotal' => $recordsTotal,
            'totalPages' => $totalPages,
            'currentPage' => $page,
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
        $division = Division::findOrFail($id);
        return view('divisions.show', compact('division'));
    }

}