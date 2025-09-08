<?php

namespace App\Http\Controllers;

use App\Models\Division;
use Illuminate\Http\Request;

class DivisionController extends Controller
{
    /**
     * Display a listing of the divisions.
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $query = Division::with(['divisionHead', 'focalPerson', 'adminAssistant', 'financeOfficer']);

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('division_name', 'like', "%{$search}%")
                  ->orWhere('division_short_name', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'division_name');
        $sortDirection = $request->get('sort_direction', 'asc');
        
        // Validate sort direction
        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'asc';
        }
        
        // Validate sort column
        $allowedSortColumns = ['id', 'division_name', 'division_short_name', 'category', 'created_at'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'division_name';
        }
        
        $query->orderBy($sortBy, $sortDirection);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $perPage = min(max($perPage, 5), 100); // Limit between 5 and 100
        
        $divisions = $query->paginate($perPage)->withQueryString();

        return view('divisions.index', compact('divisions'));
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