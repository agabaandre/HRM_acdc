<?php

namespace App\Http\Controllers;

use App\Models\FundType;
use Illuminate\Http\Request;

class FundTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $fundTypes = FundType::orderBy('name')->paginate(10);
        return view('fund-types.index', compact('fundTypes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('fund-types.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:fund_types',
        ]);

        FundType::create($validated);

        return redirect()->route('fund-types.index')
            ->with('success', 'Fund Type created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(FundType $fundType)
    {
        return view('fund-types.show', compact('fundType'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FundType $fundType)
    {
        return view('fund-types.edit', compact('fundType'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, FundType $fundType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:fund_types,name,' . $fundType->id,
        ]);

        $fundType->update($validated);

        return redirect()->route('fund-types.index')
            ->with('success', 'Fund Type updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FundType $fundType)
    {
        // Check if the fund type has any fund codes before deleting
        if ($fundType->fundCodes()->count() > 0) {
            return redirect()->route('fund-types.index')
                ->with('error', 'Cannot delete Fund Type. It has associated Fund Codes.');
        }

        $fundType->delete();

        return redirect()->route('fund-types.index')
            ->with('success', 'Fund Type deleted successfully.');
    }
}
