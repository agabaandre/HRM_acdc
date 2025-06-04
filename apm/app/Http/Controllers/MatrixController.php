<?php

namespace App\Http\Controllers;

use App\Models\Division;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use App\Models\Matrix;
use App\Models\Location;
use App\Models\Staff;
use App\Models\FundCode;
// use Illuminate\Support\Facades\View as ViewFacade;

class MatrixController extends Controller
{
    /**
     * Display a listing of matrices.
     */
    public function index(Request $request): View
    {
        $query = Matrix::with([
            'division',
            'staff',
            'focalPerson',
            'activities' => function ($q) {
                $q->select('matrix_id', 'total_participants', 'budget')
                  ->whereNotNull('matrix_id');
            }
        ]);
    
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
    
        $matrices->getCollection()->transform(function ($matrix) {
            $matrix->total_activities = $matrix->activities->count();
            $matrix->total_participants = $matrix->activities->sum('total_participants');
            $matrix->total_budget = $matrix->activities->sum(function ($activity) {
                return is_array($activity->budget) && isset($activity->budget['total'])
                    ? $activity->budget['total']
                    : 0;
            });
            return $matrix;
        });
    
        return view('matrices.index', [
            'matrices' => $matrices,
            'title' => user_session('division_name'),
            'module' => 'Quarterly Matrix',
            'divisions' => \App\Models\Division::all(),
            'focalPersons' => \App\Models\Staff::active()->get(),
        ]);
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
         // Load primary relationships
         $matrix->load(['division', 'staff', 'focalPerson']);
     
         // Paginate related activities and eager load direct relationships
         $activities = $matrix->activities()->with(['requestType', 'fundType'])->latest()->paginate(10);
     
         // Prepare additional decoded & related data per activity
         foreach ($activities as $activity) {
             // Decode JSON arrays
             $locationIds = is_array($activity->location_id)
                 ? $activity->location_id
                 : json_decode($activity->location_id ?? '[]', true);
     
             $internalRaw = is_string($activity->internal_participants)
                 ? json_decode($activity->internal_participants ?? '[]', true)
                 : ($activity->internal_participants ?? []);
     
             $internalParticipantIds = collect($internalRaw)->pluck('staff_id')->toArray();
     
             // Attach related models
             $activity->locations = Location::whereIn('id', $locationIds ?: [])->get();
             $activity->internalParticipants = Staff::whereIn('staff_id', $internalParticipantIds ?: [])->get();
         }
     
         return view('matrices.show', compact('matrix', 'activities'));
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
    
        // Prepare staff and focal person mapping per division
        $staffByDivision = [];
        $divisionFocalPersons = [];
    
        foreach ($divisions as $division) {
            $divisionStaff = $staff->where('division_id', $division->id);
            $staffByDivision[$division->id] = $divisionStaff->pluck('id')->toArray();
            $divisionFocalPersons[$division->id] = $division->focal_person;
        }
    
        // Ensure key_result_area is an array
        if (is_string($matrix->key_result_area)) {
            $decoded = json_decode($matrix->key_result_area, true);
            $matrix->key_result_area = is_array($decoded) ? $decoded : [];
        }
    
        return view('matrices.edit', compact(
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
        $isAdmin = session('user.user_role') == 10;
        $userDivisionId = session('user.division_id');
        $userStaffId = session('user.auth_staff_id');
    
        // Validate basic fields
        $validated = $request->validate([
            'year' => 'required|integer',
            'quarter' => 'required|in:Q1,Q2,Q3,Q4',
            'key_result_area' => 'required|array',
            'key_result_area.*.description' => 'required|string',
        ]);
    
        // For admins, allow editing focal person and division
        if ($isAdmin) {
            $validated += $request->validate([
                'division_id' => 'required|exists:divisions,id',
                'focal_person_id' => 'required|exists:staff,staff_id',
            ]);
        } else {
            $validated['division_id'] = $userDivisionId;
            $validated['focal_person_id'] = $userStaffId;
        }
    
        // Update matrix
        $matrix->update([
            'division_id'         => $validated['division_id'],
            'focal_person_id'     => $validated['focal_person_id'],
            'year'                => $validated['year'],
            'quarter'             => $validated['quarter'],
            'key_result_area'     => json_encode($validated['key_result_area']),
            'staff_id'            => user_session('staff_id'),
            'forward_workflow_id' => 1,
        ]);
    
        return redirect()->route('matrices.index')->with([
            'msg' => 'Matrix updated successfully.',
            'type' => 'success'
        ]);
        
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