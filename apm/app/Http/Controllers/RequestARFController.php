<?php

namespace App\Http\Controllers;

use App\Models\RequestARF;
use App\Models\Staff;
use App\Models\Workflow;
use App\Models\Division;
use App\Models\Location;
use App\Models\WorkflowModel;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class RequestARFController extends Controller
{
    protected ApprovalService $approvalService;

    public function __construct(ApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }
    /**
     * Display a listing of ARF requests.
     */
    public function index(Request $request)
    {
        $currentStaffId = user_session('staff_id');
        $currentYear = (int) date('Y');
        // Default to current year when year is missing or empty; keep "all" when explicitly chosen
        $selectedYear = $request->get('year');
        if ($selectedYear === null || $selectedYear === '') {
            $selectedYear = (string) $currentYear;
        }
        $years = array_merge(['all' => 'All years'], array_combine(
            range($currentYear, $currentYear - 10),
            range($currentYear, $currentYear - 10)
        ));

        // Get My ARFs (created by current user)
        $mySubmittedArfsQuery = RequestARF::with([
            'staff',
            'division',
            'forwardWorkflow.workflowDefinitions.approvers.staff'
        ])
            ->where('staff_id', $currentStaffId);

        if ($selectedYear !== '' && $selectedYear !== 'all') {
            $mySubmittedArfsQuery->whereYear('created_at', $selectedYear);
        }

        // Apply filters to My ARFs
        if ($request->filled('document_number')) {
            $mySubmittedArfsQuery->where(function($q) use ($request) {
                $q->where('document_number', 'like', '%' . $request->document_number . '%')
                  ->orWhere('arf_number', 'like', '%' . $request->document_number . '%');
            });
        }

        if ($request->has('division_id') && $request->division_id) {
            $mySubmittedArfsQuery->where('division_id', $request->division_id);
        }

        if ($request->has('status') && $request->status) {
            $mySubmittedArfsQuery->where('overall_status', $request->status);
        }

        if ($request->filled('search')) {
            $mySubmittedArfsQuery->where('activity_title', 'like', '%' . $request->search . '%');
        }

        $mySubmittedArfs = $mySubmittedArfsQuery->orderByDesc('created_at')->paginate(20)->withQueryString();

        // Get All ARFs (only for users with permission 87)
        $allArfs = collect();
        if (in_array(87, user_session('permissions', []))) {
            $allArfsQuery = RequestARF::with([
                'staff',
                'division',
                'forwardWorkflow.workflowDefinitions.approvers.staff'
            ])
                ->orderByDesc('created_at');

            if ($selectedYear !== '' && $selectedYear !== 'all') {
                $allArfsQuery->whereYear('created_at', $selectedYear);
            }

            // Apply filters to All ARFs
            if ($request->filled('document_number')) {
                $allArfsQuery->where(function($q) use ($request) {
                    $q->where('document_number', 'like', '%' . $request->document_number . '%')
                      ->orWhere('arf_number', 'like', '%' . $request->document_number . '%');
                });
            }

            if ($request->has('division_id') && $request->division_id) {
                $allArfsQuery->where('division_id', $request->division_id);
            }

            if ($request->has('staff_id') && $request->staff_id) {
                $allArfsQuery->where('staff_id', $request->staff_id);
            }

            if ($request->has('status') && $request->status) {
                $allArfsQuery->where('overall_status', $request->status);
            }

            if ($request->filled('search')) {
                $allArfsQuery->where('activity_title', 'like', '%' . $request->search . '%');
            }

            $allArfs = $allArfsQuery->paginate(20)->withQueryString();
        }

        $divisions = Division::orderBy('division_name')->get();
        $staff = Staff::active()->get();

        // Handle AJAX requests for tab content
        if ($request->ajax()) {
            $tab = $request->get('tab', '');
            $html = '';

            switch($tab) {
                case 'mySubmitted':
                    $html = view('request-arf.partials.my-submitted-tab', compact('mySubmittedArfs'))->render();
                    break;
                case 'allArfs':
                    $html = view('request-arf.partials.all-arfs-tab', compact('allArfs'))->render();
                    break;
            }

            return response()->json(['html' => $html]);
        }

        return view('request-arf.index', compact('mySubmittedArfs', 'allArfs', 'divisions', 'staff', 'years', 'selectedYear'));
    }

    /**
     * Show the form for creating a new ARF request.
     */
    public function create(): View
    {
        $staff = Staff::active()->get();
        $divisions = Division::all();
        $workflows = Workflow::all();
        $locations = Location::all();
        
        // Generate a unique ARF number
        $arfNumber = RequestARF::generateARFNumber();
        
        return view('request-arf.create', compact('staff', 'divisions', 'workflows', 'locations', 'arfNumber'));
    }

    /**
     * Store a newly created ARF request.
     */
    public function store(Request $request): RedirectResponse
    {
        // Check if this is a modal submission (from activities/memos)
        if ($request->has('source_type')) {
            return $this->storeFromModal($request);
        }
        
        try {
            // Traditional form validation
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,staff_id',
            'forward_workflow_id' => 'required|exists:workflows,id',
            'reverse_workflow_id' => 'required|exists:workflows,id',
            'arf_number' => 'required|string|unique:request_arfs,arf_number',
            'request_date' => 'required|date',
            'division_id' => 'required|exists:divisions,id',
            'location_id' => 'required|array',
            'activity_title' => 'required|string|max:255',
            'purpose' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'requested_amount' => 'required|numeric|min:0',
            'accounting_code' => 'required|string|max:255',
            'budget_breakdown' => 'required|array',
            'attachment' => 'nullable|array',
            'attachment.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'status' => 'sometimes|in:draft,submitted,approved,rejected',
        ]);
        
        // Handle file attachments
        $attachments = [];
        if ($request->hasFile('attachment')) {
            foreach ($request->file('attachment') as $file) {
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('arf-attachments', $filename, 'public');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ];
            }
        }
        
        $validated['attachment'] = $attachments;
        
        // Clean Unicode characters from text fields
        if (isset($validated['activity_title'])) {
            $validated['activity_title'] = clean_unicode($validated['activity_title']);
        }
        if (isset($validated['purpose'])) {
            $validated['purpose'] = clean_unicode($validated['purpose']);
        }
        
        // Set default status if not provided
        if (!isset($validated['status'])) {
            $validated['status'] = 'draft';
        }
        
        // Set approval levels and overall status
        $validated['approval_level'] = 0;
        $validated['next_approval_level'] = null;
        $validated['overall_status'] = 'draft';
        
        if ($validated['status'] === 'submitted') {
            $validated['approval_level'] = 21; // Start at level 21 for ARF (Partnerships Officer)
            $validated['next_approval_level'] = 22; // Next level to be approved
            $validated['overall_status'] = 'pending';
        }
            
            $arf = RequestARF::create($validated);
            
            // Check if this is an AJAX request
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'msg' => 'ARF request created successfully.',
                    'arf' => $arf,
                    'redirect_url' => route('request-arf.show', $arf)
                ]);
            }
        
        return redirect()
            ->route('request-arf.index')
            ->with('success', 'ARF request created successfully.');
                
        } catch (\Exception $e) {
            $errorMessage = 'An error occurred while creating the ARF request: ' . $e->getMessage();
            
            // Check if this is an AJAX request
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'msg' => $errorMessage
                ], 500);
            }
            
            return redirect()->back()->with('error', $errorMessage);
        }
    }

    /**
     * Debug method to test ARF controller accessibility
     */
    public function debugTest()
    {
        Log::info('ARF Debug Test Called');
        return response()->json(['status' => 'success', 'message' => 'ARF controller is accessible']);
    }

    /**
     * Store ARF request from modal submission (from activities/memos).
     */
    public function storeFromModal(Request $request)
    {
      

        // Check if user session is valid
        $sessionStaffId = user_session('staff_id');
        if (!$sessionStaffId) {
            Log::error('No valid staff session found');
            return redirect()->back()->with('error', 'You must be logged in to create an ARF request.');
        }

        // Get the staff record to verify it exists
        $staff = \App\Models\Staff::where('staff_id', $sessionStaffId)->first();
        if (!$staff) {
            Log::error('Staff record not found for staff_id: ' . $sessionStaffId);
            return redirect()->back()->with('error', 'Staff record not found. Please contact administrator.');
        }
        
        $staffId = $staff->staff_id; // Use the actual staff_id column

        Log::info('Starting validation...');
        
        try {
            $request->validate([
                'source_type' => 'required|in:activity,non_travel,special_memo',
                'source_id' => 'required|integer',
                'title' => 'required|string|max:255',
                'total_budget' => 'required|numeric|min:0',
                'fund_type_id' => 'nullable|integer',
                'model_type' => 'required|string',
                'action' => 'required|in:submit'
            ]);
            Log::info('Validation passed successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', ['errors' => $e->errors()]);
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'msg' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
            
            throw $e;
        }

        Log::info('ARF Validation Passed');

        // Check for duplicate ARF requests for the same source
        $existingArf = RequestARF::where('source_id', $request->source_id)
            ->where('model_type', $request->model_type)
            ->where('staff_id', $staffId)
            ->first();

        if ($existingArf) {
            $errorMessage = 'An ARF request already exists for this ' . str_replace('_', ' ', $request->source_type) . '.';
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'msg' => $errorMessage
                ], 422);
            }
            
            return redirect()->back()->with('error', $errorMessage);
        }

        try {
            // Get source data to verify it exists
            $sourceData = $this->getSourceData($request->source_type, $request->source_id);
            
            if (!$sourceData) {
                $errorMessage = 'Source data not found.';
                
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'msg' => $errorMessage
                    ], 422);
                }
                
                return redirect()->back()->with('error', $errorMessage);
            }

            // Generate ARF number with proper format
            $arfNumber = $this->generateARFNumber($sourceData, $request->model_type);
            
            // Capture budget breakdown and internal participants
            $budgetBreakdown = $this->getBudgetBreakdown($sourceData, $request->model_type);
            $internalParticipants = $this->getInternalParticipants($sourceData, $request->model_type);
            
            // Encode internal participants as JSON (budget is already in correct format)
            $internalParticipantsJson = json_encode($internalParticipants);

            // Get assigned workflow ID for RequestARF model
            $assignedWorkflowId = WorkflowModel::getWorkflowIdForModel('RequestARF');
            if (!$assignedWorkflowId) {
                $assignedWorkflowId = 2; // Default workflow ID
                Log::warning('No workflow assignment found for RequestARF model, using default workflow ID: 2');
            }
            
            // Set approval levels and workflow IDs for immediate submission
            $approvalLevel = 21; // Start at level 21 for ARF (Partnerships Officer)
            $nextApprovalLevel = 22; // Next level to be approved
            $overallStatus = 'pending'; // Set to pending immediately
            $forwardWorkflowId = $assignedWorkflowId; // Set the assigned workflow ID
            $reverseWorkflowId = $assignedWorkflowId; // Set the same for reverse workflow
            
            // Debug: Check what workflow would be assigned
            Log::info('ARF Creation Debug - Workflow Assignment', [
                'model_name' => 'RequestARF',
                'assigned_workflow_id' => $assignedWorkflowId,
                'forward_workflow_id_set_to' => $forwardWorkflowId,
                'workflow_assignments' => WorkflowModel::where('model_name', 'RequestARF')->get()->toArray()
            ]);

            // Get responsible person from source data
            $responsiblePersonId = null;
            if ($request->model_type === 'App\\Models\\Activity') {
                // For activities, use the focal person (staff_id)
                $responsiblePersonId = $sourceData->staff_id ?? null;
                Log::info('Activity responsible person set', ['staff_id' => $responsiblePersonId]);
            } elseif ($request->model_type === 'App\\Models\\NonTravelMemo') {
                // For non-travel memos, use the creator (staff_id) as responsible person
                $responsiblePersonId = $sourceData->staff_id ?? null;
                Log::info('Non-travel memo responsible person set', ['staff_id' => $responsiblePersonId, 'source_data' => $sourceData->toArray()]);
            } elseif ($request->model_type === 'App\\Models\\SpecialMemo') {
                // For special memos, use the focal person (staff_id)
                $responsiblePersonId = $sourceData->staff_id ?? null;
                Log::info('Special memo responsible person set', ['staff_id' => $responsiblePersonId]);
            }

            // Create minimal ARF request - just for approval workflow
            $arfData = [
                'staff_id' => $staffId, // Creator from session
                'responsible_person_id' => $responsiblePersonId, // Responsible person from source
                'forward_workflow_id' => $forwardWorkflowId,
                'reverse_workflow_id' => $reverseWorkflowId,
                'arf_number' => $arfNumber,
                'request_date' => now()->toDateString(),
                'division_id' => $this->getDivisionId($sourceData, $request->model_type),
                'activity_title' => clean_unicode($request->title),
                'purpose' => clean_unicode('ARF Request for ' . ucfirst(str_replace('_', ' ', $request->source_type)) . ' #' . $request->source_id),
                'start_date' => now()->toDateString(), // Not important for approval
                'end_date' => now()->toDateString(), // Not important for approval
                'requested_amount' => $request->total_budget, // Total amount from source
                'total_amount' => $request->total_budget, // Total amount for display
                'accounting_code' => $request->source_type . '_' . $request->source_id, // Reference to source
                'budget_breakdown' => $budgetBreakdown, // Budget breakdown from source (already in correct format)
                'internal_participants' => $internalParticipantsJson, // Internal participants from source as JSON
                'fund_type_id' => $request->fund_type_id ?? 1, // Fund type ID from source, default to intramural (1)
                'model_type' => $request->model_type, // Laravel model class name
                'source_id' => $request->source_id,
                'source_type' => $request->source_type,
                'approval_level' => $approvalLevel,
                'next_approval_level' => $nextApprovalLevel,
                'overall_status' => $overallStatus,
            ];

            Log::info('Creating ARF with data', ['arf_data' => $arfData]);
            
            $arf = RequestARF::create($arfData);
            
            // Save approval trail for ARF creation and submission
            $arf->saveApprovalTrail('ARF request created and submitted for approval', 'submitted');
            
            // Send email notification for approval request
            send_generic_email_notification($arf, 'approval');
            
            Log::info('ARF Created and Submitted Successfully', [
                'arf_id' => $arf->id, 
                'arf_number' => $arf->arf_number,
                'forward_workflow_id' => $arf->forward_workflow_id,
                'overall_status' => $arf->overall_status,
                'approval_level' => $arf->approval_level
            ]);

            $message = 'ARF request created and submitted for approval successfully! Status: Pending';

            // Check if this is an AJAX request
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'msg' => $message,
                    'arf' => $arf,
                    'redirect_url' => route('request-arf.show', $arf)
                ]);
            }

            return redirect()->route('request-arf.show', $arf)->with('success', $message);

        } catch (\Exception $e) {
            Log::error('ARF Creation Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            
            $errorMessage = 'An error occurred while creating the ARF request: ' . $e->getMessage();
            
            // Check if this is an AJAX request
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'msg' => $errorMessage
                ], 500);
            }

            return redirect()->back()->with('error', $errorMessage);
        }
    }

    /**
     * Get source data based on type.
     */
    private function getSourceData($sourceType, $sourceId)
    {
        switch ($sourceType) {
            case 'activity':
                return \App\Models\Activity::with(['matrix.division', 'staff.division'])->find($sourceId);
            case 'non_travel':
                return \App\Models\NonTravelMemo::with(['division', 'staff.division'])->find($sourceId);
            case 'special_memo':
                return \App\Models\SpecialMemo::with(['division', 'staff.division'])->find($sourceId);
            default:
                return null;
        }
    }

    /**
     * Get division ID from source data based on model type.
     */
    private function getDivisionId($sourceData, $modelType = null)
    {
        if (!$sourceData) {
            Log::warning('Source data is null, using default division');
            return 1; // Default division
        }

        // For activities, get division through matrix
        if ($modelType === 'App\\Models\\Activity') {
            try {
                if (method_exists($sourceData, 'matrix') && $sourceData->matrix && $sourceData->matrix->division) {
                    Log::info('Using division from activity matrix', ['division_id' => $sourceData->matrix->division->id]);
                    return $sourceData->matrix->division->id;
                }
            } catch (\Exception $e) {
                Log::warning('Error accessing matrix division', ['error' => $e->getMessage()]);
            }
        }
        
        // For non-travel and special memos, get division directly
        try {
            if (method_exists($sourceData, 'division') && $sourceData->division) {
                Log::info('Using division from source document', ['division_id' => $sourceData->division->id]);
                return $sourceData->division->id;
            }
        } catch (\Exception $e) {
            Log::warning('Error accessing source division', ['error' => $e->getMessage()]);
        }
        
        // Fallback to staff division
        try {
            if (method_exists($sourceData, 'staff') && $sourceData->staff && $sourceData->staff->division) {
                Log::info('Using division from staff', ['division_id' => $sourceData->staff->division->id]);
                return $sourceData->staff->division->id;
            }
        } catch (\Exception $e) {
            Log::warning('Error accessing staff division', ['error' => $e->getMessage()]);
        }
        
        Log::warning('No division found in source data, using default division', [
            'model_type' => $modelType,
            'source_data_keys' => is_object($sourceData) ? array_keys($sourceData->toArray()) : 'not_object'
        ]);
        return 1; // Default division
    }
    
    /**
     * Generate ARF number with proper format: ARF/DHIS/Q2/activitystartyear/activity_id
     */
    private function generateARFNumber($sourceData, $modelType = null)
    {
        $divisionCode = 'DHIS';
        $quarter = 'Q1';
        $year = date('Y');
        $activityId = $sourceData->id ?? 1;
        
        try {
            // For activities, get division code and quarter from matrix
            if ($modelType === 'App\\Models\\Activity' && $sourceData) {
                if (method_exists($sourceData, 'matrix') && $sourceData->matrix) {
                    $matrix = $sourceData->matrix;
                    
                    // Get division code
                    if ($matrix->division && !empty($matrix->division->division_name)) {
                        $divisionCode = RequestARF::generateShortCodeFromDivision($matrix->division->division_name);
                    }
                    
                    // Get quarter
                    $quarter = $matrix->quarter ?? 'Q1';
                    
                    // Get year from activity start date or matrix year
                    if ($sourceData->date_from) {
                        $year = $sourceData->date_from->format('Y');
                    } elseif ($matrix->year) {
                        $year = $matrix->year;
                    }
                }
            }
            
            // For memos, get division code
            if (in_array($modelType, ['App\\Models\\NonTravelMemo', 'App\\Models\\SpecialMemo']) && $sourceData) {
                if (method_exists($sourceData, 'division') && $sourceData->division && !empty($sourceData->division->division_name)) {
                    $divisionCode = RequestARF::generateShortCodeFromDivision($sourceData->division->division_name);
                }
                
                // Get year from start date if available
                if (method_exists($sourceData, 'date_from') && $sourceData->date_from) {
                    $year = $sourceData->date_from->format('Y');
                }
            }
        } catch (\Exception $e) {
            Log::warning('Error generating ARF number details, using defaults', [
                'error' => $e->getMessage(),
                'model_type' => $modelType
            ]);
        }
        
        return RequestARF::generateARFNumber($divisionCode, $quarter, $year, $activityId);
    }
    
    /**
     * Get budget breakdown from source data
     */
