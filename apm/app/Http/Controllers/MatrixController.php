<?php

namespace App\Http\Controllers;

use App\Models\Approver;
use App\Models\Division;
use App\Models\ApprovalTrail;
use App\Models\ActivityApprovalTrail;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowModel;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use App\Models\Matrix;
use App\Models\Location;
use App\Models\Staff;
use App\Models\FundCode;
use App\Services\ApprovalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
// use Illuminate\Support\Facades\View as ViewFacade;

class MatrixController extends Controller
{
    /**
     * Display a listing of matrices.
     */
    public function index(Request $request)
    {
        $query = Matrix::with([
            'division',
            'staff',
            'focalPerson',
            'forwardWorkflow',
            'activities' => function ($q) {
                $q->select('id', 'matrix_id', 'activity_title', 'total_participants', 'budget_breakdown')
                  ->whereNotNull('matrix_id');
            }
        ]);


        // Replace the complex whereHas query with proper division-specific and workflow logic
        $userDivisionId = user_session('division_id');
        $userStaffId    = user_session('staff_id');
        
        $query->where(function($q) use ($userDivisionId, $userStaffId) {
            // Case 1: Division-specific approval - check if user's division matches matrix division
            if ($userDivisionId) {
                $q->whereHas('forwardWorkflow.workflowDefinitions', function($subQ): void {
                    $subQ->where('is_division_specific', 1)
                    ->whereNull('division_reference_column')
                          ->where('approval_order', \Illuminate\Support\Facades\DB::raw('matrices.approval_level'));
                })
                ->where('division_id', $userDivisionId);
            }

            // Case 1b: Division-specific approval with division_reference_column - check if user's staff_id matches the value in the division_reference_column
            if ($userStaffId) {
                $q->orWhere(function($subQ) use ($userStaffId, $userDivisionId) {

                    $divisionsTable = (new Division())->getTable();
                    $subQ->whereRaw("EXISTS (
                        SELECT 1 FROM workflow_definition wd 
                        JOIN {$divisionsTable} d ON d.id = matrices.division_id 
                        WHERE wd.workflow_id = matrices.forward_workflow_id 
                        AND wd.is_division_specific = 1 
                        AND wd.division_reference_column IS NOT NULL 
                        AND wd.approval_order = matrices.approval_level
                        AND ( d.focal_person = ? OR
                            d.division_head = ? OR
                            d.admin_assistant = ? OR
                            d.finance_officer = ? OR
                            d.head_oic_id = ? OR
                            d.director_id = ? OR
                            d.director_oic_id = ?
                            OR (d.id=matrices.division_id AND d.id=?)
                        )
                    )", [$userStaffId, $userStaffId, $userStaffId, $userStaffId, $userStaffId, $userStaffId, $userStaffId, $userDivisionId])
                    ->orWhere(function($subQ2) use ($userStaffId) {
                        $subQ2->where('approval_level', $userStaffId)
                              ->orWhereHas('approvalTrails', function($trailQ) use ($userStaffId) {
                                $trailQ->where('staff_id', '=',$userStaffId);
                              });
                    });
                });
            }
            
            // Case 2: Non-division-specific approval - check workflow definition and approver
            if ($userStaffId) {
                $q->orWhere(function($subQ) use ($userStaffId) {
                    $subQ->whereHas('forwardWorkflow.workflowDefinitions', function($workflowQ) use ($userStaffId) {
                        $workflowQ->where('is_division_specific','=', 0)
                                  ->where('approval_order', \Illuminate\Support\Facades\DB::raw('matrices.approval_level'))
                                  ->whereHas('approvers', function($approverQ) use ($userStaffId) {
                                      $approverQ->where('staff_id', $userStaffId);
                                  });
                    });
                });
            }

            $q->orWhere('division_id', $userDivisionId);
        });

        // Get current year and quarter as default
        $currentYear = now()->year;
        $currentQuarter = 'Q' . now()->quarter;
       
        // Get selected values from request, use defaults only if not provided at all
        $selectedYear = $request->get('year', '');
        $selectedQuarter = $request->get('quarter', '');
        
        // Use defaults only on initial page load (no filters provided)
        if (empty($selectedYear) && !$request->has('year')) {
            $selectedYear = $currentYear;
        }
        if (empty($selectedQuarter) && !$request->has('quarter')) {
            $selectedQuarter = $currentQuarter;
        }

        // Apply year filter only if a year is selected
        if (!empty($selectedYear)) {
            $query->where('year', $selectedYear);
        }
    
        // Apply quarter filter only if a quarter is selected
        if (!empty($selectedQuarter)) {
            $query->where('quarter', $selectedQuarter);
        }
    
        if ($request->filled('focal_person')) {
            $query->where('focal_person_id', $request->focal_person);
        }
    
        if ($request->filled('division')) {
            $query->where('division_id', $request->division);
        }

       //  dd(getFullSql($query));

        $matrices = $query->orderBy('year', 'desc')
                          ->orderBy('quarter', 'desc')
                          ->paginate(24);

        //dd($matrices->toArray());

        
       
        $matrices->getCollection()->transform(function ($matrix) {
            $matrix->total_activities = $matrix->activities->count();
            $matrix->total_participants = $matrix->activities->sum('total_participants');
            $matrix->total_budget = $matrix->activities->sum(function ($activity) {
                return is_array($activity->budget_breakdown) && isset($activity->budget_breakdown['total'])
                    ? $activity->budget_breakdown['total']
                    : 0;
            });
            return $matrix;
        });

        
       
        // Create separate queries for each tab with proper server-side pagination
        $myDivisionQuery = Matrix::with([
            'division',
            'staff',
            'focalPerson',
            'forwardWorkflow',
            'activities' => function ($q) {
                $q->select('id', 'matrix_id', 'activity_title', 'total_participants', 'budget_breakdown')
                  ->whereNotNull('matrix_id');
            }
        ])->where(function($q) {
            $userDivisionId = user_session('division_id');
            $userStaffId = user_session('staff_id');
            
            // Show matrices from user's own division
            $q->where('division_id', $userDivisionId);
            
            // Also show matrices where user is the division head of other divisions
            if ($userStaffId) {
                $q->orWhereHas('division', function($divisionQuery) use ($userStaffId) {
                    $divisionQuery->where('division_head', $userStaffId);
                });
            }
        });

        // Apply filters to my division query (only if values are provided)
        if (!empty($selectedYear)) {
            $myDivisionQuery->where('year', $selectedYear);
        }
        if (!empty($selectedQuarter)) {
            $myDivisionQuery->where('quarter', $selectedQuarter);
        }
        if ($request->filled('focal_person')) {
            $myDivisionQuery->where('focal_person_id', $request->focal_person);
        }
        if ($request->filled('division')) {
            $myDivisionQuery->where('division_id', $request->division);
        }

        $myDivisionMatrices = $myDivisionQuery->orderBy('year', 'desc')
                                            ->orderBy('quarter', 'desc')
                                            ->paginate(24, ['*'], 'my_division_page');

        // Get all matrices for users with permission ID 87
        $allMatrices = collect();
        if (in_array(87, user_session('permissions', []))) {
            $allMatricesQuery = Matrix::with([
                'division',
                'staff',
                'focalPerson',
                'forwardWorkflow',
                'activities' => function ($q) {
                    $q->select('id', 'matrix_id', 'activity_title', 'total_participants', 'budget_breakdown')
                      ->whereNotNull('matrix_id');
                }
            ]);

            // Apply same filters to all matrices query (only if values are provided)
            if (!empty($selectedYear)) {
                $allMatricesQuery->where('year', $selectedYear);
            }
            if (!empty($selectedQuarter)) {
                $allMatricesQuery->where('quarter', $selectedQuarter);
            }
        
            if ($request->filled('focal_person')) {
                $allMatricesQuery->where('focal_person_id', $request->focal_person);
            }
        
            if ($request->filled('division')) {
                $allMatricesQuery->where('division_id', $request->division);
            }

            $allMatrices = $allMatricesQuery->orderBy('year', 'desc')
                                           ->orderBy('quarter', 'desc')
                                           ->paginate(24, ['*'], 'all_matrices_page');
        }

        //  dd($filteredActionedMatrices->toArray());

        // Handle AJAX requests for tab content
        if ($request->ajax()) {
            \Log::info('AJAX request received in MatrixController index', [
                'tab' => $request->get('tab'),
                'all_params' => $request->all()
            ]);
            
            $tab = $request->get('tab', '');
            $html = '';
            
            switch($tab) {
                case 'myDivision':
                    $html = view('matrices.partials.my-division-tab', compact(
                        'myDivisionMatrices', 'selectedYear', 'selectedQuarter'
                    ))->render();
                    break;
                case 'allMatrices':
                    $html = view('matrices.partials.all-matrices-tab', compact(
                        'allMatrices', 'selectedYear', 'selectedQuarter'
                    ))->render();
                    break;
            }
            
            \Log::info('Generated HTML length for matrices', ['html_length' => strlen($html)]);
            
            return response()->json(['html' => $html]);
        }
    
        return view('matrices.index', [
            'matrices' => $matrices,
            'myDivisionMatrices' => $myDivisionMatrices,
            'allMatrices' => $allMatrices,
            'title' => user_session('division_name'),
            'module' => 'Quarterly Matrix',
            'divisions' => \App\Models\Division::all(),
            'focalPersons' => \App\Models\Staff::active()->get(),
            'selectedYear' => $selectedYear,
            'selectedQuarter' => $selectedQuarter,
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
        
        // Calculate current and next quarter/year for one quarter ahead functionality
        $currentYear = date('Y');
        $currentMonth = date('n');
        $currentQuarter = 'Q' . ceil($currentMonth / 3);
        
        // Calculate next quarter and year
        $nextQuarter = '';
        $nextYear = $currentYear;
        
        switch ($currentQuarter) {
            case 'Q1':
                $nextQuarter = 'Q2';
                break;
            case 'Q2':
                $nextQuarter = 'Q3';
                break;
            case 'Q3':
                $nextQuarter = 'Q4';
                break;
            case 'Q4':
                $nextQuarter = 'Q1';
                $nextYear = $currentYear + 1;
                break;
        }
        
        // Create quarters array with current and next quarter
        $availableQuarters = [$currentQuarter];
        if ($nextQuarter) {
            $availableQuarters[] = $nextQuarter;
        }
        
        // Add next year to years array if not already present
        if (!in_array($nextYear, $years)) {
            $years[] = $nextYear;
            sort($years);
        }
    
        $staffByDivision = [];
        $divisionFocalPersons = [];
        $existingMatrices = [];
        $nextAvailableQuarters = [];
    
        foreach ($divisions as $division) {
            $divisionStaff = Staff::active()->where('division_id', $division->id)->get();
            $staffByDivision[$division->id] = $divisionStaff->pluck('id')->toArray();
            $divisionFocalPersons[$division->id] = $division->focal_person;
            
            // Get existing matrices for this division
            $existingMatrices[$division->id] = Matrix::getExistingMatricesForDivision($division->id);
            
            // Get next available quarter for current year
            $nextAvailableQuarters[$division->id] = Matrix::getNextAvailableQuarter($division->id, date('Y'));
        }
    
        // Save division name in session for breadcrumb use
        session()->put('division_name', user_session('division_name'));
    
        return view('matrices.create', [
            'divisions' => $divisions,
            'title' => user_session('division_name'),
            'module' => 'Quarterly Matrix',
            'staff' => $staff,
            'quarters' => $availableQuarters, // Only show current and next quarter
            'years' => $years,
            'focalPersons' => $focalPersons,
            'staffByDivision' => $staffByDivision,
            'divisionFocalPersons' => $divisionFocalPersons,
            'existingMatrices' => $existingMatrices,
            'nextAvailableQuarters' => $nextAvailableQuarters,
            'currentQuarter' => $currentQuarter,
            'nextQuarter' => $nextQuarter,
            'currentYear' => $currentYear,
            'nextYear' => $nextYear,
        ]);
    }
    public function store(Request $request)
    {
        $isAdmin = session('user.user_role') == 10;
        $userDivisionId = session('user.division_id');
        $userStaffId = session('user.auth_staff_id');

        // Validate form input
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2030',
            'quarter' => 'required|in:Q1,Q2,Q3,Q4',
            'key_result_area.*.description' => 'required|string',
        ]);

        // Restrict input for non-admins
        if (! $isAdmin) {
            $validated['division_id'] = $userDivisionId;
            $validated['focal_person_id'] = $userStaffId;
        } else {
            // For admins, validate division_id and focal_person_id
            $validated['division_id'] = $request->input('division_id');
            $validated['focal_person_id'] = $request->input('focal_person_id');
        }

        // Check if a matrix already exists for this division, year, and quarter
        if (Matrix::existsForDivisionYearQuarter($validated['division_id'], $validated['year'], $validated['quarter'])) {
            return Redirect::back()
                ->withInput()
                ->withErrors([
                    'quarter' => 'A matrix already exists for ' . $validated['division_id'] . ' in ' . $validated['year'] . ' ' . $validated['quarter'] . '. Only one matrix per division per quarter is allowed.'
                ]);
        }

        // Store the matrix
        $matrix = Matrix::create([
            'division_id' => $validated['division_id'],
            'focal_person_id' => $validated['focal_person_id'],
            'year' => $validated['year'],
            'quarter' => $validated['quarter'],
            'key_result_area' => json_encode($validated['key_result_area']),
            'staff_id' => user_session('staff_id'),
            'forward_workflow_id' => null,
            'overall_status' => 'draft'
        ]);
        
        // Generate approval order map for new matrix
        $approvalService = new \App\Services\ApprovalService();
        $approvalService->updateApprovalOrderMap($matrix);
        
        $recipients = Staff::where('division_id', $validated['division_id'])->get();
       
        send_matrix_notification( $matrix,  'created',$recipients);

        return Redirect::route('matrices.index')
                         ->with([
                             'msg' => 'Matrix created successfully.',
                             'type' => 'success'
                         ]);
    }
    

