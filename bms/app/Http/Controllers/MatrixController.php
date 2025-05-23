<?php

namespace App\Http\Controllers;

use App\Models\Matrix;
use App\Models\Division;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\View as ViewFacade;

class MatrixController extends Controller
{
    /**
     * Display a listing of matrices.
     */
    public function index(): View
    {
        $matrices = Matrix::with(['division', 'staff', 'focalPerson'])
            ->latest()
            ->paginate(10);

        return ViewFacade::make('matrices.index', compact('matrices'));
    }

    /**
     * Show the form for creating a new matrix.
     */
    public function create(): View
    {
        $divisions = Division::all();
        $staff = Staff::active()->get();
        // Initialize focal persons to all active staff (will be filtered by JS on division selection)
        $focalPersons = Staff::active()->get();
        $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
        $years = range(date('Y'), date('Y') + 5);
        
        // Create an array of staff IDs by division for use in JavaScript
        $staffByDivision = [];
        $divisionFocalPersons = [];
        
        foreach ($divisions as $division) {
            // Get staff for each division
            $divisionStaff = Staff::active()->where('division_id', $division->id)->get();
            $staffByDivision[$division->id] = $divisionStaff->pluck('id')->toArray();
            
            // Store the focal person for each division
            $divisionFocalPersons[$division->id] = $division->focal_person;
        }

        return ViewFacade::make('matrices.create', compact(
            'divisions', 
            'staff', 
            'quarters', 
            'years', 
            'focalPersons', 
            'staffByDivision',
            'divisionFocalPersons'
        ));
    }

    /**
     * Store a newly created matrix.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'focal_person_id' => 'required|exists:staff,id',
            'division_id' => 'required|exists:divisions,id',
            'year' => 'required|numeric|min:2024|max:2099',
            'quarter' => 'required|in:Q1,Q2,Q3,Q4',
            'key_result_area' => 'required|array',
            'staff_id' => 'required|exists:staff,id',
        ]);

        Matrix::create($validated);

        return redirect()
            ->route('matrices.index')
            ->with('success', 'Matrix created successfully.');
    }

    /**
     * Display the specified matrix.
     */
    public function show(Matrix $matrix): View
    {
        $matrix->load(['division', 'staff', 'focalPerson', 'activities']);
        return ViewFacade::make('matrices.show', compact('matrix'));
    }

    /**
     * Show the form for editing the specified matrix.
     */
    public function edit(Matrix $matrix): View
    {
        $divisions = Division::all();
        $staff = Staff::active()->get();
        $focalPersons = Staff::active()->get();
        $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
        $years = range(date('Y'), date('Y') + 5);
        
        // Create an array of staff IDs by division for use in JavaScript
        $staffByDivision = [];
        $divisionFocalPersons = [];
        
        foreach ($divisions as $division) {
            // Get staff for each division
            $divisionStaff = Staff::active()->where('division_id', $division->id)->get();
            $staffByDivision[$division->id] = $divisionStaff->pluck('id')->toArray();
            
            // Store the focal person for each division
            $divisionFocalPersons[$division->id] = $division->focal_person;
        }

        return ViewFacade::make('matrices.edit', compact(
            'matrix',
            'divisions', 
            'staff', 
            'quarters', 
            'years', 
            'focalPersons', 
            'staffByDivision',
            'divisionFocalPersons'
        ));
    }

    /**
     * Update the specified matrix.
     */
    public function update(Request $request, Matrix $matrix): RedirectResponse
    {
        $validated = $request->validate([
            'focal_person_id' => 'required|exists:staff,id',
            'division_id' => 'required|exists:divisions,id',
            'year' => 'required|numeric|min:2024|max:2099',
            'quarter' => 'required|in:Q1,Q2,Q3,Q4',
            'key_result_area' => 'required|array',
            'staff_id' => 'required|exists:staff,id',
        ]);

        $matrix->update($validated);

        return redirect()
            ->route('matrices.index')
            ->with('success', 'Matrix updated successfully.');
    }

    /**
     * Remove the specified matrix.
     */
    public function destroy(Matrix $matrix): RedirectResponse
    {
        $matrix->delete();

        return redirect()
            ->route('matrices.index')
            ->with('success', 'Matrix deleted successfully.');
    }
}