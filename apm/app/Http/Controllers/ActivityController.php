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
use App\Models\WorkflowModel;
use Exception;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ParticipantSchedule;
use Illuminate\Http\JsonResponse;
use App\Models\FundCodeTransaction;
use App\Models\Approver;
use App\Models\WorkflowDefinition;
use App\Services\ApprovalService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use function PHPUnit\Framework\isEmpty;

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

        // For matrix-specific activities, we need to provide the same variables that the view expects
        $years = range(now()->year - 2, now()->year + 2);
        $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
        $divisions = \App\Models\Division::orderBy('division_name')->get();
        
        // Initialize empty paginated results for the tabs (since this is matrix-specific)
        $allActivities = new LengthAwarePaginator([], 0, 20);
        $myDivisionActivities = new LengthAwarePaginator([], 0, 20);
        $sharedActivities = new LengthAwarePaginator([], 0, 20);
        
        // Set default values for the view
        $selectedYear = $matrix->year;
        $selectedQuarter = $matrix->quarter;
        $selectedDivisionId = $matrix->division_id;
        $userDivisionId = user_session('division_id');

        return view('activities.index', [
            'matrix' => $matrix,
            'activities' => $activities,
            'requestTypes' => $requestTypes,
            'fundTypes' => $fundTypes,
            'title' => 'Activities',
            // Add the variables that the view expects
            'years' => $years,
            'quarters' => $quarters,
            'divisions' => $divisions,
            'allActivities' => $allActivities,
            'myDivisionActivities' => $myDivisionActivities,
            'sharedActivities' => $sharedActivities,
            'selectedYear' => $selectedYear,
            'selectedQuarter' => $selectedQuarter,
            'selectedDivisionId' => $selectedDivisionId,
            'userDivisionId' => $userDivisionId
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
    
        // All staff in the system for responsible person (with job details)
        $staff =  Staff::active()
            ->select(['id', 'fname','lname','staff_id', 'division_id', 'division_name', 'job_name', 'duty_station_name'])
            ->get();
    
        // Staff only from current matrix division for internal participants
        $divisionStaff =  Staff::active()
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
            'activity'=>(Object) [],
            'staff' => $staff,
            'divisionStaff' => $divisionStaff,
            'allStaffGroupedByDivision' => $allStaff,
            'locations' => $locations,
            'fundTypes' => $fundTypes,
            'budgetCodes' => $budgetCodes,
            'costItems' => $costItems,
            'title' => 'Create Activity',
            'editing' => false,
        ]);
    }
    // save activity

    public function store(Request $request, Matrix $matrix): RedirectResponse|JsonResponse
    {
      
       // dd($request->all());
    
        $userStaffId = session('user.auth_staff_id');
    
        return DB::transaction(function () use ($request, $matrix, $userStaffId) {
            try {
                // Validate required fields
                $validated = $request->validate([
                    'activity_title' => 'required|string|max:255',
                    'location_id' => 'required|array|min:1',
                    'location_id.*' => 'exists:locations,id',
                    'participant_start' => 'required|array',
                    'participant_end' => 'required|array',
                    'participant_days' => 'required|array',
                    'attachments.*.type' => 'required|string|max:255',
                    'attachments.*.file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,ppt,pptx,xls,xlsx,doc,docx|max:10240', // 10MB max
                ]);

                // Validate total participants and budget
                $totalParticipants = (int) $request->input('total_participants', 0);
                if ($totalParticipants <= 0) {
                    $errorMessage = 'Cannot create activity with zero or negative total participants.';
                    
                    if ($request->ajax()) {
                        return response()->json([
                            'success' => false,
                            'msg' => $errorMessage
                        ], 422);
                    }
                    
                    return redirect()->back()->withInput()->with([
                        'msg' => $errorMessage,
                        'type' => 'error'
                    ]);
                }

                // Calculate total budget from budget items
                $totalBudget = 0;
                $budgetItems = $request->input('budget', []);
                $fundTypeId = (int) $request->input('fund_type', 1);
                
                if (!empty($budgetItems)) {
                    foreach ($budgetItems as $codeId => $items) {
                        if (is_array($items)) {
                            foreach ($items as $item) {
                                $qty = isset($item['units']) ? floatval($item['units']) : 1;
                                $unitCost = isset($item['unit_cost']) ? floatval($item['unit_cost']) : 0;
                                $totalBudget += $qty * $unitCost;
                            }
                        }
                    }
                }

                // Allow zero budget only for external source (fund_type_id = 3)
                if ($totalBudget <= 0 && $fundTypeId !== 3) {
                    $errorMessage = 'Cannot create activity with zero or negative total budget.';
                    
                    if ($request->ajax()) {
                        return response()->json([
                            'success' => false,
                            'msg' => $errorMessage
                        ], 422);
                    }
                    
                    return redirect()->back()->withInput()->with([
                        'msg' => $errorMessage,
                        'type' => 'error'
                    ]);
                }
    
                // Build internal_participants array with staff_id as key
                $participantStarts = $request->input('participant_start', []);
                $participantEnds = $request->input('participant_end', []);
                $participantDays = $request->input('participant_days', []);
                $internationalTravel = $request->input('international_travel', []);

                $internalParticipants = [];

                foreach ($participantStarts as $staffId => $startDate) {
                    $internalParticipants[$staffId] = [
                        'participant_start' => $startDate,
                        'participant_end' => $participantEnds[$staffId] ?? null,
                        'participant_days' => $participantDays[$staffId] ?? null,
                        'international_travel' => isset($internationalTravel[$staffId]) ? 1 : 0,
                    ];
                }
    
                // Debug formatted array before insertion
                //dd($internalParticipants);

                $budgetCodes = $request->input('budget_codes', []);
                $budgetItems = $request->input('budget', []);
                
                // Handle file uploads for attachments
                $attachments = [];
                if ($request->hasFile('attachments')) {
                    $uploadedFiles = $request->file('attachments');
                    $attachmentTypes = $request->input('attachments', []);
                    
                    foreach ($uploadedFiles as $index => $file) {
                        if ($file && $file->isValid()) {
                            $type = $attachmentTypes[$index]['type'] ?? 'Document';
                            
                            // Validate file type
                            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'ppt', 'pptx', 'xls', 'xlsx', 'doc', 'docx'];
                            $extension = strtolower($file->getClientOriginalExtension());
                            
                            if (!in_array($extension, $allowedExtensions)) {
                                throw new \Exception("Invalid file type. Only PDF, JPG, JPEG, PNG, PPT, PPTX, XLS, XLSX, DOC, and DOCX files are allowed.");
                            }
                            
                            // Generate unique filename
                            $filename = time() . '_' . uniqid() . '.' . $extension;
                            
                            // Store file in public/uploads/activities directory
                            $path = $file->storeAs('uploads/activities', $filename, 'public');
                            
                            $attachments[] = [
                                'type' => $type,
                                'filename' => $filename,
                                'original_name' => $file->getClientOriginalName(),
                                'path' => $path,
                                'size' => $file->getSize(),
                                'mime_type' => $file->getMimeType(),
                                'uploaded_at' => now()->toDateTimeString()
                            ];
                        }
                    }
                }
    
                // Create the activity record
                $activity = $matrix->activities()->create([
                    'staff_id' => $userStaffId, // Use staff_id directly
                    'workplan_activity_code'=> $request->input('activity_code'),
                    'responsible_person_id' => $request->input('responsible_person_id'), // Use staff_id directly
                    'date_from' => $request->input('date_from', now()->toDateString()),
                    'date_to' => $request->input('date_to', now()->toDateString()),
                    'total_participants' => (int) $request->input('total_participants', 1),
                    'total_external_participants' => (int) $request->input('total_external_participants', 0),
                    'key_result_area' => $request->input('key_result_area'),
                    'request_type_id' => (int) $request->input('request_type_id', 1),
                    'activity_title' => $request->input('activity_title'),
                    'background' => $request->input('background', ''),
                    'activity_request_remarks' => $request->input('activity_request_remarks', ''),
                    'forward_workflow_id' => null,
                    'reverse_workflow_id' => 1,
                    'status' => \App\Models\Activity::STATUS_DRAFT,
                    'fund_type_id' => $request->input('fund_type', 1),
                    'location_id' => json_encode($request->input('location_id', [])),
                    'internal_participants' => json_encode($internalParticipants),
                    'budget_id' => json_encode($budgetCodes),
                    'budget_breakdown' => json_encode($budgetItems),
                    'attachment' => json_encode($attachments),
                    'is_single_memo' => $request->input('is_single_memo', 0),
                    'approval_level' => 0,
                    'division_id' => $matrix->division_id,
                    'overall_status' =>\App\Models\Activity::STATUS_DRAFT,
                ]);

                Log::info('Activity created', ['activity' => $activity]);

                if(count($internalParticipants)>0)
                $this->storeParticipantSchedules($internalParticipants,$activity);

                if(count($budgetItems)>0)
                $this->storeBudget($budgetCodes,$budgetItems,$activity);
    
                $successMessage = 'Activity created successfully.';
                $redirectUrl = route('matrices.activities.show', [$matrix, $activity]);
                
                if ($request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'msg' => $successMessage,
                        'redirect_url' => $redirectUrl,
                        'activity' => [
                            'id' => $activity->id,
                            'title' => $activity->activity_title,
                            'date_from' => $activity->date_from,
                            'date_to' => $activity->date_to,
                            'total_participants' => $activity->total_participants,
                            'status' => $activity->overall_status,
                            'request_type' => $activity->requestType->name ?? 'N/A'
                        ]
                    ]);
                }
    
                return redirect($redirectUrl)
                    ->with([
                        'msg' => $successMessage,
                        'type' => 'success'
                    ]);
    
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error creating activity', ['exception' => $e]);
    
                $errorMessage = 'An error occurred while creating the activity. Please try again.';
                
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'msg' => $errorMessage
                    ], 500);
                }
    
                return redirect()->back()->withInput()->with([
                    'msg' => $errorMessage,
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
            'activity_budget.fundcode',
            'activityApprovalTrails.staff',
            'activityApprovalTrails.oicStaff',
            'approvalTrails'
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
    
        $budgetItems = is_string($activity->budget_breakdown)
            ? json_decode($activity->budget_breakdown, true)
            : ($activity->budget_breakdown ?? []);
    
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
    
        return view(( $activity->is_single_memo ? 'activities.single-memos.show' : 'activities.show'), [
            'matrix' => $activity->matrix,
            'activity' => $activity,
            'staff' => $staff,
            'locations' => $locations,
            'fundCodes' => $fundCodes,
            'budgetCodes' => $budgetIds, // Pass budget IDs as budgetCodes for compatibility
            'internalParticipants' => $internalParticipants,
            'budgetItems' => $budgetItems,
            'attachments' => $attachments,
            'title' => ($activity->is_single_memo ? 'View Single Memo: ' : 'View Activity: ') . $activity->activity_title
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
        ->where('is_active', true)
            ->where(function ($query) use ($request) {
                // Include codes where division_id matches the request, or is NULL, or is empty string (universal)
                $query->where('division_id', $request->division_id)
                      ->orWhereNull('division_id')
                      ->orWhere('division_id', '');
            })
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

    public function getFundTypeByBudgetCode(Request $request)
    {
        $budgetCodeId = $request->input('budget_code_id');

        if (!$budgetCodeId) {
            return response()->json(null);
        }

        $budgetCode = FundCode::find($budgetCodeId);
        
        if (!$budgetCode) {
            return response()->json(null);
        }

        return response()->json($budgetCode->fund_type_id);
}

    /**
     * Show the form for editing the specified activity (for matrices.activities.edit route).
     */
    public function edit(Matrix $matrix, Activity $activity)
    {
        // No need to swap variables - Laravel passes them in the correct order for resource routes
        
        // Debug logging
        \Illuminate\Support\Facades\Log::info('Edit method called', [
            'activity_id' => $activity->id,
            'activity_title' => $activity->activity_title,
            'is_single_memo' => $activity->is_single_memo,
            'matrix_id' => $matrix->id,
            'matrix_status' => $matrix->overall_status
        ]);

        // Check if matrix is approved
        if ($matrix->overall_status === 'approved') {
            return redirect()
                ->route('matrices.activities.show', [$matrix, $activity])
                ->with('error', 'Cannot edit activity. The parent matrix has been approved.');
        }

        // Eager load the division relationship
        $matrix->load('division');

        $requestTypes = RequestType::all();
        // All staff in the system for responsible person (with job details)
        $staff = Staff::active()
            ->select(['id', 'fname', 'lname', 'staff_id', 'division_id', 'division_name', 'job_name', 'duty_station_name'])
            ->get();
        
        // Staff only from current matrix division for internal participants
        $divisionStaff = Staff::active()
            ->select(['id', 'fname', 'lname', 'staff_id', 'division_id', 'division_name'])
            ->where('division_id', $matrix->division_id)
            ->get();
        
        $locations = Location::all();
        $fundTypes = FundType::all();
        $costItems = CostItem::all();
        
        // Get all staff grouped by division for external participants
        $allStaffGroupedByDivision = Staff::active()
            ->select(['staff_id', 'fname', 'lname', 'division_id', 'division_name'])
            ->get()
            ->groupBy('division_name');
            

        // Decode JSON fields
        $locationIds = is_string($activity->location_id)
            ? json_decode($activity->location_id, true)
            : ($activity->location_id ?? []);

        $budgetIds = is_string($activity->budget_id)
            ? json_decode($activity->budget_id, true)
            : ($activity->budget_id ?? []);

        $budgetItems = is_string($activity->budget_breakdown)
            ? json_decode($activity->budget_breakdown, true)
            : ($activity->budget_breakdown ?? []);

        $attachments = is_string($activity->attachment)
            ? json_decode($activity->attachment, true)
            : ($activity->attachment ?? []);

        // Decode internal participants (new format)
        $rawParticipants = is_string($activity->internal_participants)
            ? json_decode($activity->internal_participants, true)
            : ($activity->internal_participants ?? []);

        // Extract staff details and append date/days info
        $internalParticipants = [];
        $externalParticipants = [];
        if (!empty($rawParticipants)) {
            $staffDetails = Staff::whereIn('staff_id', array_keys($rawParticipants))->get()->keyBy('staff_id');

            foreach ($rawParticipants as $staffId => $participantData) {
                if (isset($staffDetails[$staffId])) {
                    $participant = [
                        'staff' => $staffDetails[$staffId],
                        'participant_start' => $participantData['participant_start'] ?? null,
                        'participant_end' => $participantData['participant_end'] ?? null,
                        'participant_days' => $participantData['participant_days'] ?? null,
                        'international_travel' => $participantData['international_travel'] ?? 1,
                    ];
                    
                    // Separate internal and external participants
                    if ($staffDetails[$staffId]->division_id == $matrix->division_id) {
                        $internalParticipants[] = $participant;
                    } else {
                        $externalParticipants[] = $participant;
                    }
                }
            }
        }

        // Fetch related data
        $selectedLocations = Location::whereIn('id', $locationIds ?: [])->get();
        $fundCodes = FundCode::whereIn('id', $budgetIds ?: [])->get();


        return view('activities.edit', [
            'matrix' => $matrix,
            'activity' => $activity,
            'requestTypes' => $requestTypes,
            'staff' => $staff,
            'divisionStaff' => $divisionStaff,
            'locations' => $locations,
            'fundTypes' => $fundTypes,
            'costItems' => $costItems,
            'allStaffGroupedByDivision' => $allStaffGroupedByDivision,
            'internalParticipants' => $internalParticipants,
            'externalParticipants' => $externalParticipants,
            'budgetItems' => $budgetItems,
            'attachments' => $attachments,
            'title' => 'Edit Activity',
            'editing' => true
        ]);
    }

    /**
     * Show the form for editing the specified single memo activity (for activities.single-memos.edit route).
     */
    public function editSingleMemo(Matrix $matrix, Activity $activity)
    {
        // Check if activity can be edited (only if it's in draft status and user is the creator)
        if ($activity->overall_status !== 'draft' || $activity->staff_id != user_session('staff_id')) {
            return redirect()
                ->route('activities.single-memos.show', $activity)
                ->with('error', 'Cannot edit activity. Only draft activities created by you can be edited.');
        }

        // Eager load the division relationship
        $matrix->load('division');

        $requestTypes = RequestType::all();
        // All staff in the system for responsible person (with job details)
        $staff = Staff::active()
            ->select(['id', 'fname', 'lname', 'staff_id', 'division_id', 'division_name', 'job_name', 'duty_station_name'])
            ->get();
        
        // Staff only from current matrix division for internal participants
        $divisionStaff = Staff::active()
            ->select(['id', 'fname', 'lname', 'staff_id', 'division_id', 'division_name'])
            ->where('division_id', $matrix->division_id)
            ->get();
        
        $locations = Location::all();
        $fundTypes = FundType::all();
        $costItems = CostItem::all();
        
        // Get all staff grouped by division for external participants
        $allStaffGroupedByDivision = Staff::active()
            ->select(['staff_id', 'fname', 'lname', 'division_id', 'division_name'])
            ->get()
            ->groupBy('division_name');
            

        // Decode JSON fields
        $locationIds = is_string($activity->location_id)
            ? json_decode($activity->location_id, true)
            : ($activity->location_id ?? []);

        $budgetIds = is_string($activity->budget_id)
            ? json_decode($activity->budget_id, true)
            : ($activity->budget_id ?? []);

        $budgetItems = is_string($activity->budget_breakdown)
            ? json_decode($activity->budget_breakdown, true)
            : ($activity->budget_breakdown ?? []);

        $attachments = is_string($activity->attachment)
            ? json_decode($activity->attachment, true)
            : ($activity->attachment ?? []);

        // Decode internal participants (new format)
        $rawParticipants = is_string($activity->internal_participants)
            ? json_decode($activity->internal_participants, true)
            : ($activity->internal_participants ?? []);

        // Extract staff details and append date/days info
        $internalParticipants = [];
        $externalParticipants = [];
        if (!empty($rawParticipants)) {
            $staffDetails = Staff::whereIn('staff_id', array_keys($rawParticipants))->get()->keyBy('staff_id');

            foreach ($rawParticipants as $staffId => $participantData) {
                if (isset($staffDetails[$staffId])) {
                    $participant = [
                        'staff' => $staffDetails[$staffId],
                        'participant_start' => $participantData['participant_start'] ?? null,
                        'participant_end' => $participantData['participant_end'] ?? null,
                        'participant_days' => $participantData['participant_days'] ?? null,
                        'international_travel' => $participantData['international_travel'] ?? 1,
                    ];
                    
                    // Separate internal and external participants
                    if ($staffDetails[$staffId]->division_id == $matrix->division_id) {
                        $internalParticipants[] = $participant;
                    } else {
                        $externalParticipants[] = $participant;
                    }
                }
            }
        }

        // Fetch related data
        $selectedLocations = Location::whereIn('id', $locationIds ?: [])->get();
        $fundCodes = FundCode::whereIn('id', $budgetIds ?: [])->get();
        
        // Calculate current activity budget for each fund code (for editing validation)
        $currentActivityBudgets = [];
        if ($fundCodes->isNotEmpty()) {
            $activityBudgets = \App\Models\ActivityBudget::where('activity_id', $activity->id)->get();
            foreach ($fundCodes as $fundCode) {
                $currentBudget = $activityBudgets->where('fund_code', $fundCode->id)->first();
                $currentActivityBudgets[$fundCode->id] = $currentBudget ? $currentBudget->total : 0;
            }
        }

        return view('activities.edit', [
            'matrix' => $matrix,
            'activity' => $activity,
            'requestTypes' => $requestTypes,
            'staff' => $staff,
            'divisionStaff' => $divisionStaff,
            'locations' => $locations,
            'fundTypes' => $fundTypes,
            'costItems' => $costItems,
            'allStaffGroupedByDivision' => $allStaffGroupedByDivision,
            'internalParticipants' => $internalParticipants,
            'externalParticipants' => $externalParticipants,
            'budgetItems' => $budgetItems,
            'attachments' => $attachments,
            'selectedLocations' => $selectedLocations,
            'fundCodes' => $fundCodes,
            'currentActivityBudgets' => $currentActivityBudgets,
            'title' => 'Edit Single Memo',
            'editing' => true
        ]);
    }

    /**
     * Update the specified single memo activity.
     */
    public function updateSingleMemo(Request $request, Matrix $matrix, Activity $activity): RedirectResponse|JsonResponse
    {
        Log::info('UpdateSingleMemo method called', [
            'matrix_id' => $matrix->id,
            'activity_id' => $activity->id,
            'is_single_memo' => $activity->is_single_memo ?? false,
            'request_data' => $request->all()
        ]);
        
        // Use the same logic as regular update but with single memo specific handling
        return $this->update($request, $matrix, $activity);
    }

    /**
     * Update the specified activity.
     */
    public function update(Request $request, Matrix $matrix, Activity $activity): RedirectResponse|JsonResponse
    {
       // dd($request->all());
        Log::info('Update method called', [
            'matrix_id' => $matrix->id,
            'activity_id' => $activity->id,
            'is_single_memo' => $activity->is_single_memo ?? false,
            'request_data' => $request->all()
        ]);
        
        // Check if matrix is approved
        Log::info('Checking matrix approval status', [
            'matrix_id' => $matrix->id,
            'matrix_status' => $matrix->overall_status,
            'is_approved' => $matrix->overall_status === 'approved'
        ]);
        
        // Block regular activities if matrix is approved, but allow single memos
        if ($matrix->overall_status == 'approved' && $activity->is_single_memo == 0) {
            $message = 'Cannot update activity. The matrix has been approved.';
            
            Log::info('Matrix is approved, blocking regular activity update', [
                'matrix_id' => $matrix->id,
                'matrix_status' => $matrix->overall_status,
                'is_single_memo' => $activity->is_single_memo
            ]);
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'msg' => $message
                ], 403);
            }
            
            return redirect()
                ->route('matrices.activities.show', [$matrix, $activity])
                ->with('error', $message);
        }
        
        // Log that single memo editing is allowed even with approved matrix
        if ($matrix->overall_status == 'approved' && $activity->is_single_memo == 1) {
            Log::info('Matrix is approved but allowing single memo update', [
                'matrix_id' => $matrix->id,
                'matrix_status' => $matrix->overall_status,
                'is_single_memo' => $activity->is_single_memo
            ]);
        }

        $userStaffId = session('user.auth_staff_id');

        return DB::transaction(function () use ($request, $matrix, $activity, $userStaffId) {
            try {
                // Validate required fields
                try {
                    $validated = $request->validate([
                        'activity_title' => 'required|string|max:255',
                        'location_id' => 'required|array|min:1',
                        'location_id.*' => 'exists:locations,id',
                        'participant_start' => 'required|array',
                        'participant_end' => 'required|array',
                    'participant_days' => 'required|array',
                    'attachments.*.type' => 'required|string|max:255',
                    'attachments.*.file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,ppt,pptx,xls,xlsx,doc,docx|max:10240', // 10MB max
                ]);
                } catch (\Illuminate\Validation\ValidationException $e) {
                    Log::error('Validation failed in update', [
                        'errors' => $e->validator->errors()->all(),
                        'request_data' => $request->all()
                    ]);
                    
                    $errorMessage = 'Validation failed: ' . implode(', ', $e->validator->errors()->all());
                    
                    if ($request->ajax()) {
                        return response()->json([
                            'success' => false,
                            'msg' => $errorMessage
                        ], 422);
                    }
                    
                    return redirect()->back()->withInput()->with([
                        'msg' => $errorMessage,
                        'type' => 'error'
                    ]);
                }

                Log::info('Validation passed, proceeding with update');

                // Build internal_participants array with staff_id as key
                $participantStarts = $request->input('participant_start', []);
                $participantEnds = $request->input('participant_end', []);
                $participantDays = $request->input('participant_days', []);
                $internationalTravel = $request->input('international_travel', []);

                $internalParticipants = [];

                foreach ($participantStarts as $staffId => $startDate) {
                    $internalParticipants[$staffId] = [
                        'participant_start' => $startDate,
                        'participant_end' => $participantEnds[$staffId] ?? null,
                        'participant_days' => $participantDays[$staffId] ?? null,
                        'international_travel' => isset($internationalTravel[$staffId]) ? 1 : 0,
                    ];
                }

                $budgetCodes = $request->input('budget_codes', []);
                $budgetItems = $request->input('budget', []);
                
                // Calculate total budget from budget items
                $totalBudget = 0;
                $fundTypeId = (int) $request->input('fund_type', 1);
                
                if (!empty($budgetItems)) {
                    foreach ($budgetItems as $codeId => $items) {
                        if (is_array($items)) {
                            foreach ($items as $item) {
                                $qty = isset($item['units']) ? floatval($item['units']) : 1;
                                $unitCost = isset($item['unit_cost']) ? floatval($item['unit_cost']) : 0;
                                $totalBudget += $qty * $unitCost;
                            }
                        }
                    }
                }

                // Allow zero budget only for external source (fund_type_id = 3)
                if ($totalBudget <= 0 && $fundTypeId !== 3) {
                    $errorMessage = 'Cannot update activity with zero or negative total budget.';
                    
                    if ($request->ajax()) {
                        return response()->json([
                            'success' => false,
                            'msg' => $errorMessage
                        ], 422);
                    }
                    
                    return redirect()->back()->withInput()->with([
                        'msg' => $errorMessage,
                        'type' => 'error'
                    ]);
                }
                
                // Handle file uploads for attachments
                $attachments = [];
                $existingAttachments = is_string($activity->attachment) 
                    ? json_decode($activity->attachment, true) 
                    : ($activity->attachment ?? []);
                
                // Get attachment data from request
                $attachmentData = $request->input('attachments', []);
                
                // Process each attachment slot
                foreach ($attachmentData as $index => $attachmentInfo) {
                    $type = $attachmentInfo['type'] ?? 'Document';
                    $file = $request->file("attachments.{$index}.file");
                    $shouldReplace = isset($attachmentInfo['replace']) && $attachmentInfo['replace'] == '1';
                    $shouldDelete = isset($attachmentInfo['delete']) && $attachmentInfo['delete'] == '1';
                    
                    // Skip if user wants to delete this attachment
                    if ($shouldDelete) {
                        continue;
                    }
                    
                    if ($file && $file->isValid()) {
                        // New file uploaded - validate and store
                            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'ppt', 'pptx', 'xls', 'xlsx', 'doc', 'docx'];
                            $extension = strtolower($file->getClientOriginalExtension());
                            
                            if (!in_array($extension, $allowedExtensions)) {
                                throw new \Exception("Invalid file type. Only PDF, JPG, JPEG, PNG, PPT, PPTX, XLS, XLSX, DOC, and DOCX files are allowed.");
                            }
                            
                            // Generate unique filename
                            $filename = time() . '_' . uniqid() . '.' . $extension;
                            
                            // Store file in public/uploads/activities directory
                            $path = $file->storeAs('uploads/activities', $filename, 'public');
                            
                            $attachments[] = [
                                'type' => $type,
                                'filename' => $filename,
                                'original_name' => $file->getClientOriginalName(),
                                'path' => $path,
                                'size' => $file->getSize(),
                                'mime_type' => $file->getMimeType(),
                                'uploaded_at' => now()->toDateTimeString()
                            ];
                        } else {
                        // No new file uploaded - check if user wants to replace
                        if ($shouldReplace && isset($existingAttachments[$index])) {
                            // User wants to replace but no new file provided - skip this attachment
                            continue;
                        } elseif (isset($existingAttachments[$index])) {
                            // Keep existing attachment
                                $attachments[] = $existingAttachments[$index];
                            }
                        }
                    }
                
                // If no attachment data was provided, keep all existing attachments
                if (empty($attachmentData)) {
                    $attachments = $existingAttachments;
                }

                // Update the activity record
                $activity->update([
                    'staff_id' => $userStaffId,
                    'workplan_activity_code' => $request->input('activity_code'),
                    'responsible_person_id' => $request->input('responsible_person_id'),
                    'date_from' => $request->input('date_from', now()->toDateString()),
                    'date_to' => $request->input('date_to', now()->toDateString()),
                    'total_participants' => (int) $request->input('total_participants', 1),
                    'total_external_participants' => (int) $request->input('total_external_participants', 0),
                    'key_result_area' => $request->input('key_result_area'),
                    'request_type_id' => (int) $request->input('request_type_id', 1),
                    'activity_title' => $request->input('activity_title'),
                    'background' => $request->input('background', ''),
                    'activity_request_remarks' => $request->input('activity_request_remarks', ''),
                    'fund_type_id' => $request->input('fund_type', 1),
                    'location_id' => json_encode($request->input('location_id', [])),
                    'internal_participants' => json_encode($internalParticipants),
                    'budget_id' => json_encode($budgetCodes),
                    'budget_breakdown' => json_encode($budgetItems),
                    'attachment' => json_encode($attachments),
                    'overall_status' => 'draft',
                    'approval_level' => 0,
                    'is_single_memo' => $activity->is_single_memo??0, // Preserve single memo status
                    'forward_workflow_id' => null,
                    'is_draft' => 1,
                ]);

                if (count($internalParticipants) > 0)
                    $this->storeParticipantSchedules($internalParticipants, $activity);

                // Always call storeBudget to handle both updates and deletions
                $this->storeBudget($budgetCodes, $budgetItems, $activity);

                $successMessage = 'Activity updated successfully.';
                $redirectUrl = route('matrices.activities.show', [$matrix, $activity]);
                
                if ($request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'msg' => $successMessage,
                        'redirect_url' => $redirectUrl
                    ]);
                }

                return redirect($redirectUrl)
                    ->with([
                        'msg' => $successMessage,
                        'type' => 'success'
                    ]);
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error updating activity', [
                    'exception' => $e,
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                    'trace' => $e->getTraceAsString()
                ]);

                $errorMessage = 'Error: ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')';
                
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'msg' => $errorMessage
                    ], 500);
                }

                return redirect()->back()->withInput()->with([
                    'msg' => $errorMessage,
                    'type' => 'error'
                ]);
            }
        });
    }

    /**
     * Remove the specified activity.
     */
    public function destroy(Matrix $matrix, Activity $activity): RedirectResponse
    {
        // Check if matrix is approved
        if ($matrix->overall_status === 'approved') {
            return redirect()
                ->route('matrices.show', $matrix)
                ->with('error', 'Cannot delete activity. The parent matrix has been approved.');
        }

        // Check if user can delete the activity
        $currentUserId = user_session('staff_id');
        $canDelete = false;

        // Allow deletion if matrix is in draft or returned status
        if (in_array($matrix->overall_status, ['draft', 'returned'])) {
            // Allow if user is the responsible person or the creator
            if ($activity->responsible_person_id == $currentUserId || $activity->staff_id == $currentUserId) {
                $canDelete = true;
            }
        }

        if (!$canDelete) {
            return redirect()
                ->route('matrices.show', $matrix)
                ->with('error', 'You do not have permission to delete this activity.');
        }

        try {
            DB::beginTransaction();

            // 1. Delete participant schedules
            ParticipantSchedule::where('activity_id', $activity->id)->delete();

            // 2. Delete activity approval trails
            ActivityApprovalTrail::where('activity_id', $activity->id)->delete();

            // 3. Get activity budgets before deletion to restore fund code balances
            $activityBudgets = ActivityBudget::where('activity_id', $activity->id)->get();
            
            // 4. Restore fund code balances and delete transactions
            foreach ($activityBudgets as $budget) {
                // Find the fund code
                $fundCode = FundCode::find($budget->fund_code);
                if ($fundCode) {
                    // Add back the budget amount to fund code balance
                    $fundCode->budget_balance = floatval($fundCode->budget_balance ?? 0) + floatval($budget->total ?? 0);
                    $fundCode->save();
                    
                    // Create reversal transaction for audit trail
                    FundCodeTransaction::create([
                        'fund_code_id' => $budget->fund_code,
                        'amount' => floatval($budget->total ?? 0),
                        'description' => "Activity Deletion Reversal: " . $activity->activity_title . " - Fund Code: " . $fundCode->code,
                        'activity_id' => $activity->id,
                        'matrix_id' => $activity->matrix_id,
                        'activity_budget_id' => $budget->id,
                        'balance_before' => floatval($fundCode->budget_balance ?? 0) - floatval($budget->total ?? 0),
                        'balance_after' => floatval($fundCode->budget_balance ?? 0),
                        'is_reversal' => true,
                        'created_by' => user_session('staff_id'),
                    ]);
                }
                
                // Delete fund code transactions for this budget
                FundCodeTransaction::where('activity_budget_id', $budget->id)->delete();
            }

            // 5. Delete activity budgets
            ActivityBudget::where('activity_id', $activity->id)->delete();

            // 6. Delete uploaded files
            $attachments = json_decode($activity->attachment, true) ?? [];
            foreach ($attachments as $attachment) {
                if (isset($attachment['path'])) {
                    $filePath = storage_path('app/public/' . $attachment['path']);
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
            }

            // 7. Delete the activity
            $activity->delete();

            DB::commit();

            return redirect()
                ->route('matrices.show', $matrix)
                ->with('success', 'Activity deleted successfully. Budget amounts have been restored to fund codes.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting activity', ['exception' => $e]);
            
            return redirect()
                ->route('matrices.show', $matrix)
                ->with('error', 'An error occurred while deleting the activity.');
        }
    }

    private function storeBudget($budgetCodes, $budgetItems, $activity)
    {
        // Get existing budget records for this activity
        $existingBudgets = ActivityBudget::where('activity_id', $activity->id)->get()->keyBy('id');
        
        if (count($budgetItems) > 0) {
            
            foreach ($budgetCodes as $key => $fundCode) {
                $items = $budgetItems[$fundCode];
               
                foreach ($items as $index => $item) {
                    $item = (object) $item;
                    $total = ($item->unit_cost * $item->units) * $item->days;

                    try {
                        DB::beginTransaction();
                        
                        // Check if we have an existing budget record to update
                        $existingBudget = $existingBudgets->first(function ($budget) use ($fundCode, $item) {
                            return $budget->fund_code == $fundCode && 
                                   $budget->cost == $item->cost &&
                                   $budget->description == $item->description;
                        });
                        
                        if ($existingBudget) {
                            // Update existing record
                            $oldTotal = $existingBudget->total;
                            $existingBudget->update([
                                'unit_cost' => $item->unit_cost,
                                'units' => $item->units,
                                'days' => $item->days,
                                'total' => $total
                            ]);
                            
                            // Update the fund code transaction if total changed
                            if ($oldTotal != $total) {
                                $transaction = FundCodeTransaction::where('activity_budget_id', $existingBudget->id)->first();
                                if ($transaction) {
                                    $fundCode = FundCode::find($fundCode);
                                    $difference = $total - $oldTotal;
                                    
                                    // Update transaction
                                    $transaction->update([
                                        'amount' => $total,
                                        'balance_after' => $transaction->balance_before - $total
                                    ]);
                                    
                                    // Update fund code balance
                                    $fundCode->budget_balance = $fundCode->budget_balance - $difference;
                                    $fundCode->save();
                                }
                            }
                            
                            $activityBudget = $existingBudget;
                        } else {
                            // Create new record
                            $activityBudget = ActivityBudget::create([
                                'activity_id' => $activity->id,
                                'matrix_id' => $activity->matrix_id,
                                'fund_type_id' => $fundCode,
                                'fund_code' => $fundCode,
                                'cost' => $item->cost,
                                'description' => $item->description,
                                'unit_cost' => $item->unit_cost,
                                'units' => $item->units,
                                'days' => $item->days,
                                'total' => $total
                            ]);

                            $this->store_fund_code_transaction($fundCode, $total, $activity, $activityBudget);
                        }
                        
                        DB::commit();
                    } catch (Exception $e) {
                        DB::rollBack();
                        Log::error('Error storing activity budget', ['exception' => $e]);
                        return false;
                    }
                } 
            }
            
            // Remove any existing budget records that are no longer in the updated data
            $updatedBudgetIds = [];
            foreach ($budgetCodes as $fundCode) {
                $items = $budgetItems[$fundCode];
                foreach ($items as $item) {
                    $item = (object) $item;
                    $existingBudget = $existingBudgets->first(function ($budget) use ($fundCode, $item) {
                        return $budget->fund_code == $fundCode && 
                               $budget->cost == $item->cost &&
                               $budget->description == $item->description;
                    });
                    if ($existingBudget) {
                        $updatedBudgetIds[] = $existingBudget->id;
                    }
                }
            }
            
            // Delete budget records that are no longer needed
            $budgetsToDelete = $existingBudgets->whereNotIn('id', $updatedBudgetIds);
            foreach ($budgetsToDelete as $budgetToDelete) {
                // First delete related transactions
                FundCodeTransaction::where('activity_budget_id', $budgetToDelete->id)->delete();
                // Then delete the budget record
                $budgetToDelete->delete();
            }
        } else {
            // If no budget items, delete all existing budget records
            foreach ($existingBudgets as $budgetToDelete) {
                // First delete related transactions
                FundCodeTransaction::where('activity_budget_id', $budgetToDelete->id)->delete();
                // Then delete the budget record
                $budgetToDelete->delete();
            }
        }
    }

    private function reduce_fund_code_balance($fundCode, $amount)
    {
        $fundCode->budget_balance = $fundCode->budget_balance - $amount;
        $fundCode->save();
    }

    private function store_fund_code_transaction($fundCodeId, $amount, $activity, $activityBudget)
    {
        
        $fundCode = FundCode::find($fundCodeId);

        FundCodeTransaction::create([
            'fund_code_id' => $fundCodeId,
            'amount' => $amount,
            'description' => "Activity: " . $activity->activity_title . " - Fund Code: " . $fundCode->code,
            'activity_id' => $activity->id,
            'matrix_id' => $activity->matrix_id,
            'activity_budget_id' => $activityBudget->id,
            'balance_before' => $fundCode->budget_balance,
            'balance_after' => $fundCode->budget_balance - $amount,
            'is_reversal' => false,
            'created_by' => user_session('staff_id'),
        ]);

        $this->reduce_fund_code_balance($fundCode, $amount);
    }

    private function storeParticipantSchedules($schedules, $activity)
    {
        try {
            // Delete existing participant schedules for this specific activity only
            ParticipantSchedule::where('activity_id', $activity->id)->delete();

            foreach ($schedules as $participantId => $details) {
                $participant = Staff::where('staff_id', $participantId)->first();
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
                    'international_travel' => $details['international_travel'] ?? 1,
                ]);
            }
        } catch (Exception $exception) {
            Log::error("Error occurred saving participant schedule " . $exception->getMessage());
        }
    }

    public function update_status(Request $request, Matrix $matrix, Activity $activity): RedirectResponse
    {
        $request->validate(['action' => 'required']);
     
        $this->update_activity_status($request, $activity);

        $message = "Activity Updated successfully";
        
        // If converted to single memo, show different message and redirect to matrix view
        if ($request->action === 'convert_to_single_memo') {
            $message = "Activity converted to single memo successfully. It has been returned to the creator for revision.";
            return redirect()
                ->route('matrices.show', $matrix)
                ->with('success', $message);
        }

        return redirect()
        ->route('matrices.activities.show', [$matrix, $activity])
        ->with('success', $message);
    }

    private function update_activity_status($request, $activity)
    {
        
        $activityTrail = new ActivityApprovalTrail();

        $activityTrail->remarks  = $request->comment  ?? 'Passed';
        $activityTrail->action   = $request->action;
        $activityTrail->approval_order = $activity->matrix->approval_level;
        $activityTrail->activity_id   = $activity->id;
        $activityTrail->matrix_id   = $activity->matrix_id;
        $activityTrail->staff_id = user_session('staff_id');
        $activityTrail->save();

        $matrix = $activity->matrix;

        if ($activityTrail->action === 'convert_to_single_memo') {
            // Convert activity to single memo
            $this->convertActivityToSingleMemo($activity, $request->comment);
        } elseif ($activityTrail->action !== 'passed') {
            // Get assigned workflow ID for Matrix model
            $assignedWorkflowId = WorkflowModel::getWorkflowIdForModel('Matrix');
            if (!$assignedWorkflowId) {
                $assignedWorkflowId = 1; // Default workflow ID
                Log::warning('No workflow assignment found for Matrix model in activity update, using default workflow ID: 1');
            }
            $matrix->forward_workflow_id = $assignedWorkflowId;
            $matrix->overall_status = 'pending';
            $matrix->update();
        }
    }

    /**
     * Convert activity to single memo.
     */
    private function convertActivityToSingleMemo(Activity $activity, string $comment = null)
    {
        try {
            // Get assigned workflow ID for Activity model (single memo workflow)
            $assignedWorkflowId = WorkflowModel::getWorkflowIdForModel('Activity');
            if (!$assignedWorkflowId) {
                $assignedWorkflowId = 1; // Default workflow ID
                Log::warning('No workflow assignment found for Activity model in convert to single memo, using default workflow ID: 1');
            }

            // Update activity to single memo
            $activity->update([
                'is_single_memo' => true,
                'document_number' => null,
                'overall_status' => 'draft', // Set to draft so creator can edit
                'approval_level' => 1, // Reset to approval level 1
                'next_approval_level' => 2, // Set next level to 2
                'forward_workflow_id' => $assignedWorkflowId,
                'reverse_workflow_id' => $assignedWorkflowId,
                'is_draft' => true, // Mark as draft so it can be edited
            ]);

            // Create additional approval trail entry for the conversion
            ActivityApprovalTrail::create([
                'activity_id' => $activity->id,
                'matrix_id' => $activity->matrix_id,
                'staff_id' => user_session('staff_id'),
                'action' => 'converted_to_single_memo',
                'remarks' => $comment ?? 'Activity converted to single memo for revision',
                'approval_order' => $activity->approval_level,
                'forward_workflow_id' => $assignedWorkflowId,
            ]);

            Log::info('Activity converted to single memo', [
                'activity_id' => $activity->id,
                'matrix_id' => $activity->matrix_id,
                'converted_by' => user_session('staff_id'),
                'comment' => $comment
            ]);

        } catch (\Exception $e) {
            Log::error('Error converting activity to single memo', [
                'activity_id' => $activity->id,
                'exception' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function batch_update_status(Request $request)
    {

        $request->validate(['action' => 'required', 'activity_ids' => 'required|array']);
        $activities = $request->input('activity_ids', []);
        //explode the activities into an array
        $activities = count($activities) > 0 ? explode(',', $activities[0]) : [];
        //dd($activities);
        $matrix = Matrix::find($request->input('matrix_id'));

        foreach ($activities as $activity) {

            $activity = Activity::find($activity);
            $this->update_activity_status($request, $activity);
        }

        $message = "Activities Updated successfully";

        return redirect()
        ->route('matrices.show', [$matrix])
        ->with('success', $message);
    }

    public function get_participant_schedules(Request $request)
    {
        $participant_schedules = ParticipantSchedule::with('activity', 'matrix')->where('participant_id', user_session('staff_id'))
        //->where('participant_end', '>=', Carbon::now()->toDateString())
        ->orderBy('participant_end', 'desc')
        ->paginate(10);

        // Transform the data while preserving pagination
        $transformed_data = $participant_schedules->getCollection()->map(function ($schedule) {
            return [
                'id' => $schedule->id,
                'title' => $schedule->activity->activity_title,
                'start' => $schedule->participant_start,
                'end' => $schedule->participant_end,
                'division' => $schedule->matrix->division->division_name,
                'days' => $schedule->participant_days,
                'matrix' => $schedule->matrix->year . ' ' . $schedule->matrix->quarter,
                'matrix_id' => $schedule->matrix->id,
                'international_travel' => $schedule->international_travel,
                'responsible_person' => $schedule->activity->focalPerson->fname . ' ' . $schedule->activity->focalPerson->lname,
                'responsible_person_id' => $schedule->activity->responsible_person_id,
                'is_completed' => Carbon::parse($schedule->participant_end)->isPast(),
            ];
        });

        // Create response with pagination metadata
        $response = [
            'success' => true,
            'data' => $transformed_data,
            'pagination' => [
                'current_page' => $participant_schedules->currentPage(),
                'last_page' => $participant_schedules->lastPage(),
                'per_page' => $participant_schedules->perPage(),
                'total' => $participant_schedules->total(),
                'from' => $participant_schedules->firstItem(),
                'to' => $participant_schedules->lastItem(),
                'has_more_pages' => $participant_schedules->hasMorePages(),
                'next_page_url' => $participant_schedules->nextPageUrl(),
                'prev_page_url' => $participant_schedules->previousPageUrl(),
            ]
        ];

        return response()->json($response);
    }

    /**
     * Display staff activities for a specific matrix/quarter.
     */
    public function showStaffActivities($staffId, $matrix, Request $request): View
    {
        // Get the staff member
        $staff = Staff::where('staff_id', $staffId)->firstOrFail();
        
        // Get the matrix by ID
        $matrix = Matrix::findOrFail($matrix);
        
        // Get quarter and year from matrix
        $quarter = $matrix->quarter;
        $year = $matrix->year;
        
        // Get filter parameters
        $statusFilter = $request->get('status');
        $activityTypeFilter = $request->get('activity_type');
        
        // Calculate date range for the quarter
        $quarterDates = $this->getQuarterDates($quarter, $year);
        
        // Get ALL activities from the current quarter where this staff is a participant
        $allActivities = Activity::with(['matrix', 'matrix.division', 'staff', 'participantSchedules' => function($query) use ($staffId, $matrix) {
                $query->where('participant_id', $staffId)
                      ->where('international_travel', 1)
                      ->where('quarter', $matrix->quarter)
                      ->where('year', $matrix->year); // Match participants schedule filter
            }])
            ->whereHas('participantSchedules', function($query) use ($staffId, $matrix) {
                $query->where('participant_id', $staffId)
                      ->where('international_travel', 1)
                      ->where('quarter', $matrix->quarter)
                      ->where('year', $matrix->year); // Match participants schedule filter
            })
            ->where('overall_status', '!=', 'cancelled')
            ->when($statusFilter, function($query) use ($statusFilter) {
                $query->where('overall_status', $statusFilter);
            })
            ->when($activityTypeFilter === 'single_memo', function($query) {
                $query->where('is_single_memo', true);
            })
            ->when($activityTypeFilter === 'regular', function($query) {
                $query->where('is_single_memo', false);
            })
            ->orderBy('date_from')
            ->get();
        
        // Separate activities by division based on participant schedules
        $myDivisionActivities = $allActivities->filter(function($activity) use ($staff) {
            // Activities where the staff member is a participant in their home division
            return $activity->participantSchedules->where('participant_id', $staff->staff_id)
                ->where('is_home_division', true)
                ->isNotEmpty();
        });
        
        $otherDivisionActivities = $allActivities->filter(function($activity) use ($staff) {
            // Activities where the staff member is a participant in other divisions
            return $activity->participantSchedules->where('participant_id', $staff->staff_id)
                ->where('is_home_division', false)
                ->isNotEmpty();
        });
        
        return view('activities.staff-activities', [
            'staff' => $staff,
            'matrix' => $matrix,
            'myDivisionActivities' => $myDivisionActivities,
            'otherDivisionActivities' => $otherDivisionActivities,
            'quarter' => $quarter,
            'year' => $year,
            'quarterDates' => $quarterDates
        ]);
    }
    
    /**
     * Get quarter dates for filtering activities.
     */
    private function getQuarterDates($quarter, $year)
    {
        switch (strtolower($quarter)) {
            case 'q1':
                return [
                    'start' => $year . '-01-01',
                    'end' => $year . '-03-31'
                ];
            case 'q2':
                return [
                    'start' => $year . '-04-01',
                    'end' => $year . '-06-30'
                ];
            case 'q3':
                return [
                    'start' => $year . '-07-01',
                    'end' => $year . '-09-30'
                ];
            case 'q4':
                return [
                    'start' => $year . '-10-01',
                    'end' => $year . '-12-31'
                ];
            default:
                return [
                    'start' => $year . '-01-01',
                    'end' => $year . '-12-31'
                ];
        }
    }

    /**
     * Display a listing of single memos (activities with is_single_memo = true).
     */
    public function singlememos(Request $request): View
    {
        $query = Activity::with(['staff', 'matrix.division', 'fundType', 'requestType'])
            ->where('is_single_memo', true)
            ->latest();
        
        // Get current user's staff ID
        $currentStaffId = user_session('staff_id');

        if ($request->has('staff_id') && $request->staff_id) {
            $query->where('staff_id', $request->staff_id);
        }
    
        if ($request->has('division_id') && $request->division_id) {
            $query->where('division_id', $request->division_id);
        }

        if ($request->has('status') && $request->status) {
            $query->where('overall_status', $request->status);
        }

        if ($request->has('document_number') && $request->document_number) {
            $query->where('document_number', 'like', '%' . $request->document_number . '%');
        }

        // Check if user is division approver or has specific approval workflow
        if (isDivisionApprover() || !empty(user_session('division_id'))) {
            $query->where('division_id', user_session('division_id'));
        } else {
            // Check approval workflow
            $approvers = Approver::where('staff_id', user_session('staff_id'))->get();
            $approvers = $approvers->pluck('workflow_dfn_id')->toArray();
            $workflow_dfns = WorkflowDefinition::whereIn('id', $approvers)->get();
            $query->whereIn('approval_level', $workflow_dfns->pluck('approval_order')->toArray());
        }
        
        // Hide draft memos from non-creators
        $query->where(function ($q) use ($currentStaffId) {
            $q->where('overall_status', '!=', 'draft')
              ->orWhere('staff_id', $currentStaffId);
        });
        
        $singleMemos = $query->paginate(10);
        $staff = Staff::active()->get();
    
        // Get distinct divisions from staff table
        $divisions = Staff::select('division_id', 'division_name')
            ->whereNotNull('division_id')
            ->distinct()
            ->orderBy('division_name')
            ->get();
    
        return view('activities.single-memos.index', compact('singleMemos', 'staff', 'divisions'));
    }

    /**
     * Submit single memo for approval.
     */
public function submitSingleMemoForApproval(Activity $activity): RedirectResponse
    {
        if ($activity->overall_status != 'draft') {
            return redirect()->back()->with([
                'msg' => 'Only draft single memos can be submitted for approval.',
                'type' => 'error',
            ]);
        }

        if ($activity->staff_id != user_session('staff_id')) {
            return redirect()->back()->with([
                'msg' => 'Only the creator can submit this memo for approval.',
                'type' => 'error',
            ]);
        }

        // Get assigned workflow ID for Activity model
        $assignedWorkflowId = WorkflowModel::getWorkflowIdForModel('Activity');
        if (!$assignedWorkflowId) {
            $assignedWorkflowId = 1; // Default workflow ID
            Log::warning('No workflow assignment found for Activity model, using default workflow ID: 1');
        }

        // Simply set status and overall_status to 'pending'
        $activity->update([
            'overall_status' => 'pending',
            'approval_level' => 1,
            'forward_workflow_id' => $assignedWorkflowId,
            'is_draft' => 0,
        ]); 

        // Save approval trail
        $activity->saveApprovalTrail('Submitted for approval', 'submitted');

        return redirect()->route('activities.single-memos.show', $activity)->with([
            'msg' => 'Single memo submitted for approval successfully.',
            'type' => 'success',
        ]);
    }

    /**
     * Update approval status for single memo.
     */
    public function updateSingleMemoStatus(Request $request, Activity $activity): RedirectResponse
    {
        $request->validate([
            'action' => 'required|in:approved,rejected,returned',
            'comment' => 'nullable|string|max:1000',
        ]);

        $approvalService = app(ApprovalService::class);
        
        if (!$approvalService->canTakeAction($activity, user_session('staff_id'))) {
            return redirect()->back()->with([
                'msg' => 'You are not authorized to take this action.',
                'type' => 'error',
            ]);
        }

        $activity->updateApprovalStatus($request->action, $request->comment);

        switch ($request->action) {
            case 'approved':
                $message = 'Single memo approved successfully.';
                break;
            case 'rejected':
                $message = 'Single memo rejected.';
                break;
            case 'returned':
                $message = 'Single memo returned for revision.';
                break;
            default:
                $message = 'Status updated successfully.';
                break;
        }

        return redirect()->route('activities.single-memos.show', $activity)->with([
            'msg' => $message,
            'type' => 'success',
        ]);
    }

    /**
     * Show approval status page for single memo.
     */
    public function showSingleMemoStatus(Activity $activity): View
    {
        $activity->load(['staff', 'matrix.division', 'forwardWorkflow']);
        
        // Get approval level information
        $approvalLevels = $this->getApprovalLevels($activity);
        
        // Pass as singleMemo for the view
        $singleMemo = $activity;
        
        return view('activities.single-memos.status', compact('singleMemo', 'approvalLevels'));
    }

    /**
     * Get approval levels for single memo
     */
    private function getApprovalLevels(Activity $activity): array
    {
        if (!$activity->forward_workflow_id) {
            return [];
        }

        $levels = \App\Models\WorkflowDefinition::where('workflow_id', $activity->forward_workflow_id)
            ->where('is_enabled', 1)
            ->orderBy('approval_order', 'asc')
            ->get();

        $approvalLevels = [];
        foreach ($levels as $level) {
            $isCurrentLevel = $level->approval_order == $activity->approval_level;
            $isCompleted = $activity->approval_level > $level->approval_order;
            $isPending = $activity->approval_level == $level->approval_order && $activity->overall_status === 'pending';
            
            $approver = null;
            if ($level->is_division_specific && $activity->division) {
                $staffId = $activity->division->{$level->division_reference_column} ?? null;
                if ($staffId) {
                    $approver = \App\Models\Staff::where('staff_id', $staffId)->first();
                }
            } else {
                $approverRecord = \App\Models\Approver::where('workflow_dfn_id', $level->id)->first();
                if ($approverRecord) {
                    $approver = \App\Models\Staff::where('staff_id', $approverRecord->staff_id)->first();
                }
            }

            $approvalLevels[] = [
                'order' => $level->approval_order,
                'role' => $level->role,
                'approver' => $approver,
                'is_current' => $isCurrentLevel,
                'is_completed' => $isCompleted,
                'is_pending' => $isPending,
                'is_division_specific' => $level->is_division_specific,
                'division_reference' => $level->division_reference_column,
                'category' => $level->category,
            ];
        }

        return $approvalLevels;
    }

    /**
     * Print single memo PDF
     */
    public function printSingleMemo(Activity $activity)
    {
        // For now, redirect to the show page
        // TODO: Implement PDF generation for single memos
        return redirect()->route('activities.single-memos.show', $activity)
            ->with('info', 'PDF generation for single memos is not yet implemented.');
    }

    /**
     * Display the main activities page with three tabs.
     */
    public function activitiesIndex(Request $request): View
    {
        $userStaffId = user_session('staff_id');
        $userDivisionId = user_session('division_id');
        
        // Get next quarter as default
        $currentYear = now()->year;
        $currentQuarter = now()->quarter;
        $nextQuarter = $currentQuarter == 4 ? 1 : $currentQuarter + 1;
        $nextYear = $currentQuarter == 4 ? $currentYear + 1 : $currentYear;
        
        $selectedYear = $request->get('year', $nextYear);
        $selectedQuarter = $request->get('quarter', 'Q' . $nextQuarter);
        $selectedDivisionId = $request->get('division_id', '');
        $selectedDocumentNumber = $request->get('document_number', '');
        $selectedStaffId = $request->get('staff_id', '');
        
        // Ensure quarter is in correct format (Q1, Q2, Q3, Q4)
        if (!str_starts_with($selectedQuarter, 'Q')) {
            $selectedQuarter = 'Q' . $selectedQuarter;
        }
        
        // Base query for activities - show ALL activities regardless of approval status
        $baseQuery = Activity::with([
            'matrix.division',
            'responsiblePerson',
            'staff',
            'fundType'
        ])->whereHas('matrix', function ($query) use ($selectedYear, $selectedQuarter) {
            $query->where('year', $selectedYear)
                  ->where('quarter', $selectedQuarter);
        });

        // Apply additional filters
        if ($selectedDocumentNumber) {
            $baseQuery->where('document_number', 'like', '%' . $selectedDocumentNumber . '%');
        }
        
        if ($selectedStaffId) {
            $baseQuery->where('responsible_person_id', $selectedStaffId);
        }
        
        // Debug: Check what matrices are found
        $debugMatrices = \App\Models\Matrix::where('year', $selectedYear)
            ->where('quarter', $selectedQuarter)
            ->where('overall_status', 'approved')
            ->get(['id', 'division_id', 'overall_status', 'forward_workflow_id']);
            
        Log::info('Debug: Found approved matrices for activities', [
            'year' => $selectedYear,
            'quarter' => $selectedQuarter,
            'matrices_count' => $debugMatrices->count(),
            'matrices' => $debugMatrices->toArray()
        ]);
        
        // Tab 1: All Activities (visible to users with permission 87)
        $allActivities = new LengthAwarePaginator([], 0, 20); // Initialize with empty paginated result
        if (in_array(87, user_session('permissions', []))) {
            $allActivitiesQuery = clone $baseQuery;
            
            if ($selectedDivisionId) {
                $allActivitiesQuery->whereHas('matrix', function ($query) use ($selectedDivisionId) {
                    $query->where('division_id', $selectedDivisionId);
                });
            }
            
            $allActivities = $allActivitiesQuery->latest()->paginate(20);
            
            // Debug: Log activities before filtering
            Log::info('All Activities Before Final Approval Filter', [
                'originalCount' => $allActivitiesQuery->count(),
                'activities' => $allActivities->getCollection()->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'title' => $activity->activity_title,
                        'matrix_id' => $activity->matrix_id,
                        'matrix_status' => $activity->matrix->overall_status ?? 'N/A'
                    ];
                })->toArray()
            ]);
            
            // TEMPORARILY DISABLED: Filter to only show activities approved at the final level
            // This is causing issues - let's see what activities we get without filtering
            /*
            $allActivities->getCollection()->transform(function($activity) {
                if ($this->isActivityFullyApproved($activity)) {
                    return $activity;
                }
                return null;
            });
            $allActivities->setCollection($allActivities->getCollection()->filter()->values());
            */
            
            // Debug logging for final approval filtering
            Log::info('All Activities Final Approval Filter', [
                'originalCount' => $allActivitiesQuery->count(),
                'afterFilterCount' => $allActivities->count(),
                'activities' => $allActivities->getCollection()->pluck('id')->toArray()
            ]);
        }
        
        // Tab 2: My Division Activities
        $myDivisionActivities = new LengthAwarePaginator([], 0, 20); // Initialize with empty paginated result
        if ($userDivisionId) {
            $myDivisionQuery = clone $baseQuery;
            $myDivisionQuery->where(function ($query) use ($userDivisionId, $userStaffId) {
                $query->whereHas('matrix', function ($matrixQuery) use ($userDivisionId) {
                    $matrixQuery->where('division_id', $userDivisionId);
                })->orWhere('responsible_person_id', $userStaffId); // Include activities where user is responsible
            });
            $myDivisionActivities = $myDivisionQuery->latest()->paginate(20);
            
            // TEMPORARILY DISABLED: Filter to only show activities approved at the final level
            /*
            $myDivisionActivities->getCollection()->transform(function($activity) {
                if ($this->isActivityFullyApproved($activity)) {
                    return $activity;
                }
                return null;
            });
            $myDivisionActivities->setCollection($myDivisionActivities->getCollection()->filter()->values());
            */
        }
        
        // Tab 3: Shared Activities (activities I'm added to in other divisions)
        $sharedActivities = new LengthAwarePaginator([], 0, 20); // Initialize with empty paginated result
        if ($userStaffId) {
            $sharedQuery = clone $baseQuery;
            $sharedQuery->where(function ($query) use ($userStaffId, $userDivisionId) {
                $query->where('staff_id', $userStaffId) // Activities I created
                      ->orWhere('responsible_person_id', $userStaffId) // Activities I'm responsible for
                      ->orWhereHas('participantSchedules', function ($scheduleQuery) use ($userStaffId) {
                          $scheduleQuery->where('participant_id', $userStaffId); // Activities I'm participating in
                      });
            })->whereHas('matrix', function ($query) use ($userDivisionId) {
                $query->where('division_id', '!=', $userDivisionId); // From other divisions
            });
            $sharedActivities = $sharedQuery->latest()->paginate(20);
            
            // TEMPORARILY DISABLED: Filter to only show activities approved at the final level
            /*
            $sharedActivities->getCollection()->transform(function($activity) {
                if ($this->isActivityFullyApproved($activity)) {
                    return $activity;
                }
                return null;
            });
            $sharedActivities->setCollection($sharedActivities->getCollection()->filter()->values());
            */
        }
        
        // Get divisions for filter
        $divisions = \App\Models\Division::orderBy('division_name')->get();
        
        // Get years and quarters for filter
        $years = range($currentYear - 2, $currentYear + 2);
        $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
        
        // Get staff data for filter
        $staff = \App\Models\Staff::orderBy('fname')->orderBy('lname')->get();
        
        // Debug logging
        Log::info('Activities Index Debug', [
            'selectedYear' => $selectedYear,
            'selectedQuarter' => $selectedQuarter,
            'selectedDivisionId' => $selectedDivisionId,
            'userStaffId' => $userStaffId,
            'userDivisionId' => $userDivisionId,
            'allActivitiesCount' => $allActivities->count(),
            'myDivisionActivitiesCount' => $myDivisionActivities->count(),
            'sharedActivitiesCount' => $sharedActivities->count(),
            'baseQuerySQL' => $baseQuery->toSql(),
            'baseQueryBindings' => $baseQuery->getBindings(),
            'myDivisionActivities' => $myDivisionActivities->getCollection()->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'title' => $activity->activity_title,
                    'matrix_id' => $activity->matrix_id,
                    'staff_id' => $activity->staff_id,
                    'responsible_person_id' => $activity->responsible_person_id,
                    'matrix_division_id' => $activity->matrix->division_id ?? 'N/A'
                ];
            })->toArray()
        ]);
        
        return view('activities.index', compact(
            'allActivities',
            'myDivisionActivities', 
            'sharedActivities',
            'divisions',
            'staff',
            'years',
            'quarters',
            'selectedYear',
            'selectedQuarter',
            'selectedDivisionId',
            'selectedDocumentNumber',
            'selectedStaffId',
            'userDivisionId'
        ));
    }

    /**
     * Check if an activity has been fully approved at the final approval level.
     */
    private function isActivityFullyApproved(Activity $activity): bool
    {
        // Get the matrix's workflow
        $matrix = $activity->matrix;
        if (!$matrix || !$matrix->forward_workflow_id) {
            Log::info('Activity not fully approved: No matrix or workflow', [
                'activity_id' => $activity->id,
                'matrix_id' => $activity->matrix_id,
                'forward_workflow_id' => $matrix?->forward_workflow_id
            ]);
            return false;
        }

        // Get the maximum approval order from workflow definition
        $maxApprovalOrder = \App\Models\WorkflowDefinition::where('workflow_id', $matrix->forward_workflow_id)
            ->where('is_enabled', 1)
            ->max('approval_order');

        if (!$maxApprovalOrder) {
            Log::info('Activity not fully approved: No max approval order', [
                'activity_id' => $activity->id,
                'workflow_id' => $matrix->forward_workflow_id
            ]);
            return false;
        }

        // Check if the activity has any approval trails with 'passed' action
        $activityApprovals = \App\Models\ActivityApprovalTrail::where('activity_id', $activity->id)
            ->where('action', 'passed')
            ->get();

        Log::info('Activity approval check', [
            'activity_id' => $activity->id,
            'matrix_id' => $matrix->id,
            'matrix_status' => $matrix->overall_status,
            'workflow_id' => $matrix->forward_workflow_id,
            'max_approval_order' => $maxApprovalOrder,
            'passed_approvals_count' => $activityApprovals->count(),
            'passed_approvals' => $activityApprovals->pluck('action', 'staff_id')->toArray()
        ]);

        // Simplified logic: If matrix is approved and activity has at least one passed approval, consider it fully approved
        if ($matrix->overall_status === 'approved' && $activityApprovals->count() > 0) {
            Log::info('Activity fully approved: Matrix approved + has passed approvals', [
                'activity_id' => $activity->id
            ]);
            return true;
        }

        // Alternative: Check if we have enough passed approvals to match the workflow
        if ($activityApprovals->count() >= $maxApprovalOrder) {
            Log::info('Activity fully approved: Has enough passed approvals', [
                'activity_id' => $activity->id,
                'passed_count' => $activityApprovals->count(),
                'required_count' => $maxApprovalOrder
            ]);
            return true;
        }

        Log::info('Activity not fully approved: Insufficient approvals', [
            'activity_id' => $activity->id,
            'passed_count' => $activityApprovals->count(),
            'required_count' => $maxApprovalOrder
        ]);

        return false;
    }

    /**
     * Generate PDF memo for an activity.
     */
    public function generateMemoPdf(Matrix $matrix, Activity $activity)
    {
        // Load comprehensive relationships for the activity
        $activity->load([
            'matrix.division.divisionHead',
            'matrix.division.focalPerson',
            'requestType',
            'fundType',
            'activityApprovalTrails.staff',
            'matrix.matrixApprovalTrails.staff',
            'responsiblePerson',
            'staff',
            'activity_budget.fundcode.fundType',
            'focalPerson'
        ]);

        // Load matrix with comprehensive relationships
        $matrix->load([
            'division.divisionHead',
            'division.focalPerson',
            'matrixApprovalTrails.staff',
            'activities' => function ($query) {
                $query->with(['staff', 'focalPerson', 'responsiblePerson', 'activity_budget.fundcode.fundType']);
            }
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

        $budgetItems = is_string($activity->budget_breakdown)
            ? json_decode($activity->budget_breakdown, true)
            : ($activity->budget_breakdown ?? []);

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

        // Get comprehensive workflow information
        $workflowInfo = $this->getComprehensiveWorkflowInfo($activity, $matrix);

        // Organize workflow steps by memo_print_section
        $organizedWorkflowSteps = $this->organizeWorkflowStepsBySection($workflowInfo['workflow_steps']);

        // Get matrix approval trails with staff details
        $matrixApprovals = $matrix->matrixApprovalTrails()->with('staff')->get();

        // Get activity approval trails with staff details and workflow definition
        $activityApprovals = $activity->activityApprovalTrails()->with(['staff', 'oicStaff', 'workflowDefinition'])->get();

        // Generate PDF using the comprehensive data
        //$print=true;
        $print=false;
        $pdf = mpdf_print('activities.memo-pdf-simple', [
            'activity' => $activity,
            'matrix' => $matrix,
            'locations' => $locations,
            'fundCodes' => $fundCodes, 
            'internalParticipants' => $internalParticipants,
            'budget_items' => $budgetItems,
            'attachments' => $attachments,
            'matrix_approval_trails' => $matrixApprovals,
            'activity_approval_trails' => $activityApprovals,
            'staff' => $activity->staff,
            'workflow_info' => $workflowInfo,
            'organized_workflow_steps' => $organizedWorkflowSteps
        ],['preview_html' => $print]);

        // Generate filename
        $filename = 'Activity_Memo_' . str_replace(['/', '\\'], '_', $activity->activity_ref ?? $activity->created_at->format('Y-m-d')) . '_' . now()->format('Y-m-d') . '.pdf';

        // Return PDF for display in browser using mPDF Output method
        return response($pdf->Output($filename, 'I'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"'
        ]);
    }

    /**
     * Get comprehensive workflow information including matrix approval trails
     */
    private function getComprehensiveWorkflowInfo(Activity $activity, Matrix $matrix)
    {
        $workflowInfo = [
            'current_level' => null,
            'current_approver' => null,
            'workflow_steps' => collect(),
            'approval_trail' => collect(),
            'matrix_approval_trail' => collect()
        ];

        // Get matrix workflow information
        if ($matrix->forward_workflow_id) {
            // Get workflow definition with category filtering for order 7
            $workflowDefinitions = \App\Models\WorkflowDefinition::where('workflow_id', $matrix->forward_workflow_id)
                ->where('is_enabled', 1)
                ->where(function($query) use ($matrix) {
                    $query->where('approval_order', '!=', 7)
                          ->orWhere(function($subQuery) use ($matrix) {
                              $subQuery->where('approval_order', 7)
                                       ->where('category', $matrix->division->category ?? null);
                          });
                })
                ->orderBy('approval_order')
                ->with(['approvers.staff', 'approvers.oicStaff'])
                ->get();

            $workflowInfo['workflow_steps'] = $workflowDefinitions->map(function ($definition) use ($matrix) {
                $approvers = collect();

                if ($definition->is_division_specific && $matrix->division) {
                    // Get approver from division table using division_reference_column
                    $divisionColumn = $definition->division_reference_column;
                    if ($divisionColumn && isset($matrix->division->$divisionColumn)) {
                        $staffId = $matrix->division->$divisionColumn;
                        if ($staffId) {
                            $staff = \App\Models\Staff::where('staff_id', $staffId)->first();
                            if ($staff) {
                                $approvers->push([
                                    'staff' => [
                                        'id' => $staff->staff_id,
                                        'title' => $staff->title ?? 'N/A',
                                        'fname' => $staff->fname ?? '',
                                        'lname' => $staff->lname ?? '',
                                        'name' => $staff->fname . ' ' . $staff->lname,
                                        'job_title' => $staff->job_name ?? $staff->position ?? 'N/A',
                                        'position' => $staff->position ?? 'N/A',
                                        'work_email' => $staff->work_email ?? 'N/A',
                                        'personal_email' => $staff->personal_email ?? 'N/A',
                                        'phone' => $staff->phone ?? 'N/A',
                                        'mobile' => $staff->mobile ?? 'N/A',
                                        'signature' => $staff->signature ?? null,
                                        'division' => $staff->division_name ?? 'N/A',
                                        'division_id' => $staff->division_id ?? null,
                                        'duty_station' => $staff->duty_station_name ?? 'N/A',
                                        'duty_station_id' => $staff->duty_station_id ?? null,
                                        'nationality' => $staff->nationality ?? 'N/A',
                                        'gender' => $staff->gender ?? 'N/A',
                                        'date_of_birth' => $staff->date_of_birth ?? null,
                                        'hire_date' => $staff->hire_date ?? null,
                                        'contract_type' => $staff->contract_type ?? 'N/A',
                                        'employment_status' => $staff->employment_status ?? 'N/A',
                                        'created_at' => $staff->created_at ?? null,
                                        'updated_at' => $staff->updated_at ?? null
                                    ],
                                    'oic_staff' => null,
                                    'start_date' => null,
                                    'end_date' => null
                                ]);
                            }
                        }
                    }
                } else {
                    // Get approvers from approvers table
                    $approvers = $definition->approvers->map(function ($approver) {
                        return [
                            'staff' => $approver->staff ? [
                                'id' => $approver->staff->staff_id,
                                'title' => $approver->staff->title ?? 'N/A',
                                'fname' => $approver->staff->fname ?? '',
                                'lname' => $approver->staff->lname ?? '',
                                'name' => $approver->staff->fname . ' ' . $approver->staff->lname,
                                'job_title' => $approver->staff->job_name ?? $approver->staff->position ?? 'N/A',
                                'position' => $approver->staff->position ?? 'N/A',
                                'work_email' => $approver->staff->work_email ?? 'N/A',
                                'personal_email' => $approver->staff->personal_email ?? 'N/A',
                                'phone' => $approver->staff->phone ?? 'N/A',
                                'mobile' => $approver->staff->mobile ?? 'N/A',
                                'signature' => $approver->staff->signature ?? null,
                                'division' => $approver->staff->division_name ?? 'N/A',
                                'division_id' => $approver->staff->division_id ?? null,
                                'duty_station' => $approver->staff->duty_station_name ?? 'N/A',
                                'duty_station_id' => $approver->staff->duty_station_id ?? null,
                                'nationality' => $approver->staff->nationality ?? 'N/A',
                                'gender' => $approver->staff->gender ?? 'N/A',
                                'date_of_birth' => $approver->staff->date_of_birth ?? null,
                                'hire_date' => $approver->staff->hire_date ?? null,
                                'contract_type' => $approver->staff->contract_type ?? 'N/A',
                                'employment_status' => $approver->staff->employment_status ?? 'N/A',
                                'created_at' => $approver->staff->created_at ?? null,
                                'updated_at' => $approver->staff->updated_at ?? null
                            ] : null,
                            'oic_staff' => $approver->oicStaff ? [
                                'id' => $approver->oicStaff->staff_id,
                                'title' => $approver->oicStaff->title ?? 'N/A',
                                'fname' => $approver->oicStaff->fname ?? '',
                                'lname' => $approver->oicStaff->lname ?? '',
                                'name' => $approver->oicStaff->fname . ' ' . $approver->oicStaff->lname,
                                'job_title' => $approver->oicStaff->job_name ?? $approver->oicStaff->position ?? 'N/A',
                                'position' => $approver->oicStaff->position ?? 'N/A',
                                'work_email' => $approver->oicStaff->work_email ?? 'N/A',
                                'personal_email' => $approver->oicStaff->personal_email ?? 'N/A',
                                'phone' => $approver->oicStaff->phone ?? 'N/A',
                                'mobile' => $approver->oicStaff->mobile ?? 'N/A',
                                'signature' => $approver->oicStaff->signature ?? null,
                                'division' => $approver->oicStaff->division_name ?? 'N/A',
                                'division_id' => $approver->oicStaff->division_id ?? null,
                                'duty_station' => $approver->oicStaff->duty_station_name ?? 'N/A',
                                'duty_station_id' => $approver->oicStaff->duty_station_id ?? null,
                                'nationality' => $approver->oicStaff->nationality ?? 'N/A',
                                'gender' => $approver->oicStaff->gender ?? 'N/A',
                                'date_of_birth' => $approver->oicStaff->date_of_birth ?? null,
                                'hire_date' => $approver->oicStaff->hire_date ?? null,
                                'contract_type' => $approver->oicStaff->contract_type ?? 'N/A',
                                'employment_status' => $approver->oicStaff->employment_status ?? 'N/A',
                                'created_at' => $approver->oicStaff->created_at ?? null,
                                'updated_at' => $approver->oicStaff->updated_at ?? null
                            ] : null,
                            'start_date' => $approver->start_date,
                            'end_date' => $approver->end_date
                        ];
                    })->values();
                }

                return [
                    'order' => $definition->approval_order,
                    'role' => $definition->role,
                    'memo_print_section' => $definition->memo_print_section ?? 'through',
                    'print_order' => $definition->print_order,
                    'approvers' => $approvers
                ];
            })->values();

            // Get current approval level
            if ($matrix->approval_level) {
                $currentDefinition = $workflowDefinitions->where('approval_order', $matrix->approval_level)->first();
                if ($currentDefinition) {
                    $workflowInfo['current_level'] = $currentDefinition->role;

                    // Handle division-specific approvers
                    if ($currentDefinition->is_division_specific && $matrix->division) {
                        $divisionColumn = $currentDefinition->division_reference_column;
                        if ($divisionColumn && isset($matrix->division->$divisionColumn)) {
                            $staffId = $matrix->division->$divisionColumn;
                            if ($staffId) {
                                $staff = \App\Models\Staff::where('staff_id', $staffId)->first();
                                if ($staff) {
                                    $workflowInfo['current_approver'] = $staff->fname . ' ' . $staff->lname;
                                }
                            }
                        }
                    } else {
                        // Handle regular approvers
                        $currentApprover = $currentDefinition->approvers->first();
                        if ($currentApprover) {
                            $workflowInfo['current_approver'] = $currentApprover->staff ?
                                $currentApprover->staff->fname . ' ' . $currentApprover->staff->lname : ($currentApprover->oicStaff ? $currentApprover->oicStaff->fname . ' ' . $currentApprover->oicStaff->lname : 'N/A');
                        }
                    }
                }
            }
        }

        // Get matrix approval trail
        $matrixApprovalTrails = $matrix->matrixApprovalTrails()
            ->orderBy('created_at')
            ->with('staff')
            ->get();

        $workflowInfo['matrix_approval_trail'] = $matrixApprovalTrails->map(function ($trail) {
            return [
                'action' => $trail->action,
                'remarks' => $trail->remarks,
                'staff' => $trail->staff ? [
                    'name' => $trail->staff->fname . ' ' . $trail->staff->lname,
                    'job_title' => $trail->staff->job_name ?? $trail->staff->position ?? 'N/A',
                    'work_email' => $trail->staff->work_email ?? 'N/A',
                    'signature' => $trail->staff->signature ?? null
                ] : null,
                'date' => $trail->created_at ? $trail->created_at->format('d/m/Y H:i:s') : 'N/A',
                'approval_order' => $trail->approval_order ?? null
            ];
        })->values();

        // Get activity approval trail
        $activityApprovalTrails = \App\Models\ActivityApprovalTrail::where('activity_id', $activity->id)
            ->orderBy('created_at')
            ->with('staff')
            ->get();

        $workflowInfo['approval_trail'] = $activityApprovalTrails->map(function ($trail) {
            return [
                'action' => $trail->action,
                'remarks' => $trail->remarks,
                'staff' => $trail->staff ? [
                    'name' => $trail->staff->fname . ' ' . $trail->staff->lname,
                    'job_title' => $trail->staff->job_name ?? $trail->staff->position ?? 'N/A',
                    'work_email' => $trail->staff->work_email ?? 'N/A',
                    'signature' => $trail->staff->signature ?? null
                ] : null,
                'date' => $trail->created_at ? $trail->created_at->format('d/m/Y H:i:s') : 'N/A',
                'matrix_id' => $trail->matrix_id ?? null,
                'approval_order' => $trail->approval_order ?? null
            ];
        })->values();

        return $workflowInfo;
    }

    /**
     * Organize workflow steps by memo_print_section for dynamic memo rendering
     */
    private function organizeWorkflowStepsBySection($workflowSteps)
    {
        $organizedSteps = [
            'to' => collect(),
            'through' => collect(),
            'from' => collect(),
            'others' => collect()
        ];

        foreach ($workflowSteps as $step) {
            $section = $step['memo_print_section'] ?? 'through';
            $organizedSteps[$section]->push($step);
        }

        // Sort each section by print_order first, then by approval order as fallback
        foreach ($organizedSteps as $section => $steps) {
            $organizedSteps[$section] = $steps->sortBy([
                ['print_order', 'asc'],
                ['order', 'asc']
            ])->values();
        }

        return $organizedSteps;
    }

    /**
     * Get workflow information for the activity (legacy method)
     */
    private function getWorkflowInfo(Activity $activity)
    {
        $workflowInfo = [
            'current_level' => null,
            'current_approver' => null,
            'workflow_steps' => collect(),
            'approval_trail' => collect()
        ];

        if ($activity->forward_workflow_id) {
            // Get workflow definition
            $workflowDefinitions = \App\Models\WorkflowDefinition::where('workflow_id', $activity->forward_workflow_id)
                ->where('is_enabled', 1)
                ->orderBy('approval_order')
                ->with(['approvers.staff', 'approvers.oicStaff'])
                ->get();

            $workflowInfo['workflow_steps'] = $workflowDefinitions->map(function ($definition) {
                return [
                    'order' => $definition->approval_order,
                    'role' => $definition->role,
                    'approvers' => $definition->approvers->map(function ($approver) {
                        return [
                            'staff' => $approver->staff ? [
                                'name' => $approver->staff->fname . ' ' . $approver->staff->lname,
                                'position' => $approver->staff->position ?? 'N/A',
                                'division' => $approver->staff->division_name ?? 'N/A'
                            ] : null,
                            'oic_staff' => $approver->oicStaff ? [
                                'name' => $approver->oicStaff->fname . ' ' . $approver->oicStaff->lname,
                                'position' => $approver->oicStaff->position ?? 'N/A',
                                'division' => $approver->oicStaff->division_name ?? 'N/A'
                            ] : null,
                            'start_date' => $approver->start_date,
                            'end_date' => $approver->end_date
                        ];
                    })->values()
                ];
            })->values();

            // Get current approval level
            if ($activity->approval_level) {
                $currentDefinition = $workflowDefinitions->where('approval_order', $activity->approval_level)->first();
                if ($currentDefinition) {
                    $workflowInfo['current_level'] = $currentDefinition->role;
                    $currentApprover = $currentDefinition->approvers->first();
                    if ($currentApprover) {
                        $workflowInfo['current_approver'] = $currentApprover->staff ? 
                            $currentApprover->staff->fname . ' ' . $currentApprover->staff->lname : ($currentApprover->oicStaff ? $currentApprover->oicStaff->fname . ' ' . $currentApprover->oicStaff->lname : 'N/A');
                    }
                }
            }
        }

        // Get approval trail from activity_approval_trails table
        $approvalTrails = \App\Models\ActivityApprovalTrail::where('activity_id', $activity->id)
            ->orderBy('created_at')
            ->with('staff')
            ->get();

        $workflowInfo['approval_trail'] = $approvalTrails->map(function ($trail) {
            return [
                'action' => $trail->action,
                'remarks' => $trail->remarks,
                'staff' => $trail->staff ? $trail->staff->fname . ' ' . $trail->staff->lname : 'N/A',
                'date' => $trail->created_at ? $trail->created_at->format('d/m/Y H:i:s') : 'N/A',
                'matrix_id' => $trail->matrix_id ?? null
            ];
        })->values();

        return $workflowInfo;
    }

    /**
     * Display pending approvals for single memos.
     */
    public function singleMemoPendingApprovals(Request $request): View
    {
        $currentStaffId = user_session('staff_id');
        
        // Get pending memos at current user's approval level
        $pendingQuery = Activity::with(['staff', 'division', 'requestType', 'fundType'])
            ->where('is_single_memo', true)
            ->where('overall_status', 'pending')
            ->latest();

        // Get approved memos by current user
        $approvedQuery = Activity::with(['staff', 'division', 'requestType', 'fundType'])
            ->where('is_single_memo', true)
            ->whereHas('approvalTrails', function ($query) use ($currentStaffId) {
                $query->where('staff_id', $currentStaffId)
                      ->where('action', 'approved');
            })
            ->latest();

        // Apply approval level filtering for pending memos
        if (isDivisionApprover() || !empty(user_session('division_id'))) {
            $pendingQuery->where('division_id', user_session('division_id'));
        } else {
            // Check approval workflow
            $approvers = Approver::where('staff_id', $currentStaffId)->get();
            $approvers = $approvers->pluck('workflow_dfn_id')->toArray();
            $workflow_dfns = WorkflowDefinition::whereIn('id', $approvers)->get();
            $pendingQuery->whereIn('approval_level', $workflow_dfns->pluck('approval_order')->toArray());
        }

        $pendingMemos = $pendingQuery->paginate(10);
        $approvedByMe = $approvedQuery->paginate(10);

        // Get filter data
        $requestTypes = RequestType::all();
        $divisions = Staff::select('division_id', 'division_name')
            ->whereNotNull('division_id')
            ->distinct()
            ->orderBy('division_name')
            ->get();

        // Helper function for workflow info
        $getWorkflowInfo = function ($memo) {
            $workflowInfo = [
                'approvalLevel' => $memo->approval_level ?? 0,
                'workflowRole' => 'N/A',
                'actorName' => 'N/A'
            ];

            if ($memo->current_actor) {
                $workflowInfo['actorName'] = $memo->current_actor->fname . ' ' . $memo->current_actor->lname;
            }

            if ($memo->workflow_definition) {
                $workflowInfo['workflowRole'] = $memo->workflow_definition->role ?? 'N/A';
            }

            return $workflowInfo;
        };

        return view('activities.single-memos.pending-approvals', compact(
            'pendingMemos', 
            'approvedByMe', 
            'requestTypes', 
            'divisions',
            'getWorkflowInfo'
        ));
    }

    /**
     * Remove the specified single memo.
     */
    public function destroySingleMemo(Activity $activity): RedirectResponse
    {
        // Get the matrix for this single memo
        $matrix = $activity->matrix;
        
        // Check if matrix is approved
        if ($matrix->overall_status === 'approved') {
            return redirect()
                ->route('matrices.show', $matrix)
                ->with('error', 'Cannot delete single memo. The parent matrix has been approved.');
        }

        // Check if user can delete the single memo
        $currentUserId = user_session('staff_id');
        $canDelete = false;

        // Allow deletion if matrix is in draft or returned status
        if (in_array($matrix->overall_status, ['draft', 'returned'])) {
            // Allow if user is the responsible person or the creator
            if ($activity->responsible_person_id == $currentUserId || $activity->staff_id == $currentUserId) {
                $canDelete = true;
            }
        }

        if (!$canDelete) {
            return redirect()
                ->route('matrices.show', $matrix)
                ->with('error', 'You do not have permission to delete this single memo.');
        }

        try {
            DB::beginTransaction();

            // 1. Delete participant schedules
            ParticipantSchedule::where('activity_id', $activity->id)->delete();

            // 2. Delete activity approval trails
            ActivityApprovalTrail::where('activity_id', $activity->id)->delete();

            // 3. Get activity budgets before deletion to restore fund code balances
            $activityBudgets = ActivityBudget::where('activity_id', $activity->id)->get();
            
            // 4. Restore fund code balances and delete transactions
            foreach ($activityBudgets as $budget) {
                // Find the fund code
                $fundCode = FundCode::find($budget->fund_code);
                if ($fundCode) {
                    // Add back the budget amount to fund code balance
                    $fundCode->budget_balance = floatval($fundCode->budget_balance ?? 0) + floatval($budget->total ?? 0);
                    $fundCode->save();
                    
                    // Create reversal transaction for audit trail
                    FundCodeTransaction::create([
                        'fund_code_id' => $budget->fund_code,
                        'amount' => floatval($budget->total ?? 0),
                        'description' => "Single Memo Deletion Reversal: " . $activity->activity_title . " - Fund Code: " . $fundCode->code,
                        'activity_id' => $activity->id,
                        'matrix_id' => $activity->matrix_id,
                        'activity_budget_id' => $budget->id,
                        'balance_before' => floatval($fundCode->budget_balance ?? 0) - floatval($budget->total ?? 0),
                        'balance_after' => floatval($fundCode->budget_balance ?? 0),
                        'is_reversal' => true,
                        'created_by' => user_session('staff_id'),
                    ]);
                }
                
                // Delete fund code transactions for this budget
                FundCodeTransaction::where('activity_budget_id', $budget->id)->delete();
            }

            // 5. Delete activity budgets
            ActivityBudget::where('activity_id', $activity->id)->delete();

            // 6. Delete uploaded files
            $attachments = json_decode($activity->attachment, true) ?? [];
            foreach ($attachments as $attachment) {
                if (isset($attachment['path'])) {
                    $filePath = storage_path('app/public/' . $attachment['path']);
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
            }

            // 7. Delete the single memo (activity)
            $activity->delete();

            DB::commit();

            return redirect()
                ->route('matrices.show', $matrix)
                ->with('success', 'Single memo deleted successfully. Budget amounts have been restored to fund codes.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting single memo', ['exception' => $e]);
            
            return redirect()
                ->route('matrices.show', $matrix)
                ->with('error', 'An error occurred while deleting the single memo.');
        }
    }
}
