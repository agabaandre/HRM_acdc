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
    public function index(Request $request): View
    {
        $query = Matrix::with(['division', 'staff', 'focalPerson']);
    
        // Apply filters
        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }
    
        if ($request->filled('quarter')) {
            $query->where('quarter', $request->quarter);
        }
    
        if ($request->filled('focal_person')) {
            $query->where('focal_person_id', $request->focal_person);
        }
    
        if ($request->filled('division')) {
            $query->where('division_id', $request->division);
        }
    
        $matrices = $query->latest()->paginate(10);
    
        $title = user_session('division_name');
        $module = 'Quarterly Matrix';
        $divisions = \App\Models\Division::all();
        $focalPersons = \App\Models\Staff::active()->get();
    
        return view('matrices.index', compact(
            'matrices', 'title', 'module', 'divisions', 'focalPersons'
        ));
    }
    

    /**
     * Show the form for creating a new matrix.
     */
    public function create(): View
    {
        $divisions = Division::all();
        $staff = Staff::active()->get();
        $focalPersons = $staff;
        $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
        $years = range(date('Y'), date('Y') + 5);
    
        $staffByDivision = [];
        $divisionFocalPersons = [];
    
        foreach ($divisions as $division) {
            $divisionStaff = Staff::active()->where('division_id', $division->id)->get();
            $staffByDivision[$division->id] = $divisionStaff->pluck('id')->toArray();
            $divisionFocalPersons[$division->id] = $division->focal_person;
        }
    
        // Save division name in session for breadcrumb use
        session()->put('division_name', user_session('division_name'));
    
        return view('matrices.create', [
            'divisions' => $divisions,
            'title' => user_session('division_name'),
            'module' => 'Quarterly Matrix',
            'staff' => $staff,
            'quarters' => $quarters,
            'years' => $years,
            'focalPersons' => $focalPersons,
            'staffByDivision' => $staffByDivision,
            'divisionFocalPersons' => $divisionFocalPersons,
        ]);
    }
    public function store(Request $request)
{
    $isAdmin = session('user.user_role') == 10;
    $userDivisionId = session('user.division_id');
    $userStaffId = session('user.auth_staff_id');

    // Validate form input
    $validated = $request->validate([
        'year' => 'required|integer',
        'quarter' => 'required|in:Q1,Q2,Q3,Q4',
        'key_result_area.*.description' => 'required|string',
    ]);

    // Restrict input for non-admins
    if (! $isAdmin) {
        $validated['division_id'] = $userDivisionId;
        $validated['focal_person_id'] = $userStaffId;
    }

    // Store the matrix
    Matrix::create([
        'division_id' => $validated['division_id'],
        'focal_person_id' => $validated['focal_person_id'],
        'year' => $validated['year'],
        'quarter' => $validated['quarter'],
        'key_result_area' => json_encode($validated['key_result_area']),
        'staff_id' => user_session('staff_id'),
        'forward_workflow_id' => 1, // You had this twice. Only one is needed.
    ]);

    return redirect()->route('matrices.index')
                     ->with([
                         'msg' => 'Matrix created successfully.',
                         'type' => 'success'
                     ]);
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