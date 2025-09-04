<?php

namespace App\Http\Controllers;

use App\Models\Funder;
use Illuminate\Http\Request;

class FunderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Funder::query();
        
        // Apply search filter if provided
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('contact_person', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        // Apply status filter if provided
        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }
        
        // Apply year filter if provided
        if ($request->has('year') && !empty($request->year)) {
            $year = $request->year;
            $query->whereHas('fundCodes', function($q) use ($year) {
                $q->where('year', $year);
            });
        }
        
        $funders = $query->orderBy('name')->paginate(10);
        
        // Get available years for filter dropdown
        $availableYears = \App\Models\FundCode::select('year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');
        
        return view('funders.index', compact('funders', 'availableYears'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('funders.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:funders',
            'description' => 'nullable|string',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'website' => 'nullable|url|max:255',
            'is_active' => 'boolean',
        ]);

        // Set is_active to true by default if not provided
        if (!isset($validated['is_active'])) {
            $validated['is_active'] = true;
        }

        $funder = Funder::create($validated);

        return redirect()->route('funders.index')
            ->with('msg', 'Funder created successfully.')
            ->with('type', 'success');
    }

    /**
     * Display the specified resource.
     */
    public function show(Funder $funder)
    {
        $funder->load('fundCodes');
        return view('funders.show', compact('funder'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Funder $funder)
    {
        return view('funders.edit', compact('funder'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Funder $funder)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:funders,name,' . $funder->id,
            'description' => 'nullable|string',
            'contact_person' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'website' => 'nullable|url|max:255',
            'is_active' => 'boolean',
        ]);

        // Handle checkbox for is_active
        $validated['is_active'] = $request->has('is_active');

        $funder->update($validated);

        return redirect()->route('funders.index')
            ->with('msg', 'Funder updated successfully.')
            ->with('type', 'success');
    }

}
