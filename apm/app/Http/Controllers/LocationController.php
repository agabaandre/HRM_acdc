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
        $locations = Location::latest()->paginate(10);
        return view('locations.index', compact('locations'));
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
            return redirect()->route('locations.index')
                ->with('msg', 'Location updated successfully.')
                ->with('type', 'success');
        } catch (\Exception $e) {
            return back()->withInput()
                ->with('msg', 'Error updating location: ' . $e->getMessage())
                ->with('type', 'error');
        }
    }

}