    /**
     * Display the specified matrix.
     */


    public function show(Matrix $matrix, Request $request): View
    {
        // Load primary relationships
        //(can_take_action($matrix));

        $matrix->load(['division', 'staff','participant_schedules','participant_schedules.staff','matrixApprovalTrails.staff','matrixApprovalTrails.oicStaff']);
    //dd($matrix);
        // Separate regular activities and single memos
        $activitiesQuery = $matrix->activities()->where('is_single_memo', 0)->with(['requestType', 'fundType', 'responsiblePerson', 'activity_budget','activity_budget.fundcode']);
        $singleMemosQuery = $matrix->activities()->where('is_single_memo', 1)->with(['requestType', 'fundType', 'responsiblePerson', 'activity_budget','activity_budget.fundcode']);
        
        // Apply document number filter if provided
        if ($request->filled('document_number')) {
            $activitiesQuery->where('document_number', 'like', '%' . $request->document_number . '%');
        }
        
        // Apply single memo document number filter if provided
        if ($request->filled('single_memo_search')) {
            $singleMemosQuery->where('document_number', 'like', '%' . $request->single_memo_search . '%');
        }
        
        $perPage = $request->get('per_page', 20);
        $activities = $activitiesQuery->latest()->paginate($perPage);
        $singleMemos = $singleMemosQuery->latest()->paginate(20);
     
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
        
        // Prepare additional decoded & related data per single memo
        foreach ($singleMemos as $memo) {
            // Decode JSON arrays
            $locationIds = is_array($memo->location_id)
                ? $memo->location_id
                : json_decode($memo->location_id ?? '[]', true);
    
            $internalRaw = is_string($memo->internal_participants)
                ? json_decode($memo->internal_participants ?? '[]', true)
                : ($memo->internal_participants ?? []);
    
            $internalParticipantIds = collect($internalRaw)->pluck('staff_id')->toArray();
    
            // Attach related models
            $memo->locations = Location::whereIn('id', $locationIds ?: [])->get();
            $memo->internalParticipants = Staff::whereIn('staff_id', $internalParticipantIds ?: [])->get();
        }
        
        // Filter division staff by name if provided
        //dd($matrix);
    
        return view('matrices.show', compact('matrix', 'activities', 'singleMemos'));
     }

    /**
     * Get activities for approvers via AJAX with filtering by approval order and allowed funders
     */
    public function getActivitiesForApprover(Matrix $matrix, Request $request)
    {
        $userStaffId = user_session('staff_id');
        $userDivisionId = user_session('division_id');
        
        // If user is not logged in, return all activities without filtering
        if (!$userStaffId) {
            return $this->getAllActivities($matrix, $request);
        }

        // Get user's workflow definition and approval order
        $userWorkflowDefinition = $this->getUserWorkflowDefinition($matrix, $userStaffId, $userDivisionId);
        
        // If no workflow definition found for user, return all activities without filtering
        if (!$userWorkflowDefinition) {
            return $this->getAllActivities($matrix, $request);
        }

        // Check if user is an approver at the current approval level
        $isApprover = $this->isUserApproverAtCurrentLevel($matrix, $userStaffId, $userDivisionId);
        
        // If user is not an approver at current level, return all activities without filtering
        if (!$isApprover) {
            return $this->getAllActivities($matrix, $request);
        }

        // Build activities query with filtering and eager loading
        $activitiesQuery = $matrix->activities()
            ->where('is_single_memo', 0)
            ->with([
                'requestType', 
                'fundType', 
                'responsiblePerson', 
                'activity_budget', 
                'activity_budget.fundcode',
                'activity_budget.fundcode.funder',
                'matrix.division', // Eager load matrix division
                'matrix.forwardWorkflow' // Eager load workflow
            ]);

        // Get all activities first, then filter using the same logic as get_approvable_activities
        $allActivities = $matrix->activities()
            ->where('is_single_memo', 0)
            ->with([
                'requestType', 
                'fundType', 
                'responsiblePerson', 
                'activity_budget', 
                'activity_budget.fundcode',
                'activity_budget.fundcode.funder',
                'matrix.division',
                'matrix.forwardWorkflow'
            ])
            ->get();
        
        // Filter activities based on allowed_funders using the same logic as get_approvable_activities
        $filteredActivities = collect();
        if ($userWorkflowDefinition->allowed_funders) {
            $allowedFunders = is_string($userWorkflowDefinition->allowed_funders) 
                ? json_decode($userWorkflowDefinition->allowed_funders, true) 
                : $userWorkflowDefinition->allowed_funders;
            
            if (is_array($allowedFunders) && !empty($allowedFunders)) {
                foreach ($allActivities as $activity) {
                    $canApprove = true;
                    
                    // Use budget_breakdown JSON to determine fund type instead of activity_budget model
                    if ($activity->budget_breakdown) {
                        // Parse budget_breakdown JSON
                        $budgetBreakdown = is_string($activity->budget_breakdown) 
                            ? json_decode($activity->budget_breakdown, true) 
                            : $activity->budget_breakdown;
                        
                        if (is_array($budgetBreakdown) && !empty($budgetBreakdown)) {
                            // Check if activity's fund type is in allowed_funders
                            $activityFundTypeId = $activity->fund_type_id;
                            $canApprove = in_array($activityFundTypeId, $allowedFunders);
                        } else {
                            // For activities with empty budget_breakdown, only allow if fund type 3 is in allowed_funders
                            $canApprove = in_array(3, $allowedFunders);
                        }
                    } else {
                        // For activities with no budget_breakdown, only allow if fund type 3 is in allowed_funders
                        $canApprove = in_array(3, $allowedFunders);
                    }
                    
                    if ($canApprove) {
                        $filteredActivities->push($activity);
                    }
                }
            } else {
                $filteredActivities = $allActivities;
            }
        } else {
            $filteredActivities = $allActivities;
        }
        
        // Now apply search and document number filters to the filtered activities
        $activitiesQuery = $filteredActivities;

        // Apply document number filter if provided
        if ($request->filled('document_number')) {
            $activitiesQuery = $activitiesQuery->filter(function($activity) use ($request) {
                return stripos($activity->document_number ?? '', $request->document_number) !== false;
            });
        }

        // Apply general search filter if provided
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $activitiesQuery = $activitiesQuery->filter(function($activity) use ($searchTerm) {
                return stripos($activity->activity_title ?? '', $searchTerm) !== false
                    || stripos($activity->document_number ?? '', $searchTerm) !== false
                    || stripos($activity->background ?? '', $searchTerm) !== false
                    || stripos($activity->activity_request_remarks ?? '', $searchTerm) !== false
                    || stripos($activity->responsiblePerson->fname ?? '', $searchTerm) !== false
                    || stripos($activity->responsiblePerson->lname ?? '', $searchTerm) !== false
                    || stripos($activity->fundType->name ?? '', $searchTerm) !== false;
            });
        }

        // Sort by latest (created_at desc)
        $activitiesQuery = $activitiesQuery->sortByDesc('created_at');

        // Apply pagination manually
        $perPage = $request->get('per_page', 20);
        $currentPage = $request->get('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $activities = $activitiesQuery->slice($offset, $perPage)->values();
        
        // Create pagination object
        $total = $activitiesQuery->count();
        $activities = new \Illuminate\Pagination\LengthAwarePaginator(
            $activities,
            $total,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'pageName' => 'page']
        );

        // Collect all location and staff IDs for batch loading
        $allLocationIds = [];
        $allStaffIds = [];
        $activitiesData = [];

        foreach ($activities->items() as $activity) {
            // Decode JSON arrays
            $locationIds = is_array($activity->location_id)
                ? $activity->location_id
                : json_decode($activity->location_id ?? '[]', true);

            $internalRaw = is_string($activity->internal_participants)
                ? json_decode($activity->internal_participants ?? '[]', true)
                : ($activity->internal_participants ?? []);

            $internalParticipantIds = collect($internalRaw)->pluck('staff_id')->toArray();

            // Collect IDs for batch loading
            $allLocationIds = array_merge($allLocationIds, $locationIds ?: []);
            $allStaffIds = array_merge($allStaffIds, $internalParticipantIds ?: []);

            // Store activity data for processing
            $activitiesData[] = [
                'activity' => $activity,
                'location_ids' => $locationIds ?: [],
                'staff_ids' => $internalParticipantIds ?: []
            ];
        }

        // Batch load all locations and staff
        $locations = Location::whereIn('id', array_unique($allLocationIds))->get()->keyBy('id');
        $staff = Staff::whereIn('staff_id', array_unique($allStaffIds))->get()->keyBy('staff_id');

        // Cache workflow definition data to avoid repeated queries
        $workflowDefinitionData = [
            'id' => $userWorkflowDefinition->id,
            'approval_order' => $userWorkflowDefinition->approval_order,
            'allowed_funders' => $userWorkflowDefinition->allowed_funders
        ];

        // Process activities with pre-loaded data
        $processedActivities = collect();
        foreach ($activitiesData as $data) {
            $activity = $data['activity'];
            
            // Attach related models from pre-loaded collections
            $activity->locations = $locations->whereIn('id', $data['location_ids'])->values();
            $activity->internalParticipants = $staff->whereIn('staff_id', $data['staff_ids'])->values();
            
            // Add funder information from budget_breakdown if not available from activity_budget
            if (!$activity->activity_budget || count($activity->activity_budget) === 0) {
                if ($activity->budget_breakdown) {
                    $budgetBreakdown = is_string($activity->budget_breakdown) 
                        ? json_decode($activity->budget_breakdown, true) 
                        : $activity->budget_breakdown;
                    
                    if (is_array($budgetBreakdown)) {
                        // Get the first fund code ID from budget breakdown
                        $fundCodeIds = array_filter(array_keys($budgetBreakdown), function($key) {
                            return $key !== 'grand_total';
                        });
                        
                        if (!empty($fundCodeIds)) {
                            $firstFundCodeId = $fundCodeIds[0];
                            $fundCode = \App\Models\FundCode::with('funder')->find($firstFundCodeId);
                            if ($fundCode && $fundCode->funder) {
                                // Add funder info to activity
                                $activity->funder_from_budget_breakdown = $fundCode->funder;
                                $activity->fund_code_from_budget_breakdown = $fundCode;
                            }
                        }
                    }
                }
            }
            
            // Add approval-related data (optimized with caching)
            $activity->can_approve = can_approve_activity($activity);
            $activity->allow_print = allow_print_activity($activity);
            $activity->my_last_action = $activity->my_last_action ?? null;
            $activity->my_current_level_action = $activity->my_current_level_action ?? null;
            $activity->has_passed_at_current_level = $activity->has_passed_at_current_level ?? false;
            
            // Check if user's approval order has already passed this activity
            $activity->user_has_passed = $this->hasUserPassedActivity($activity, $userWorkflowDefinition);
            
            $processedActivities->push($activity);
        }

        // Update the paginated collection with processed activities
        $activities->setCollection($processedActivities);

