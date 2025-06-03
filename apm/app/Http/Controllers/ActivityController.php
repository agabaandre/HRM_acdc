<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Matrix;
use App\Models\RequestType;
use App\Models\FundType;
use App\Models\FundCode;
use App\Models\Location;
use App\Models\Staff;
use App\Models\CostItem;
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
        // Eager load division
        $matrix->load('division');
    
        // Request Types
        $requestTypes = RequestType::all();
    
        // Staff only from current matrix division
        $staff = Staff::active()
            ->where('division_id', $matrix->division_id)
            ->get();
    
        // All staff grouped by division for external participants
        $allStaff = Staff::active()
            ->where('division_id', '!=', $matrix->division_id)
            ->get()
            ->groupBy('division_name');
    
        // Cache locations
        $locations = Cache::remember('locations', 60, function () {
            return Location::all();
        });
    
        // Fund and Cost items
        $fundTypes = FundType::all();
        $budgetCodes = FundCode::all();
        $costItems = CostItem::all();
    
        return view('activities.create', [
            'matrix' => $matrix,
            'requestTypes' => $requestTypes,
            'staff' => $staff,
            'allStaffGroupedByDivision' => $allStaff,
            'locations' => $locations,
            'fundTypes' => $fundTypes,
            'budgetCodes' => $budgetCodes,
            'costItems' => $costItems,
            'title' => 'Create Activity',
            'editing' => false,
        ]);
    }
    

    /**
     * Store a newly created activity.
     */
    public function store(Request $request, Matrix $matrix): RedirectResponse
    {
        if ($matrix->overall_status === 'approved') {
            return redirect()
                ->route('matrices.show', $matrix)
                ->with([
                    'msg' => 'Cannot create new activity. The matrix has been approved.',
                    'type' => 'error'
                ]);
        }
    
        return \DB::transaction(function () use ($request, $matrix) {
            try {
                $validated = $request->validate([
                    'staff_id' => 'required|exists:staff,staff_id',
                    'date_from' => 'required|date',
                    'date_to' => 'required|date|after_or_equal:date_from',
                    'location_id' => 'required|array|min:1',
                    'location_id.*' => 'string',
                    'total_participants' => 'required|integer|min:1',
                    'internal_participants' => 'required|array|min:1',
                    'internal_participants.*' => 'string',
                    'request_type_id' => 'required|exists:request_types,id',
                    'activity_title' => 'required|string|max:255',
                    'background' => 'required|string',
                    'activity_request_remarks' => 'required|string',
                    'fund_type' => 'required|exists:fund_types,id',
                    'budget_codes' => 'required|array|min:1',
                    'budget_codes.*' => 'exists:fund_codes,id',
                    'key_result_link' => 'required|string',
                    'participant_days' => 'required|array',
                    'budget' => 'required|array',
                ]);
    
                // Convert location names to IDs
                $locationIds = \App\Models\Location::whereIn('name', $validated['location_id'])->pluck('id')->toArray();
    
                // Convert participant names to IDs
                $staffMap = \App\Models\Staff::whereIn('name', $validated['internal_participants'])->pluck('id', 'name');
    
                // Format participant days using IDs
                $participantDays = [];
                foreach ($validated['participant_days'] as $name => $days) {
                    if ($staffMap->has($name)) {
                        $participantDays[$staffMap[$name]] = ['days' => (int)$days];
                    }
                }
    
                $activity = $matrix->activities()->create([
                    'staff_id' => $validated['staff_id'],
                    'date_from' => $validated['date_from'],
                    'date_to' => $validated['date_to'],
                    'total_participants' => $validated['total_participants'],
                    'key_result_area' => $validated['key_result_link'],
                    'request_type_id' => $validated['request_type_id'],
                    'activity_title' => $validated['activity_title'],
                    'background' => $validated['background'],
                    'activity_request_remarks' => $validated['activity_request_remarks'],
                    'forward_workflow_id' => 1,
                    'reverse_workflow_id' => 1,
                    'status' => \App\Models\Activity::STATUS_DRAFT,
                    'fund_type_id' => $validated['fund_type'],
                    'location_id' => json_encode($validated['location_id']), // keep names
                    'internal_participants' => json_encode($validated['internal_participants']), // keep names
                    'budget' => json_encode($validated['budget']),
                    'budget_id' => json_encode($validated['budget_codes']),
                ]);
    
                // Sync related tables
                $activity->locations()->sync($locationIds);
                $activity->fundCodes()->sync($validated['budget_codes']);
                $activity->participants()->sync($participantDays);
    
                return redirect()
                    ->route('matrices.activities.show', [$matrix, $activity])
                    ->with([
                        'msg' => 'Activity created successfully.',
                        'type' => 'success'
                    ]);
    
            } catch (\Exception $e) {
                \DB::rollBack();
                \Log::error('Error creating activity', ['exception' => $e]);
    
                return redirect()->back()->withInput()->with([
                    'msg' => 'An error occurred while creating the activity. Please try again.',
                    'type' => 'error'
                ]);
            }
        });
    }
    
    


    /**
     * Display the specified activity.
     */
    public function show(Matrix $matrix, Activity $activity): View
    {
        // Eager load all necessary relationships
        $activity->load([
            'staff',
            'requestType',
            'serviceRequests',
            'fundType',
            'locations',
            'fundCodes',
            'participants'
        ]);
        
        $matrix->load('division');
        $staff = Staff::active()->get();

        return view('activities.show', [
            'matrix' => $matrix,
            'activity' => $activity,
            'staff' => $staff,
            'title' => 'View Activity: ' . $activity->activity_title
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
            ->get(['id', 'code', 'description','available_balance']);

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
