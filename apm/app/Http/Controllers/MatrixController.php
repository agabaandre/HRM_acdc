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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
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
            $query->where('id', $request->division);
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
        ])->where('division_id', user_session('division_id'));

        // Apply filters to my division query
        if ($request->filled('year')) {
            $myDivisionQuery->where('year', $request->year);
        }
        if ($request->filled('quarter')) {
            $myDivisionQuery->where('quarter', $request->quarter);
        }
        if ($request->filled('focal_person')) {
            $myDivisionQuery->where('focal_person_id', $request->focal_person);
        }
        if ($request->filled('division')) {
            $myDivisionQuery->where('id', $request->division);
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

            // Apply same filters to all matrices query
            if ($request->filled('year')) {
                $allMatricesQuery->where('year', $request->year);
            }
        
            if ($request->filled('quarter')) {
                $allMatricesQuery->where('quarter', $request->quarter);
            }
        
            if ($request->filled('focal_person')) {
                $allMatricesQuery->where('focal_person_id', $request->focal_person);
            }
        
            if ($request->filled('division')) {
                $allMatricesQuery->where('id', $request->division);
            }

            $allMatrices = $allMatricesQuery->orderBy('year', 'desc')
                                           ->orderBy('quarter', 'desc')
                                           ->paginate(24, ['*'], 'all_matrices_page');
        }

        //  dd($filteredActionedMatrices->toArray());

    
        return view('matrices.index', [
            'matrices' => $matrices,
            'myDivisionMatrices' => $myDivisionMatrices,
            'allMatrices' => $allMatrices,
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

        $matrix->load(['division', 'staff','participant_schedules','participant_schedules.staff','matrixApprovalTrails']);
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

        // Build activities query with filtering and eager loading
        $activitiesQuery = $matrix->activities()
            ->where('is_single_memo', 0)
            ->with([
                'requestType', 
                'fundType', 
                'responsiblePerson', 
                'activity_budget', 
                'activity_budget.fundcode',
                'matrix.division', // Eager load matrix division
                'matrix.forwardWorkflow' // Eager load workflow
            ]);

        // Filter by allowed funders if specified in workflow definition
        if ($userWorkflowDefinition->allowed_funders) {
            $allowedFunders = is_string($userWorkflowDefinition->allowed_funders) 
                ? json_decode($userWorkflowDefinition->allowed_funders, true) 
                : $userWorkflowDefinition->allowed_funders;
            
            if (is_array($allowedFunders) && !empty($allowedFunders)) {
                // Filter by fund type through the activity_budget relationship
                $activitiesQuery->whereHas('activity_budget.fundcode', function($query) use ($allowedFunders) {
                    $query->whereIn('fund_type_id', $allowedFunders);
                });
            }
        }

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

        // Cache workflow definition data to avoid repeated queries
        $workflowDefinitionData = [
            'id' => $userWorkflowDefinition->id,
            'approval_order' => $userWorkflowDefinition->approval_order,
            'allowed_funders' => $userWorkflowDefinition->allowed_funders
        ];

        // Process activities with pre-loaded data
        foreach ($activitiesData as $data) {
            $activity = $data['activity'];
            
            // Attach related models from pre-loaded collections
            $activity->locations = $locations->whereIn('id', $data['location_ids'])->values();
            $activity->internalParticipants = $staff->whereIn('staff_id', $data['staff_ids'])->values();
            
            // Add approval-related data (optimized with caching)
            $activity->can_approve = can_approve_activity($activity);
            $activity->allow_print = allow_print_activity($activity);
            $activity->my_last_action = $activity->my_last_action ?? null;
            
            // Check if user's approval order has already passed this activity
            $activity->user_has_passed = $this->hasUserPassedActivity($activity, $userWorkflowDefinition);
        }

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

        // Check if user's last action on this activity was "passed" at the current approval level
        $userStaffId = user_session('staff_id');
        if ($userStaffId) {
            $currentApprovalLevel = $activity->matrix->approval_level;
            
            $lastAction = ActivityApprovalTrail::where('activity_id', $activity->id)
                ->where('staff_id', $userStaffId)
                ->where('approval_order', $currentApprovalLevel)
                ->orderBy('id', 'desc')
                ->first();
                
            if ($lastAction && $lastAction->action === 'passed') {
                return true;
            }
        }

        return false;
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

        // Build single memos query with filtering
        $singleMemosQuery = $matrix->activities()
            ->where('is_single_memo', 1)
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
        // Build single memos query without filtering
        $singleMemosQuery = $matrix->activities()
            ->where('is_single_memo', 1)
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
            $query = Staff::where('division_id', $matrix->division_id);

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
                    ->get();

                // Calculate division days (activities where participant is in their home division)
                $division_days = $participantSchedules->where('is_home_division', true)->sum('participant_days');
                
                // Calculate other division days (activities where participant is in other divisions)
                $other_days = $participantSchedules->where('is_home_division', false)->sum('participant_days');
                
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
                    ->get();

                $division_days = $participantSchedules->where('is_home_division', true)->sum('participant_days');
                $other_days = $participantSchedules->where('is_home_division', false)->sum('participant_days');
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

        if($request->action == 'approvals'){

            if($last_approval_trail){
                $workflow_defn       = WorkflowDefinition::where('approval_order', $last_approval_trail->approval_order)->first();
                $last_workflow_id    = $workflow_defn->workflow_id;
                $last_approval_order = $last_approval_trail->approval_order;
            }
            else
                $last_approval_order=1;

            $overall_status = 'pending';
            $this->saveMatrixTrail($matrix,'Submitted for approval','submitted');
        }

        // Get assigned workflow ID for Matrix model when submitting for approval
        $assignedWorkflowId = $last_workflow_id;
        if ($request->action == 'approvals' && $last_workflow_id == null) {
            $assignedWorkflowId = WorkflowModel::getWorkflowIdForModel('Matrix');
            if (!$assignedWorkflowId) {
                $assignedWorkflowId = 1; // Default workflow ID
                Log::warning('No workflow assignment found for Matrix model, using default workflow ID: 1');
            }
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
        send_matrix_email_notification($matrix, 'approval');
    }

    public function request_approval( Matrix $matrix){

        $this->updateMatrix($matrix,(Object)['action'=>'approvals'],null);
        //notify and save notification
        send_matrix_email_notification($matrix, 'approval');
        
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

    public function update_status(Request $request, Matrix $matrix): RedirectResponse
    {
        $request->validate(['action' => 'required']);
        //dd($request->all());
        $this->saveMatrixTrail($matrix,$request->comment,$request->action);
        
        $notification_type =null;

        if($request->action !=='approved'){

            $matrix->forward_workflow_id = (intval($matrix->approval_level)==1)?null:1;
            $matrix->approval_level = ($matrix->approval_level==1)?0:1;
            $matrix->overall_status ='returned';
            //notify and save notification
            $notification_type = 'returned';
        }else{
            //move to next
            $next_approval_point = $this->get_next_approver($matrix);
           
           if($next_approval_point){

            $matrix->forward_workflow_id = $next_approval_point->workflow_id;
            $matrix->approval_level = $next_approval_point->approval_order;
            $matrix->next_approval_level = $next_approval_point->approval_order;
            $matrix->overall_status = 'pending';

            //notify and save notification
            $notification_type = 'approval';
           }
           else{
            //no more approval levels
            $matrix->overall_status = 'approved';
            $notification_type = 'approved';
           }
        }
        
        $matrix->update();

        //notify and save notification
        send_matrix_email_notification($matrix, $notification_type);
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
        $matrixTrail->save();
        //dd($matrixTrail);

        mark_matrix_notifications_read(user_session('staff_id'), $matrix->id);
    }

    private function get_next_approver($matrix){

        $division   = $matrix->division;

        $current_definition = WorkflowDefinition::where('workflow_id',$matrix->forward_workflow_id)
           ->where('is_enabled',1)
           ->where('approval_order',$matrix->approval_level)
           ->first();

        $go_to_category_check_for_external =(!$matrix->has_extramural && !$matrix->has_extramural && ($matrix->approval_level!=null && $current_definition->approval_order > $matrix->approval_level));

        //if it's time to trigger categroy check, just check and continue
        if(($current_definition && $current_definition->triggers_category_check) || $go_to_category_check_for_external){

            $category_definition = WorkflowDefinition::where('workflow_id',$matrix->forward_workflow_id)
                        ->where('is_enabled',1)
                        ->where('category',$division->category)
                        ->orderBy('approval_order','asc')
                        ->first();

            return $category_definition;
        }

        $nextStepIncrement = 1;

        //Skip Directorate from HOD if no directorate
        if($matrix->forward_workflow_id>0 && $current_definition->approval_order==1 && !$division->director_id)
            $nextStepIncrement = 2;

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

       
        return  $definition;

    }

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
        
        // Get approval level information
        $approvalLevels = $this->getApprovalLevels($matrix);
        
        return view('matrices.status', compact('matrix', 'approvalLevels'));
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
            
            foreach($matrix->activities as $activity) {
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

            // Get activities count
            $activitiesCount = $matrix->activities->count();

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