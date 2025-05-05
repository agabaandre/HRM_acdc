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
    public function index()
    {
        $divisions = Division::all();
        return view('divisions.index', compact('divisions'));
    }

    /**
     * Show the form for creating a new division.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('divisions.create');
    }

    /**
     * Store a newly created division in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'division_name' => 'required|string|max:150',
            'division_head' => 'required|integer',
            'focal_person' => 'required|integer',
            'admin_assistant' => 'required|integer',
            'finance_officer' => 'required|integer',
        ]);

        Division::create($validated);

        return redirect()->route('divisions.index')
            ->with('success', 'Division created successfully.');
    }

    /**
     * Display the specified division.
     *
     * @param  \App\Models\Division  $division
     * @return \Illuminate\View\View
     */
    public function show(Division $division)
    {
        return view('divisions.show', compact('division'));
    }

    /**
     * Show the form for editing the specified division.
     *
     * @param  \App\Models\Division  $division
     * @return \Illuminate\View\View
     */
    public function edit(Division $division)
    {
        return view('divisions.edit', compact('division'));
    }

    /**
     * Update the specified division in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Division  $division
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Division $division)
    {
        $validated = $request->validate([
            'division_name' => 'required|string|max:150',
            'division_head' => 'required|integer',
            'focal_person' => 'required|integer',
            'admin_assistant' => 'required|integer',
            'finance_officer' => 'required|integer',
        ]);

        $division->update($validated);

        return redirect()->route('divisions.index')
            ->with('success', 'Division updated successfully.');
    }

    /**
     * Remove the specified division from storage.
     *
     * @param  \App\Models\Division  $division
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Division $division)
    {
        $division->delete();

        return redirect()->route('divisions.index')
            ->with('success', 'Division deleted successfully.');
    }
}