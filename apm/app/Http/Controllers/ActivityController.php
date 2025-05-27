<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Matrix;
use App\Models\RequestType;
use App\Models\FundType;
use App\Models\FundCode;
use App\Models\Location;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;


class ActivityController extends Controller
{
    /**
     * Display a listing of activities for a matrix.
     */
    public function index(Matrix $matrix): View
    {
        // Eager load the division relationship
        $matrix->load('division');
        
        $activities = $matrix->activities()
            ->with(['staff', 'requestType'])
            ->latest()
            ->paginate(10);

        $requestTypes = RequestType::all();
        $fundTypes = FundType::all();

        return view('activities.index', [
            'matrix' => $matrix,
            'activities' => $activities,
            'requestTypes' => $requestTypes,
            'fundTypes' => $fundTypes,
            'title' => 'Activities'
        ]);
    }

    /**
     * Show the form for creating a new activity.
     */
    public function create(Matrix $matrix): View
    {
        // Eager load the division relationship
        $matrix->load('division');
        
        $requestTypes = RequestType::all();
        $staff = Staff::active()->get();
    
        // Cache the location data for 60 minutes
        $locations = Cache::remember('locations', 60, function () {
            return Location::all();
        });
        $fundTypes = FundType::all();
        $budgetCodes = FundCode::all();
        
        return view('activities.create', [
            'matrix' => $matrix,
            'requestTypes' => $requestTypes,
            'staff' => $staff,
            'locations' => $locations,
            'fundTypes' => $fundTypes,
            'budgetCodes' => $budgetCodes,
            'title' => 'Create Activity',
            'editing' => true
        ]);
    }

    /**
     * Store a newly created activity.
     */
    public function store(Request $request, Matrix $matrix): RedirectResponse
    {
        // Check if matrix is approved
        if ($matrix->overall_status === 'approved') {
            return redirect()
                ->route('matrices.show', $matrix)
                ->with('error', 'Cannot create new activity. The matrix has been approved.');
        }
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'location_id' => 'required|array',
            'total_participants' => 'required|integer|min:1',
            'internal_participants' => 'required|array',
            'budget_id' => 'required|array',
            'key_result_area' => 'required|string',
            'request_type_id' => 'required|exists:request_types,id',
            'activity_title' => 'required|string|max:255',
            'background' => 'required|string',
            'activity_request_remarks' => 'required|string',
            'is_special_memo' => 'boolean',
            'budget' => 'required|array',
            'attachment' => 'nullable|array',
            'save_as_draft' => 'sometimes|boolean',
            'submit' => 'sometimes|boolean'
        ]);

        // Set initial workflow IDs
        $validated['forward_workflow_id'] = 1; // Default initial workflow
        $validated['reverse_workflow_id'] = 1;
        
        // Set status based on which button was clicked
        $validated['status'] = $request->has('submit') 
            ? Activity::STATUS_SUBMITTED 
            : Activity::STATUS_DRAFT;

        $activity = $matrix->activities()->create($validated);

        $message = $request->has('submit') 
            ? 'Activity submitted successfully.' 
            : 'Activity saved as draft.';

        return redirect()
            ->route('matrices.activities.show', [$matrix, $activity])
            ->with('success', $message);
    }

    /**
     * Display the specified activity.
     */
    public function show(Matrix $matrix, Activity $activity): View
    {
        // Eager load the division relationship
        $matrix->load('division');
        $activity->load(['staff', 'requestType', 'serviceRequests']);
        $staff = Staff::active()->get();
        
        return view('activities.show', [
            'matrix' => $matrix,
            'activity' => $activity,
            'staff' => $staff,
            'title' => 'View Activity'
        ]);
    }

    /**
     * Show the form for editing the specified activity.
     */
    /**
     * Get budget codes by fund type and division.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBudgetCodesByFundType(Request $request)
    {
        $request->validate([
            'fund_type_id' => 'required|exists:fund_types,id',
            'division_id' => 'required|exists:divisions,id'
        ]);

        $budgetCodes = FundCode::where('fund_type_id', $request->fund_type_id)
            ->where('division_id', $request->division_id)
            ->where('is_active', true)
            ->get(['id', 'code', 'description']);

        return response()->json($budgetCodes);
    }

    /**
     * Show the form for editing the specified activity.
     */
    public function edit(Matrix $matrix, Activity $activity): View
    {
        // Check if matrix is approved
        if ($matrix->overall_status === 'approved') {
            return redirect()
                ->route('matrices.activities.show', [$matrix, $activity])
                ->with('error', 'Cannot edit activity. The parent matrix has been approved.');
        }

        // Eager load the division relationship
        $matrix->load('division');
        
        $requestTypes = RequestType::all();
        $staff = Staff::active()->get();
        $locations = Location::all();
        $fundTypes = FundType::all();
        $budgetCodes = FundCode::all();

        return view('activities.edit', [
            'matrix' => $matrix,
            'activity' => $activity,
            'requestTypes' => $requestTypes,
            'staff' => $staff,
            'locations' => $locations,
            'fundTypes' => $fundTypes,
            'budgetCodes' => $budgetCodes,
            'title' => 'Edit Activity',
            'editing' => true
        ]);
    }

    /**
     * Update the specified activity.
     */
    public function update(Request $request, Matrix $matrix, Activity $activity): RedirectResponse
    {
        // Check if matrix is approved
        if ($matrix->overall_status === 'approved') {
            return redirect()
                ->route('matrices.activities.show', [$matrix, $activity])
                ->with('error', 'Cannot update activity. The parent matrix has been approved.');
        }

        $validated = $request->validate([
            'workplan_activity_code' => 'required|string|max:255',
            'staff_id' => 'required|exists:staff,id',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'location_id' => 'required|array',
            'total_participants' => 'required|integer|min:1',
            'internal_participants' => 'required|array',
            'budget_id' => 'required|array',
            'key_result_area' => 'required|string',
            'request_type_id' => 'required|exists:request_types,id',
            'activity_title' => 'required|string|max:255',
            'background' => 'required|string',
            'activity_request_remarks' => 'required|string',
            'is_special_memo' => 'boolean',
            'budget' => 'required|array',
            'attachment' => 'nullable|array',
            'save_as_draft' => 'sometimes|boolean',
            'submit' => 'sometimes|boolean'
        ]);

        // Set status based on which button was clicked
        if ($request->has('submit')) {
            $validated['status'] = Activity::STATUS_SUBMITTED;
        } elseif ($request->has('save_as_draft')) {
            $validated['status'] = Activity::STATUS_DRAFT;
        }

        $activity->update($validated);

        $message = $request->has('submit') 
            ? 'Activity submitted successfully.' 
            : 'Activity updated successfully.';

        return redirect()
            ->route('matrices.activities.show', [$matrix, $activity])
            ->with('success', $message);
    }

    /**
     * Remove the specified activity.
     */
    public function destroy(Matrix $matrix, Activity $activity): RedirectResponse
    {
        // Check if matrix is approved
        if ($matrix->overall_status === 'approved') {
            return redirect()
                ->route('matrices.activities.show', [$matrix, $activity])
                ->with('error', 'Cannot delete activity. The parent matrix has been approved.');
        }

        $activity->delete();

        return redirect()
            ->route('matrices.activities.index', $matrix)
            ->with('success', 'Activity deleted successfully.');
    }
}