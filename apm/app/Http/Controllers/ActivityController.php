<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityApprovalTrail;
use App\Models\ActivityBudget;
use App\Models\Matrix;
use App\Models\RequestType;
use App\Models\FundType;
use App\Models\FundCode;
use App\Models\Location;
use App\Models\Staff;
use App\Models\CostItem;
use Exception;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use App\Models\ParticipantSchedule;

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
        ini_set('memory_limit', '1024M');
        // Eager load division
        $matrix->load('division');
      
        // Request Types
        $requestTypes = RequestType::all();
    
        // Staff only from current matrix division
        $staff =  Staff::active()
            ->select(['id', 'fname','lname','staff_id', 'division_id', 'division_name'])
            ->where('division_id', $matrix->division_id)
            ->get();
    
        // All staff grouped by division for external participants
        $allStaff =  Staff::active()
            ->select(['id', 'fname','lname','staff_id', 'division_id', 'division_name'])
            ->where('division_id', '!=', $matrix->division_id)
            ->get()
            ->groupBy('division_name');
    
        // Cache locations
        // $locations = Cache::remember('locations', 60, function () {
        //     return Location::all();
        // });

        $locations =Location::all();
    
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
    
        $userStaffId = session('user.auth_staff_id');
    
        return \DB::transaction(function () use ($request, $matrix, $userStaffId) {
            try {
                // Validate required fields
                $validated = $request->validate([
                    'activity_title' => 'required|string|max:255',
                    'location_id' => 'required|array|min:1',
                    'location_id.*' => 'exists:locations,id',
                    'participant_start' => 'required|array',
                    'participant_end' => 'required|array',
                    'participant_days' => 'required|array',
                ]);
    
                // Build internal_participants array with staff_id as key
                $participantStarts = $request->input('participant_start', []);
                $participantEnds = $request->input('participant_end', []);
                $participantDays = $request->input('participant_days', []);
    
                $internalParticipants = [];
    
                foreach ($participantStarts as $staffId => $startDate) {
                    $internalParticipants[$staffId] = [
                        'participant_start' => $startDate,
                        'participant_end' => $participantEnds[$staffId] ?? null,
                        'participant_days' => $participantDays[$staffId] ?? null,
                    ];
                }
    
                // Debug formatted array before insertion
                //dd($internalParticipants);

                $budgetCodes = $request->input('budget_codes', []);
                $budgetItems = $request->input('budget', []);
    
                // Create the activity record
                $activity = $matrix->activities()->create([
                    'staff_id' => $userStaffId,
                    'workplan_activity_code'=> $request->input('activity_code'),
                    'responsible_person_id' => $request->input('responsible_person_id', 1),
                    'date_from' => $request->input('date_from', now()->toDateString()),
                    'date_to' => $request->input('date_to', now()->toDateString()),
                    'total_participants' => (int) $request->input('total_participants', 1),
                    'total_external_participants' => (int) $request->input('total_external_participants', 0),
                    'key_result_area' => $request->input('key_result_link', '-'),
                    'request_type_id' => (int) $request->input('request_type_id', 1),
                    'activity_title' => $request->input('activity_title'),
                    'background' => $request->input('background', ''),
                    'activity_request_remarks' => $request->input('activity_request_remarks', ''),
                    'forward_workflow_id' => 1,
                    'reverse_workflow_id' => 1,
                    'status' => \App\Models\Activity::STATUS_DRAFT,
                    'fund_type_id' => $request->input('fund_type', 1),
                    'location_id' => json_encode($request->input('location_id', [])),
                    'internal_participants' => json_encode($internalParticipants),
                    'budget_id' => json_encode($budgetCodes),
                    'budget' => json_encode($budgetItems),
                    'attachment' => json_encode($request->input('attachments', [])),
                ]);

                if(count($internalParticipants)>0)
                $this->storeParticipantSchedules($internalParticipants,$activity);

                if(count($budgetItems)>0)
                $this->storeBudget($budgetCodes,$budgetItems,$activity);
    
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
    

    public function show(Matrix $matrix, Activity $activity): View
    {
        // Load related models
        $activity->load([
            'staff',
            'requestType',
            'serviceRequests',
            'fundType',
            'matrix.division',
            'activity_budget',
            'activity_budget.fundcode'
        ]);
    
        // Load all staff
        $staff = Staff::active()->get();
    
        // Decode JSON fields
        $locationIds = is_string($activity->location_id)
            ? json_decode($activity->location_id, true)
            : ($activity->location_id ?? []);
    
        $budgetIds = is_string($activity->budget_id)
            ? json_decode($activity->budget_id, true)
            : ($activity->budget_id ?? []);
    
        $budgetItems = is_string($activity->budget)
            ? json_decode($activity->budget, true)
            : ($activity->budget ?? []);
    
        $attachments = is_string($activity->attachment)
            ? json_decode($activity->attachment, true)
            : ($activity->attachment ?? []);
    
        // Decode internal participants (new format)
        $rawParticipants = is_string($activity->internal_participants)
            ? json_decode($activity->internal_participants, true)
            : ($activity->internal_participants ?? []);
    
        // Extract staff details and append date/days info
        $internalParticipants = [];
        if (!empty($rawParticipants)) {
            $staffDetails = Staff::whereIn('staff_id', array_keys($rawParticipants))->get()->keyBy('staff_id');
    
            foreach ($rawParticipants as $staffId => $participantData) {
                if (isset($staffDetails[$staffId])) {
                    $internalParticipants[] = [
                        'staff' => $staffDetails[$staffId],
                        'participant_start' => $participantData['participant_start'] ?? null,
                        'participant_end' => $participantData['participant_end'] ?? null,
                        'participant_days' => $participantData['participant_days'] ?? null,
                    ];
                }
            }
        }
    
        // Fetch related data
        $locations = Location::whereIn('id', $locationIds ?: [])->get();
        $fundCodes = FundCode::whereIn('id', $budgetIds ?: [])->get();
    
        return view('activities.show', [
            'matrix' => $matrix,
            'activity' => $activity,
            'staff' => $staff,
            'locations' => $locations,
            'fundCodes' => $fundCodes,
            'internalParticipants' => $internalParticipants,
            'budgetItems' => $budgetItems,
            'attachments' => $attachments,
            'title' => 'View Activity: ' . $activity->activity_title
        ]);
    }
    


    public function getBudgetCodesByFundType(Request $request)
{
    $request->validate([
        'fund_type_id' => 'required|exists:fund_types,id',
        'division_id' => 'required|exists:divisions,id',
    ]);

    $budgetCodes = FundCode::with('funder:id,name')
        ->where('fund_type_id', $request->fund_type_id)
        ->where('division_id', $request->division_id)
        ->where('is_active', true)
        ->get(['id', 'code', 'activity', 'budget_balance', 'funder_id']);

    $result = $budgetCodes->map(function ($code) {
        return [
            'id' => $code->id,
            'code' => $code->code,
            'activity' => $code->activity,
            'budget_balance' => $code->budget_balance,
            'funder_name' => optional($code->funder)->name,
        ];
    });

    return response()->json($result);
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

        if($validated['budget'])
            $this->storeBudget($validated['budget_id'],$validated['budget'],$activity);

        if($validated['internal_participants'])
            $this->storeParticipantSchedules($validated['internal_participants'],$activity);

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

    private function storeBudget($budgetCodes,$budgetItems,$activity)
    {
        if(count($budgetItems)>0)
         ActivityBudget::where('activity_id',$activity->id)->delete();

        foreach($budgetCodes as $key=>$fundCode){
            $items = $budgetItems[$fundCode];
           
            foreach($items as $item){
                $item = (Object) $item;
                $total = ($item->unit_cost * $item->units) * $item->days;

                ActivityBudget::create([
                    'activity_id'=>$activity->id,
                    'matrix_id'=>$activity->matrix_id,
                    'fund_type_id'=>$fundCode,
                    'fund_code'=>$fundCode,
                    'cost'=>$item->cost,
                    'description'=>$item->description,
                    'unit_cost'=>$item->unit_cost,
                    'units'=>$item->units,
                    'days'=>$item->days,
                    'total'=>$total
                ]);
            }
        }
    }

    private function storeParticipantSchedules($schedules,$activity)
    {
        try{

            foreach ($schedules as $participantId => $details) {
                $participant = Staff::where('staff_id',$participantId)->first();
                ParticipantSchedule::create([
                    'participant_id' => $participantId,
                    'activity_id' => $activity->id,
                    'matrix_id' => $activity->matrix->id,
                    'quarter' => $activity->matrix->quarter,
                    'year' => $activity->matrix->year,
                    'division_id' => $activity->matrix->division_id,
                    'is_home_division' => intval($activity->matrix->division_id == $participant->division_id),
                    'participant_start' => $details['participant_start'],
                    'participant_end' => $details['participant_end'],
                    'participant_days' => $details['participant_days'],
                ]);
            }
        }
        catch(Exception $exception){
            \Log::error("Error ocurred saving particiapnt schedule ".$exception->getMessage());
        }

    }

    public function update_status(Request $request, Matrix $matrix, Activity $activity): RedirectResponse
    {
        $request->validate(['action' => 'required']);
     
        $this->update_activity_status($request,$activity);

        $message = "Activity Updated successfully";

        return redirect()
        ->route('matrices.activities.show', [$matrix, $activity])
        ->with('success', $message);

    }

    private function update_activity_status($request,$activity){
        
        $activityTrail = new ActivityApprovalTrail();

        $activityTrail->remarks  = $request->comment  ?? 'Passed';
        $activityTrail->action   = $request->action;
        $activityTrail->activity_id   = $activity->id;
        $activityTrail->matrix_id   = $activity->matrix_id;
        $activityTrail->staff_id = user_session('staff_id');
        $activityTrail->save();

        $matrix = $activity->matrix;

        if($activityTrail->action !=='passed'){
            $matrix->forward_workflow_id = 1;
            $matrix->overall_status ='pending';
            $matrix->update();
        }
        
    }

    public function batch_update_status(Request $request){

        $request->validate(['action' => 'required','activity_ids' => 'required|array']);
        $activities = $request->input('activity_ids', []);
        $matrix = Matrix::find($request->input('matrix_id'));

        foreach($activities as $activity){
            $activity = Activity::find($activity);
            $this->update_activity_status($request, $activity);
        }

        $message = "Activities Updated successfully";

        return redirect()
        ->route('matrices.show', [$matrix])
        ->with('success', $message);
    }
}