private function getBudgetBreakdown($sourceData, $modelType = null)
    {
        // For activities, get from budget JSON column and save as-is
        if ($modelType === 'App\\Models\\Activity' && $sourceData) {
            // Get budget from JSON column and return as-is to avoid breaking approval service
            return $sourceData->budget_breakdown ?? null;
        }
        
        // For non-travel memos, get from budget_breakdown field (already array)
        if ($modelType === 'App\\Models\\NonTravelMemo' && $sourceData) {
            return $sourceData->budget_breakdown ?? null;
        }
        
        // For special memos, get from budget_breakdown field and return as raw JSON string
        if ($modelType === 'App\\Models\\SpecialMemo' && $sourceData) {
            \Log::info('Special memo budget processing', [
                'budget_type' => gettype($sourceData->budget_breakdown),
                'budget_preview' => is_string($sourceData->budget_breakdown) ? substr($sourceData->budget_breakdown, 0, 100) : $sourceData->budget_breakdown
            ]);
            
            // Special memo budget_breakdown is cast as array by Laravel, so we need to re-encode it to JSON
            if (is_array($sourceData->budget_breakdown)) {
                \Log::info('Re-encoding special memo budget_breakdown array to JSON string');
                return json_encode($sourceData->budget_breakdown);
            }
            
            return $sourceData->budget_breakdown ?? null;
        }
        
        return null;
    }
    
    /**
     * Get internal participants from source data
     */
    private function getInternalParticipants($sourceData, $modelType = null)
    {
        $internalParticipants = [];
        
        \Log::info('Getting internal participants', [
            'model_type' => $modelType,
            'source_data_id' => $sourceData->id ?? 'N/A',
            'has_internal_participants_method' => method_exists($sourceData, 'internal_participants')
        ]);
        
        // Non-travel memos don't have participants
        if ($modelType === 'App\\Models\\NonTravelMemo') {
            \Log::info('Non-travel memo detected, returning empty participants');
            return $internalParticipants;
        }
        
        // For other source types, get from internal_participants field
        if ($sourceData && method_exists($sourceData, 'internal_participants')) {
            $participants = $sourceData->internal_participants ?? [];
            
            \Log::info('Raw participants data', [
                'participants_type' => gettype($participants),
                'participants_value' => $participants,
                'is_array' => is_array($participants),
                'is_string' => is_string($participants)
            ]);
            
            // Handle both array and JSON string formats
            if (is_string($participants)) {
                $participants = json_decode($participants, true) ?? [];
                \Log::info('Decoded JSON participants', ['decoded' => $participants]);
            }
            
            $internalParticipants = $participants;
            
            \Log::info('Final internal participants', [
                'count' => count($internalParticipants),
                'participants' => $internalParticipants
            ]);
        } else {
            \Log::warning('Source data does not have internal_participants method or is null', [
                'source_data_exists' => $sourceData ? 'yes' : 'no',
                'has_method' => $sourceData ? method_exists($sourceData, 'internal_participants') : 'N/A'
            ]);
        }
        
        return $internalParticipants;
    }

    /**
     * Format ARF title with contextual information
     */
    private function formatArfTitle($sourceModel, $memoType)
    {
        $title = $sourceModel->activity_title ?? 'Untitled Activity';
        
        // Add memo type prefix
        $formattedTitle = "[{$memoType}] {$title}";
        
        // Add division information if available
        if (isset($sourceModel->division) && $sourceModel->division) {
            $formattedTitle .= " - {$sourceModel->division->division_name}";
        }
        
        // Add fund type information if available
        if (isset($sourceModel->fundType) && $sourceModel->fundType) {
            $formattedTitle .= " ({$sourceModel->fundType->name})";
        }
        
        return $formattedTitle;
    }

    /**
     * Display the specified ARF request.
     */
    public function show($request_arf): View
    {
            Log::info('ARF Show Method Called', ['id' => $request_arf]);
        
        $requestARF = RequestARF::with(['approvalTrails.staff', 'approvalTrails.approverRole', 'funder'])->find($request_arf);
        
        if (!$requestARF) {
            Log::error('ARF not found', ['id' => $request_arf]);
            abort(404, 'ARF request not found');
        }
        
        Log::info('ARF Found', [
            'arf_id' => $requestARF->id,
            'arf_number' => $requestARF->arf_number,
            'staff_id' => $requestARF->staff_id
        ]);
        
        // Load essential ARF relationships
        $requestARF->load(['staff', 'fundType', 'responsiblePerson']);
        
        // Get source data using model_type and source_id
        $sourceModel = null;
        $sourceData = [
            'title' => 'N/A',
            'start_date' => null,
            'end_date' => null,
            'location' => 'N/A',
            'division' => null,
            'division_head' => null,
            'responsible_person' => null,
            'budget_breakdown' => [],
            'internal_participants' => [],
            'activity_request_remarks' => 'N/A',
            'total_budget' => 0,
            'matrix_id' => null,
        ];
        
        // Use the model's getSourceModel method
        $sourceModel = $requestARF->getSourceModel();
        
        if ($sourceModel) {
            Log::info('Source model loaded successfully', [
                'arf_id' => $requestARF->id,
                'model_type' => $requestARF->model_type,
                'source_id' => $requestARF->source_id,
                'source_model_id' => $sourceModel->id
            ]);
            
            try {
                // Load necessary relationships based on model type
                if ($requestARF->model_type === 'App\\Models\\Activity') {
                    $sourceModel->load(['matrix.division.divisionHead', 'staff', 'activity_budget']);
                    
                    // Get fund codes for budget display
                    $fundCodes = [];
                    if ($sourceModel->budget_id) {
                        $budgetIds = is_string($sourceModel->budget_id) ? json_decode($sourceModel->budget_id, true) : $sourceModel->budget_id;
                        if (is_array($budgetIds)) {
                            $fundCodes = \App\Models\FundCode::whereIn('id', $budgetIds)->with('fundType')->get()->keyBy('id');
                        }
                    }
                    
                    $sourceData = [
                        'title' => $sourceModel->activity_title ?? 'N/A',
                        'start_date' => $sourceModel->date_from ?? null,
                        'end_date' => $sourceModel->date_to ?? null,
                        'location' => $sourceModel->locations() ? $sourceModel->locations()->pluck('name')->join(', ') : 'N/A',
                        'division' => $sourceModel->matrix->division ?? null,
                        'division_head' => $sourceModel->matrix->division->divisionHead ?? null,
                        'responsible_person' => $sourceModel->staff ?? null,
                        'budget_breakdown' => is_string($sourceModel->budget_breakdown) ? json_decode($sourceModel->budget_breakdown, true) ?? [] : ($sourceModel->budget_breakdown ?? []), // Use budget_breakdown column JSON
                        'fund_codes' => $fundCodes, // Add fund codes for proper display
                        'internal_participants' => is_string($sourceModel->internal_participants) ? json_decode($sourceModel->internal_participants, true) ?? [] : ($sourceModel->internal_participants ?? []),
                        'activity_request_remarks' => $sourceModel->activity_request_remarks ?? 'N/A',
                        'total_budget' => $sourceModel->total_budget ?? 0,
                        'matrix_id' => $sourceModel->matrix_id ?? null,
                    ];
                } elseif ($requestARF->model_type === 'App\\Models\\NonTravelMemo') {
                    $sourceModel->load(['division.divisionHead', 'staff', 'fundType']);
                    
                    // Get fund codes for budget display
                    $fundCodes = [];
                    if ($sourceModel->budget_id) {
                        $budgetIds = is_string($sourceModel->budget_id) ? json_decode($sourceModel->budget_id, true) : $sourceModel->budget_id;
                        if (is_array($budgetIds)) {
                            $fundCodes = \App\Models\FundCode::whereIn('id', $budgetIds)->with('fundType', 'funder')->get()->keyBy('id');
                        }
                    }
                    
                    $sourceData = [
                        'title' => $this->formatArfTitle($sourceModel, 'Non-Travel Memo'),
                        'start_date' => $sourceModel->date_from ?? null,
                        'end_date' => $sourceModel->date_to ?? null,
                        'location' => $sourceModel->location ?? 'N/A',
                        'division' => $sourceModel->division ?? null,
                        'division_head' => $sourceModel->division->divisionHead ?? null,
                        'responsible_person' => $sourceModel->staff ?? null,
                        'budget_breakdown' => is_string($sourceModel->budget_breakdown) ? json_decode($sourceModel->budget_breakdown, true) ?? [] : ($sourceModel->budget_breakdown ?? []),
                        'fund_codes' => $fundCodes, // Add fund codes for proper display
                        'internal_participants' => [], // Non-travel memos have no participants
                        'activity_request_remarks' => $sourceModel->activity_request_remarks ?? 'N/A',
                        'total_budget' => $sourceModel->total_budget ?? 0,
                        'matrix_id' => null,
                    ];
                } elseif ($requestARF->model_type === 'App\\Models\\SpecialMemo') {
                    $sourceModel->load(['division.divisionHead', 'staff', 'fundType']);
                    
                    // Get fund codes for budget display
                    $fundCodes = [];
                    if ($sourceModel->budget_id) {
                        $budgetIds = is_string($sourceModel->budget_id) ? json_decode($sourceModel->budget_id, true) : $sourceModel->budget_id;
                        if (is_array($budgetIds)) {
                            $fundCodes = \App\Models\FundCode::whereIn('id', $budgetIds)->with('fundType', 'funder')->get()->keyBy('id');
                        }
                    }
                    
                    $sourceData = [
                        'title' => $this->formatArfTitle($sourceModel, 'Special Memo'),
                        'start_date' => $sourceModel->date_from ?? null,
                        'end_date' => $sourceModel->date_to ?? null,
                        'location' => $sourceModel->location ?? 'N/A',
                        'division' => $sourceModel->division ?? null,
                        'division_head' => $sourceModel->division->divisionHead ?? null,
                        'responsible_person' => $sourceModel->staff ?? null,
                        'budget_breakdown' => is_string($sourceModel->budget_breakdown) ? json_decode($sourceModel->budget_breakdown, true) ?? [] : ($sourceModel->budget_breakdown ?? []),
                        'fund_codes' => $fundCodes, // Add fund codes for proper display
                        'internal_participants' => is_string($sourceModel->internal_participants) ? json_decode($sourceModel->internal_participants, true) ?? [] : ($sourceModel->internal_participants ?? []),
                        'activity_request_remarks' => $sourceModel->activity_request_remarks ?? 'N/A',
                        'total_budget' => $sourceModel->total_budget ?? 0,
                        'matrix_id' => null,
                    ];
                }
                
                Log::info('Source data populated successfully', [
                    'arf_id' => $requestARF->id,
                    'title' => $sourceData['title'],
                    'division_name' => $sourceData['division']->division_name ?? 'N/A',
                    'responsible_person' => $sourceData['responsible_person']->first_name ?? 'N/A',
                    'budget_breakdown_count' => is_array($sourceData['budget_breakdown']) ? count($sourceData['budget_breakdown']) : 'not array',
                    'internal_participants_count' => is_array($sourceData['internal_participants']) ? count($sourceData['internal_participants']) : 'not array',
                    'fund_codes_count' => $sourceData['fund_codes'] ? count($sourceData['fund_codes']) : 0
                ]);
                
            } catch (\Exception $e) {
                Log::error('Error loading source model relationships for ARF', [
                    'arf_id' => $requestARF->id,
                    'model_type' => $requestARF->model_type,
                    'source_id' => $requestARF->source_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        } else {
            Log::warning('Source model not found for ARF', [
                'arf_id' => $requestARF->id,
                'model_type' => $requestARF->model_type,
                'source_id' => $requestARF->source_id
            ]);
        }
        
        // Get approval levels for progress bar
        $approvalLevels = $this->getApprovalLevels($requestARF);
        
        return view('request-arf.show', compact('requestARF', 'sourceModel', 'sourceData', 'approvalLevels'));
    }

    /**
     * Get approval levels for progress bar calculation.
     */
    private function getApprovalLevels(RequestARF $requestARF): array
    {
        if (!$requestARF->forward_workflow_id) {
            return [];
        }

        $levels = \App\Models\WorkflowDefinition::where('workflow_id', $requestARF->forward_workflow_id)
            ->where('is_enabled', 1)
            ->orderBy('approval_order', 'asc')
            ->get();

        $approvalLevels = [];
        foreach ($levels as $level) {
            $isCurrentLevel = $level->approval_order == $requestARF->approval_level;
            $isCompleted = $requestARF->approval_level > $level->approval_order;
            $isPending = $requestARF->approval_level == $level->approval_order && $requestARF->overall_status === 'pending';
            
            $approver = null;
            if ($level->is_division_specific && $requestARF->division) {
                $staffId = $requestARF->division->{$level->division_reference_column} ?? null;
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
     * Show the form for editing the specified ARF request.
     */
    public function edit($request_arf): View
    {
        $requestARF = RequestARF::find($request_arf);
        
        if (!$requestARF) {
            abort(404, 'ARF request not found');
        }
        
        $staff = Staff::active()->get();
        $divisions = Division::all();
        $workflows = Workflow::all();
        $locations = Location::all();
        
        return view('request-arf.edit', compact('requestARF', 'staff', 'divisions', 'workflows', 'locations'));
    }

    /**
     * Update the specified ARF request.
     */
    public function update(Request $request, $request_arf): RedirectResponse
    {
        $requestARF = RequestARF::find($request_arf);
        
        if (!$requestARF) {
            abort(404, 'ARF request not found');
        }
        
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,staff_id',
            'forward_workflow_id' => 'required|exists:workflows,id',
            'reverse_workflow_id' => 'required|exists:workflows,id',
            'arf_number' => 'required|string|unique:request_arfs,arf_number,' . $requestARF->id,
            'request_date' => 'required|date',
            'division_id' => 'required|exists:divisions,id',
            'location_id' => 'required|array',
            'activity_title' => 'required|string|max:255',
            'purpose' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'requested_amount' => 'required|numeric|min:0',
            'accounting_code' => 'required|string|max:255',
            'budget_breakdown' => 'required|array',
            'attachment' => 'nullable|array',
            'attachment.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'status' => 'sometimes|in:draft,submitted,approved,rejected',
        ]);
        
        // Handle attachments update
        $existingAttachments = $requestARF->attachment ?? [];
        $attachments = $existingAttachments;
        
        // Process new attachments
        if ($request->hasFile('attachment')) {
            foreach ($request->file('attachment') as $file) {
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('arf-attachments', $filename, 'public');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ];
            }
        }
        
        $validated['attachment'] = $attachments;
        
        // Clean Unicode characters from text fields
        if (isset($validated['activity_title'])) {
            $validated['activity_title'] = clean_unicode($validated['activity_title']);
        }
        if (isset($validated['purpose'])) {
            $validated['purpose'] = clean_unicode($validated['purpose']);
        }
        
        // Set approval levels and overall status based on status
        if (isset($validated['status'])) {
            if ($validated['status'] === 'submitted') {
                $validated['approval_level'] = 1;
                $validated['next_approval_level'] = 2;
                $validated['overall_status'] = 'pending';
            } elseif ($validated['status'] === 'draft') {
                $validated['approval_level'] = 0;
                $validated['next_approval_level'] = null;
                $validated['overall_status'] = 'draft';
            }
        }
        
        $requestARF->update($validated);
        
        return redirect()
            ->route('request-arf.index')
            ->with('success', 'ARF request updated successfully.');
    }

    /**
     * Remove the specified ARF request.
     */
    public function destroy(RequestARF $requestARF): RedirectResponse
    {
        try {
            DB::beginTransaction();
            
            // Delete related attachments from storage
            if (!empty($requestARF->attachment)) {
                foreach ($requestARF->attachment as $attachment) {
                    if (isset($attachment['path'])) {
                        Storage::disk('public')->delete($attachment['path']);
                    }
                }
            }
            
            // Delete approval trails
            \App\Models\ApprovalTrail::where('model_type', 'App\\Models\\RequestARF')
                ->where('model_id', $requestARF->id)
                ->delete();
            
            $requestARF->delete();
            
            DB::commit();
            
            return redirect()
                ->route('request-arf.index')
                ->with('success', 'ARF request deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting ARF request', ['exception' => $e]);
            
            return redirect()
                ->route('request-arf.index')
                ->with('error', 'An error occurred while deleting the ARF request.');
        }
    }
    
    /**
     * Remove a specific attachment from an ARF request.
     */
    public function removeAttachment(Request $request, RequestARF $requestARF): RedirectResponse
    {
        $validated = $request->validate([
            'attachment_index' => 'required|integer',
        ]);
        
        $index = $validated['attachment_index'];
        $attachments = $requestARF->attachment ?? [];
        
        if (isset($attachments[$index])) {
            $attachment = $attachments[$index];
            
            // Delete file from storage
            if (isset($attachment['path'])) {
                Storage::disk('public')->delete($attachment['path']);
            }
            
            // Remove from the array
            unset($attachments[$index]);
            
            // Reindex array
            $attachments = array_values($attachments);
            
            // Update record
            $requestARF->update(['attachment' => $attachments]);
            
            return redirect()
                ->back()
                ->with('success', 'Attachment removed successfully.');
        }
        
        return redirect()
            ->back()
            ->with('error', 'Attachment not found.');
    }

    /**
     * Handle approval actions for ARF requests.
     */
    public function approve(Request $request, RequestARF $requestARF): RedirectResponse
    {
        $validationRules = [
            'action' => 'required|in:approved,rejected,returned',
            'comment' => 'nullable|string|max:1000',
        ];

        // Add funder validation only for approved action
        if ($request->action === 'approved') {
            $validationRules['funder_id'] = 'required|exists:funders,id';
            $validationRules['extramural_code'] = 'required|string|max:255';
        }

        $request->validate($validationRules);

        // Update ARF with funder information if approved
        if ($request->action === 'approved') {
            $requestARF->update([
                'funder_id' => $request->funder_id,
                'extramural_code' => $request->extramural_code,
            ]);
        }

        // Use the generic approval system
        $genericController = app(\App\Http\Controllers\GenericApprovalController::class);
        return $genericController->updateStatus($request, 'RequestARF', $requestARF->id);
    }

    /**
     * Print ARF request as PDF.
     */
    public function print(Request $request, RequestARF $requestARF)
    {
        // Load essential ARF relationships
        $requestARF->load(['staff', 'fundType', 'responsiblePerson', 'funder', 'approvalTrails.staff', 'approvalTrails.approverRole']);
        
        // Get source data using model_type and source_id
        $sourceModel = null;
        $sourceData = [
            'title' => 'N/A',
            'start_date' => null,
            'end_date' => null,
            'location' => 'N/A',
            'division' => null,
            'division_head' => null,
            'responsible_person' => null,
            'budget_breakdown' => [],
            'internal_participants' => [],
            'activity_request_remarks' => 'N/A',
            'total_budget' => 0,
            'matrix_id' => null,
        ];
        
        // Use the model's getSourceModel method
        $sourceModel = $requestARF->getSourceModel();
        
        if ($sourceModel) {
            // Load necessary relationships based on model type
            if ($requestARF->model_type === 'App\\Models\\Activity') {
                // Check if it's a single memo activity
                $isSingleMemo = $sourceModel->is_single_memo ?? false;
                
                if ($isSingleMemo) {
                    // For single memo activities, load polymorphic approval trails from approval_trails table
                    // Also load matrix approval trails for fallback if single memo trails don't have Grants approver
                    $sourceModel->load(['matrix.division.divisionHead', 'matrix.approvalTrails.staff', 'matrix.approvalTrails.oicStaff', 'matrix.approvalTrails.approverRole', 'staff', 'activity_budget']);
                    // Load approval trails from the polymorphic approval_trails table
                    // Include approverRole relationship and filter by workflow_id if available
                    $approvalTrailsQuery = \App\Models\ApprovalTrail::where('model_type', 'App\\Models\\Activity')
                        ->where('model_id', $sourceModel->id)
                        ->where('is_archived', 0)
                        ->with(['staff', 'oicStaff', 'approverRole']);
                    
                    // Filter by workflow_id if available
                    if ($sourceModel->forward_workflow_id) {
                        $approvalTrailsQuery->where('forward_workflow_id', $sourceModel->forward_workflow_id);
                    }
                    
                    $approvalTrails = $approvalTrailsQuery->orderBy('approval_order')
                        ->orderBy('created_at')
                        ->get();
                } else {
                    // For matrix activities, load activity approval trails (activities use ActivityApprovalTrail table)
                    $sourceModel->load(['matrix.division.divisionHead', 'matrix.matrixApprovalTrails.staff', 'matrix.matrixApprovalTrails.approverRole', 'staff', 'activity_budget', 'activityApprovalTrails.staff', 'activityApprovalTrails.approverRole']);
                    $approvalTrails = $sourceModel->activityApprovalTrails;
                }
                
                // Get fund codes for budget display
                $fundCodes = [];
                if ($sourceModel->budget_id) {
                    $budgetIds = is_string($sourceModel->budget_id) ? json_decode($sourceModel->budget_id, true) : $sourceModel->budget_id;
                    if (is_array($budgetIds)) {
                        $fundCodes = \App\Models\FundCode::whereIn('id', $budgetIds)->with('fundType')->get()->keyBy('id');
                    }
                }
                
                $sourceData = [
                    'title' => $sourceModel->activity_title ?? 'N/A',
                    'start_date' => $sourceModel->date_from ?? null,
                    'end_date' => $sourceModel->date_to ?? null,
                    'location' => $sourceModel->locations() ? $sourceModel->locations()->pluck('name')->join(', ') : 'N/A',
                    'division' => $sourceModel->matrix_id ? ($sourceModel->matrix->division ?? null) : null,
                    'division_head' => $sourceModel->matrix_id ? ($sourceModel->matrix->division->divisionHead ?? null) : null,
                    'responsible_person' => $sourceModel->staff ?? null,
                    'budget_breakdown' => is_string($sourceModel->budget_breakdown) ? json_decode($sourceModel->budget_breakdown, true) ?? [] : ($sourceModel->budget_breakdown ?? []),
                    'fund_codes' => $fundCodes,
                    'internal_participants' => is_string($sourceModel->internal_participants) ? json_decode($sourceModel->internal_participants, true) ?? [] : ($sourceModel->internal_participants ?? []),
                    'activity_request_remarks' => $sourceModel->activity_request_remarks ?? 'N/A',
                    'total_budget' => $sourceModel->total_budget ?? 0,
                    'matrix_id' => $sourceModel->matrix_id ?? null,
                    'matrix' => $sourceModel->matrix ?? null, // Include the matrix object with approval trails
                    'approval_trails' => $approvalTrails,
                    'is_single_memo' => $isSingleMemo,
                    'forward_workflow_id' => $sourceModel->forward_workflow_id ?? 1,
                    'created_at' => $sourceModel->created_at,
                    'updated_at' => $sourceModel->updated_at,
                ];
            } elseif ($requestARF->model_type === 'App\\Models\\NonTravelMemo') {
                $sourceModel->load(['division.divisionHead', 'staff', 'fundType', 'approvalTrails.staff', 'approvalTrails.approverRole']);
                
                // Get fund codes for budget display
                $fundCodes = [];
                if ($sourceModel->budget_id) {
                    $budgetIds = is_string($sourceModel->budget_id) ? json_decode($sourceModel->budget_id, true) : $sourceModel->budget_id;
                    if (is_array($budgetIds)) {
                        $fundCodes = \App\Models\FundCode::whereIn('id', $budgetIds)->with('fundType', 'funder')->get()->keyBy('id');
                    }
                }
                
                $sourceData = [
                    'title' => $this->formatArfTitle($sourceModel, 'Non-Travel Memo'),
                    'start_date' => $sourceModel->date_from ?? null,
                    'end_date' => $sourceModel->date_to ?? null,
                    'location' => $sourceModel->location ?? 'N/A',
                    'division' => $sourceModel->division ?? null,
                    'division_head' => $sourceModel->division->divisionHead ?? null,
                    'responsible_person' => $sourceModel->staff ?? null,
                    'budget_breakdown' => is_string($sourceModel->budget_breakdown) ? json_decode($sourceModel->budget_breakdown, true) ?? [] : ($sourceModel->budget_breakdown ?? []),
                    'fund_codes' => $fundCodes, // Add fund codes for proper display
                    'internal_participants' => [],
                    'activity_request_remarks' => $sourceModel->activity_request_remarks ?? 'N/A',
                    'total_budget' => $sourceModel->total_budget ?? 0,
                    'matrix_id' => null,
                    'approval_trails' => $sourceModel->approvalTrails,
                    'created_at' => $sourceModel->created_at,
                    'updated_at' => $sourceModel->updated_at,
                ];
            } elseif ($requestARF->model_type === 'App\\Models\\SpecialMemo') {
                $sourceModel->load(['division.divisionHead', 'staff', 'fundType', 'approvalTrails.staff', 'approvalTrails.approverRole']);
                
                // Get fund codes for budget display
                $fundCodes = [];
                if ($sourceModel->budget_id) {
                    $budgetIds = is_string($sourceModel->budget_id) ? json_decode($sourceModel->budget_id, true) : $sourceModel->budget_id;
                    if (is_array($budgetIds)) {
                        $fundCodes = \App\Models\FundCode::whereIn('id', $budgetIds)->with('fundType', 'funder')->get()->keyBy('id');
                    }
                }
                
                $sourceData = [
                    'title' => $this->formatArfTitle($sourceModel, 'Special Memo'),
                    'start_date' => $sourceModel->date_from ?? null,
                    'end_date' => $sourceModel->date_to ?? null,
                    'location' => $sourceModel->location ?? 'N/A',
                    'division' => $sourceModel->division ?? null,
                    'division_head' => $sourceModel->division->divisionHead ?? null,
                    'responsible_person' => $sourceModel->staff ?? null,
                    'budget_breakdown' => is_string($sourceModel->budget_breakdown) ? json_decode($sourceModel->budget_breakdown, true) ?? [] : ($sourceModel->budget_breakdown ?? []),
                    'fund_codes' => $fundCodes, // Add fund codes for proper display
                    'internal_participants' => is_string($sourceModel->internal_participants) ? json_decode($sourceModel->internal_participants, true) ?? [] : ($sourceModel->internal_participants ?? []),
                    'activity_request_remarks' => $sourceModel->activity_request_remarks ?? 'N/A',
                    'total_budget' => $sourceModel->total_budget ?? 0,
                    'matrix_id' => null,
                    'approval_trails' => $sourceModel->approvalTrails,
                    'created_at' => $sourceModel->created_at,
                    'updated_at' => $sourceModel->updated_at,
                ];
            } elseif ($requestARF->model_type === 'App\\Models\\ChangeRequest') {
                $sourceModel->load(['division.divisionHead', 'staff', 'fundType', 'matrix.division.divisionHead', 'matrix.matrixApprovalTrails.staff', 'matrix.matrixApprovalTrails.approverRole', 'approvalTrails.staff', 'approvalTrails.approverRole']);
                
                // Get fund codes for budget display
                $fundCodes = [];
                if ($sourceModel->budget_id) {
                    $budgetIds = is_string($sourceModel->budget_id) ? json_decode($sourceModel->budget_id, true) : $sourceModel->budget_id;
                    if (is_array($budgetIds)) {
                        $fundCodes = \App\Models\FundCode::whereIn('id', $budgetIds)->with('fundType', 'funder')->get()->keyBy('id');
                    }
                }
                
                $sourceData = [
                    'title' => $this->formatArfTitle($sourceModel, 'Change Request'),
                    'start_date' => $sourceModel->date_from ?? null,
                    'end_date' => $sourceModel->date_to ?? null,
                    'location' => $sourceModel->location ?? 'N/A',
                    'division' => $sourceModel->division ?? null,
                    'division_head' => $sourceModel->division->divisionHead ?? null,
                    'responsible_person' => $sourceModel->staff ?? null,
                    'budget_breakdown' => is_string($sourceModel->budget_breakdown) ? json_decode($sourceModel->budget_breakdown, true) ?? [] : ($sourceModel->budget_breakdown ?? []),
                    'fund_codes' => $fundCodes,
                    'internal_participants' => [],
                    'activity_request_remarks' => $sourceModel->activity_request_remarks ?? 'N/A',
                    'total_budget' => $sourceModel->total_budget ?? 0,
                    'matrix_id' => $sourceModel->matrix_id ?? null,
                    'matrix' => $sourceModel->matrix ?? null, // Include matrix object with approval trails
                    'approval_trails' => $sourceModel->approvalTrails,
                    'created_at' => $sourceModel->created_at,
                    'updated_at' => $sourceModel->updated_at,
                ];
            }
        }
        
        // Prepare data for PDF
        $data = [
            'requestARF' => $requestARF,
            'sourceData' => $sourceData,
            'sourceModel' => $sourceModel,
            'fundCodes' => $sourceData['fund_codes'] ?? collect(),
            'internalParticipants' => $sourceData['internal_participants'] ?? [],
            'budgetBreakdown' => $sourceData['budget_breakdown'] ?? [],
        ];
        //dd($sourceData);
        // Generate PDF using the custom generate_pdf function
        $mpdf = generate_pdf('request-arf.arf-pdf-simple', $data);
        
        $filename = 'ARF_' . $requestARF->arf_number . '_' . date('Y-m-d') . '.pdf';
        
        return $mpdf->Output($filename, 'I'); // 'D' for download
    }

    /**
     * Export my submitted ARF requests to CSV.
     */
    public function exportMySubmittedCsv(Request $request)
    {
        // Placeholder for future implementation
        return redirect()->back()->with('info', 'Export functionality will be implemented soon.');
    }

    /**
     * Export all ARF requests to CSV.
     */
    public function exportAllCsv(Request $request)
    {
        // Placeholder for future implementation  
        return redirect()->back()->with('info', 'Export functionality will be implemented soon.');
    }

    /**
     * Submit ARF request for approval.
     */
    public function submitForApproval(Request $request, $request_arf): RedirectResponse
    {
        $requestARF = RequestARF::find($request_arf);
        
        if (!$requestARF) {
            abort(404, 'ARF request not found');
        }

        // Check if user can submit this ARF
        if (!is_with_creator_generic($requestARF)) {
            return redirect()->back()->with('error', 'You are not authorized to submit this ARF request.');
        }

        // Check if ARF is in a submittable state
        if (!in_array($requestARF->overall_status, ['draft', 'returned'])) {
            return redirect()->back()->with('error', 'This ARF request cannot be submitted in its current state.');
        }

        try {
            // Get assigned workflow ID for RequestARF model
            $assignedWorkflowId = WorkflowModel::getWorkflowIdForModel('RequestARF');
            
            // Debug logging
            Log::info('ARF Workflow Debug', [
                'assigned_workflow_id' => $assignedWorkflowId,
                'model_name' => 'RequestARF',
                'workflow_assignments' => WorkflowModel::where('model_name', 'RequestARF')->get()->toArray()
            ]);
            
            if (!$assignedWorkflowId) {
                $assignedWorkflowId = 2; // Default workflow ID
                Log::warning('No workflow assignment found for RequestARF model, using default workflow ID: 2');
            }

            // Update status to pending and set workflow
            $requestARF->update([
                'overall_status' => 'pending',
                'approval_level' => 1,
                'next_approval_level' => 2,
                'forward_workflow_id' => $assignedWorkflowId,
                'reverse_workflow_id' => $assignedWorkflowId,
            ]);

            return redirect()->back()->with('success', 'ARF request submitted for approval successfully!');
        } catch (\Exception $e) {
            Log::error('ARF submission failed', [
                'arf_id' => $requestARF->id,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->back()->with('error', 'Failed to submit ARF request. Please try again.');
        }
    }

    /**
     * Update ARF request status.
     */
    public function updateStatus(Request $request, $request_arf): RedirectResponse
    {
        $requestARF = RequestARF::find($request_arf);
        
        if (!$requestARF) {
            abort(404, 'ARF request not found');
        }

        $request->validate([
            'status' => 'required|in:draft,pending,approved,rejected,returned',
            'comment' => 'nullable|string|max:1000'
        ]);

        try {
            $requestARF->update([
                'overall_status' => $request->status,
                'updated_at' => now()
            ]);

            // Log the status change
            Log::info('ARF status updated', [
                'arf_id' => $requestARF->id,
                'new_status' => $request->status,
                'comment' => $request->comment
            ]);

            return redirect()->back()->with('success', 'ARF request status updated successfully!');
        } catch (\Exception $e) {
            Log::error('ARF status update failed', [
                'arf_id' => $requestARF->id,
                'error' => $e->getMessage()
            ]);
            
            return redirect()->back()->with('error', 'Failed to update ARF request status. Please try again.');
        }
    }

    /**
     * Show pending approvals for ARF requests
     */
    public function pendingApprovals(Request $request)
    {
        $userStaffId = user_session('staff_id');

        // Check if we have valid session data
        if (!$userStaffId) {
            return view('request-arf.pending-approvals', [
                'pendingArfs' => collect(),
                'approvedByMe' => collect(),
                'divisions' => collect(),
                'error' => 'No session data found. Please log in again.'
            ]);
        }

        // Use the exact same logic as the home helper for consistency
        $userDivisionId = user_session('division_id');
        
        // Get filter parameters
        $documentNumber = $request->get('document_number');
        $divisionId = $request->get('division_id');
        $staffId = $request->get('staff_id');
        
        // Base query for pending ARF requests
        $pendingQuery = RequestARF::with([
            'staff',
            'division',
            'forwardWorkflow.workflowDefinitions.approvers.staff',
            'forwardWorkflow.workflowDefinitions'
        ])
        ->where('overall_status', 'pending')
        ->where('forward_workflow_id', '!=', null)
        ->where('approval_level', '>', 0);

        $pendingQuery->where(function($q) use ($userDivisionId, $userStaffId) {
            // Check if user can approve at current level
            $q->whereHas('forwardWorkflow.workflowDefinitions', function($workflowQuery) use ($userDivisionId, $userStaffId) {
                $workflowQuery->where('is_enabled', 1)
                ->whereColumn('approval_order', 'request_arfs.approval_level')
                ->where(function($approverQuery) use ($userDivisionId, $userStaffId) {
                    // Division-specific approvers
                    $approverQuery->where(function($divQuery) use ($userDivisionId, $userStaffId) {
                        $divQuery->where('is_division_specific', 1)
                            ->whereHas('approvers', function($approverSubQuery) use ($userStaffId) {
                                $approverSubQuery->where('staff_id', $userStaffId);
                            });
                    })
                    // General approvers
                    ->orWhere(function($genQuery) use ($userStaffId) {
                        $genQuery->where('is_division_specific', 0)
                            ->whereHas('approvers', function($approverSubQuery) use ($userStaffId) {
                                $approverSubQuery->where('staff_id', $userStaffId);
                            });
                    });
                });
            });
        });

        // Apply filters
        if ($documentNumber) {
            $pendingQuery->where(function($q) use ($documentNumber) {
                $q->where('document_number', 'like', "%{$documentNumber}%")
                  ->orWhere('arf_number', 'like', "%{$documentNumber}%");
            });
        }
        
        if ($divisionId) {
            $pendingQuery->where('division_id', $divisionId);
        }
        
        if ($staffId) {
            $pendingQuery->where('staff_id', $staffId);
        }

        // Get pending ARF requests
        $pendingArfs = $pendingQuery->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        // Get approved by me ARF requests
        $approvedByMeQuery = RequestARF::with([
            'staff',
            'division',
            'forwardWorkflow.workflowDefinitions.approvers.staff'
        ])
        ->where('overall_status', 'approved')
        ->whereHas('approvalTrails', function($q) use ($userStaffId) {
            $q->where('staff_id', $userStaffId)
              ->where('action', 'approved');
        });

        // Apply same filters to approved by me
        if ($documentNumber) {
            $approvedByMeQuery->where(function($q) use ($documentNumber) {
                $q->where('document_number', 'like', "%{$documentNumber}%")
                  ->orWhere('arf_number', 'like', "%{$documentNumber}%");
            });
        }
        
        if ($divisionId) {
            $approvedByMeQuery->where('division_id', $divisionId);
        }
        
        if ($staffId) {
            $approvedByMeQuery->where('staff_id', $staffId);
        }

        $approvedByMe = $approvedByMeQuery->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        // Get divisions for filter
        $divisions = Division::orderBy('division_name')->get();

        // Handle AJAX requests for tab content
        if ($request->ajax()) {
            $tab = $request->get('tab', '');
            $html = '';
            
            switch($tab) {
                case 'pending':
                    $html = view('request-arf.partials.pending-approvals-tab', compact('pendingArfs'))->render();
                    break;
                case 'approved':
                    $html = view('request-arf.partials.approved-by-me-tab', compact('approvedByMe'))->render();
                    break;
            }
            
            return response()->json(['html' => $html]);
        }

        return view('request-arf.pending-approvals', [
            'pendingArfs' => $pendingArfs,
            'approvedByMe' => $approvedByMe,
            'divisions' => $divisions
        ]);
    }
}