<?php

namespace App\Http\Controllers;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('locations.index');
    }

    /**
     * Get locations data for AJAX (server-side table with search and pagination).
     */
    public function getLocationsAjax(Request $request)
    {
        $search = $request->get('search', '');
        $page = (int) $request->get('page', 1);
        $pageSize = (int) $request->get('pageSize', 25);
        $pageSize = max(1, min(100, $pageSize));
        $skip = ($page - 1) * $pageSize;

        $query = Location::query()->orderBy('name');

        if ($search !== '') {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $recordsTotal = $query->count();
        $totalPages = $pageSize > 0 ? (int) ceil($recordsTotal / $pageSize) : 0;
        $data = $query->skip($skip)->take($pageSize)->get();

        $summary = [
            'total_locations' => Location::count(),
            'filtered_locations' => $recordsTotal,
        ];

        return response()->json([
            'data' => $data,
            'recordsTotal' => $recordsTotal,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'summary' => $summary,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('locations.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            Location::create($validated);
            return redirect()->route('locations.index')
                ->with('msg', 'Location created successfully.')
                ->with('type', 'success');
        } catch (\Exception $e) {
            return back()->withInput()
                ->with('msg', 'Error creating location: ' . $e->getMessage())
                ->with('type', 'error');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Location $location)
    {
        return view('locations.show', compact('location'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Location $location)
    {
        return view('locations.edit', compact('location'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Location $location)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            $location->update($validated);
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'msg' => 'Location updated successfully.',
                ]);
            }
            return redirect()->route('locations.index')
                ->with('msg', 'Location updated successfully.')
                ->with('type', 'success');
        } catch (\Exception $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'msg' => 'Error updating location: ' . $e->getMessage(),
                ], 422);
            }
            return back()->withInput()
                ->with('msg', 'Error updating location: ' . $e->getMessage())
                ->with('type', 'error');
        }
    }

}
