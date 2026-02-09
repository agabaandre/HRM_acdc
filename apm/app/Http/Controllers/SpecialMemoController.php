<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityApprovalTrail;
use App\Models\Matrix;
use Illuminate\Support\Facades\DB;
use App\Models\RequestType;
use App\Models\FundType;
use App\Models\FundCode;
use App\Models\Location;
use App\Models\Staff;
use App\Models\CostItem;
use App\Models\SpecialMemo;
use App\Models\WorkflowModel;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use App\Models\Division;
use App\Models\Workflow;        
use App\Models\Approver;
use App\Models\WorkflowDefinition;
use App\Models\FundCodeTransaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class SpecialMemoController extends Controller
{
    public function index(Request $request)
    {
        // Cache lookup tables for 60 minutes
        $staff = Cache::remember('special_memo_staff', 60 * 60, fn() => Staff::active()->get());
        $divisions = Cache::remember('special_memo_divisions', 60 * 60, fn() => \App\Models\Division::all());
        $requestTypes = Cache::remember('special_memo_request_types', 60 * 60, fn() => RequestType::all());

        // Get current user's staff ID
        $currentStaffId = user_session('staff_id');
        $userDivisionId = user_session('division_id');

        // Year filter: default to current year when missing; filter by created_at; results ordered by created_at desc (recent first)
        $currentYear = (int) date('Y');
        $year = $request->get('year');
        if ($year === null || $year === '') {
            $year = (string) $currentYear;
        }
        $year = (string) $year;
        if ($year !== 'all' && is_numeric($year) && (int) $year === 0) {
            $year = (string) $currentYear;
        }

        // Tab 1: My Submitted Special Memos (memos where current user is creator OR responsible person)
        $mySubmittedQuery = SpecialMemo::with([
            'staff',
            'division',
            'requestType',
            'fundType',
            'forwardWorkflow.workflowDefinitions.approvers.staff'
        ])
            ->where(function ($q) use ($currentStaffId) {
                $q->where('staff_id', $currentStaffId)
                  ->orWhere('responsible_person_id', $currentStaffId);
            });

        if ($year !== '' && $year !== 'all' && (int) $year > 0) {
            $mySubmittedQuery->whereYear('created_at', (int) $year);
        }

        // Apply filters to my submitted memos
        if ($request->filled('request_type_id')) {
            $mySubmittedQuery->where('request_type_id', $request->request_type_id);
        }
        if ($request->filled('division_id')) {
            $mySubmittedQuery->where('division_id', $request->division_id);
        }
        if ($request->filled('status')) {
            $mySubmittedQuery->where('overall_status', $request->status);
        }
        if ($request->filled('document_number')) {
            $mySubmittedQuery->where('document_number', 'like', '%' . $request->document_number . '%');
        }
        if ($request->filled('search')) {
            $mySubmittedQuery->where('activity_title', 'like', '%' . $request->search . '%');
        }

        $mySubmittedMemos = $mySubmittedQuery->orderByDesc('created_at')->paginate(20)->withQueryString();

        // Tab 2: All Special Memos (visible to users with permission 87)
        $allMemos = collect();
        if (in_array(87, user_session('permissions', []))) {
            $allMemosQuery = SpecialMemo::with([
                'staff',
                'division',
                'requestType',
                'fundType',
                'forwardWorkflow.workflowDefinitions.approvers.staff'
            ]);

            if ($year !== '' && $year !== 'all' && (int) $year > 0) {
                $allMemosQuery->whereYear('created_at', (int) $year);
            }

            // Apply filters to all memos
            if ($request->filled('staff_id')) {
                $allMemosQuery->where('staff_id', $request->staff_id);
            }
            if ($request->filled('request_type_id')) {
                $allMemosQuery->where('request_type_id', $request->request_type_id);
            }
            if ($request->filled('division_id')) {
                $allMemosQuery->where('division_id', $request->division_id);
            }
            if ($request->filled('status')) {
                $allMemosQuery->where('overall_status', $request->status);
            }
            if ($request->filled('document_number')) {
                $allMemosQuery->where('document_number', 'like', '%' . $request->document_number . '%');
            }
            if ($request->filled('search')) {
                $allMemosQuery->where('activity_title', 'like', '%' . $request->search . '%');
            }

            $allMemos = $allMemosQuery->orderByDesc('created_at')->paginate(20)->withQueryString();
        }

        // Tab 3: Shared Special Memos (memos where current user is added as participant but not creator)
        $sharedMemosQuery = SpecialMemo::with([
            'staff',
            'division',
            'requestType',
            'fundType',
            'forwardWorkflow.workflowDefinitions.approvers.staff'
        ])
            ->where('staff_id', '!=', $currentStaffId)
            ->whereJsonContains('internal_participants', $currentStaffId);

        if ($year !== '' && $year !== 'all' && (int) $year > 0) {
            $sharedMemosQuery->whereYear('created_at', (int) $year);
        }

        // Apply filters to shared memos
        if ($request->filled('request_type_id')) {
            $sharedMemosQuery->where('request_type_id', $request->request_type_id);
        }
        if ($request->filled('division_id')) {
            $sharedMemosQuery->where('division_id', $request->division_id);
        }
        if ($request->filled('status')) {
            $sharedMemosQuery->where('overall_status', $request->status);
        }
        if ($request->filled('staff_id')) {
            $sharedMemosQuery->where('staff_id', $request->staff_id);
        }
        if ($request->filled('document_number')) {
            $sharedMemosQuery->where('document_number', 'like', '%' . $request->document_number . '%');
        }
        if ($request->filled('search')) {
            $sharedMemosQuery->where('activity_title', 'like', '%' . $request->search . '%');
        }

        $sharedMemos = $sharedMemosQuery->orderByDesc('created_at')->paginate(20)->withQueryString();

        // Handle AJAX requests for tab content
        if ($request->ajax()) {
            $tab = $request->get('tab', '');
            $html = '';

            $year = $request->query('year');
            if ($year === null || $year === '') {
                $year = (string) (int) date('Y');
            }
            $year = (string) $year;
            if ($year !== 'all' && is_numeric($year) && (int) $year === 0) {
                $year = (string) (int) date('Y');
            }

            $mySubmittedQueryAjax = SpecialMemo::with([
                'staff', 'division', 'requestType', 'fundType',
                'forwardWorkflow.workflowDefinitions.approvers.staff'
            ])->where(function ($q) use ($currentStaffId) {
                $q->where('staff_id', $currentStaffId)
                  ->orWhere('responsible_person_id', $currentStaffId);
            });
            if ($year !== '' && $year !== 'all' && (int) $year > 0) {
                $mySubmittedQueryAjax->whereYear('created_at', (int) $year);
            }
            if ($request->filled('request_type_id')) {
                $mySubmittedQueryAjax->where('request_type_id', $request->request_type_id);
            }
            if ($request->filled('division_id')) {
                $mySubmittedQueryAjax->where('division_id', $request->division_id);
            }
            if ($request->filled('status')) {
                $mySubmittedQueryAjax->where('overall_status', $request->status);
            }
            if ($request->filled('document_number')) {
                $mySubmittedQueryAjax->where('document_number', 'like', '%' . $request->document_number . '%');
            }
            if ($request->filled('search')) {
                $mySubmittedQueryAjax->where('activity_title', 'like', '%' . $request->search . '%');
            }
            $mySubmittedMemos = $mySubmittedQueryAjax->orderByDesc('created_at')->paginate(20)->withQueryString();

            $allMemos = collect();
            if (in_array(87, user_session('permissions', []))) {
                $allMemosQueryAjax = SpecialMemo::with([
                    'staff', 'division', 'requestType', 'fundType',
                    'forwardWorkflow.workflowDefinitions.approvers.staff'
                ]);
                if ($year !== '' && $year !== 'all' && (int) $year > 0) {
                    $allMemosQueryAjax->whereYear('created_at', (int) $year);
                }
                if ($request->filled('staff_id')) {
                    $allMemosQueryAjax->where('staff_id', $request->staff_id);
                }
                if ($request->filled('request_type_id')) {
                    $allMemosQueryAjax->where('request_type_id', $request->request_type_id);
                }
                if ($request->filled('division_id')) {
                    $allMemosQueryAjax->where('division_id', $request->division_id);
                }
                if ($request->filled('status')) {
                    $allMemosQueryAjax->where('overall_status', $request->status);
                }
                if ($request->filled('document_number')) {
                    $allMemosQueryAjax->where('document_number', 'like', '%' . $request->document_number . '%');
                }
                if ($request->filled('search')) {
                    $allMemosQueryAjax->where('activity_title', 'like', '%' . $request->search . '%');
                }
                $allMemos = $allMemosQueryAjax->orderByDesc('created_at')->paginate(20)->withQueryString();
            }

            $sharedMemosQueryAjax = SpecialMemo::with([
                'staff', 'division', 'requestType', 'fundType',
                'forwardWorkflow.workflowDefinitions.approvers.staff'
            ])->where('staff_id', '!=', $currentStaffId)->whereJsonContains('internal_participants', $currentStaffId);
            if ($year !== '' && $year !== 'all' && (int) $year > 0) {
                $sharedMemosQueryAjax->whereYear('created_at', (int) $year);
            }
            if ($request->filled('request_type_id')) {
                $sharedMemosQueryAjax->where('request_type_id', $request->request_type_id);
            }
            if ($request->filled('division_id')) {
                $sharedMemosQueryAjax->where('division_id', $request->division_id);
            }
            if ($request->filled('status')) {
                $sharedMemosQueryAjax->where('overall_status', $request->status);
            }
            if ($request->filled('staff_id')) {
                $sharedMemosQueryAjax->where('staff_id', $request->staff_id);
            }
            if ($request->filled('document_number')) {
                $sharedMemosQueryAjax->where('document_number', 'like', '%' . $request->document_number . '%');
            }
            if ($request->filled('search')) {
                $sharedMemosQueryAjax->where('activity_title', 'like', '%' . $request->search . '%');
            }
            $sharedMemos = $sharedMemosQueryAjax->orderByDesc('created_at')->paginate(20)->withQueryString();

            $countMySubmitted = $mySubmittedMemos->total();
            $countAllMemos = $allMemos instanceof \Illuminate\Pagination\LengthAwarePaginator ? $allMemos->total() : $allMemos->count();
            $countSharedMemos = $sharedMemos->total();

            switch($tab) {
                case 'mySubmitted':
                    $html = view('special-memo.partials.my-submitted-tab', compact(
                        'mySubmittedMemos'
                    ))->render();
                    break;
                case 'allMemos':
                    $html = view('special-memo.partials.all-memos-tab', compact(
                        'allMemos'
                    ))->render();
                    break;
                case 'sharedMemos':
                    $html = view('special-memo.partials.shared-memos-tab', compact(
                        'sharedMemos'
                    ))->render();
                    break;
            }

            return response()->json([
                'html' => $html,
                'count_my_submitted' => $countMySubmitted,
                'count_all_memos' => $countAllMemos,
                'count_shared_memos' => $countSharedMemos,
            ]);
        }

        // Preserve year keys; lowest year is 2025 (system start)
        $currentYear = (int) date('Y');
        $minYear = max(2025, $currentYear - 10);
        $yearRange = range($currentYear, $minYear);
        $years = ['all' => 'All years'] + array_combine($yearRange, $yearRange);

        return view('special-memo.index', compact(
            'mySubmittedMemos',
            'allMemos',
            'sharedMemos',
            'staff',
            'requestTypes',
            'divisions',
            'currentStaffId',
            'userDivisionId',
            'year',
            'years'
        ));
    }
    

    /**
     * Show the form for creating a new special memo.
     */
    public function create(): View
    {
        ini_set('memory_limit', '1024M');
        $division_id = user_session('division_id');
      
        // Request Types
        $requestTypes = RequestType::all();
    
        // All staff in the system for responsible person (with job details)
        $staff =  Staff::active()
            ->select(['id', 'fname','lname','staff_id', 'division_id', 'division_name', 'job_name', 'duty_station_name'])
            ->get();
    
        // Staff only from current division for internal participants
        $divisionStaff =  Staff::active()
            ->select(['id', 'fname','lname','staff_id', 'division_id', 'division_name'])
            ->where('division_id', $division_id)
            ->get();
    
        // All staff grouped by division for external participants
        $allStaff =  Staff::active()
            ->select(['id', 'fname','lname','staff_id', 'division_id', 'division_name'])
            ->where('division_id', '!=', $division_id)
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
    
        return view('special-memo.create', [
            'specialMemo' => null, // Pass null for new special memo
            'requestTypes' => $requestTypes,
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

    /**
     * Store a newly created special memo.
     */
    public function store(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $userStaffId = session('user.auth_staff_id');
        $userDivisionId = session('user.division_id');
    
        $validated = $request->validate([
            'activity_title' => 'required|string|max:200',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'background' => 'required|string',
            'justification' => 'required|string',
            'supporting_reasons' => 'required|string',
            'location_id' => 'required|array|min:1',
            'location_id.*' => 'exists:locations,id',
            'participant_start' => 'required|array',
            'participant_end' => 'required|array',
            'participant_days' => 'required|array',
            'responsible_person_id' => 'required|exists:staff,staff_id',
            'fund_type_id' => 'required|exists:fund_types,id',
            'budget_codes' => 'nullable|array',
            'budget_codes.*' => 'exists:fund_codes,id',
            'activity_code' => 'nullable|string|max:255',
            'attachments.*.type' => 'required_with:attachments.*.file|string|max:255',
            'attachments.*.file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,ppt,pptx,xls,xlsx,doc,docx|max:10240',
            'attachments.*.replace' => 'nullable|boolean',
            'attachments.*.delete' => 'nullable|boolean',
        ]);

        // Custom validation: World Bank Activity Code is required when World Bank budget code (funder_id=1) is selected
        $budgetCodes = $request->input('budget_codes', []);
        $activityCode = $request->input('activity_code', '');
        
        if (!empty($budgetCodes)) {
            // Check if any selected budget code belongs to World Bank (funder_id = 1)
            $worldBankCodes = \App\Models\FundCode::whereIn('id', $budgetCodes)
                ->where('funder_id', 1)
                ->exists();
            
            if ($worldBankCodes && empty($activityCode)) {
                $errorMessage = 'World Bank Activity Code is required when World Bank budget code is selected.';
                
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => $errorMessage
                    ], 422);
                }
                
                return redirect()->back()->withInput()->with([
                    'msg' => $errorMessage,
                    'type' => 'error'
                ]);
            }
        }

        // Validate total participants and budget
        $totalParticipants = (int) $request->input('total_participants', 0);
        if ($totalParticipants <= 0) {
            $errorMessage = 'Cannot create special memo with zero or negative total participants.';
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
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
        $fundTypeId = (int) $request->input('fund_type_id', 1);
        
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
            $errorMessage = 'Cannot create special memo with zero or negative total budget.';
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 422);
            }
            
            return redirect()->back()->withInput()->with([
                'msg' => $errorMessage,
                'type' => 'error'
            ]);
        }
    
        try {
            DB::beginTransaction();
    
            // Reformat internal participants
            $internalParticipants = [];
            foreach ($request->participant_start as $staffId => $start) {
                $internalParticipants[$staffId] = [
                    'participant_start' => $start,
                    'participant_end' => $request->participant_end[$staffId] ?? null,
                    'participant_days' => $request->participant_days[$staffId] ?? null,
                ];
            }
    
            // Handle attachments
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
                        
                        // Store file in public/uploads/special-memos directory
                        $path = $file->storeAs('uploads/special-memos', $filename, 'public');
                        
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

            // Determine status based on action
            $action = $request->input('action', 'draft');
            $isDraft = ($action === 'draft');
            $overallStatus = $isDraft ? 'draft' : 'pending';

            // Get assigned workflow ID for SpecialMemo model
            $assignedWorkflowId = null;
            if (!$isDraft) {
                $assignedWorkflowId = 1; // Default workflow ID
                Log::warning('No workflow assignment found for SpecialMemo model, using default workflow ID: 1');
            }

            $specialMemo = SpecialMemo::create([
                'is_special_memo' => 1,
                'is_draft' => $isDraft,
                'staff_id' => $userStaffId,
                'division_id' => $userDivisionId,
                'responsible_person_id' => $request->input('responsible_person_id', 1),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'activity_title' => clean_unicode($request->input('activity_title')),
                'background' => clean_unicode($request->input('background', '')),
                'justification' => clean_unicode($request->input('justification', '')),
                'activity_request_remarks' => clean_unicode($request->input('activity_request_remarks', '')),
                'key_result_area' => clean_unicode($request->input('key_result_link', '-')),
                'request_type_id' => (int) $request->input('request_type_id', 1),
                'fund_type_id' => (int) $request->input('fund_type_id', 1),
                'workplan_activity_code' => $request->input('activity_code', ''),
                'forward_workflow_id' => $assignedWorkflowId, // Use assigned workflow ID
                'reverse_workflow_id' => $isDraft ? null : $assignedWorkflowId,
                'overall_status' => $overallStatus,
                'approval_level' => $isDraft ? 0 : 1, // Set approval level only when submitting
                'next_approval_level' => $isDraft ? 1 : 2, // Set next level only when submitting
                'total_participants' => (int) $request->input('total_participants', 0),
                'total_external_participants' => (int) $request->input('total_external_participants', 0),
    
                'location_id' => json_encode($request->input('location_id', [])),
                'internal_participants' => json_encode($internalParticipants),
    
                'budget_id' => json_encode($request->input('budget_codes', [])),
                'budget_breakdown' => json_encode($request->input('budget', [])),
                'attachment' => json_encode($attachments),
    
                'supporting_reasons' => clean_unicode($request->input('supporting_reasons', null)),
            ]);

            // Process fund code balance reductions and create transaction records
            if (!$isDraft && !empty($request->input('budget_codes')) && !empty($request->input('budget'))) {
                $budgetCodes = $request->input('budget_codes');
                $budgetItems = $request->input('budget');
                
                foreach ($budgetCodes as $codeId) {
                    if (isset($budgetItems[$codeId]) && is_array($budgetItems[$codeId])) {
                        $total = 0;
                        foreach ($budgetItems[$codeId] as $item) {
                            $qty = isset($item['units']) ? floatval($item['units']) : 1;
                            $unitCost = isset($item['unit_cost']) ? floatval($item['unit_cost']) : 0;
                            $total += $qty * $unitCost;
                        }
                        
                        if ($total > 0) {
                            // Get current balance before reduction
                            $fundCode = FundCode::find($codeId);
                            if ($fundCode) {
                                $balanceBefore = floatval($fundCode->budget_balance ?? 0);
                                $balanceAfter = $balanceBefore - $total;
                                
                                // Reduce fund code balance
                                $fundCode->decrement('budget_balance', $total);
                                
                                // For special memos, we'll just log the balance change
                                // FundCodeTransaction requires activity_id, matrix_id, and activity_budget_id
                                // which special memos don't have, so we'll skip creating the transaction record
                                // and just log the balance change
                                
                                // Log the balance change
                                \Illuminate\Support\Facades\Log::info('Fund code balance reduced for special memo', [
                                    'fund_code_id' => $codeId,
                                    'fund_code' => $fundCode->code,
                                    'special_memo_id' => $specialMemo->id,
                                    'amount_reduced' => $total,
                                    'balance_before' => $balanceBefore,
                                    'balance_after' => $balanceAfter,
                                    'staff_id' => $userStaffId,
                                    'activity_title' => clean_unicode($request->input('activity_title'))
                                ]);
                            }
                        }
                    }
                }
            }
    
            DB::commit();
    
            $message = ($action === 'submit') 
                ? 'Special Memo created and submitted for approval successfully.'
                : 'Special Memo saved as draft successfully.';
            
            // If it's an AJAX request, return JSON response
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'memo' => [
                        'id' => $specialMemo->id,
                        'title' => $specialMemo->activity_title,
                        'request_type' => $specialMemo->requestType->name ?? 'N/A',
                        'status' => $specialMemo->overall_status,
                        'date_from' => $specialMemo->date_from,
                        'date_to' => $specialMemo->date_to,
                        'total_budget' => $this->calculateTotalBudget($request->input('budget', [])),
                        'preview_url' => route('special-memo.show', $specialMemo->id)
                    ]
                ]);
            }
    
            return redirect()->route('special-memo.index')->with([
                'msg' => $message,
                'type' => 'success',
            ]);
    
        } catch (\Throwable $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Error creating special memo', ['exception' => $e]);
    
            return redirect()->back()->withInput()->with([
                'msg' => 'An error occurred while creating the special memo. Please try again.',
                'type' => 'error',
            ]);
        }
    }
    
    /**
     * Calculate total budget from budget array
     */
    private function calculateTotalBudget(array $budget): float
    {
        $total = 0;
        foreach ($budget as $codeId => $items) {
            if (is_array($items)) {
                foreach ($items as $item) {
                    $qty = isset($item['units']) ? floatval($item['units']) : 1;
                    $unitCost = isset($item['unit_cost']) ? floatval($item['unit_cost']) : 0;
                    $total += $qty * $unitCost;
                }
            }
        }
        return $total;
    }

    /**
     * Display the specified special memo.
     */
    public function show(SpecialMemo $specialMemo): View
    {
        $specialMemo->load(['staff', 'division', 'staff.division', 'responsiblePerson']);
        
        return view('special-memo.show', compact('specialMemo'));
    }

    /**
     * Show the form for editing the specified special memo.
     */
    public function edit(SpecialMemo $specialMemo): View
    {
        // Check if user has privileges to edit this memo using can_edit_memo()
        if (!can_edit_memo($specialMemo)) {
            return redirect()
                ->route('special-memo.show', $specialMemo)
                ->with('error', 'You do not have permission to edit this memo.');
        }

        ini_set('memory_limit', '1024M');
        $division_id = user_session('division_id');
      
        // Request Types
        $requestTypes = RequestType::all();
    
        // All staff in the system for responsible person (with job details)
        $staff =  Staff::active()
            ->select(['id', 'fname','lname','staff_id', 'division_id', 'division_name', 'job_name', 'duty_station_name'])
            ->get();
    
        // Staff only from current division for internal participants
        $divisionStaff =  Staff::active()
            ->select(['id', 'fname','lname','staff_id', 'division_id', 'division_name'])
            ->where('division_id', $division_id)
            ->get();
    
        // All staff grouped by division for external participants
        $allStaffGroupedByDivision = Staff::active()
            ->select(['staff_id', 'fname', 'lname', 'division_id', 'division_name'])
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

        // dd($specialMemo->budget);

        // Fix for potentially double-encoded or malformed JSON in budget_breakdown
        $budget = $specialMemo->budget_breakdown;

        if (!is_array($budget)) {
            $decoded = json_decode($budget, true);
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }
            $budget = is_array($decoded) ? $decoded : [];
        }

        // Replace original budget on the model (optional, for view consistency)
        $specialMemo->budget_breakdown = $budget;

        // Process participants data for edit form - following activities pattern
        $rawParticipants = is_string($specialMemo->internal_participants)
            ? json_decode($specialMemo->internal_participants, true)
            : ($specialMemo->internal_participants ?? []);

        // Debug: Log the raw participants data
        \Illuminate\Support\Facades\Log::info('Raw participants data:', ['data' => $rawParticipants]);
        \Illuminate\Support\Facades\Log::info('Raw participants data type:', ['type' => gettype($rawParticipants)]);
        \Illuminate\Support\Facades\Log::info('Raw participants data count:', ['count' => is_array($rawParticipants) ? count($rawParticipants) : 'not array']);

        // Extract staff details and append date/days info - following activities pattern
        $internalParticipants = [];
        $externalParticipants = [];
        if (!empty($rawParticipants)) {
            // Check if data is already in the correct format (array of objects with staff key)
            if (isset($rawParticipants[0]) && isset($rawParticipants[0]['staff'])) {
                // Data is already in the correct format, separate internal and external
                foreach ($rawParticipants as $participant) {
                    if ($participant['staff']->division_id == $division_id) {
                        $internalParticipants[] = $participant;
                    } else {
                        $externalParticipants[] = $participant;
                    }
                }
                \Illuminate\Support\Facades\Log::info('Participants already in correct format, separated internal and external', []);
            } else {
                // Data is in key-value format, process it
                $staffIds = array_keys($rawParticipants);
                \Illuminate\Support\Facades\Log::info('Staff IDs to fetch:', ['staff_ids' => $staffIds]);
                
                $staffDetails = Staff::whereIn('staff_id', $staffIds)->get()->keyBy('staff_id');
                \Illuminate\Support\Facades\Log::info('Staff details found:', ['count' => $staffDetails->count()]);

                foreach ($rawParticipants as $staffId => $participantData) {
                    if (isset($staffDetails[$staffId])) {
                        $participant = [
                            'staff' => $staffDetails[$staffId],
                            'participant_start' => $participantData['participant_start'] ?? null,
                            'participant_end' => $participantData['participant_end'] ?? null,
                            'participant_days' => $participantData['participant_days'] ?? null,
                        ];
                        
                        // Separate internal and external participants
                        if ($staffDetails[$staffId]->division_id == $division_id) {
                            $internalParticipants[] = $participant;
                        } else {
                            $externalParticipants[] = $participant;
                        }
                    }
                }
                \Illuminate\Support\Facades\Log::info('Processed participants from key-value format, separated internal and external', []);
            }
        }

        // Debug: Log the processed participants
        \Illuminate\Support\Facades\Log::info('Processed participants count:', ['count' => count($internalParticipants)]);
        \Illuminate\Support\Facades\Log::info('Processed participants:', ['participants' => $internalParticipants]);

        // Process budget data for edit form - following activities pattern
        $budgetIds = is_string($specialMemo->budget_id)
            ? json_decode($specialMemo->budget_id, true)
            : ($specialMemo->budget_id ?? []);

        $budgetItems = is_string($specialMemo->budget_breakdown)
            ? json_decode($specialMemo->budget_breakdown, true)
            : ($specialMemo->budget_breakdown ?? []);

        // Get fund type from special memo or from budget codes
        $fundTypeId = $specialMemo->fund_type_id ?? null;
        if (!$fundTypeId && !empty($budgetIds)) {
            $firstBudgetCode = \App\Models\FundCode::find($budgetIds[0]);
            if ($firstBudgetCode) {
                $fundTypeId = $firstBudgetCode->fund_type_id;
            }
        }

        // Get budget codes for the specific fund type
        $budgetCodesForFundType = [];
        if ($fundTypeId) {
            $budgetCodesForFundType = \App\Models\FundCode::where('fund_type_id', $fundTypeId)
                ->where('division_id', $division_id)
                ->get();
        }
    
        return view('special-memo.edit', [
            'specialMemo' => $specialMemo,
            'requestTypes' => $requestTypes,
            'staff' => $staff,
            'divisionStaff' => $divisionStaff,
            'allStaffGroupedByDivision' => $allStaffGroupedByDivision,
            'locations' => $locations,
            'fundTypes' => $fundTypes,
            'budgetCodes' => $budgetCodesForFundType, // Use filtered budget codes
            'costItems' => $costItems,
            'internalParticipants' => $internalParticipants, // Following activities pattern
            'externalParticipants' => $externalParticipants, // External participants
            'budgetItems' => $budgetItems, // Following activities pattern
            'fundTypeId' => $fundTypeId, // Pass the fund type ID
            'budgetIds' => $budgetIds, // Pass the selected budget IDs
            'title' => 'Edit Special Memo',
            'editing' => true,
        ]);
    }

    /**
     * Update the specified special memo.
     */
    public function update(Request $request, SpecialMemo $specialMemo): RedirectResponse
    {
        // Check if user has privileges to edit this memo using can_edit_memo()
        if (!can_edit_memo($specialMemo)) {
            return redirect()
                ->route('special-memo.show', $specialMemo)
                ->with('error', 'You do not have permission to edit this memo.');
        }

        // Debug: Log the request data
        \Log::info('Special Memo Update Request', [
            'action' => $request->input('action'),
            'overall_status' => $specialMemo->overall_status,
            'is_draft' => $specialMemo->is_draft,
            'all_request_data' => $request->all()
        ]);
        
        $validated = $request->validate([
            'activity_title' => 'required|string|max:200',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'background' => 'required|string',
            'justification' => 'required|string',
            'supporting_reasons' => 'required|string',
            'location_id' => 'required|array|min:1',
            'location_id.*' => 'exists:locations,id',
            'participant_start' => 'required|array',
            'participant_end' => 'required|array',
            'participant_days' => 'required|array',
            'responsible_person_id' => 'required|exists:staff,staff_id',
            'fund_type_id' => 'required|exists:fund_types,id',
            'budget_codes' => 'nullable|array',
            'budget_codes.*' => 'exists:fund_codes,id',
            'activity_code' => 'nullable|string|max:255',
            'attachments.*.type' => 'required_with:attachments.*.file|string|max:255',
            'attachments.*.file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,ppt,pptx,xls,xlsx,doc,docx|max:10240',
            'attachments.*.replace' => 'nullable|boolean',
            'attachments.*.delete' => 'nullable|boolean',
        ]);

        // Custom validation: World Bank Activity Code is required when World Bank budget code (funder_id=1) is selected
        $budgetCodes = $request->input('budget_codes', []);
        $activityCode = $request->input('activity_code', '');
        
        if (!empty($budgetCodes)) {
            // Check if any selected budget code belongs to World Bank (funder_id = 1)
            $worldBankCodes = \App\Models\FundCode::whereIn('id', $budgetCodes)
                ->where('funder_id', 1)
                ->exists();
            
            if ($worldBankCodes && empty($activityCode)) {
                $errorMessage = 'World Bank Activity Code is required when World Bank budget code is selected.';
                
                return redirect()->back()->withInput()->with([
                    'msg' => $errorMessage,
                    'type' => 'error'
                ]);
            }
        }

        // Validate total participants and budget
        $totalParticipants = (int) $request->input('total_participants', 0);
        if ($totalParticipants <= 0) {
            $errorMessage = 'Cannot update special memo with zero or negative total participants.';
            
            return redirect()->back()->withInput()->with([
                'msg' => $errorMessage,
                'type' => 'error'
            ]);
        }

        // Calculate total budget from budget items
        $totalBudget = 0;
        $budgetItems = $request->input('budget', []);
        $fundTypeId = (int) $request->input('fund_type_id', 1);
        
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
            $errorMessage = 'Cannot update special memo with zero or negative total budget.';
            
            return redirect()->back()->withInput()->with([
                'msg' => $errorMessage,
                'type' => 'error'
            ]);
        }
    
        try {
            DB::beginTransaction();
    
            // Reformat internal participants
            $internalParticipants = [];
            foreach ($request->participant_start as $staffId => $start) {
                $internalParticipants[$staffId] = [
                    'participant_start' => $start,
                    'participant_end' => $request->participant_end[$staffId] ?? null,
                    'participant_days' => $request->participant_days[$staffId] ?? null,
                ];
            }
    
            // Handle attachments - following non-travel pattern
            $attachments = [];
            $existingAttachments = is_string($specialMemo->attachment) 
                ? json_decode($specialMemo->attachment, true) 
                : ($specialMemo->attachment ?? []);
            
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
                    
                    // Store file in public/uploads/special-memos directory
                    $path = $file->storeAs('uploads/special-memos', $filename, 'public');
                    
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

            // Determine status based on action
            $action = $request->input('action', 'draft');
            $isDraft = ($action === 'draft');
            $overallStatus = $isDraft ? 'draft' : 'pending';

            // Note: staff_id (creator) is preserved and never changed
            $updateData = [
                'responsible_person_id' => $request->input('responsible_person_id', 1),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'activity_title' => clean_unicode($request->input('activity_title')),
                'background' => clean_unicode($request->input('background', '')),
                'justification' => clean_unicode($request->input('justification', '')),
                'activity_request_remarks' => clean_unicode($request->input('activity_request_remarks', '')),
                'key_result_area' => clean_unicode($request->input('key_result_link', '-')),
                'request_type_id' => (int) $request->input('request_type_id', 1),
                'fund_type_id' => (int) $request->input('fund_type_id', 1),
                'workplan_activity_code' => $request->input('activity_code', ''),
                'is_draft' => $isDraft,
                'overall_status' => $overallStatus,
                'total_participants' => (int) $request->input('total_participants', 0),
                'total_external_participants' => (int) $request->input('total_external_participants', 0),
    
                'location_id' => json_encode($request->input('location_id', [])),
                'internal_participants' => json_encode($internalParticipants),
    
                'budget_id' => json_encode($request->input('budget_codes', [])),
                'budget_breakdown' => json_encode($request->input('budget', [])),
                'attachment' => json_encode($attachments),
    
                'supporting_reasons' => clean_unicode($request->input('supporting_reasons', null)),
            ];

           // dd($updateData);
            // Add workflow fields only when submitting for approval
            if (!$isDraft) {
                // Get assigned workflow ID for SpecialMemo model
                $assignedWorkflowId = 1; // Default workflow ID
                Log::warning('No workflow assignment found for SpecialMemo model in update, using default workflow ID: 1');
                $updateData['forward_workflow_id'] = $assignedWorkflowId;
                $updateData['approval_level'] = 1;
                $updateData['next_approval_level'] = 2;
            } else {
                $updateData['forward_workflow_id'] = null;
                $updateData['approval_level'] = 0;
                $updateData['next_approval_level'] = 1;
            }

            $specialMemo->update($updateData);
    
            // Handle submission for approval
            if ($request->input('action') === 'submit') {
                \Log::info('Submit for approval triggered', [
                    'action' => $request->input('action'),
                    'overall_status' => $specialMemo->overall_status,
                    'is_draft' => $specialMemo->is_draft
                ]);
                
                // Check if the memo is in draft status before submitting
                if ($specialMemo->overall_status != 'draft') {
                    \Log::warning('Submit for approval failed - not in draft status', [
                        'overall_status' => $specialMemo->overall_status,
                        'is_draft' => $specialMemo->is_draft
                    ]);
                    
                    DB::rollBack();
                    return redirect()->back()->with([
                        'msg' => 'Only draft special memos can be submitted for approval.',
                        'type' => 'error',
                    ]);
                }
                
                // Submit for approval
                return $this->submitForApproval($specialMemo);
            }
    
            DB::commit();
    
            $message = 'Special Memo updated and saved as draft successfully.';
    
            return redirect()->route('special-memo.index')->with([
                'msg' => $message,
                'type' => 'success',
            ]);
    
        } catch (\Throwable $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Error updating special memo', ['exception' => $e]);
    
            return redirect()->back()->withInput()->with([
                'msg' => 'An error occurred while updating the special memo. Please try again.',
                'type' => 'error',
            ]);
        }
    }

    /**
     * Remove the specified special memo.
     */
    public function destroy(SpecialMemo $specialMemo): RedirectResponse
    {
        try {
            DB::beginTransaction();
            
            // Delete related attachments from storage
            if (!empty($specialMemo->attachment)) {
                foreach ($specialMemo->attachment as $attachment) {
                    if (isset($attachment['path'])) {
                        Storage::disk('public')->delete($attachment['path']);
                    }
                }
            }
            
            // Delete approval trails
            \App\Models\ApprovalTrail::where('model_type', 'App\\Models\\SpecialMemo')
                ->where('model_id', $specialMemo->id)
                ->delete();
            
            $specialMemo->delete();
            
            DB::commit();
            
            return redirect()
                ->route('special-memo.index')
                ->with('success', 'Special memo deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Error deleting special memo', ['exception' => $e]);
            
            return redirect()
                ->route('special-memo.index')
                ->with('error', 'An error occurred while deleting the special memo.');
        }
    }
    
    /**
     * Remove a specific attachment from a special memo.
     */
    public function removeAttachment(Request $request, SpecialMemo $specialMemo): RedirectResponse
    {
        $validated = $request->validate([
            'attachment_index' => 'required|integer',
        ]);
        
        $index = $validated['attachment_index'];
        $attachments = $specialMemo->attachment ?? [];
        
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
            $specialMemo->update(['attachment' => $attachments]);
            
            return redirect()
                ->back()
                ->with('success', 'Attachment removed successfully.');
        }
        
        return redirect()
            ->back()
            ->with('error', 'Attachment not found.');
    }

    /**
     * Submit special memo for approval.
     */
    public function submitForApproval(SpecialMemo $specialMemo): RedirectResponse
    {
        // dd($specialMemo->overall_status);
        // dd($specialMemo->overall_status);
        //dd($specialMemo->overall_status);
        if ($specialMemo->overall_status != 'draft') {
            return redirect()->back()->with([
                'msg' => 'Only draft special memos can be submitted for approval.',
                'type' => 'error',
            ]);
        }

        // Update the memo status directly
        $specialMemo->overall_status = 'pending';
        $specialMemo->approval_level = 1;
        // Get assigned workflow ID for SpecialMemo model
        $assignedWorkflowId = 1; // Default workflow ID
        Log::warning('No workflow assignment found for SpecialMemo model in submitForApproval, using default workflow ID: 1');
        $specialMemo->forward_workflow_id = $assignedWorkflowId;
        $specialMemo->next_approval_level = 2;
        $specialMemo->is_draft = 0; // Set is_draft to 0 (false) when submitting for approval
        
        $specialMemo->save();

        // Save approval trail
        $specialMemo->saveApprovalTrail('Submitted for approval', 'submitted');

        return redirect()->route('special-memo.show', $specialMemo)->with([
            'msg' => 'Special memo submitted for approval successfully.',
            'type' => 'success',
        ]);
    }

    /**
     * Update approval status using generic approval system.
     */
    public function updateStatus(Request $request, SpecialMemo $specialMemo): RedirectResponse
    {
        $request->validate([
            'action' => 'required|in:approved,rejected,returned,cancelled',
            'comment' => 'nullable|string|max:1000',
            'available_budget' => 'nullable|numeric|min:0'
        ]);

        // Use the generic approval system
        $genericController = app(\App\Http\Controllers\GenericApprovalController::class);
        return $genericController->updateStatus($request, 'SpecialMemo', $specialMemo->id);
    }

    /**
     * Resubmit a returned special memo for approval.
     */
    public function resubmit(Request $request, SpecialMemo $specialMemo): RedirectResponse
    {
        $request->validate([
            'comment' => 'nullable|string|max:1000'
        ]);

        // Check if the memo is in the correct status for resubmission
        if (!in_array($specialMemo->overall_status, ['returned', 'pending'])) {
            return redirect()->back()->with('error', 'Only returned or pending memos can be resubmitted.');
        }

        if (!isdivision_head($specialMemo)) {
            return redirect()->back()->with('error', 'Only division heads can resubmit returned memos.');
        }

        // Check if memo is at the correct level for resubmission (0 or 1)
        if ($specialMemo->approval_level > 1) {
            return redirect()->back()->with('error', 'Memo must be at the correct level to be resubmitted.');
        }

        // Handle resubmission based on current level
        if ($specialMemo->approval_level == 0) {
            // Memo was returned by HOD to focal person - resubmit to HOD (level 1)
            $specialMemo->approval_level = 1;
            $specialMemo->forward_workflow_id = \App\Models\WorkflowModel::getWorkflowIdForModel('SpecialMemo');
            $specialMemo->overall_status = 'pending';
            $specialMemo->save();
        } else {
            // Memo was returned by other approver to HOD - resubmit to that approver
            $lastApprovalTrail = \App\Models\ApprovalTrail::where('model_id', $specialMemo->id)
                ->where('model_type', 'App\\Models\\SpecialMemo')
                ->where('action', 'returned')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$lastApprovalTrail) {
                return redirect()->back()->with('error', 'Could not find the approver who returned this memo.');
            }

            // Set the memo back to the approver who returned it
            $specialMemo->approval_level = $lastApprovalTrail->approval_order;
            $specialMemo->forward_workflow_id = $lastApprovalTrail->forward_workflow_id;
            $specialMemo->overall_status = 'pending';
            $specialMemo->save();
        }

        // Create a new approval trail for the resubmission
        $resubmitTrail = new \App\Models\ApprovalTrail();
        $resubmitTrail->model_id = $specialMemo->id;
        $resubmitTrail->model_type = 'App\\Models\\SpecialMemo';
        $resubmitTrail->remarks = $request->comment ?? 'Memo resubmitted for approval';
        $resubmitTrail->forward_workflow_id = $specialMemo->forward_workflow_id;
        $resubmitTrail->action = 'resubmitted';
        $resubmitTrail->approval_order = $specialMemo->approval_level;
        
        // Always use the HOD (current user) as the resubmitter in the approval trail
        // This shows who actually performed the resubmission action
        $resubmitTrail->staff_id = user_session('staff_id');
        
        $resubmitTrail->is_archived = 0;
        $resubmitTrail->save();

        return redirect()->route('special-memo.show', $specialMemo)
            ->with('success', 'Memo has been resubmitted for approval.');
    }

    /**
     * Show approval status page.
     */
    public function status(SpecialMemo $specialMemo): View
    {
        $specialMemo->load(['staff', 'division', 'forwardWorkflow']);
        
        // Get approval order map from the special memo
        $approvalOrderMap = [];
        if ($specialMemo->approval_order_map) {
            $approvalOrderMap = json_decode($specialMemo->approval_order_map, true);
        } else {
            // Generate approval order map if not exists
            $approvalService = new \App\Services\ApprovalService();
            $approvalOrderMap = $approvalService->generateApprovalOrderMap($specialMemo);
        }
        
        return view('special-memo.status', compact('specialMemo', 'approvalOrderMap'));
    }

    /**
     * Get detailed approval level information for the memo.
     */
    private function getApprovalLevels(SpecialMemo $specialMemo): array
    {
        if (!$specialMemo->forward_workflow_id) {
            return [];
        }

        $levels = \App\Models\WorkflowDefinition::where('workflow_id', $specialMemo->forward_workflow_id)
            ->where('is_enabled', 1)
            ->orderBy('approval_order', 'asc')
            ->get();

        $approvalLevels = [];
        foreach ($levels as $level) {
            $isCurrentLevel = $level->approval_order == $specialMemo->approval_level;
            $isCompleted = $specialMemo->approval_level > $level->approval_order;
            $isPending = $specialMemo->approval_level == $level->approval_order && $specialMemo->overall_status === 'pending';
            
            $approver = null;
            if ($level->is_division_specific && $specialMemo->division) {
                $staffId = $specialMemo->division->{$level->division_reference_column} ?? null;
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
     * Show pending approvals and approved by me for special memos
     */
    public function pendingApprovals(Request $request): View
    {
        $userStaffId = user_session('staff_id');

        // Check if we have valid session data
        if (!$userStaffId) {
            return view('special-memo.pending-approvals', [
                'pendingMemos' => collect(),
                'approvedByMe' => collect(),
                'divisions' => collect(),
                'requestTypes' => collect(),
                'error' => 'No session data found. Please log in again.'
            ]);
        }

        // Use the exact same logic as the home helper for consistency
        $userDivisionId = user_session('division_id');
        
        $pendingQuery = SpecialMemo::with([
            'staff',
            'division',
            'requestType',
            'forwardWorkflow.workflowDefinitions.approvers.staff',
            'forwardWorkflow.workflowDefinitions'
        ])
        ->where('overall_status', 'pending')
        ->where('forward_workflow_id', '!=', null)
        ->where('approval_level', '>', 0);

        $pendingQuery->where(function($q) use ($userDivisionId, $userStaffId) {
            // Case 1: Division-specific approval - check if user's division matches memo division
            if ($userDivisionId) {
                $q->whereHas('forwardWorkflow.workflowDefinitions', function($subQ): void {
                    $subQ->where('is_division_specific', 1)
                    ->whereNull('division_reference_column')
                          ->where('approval_order', \Illuminate\Support\Facades\DB::raw('special_memos.approval_level'));
                })
                ->where('division_id', $userDivisionId);
            }

            // Case 1b: Division-specific approval with division_reference_column - check if user's staff_id matches the value in the division_reference_column
            if ($userStaffId) {
                $q->orWhere(function($subQ) use ($userStaffId, $userDivisionId) {
                    $divisionsTable = (new \App\Models\Division())->getTable();
                    $subQ->whereRaw("EXISTS (
                        SELECT 1 FROM workflow_definition wd 
                        JOIN {$divisionsTable} d ON d.id = special_memos.division_id 
                        WHERE wd.workflow_id = special_memos.forward_workflow_id 
                        AND wd.is_division_specific = 1 
                        AND wd.division_reference_column IS NOT NULL 
                        AND wd.approval_order = special_memos.approval_level
                        AND ( d.focal_person = ? OR
                            d.division_head = ? OR
                            d.admin_assistant = ? OR
                            d.finance_officer = ? OR
                            d.head_oic_id = ? OR
                            d.director_id = ? OR
                            d.director_oic_id = ?
                            OR (d.id=special_memos.division_id AND d.id=?)
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
                                  ->where('approval_order', \Illuminate\Support\Facades\DB::raw('special_memos.approval_level'))
                                  ->whereHas('approvers', function($approverQ) use ($userStaffId) {
                                      $approverQ->where('staff_id', $userStaffId);
                                  });
                    });
                });
            }

            $q->orWhere('division_id', $userDivisionId);
        });

        // Get the memos and apply the same filtering as the home helper
        $memos = $pendingQuery->get();
        
        // Apply the same additional filtering as the home helper for consistency
        $filteredMemos = $memos->filter(function ($memo) {
            return can_take_action_generic($memo);
        });
        
        // Manually paginate the filtered collection
        $currentPage = \Illuminate\Pagination\Paginator::resolveCurrentPage();
        $perPage = 20;
        $currentPageItems = $filteredMemos->slice(($currentPage - 1) * $perPage, $perPage)->values();
        
        $pendingMemos = new \Illuminate\Pagination\LengthAwarePaginator($currentPageItems, $filteredMemos->count(), $perPage, $currentPage, ['path' => request()->url()]);

        // Get memos approved by current user
        $approvedByMeQuery = SpecialMemo::with([
            'staff',
            'division',
            'requestType',
            'forwardWorkflow.workflowDefinitions.approvers.staff',
            'forwardWorkflow.workflowDefinitions'
        ])
        ->whereHas('approvalTrails', function($q) use ($userStaffId) {
            $q->where('staff_id', $userStaffId)
              ->where('action', 'approved');
        })
        ->orderBy('updated_at', 'desc');

        $approvedByMe = $approvedByMeQuery->paginate(20);

        // Get data for filters
        $divisions = \App\Models\Division::orderBy('division_name')->get();
        $requestTypes = \App\Models\RequestType::orderBy('name')->get();

        // Helper function to get workflow information for a memo
        $getWorkflowInfo = function($memo) {
            $approvalLevel = $memo->approval_level ?? 'N/A';
            $workflowRole = 'N/A';
            $actorName = 'N/A';
            
            if ($memo->forwardWorkflow && $memo->forwardWorkflow->workflowDefinitions) {
                $currentDefinition = $memo->forwardWorkflow->workflowDefinitions
                    ->where('approval_order', $memo->approval_level)
                    ->where('is_enabled', 1)
                    ->first();
                    
                if ($currentDefinition) {
                    $workflowRole = $currentDefinition->role ?? 'N/A';
                    
                    // Get actor name
                    if ($currentDefinition->is_division_specific && $memo->division) {
                        $staffId = $memo->division->{$currentDefinition->division_reference_column} ?? null;
                        if ($staffId) {
                            $actor = \App\Models\Staff::where('staff_id', $staffId)->first();
                            if ($actor) {
                                $actorName = $actor->fname . ' ' . $actor->lname;
                            }
                        }
                    } else {
                        $approver = \App\Models\Approver::where('workflow_dfn_id', $currentDefinition->id)->first();
                        if ($approver) {
                            $actor = \App\Models\Staff::where('staff_id', $approver->staff_id)->first();
                            if ($actor) {
                                $actorName = $actor->fname . ' ' . $actor->lname;
                            }
                        }
                    }
                }
            }
            
            return [
                'approvalLevel' => $approvalLevel,
                'workflowRole' => $workflowRole,
                'actorName' => $actorName
            ];
        };

        return view('special-memo.pending-approvals', compact(
            'pendingMemos',
            'approvedByMe',
            'divisions',
            'requestTypes',
            'getWorkflowInfo'
        ));
    }

    /**
     * Generate a printable PDF for a Special Memo.
     */
    public function print(SpecialMemo $specialMemo)
    {
        // Eager load relations
        $specialMemo->load([
            'staff', 
            'division', 
            'requestType',
            'approvalTrails.staff',
            'approvalTrails.oicStaff',
            'approvalTrails.workflowDefinition'
        ]);

        // Decode JSON fields safely
        $locationIds = is_string($specialMemo->location_id)
            ? json_decode($specialMemo->location_id, true)
            : ($specialMemo->location_id ?? []);

        $budgetIds = is_string($specialMemo->budget_id)
            ? json_decode($specialMemo->budget_id, true)
            : ($specialMemo->budget_id ?? []);

        $budgetBreakdown = $specialMemo->budget_breakdown;
        if (!is_array($budgetBreakdown)) {
            $decoded = json_decode($budgetBreakdown, true);
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }
            $budgetBreakdown = is_array($decoded) ? $decoded : [];
        }

        $attachments = is_string($specialMemo->attachment)
            ? json_decode($specialMemo->attachment, true)
            : ($specialMemo->attachment ?? []);

        $rawParticipants = is_string($specialMemo->internal_participants)
            ? json_decode($specialMemo->internal_participants, true)
            : ($specialMemo->internal_participants ?? []);

        // Resolve participants to Staff models
        $internalParticipants = [];
        if (!empty($rawParticipants) && is_array($rawParticipants)) {
            // Check if participants are already processed (have 'staff' key)
            if (isset($rawParticipants[0]) && isset($rawParticipants[0]['staff'])) {
                // Participants are already processed, use as is
                $internalParticipants = $rawParticipants;
            } else {
                // Participants need to be processed - get staff details
                $staffIds = [];
                foreach ($rawParticipants as $participantData) {
                    if (isset($participantData['staff_id'])) {
                        $staffIds[] = $participantData['staff_id'];
                    }
                }
                
                if (!empty($staffIds)) {
                    $staffDetails = Staff::whereIn('staff_id', $staffIds)
                ->get()
                ->keyBy('staff_id');

                    foreach ($rawParticipants as $participantData) {
                        $staffId = $participantData['staff_id'] ?? null;
                $internalParticipants[] = [
                            'staff' => $staffId ? ($staffDetails[$staffId] ?? null) : null,
                    'participant_start' => $participantData['participant_start'] ?? null,
                    'participant_end' => $participantData['participant_end'] ?? null,
                    'participant_days' => $participantData['participant_days'] ?? null,
                ];
                    }
                }
            }
        }

        // Fetch related collections
        $locations = Location::whereIn('id', $locationIds ?: [])->get();
        $fundCodes = FundCode::whereIn('id', $budgetIds ?: [])->with('fundType')->get();

        // Get approval trails
        $approvalTrails = $specialMemo->approvalTrails;

        // Get workflow information
        $workflowInfo = $this->getComprehensiveWorkflowInfo($specialMemo);
        $organizedWorkflowSteps = \App\Helpers\PrintHelper::organizeWorkflowStepsBySection($workflowInfo['workflow_steps']);

        // Use mPDF helper function
        $print = false;
        $pdf = mpdf_print('special-memo.memo-pdf-simple', [
            'specialMemo' => $specialMemo,
            'locations' => $locations,
            'fundCodes' => $fundCodes,
            'attachments' => $attachments,
            'budgetBreakdown' => $budgetBreakdown,
            'internalParticipants' => $internalParticipants,
            'approval_trails' => $approvalTrails,
            'matrix_approval_trails' => $approvalTrails, // For compatibility with activities template
            'workflow_info' => $workflowInfo,
            'organized_workflow_steps' => $organizedWorkflowSteps
        ], ['preview_html' => $print]);

        // Generate filename
        $filename = 'Special_Memo_' . $specialMemo->id . '_' . now()->format('Y-m-d') . '.pdf';

        // Return PDF for display in browser using mPDF Output method
        return response($pdf->Output($filename, 'I'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"'
        ]);
    }

    /**
     * Get comprehensive workflow information including approval trails
     */
    private function getComprehensiveWorkflowInfo(SpecialMemo $specialMemo)
    {
        $workflowInfo = [
            'current_level' => null,
            'current_approver' => null,
            'workflow_steps' => collect(),
            'approval_trail' => collect(),
            'matrix_approval_trail' => collect()
        ];

        // Get workflow definitions
        $workflowDefinitions = \App\Models\WorkflowDefinition::where('workflow_id', $specialMemo->forward_workflow_id)
            ->where('is_enabled', 1)
            ->where(function($query) use ($specialMemo) {
                $query->where('approval_order', '!=', 7)
                      ->orWhere(function($subQuery) use ($specialMemo) {
                          $subQuery->where('approval_order', 7)
                                   ->where('category', $specialMemo->division->category ?? null);
                      });
            })
            ->orderBy('approval_order')
            ->with(['approvers.staff', 'approvers.oicStaff'])
            ->get();

        // Get approval trails
        $approvalTrails = $specialMemo->approvalTrails()->with(['staff', 'oicStaff', 'workflowDefinition'])->get();

        foreach ($workflowDefinitions as $definition) {
            $approvers = [];
            
            // Get approvers for this workflow definition
            foreach ($definition->approvers as $approver) {
                $approverData = [
                    'staff' => $approver->staff ? $approver->staff->toArray() : null,
                    'oic_staff' => $approver->oicStaff ? $approver->oicStaff->toArray() : null,
                ];
                $approvers[] = $approverData;
            }

            $workflowInfo['workflow_steps']->push([
                'order' => $definition->approval_order,
                'role' => $definition->role,
                'memo_print_section' => $definition->memo_print_section,
                'print_order' => $definition->print_order,
                'approvers' => $approvers
            ]);
        }

        $workflowInfo['approval_trail'] = $approvalTrails;
        $workflowInfo['matrix_approval_trail'] = $approvalTrails;

        return $workflowInfo;
    }

    /**
     * Organize workflow steps by memo_print_section
     */
    private function organizeWorkflowStepsBySection($workflowSteps)
    {
        $organized = [
            'to' => collect(),
            'through' => collect(),
            'from' => collect(),
            'others' => collect()
        ];

        foreach ($workflowSteps as $step) {
            $section = $step['memo_print_section'] ?? 'others';
            
            if (isset($organized[$section])) {
                $organized[$section]->push($step);
            } else {
                $organized['others']->push($step);
            }
        }

        // Sort each section by print_order
        foreach ($organized as $section => $steps) {
            $organized[$section] = $steps->sortBy('print_order');
        }

        return $organized;
    }

    /**
     * Export my submitted special memos to CSV
     */
    public function exportMySubmittedCsv(Request $request)
    {
        $currentStaffId = user_session('staff_id');
        
        $query = SpecialMemo::with([
            'staff', 
            'division', 
            'requestType'
        ])->where('staff_id', $currentStaffId);

        // Apply filters
        if ($request->filled('request_type_id')) {
            $query->where('request_type_id', $request->request_type_id);
        }
        if ($request->filled('division_id')) {
            $query->where('division_id', $request->division_id);
        }
        if ($request->filled('status')) {
            $query->where('overall_status', $request->status);
        }
        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }

        $memos = $query->latest()->get();

        $filename = 'my_submitted_special_memos_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($memos) {
            $file = fopen('php://output', 'w');
            
            // CSV Headers
            fputcsv($file, [
                'ID', 'Activity Title', 'Key Result Area', 'Request Type', 'Division', 
                'Date Range', 'Total Participants', 'Status', 'Created Date', 'Updated Date'
            ]);

            // CSV Data
            foreach ($memos as $memo) {
                fputcsv($file, [
                    $memo->id,
                    $memo->activity_title ?? 'N/A',
                    $memo->key_result_area ?? 'N/A',
                    $memo->requestType ? $memo->requestType->name : 'N/A',
                    $memo->division ? $memo->division->division_name : 'N/A',
                    $memo->formatted_dates ?? 'N/A',
                    $memo->total_participants ?? 'N/A',
                    $memo->overall_status ?? 'N/A',
                    $memo->created_at ? $memo->created_at->format('Y-m-d') : 'N/A',
                    $memo->updated_at ? $memo->updated_at->format('Y-m-d') : 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export all special memos to CSV (for users with permission 87)
     */
    public function exportAllCsv(Request $request)
    {
        if (!in_array(87, user_session('permissions', []))) {
            abort(403, 'Unauthorized access');
        }

        $query = SpecialMemo::with([
            'staff', 
            'division', 
            'requestType'
        ]);

        // Apply filters
        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }
        if ($request->filled('request_type_id')) {
            $query->where('request_type_id', $request->request_type_id);
        }
        if ($request->filled('division_id')) {
            $query->where('division_id', $request->division_id);
        }
        if ($request->filled('status')) {
            $query->where('overall_status', $request->status);
        }

        $memos = $query->latest()->get();

        $filename = 'all_special_memos_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($memos) {
            $file = fopen('php://output', 'w');
            
            // CSV Headers
            fputcsv($file, [
                'ID', 'Activity Title', 'Key Result Area', 'Request Type', 'Division', 
                'Staff', 'Date Range', 'Total Participants', 'Status', 'Created Date', 'Updated Date'
            ]);

            // CSV Data
            foreach ($memos as $memo) {
                fputcsv($file, [
                    $memo->id,
                    $memo->activity_title ?? 'N/A',
                    $memo->key_result_area ?? 'N/A',
                    $memo->requestType ? $memo->requestType->name : 'N/A',
                    $memo->division ? $memo->division->division_name : 'N/A',
                    $memo->staff ? ($memo->staff->first_name . ' ' . $memo->staff->last_name) : 'N/A',
                    $memo->formatted_dates ?? 'N/A',
                    $memo->total_participants ?? 'N/A',
                    $memo->overall_status ?? 'N/A',
                    $memo->created_at ? $memo->created_at->format('Y-m-d') : 'N/A',
                    $memo->updated_at ? $memo->updated_at->format('Y-m-d') : 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export shared special memos to CSV (memos where current user is participant but not creator)
     */
    public function exportSharedCsv(Request $request)
    {
        $currentStaffId = user_session('staff_id');
        
        $query = SpecialMemo::with([
            'staff', 
            'division', 
            'requestType'
        ])
            ->where('staff_id', '!=', $currentStaffId) // Not created by current user
            ->whereJsonContains('internal_participants', $currentStaffId); // But current user is a participant

        // Apply filters
        if ($request->filled('request_type_id')) {
            $query->where('request_type_id', $request->request_type_id);
        }
        if ($request->filled('division_id')) {
            $query->where('division_id', $request->division_id);
        }
        if ($request->filled('status')) {
            $query->where('overall_status', $request->status);
        }

        $memos = $query->latest()->get();

        $filename = 'shared_special_memos_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($memos) {
            $file = fopen('php://output', 'w');
            
            // CSV Headers
            fputcsv($file, [
                'ID', 'Activity Title', 'Key Result Area', 'Request Type', 'Division', 
                'Created By', 'Date Range', 'Total Participants', 'Status', 'Created Date', 'Updated Date'
            ]);

            // CSV Data
            foreach ($memos as $memo) {
                fputcsv($file, [
                    $memo->id,
                    $memo->activity_title ?? 'N/A',
                    $memo->key_result_area ?? 'N/A',
                    $memo->requestType ? $memo->requestType->name : 'N/A',
                    $memo->division ? $memo->division->division_name : 'N/A',
                    $memo->staff ? ($memo->staff->fname . ' ' . $memo->staff->lname) : 'N/A',
                    $memo->formatted_dates ?? 'N/A',
                    $memo->total_participants ?? 'N/A',
                    $memo->overall_status ?? 'N/A',
                    $memo->created_at ? $memo->created_at->format('Y-m-d') : 'N/A',
                    $memo->updated_at ? $memo->updated_at->format('Y-m-d') : 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Admin-only method to update creator (staff_id) and responsible person for special memos
     * Only accessible to users with role == 10 (system admin)
     */
    public function adminUpdate(Request $request, SpecialMemo $specialMemo): JsonResponse
    {
        // Check if user is system admin (role == 10)
        $user = session('user', []);
        $userRole = $user['role'] ?? $user['user_role'] ?? null;
        
        if ($userRole != 10) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only system administrators can perform this action.'
            ], 403);
        }

        // Validate request
        $request->validate([
            'staff_id' => 'required|integer|exists:staff,staff_id',
            'responsible_person_id' => 'required|integer|exists:staff,staff_id',
        ]);

        try {
            // Store original values for logging
            $oldStaffId = $specialMemo->staff_id;
            $oldResponsiblePersonId = $specialMemo->responsible_person_id;
            
            // Update the special memo
            $specialMemo->update([
                'staff_id' => $request->input('staff_id'),
                'responsible_person_id' => $request->input('responsible_person_id'),
            ]);

            // Log the admin action
            Log::info('Admin updated special memo creator/responsible person', [
                'admin_user_id' => $user['user_id'] ?? $user['id'] ?? null,
                'special_memo_id' => $specialMemo->id,
                'old_staff_id' => $oldStaffId,
                'new_staff_id' => $request->input('staff_id'),
                'old_responsible_person_id' => $oldResponsiblePersonId,
                'new_responsible_person_id' => $request->input('responsible_person_id'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Creator and Responsible Person updated successfully.'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update special memo creator/responsible person', [
                'special_memo_id' => $specialMemo->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update: ' . $e->getMessage()
            ], 500);
        }
    }
}
