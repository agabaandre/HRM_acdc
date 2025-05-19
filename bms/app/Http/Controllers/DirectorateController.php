<?php

namespace App\Http\Controllers;

use App\Models\Directorate;
use App\Models\Division;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DirectorateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Directorate::query();

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->has('status') && !empty($request->status)) {
            $status = $request->status === 'active' ? 1 : 0;
            $query->where('is_active', $status);
        }

        $directorates = $query->latest()->paginate(10);

        return view('directorates.index', compact('directorates'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('directorates.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:directorates,name',
            'code' => 'required|string|max:50|unique:directorates,code',
            'description' => 'nullable|string',
            'is_active' => 'nullable'
        ]);

        $directorate = new Directorate();
        $directorate->name = $request->name;
        $directorate->code = $request->code;
        $directorate->description = $request->description;
        $directorate->is_active = $request->has('is_active') ? 1 : 0;
        $directorate->save();

        return redirect()->route('directorates.index')
            ->with('success', 'Directorate created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $directorate = Directorate::findOrFail($id);
        $divisions = Division::where('directorate_id', $id)->get();

        return view('directorates.show', compact('directorate', 'divisions'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $directorate = Directorate::findOrFail($id);
        return view('directorates.edit', compact('directorate'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $directorate = Directorate::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255|unique:directorates,name,' . $id,
            'code' => 'required|string|max:50|unique:directorates,code,' . $id,
            'description' => 'nullable|string',
            'is_active' => 'nullable'
        ]);

        $directorate->name = $request->name;
        $directorate->code = $request->code;
        $directorate->description = $request->description;
        $directorate->is_active = $request->has('is_active') ? 1 : 0;
        $directorate->save();

        return redirect()->route('directorates.index')
            ->with('success', 'Directorate updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $directorate = Directorate::findOrFail($id);
        
        // Check if the directorate has any related divisions
        $divisionsCount = Division::where('directorate_id', $id)->count();
        
        if ($divisionsCount > 0) {
            return redirect()->route('directorates.index')
                ->with('error', 'Cannot delete this directorate because it has related divisions.');
        }
        
        $directorate->delete();
        return redirect()->route('directorates.index')
            ->with('success', 'Directorate deleted successfully.');
    }
}
