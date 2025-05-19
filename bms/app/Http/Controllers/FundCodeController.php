<?php

namespace App\Http\Controllers;

use App\Models\FundCode;
use App\Models\FundType;
use App\Models\Division;
use Illuminate\Http\Request;

class FundCodeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = FundCode::query()->with(['fundType', 'division']);
        
        // Apply search filter if provided
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        // Apply fund type filter if provided
        if ($request->has('fund_type_id') && !empty($request->fund_type_id)) {
            $query->where('fund_type_id', $request->fund_type_id);
        }
        
        // Apply division filter if provided
        if ($request->has('division_id') && !empty($request->division_id)) {
            $query->where('division_id', $request->division_id);
        }
        
        // Apply status filter if provided
        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }
        
        $fundCodes = $query->orderBy('code')->paginate(10);
        $fundTypes = FundType::orderBy('name')->get();
        $divisions = Division::orderBy('division_name')->get();
        
        return view('fund-codes.index', compact('fundCodes', 'fundTypes', 'divisions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $fundTypes = FundType::orderBy('name')->get();
        $divisions = Division::where('is_active', true)->orderBy('division_name')->get();
        $selectedFundType = $request->input('fund_type_id');
        
        return view('fund-codes.create', compact('fundTypes', 'divisions', 'selectedFundType'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:fund_codes',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'fund_type_id' => 'required|exists:fund_types,id',
            'division_id' => 'required|exists:divisions,id',
            'is_active' => 'boolean',
        ]);

        // Set is_active to true by default if not provided
        if (!isset($validated['is_active'])) {
            $validated['is_active'] = true;
        }

        $fundCode = FundCode::create($validated);

        return redirect()->route('fund-codes.show', $fundCode)
            ->with('success', 'Fund Code created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(FundCode $fundCode)
    {
        $fundCode->load(['fundType', 'division']);
        return view('fund-codes.show', compact('fundCode'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FundCode $fundCode)
    {
        $fundTypes = FundType::orderBy('name')->get();
        $divisions = Division::orderBy('division_name')->get();
        
        return view('fund-codes.edit', compact('fundCode', 'fundTypes', 'divisions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, FundCode $fundCode)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:fund_codes,code,' . $fundCode->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'fund_type_id' => 'required|exists:fund_types,id',
            'division_id' => 'required|exists:divisions,id',
            'is_active' => 'boolean',
        ]);

        // Handle checkbox for is_active
        $validated['is_active'] = $request->has('is_active');

        $fundCode->update($validated);

        return redirect()->route('fund-codes.show', $fundCode)
            ->with('success', 'Fund Code updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FundCode $fundCode)
    {
        // Here you could add checks for dependencies before deleting
        // For example, if fund codes are used in other entities
        
        $fundCode->delete();

        return redirect()->route('fund-codes.index')
            ->with('success', 'Fund Code deleted successfully.');
    }
}