        return response()->json([
            'activities' => $activities,
            'user_workflow_definition' => [
                'id' => $userWorkflowDefinition->id,
                'role' => $userWorkflowDefinition->role,
                'approval_order' => $userWorkflowDefinition->approval_order,
                'allowed_funders' => $userWorkflowDefinition->allowed_funders,
                'is_division_specific' => $userWorkflowDefinition->is_division_specific,
            ],
            'pagination' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
                'from' => $activities->firstItem(),
                'to' => $activities->lastItem(),
            ]
        ]);
    }

    /**
     * Get user's workflow definition for the given matrix
     */
    private function getUserWorkflowDefinition(Matrix $matrix, $userStaffId, $userDivisionId)
    {
        if (!$matrix->forward_workflow_id) {
            return null;
        }

        // Get current approval level workflow definitions
        $workflowDefinitions = WorkflowDefinition::where('workflow_id', $matrix->forward_workflow_id)
            ->where('approval_order', $matrix->approval_level)
            ->where('is_enabled', 1)
            ->get();

        if ($workflowDefinitions->isEmpty()) {
            return null;
        }

        // Check for division-specific workflow definition
        if ($workflowDefinitions->count() > 1 && $matrix->division) {
            $divisionSpecific = $workflowDefinitions->where('is_division_specific', 1)
                ->where('category', $matrix->division->category)
                ->first();
            
            if ($divisionSpecific) {
                return $divisionSpecific;
            }
        }

        // Check if user is an approver for any of the workflow definitions
        foreach ($workflowDefinitions as $definition) {
            if ($definition->is_division_specific) {
                // Check division-specific approvers
                if ($this->isDivisionSpecificApprover($matrix, $definition, $userStaffId, $userDivisionId)) {
                    return $definition;
                }
            } else {
                // Check regular approvers
                $today = \Carbon\Carbon::today();
                
                // Check for regular approver
                $isApprover = Approver::where('workflow_dfn_id', $definition->id)
                    ->where('staff_id', $userStaffId)
                    ->where(function($query) use ($today) {
                        $query->whereNull('end_date')
                              ->orWhere('end_date', '>=', $today);
                    })
                    ->exists();
                
                if ($isApprover) {
                    return $definition;
                }
                
                // Check for OIC approver
                $isOicApprover = Approver::where('workflow_dfn_id', $definition->id)
                    ->where('oic_staff_id', $userStaffId)
                    ->where(function($query) use ($today) {
                        $query->whereNull('end_date')
                              ->orWhere('end_date', '>=', $today);
                    })
                    ->exists();
                
                if ($isOicApprover) {
                    return $definition;
                }
            }
        }

        return null;
    }

    /**
     * Check if user is an approver at the current approval level
     */
    private function isUserApproverAtCurrentLevel($matrix, $userStaffId, $userDivisionId)
    {
        if (!$matrix->forward_workflow_id) {
            return false;
        }

        // Get current approval level workflow definitions
        $workflowDefinitions = WorkflowDefinition::where('workflow_id', $matrix->forward_workflow_id)
            ->where('approval_order', $matrix->approval_level)
            ->where('is_enabled', 1)
            ->get();

        if ($workflowDefinitions->isEmpty()) {
            return false;
        }

        // Check for division-specific workflow definition
        if ($workflowDefinitions->count() > 1 && $matrix->division) {
            $divisionSpecific = $workflowDefinitions->where('is_division_specific', 1)
                ->where('category', $matrix->division->category)
                ->first();
            
            if ($divisionSpecific) {
                return $this->isDivisionSpecificApprover($matrix, $divisionSpecific, $userStaffId, $userDivisionId);
            }
        }

        // Check if user is an approver for any of the workflow definitions
        foreach ($workflowDefinitions as $definition) {
            if ($definition->is_division_specific) {
                // Check division-specific approvers
                if ($this->isDivisionSpecificApprover($matrix, $definition, $userStaffId, $userDivisionId)) {
                    return true;
                }
            } else {
                // Check regular approvers
                $today = \Carbon\Carbon::today();
                
                // Check for regular approver
                $isApprover = Approver::where('workflow_dfn_id', $definition->id)
                    ->where('staff_id', $userStaffId)
                    ->where(function($query) use ($today) {
                        $query->whereNull('end_date')
                              ->orWhere('end_date', '>=', $today);
                    })
                    ->exists();
                
                if ($isApprover) {
                    return true;
                }
                
                // Check for OIC approver
                $isOicApprover = Approver::where('workflow_dfn_id', $definition->id)
                    ->where('oic_staff_id', $userStaffId)
                    ->where(function($query) use ($today) {
                        $query->whereNull('end_date')
                              ->orWhere('end_date', '>=', $today);
                    })
                    ->exists();
                
                if ($isOicApprover) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if user is a division-specific approver
     */
    private function isDivisionSpecificApprover(Matrix $matrix, WorkflowDefinition $definition, $userStaffId, $userDivisionId)
    {
        if (!$definition->is_division_specific || !$matrix->division) {
            return false;
        }

        // Check if user's division matches matrix division
        if ($userDivisionId && $matrix->division_id == $userDivisionId) {
            return true;
        }

        // Check division reference column
        if ($definition->division_reference_column) {
            $columnValue = $matrix->division->{$definition->division_reference_column};
            if ($columnValue == $userStaffId) {
                return true;
            }
            
            // Check for active OIC for this reference column
            $today = \Carbon\Carbon::today();
            $oicColumnMap = [
                'division_head' => 'head_oic_id',
                'finance_officer' => 'finance_officer_oic_id',
                'director_id' => 'director_oic_id'
            ];
            
            $oicColumn = $oicColumnMap[$definition->division_reference_column] ?? $definition->division_reference_column . '_oic_id';
            $oicStartColumn = str_replace('_oic_id', '_oic_start_date', $oicColumn);
            $oicEndColumn = str_replace('_oic_id', '_oic_end_date', $oicColumn);
            
            // Check if current user is the active OIC
            if (isset($matrix->division->$oicColumn) && $matrix->division->$oicColumn == $userStaffId) {
                $isOicActive = true;
                if (isset($matrix->division->$oicStartColumn) && $matrix->division->$oicStartColumn) {
                    $isOicActive = $isOicActive && $matrix->division->$oicStartColumn <= $today;
                }
                if (isset($matrix->division->$oicEndColumn) && $matrix->division->$oicEndColumn) {
                    $isOicActive = $isOicActive && $matrix->division->$oicEndColumn >= $today;
                }
                
                if ($isOicActive) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if user's last action on this activity was "passed" at the current approval level
     */
    private function hasUserPassedActivity($activity, $userWorkflowDefinition)
    {
        if (!$userWorkflowDefinition || !$activity->matrix) {
            return false;
        }

        // If activity is already passed (allow_print), user has passed
        if ($activity->allow_print) {
            return true;
        }

        // Use the new attribute to check if user has passed at current level
        return $activity->has_passed_at_current_level;
    }

    /**
     * Get all activities without filtering (fallback for non-authenticated users)
     */
    private function getAllActivities(Matrix $matrix, Request $request)
    {
        // Build activities query without filtering and eager loading
        $activitiesQuery = $matrix->activities()
            ->where('is_single_memo', 0)
            ->with([
                'requestType', 
                'fundType', 
                'responsiblePerson', 
                'activity_budget', 
                'activity_budget.fundcode',
                'activity_budget.fundcode.funder',
                'matrix.division',
                'matrix.forwardWorkflow'
            ]);

        // Apply document number filter if provided
        if ($request->filled('document_number')) {
            $activitiesQuery->where('document_number', 'like', '%' . $request->document_number . '%');
        }

        // Apply general search filter if provided
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $activitiesQuery->where(function($query) use ($searchTerm) {
                $query->where('activity_title', 'like', '%' . $searchTerm . '%')
                      ->orWhere('document_number', 'like', '%' . $searchTerm . '%')
                      ->orWhere('background', 'like', '%' . $searchTerm . '%')
                      ->orWhere('activity_request_remarks', 'like', '%' . $searchTerm . '%')
                      ->orWhereHas('responsiblePerson', function($q) use ($searchTerm) {
                          $q->where('fname', 'like', '%' . $searchTerm . '%')
                            ->orWhere('lname', 'like', '%' . $searchTerm . '%');
                      })
                      ->orWhereHas('fundType', function($q) use ($searchTerm) {
                          $q->where('name', 'like', '%' . $searchTerm . '%');
                      });
            });
        }

        $perPage = $request->get('per_page', 20);
        $activities = $activitiesQuery->latest()->paginate($perPage);

        // Collect all location and staff IDs for batch loading
        $allLocationIds = [];
        $allStaffIds = [];
        $activitiesData = [];

        foreach ($activities as $activity) {
            // Decode JSON arrays
            $locationIds = is_array($activity->location_id)
                ? $activity->location_id
                : json_decode($activity->location_id ?? '[]', true);

            $internalRaw = is_string($activity->internal_participants)
                ? json_decode($activity->internal_participants ?? '[]', true)
                : ($activity->internal_participants ?? []);

            $internalParticipantIds = collect($internalRaw)->pluck('staff_id')->toArray();

            // Collect IDs for batch loading
            $allLocationIds = array_merge($allLocationIds, $locationIds ?: []);
            $allStaffIds = array_merge($allStaffIds, $internalParticipantIds ?: []);

            // Store activity data for processing
            $activitiesData[] = [
                'activity' => $activity,
                'location_ids' => $locationIds ?: [],
                'staff_ids' => $internalParticipantIds ?: []
            ];
        }

        // Batch load all locations and staff
        $locations = Location::whereIn('id', array_unique($allLocationIds))->get()->keyBy('id');
        $staff = Staff::whereIn('staff_id', array_unique($allStaffIds))->get()->keyBy('staff_id');

        // Process activities with pre-loaded data
        foreach ($activitiesData as $data) {
            $activity = $data['activity'];
            
            // Attach related models from pre-loaded collections
            $activity->locations = $locations->whereIn('id', $data['location_ids'])->values();
            $activity->internalParticipants = $staff->whereIn('staff_id', $data['staff_ids'])->values();
            
            // Add funder information from budget_breakdown if not available from activity_budget
            if (!$activity->activity_budget || count($activity->activity_budget) === 0) {
                if ($activity->budget_breakdown) {
                    $budgetBreakdown = is_string($activity->budget_breakdown) 
                        ? json_decode($activity->budget_breakdown, true) 
                        : $activity->budget_breakdown;
                    
                    if (is_array($budgetBreakdown)) {
                        // Get the first fund code ID from budget breakdown
                        $fundCodeIds = array_filter(array_keys($budgetBreakdown), function($key) {
                            return $key !== 'grand_total';
                        });
                        
                        if (!empty($fundCodeIds)) {
                            $firstFundCodeId = $fundCodeIds[0];
                            $fundCode = \App\Models\FundCode::with('funder')->find($firstFundCodeId);
                            if ($fundCode && $fundCode->funder) {
                                // Add funder info to activity
                                $activity->funder_from_budget_breakdown = $fundCode->funder;
                                $activity->fund_code_from_budget_breakdown = $fundCode;
                            }
                        }
                    }
                }
            }
            
            // Add approval-related data (default to false for non-authenticated users)
            $activity->can_approve = false;
            $activity->allow_print = false;
            $activity->my_last_action = null;
            $activity->user_has_passed = false;
        }

        return response()->json([
            'activities' => $activities,
            'user_workflow_definition' => null,
            'pagination' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
                'from' => $activities->firstItem(),
                'to' => $activities->lastItem(),
            ]
        ]);
    }

    /**
     * Get single memos for approvers via AJAX with filtering
     */
    public function getSingleMemosForApprover(Matrix $matrix, Request $request)
    {
        $userStaffId = user_session('staff_id');
        $userDivisionId = user_session('division_id');
        
        // If user is not logged in, return all single memos without filtering
        if (!$userStaffId) {
            return $this->getAllSingleMemos($matrix, $request);
        }

        // Get user's workflow definition and approval order
        $userWorkflowDefinition = $this->getUserWorkflowDefinition($matrix, $userStaffId, $userDivisionId);
        
        // If no workflow definition found for user, return all single memos without filtering
        if (!$userWorkflowDefinition) {
            return $this->getAllSingleMemos($matrix, $request);
        }

        // Build single memos query with filtering - only show approved single memos
        $singleMemosQuery = $matrix->activities()
            ->where('is_single_memo', 1)
            ->where('overall_status', 'approved')
            ->with(['requestType', 'fundType', 'responsiblePerson', 'activity_budget', 'activity_budget.fundcode']);

        // Apply document number filter if provided
        if ($request->filled('document_number')) {
            $singleMemosQuery->where('document_number', 'like', '%' . $request->document_number . '%');
        }

        // Apply general search filter if provided
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $singleMemosQuery->where(function($query) use ($searchTerm) {
                $query->where('activity_title', 'like', '%' . $searchTerm . '%')
                      ->orWhere('document_number', 'like', '%' . $searchTerm . '%')
                      ->orWhere('background', 'like', '%' . $searchTerm . '%')
                      ->orWhere('activity_request_remarks', 'like', '%' . $searchTerm . '%')
                      ->orWhereHas('responsiblePerson', function($q) use ($searchTerm) {
                          $q->where('fname', 'like', '%' . $searchTerm . '%')
                            ->orWhere('lname', 'like', '%' . $searchTerm . '%');
                      })
                      ->orWhereHas('fundType', function($q) use ($searchTerm) {
                          $q->where('name', 'like', '%' . $searchTerm . '%');
                      });
            });
        }

        $perPage = $request->get('per_page', 20);
        $singleMemos = $singleMemosQuery->latest()->paginate($perPage);

        // Prepare additional decoded & related data per single memo
        foreach ($singleMemos as $memo) {
            // Decode JSON arrays
            $locationIds = is_array($memo->location_id)
                ? $memo->location_id
                : json_decode($memo->location_id ?? '[]', true);

            $internalRaw = is_string($memo->internal_participants)
                ? json_decode($memo->internal_participants ?? '[]', true)
                : ($memo->internal_participants ?? []);

            $internalParticipantIds = collect($internalRaw)->pluck('staff_id')->toArray();

            // Attach related models
            $memo->locations = Location::whereIn('id', $locationIds ?: [])->get();
            $memo->internalParticipants = Staff::whereIn('staff_id', $internalParticipantIds ?: [])->get();
            
            // Add approval-related data
            $memo->can_approve = can_approve_activity($memo);
            $memo->allow_print = allow_print_activity($memo);
            $memo->my_last_action = $memo->my_last_action ?? null;
            
            // Check if user's last action on this single memo was "passed"
            $memo->user_has_passed = $this->hasUserPassedActivity($memo, $userWorkflowDefinition);
        }

        return response()->json([
            'single_memos' => $singleMemos,
            'user_workflow_definition' => [
                'id' => $userWorkflowDefinition->id,
                'role' => $userWorkflowDefinition->role,
                'approval_order' => $userWorkflowDefinition->approval_order,
                'allowed_funders' => $userWorkflowDefinition->allowed_funders,
                'is_division_specific' => $userWorkflowDefinition->is_division_specific,
            ],
            'pagination' => [
                'current_page' => $singleMemos->currentPage(),
                'last_page' => $singleMemos->lastPage(),
                'per_page' => $singleMemos->perPage(),
                'total' => $singleMemos->total(),
                'from' => $singleMemos->firstItem(),
                'to' => $singleMemos->lastItem(),
            ]
        ]);
    }

    /**
     * Get all single memos without filtering (fallback for non-authenticated users)
     */
    private function getAllSingleMemos(Matrix $matrix, Request $request)
    {
        // Build single memos query without filtering - only show approved single memos
        $singleMemosQuery = $matrix->activities()
            ->where('is_single_memo', 1)
            ->where('overall_status', 'approved')
            ->with(['requestType', 'fundType', 'responsiblePerson', 'activity_budget', 'activity_budget.fundcode']);

        // Apply document number filter if provided
        if ($request->filled('document_number')) {
            $singleMemosQuery->where('document_number', 'like', '%' . $request->document_number . '%');
        }

        // Apply general search filter if provided
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $singleMemosQuery->where(function($query) use ($searchTerm) {
                $query->where('activity_title', 'like', '%' . $searchTerm . '%')
                      ->orWhere('document_number', 'like', '%' . $searchTerm . '%')
                      ->orWhere('background', 'like', '%' . $searchTerm . '%')
                      ->orWhere('activity_request_remarks', 'like', '%' . $searchTerm . '%')
                      ->orWhereHas('responsiblePerson', function($q) use ($searchTerm) {
                          $q->where('fname', 'like', '%' . $searchTerm . '%')
                            ->orWhere('lname', 'like', '%' . $searchTerm . '%');
                      })
                      ->orWhereHas('fundType', function($q) use ($searchTerm) {
                          $q->where('name', 'like', '%' . $searchTerm . '%');
                      });
            });
        }

        $perPage = $request->get('per_page', 20);
        $singleMemos = $singleMemosQuery->latest()->paginate($perPage);

        // Prepare additional decoded & related data per single memo
        foreach ($singleMemos as $memo) {
            // Decode JSON arrays
            $locationIds = is_array($memo->location_id)
                ? $memo->location_id
                : json_decode($memo->location_id ?? '[]', true);

            $internalRaw = is_string($memo->internal_participants)
                ? json_decode($memo->internal_participants ?? '[]', true)
                : ($memo->internal_participants ?? []);

            $internalParticipantIds = collect($internalRaw)->pluck('staff_id')->toArray();

            // Attach related models
            $memo->locations = Location::whereIn('id', $locationIds ?: [])->get();
            $memo->internalParticipants = Staff::whereIn('staff_id', $internalParticipantIds ?: [])->get();
            
            // Add approval-related data (default to false for non-authenticated users)
            $memo->can_approve = false;
            $memo->allow_print = false;
            $memo->my_last_action = null;
            $memo->user_has_passed = false;
        }

        return response()->json([
            'single_memos' => $singleMemos,
            'user_workflow_definition' => null,
            'pagination' => [
                'current_page' => $singleMemos->currentPage(),
                'last_page' => $singleMemos->lastPage(),
                'per_page' => $singleMemos->perPage(),
                'total' => $singleMemos->total(),
                'from' => $singleMemos->firstItem(),
                'to' => $singleMemos->lastItem(),
            ]
        ]);
    }

    /**
     * Get division staff data via AJAX for DataTables
     */
    public function getDivisionStaffAjax(Matrix $matrix, Request $request)
    {
        try {
            $search = $request->get('search', '');
            $page = $request->get('page', 1);
            $pageSize = $request->get('pageSize', 25);
            $start = ($page - 1) * $pageSize;

            // Get all division staff first to calculate summary statistics
            $allDivisionStaff = $matrix->division_staff;
            $quarter_year = $matrix->quarter . "-" . $matrix->year;

            // Build query for filtered staff
            // Exclude staff with status "Expired" and "Separated"
            $query = Staff::where('division_id', $matrix->division_id)
                ->whereNotIn('status', ['Expired', 'Separated']);

            // Apply search filter
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('fname', 'like', '%' . $search . '%')
                      ->orWhere('lname', 'like', '%' . $search . '%')
                      ->orWhere('job_name', 'like', '%' . $search . '%')
                      ->orWhere('duty_station_name', 'like', '%' . $search . '%');
                });
            }

            // Get total count
            $totalRecords = $query->count();

            // Get paginated data
            $divisionStaff = $query->skip($start)->take($pageSize)->get();

            // Calculate days for each staff member
            $staffData = [];

            foreach ($divisionStaff as $staff) {
                // Get participant data for this staff member for the current quarter only
                $participantSchedules = \App\Models\ParticipantSchedule::where('participant_id', $staff->staff_id)
                    ->where('international_travel', 1)
                    ->where('quarter', $matrix->quarter)
                    ->where('year', $matrix->year)
                    ->whereHas('activity', function($q) {
                        $q->where('overall_status', '!=', 'cancelled'); // Match staff activities filter
                    })
                    ->with('activity')
                    ->get();

                // Calculate division days (activities where participant is in their home division)
                $division_days = $participantSchedules->where('is_home_division', true)->sum('participant_days');

                // Other division days: only activities that are pending or approved (exclude draft, rejected)
                $other_days = $participantSchedules
                    ->where('is_home_division', false)
                    ->filter(function ($ps) {
                        $status = $ps->activity->overall_status ?? '';
                        return in_array($status, ['pending', 'approved']);
                    })
                    ->sum('participant_days');
                
                $total_days = $division_days + $other_days;
                $isOverLimit = $total_days > 21;

                $staffData[] = [
                    'staff_id' => $staff->staff_id,
                    'title' => $staff->title,
                    'fname' => $staff->fname,
                    'lname' => $staff->lname,
                    'job_name' => $staff->job_name,
                    'duty_station_name' => $staff->duty_station_name,
                    'division_days' => $division_days,
                    'other_days' => $other_days,
                    'total_days' => $total_days,
                    'is_over_limit' => $isOverLimit
                ];
            }

            // Calculate summary statistics from all division staff
            $totalStaff = $allDivisionStaff->count();
            $totalDivisionDays = 0;
            $overLimitCount = 0;

            foreach ($allDivisionStaff as $staff) {
                $participantSchedules = \App\Models\ParticipantSchedule::where('participant_id', $staff->staff_id)
                    ->where('international_travel', 1)
                    ->whereHas('activity', function($q) use ($matrix) {
                        $q->where('matrix_id', $matrix->id)
                          ->where('overall_status', '!=', 'cancelled'); // Match staff activities filter
                    })
                    ->with('activity')
                    ->get();

                $division_days = $participantSchedules->where('is_home_division', true)->sum('participant_days');
                // Other division days: only pending or approved activities (exclude draft, rejected)
                $other_days = $participantSchedules
                    ->where('is_home_division', false)
                    ->filter(function ($ps) {
                        $status = $ps->activity->overall_status ?? '';
                        return in_array($status, ['pending', 'approved']);
                    })
                    ->sum('participant_days');
                $total_days = $division_days + $other_days;

                $totalDivisionDays += $division_days;
                if ($total_days >= 21) {
                    $overLimitCount++;
                }
            }

            return response()->json([
                'data' => $staffData,
                'recordsTotal' => $totalRecords,
                'currentPage' => $page,
                'pageSize' => $pageSize,
                'totalPages' => ceil($totalRecords / $pageSize),
                'summary' => [
                    'total_staff' => $totalStaff,
                    'total_division_days' => $totalDivisionDays,
                    'over_limit_count' => $overLimitCount
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getDivisionStaffAjax: ' . $e->getMessage(), [
                'matrix_id' => $matrix->id,
                'error' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'An error occurred while loading staff data',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    

    /**
     * Show the form for editing the specified matrix.
     */
    public function edit(Matrix $matrix): View
    {
        // Only allow editing if matrix is in draft or returned status
        if (!in_array($matrix->overall_status, ['draft', 'returned'])) {
            return redirect()
                ->route('matrices.index')
                ->with('error', 'Only draft or returned matrices can be edited.');
        }
        $divisions = Division::all();
        $staff = Staff::active()->get();
        $focalPersons = $staff;
        $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
        $years = range(date('Y'), date('Y') + 5);
        
        // Calculate current and next quarter/year for one quarter ahead functionality
        $currentYear = date('Y');
        $currentMonth = date('n');
        $currentQuarter = 'Q' . ceil($currentMonth / 3);
        
        // Calculate next quarter and year
        $nextQuarter = '';
        $nextYear = $currentYear;
        
        switch ($currentQuarter) {
            case 'Q1':
                $nextQuarter = 'Q2';
                break;
            case 'Q2':
                $nextQuarter = 'Q3';
                break;
            case 'Q3':
                $nextQuarter = 'Q4';
                break;
            case 'Q4':
                $nextQuarter = 'Q1';
                $nextYear = $currentYear + 1;
                break;
        }
        
        // Create quarters array with current and next quarter
        $availableQuarters = [$currentQuarter];
        if ($nextQuarter) {
            $availableQuarters[] = $nextQuarter;
        }
        
        // Add next year to years array if not already present
        if (!in_array($nextYear, $years)) {
            $years[] = $nextYear;
            sort($years);
        }
    
        $staffByDivision = [];
        $divisionFocalPersons = [];
        $existingMatrices = [];
        $nextAvailableQuarters = [];
    
        foreach ($divisions as $division) {
            $divisionStaff = Staff::active()->where('division_id', $division->id)->get();
            $staffByDivision[$division->id] = $divisionStaff->pluck('id')->toArray();
            $divisionFocalPersons[$division->id] = $division->focal_person;
            
            // Get existing matrices for this division
            $existingMatrices[$division->id] = Matrix::getExistingMatricesForDivision($division->id);
            
            // Get next available quarter for current year
            $nextAvailableQuarters[$division->id] = Matrix::getNextAvailableQuarter($division->id, date('Y'));
        }
    
        // Ensure key_result_area is an array
        if (is_string($matrix->key_result_area)) {
            $decoded = json_decode($matrix->key_result_area, true);
            $matrix->key_result_area = is_array($decoded) ? $decoded : [];
        }
    
        // Save division name in session for breadcrumb use
        session()->put('division_name', user_session('division_name'));
    
        return view('matrices.create', [
            'matrix' => $matrix, // Pass the matrix for editing
            'editing' => true, // Flag to indicate we're editing
            'divisions' => $divisions,
            'title' => user_session('division_name'),
            'module' => 'Quarterly Matrix',
            'staff' => $staff,
            'quarters' => $availableQuarters, // Only show current and next quarter
            'years' => $years,
            'focalPersons' => $focalPersons,
            'staffByDivision' => $staffByDivision,
            'divisionFocalPersons' => $divisionFocalPersons,
            'existingMatrices' => $existingMatrices,
            'nextAvailableQuarters' => $nextAvailableQuarters,
            'currentQuarter' => $currentQuarter,
            'nextQuarter' => $nextQuarter,
            'currentYear' => $currentYear,
            'nextYear' => $nextYear,
        ]);
    }
    
    

    /**
     * Update the specified matrix.
     */
    public function update(Request $request, Matrix $matrix): RedirectResponse
    {
        // Only allow updating if matrix is in draft or returned status
        if (!in_array($matrix->overall_status, ['draft', 'returned'])) {
            return redirect()
                ->route('matrices.index')
                ->with('error', 'Only draft or returned matrices can be updated.');
        }

        $isAdmin = session('user.user_role') == 10;
        $userDivisionId = session('user.division_id');
        $userStaffId = session('user.auth_staff_id');
    
        // Validate basic fields
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2030',
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

        // Check if a matrix already exists for this division, year, and quarter (excluding current matrix)
        if (Matrix::existsForDivisionYearQuarter($validated['division_id'], $validated['year'], $validated['quarter'], $matrix->id)) {
            return redirect()->back()
                ->withInput()
                ->withErrors([
                    'quarter' => 'A matrix already exists for this division in ' . $validated['year'] . ' ' . $validated['quarter'] . '. Only one matrix per division per quarter is allowed.'
                ]);
        }

        $this->updateMatrix($matrix,$request,$validated);

        return redirect()->route('matrices.index')->with([
            'msg' => 'Matrix updated successfully.',
            'type' => 'success'
        ]);
        
    }

    public function updateMatrix($matrix,$request,$validated=null){

        $last_workflow_id=null;
        $last_approval_order=$matrix->approval_level;
        $overall_status = $matrix->overall_status;

        $last_approval_trail = ApprovalTrail::where('model_id',$matrix->id)->where('model_type', Matrix::class)->whereNotIn('action',['approved','submitted'])->orderByDesc('id')->first();

        // Get assigned workflow ID for Matrix model when submitting for approval (before saving trail so trail has correct forward_workflow_id)
        $assignedWorkflowId = $last_workflow_id;
        if($request->action == 'approvals'){
            if($last_approval_trail){
                $workflow_defn       = WorkflowDefinition::where('approval_order', $last_approval_trail->approval_order)->first();
                $last_workflow_id    = $workflow_defn->workflow_id??1;
                $last_approval_order = $last_approval_trail->approval_order;
                $assignedWorkflowId  = $last_workflow_id;
            }
            else
                $last_approval_order=1;

            if ($assignedWorkflowId == null) {
                $assignedWorkflowId = WorkflowModel::getWorkflowIdForModel('Matrix');
                if (!$assignedWorkflowId) {
                    $assignedWorkflowId = 1; // Default workflow ID
                    Log::warning('No workflow assignment found for Matrix model, using default workflow ID: 1');
                }
            }
            // Set on matrix before saveMatrixTrail so the submitted trail gets the correct forward_workflow_id (fixes dashboard "No data" for avg time)
            $matrix->forward_workflow_id = $assignedWorkflowId;
            $overall_status = 'pending';
            $this->saveMatrixTrail($matrix,'Submitted for approval','submitted');
        }

        $update_data = [
            'staff_id'            => $matrix->staff_id ?? user_session('staff_id'),
            'forward_workflow_id' => $assignedWorkflowId,
            'approval_level' => $last_approval_order ?? 1,
            'overall_status' => $overall_status
        ];

        if($validated){
            $update_data['division_id'] = $validated['division_id'];
            $update_data['focal_person_id'] = $validated['focal_person_id'];
            $update_data['year']    = $validated['year'];
            $update_data['quarter'] = $validated['quarter'];
            $update_data['key_result_area'] = json_encode($validated['key_result_area']);
        }

        // Update matrix
        $matrix->update($update_data);
        
        // Update approval order map
        $approvalService = new \App\Services\ApprovalService();
        $approvalService->updateApprovalOrderMap($matrix);
        
        send_generic_email_notification($matrix, 'approval');
    }

    public function request_approval(Request $request, Matrix $matrix){

        // Check if this is HOD resubmission with comment
        $hodComment = $request->input('hod_comment');
        $focalPersonComment = $request->input('focal_person_comment');
        
        if ($hodComment) {
            // This is HOD resubmission, save the comment in the approval trail
            $this->saveMatrixTrail($matrix, $hodComment, 'submitted');
        } elseif ($focalPersonComment) {
            // This is focal person resubmission after return, save the comment in the approval trail
            $this->saveMatrixTrail($matrix, $focalPersonComment, 'submitted');
        }
        
        $this->updateMatrix($matrix,(Object)['action'=>'approvals'],null);
        // Notification is already sent in updateMatrix method
        
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
        // Only allow deletion if matrix is in draft or returned status
        if (!in_array($matrix->overall_status, ['draft', 'returned'])) {
            return redirect()
                ->route('matrices.index')
                ->with('error', 'Only draft or returned matrices can be deleted.');
        }

        $matrix->delete();

        return redirect()
            ->route('matrices.index')
            ->with('success', 'Matrix deleted successfully.');
    }

    public function update_status(Request $request, Matrix $matrix): RedirectResponse
    {
        $request->validate(['action' => 'required']);

        $approvalService = new ApprovalService();
        $userStaffId = user_session('staff_id');

        // Guard: only the current approver for this level can take this action (prevents approving for wrong level)
        if (!$approvalService->canTakeAction($matrix, (int) $userStaffId)) {
            return redirect()
                ->route('matrices.show', [$matrix])
                ->with('error', 'You are not the current approver for this level, or this action is not allowed.');
        }

        // Guard: idempotency – avoid duplicate trail when same user submits same action for this level (e.g. double-click / poor network)
        $recentDuplicate = ApprovalTrail::where('model_id', $matrix->id)
            ->where('model_type', Matrix::class)
            ->where('staff_id', $userStaffId)
            ->where('approval_order', $matrix->approval_level)
            ->where('action', $request->action)
            ->where('is_archived', 0)
            ->where('created_at', '>=', now()->subMinutes(2))
            ->exists();

        if ($recentDuplicate) {
            return redirect()
                ->route('matrices.show', [$matrix])
                ->with('success', 'Your action was already recorded. No duplicate was created.');
        }

        $next_approval_point = $approvalService->getNextApprover($matrix);

        $this->saveMatrixTrail($matrix, $request->comment, $request->action);
       // dd($request->action);
        $notification_type =null;

        if($request->action !=='approved'){
         
            $matrix->forward_workflow_id = (intval($matrix->approval_level)==1)?null:1;
            $matrix->approval_level = ($matrix->approval_level==1)?0:1;
            $matrix->overall_status ='returned';
           // dd('here1')
;            // Archive approval trails to restart approval process
            archive_approval_trails($matrix);
            
            //notify and save notification
            $notification_type = 'returned';
        }else{
   
          //dd('here2');
            //move to next
            $approvalservice = new ApprovalService();
            $next_approval_point = $approvalservice->getNextApprover($matrix);
            //$next_approval_point;
         
           
           if($next_approval_point){

            $matrix->forward_workflow_id = $next_approval_point->workflow_id;
            $matrix->approval_level = $next_approval_point->approval_order;
            $matrix->next_approval_level = $next_approval_point->approval_order;
            $matrix->overall_status = 'pending';
            
            // Update all activities' overall_status to 'pending' when matrix moves to next approval level
            $matrix->activities()->where('is_single_memo', 0)->update(['overall_status' => 'pending']);

            //notify and save notification
            $notification_type = 'approval';
           }
           else{
            //no more approval levels
            $matrix->overall_status = 'approved';
            $notification_type = 'approved';
            
            // Update all activities' overall_status to 'approved' when matrix is approved
            $matrix->activities()->where('is_single_memo', 0)->update(['overall_status' => 'approved']);
           }
        }
        
        $matrix->update();
        
        // Update approval order map
        $approvalService = new \App\Services\ApprovalService();
        $approvalService->updateApprovalOrderMap($matrix);

        //notify and save notification
        send_generic_email_notification($matrix, $notification_type);
        $message = "Matrix Updated successfully";

        return redirect()
        ->route('matrices.show', [$matrix])
        ->with('success', $message);

    }

    private function saveMatrixTrail($matrix,$comment,$action){
//dd($matrix,$comment,$action);
        $matrixTrail = new ApprovalTrail();
        $matrixTrail->remarks  = $comment;
        $matrixTrail->action   = $action;
        $matrixTrail->model_id   = $matrix->id;
        $matrixTrail->forward_workflow_id = $matrix->forward_workflow_id;
        $matrixTrail->model_type = Matrix::class;
        $matrixTrail->matrix_id   = $matrix->id; // For backward compatibility
        $matrixTrail->approval_order   = ($matrix->approval_level==0||$action=='submitted')?0:$matrix->approval_level;
        $matrixTrail->staff_id = user_session('staff_id');
        $matrixTrail->is_archived = 0; // Explicitly set as non-archived
        $matrixTrail->save();
        //dd($matrixTrail);

        mark_matrix_notifications_read(user_session('staff_id'), $matrix->id);
    }

    private function get_next_approver($matrix){

        $division   = $matrix->division;

        ///dd($division);

        $current_definition = WorkflowDefinition::where('workflow_id',$matrix->forward_workflow_id)
           ->where('is_enabled',1)
           ->where('approval_order',$matrix->approval_level)
           ->first();

        $nextStepIncrement = 1;
        $go_to_category_check_for_external =(!$matrix->has_extramural && !$matrix->has_intramural && ($matrix->approval_level!=null && $current_definition->approval_order > $matrix->approval_level));

       /// dd($go_to_category_check_for_external);

         //triger checks at piu, grants, finance
         //skip Director finance if approver at approval order 4 or 5 extramural or intramural activities are oved

        //Skip Directorate from HOD if no directorate && skips programs and operations to go to other
        if(($matrix->forward_workflow_id>0 && $current_definition->approval_order==1 && !$division->director_id)|| ($current_definition && $current_definition->triggers_category_check && $division->category=='Other')){
            $nextStepIncrement = 2;
        }
          
        //dd($nextStepIncrement);
       
        //if it's time to trigger categroy check, just check and continue
        if(($current_definition && $current_definition->triggers_category_check && $division->category!='Other')||$go_to_category_check_for_external){
    
          //dd($category_definition)
        $category_definition = WorkflowDefinition::where('workflow_id',$matrix->forward_workflow_id)
            ->where('is_enabled',1)
            ->where('category',$division->category)
            ->orderBy('approval_order','asc')
            ->first();

        return $category_definition;

        }

      
       

   

         if(!$matrix->forward_workflow_id) { // null
            // Get assigned workflow ID for Matrix model
            $assignedWorkflowId = WorkflowModel::getWorkflowIdForModel('Matrix');
            if (!$assignedWorkflowId) {
                $assignedWorkflowId = 1; // Default workflow ID
                Log::warning('No workflow assignment found for Matrix model in workflow processing, using default workflow ID: 1');
            }
            $matrix->forward_workflow_id = $assignedWorkflowId;
        }
   
        $next_definition = WorkflowDefinition::where('workflow_id',$matrix->forward_workflow_id)
           ->where('is_enabled',1)
           ->where('approval_order',$matrix->approval_level +$nextStepIncrement)->get();

      //dd($next_definition);
            
        //if matrix has_extramural is true and matrix->approval_level !==definition_approval_order, 
        // get from $definition where fund_type=2, else where fund_type=2
        //if one, just return the one available
        if ($next_definition->count() > 1) {

            if ($matrix->has_extramural && $matrix->approval_level !== $next_definition->first()->approval_order) {
                return $next_definition->where('fund_type', 2);
            } 
            else {
                return $next_definition->where('fund_type', 1);
            }
        }

        $definition = ($next_definition->count()>0)?$next_definition[0]:null;
        //dd($definition);
        //intramural only, skip extra mural role
        if($definition  && !$matrix->has_extramural &&  $definition->fund_type==2){
          return WorkflowDefinition::where('workflow_id',$matrix->forward_workflow_id)
            ->where('is_enabled',1)
            ->where('approval_order',$definition->approval_order+1)->first();
        }

        //only extramural, skip by intramural roles
        if($definition  && !$matrix->has_intramural &&  $definition->fund_type==1){
            return WorkflowDefinition::where('workflow_id',$matrix->forward_workflow_id)
              ->where('is_enabled',1)
              ->where('approval_order', $definition->approval_order+2)->first();
        }

      // dd($nextStepIncrement);
        return  $definition;

    }

     /**
     * Determine the next approver in the workflow based on the approval process diagram.
     * 
     * WORKFLOW STEPS (Based on Approval Image):
     * Step 0: Staff Uploads activity by Division
     * Step 1: HOD Reviews the Activity
     * Step 2: Director Reviews the Activity (if division has directorate)
     * Step 3: Fund Source Check (Gavi/CEPI/WB vs External)
     * Step 4: Finance Officer (Intramural only)
     * Step 5: Director Finance (Intramural & Extramural)
     * Step 6: Division Category Check (Operations/Programs/Other)
     * Step 7: Head Operations/Programs (based on category)
     * Step 8: DDG (Deputy Director General)
     * Step 9: COP (Chief of Programs)
     * Step 10: DG (Director General)
     * Step 11: Registry (Final registration)
     * 
     * RETURN LOGIC (From Image Notes):
     * - A return at any level will be redirected back to HOD
     * - HOD can either put a comment and send it back to the level where it was returned from
     * - Or send it back to draft for changes to be applied
     * - Once an activity goes back to draft stage, it has to follow the workflow again
     * 
     * @param Matrix $matrix The matrix being processed
     * @return WorkflowDefinition|null The next workflow definition to process
     */
    // private function get_next_approver($matrix)
    // {
    //     // Input validation
    //     if (!$matrix || !$matrix->division) {
    //         Log::error('Invalid matrix or division in get_next_approver');
    //         return null;
    //     }

    //     $division = $matrix->division;
    //     $approvalLevel = (int) ($matrix->approval_level ?? 0);
        
    //     // Determine funding types based on matrix properties
    //     // Note: These values can change dynamically as activities are processed
    //     $hasIntra = (bool) ($matrix->has_intramural ?? false);
    //     $hasExtra = (bool) ($matrix->has_extramural ?? false);
    //     $isExternal = (!$hasIntra && !$hasExtra); // External source if neither intra nor extra
        
    //     // Dynamic funding status check: Re-evaluate funding status at each level
    //     // This handles cases where activities are removed during the workflow
    //     if ($approvalLevel >= 3) {
    //         // Refresh the matrix to get the latest funding status
    //         $matrix->refresh();
            
    //         // Recalculate funding status to detect changes
    //         $currentHasIntra = (bool) ($matrix->has_intramural ?? false);
    //         $currentHasExtra = (bool) ($matrix->has_extramural ?? false);
    //         $currentIsExternal = (!$currentHasIntra && !$currentHasExtra);
            
    //         // If funding status changed to external, update the flags
    //         if ($currentIsExternal && !$isExternal) {
    //             $isExternal = true;
    //             $hasIntra = false;
    //             $hasExtra = false;
                
    //             // Log the change for debugging
    //             Log::info("Funding status changed to external at level {$approvalLevel} for matrix {$matrix->id}", [
    //                 'previous_status' => 'had_funding',
    //                 'current_status' => 'external',
    //                 'approval_level' => $approvalLevel
    //             ]);
    //         }
            
    //         // If funding status changed from external to having funding, update the flags
    //         if (!$currentIsExternal && $isExternal) {
    //             $isExternal = false;
    //             $hasIntra = $currentHasIntra;
    //             $hasExtra = $currentHasExtra;
                
    //             // Log the change for debugging
    //             Log::info("Funding status changed from external at level {$approvalLevel} for matrix {$matrix->id}", [
    //                 'previous_status' => 'external',
    //                 'current_status' => 'has_funding',
    //                 'has_intramural' => $hasIntra,
    //                 'has_extramural' => $hasExtra,
    //                 'approval_level' => $approvalLevel
    //             ]);
    //         }
    //     }
        
    //     // Debug logging (commented out due to permission issues)
    //     // Log::info("Matrix {$matrix->id} workflow check", [
    //     //     'approval_level' => $approvalLevel,
    //     //     'has_intramural' => $hasIntra,
    //     //     'has_extramural' => $hasExtra,
    //     //     'is_external' => $isExternal,
    //     //     'division_category' => $division->category ?? 'null',
    //     //     'division_director_id' => $division->director_id ?? 'null'
    //     // ]);
        
    //     // Debug output for testing (remove in production)
    //     // echo "DEBUG: Matrix {$matrix->id} - Level: {$approvalLevel}, HasIntra: " . ($hasIntra ? 'true' : 'false') . ", HasExtra: " . ($hasExtra ? 'true' : 'false') . ", IsExternal: " . ($isExternal ? 'true' : 'false') . ", Category: " . ($division->category ?? 'null') . PHP_EOL;

    //     // Ensure workflow ID is set - get from matrix's workflow_id property or use default
    //     if (!$matrix->forward_workflow_id) {
    //         $assignedWorkflowId = $matrix->workflow_id ?? 1; // Use matrix's workflow_id or default to 1
    //         if (!$assignedWorkflowId) {
    //             $assignedWorkflowId = 1;
    //             Log::warning('No workflow assignment found for Matrix; using default workflow_id=1');
    //         }
    //         $matrix->forward_workflow_id = $assignedWorkflowId;
    //     }

    //     // Helper function to get workflow definition by order, fund type, and category
    //     $pick = function (int $order, ?int $fundType = null, ?string $category = null) use ($matrix) {
    //         $query = WorkflowDefinition::where('workflow_id', $matrix->forward_workflow_id)
    //             ->where('is_enabled', 1)
    //             ->where('approval_order', $order);

    //         if ($fundType !== null) $query->where('fund_type', $fundType); // 1=intramural, 2=extramural, 3=external
    //         if ($category !== null) $query->where('category', $category);

    //         return $query->first();
    //     };

    //     // Helper function to get first category-based approver
    //     $pickFirstCategoryNode = function (?string $category) use ($matrix, $pick, $approvalLevel) {
    //         $cat = $category ?: 'Other';
            
    //         // Simple and elegant: Find workflow definition that matches the division category
    //         // This approach is scalable and doesn't require hardcoded logic for each category
    //         $definition = WorkflowDefinition::where('workflow_id', $matrix->forward_workflow_id)
    //             ->where('is_enabled', 1)
    //             ->where('category', $cat)
    //             ->where('approval_order', '>', $approvalLevel) // Only look for next level, not current
    //             ->orderBy('approval_order', 'asc')
    //        ->first();

    //         // If no category-specific approver found, find the next available approval order
    //         if (!$definition) {
    //             $definition = WorkflowDefinition::where('workflow_id', $matrix->forward_workflow_id)
    //                 ->where('is_enabled', 1)
    //                 ->where('approval_order', '>', $approvalLevel)
    //                 ->orderBy('approval_order', 'asc')
    //                 ->first();
    //         }
            
    //         return $definition;
    //     };

    //     // Get current workflow definition
    //     $current_definition = $approvalLevel > 0
    //         ? WorkflowDefinition::where('workflow_id', $matrix->forward_workflow_id)
    //             ->where('is_enabled', 1)
    //             ->where('approval_order', $approvalLevel)
    //             ->first()
    //         : null;

    //     // STEP 1: HOD Review Logic
    //     // If at HOD level (approval_order = 1), check if we should skip directorate
    //     if ($approvalLevel == 1) {
    //         // Special case: If division category is 'Other', go directly to DDG who doubles as Head of Other (order 9)
    //         // This takes priority over all funding type checks
    //         if ($division->category === 'Other') {
    //             $definition = $pickFirstCategoryNode($division->category);
    //             if ($definition) return $definition;
    //         }
            
    //         // For external source, first check if division has director
    //         if ($isExternal) {
    //             // Check if division has directorate (null or 0 means no director)
    //             if ($division->director_id === null || $division->director_id == 0) {
    //                 // No director - go directly to division category check
    //                 $definition = $pickFirstCategoryNode($division->category ?? null);
    //                 if ($definition) return $definition;
    //             } else {
    //                 // Has director - proceed to Director step (order 2)
    //                 $directorStep = WorkflowDefinition::where('workflow_id', $matrix->forward_workflow_id)
    //                     ->where('is_enabled', 1)
    //                     ->where('approval_order', 2)
    //                     ->first();
                        
    //                 if ($directorStep) {
    //                     return $directorStep;
    //                 } else {
    //                     // No Director step in workflow - go to division category check
    //                     $definition = $pickFirstCategoryNode($division->category ?? null);
    //                     if ($definition) return $definition;
                        
    //                     // If no category-specific approver found, go to next available step
    //                     $definition = WorkflowDefinition::where('workflow_id', $matrix->forward_workflow_id)
    //                         ->where('is_enabled', 1)
    //                         ->where('approval_order', '>', $approvalLevel)
    //                         ->orderBy('approval_order', 'asc')
    //         ->first();
    //                     if ($definition) return $definition;
                        
    //                     // If no next step found due to activity changes, check division category again
    //                     $definition = $pickFirstCategoryNode($division->category ?? null);
    //                     if ($definition) return $definition;
    //                 }
    //             }
    //         }
            
    //         // For non-external sources, check if division has directorate (null or 0 means no director)
    //         if ($division->director_id === null || $division->director_id == 0) {
    //             // No directorate - skip to next available step after Director (order 2)
    //             // But first check fund types to route correctly
    //             if ($hasIntra && !$hasExtra) {
    //                 // Intramural: skip Director, go to PIU Officer (4)
    //                 $definition = $pick(4, 1);
    //                 if ($definition) return $definition;
    //             }
                
    //             if ($hasExtra && !$hasIntra) {
    //                 // Extramural: skip Director, go to Grants Officer (3)
    //                 $definition = $pick(3, 2);
    //                 if ($definition) return $definition;
    //             }
                
    //             if ($hasIntra && $hasExtra) {
    //                 // Mixed funding: skip Director, start with Grants Officer (3)
    //                 $definition = $pick(3, 2);
    //                 if ($definition) return $definition;
    //             }
                
    //         // Fallback - go to next available step after Director
    //         $definition = WorkflowDefinition::where('workflow_id', $matrix->forward_workflow_id)
    //             ->where('is_enabled', 1)
    //             ->where('approval_order', '>', 2) // Skip Director step (order 2)
    //             ->orderBy('approval_order', 'asc')
    //             ->first();
    //         if ($definition) return $definition;
            
    //         // If no next step found due to activity changes, check division category
    //         $definition = $pickFirstCategoryNode($division->category ?? null);
    //         if ($definition) return $definition;
    //         }
            
    //         // Has directorate - check if there's a Director step (order 2)
    //         $directorStep = WorkflowDefinition::where('workflow_id', $matrix->forward_workflow_id)
    //             ->where('is_enabled', 1)
    //             ->where('approval_order', 2)
    //             ->first();
                
    //         if ($directorStep) {
    //             // This workflow has a Director step - proceed to it
    //             return $directorStep;
    //         } else {
    //             // This workflow doesn't have a Director step - go to next available step
    //             $definition = WorkflowDefinition::where('workflow_id', $matrix->forward_workflow_id)
    //                 ->where('is_enabled', 1)
    //                 ->where('approval_order', '>', $approvalLevel)
    //                 ->orderBy('approval_order', 'asc')
    //                 ->first();
    //             if ($definition) return $definition;
                
    //             // If no next step found due to activity changes, check division category
    //             $definition = $pickFirstCategoryNode($division->category ?? null);
    //             if ($definition) return $definition;
    //         }
    //     }

    //     // STEP 2: Directorate Check
    //     // If at Director level (approval_order = 2), perform all funding type checks like HOD level
    //     if ($approvalLevel == 2) {
    //         // Perform the same funding type checks as HOD level, but without director existence check
            
    //         // For external source, go directly to division category check
    //         if ($isExternal) {
    //             $definition = $pickFirstCategoryNode($division->category ?? null);
    //             if ($definition) return $definition;
    //         }
            
    //         // For intramural only
    //         if ($hasIntra && !$hasExtra) {
    //             // Intramural: PIU Officer (4) -> Finance Officer (5) -> Director Finance (6)
    //             $definition = $pick(4, 1); // PIU Officer for intramural
    //             if ($definition) return $definition;
    //         }

    //         // For extramural only
    //         if ($hasExtra && !$hasIntra) {
    //             // Extramural: Grants Officer (3) -> Director Finance (6) (skips Finance Officer)
    //             $definition = $pick(3, 2); // Grants Officer for extramural
    //             if ($definition) return $definition;
    //         }

    //         // For mixed funding
    //         if ($hasIntra && $hasExtra) {
    //             // Mixed funding: Both Grants and PIU Officer need to review
    //             // Start with Grants Officer (3) for extramural activities first
    //             $definition = $pick(3, 2); // Grants Officer for extramural
    //             if ($definition) return $definition;
    //         }

    //     // Fallback - go to next available step
    //     $definition = WorkflowDefinition::where('workflow_id', $matrix->forward_workflow_id)
    //         ->where('is_enabled', 1)
    //         ->where('approval_order', '>', $approvalLevel)
    //         ->orderBy('approval_order', 'asc')
    //         ->first();
    //     if ($definition) return $definition;
        
    //     // If no next step found due to activity changes, check division category
    //     $definition = $pickFirstCategoryNode($division->category ?? null);
    //     if ($definition) return $definition;
    //     }

    //     // STEP 3: Fund Source Split (Grants Officer)
    //     // After Grants Officer (approval_order = 3)
    //     if ($approvalLevel == 3) {
    //         // PIU/Grants Officer can remove activities - check for funding status changes
    //         $matrix->refresh();
    //         $currentHasIntra = (bool) ($matrix->has_intramural ?? false);
    //         $currentHasExtra = (bool) ($matrix->has_extramural ?? false);
    //         $currentIsExternal = (!$currentHasIntra && !$currentHasExtra);
            
    //         // If funding status changed to external, go to category check
    //         if ($currentIsExternal) {
    //             // Log::info("PIU/Grants Officer removed all activities - switching to external source for matrix {$matrix->id}");
    //             $definition = $pickFirstCategoryNode($division->category ?? null);
    //             return $definition;
    //         }
            
    //         // Update funding flags based on current status
    //         $hasIntra = $currentHasIntra;
    //         $hasExtra = $currentHasExtra;
    //         $isExternal = $currentIsExternal;
            
    //         // Check if we have mixed funding (both intramural and extramural)
    //         if ($hasIntra && $hasExtra) {
    //             // Mixed funding: Go to PIU Officer (4) for intramural activities
    //             $definition = $pick(4, 1); // PIU Officer for intramural
    //             if ($definition) return $definition;
    //         }
            
    //     // For extramural only, go to Director Finance (6)
    //     $definition = $pick(6);
    //     if ($definition) return $definition;
        
    //     // If no specific approver found due to activity changes, check division category
    //     $definition = $pickFirstCategoryNode($division->category ?? null);
    //     if ($definition) return $definition;
    //     }

    //     // STEP 4: PIU Officer (Intramural only)
    //     // After PIU Officer (approval_order = 4), check if intramural activities were removed
    //     if ($approvalLevel == 4) {
    //         // PIU Officer can remove activities - check for funding status changes
    //         $matrix->refresh();
    //         $currentHasIntra = (bool) ($matrix->has_intramural ?? false);
    //         $currentHasExtra = (bool) ($matrix->has_extramural ?? false);
    //         $currentIsExternal = (!$currentHasIntra && !$currentHasExtra);
            
    //         // If funding status changed to external, go to category check
    //         if ($currentIsExternal) {
    //             // Log::info("PIU Officer removed all activities - switching to external source for matrix {$matrix->id}");
    //             $definition = $pickFirstCategoryNode($division->category ?? null);
    //             return $definition;
    //         }
            
    //         // Update funding flags based on current status
    //         $hasIntra = $currentHasIntra;
    //         $hasExtra = $currentHasExtra;
    //         $isExternal = $currentIsExternal;
            
    //     // Go to Finance Officer (5) for intramural activities
    //     $definition = $pick(5, 1); // Finance Officer for intramural
    //     if ($definition) return $definition;
        
    //     // If no Finance Officer found due to activity changes, check division category
    //     $definition = $pickFirstCategoryNode($division->category ?? null);
    //     if ($definition) return $definition;
    //     }

    //     // STEP 5: Finance Officer (Intramural only)
    //     // After Finance Officer (approval_order = 5), check if intramural activities were removed
    //     if ($approvalLevel == 5) {
    //         // Finance Officer can remove activities - check for funding status changes
    //         $matrix->refresh();
    //         $currentHasIntra = (bool) ($matrix->has_intramural ?? false);
    //         $currentHasExtra = (bool) ($matrix->has_extramural ?? false);
    //         $currentIsExternal = (!$currentHasIntra && !$currentHasExtra);
            
    //         // If funding status changed to external, go to category check
    //         if ($currentIsExternal) {
    //             // Log::info("Finance Officer removed all activities - switching to external source for matrix {$matrix->id}");
    //             $definition = $pickFirstCategoryNode($division->category ?? null);
    //             return $definition;
    //         }
            
    //         // Update funding flags based on current status
    //         $hasIntra = $currentHasIntra;
    //         $hasExtra = $currentHasExtra;
    //         $isExternal = $currentIsExternal;
            
    //     // If intramural activities still exist, go to Director Finance (6)
    //     $definition = $pick(6);
    //     if ($definition) return $definition;
        
    //     // If no Director Finance found due to activity changes, check division category
    //     $definition = $pickFirstCategoryNode($division->category ?? null);
    //     if ($definition) return $definition;
    //     }

    //     // STEP 6: Director Finance
    //     // After Director Finance (approval_order = 6), go to division category check
    //     if ($approvalLevel == 6) {
    //         // Director Finance can remove activities - check for funding status changes
    //         $matrix->refresh();
    //         $currentHasIntra = (bool) ($matrix->has_intramural ?? false);
    //         $currentHasExtra = (bool) ($matrix->has_extramural ?? false);
    //         $currentIsExternal = (!$currentHasIntra && !$currentHasExtra);
            
    //         // Update funding flags based on current status
    //         $hasIntra = $currentHasIntra;
    //         $hasExtra = $currentHasExtra;
    //         $isExternal = $currentIsExternal;
            
    //         // Go to division category check
    //         $definition = $pickFirstCategoryNode($division->category ?? null);
    //         return $definition;
    //     }

    //     // STEP 6: Division Category Check
    //     // Check if we should trigger category check based on current definition
    //     $shouldCategoryCheck = ($current_definition && $current_definition->triggers_category_check) 
    //         || ($isExternal && $approvalLevel >= 2);

    //     if ($shouldCategoryCheck) {
    //         // Log::info("Triggering category check for matrix {$matrix->id}", [
    //         //     'category' => $division->category ?? 'null',
    //         //     'approval_level' => $approvalLevel,
    //         //     'is_external' => $isExternal
    //         // ]);
    //         $definition = $pickFirstCategoryNode($division->category ?? null);
    //         // Log::info("Category check result for matrix {$matrix->id}", [
    //         //     'found_definition' => $definition ? $definition->role : 'null',
    //         //     'approval_order' => $definition ? $definition->approval_order : 'null'
    //         // ]);
    //         return $definition;
    //     }

    //     // Additional check: If external source and at any level after Director, go to category check
    //     if ($isExternal && $approvalLevel > 2) {
    //         $definition = $pickFirstCategoryNode($division->category ?? null);
    //         return $definition;
    //     }

    //     // Special case: If at Finance Officer level (4) and no intramural activities remain,
    //     // treat as external source and go to category check
    //     if ($approvalLevel == 4 && !$hasIntra && !$hasExtra) {
    //         $definition = $pickFirstCategoryNode($division->category ?? null);
    //         return $definition;
    //     }

    //     // STEP 7-11: Final Approval Chain (Head Operations/Programs -> DDG -> COP -> DG -> Registry)
    //     // At level 7, use category-based routing to find the correct approver
    //     if ($approvalLevel == 7) {
    //         $definition = $pickFirstCategoryNode($division->category ?? null);
    //         if ($definition) {
    //             return $definition;
    //         }
    //     }
        
    //     // Find the next available approval order
    //     $definition = WorkflowDefinition::where('workflow_id', $matrix->forward_workflow_id)
    //         ->where('is_enabled', 1)
    //         ->where('approval_order', '>', $approvalLevel)
    //         ->orderBy('approval_order', 'asc')
    //         ->first();

    //     if ($definition) {
    //         // External: skip finance/PIU/Grants nodes (fund_type 1/2) -> jump to category
    //         if ($isExternal && in_array((int)$definition->fund_type, [1, 2])) {
    //             $definition = $pickFirstCategoryNode($division->category ?? null);
    //             return $definition;
    //         }

    //         // Intramural only -> skip extramural row
    //         if ($hasIntra && !$hasExtra && (int)$definition->fund_type == 2) {
    //             // Find next approver after skipping extramural
    //             $nextDefinition = WorkflowDefinition::where('workflow_id', $matrix->forward_workflow_id)
    //                 ->where('is_enabled', 1)
    //                 ->where('approval_order', '>', $definition->approval_order)
    //                 ->orderBy('approval_order', 'asc')
    //                 ->first();
                
    //             if ($nextDefinition) {
    //                 return $nextDefinition;
    //             } else {
    //                 return $pickFirstCategoryNode($division->category ?? null);
    //             }
    //         }

    //         // Extramural only -> skip intramural row
    //         if ($hasExtra && !$hasIntra && (int)$definition->fund_type == 1) {
    //             // Find next approver after skipping intramural
    //             $nextDefinition = WorkflowDefinition::where('workflow_id', $matrix->forward_workflow_id)
    //                 ->where('is_enabled', 1)
    //                 ->where('approval_order', '>', $definition->approval_order)
    //                 ->orderBy('approval_order', 'asc')
    //                 ->first();
                
    //             if ($nextDefinition) {
    //                 return $nextDefinition;
    //             } else {
    //                 return $pickFirstCategoryNode($division->category ?? null);
    //             }
    //         }
    //     }

    //     // Generic fallback for any workflow that doesn't match the specific patterns above
    //     // This handles simple sequential workflows like ARF (workflow_id=2) or Service Requests (workflow_id=3)
    //     $definition = WorkflowDefinition::where('workflow_id', $matrix->forward_workflow_id)
    //         ->where('is_enabled', 1)
    //         ->where('approval_order', '>', $approvalLevel)
    //         ->orderBy('approval_order', 'asc')
    //         ->first();

    //     return $definition; // null if end (e.g., after Registry)
    // }



    /**
     * Display pending approvals for the current user.
     */
    public function pendingApprovals(Request $request): View
    {
        $userStaffId = user_session('staff_id');

        // Check if we have valid session data
        if (!$userStaffId) {
            return view('matrices.pending-approvals', [
                'pendingMatrices' => collect(),
                'approvedByMe' => collect(),
                'divisions' => collect(),
                'focalPersons' => collect(),
                'error' => 'No session data found. Please log in again.'
            ]);
        }

        // Copy the working logic from index method for pending approvals
        $query = Matrix::with([
            'division',
            'staff',
            'focalPerson',
            'forwardWorkflow',
            'activities' => function ($q) {
                $q->select('id', 'matrix_id', 'activity_title', 'total_participants', 'budget_breakdown')
                  ->whereNotNull('matrix_id');
            }
        ]);

        // Only show pending matrices (not draft)
        $query->where('overall_status', 'pending')
              ->where('forward_workflow_id', '!=', null)
              ->where('approval_level', '>', 0);

        // Apply the same filtering logic as index method
        $userDivisionId = user_session('division_id');
        
        $query->where(function($q) use ($userDivisionId, $userStaffId) {
            // Case 1: Division-specific approval - check if user's division matches matrix division
            if ($userDivisionId) {
                $q->whereHas('forwardWorkflow.workflowDefinitions', function($subQ): void {
                    $subQ->where('is_division_specific', 1)
                    ->whereNull('division_reference_column')
                          ->where('approval_order', \Illuminate\Support\Facades\DB::raw('matrices.approval_level'));
                })
                ->where('division_id', $userDivisionId);
            }

            // Case 1b: Division-specific approval with division_reference_column - check if user's staff_id matches the value in the division_reference_column
            if ($userStaffId) {
                $q->orWhere(function($subQ) use ($userStaffId, $userDivisionId) {
                    $divisionsTable = (new Division())->getTable();
                    $subQ->whereRaw("EXISTS (
                        SELECT 1 FROM workflow_definition wd 
                        JOIN {$divisionsTable} d ON d.id = matrices.division_id 
                        WHERE wd.workflow_id = matrices.forward_workflow_id 
                        AND wd.is_division_specific = 1 
                        AND wd.division_reference_column IS NOT NULL 
                        AND wd.approval_order = matrices.approval_level
                        AND ( d.focal_person = ? OR
                            d.division_head = ? OR
                            d.admin_assistant = ? OR
                            d.finance_officer = ? OR
                            d.head_oic_id = ? OR
                            d.director_id = ? OR
                            d.director_oic_id = ?
                            OR (d.id=matrices.division_id AND d.id=?)
                        )
                    )", [$userStaffId, $userStaffId, $userStaffId, $userStaffId, $userStaffId, $userStaffId, $userStaffId, $userDivisionId])
                    ->orWhere(function($subQ2) use ($userStaffId) {
                        $subQ2->where('approval_level', $userStaffId)
                              ->orWhereHas('approvalTrails', function($trailQ) use ($userStaffId) {
                                $trailQ->where('staff_id', '=',$userStaffId);
                              });
                    });
                });
            }
            
            // Case 2: Non-division-specific approval - check workflow definition and approver
            if ($userStaffId) {
                $q->orWhere(function($subQ) use ($userStaffId) {
                    $subQ->whereHas('forwardWorkflow.workflowDefinitions', function($workflowQ) use ($userStaffId) {
                        $workflowQ->where('is_division_specific','=', 0)
                                  ->where('approval_order', \Illuminate\Support\Facades\DB::raw('matrices.approval_level'))
                                  ->whereHas('approvers', function($approverQ) use ($userStaffId) {
                                      $approverQ->where('staff_id', $userStaffId);
                                  });
                    });
                });
            }

            $q->orWhere('division_id', $userDivisionId);
        });

        $pendingMatrices = $query->paginate(20);

        // Apply the same additional filtering as index method for consistency
        $pendingMatrices->getCollection()->transform(function ($matrix) {
            return can_take_action($matrix) ? $matrix : null;
        });
        $pendingMatrices->setCollection($pendingMatrices->getCollection()->filter());

        // Get matrices approved by current user
        $approvedByMe = Matrix::with([
            'division',
            'staff',
            'focalPerson',
            'forwardWorkflow'
        ])->whereHas('approvalTrails', function($q) use ($userStaffId) {
            $q->where('staff_id', $userStaffId)
              ->whereIn('action', ['approved', 'rejected', 'returned']);
        })->paginate(20);

        // Get divisions for filter
        $divisions = Division::orderBy('division_name')->get();
        
        // Get focal persons for filter - focal person info is stored in divisions table
        $focalPersons = Staff::whereIn('staff_id', function($query) {
            $query->select('focal_person')
                  ->from('divisions')
                  ->whereNotNull('focal_person');
        })->orderBy('fname')
          ->get();

        return view('matrices.pending-approvals', compact(
            'pendingMatrices',
            'approvedByMe',
            'divisions',
            'focalPersons'
        ));
    }

    /**
     * Show the approval status page for a matrix.
     */
    public function status(Matrix $matrix): View
    {
        $matrix->load(['staff', 'division', 'forwardWorkflow', 'approvalTrails.staff']);
        
        // Get approval order map from the matrix
        $approvalOrderMap = [];
        if ($matrix->approval_order_map) {
            $approvalOrderMap = json_decode($matrix->approval_order_map, true);
        } else {
            // Generate approval order map if not exists
            $approvalService = new \App\Services\ApprovalService();
            $approvalOrderMap = $approvalService->generateApprovalOrderMap($matrix);
        }
        
        return view('matrices.status', compact('matrix', 'approvalOrderMap'));
    }

    /**
     * Get detailed approval level information for the matrix.
     */
    private function getApprovalLevels(Matrix $matrix): array
    {
        if (!$matrix->forward_workflow_id) {
            return [];
        }

        $levels = \App\Models\WorkflowDefinition::where('workflow_id', $matrix->forward_workflow_id)
            ->where('is_enabled', 1)
            ->orderBy('approval_order', 'asc')
            ->get();

        $approvalLevels = [];
        foreach ($levels as $level) {
            $isCurrentLevel = $level->approval_order == $matrix->approval_level;
            $isCompleted = $matrix->approval_level > $level->approval_order;
            $isPending = $matrix->approval_level == $level->approval_order && $matrix->overall_status === 'pending';
            
            $approver = null;
            if ($level->is_division_specific && $matrix->division) {
                $staffId = $matrix->division->{$level->division_reference_column} ?? null;
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
     * Export matrices to CSV
     */
    public function exportCsv(Request $request)
    {
        $query = Matrix::with([
            'division',
            'staff',
            'focalPerson',
            'forwardWorkflow'
        ]);

        // Apply filters if provided
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

        $matrices = $query->latest()->get();

        $filename = 'matrices_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($matrices) {
            $file = fopen('php://output', 'w');
            
            // CSV Headers
            fputcsv($file, [
                'ID', 'Title', 'Year', 'Quarter', 'Division', 'Focal Person', 
                'Status', 'Approval Level', 'Created Date', 'Updated Date'
            ]);

            // CSV Data
            foreach ($matrices as $matrix) {
                fputcsv($file, [
                    $matrix->id,
                    $matrix->title ?? 'N/A',
                    $matrix->year,
                    $matrix->quarter,
                    $matrix->division ? $matrix->division->division_name : 'N/A',
                    $matrix->focalPerson ? ($matrix->focalPerson->fname . ' ' . $matrix->focalPerson->lname) : 'N/A',
                    $matrix->overall_status ?? 'N/A',
                    $matrix->approval_level ?? 'N/A',
                    $matrix->created_at ? $matrix->created_at->format('Y-m-d') : 'N/A',
                    $matrix->updated_at ? $matrix->updated_at->format('Y-m-d') : 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export division matrices to CSV
     */
    public function exportDivisionCsv(Request $request)
    {
        $userDivisionId = user_session('division_id');
        
        $query = Matrix::with([
            'division',
            'staff',
            'focalPerson',
            'forwardWorkflow'
        ])->where('division_id', $userDivisionId);

        // Apply filters if provided
        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }
    
        if ($request->filled('quarter')) {
            $query->where('quarter', $request->quarter);
        }
    
        if ($request->filled('focal_person')) {
            $query->where('focal_person_id', $request->focal_person);
        }

        $matrices = $query->latest()->get();

        $filename = 'division_matrices_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($matrices) {
            $file = fopen('php://output', 'w');
            
            // CSV Headers
            fputcsv($file, [
                'ID', 'Title', 'Year', 'Quarter', 'Division', 'Focal Person', 
                'Status', 'Approval Level', 'Created Date', 'Updated Date'
            ]);

            // CSV Data
            foreach ($matrices as $matrix) {
                fputcsv($file, [
                    $matrix->id,
                    $matrix->title ?? 'N/A',
                    $matrix->year,
                    $matrix->quarter,
                    $matrix->division ? $matrix->division->division_name : 'N/A',
                    $matrix->focalPerson ? ($matrix->focalPerson->fname . ' ' . $matrix->focalPerson->lname) : 'N/A',
                    $matrix->overall_status ?? 'N/A',
                    $matrix->approval_level ?? 'N/A',
                    $matrix->created_at ? $matrix->created_at->format('Y-m-d') : 'N/A',
                    $matrix->updated_at ? $matrix->updated_at->format('Y-m-d') : 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export pending approvals to CSV
     */
    public function exportPendingApprovalsCsv(Request $request)
    {
        $userStaffId = user_session('staff_id');
        $userDivisionId = user_session('division_id');

        $query = Matrix::with([
            'division',
            'staff',
            'focalPerson',
            'forwardWorkflow'
        ])->where('overall_status', 'pending')
          ->where('forward_workflow_id', '!=', null)
          ->where('approval_level', '>', 0);

        // Apply the same filtering logic as pendingApprovals method
        $query->where(function($q) use ($userDivisionId, $userStaffId) {
            if ($userDivisionId) {
                $q->whereHas('forwardWorkflow.workflowDefinitions', function($subQ): void {
                    $subQ->where('is_division_specific', 1)
                    ->whereNull('division_reference_column')
                          ->where('approval_order', \Illuminate\Support\Facades\DB::raw('matrices.approval_level'));
                })
                ->where('division_id', $userDivisionId);
            }

            if ($userStaffId) {
                $q->orWhere(function($subQ) use ($userStaffId, $userDivisionId) {
                    $divisionsTable = (new Division())->getTable();
                    $subQ->whereRaw("EXISTS (
                        SELECT 1 FROM workflow_definition wd 
                        JOIN {$divisionsTable} d ON d.id = matrices.division_id 
                        WHERE wd.workflow_id = matrices.forward_workflow_id 
                        AND wd.is_division_specific = 1 
                        AND wd.division_reference_column IS NOT NULL 
                        AND wd.approval_order = matrices.approval_level
                        AND ( d.focal_person = ? OR
                            d.division_head = ? OR
                            d.admin_assistant = ? OR
                            d.finance_officer = ? OR
                            d.head_oic_id = ? OR
                            d.director_id = ? OR
                            d.director_oic_id = ?
                            OR (d.id=matrices.division_id AND d.id=?)
                        )
                    )", [$userStaffId, $userStaffId, $userStaffId, $userStaffId, $userStaffId, $userStaffId, $userStaffId, $userDivisionId])
                    ->orWhere(function($subQ2) use ($userStaffId) {
                        $subQ2->where('approval_level', $userStaffId)
                              ->orWhereHas('approvalTrails', function($trailQ) use ($userStaffId) {
                                $trailQ->where('staff_id', '=',$userStaffId);
                              });
                    });
                });
            }
            
            if ($userStaffId) {
                $q->orWhere(function($subQ) use ($userStaffId) {
                    $subQ->whereHas('forwardWorkflow.workflowDefinitions', function($workflowQ) use ($userStaffId) {
                        $workflowQ->where('is_division_specific','=', 0)
                                  ->where('approval_order', \Illuminate\Support\Facades\DB::raw('matrices.approval_level'))
                                  ->whereHas('approvers', function($approverQ) use ($userStaffId) {
                                      $approverQ->where('staff_id', $userStaffId);
                                  });
                    });
                });
            }

            $q->orWhere('division_id', $userDivisionId);
        });

        $matrices = $query->get();

        // Apply the same additional filtering
        $matrices = $matrices->filter(function ($matrix) {
            return can_take_action($matrix);
        });

        $filename = 'pending_approvals_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($matrices) {
            $file = fopen('php://output', 'w');
            
            // CSV Headers
            fputcsv($file, [
                'ID', 'Title', 'Year', 'Quarter', 'Division', 'Focal Person', 
                'Status', 'Approval Level', 'Current Approver', 'Created Date'
            ]);

            // CSV Data
            foreach ($matrices as $matrix) {
                fputcsv($file, [
                    $matrix->id,
                    $matrix->title ?? 'N/A',
                    $matrix->year,
                    $matrix->quarter,
                    $matrix->division ? $matrix->division->division_name : 'N/A',
                    $matrix->focalPerson ? ($matrix->focalPerson->fname . ' ' . $matrix->focalPerson->lname) : 'N/A',
                    $matrix->overall_status ?? 'N/A',
                    $matrix->approval_level ?? 'N/A',
                    $matrix->current_actor ? ($matrix->current_actor->fname . ' ' . $matrix->current_actor->lname) : 'N/A',
                    $matrix->created_at ? $matrix->created_at->format('Y-m-d') : 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export approved by me matrices to CSV
     */
    public function exportApprovedByMeCsv(Request $request)
    {
        $userStaffId = user_session('staff_id');
        $userDivisionId = user_session('division_id');

        $query = Matrix::with([
            'division',
            'staff',
            'focalPerson',
            'forwardWorkflow',
            'approvalTrails'
        ])->whereHas('approvalTrails', function($q) use ($userStaffId) {
            $q->where('staff_id', $userStaffId);
        });

        // Apply filters if provided
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

        $matrices = $query->latest()->get();

        $filename = 'approved_by_me_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($matrices, $userStaffId) {
            $file = fopen('php://output', 'w');
            
            // CSV Headers
            fputcsv($file, [
                'ID', 'Title', 'Year', 'Quarter', 'Division', 'Focal Person', 
                'Status', 'Your Action', 'Action Date', 'Created Date'
            ]);

            // CSV Data
            foreach ($matrices as $matrix) {
                $myApproval = $matrix->approvalTrails->where('staff_id', $userStaffId)->first();
                fputcsv($file, [
                    $matrix->id,
                    $matrix->title ?? 'N/A',
                    $matrix->year,
                    $matrix->quarter,
                    $matrix->division ? $matrix->division->division_name : 'N/A',
                    $matrix->focalPerson ? ($matrix->focalPerson->fname . ' ' . $matrix->focalPerson->lname) : 'N/A',
                    $matrix->overall_status ?? 'N/A',
                    $myApproval ? ucfirst($myApproval->action ?? 'Unknown') : 'N/A',
                    $myApproval && $myApproval->created_at ? $myApproval->created_at->format('Y-m-d H:i') : 'N/A',
                    $matrix->created_at ? $matrix->created_at->format('Y-m-d') : 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get matrix budget information
     */
    public function getMatrixBudgets(Matrix $matrix)
    {
        try {
            $intramuralBudget = 0;
            $extramuralBudget = 0;
            $totalBudget = 0;
            $activitiesCount = 0;
            
            $currentStaffId = user_session('staff_id');
            $userDivisionId = user_session('division_id');
            
            // Check if user is an approver at the current approval level
            $isApprover = $this->isUserApproverAtCurrentLevel($matrix, $currentStaffId, $userDivisionId);
            
            if ($isApprover) {
                // If user is an approver, use the helper function to get approvable activities
                $approvableActivities = get_approvable_activities($matrix);
                
                // Also include approved single memos for budget calculation
                $approvedSingleMemos = $matrix->activities->filter(function($activity) use ($currentStaffId) {
                    return $activity->is_single_memo && 
                           $activity->overall_status === 'approved' &&
                           ($activity->staff_id == $currentStaffId || $activity->responsible_person_id == $currentStaffId);
                });
                
                // Combine approvable activities and approved single memos
                $visibleActivities = $approvableActivities->merge($approvedSingleMemos);
            } else {
                // If user is not an approver at current level, show all activities
                $visibleActivities = $matrix->activities->where('is_single_memo', false);
            }
            
            foreach($visibleActivities as $activity) {
                $budget = is_array($activity->budget_breakdown) ? $activity->budget_breakdown : json_decode($activity->budget_breakdown, true);
                
                if (is_array($budget)) {
                    foreach ($budget as $key => $entries) {
                        if ($key === 'grand_total') continue;
                        
                        if (is_array($entries)) {
                            foreach ($entries as $item) {
                                $unitCost = floatval($item['unit_cost'] ?? 0);
                                $units = floatval($item['units'] ?? 0);
                                $days = floatval($item['days'] ?? 1);
                                
                                if ($days > 1) {
                                    $itemTotal = $unitCost * $units * $days;
                                } else {
                                    $itemTotal = $unitCost * $units;
                                }
                                
                                // Add to total budget
                                $totalBudget += $itemTotal;
                                
                                // Categorize by fund type based on the key (fund code ID)
                                $fundCodeId = intval($key);
                                $fundCode = \App\Models\FundCode::with('fundType')->find($fundCodeId);
                                
                                if ($fundCode && $fundCode->fundType) {
                                    $fundTypeId = $fundCode->fundType->id;
                                    if (in_array($fundTypeId, [1, 3])) {
                                        // Intramural (fund types 1 and 3)
                                        $intramuralBudget += $itemTotal;
                                    } elseif ($fundTypeId == 2) {
                                        // Extramural (fund type 2)
                                        $extramuralBudget += $itemTotal;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Count only visible activities
            $activitiesCount = $visibleActivities->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'intramural_budget' => $intramuralBudget,
                    'extramural_budget' => $extramuralBudget,
                    'total_budget' => $totalBudget,
                    'activities_count' => $activitiesCount
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error calculating budgets: ' . $e->getMessage()
            ], 500);
        }
    }
}