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
            ->with('msg', 'Fund Type created successfully.')
            ->with('type', 'success');
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
            ->with('msg', 'Fund Type updated successfully.')
            ->with('type', 'success');
    }

}
