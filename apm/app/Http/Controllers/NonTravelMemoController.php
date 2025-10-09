<?php

namespace App\Http\Controllers;

use App\Models\NonTravelMemo;
use App\Models\NonTravelMemoCategory;
use App\Models\Staff;
use App\Models\Location;
use App\Models\FundType;
use App\Models\FundCode;
use App\Models\CostItem;
use App\Models\WorkflowModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use App\Services\ApprovalService;
use App\Models\Approver;
use App\Models\WorkflowDefinition;
use App\Models\FundCodeTransaction;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class NonTravelMemoController extends Controller
{
    /** List all memos with optional filters */
    public function index(Request $request)
    {
            //  dd(ApprovalService::canTakeAction(new NonTravelMemo(),user_session('staff_id')));
        // Cache lookup tables for 60 minutes
        $staff  = Cache::remember('non_travel_staff', 60 * 60, fn() => Staff::active()->get());
        $categories = Cache::remember('non_travel_categories', 60 * 60, fn() => NonTravelMemoCategory::all());
        $divisions = Cache::remember('non_travel_divisions', 60 * 60, fn() => \App\Models\Division::all());

        // Get current user's staff ID
        $currentStaffId = user_session('staff_id');
        $userDivisionId = user_session('division_id');

        // Tab 1: My Submitted Memos (memos created by current user)
        $mySubmittedQuery = NonTravelMemo::with([
            'staff', 
            'division', 
            'nonTravelMemoCategory', 
            'fundType',
            'forwardWorkflow.workflowDefinitions.approvers.staff'
        ])
            ->where('staff_id', $currentStaffId);

        // Apply filters to my submitted memos
        if ($request->filled('category_id')) {
            $mySubmittedQuery->where('non_travel_memo_category_id', $request->category_id);
        }
        if ($request->filled('division_id')) {
            $mySubmittedQuery->where('division_id', $request->division_id);
        }
        if ($request->filled('staff_id')) {
            $mySubmittedQuery->where('staff_id', $request->staff_id);
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

        $mySubmittedMemos = $mySubmittedQuery->latest()->paginate(20)->withQueryString();

        // Tab 2: All Non-Travel Memos (visible to users with permission 87)
        $allMemos = collect();
        if (in_array(87, user_session('permissions', []))) {
            $allMemosQuery = NonTravelMemo::with([
                'staff', 
                'division', 
                'nonTravelMemoCategory', 
                'fundType',
                'forwardWorkflow.workflowDefinitions.approvers.staff'
            ]);

            // Apply filters to all memos
            if ($request->filled('staff_id')) {
                $allMemosQuery->where('staff_id', $request->staff_id);
            }
            if ($request->filled('category_id')) {
                $allMemosQuery->where('non_travel_memo_category_id', $request->category_id);
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

            $allMemos = $allMemosQuery->latest()->paginate(20)->withQueryString();
        }

        // Handle AJAX requests for tab content
        if ($request->ajax()) {
            \Log::info('AJAX request received in NonTravelMemoController index', [
                'tab' => $request->get('tab'),
                'all_params' => $request->all()
            ]);
            
            $tab = $request->get('tab', '');
            $html = '';
            
            // Rebuild queries with filters for AJAX requests
            $mySubmittedQuery = NonTravelMemo::with([
                'staff', 
                'division', 
                'nonTravelMemoCategory', 
                'fundType',
                'forwardWorkflow.workflowDefinitions.approvers.staff'
            ])
                ->where('staff_id', $currentStaffId);

            // Apply filters to my submitted memos
            if ($request->filled('category_id')) {
                $mySubmittedQuery->where('non_travel_memo_category_id', $request->category_id);
            }
            if ($request->filled('division_id')) {
                $mySubmittedQuery->where('division_id', $request->division_id);
            }
            if ($request->filled('staff_id')) {
                $mySubmittedQuery->where('staff_id', $request->staff_id);
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

            $mySubmittedMemos = $mySubmittedQuery->latest()->paginate(20)->withQueryString();

            // Tab 2: All Non-Travel Memos (visible to users with permission 87)
            $allMemos = collect();
            if (in_array(87, user_session('permissions', []))) {
                $allMemosQuery = NonTravelMemo::with([
                    'staff', 
                    'division', 
                    'nonTravelMemoCategory', 
                    'fundType',
                    'forwardWorkflow.workflowDefinitions.approvers.staff'
                ]);

                // Apply filters to all memos
                if ($request->filled('staff_id')) {
                    $allMemosQuery->where('staff_id', $request->staff_id);
                }
                if ($request->filled('category_id')) {
                    $allMemosQuery->where('non_travel_memo_category_id', $request->category_id);
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

                $allMemos = $allMemosQuery->latest()->paginate(20)->withQueryString();
            }
            
            switch($tab) {
                case 'mySubmitted':
                    $html = view('non-travel.partials.my-submitted-tab', compact(
                        'mySubmittedMemos'
                    ))->render();
                    break;
                case 'allMemos':
                    $html = view('non-travel.partials.all-memos-tab', compact(
                        'allMemos'
                    ))->render();
                    break;
            }
            
            \Log::info('Generated HTML length for non-travel', ['html_length' => strlen($html)]);
            
            return response()->json(['html' => $html]);
        }

        return view('non-travel.index', compact(
            'mySubmittedMemos', 
            'allMemos', 
            'staff', 
            'categories', 
            'divisions',
            'currentStaffId',
            'userDivisionId'
        ));
    }

   public function create(): View
    {
        ini_set('memory_limit', '1024M');

        // Cache locations for 60 minutes to avoid reloading a large dataset
        $locations = Cache::remember('non_travel_locations', 60 * 60, function () {
            return Location::all();
        });
        $fundTypes = FundType::all();

        return view('non-travel.create', [
            'categories' => NonTravelMemoCategory::all(),
            'staffList'  => Staff::active()->get(),
            'locations'  => $locations,
            'budgets'    => FundCode::all(),
            'fundTypes' => $fundTypes
        ]);
    }

    /** Persist new memo */
    public function store(Request $request): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        //dd($request->all());

        // Get fund type to determine validation rules
        $fundTypeId = (int) $request->input('fund_type_id', 1);
        
        $validationRules = [
            //'staff_id'                     => 'required|exists:staff,id',
            'date_required'                => 'required|date',
            'location_id'                  => 'required|array|min:1',
            'location_id.*'                => 'exists:locations,id',
            'non_travel_memo_category_id'  => 'required|exists:non_travel_memo_categories,id',
            'title'                        => 'required|string|max:255',
            'background'                   => 'required|string',
            'justification'                => 'required|string',
            'activity_request_remarks'     => 'nullable|string',
            'attachments.*.type'           => 'required_with:attachments.*.file|string|max:255',
            'attachments.*.file'           => 'nullable|file|mimes:pdf,jpg,jpeg,png,ppt,pptx,xls,xlsx,doc,docx|max:10240',
            'fund_type_id'                 => 'nullable|exists:fund_types,id',
            //'budget.*'                     => 'array',
        ];
        
        // Only require budget for non-external source fund types
        if ($fundTypeId !== 3) {
            $validationRules['budget_codes'] = 'required|array|min:1';
            $validationRules['budget_codes.*'] = 'exists:fund_codes,id';
            $validationRules['budget_breakdown'] = 'required|array';
        } else {
            $validationRules['budget_codes'] = 'nullable|array';
            $validationRules['budget_codes.*'] = 'exists:fund_codes,id';
            $validationRules['budget_breakdown'] = 'nullable|array';
        }
        
        $data = $request->validate($validationRules);

        $data['staff_id'] = user_session('staff_id');
        $data['division_id'] = user_session('division_id');

        // Extract fund_type_id from budget codes if not provided
        if (empty($data['fund_type_id']) && !empty($data['budget_codes'])) {
            $firstBudgetCode = \App\Models\FundCode::find($data['budget_codes'][0]);
            if ($firstBudgetCode && $firstBudgetCode->fund_type_id) {
                $data['fund_type_id'] = $firstBudgetCode->fund_type_id;
            }
        }

        // Handle attachments
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
                   $path = $file->storeAs('uploads/non-travel', $filename, 'public');
                   
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

        // Prepare JSON columns
        $locationJson = json_encode($data['location_id']);
        $budgetIdJson = json_encode($data['budget_codes']);
        $budgetBreakdownJson = json_encode($data['budget_breakdown']);
        $attachmentsJson = json_encode($attachments);

        // Determine status based on action
        $action = $request->input('action', 'draft');
        $isDraft = ($action === 'draft');
        $overallStatus = $isDraft ? 'draft' : 'pending';

        // Get assigned workflow ID for NonTravelMemo model
        $assignedWorkflowId = null;
        if (!$isDraft) {
            $assignedWorkflowId = WorkflowModel::getWorkflowIdForModel('NonTravelMemo');
            // Fallback to default workflow ID if no assignment found
            if (!$assignedWorkflowId) {
                $assignedWorkflowId = 1; // Default workflow ID
                Log::warning('No workflow assignment found for NonTravelMemo model, using default workflow ID: 1');
            }
        }

        // Save to DB
        $memo = NonTravelMemo::create([
            'reverse_workflow_id' => (int)($request->input('reverse_workflow_id', 1)),
            'workplan_activity_code' => $request->input('activity_code', ''),
            'staff_id' => (int)$data['staff_id'],
            'division_id' => (int)$data['division_id'],
            'fund_type_id' => (int)($data['fund_type_id'] ?? 1),
            'memo_date' => (string)$data['date_required'],
            'location_id' => $locationJson,
            'non_travel_memo_category_id' => (int)$data['non_travel_memo_category_id'],
            'budget_id' => $budgetIdJson,
            'activity_title' => $data['title'],
            'background' => $data['background'],
            'activity_request_remarks' => $data['activity_request_remarks'] ?? '',
            'justification' => $data['justification'],
            'budget_breakdown' => $budgetBreakdownJson,
            'attachment' => $attachmentsJson,
            'forward_workflow_id' => $assignedWorkflowId,
            'approval_level' => $isDraft ? 0 : 1,
                            'next_approval_level' => $isDraft ? 1 : 2,
            'overall_status' => $overallStatus,
            'is_draft' => $isDraft,
        ]);

        // Process fund code balance reductions and create transaction records
        if (!$isDraft && !empty($data['budget_codes']) && !empty($data['budget_breakdown'])) {
            $budgetCodes = $data['budget_codes'];
            $budgetItems = $data['budget_breakdown'];
            
            foreach ($budgetCodes as $codeId) {
                $total = 0;
                if (isset($budgetItems[$codeId]) && is_array($budgetItems[$codeId])) {
                    foreach ($budgetItems[$codeId] as $item) {
                        // Support both array and object
                        $qty = isset($item['quantity']) ? $item['quantity'] : (isset($item->quantity) ? $item->quantity : 1);
                        $unitCost = isset($item['unit_cost']) ? $item['unit_cost'] : (isset($item->unit_cost) ? $item->unit_cost : 0);
                        $total += $qty * $unitCost;
                    }
                }
                
                if ($total > 0) {
                    // Get current balance before reduction
                    $fundCode = FundCode::find($codeId);
                    if ($fundCode) {
                        $balanceBefore = floatval($fundCode->budget_balance ?? 0);
                        $balanceAfter = $balanceBefore - $total;
                        
                        // Reduce fund code balance using the helper
                        reduce_fund_code_balance($codeId, $total);
                        
                        // Create transaction record for audit trail
                        FundCodeTransaction::create([
                            'fund_code_id' => $codeId,
                            'amount' => $total,
                            'description' => "Non-Travel Memo: {$data['title']} - Budget allocation",
                            'activity_id' => $memo->id,
                            'matrix_id' => null,
                            'channel' => 'non_travel',
                            'activity_budget_id' => null,
                            'balance_before' => $balanceBefore,
                            'balance_after' => $balanceAfter,
                            'is_reversal' => false,
                            'created_by' => user_session('staff_id'),
                        ]);
                        
                        // Log the balance change
                        Log::info('Fund code balance reduced for non-travel memo', [
                            'fund_code_id' => $codeId,
                            'fund_code' => $fundCode->code,
                            'non_travel_memo_id' => $memo->id,
                            'amount_reduced' => $total,
                            'balance_before' => $balanceBefore,
                            'balance_after' => $balanceAfter,
                            'staff_id' => user_session('staff_id'),
                            'activity_title' => $data['title']
                        ]);
                    }
                }
            }
        }

        // Return appropriate message based on action
        $message = $isDraft 
            ? 'Non-travel memo saved as draft successfully.'
            : 'Non-travel memo submitted for approval successfully.';
        
        // If it's an AJAX request, return JSON response
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'memo' => [
                    'id' => $memo->id,
                    'title' => $memo->activity_title,
                    'category' => $memo->nonTravelMemoCategory->name ?? 'N/A',
                    'status' => $memo->overall_status,
                    'date_required' => $memo->memo_date,
                    'total_budget' => $this->calculateTotalBudget($data['budget_breakdown']),
                    'preview_url' => route('non-travel.show', $memo->id)
                ]
            ]);
        }
            
        return redirect()->route('non-travel.index')
            ->with('success', $message);
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
                    $qty = isset($item['quantity']) ? floatval($item['quantity']) : 1;
                    $unitCost = isset($item['unit_cost']) ? floatval($item['unit_cost']) : 0;
                    $total += $qty * $unitCost;
                }
            }
        }
        return $total;
    }

    /** Show one memo */
    public function show(NonTravelMemo $nonTravel): View
    {
       
        $nonTravel->load(['staff', 'nonTravelMemoCategory', 'fundType']);
        
        // Decode JSON fields
        $nonTravel->budget_breakdown = is_string($nonTravel->budget_breakdown) 
            ? json_decode($nonTravel->budget_breakdown, true) 
            : $nonTravel->budget_breakdown;

        $nonTravel->location_id = is_string($nonTravel->location_id) 
            ? json_decode($nonTravel->location_id, true) 
            : $nonTravel->location_id;

        $nonTravel->attachment = is_string($nonTravel->attachment) 
            ? json_decode($nonTravel->attachment, true) 
            : $nonTravel->attachment;

        $nonTravel->budget_id = is_string($nonTravel->budget_id) 
            ? json_decode($nonTravel->budget_id, true) 
            : $nonTravel->budget_id;

        return view('non-travel.show', compact('nonTravel'));
    }

    /** Show edit form */
    public function edit(NonTravelMemo $nonTravel)
    {
        // Check if this is a change request
        $isChangeRequest = request('change_request') == '1';
        
        // For change requests, allow access to approved memos if user is owner/responsible
        if ($isChangeRequest) {
            $user = (object) session('user', []);
            $isOwner = isset($nonTravel->staff_id, $user->staff_id) && $nonTravel->staff_id == $user->staff_id;
            $isResponsible = isset($nonTravel->responsible_person_id, $user->staff_id) && $nonTravel->responsible_person_id == $user->staff_id;
            
            if (!$isOwner && !$isResponsible) {
                return redirect()
                    ->route('non-travel.show', $nonTravel)
                    ->with('error', 'You can only create change requests for memos you own or are responsible for.');
            }
        } else {
            // For normal edits, use the existing permission check
            if (!can_edit_memo($nonTravel)) {
                return redirect()
                    ->route('non-travel.show', $nonTravel)
                    ->with('error', 'You do not have permission to edit this memo.');
            }
        }

        // Retrieve budgets with funder details
        $budgets = FundCode::with('funder')
            ->where('division_id', user_session('division_id'))
            ->get()
            ->map(function ($budget) {
                return [
                    'id' => $budget->id,
                    'code' => $budget->code,
                    'funder_name' => $budget->funder->name ?? 'No Funder',
                    'budget_balance' => $budget->budget_balance,
                ];
            });

        // Decode selected budget codes
        $selectedBudgetCodes = is_array($nonTravel->budget_id) 
            ? $nonTravel->budget_id 
            : (is_string($nonTravel->budget_id) ? json_decode($nonTravel->budget_id, true) : []);

        // Decode budget breakdown data - use budget_breakdown field, not budget
        $budgetBreakdown = is_array($nonTravel->budget_breakdown) 
            ? $nonTravel->budget_breakdown 
            : (is_string($nonTravel->budget_breakdown) ? json_decode($nonTravel->budget_breakdown, true) : []);
         $attachments = is_string($nonTravel->attachment)
            ? json_decode($nonTravel->attachment, true)
            : ($nonTravel->attachment ?? []);
        // Debug: Log the budget data
        \Illuminate\Support\Facades\Log::info('NonTravelMemo Budget Debug', [
            'memo_id' => $nonTravel->id,
            'raw_budget_breakdown' => $nonTravel->budget_breakdown,
            'budget_breakdown_type' => gettype($nonTravel->budget_breakdown),
            'decoded_budget_breakdown' => $budgetBreakdown,
            'selected_budget_codes' => $selectedBudgetCodes
        ]);

        // Retrieve other necessary data
        $categories = NonTravelMemoCategory::all();
        $locations = Location::all();
        $workflows = WorkflowDefinition::all();
        $staff = Staff::active()->get(); // Retrieve active staff members

        // Get fund types for the form
        $fundTypes = FundType::all();
        
        return view('non-travel.edit-new', compact(
            'nonTravel', 
            'budgets', 
            'selectedBudgetCodes', 
            'attachments',
            'budgetBreakdown',
            'categories', 
            'locations', 
            'workflows', 
            'staff',
            'fundTypes'
        ));
    }

    // /** Update memo */
    public function update(Request $request, NonTravelMemo $nonTravel): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        //dd($request);
        // Check if user has privileges to edit this memo using can_edit_memo()
        if (!can_edit_memo($nonTravel)) {
            return redirect()
                ->route('non-travel.show', $nonTravel)
                ->with('error', 'You do not have permission to edit this memo.');
        }

        // Get fund type to determine validation rules
        $fundTypeId = (int) $request->input('fund_type_id', 1);
        
        $validationRules = [
            'memo_date'                    => 'required|date',
            'location_id'                  => 'required|array|min:1',
            'location_id.*'                => 'exists:locations,id',
            'non_travel_memo_category_id'  => 'required|exists:non_travel_memo_categories,id',
            'activity_title'               => 'required|string|max:255',
            'activity_request_remarks'     => 'nullable|string',
            'background'                   => 'required|string',
            'justification'                => 'required|string',
            'attachments.*.type'           => 'required_with:attachments|string|max:255',
            'attachments.*.file'           => 'nullable|file|mimes:pdf,jpg,jpeg,png,ppt,pptx,xls,xlsx,doc,docx|max:10240',
            'attachments.*.replace'        => 'nullable|boolean',
            'attachments.*.delete'         => 'nullable|boolean',
            'fund_type_id'                 => 'nullable|exists:fund_types,id',
        ];
        
        // Only require budget for non-external source fund types
        if ($fundTypeId !== 3) {
            $validationRules['budget_breakdown'] = 'required|array|min:1';
        } else {
            $validationRules['budget_breakdown'] = 'nullable|array';
        }
        
        $data = $request->validate($validationRules);

        // For non-travel memos: staff_id (creator) is the responsible person and should never be changed
        // We don't include staff_id or responsible_person_id in validation rules to prevent updates
        // The staff_id remains the original creator throughout the memo's lifecycle

       // Handle file uploads for attachments
       $attachments = [];
       $existingAttachments = is_string($nonTravel->attachment) 
           ? json_decode($nonTravel->attachment, true) 
           : ($nonTravel->attachment ?? []);
       
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
               $path = $file->storeAs('uploads/non-travel', $filename, 'public');
               
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
        // Extract fund_type_id from budget codes if not provided
        if (empty($data['fund_type_id'])) {
            // Get existing budget codes from the memo
            $existingBudgetCodes = is_array($nonTravel->budget_id) 
                ? $nonTravel->budget_id 
                : (is_string($nonTravel->budget_id) ? json_decode($nonTravel->budget_id, true) : []);
            
            if (!empty($existingBudgetCodes)) {
                $firstBudgetCode = \App\Models\FundCode::find($existingBudgetCodes[0]);
                if ($firstBudgetCode && $firstBudgetCode->fund_type_id) {
                    $data['fund_type_id'] = $firstBudgetCode->fund_type_id;
                }
            }
        }

        // Prepare JSON columns
        $locationJson = json_encode($data['location_id']);
        $budgetBreakdownJson = json_encode($data['budget_breakdown']);
        $attachmentsJson = json_encode($attachments);

        // Update the memo
        $nonTravel->update([
            'memo_date' => $data['memo_date'],
            'location_id' => $locationJson,
            'non_travel_memo_category_id' => $data['non_travel_memo_category_id'],
            'fund_type_id' => (int)($data['fund_type_id'] ?? $nonTravel->fund_type_id ?? 1),
            'activity_title' => $data['activity_title'],
            'activity_request_remarks' => $data['activity_request_remarks'],
            'background' => $data['background'],
            'justification' => $data['justification'],
            'budget_breakdown' => $budgetBreakdownJson,
            'attachment' => $attachmentsJson,
        ]);

        // Process fund code balance reductions and create transaction records (only for non-draft updates)
        // if ($nonTravel->overall_status !== 'draft' && !empty($data['budget_breakdown'])) {
        //     $budgetItems = $data['budget_breakdown'];
            
        //     // Get existing budget codes from the memo
        //     $existingBudgetCodes = is_array($nonTravel->budget_id) 
        //         ? $nonTravel->budget_id 
        //         : (is_string($nonTravel->budget_id) ? json_decode($nonTravel->budget_id, true) : []);
            
        //     foreach ($existingBudgetCodes as $codeId) {
        //         $total = 0;
        //         if (isset($budgetItems[$codeId]) && is_array($budgetItems[$codeId])) {
        //             foreach ($budgetItems[$codeId] as $item) {
        //                 // Support both array and object
        //                 $qty = isset($item['quantity']) ? $item['quantity'] : (isset($item->quantity) ? $item->quantity : 1);
        //                 $unitCost = isset($item['unit_cost']) ? $item['unit_cost'] : (isset($item->unit_cost) ? $item->unit_cost : 0);
        //                 $total += $qty * $unitCost;
        //             }
        //         }
                
        //         if ($total > 0) {
        //             // Get current balance before reduction
        //             $fundCode = FundCode::find($codeId);
        //             if ($fundCode) {
        //                 $balanceBefore = floatval($fundCode->budget_balance ?? 0);
        //                 $balanceAfter = $balanceBefore - $total;
                        
        //                 // Reduce fund code balance using the helper
        //                 //reduce_fund_code_balance($codeId, $total);
                        
        //                 // Create transaction record for audit trail
        //                 FundCodeTransaction::updateOrCreate([
        //                     'fund_code_id' => $codeId,
        //                     'amount' => $total,
        //                     'description' => "Non-Travel Memo Update: {$data['activity_title']} - Budget allocation",
        //                     'activity_id' => $nonTravel->id,
        //                     'matrix_id' => null,
        //                     'channel' => 'non_travel',
        //                     'activity_budget_id' => null,
        //                     'balance_before' => $balanceBefore,
        //                     'balance_after' => $balanceAfter,
        //                     'is_reversal' => false,
        //                     'created_by' => user_session('staff_id'),
        //                 ]);

                        
        //                 // Log the balance change
        //                 // \Illuminate\Support\Facades\Log::info('Fund code balance reduced for non-travel memo update', [
        //                 //     'fund_code_id' => $codeId,
        //                 //     'fund_code' => $fundCode->code,
        //                 //     'non_travel_memo_id' => $nonTravel->id,
        //                 //     'amount_reduced' => $total,
        //                 //     'balance_before' => $balanceBefore,
        //                 //     'balance_after' => $balanceAfter,
        //                 //     'staff_id' => user_session('staff_id'),
        //                 //     'activity_title' => $data['activity_title']
        //                 // ]);
        //             }
        //         }
        //     }
        // }

        // If it's an AJAX request, return JSON response
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Non-travel memo updated successfully.',
                'memo' => [
                    'id' => $nonTravel->id,
                    'title' => $nonTravel->activity_title,
                    'category' => $nonTravel->nonTravelMemoCategory->name ?? 'N/A',
                    'status' => $nonTravel->overall_status,
                    'date_required' => $nonTravel->memo_date,
                    'total_budget' => $this->calculateTotalBudget($data['budget_breakdown']),
                    'preview_url' => route('non-travel.show', $nonTravel->id)
                ]
            ]);
        }

        return redirect()->route('non-travel.show', $nonTravel)
            ->with('success', 'Non-travel memo updated successfully.');
    }

    // public function update(Request $request, NonTravelMemo $nonTravel): RedirectResponse
    // {

    //     //dd($request);
    //     // Check if memo is in draft status - only allow updating of drafts
    //     if ($nonTravel->overall_status !== 'draft') {
    //         return redirect()
    //             ->route('non-travel.show', $nonTravel)
    //             ->with('error', 'Cannot update memo. Only draft memos can be updated.');
    //     }

    //     $data = $request->validate([
    //         'memo_date'                    => 'required|date',
    //         'location_id'                  => 'required|array|min:1',
    //         'location_id.*'                => 'exists:locations,id',
    //         'non_travel_memo_category_id'  => 'required|exists:non_travel_memo_categories,id',
    //         'activity_title'               => 'required|string|max:255',
    //         'activity_request_remarks'     => 'required|string',
    //         'background'                   => 'required|string',
    //         'justification'                => 'required|string',
    //         'other_information'            => 'nullable|string',
    //         'budget_breakdown'             => 'required|array|min:1',
    //     ]);

    //     // Handle attachments
    //     // $files = $nonTravel->attachment ?? [];
    //     // if ($request->hasFile('attachments')) {
    //     //     foreach ($request->file('attachments') as $f) {
    //     //         $path = $f->store('non-travel/attachments', 'public');
    //     //         $files[] = [
    //     //             'name' => $f->getClientOriginalName(),
    //     //             'path' => $path,
    //     //             'size' => $f->getSize(),
    //     //             'mime_type' => $f->getMimeType(),
    //     //             'uploaded_at' => now()->toDateTimeString(),
    //     //         ];
    //     //     }
    //     // }

    //     // Prepare JSON columns
    //     $locationJson = json_encode($data['location_id']);
    //     $budgetBreakdownJson = json_encode($data['budget_breakdown']);
    //     $attachmentsJson = json_encode($nonTravel->attachment);

    //     // Update the memo
    //     $nonTravel->update([
    //         'memo_date' => $data['memo_date'],
    //         'location_id' => $locationJson,
    //         'non_travel_memo_category_id' => $data['non_travel_memo_category_id'],
    //         'activity_title' => $data['activity_title'],
    //         'activity_request_remarks' => $data['activity_request_remarks'],
    //         'background' => $data['background'],
    //         'justification' => $data['justification'],
    //         'budget_breakdown' => $budgetBreakdownJson,
    //         'attachment' => $attachmentsJson,
    //     ]);

    //     return redirect()->route('non-travel.show', $nonTravel)
    //         ->with('success', 'Non-travel memo updated successfully.');
    // }

    
    

    /** Delete memo and its files */
    public function destroy(NonTravelMemo $nonTravel): RedirectResponse
    {
        foreach ($nonTravel->attachments ?? [] as $att) {
            Storage::disk('public')->delete($att['path']);
        }
        $nonTravel->delete();

        return back()->with('success', 'Request deleted.');
    }

    /**
     * Submit non-travel memo for approval.
     */
    public function submitForApproval(NonTravelMemo $nonTravel): RedirectResponse
    {
       

        $nonTravel->submitForApproval();

        return redirect()->route('non-travel.show', $nonTravel)->with([
            'msg' => 'Non-travel memo submitted for approval successfully.',
            'type' => 'success',
        ]);
    }

    /**
     * Update approval status using generic approval system.
     */
    public function updateStatus(Request $request, NonTravelMemo $nonTravel): RedirectResponse
    {
        // Debug: Log the incoming request data
       //dd(can_division_head_edit_generic($nonTravel));

        \Illuminate\Support\Facades\Log::info('NonTravelMemo updateStatus called', [
            'request_all' => $request->all(),
            'memo_id' => $nonTravel->id,
            'current_status' => $nonTravel->overall_status,
            'current_level' => $nonTravel->approval_level,
            'user_id' => user_session('staff_id')
        ]);

        $request->validate([
            'action' => 'required|in:approved,rejected,returned,cancelled',
            'comment' => 'nullable|string|max:1000',
            'available_budget' => 'nullable|numeric|min:0'
        ]);

        // Debug: Log validation passed
        \Illuminate\Support\Facades\Log::info('Validation passed, calling generic approval controller');

        // Use the generic approval system
        $genericController = app(\App\Http\Controllers\GenericApprovalController::class);
        return $genericController->updateStatus($request, 'NonTravelMemo', $nonTravel->id);
    }

    /**
     * Resubmit a returned non-travel memo for approval.
     */
    public function resubmit(Request $request, NonTravelMemo $nonTravel): RedirectResponse
    {
        $request->validate([
            'comment' => 'nullable|string|max:1000'
        ]);

        // Check if the memo is in the correct status for resubmission
        if (!in_array($nonTravel->overall_status, ['returned', 'pending'])) {
            return redirect()->back()->with('error', 'Only returned or pending memos can be resubmitted.');
        }

        if (!isdivision_head($nonTravel)) {
            return redirect()->back()->with('error', 'Only division heads can resubmit returned memos.');
        }

        // Check if memo is at the correct level for resubmission (0 or 1)
        if ($nonTravel->approval_level > 1) {
            return redirect()->back()->with('error', 'Memo must be at the correct level to be resubmitted.');
        }

        // Handle resubmission based on current level
        if ($nonTravel->approval_level == 0) {
            // Memo was returned by HOD to focal person - resubmit to HOD (level 1)
            $nonTravel->approval_level = 1;
            $nonTravel->forward_workflow_id = \App\Models\WorkflowModel::getWorkflowIdForModel('NonTravelMemo');
            $nonTravel->overall_status = 'pending';
            $nonTravel->save();
        } else {
            // Memo was returned by other approver to HOD - resubmit to that approver
            $lastApprovalTrail = \App\Models\ApprovalTrail::where('model_id', $nonTravel->id)
                ->where('model_type', 'App\\Models\\NonTravelMemo')
                ->where('action', 'returned')
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$lastApprovalTrail) {
                return redirect()->back()->with('error', 'Could not find the approver who returned this memo.');
            }

            // Set the memo back to the approver who returned it
            $nonTravel->approval_level = $lastApprovalTrail->approval_order;
            $nonTravel->forward_workflow_id = $lastApprovalTrail->forward_workflow_id;
            $nonTravel->overall_status = 'pending';
            $nonTravel->save();
        }

        // Create a new approval trail for the resubmission
        $resubmitTrail = new \App\Models\ApprovalTrail();
        $resubmitTrail->model_id = $nonTravel->id;
        $resubmitTrail->model_type = 'App\\Models\\NonTravelMemo';
        $resubmitTrail->remarks = $request->comment ?? 'Memo resubmitted for approval';
        $resubmitTrail->forward_workflow_id = $nonTravel->forward_workflow_id;
        $resubmitTrail->action = 'resubmitted';
        $resubmitTrail->approval_order = $nonTravel->approval_level;
        
        // Always use the HOD (current user) as the resubmitter in the approval trail
        // This shows who actually performed the resubmission action
        $resubmitTrail->staff_id = user_session('staff_id');
        
        $resubmitTrail->is_archived = 0;
        $resubmitTrail->save();

        return redirect()->route('non-travel.show', $nonTravel)
            ->with('success', 'Memo has been resubmitted for approval.');
    }

    /**
     * Show approval status page.
     */
    public function status(NonTravelMemo $nonTravel): View
    {
        $nonTravel->load(['staff', 'division', 'forwardWorkflow']);
        
        // Get approval order map from the non-travel memo
        $approvalOrderMap = [];
        if ($nonTravel->approval_order_map) {
            $approvalOrderMap = json_decode($nonTravel->approval_order_map, true);
        } else {
            // Generate approval order map if not exists
            $approvalService = new \App\Services\ApprovalService();
            $approvalOrderMap = $approvalService->generateApprovalOrderMap($nonTravel);
        }
        
        return view('non-travel.status', compact('nonTravel', 'approvalOrderMap'));
    }

    public function pendingApprovals(Request $request): View
    {
        $userStaffId = user_session('staff_id');

        // Check if we have valid session data
        if (!$userStaffId) {
            return view('non-travel.pending-approvals', [
                'pendingMemos' => collect(),
                'approvedByMe' => collect(),
                'divisions' => collect(),
                'categories' => collect(),
                'error' => 'No session data found. Please log in again.'
            ]);
        }

        // Use the exact same logic as the home helper for consistency
        $userDivisionId = user_session('division_id');
        
        $pendingQuery = NonTravelMemo::with([
            'staff',
            'division',
            'nonTravelMemoCategory',
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
                          ->where('approval_order', \Illuminate\Support\Facades\DB::raw('non_travel_memos.approval_level'));
                })
                ->where('division_id', $userDivisionId);
            }

            // Case 1b: Division-specific approval with division_reference_column - check if user's staff_id matches the value in the division_reference_column
            if ($userStaffId) {
                $q->orWhere(function($subQ) use ($userStaffId, $userDivisionId) {
                    $divisionsTable = (new \App\Models\Division())->getTable();
                    $subQ->whereRaw("EXISTS (
                        SELECT 1 FROM workflow_definition wd 
                        JOIN {$divisionsTable} d ON d.id = non_travel_memos.division_id 
                        WHERE wd.workflow_id = non_travel_memos.forward_workflow_id 
                        AND wd.is_division_specific = 1 
                        AND wd.division_reference_column IS NOT NULL 
                        AND wd.approval_order = non_travel_memos.approval_level
                        AND ( d.focal_person = ? OR
                            d.division_head = ? OR
                            d.admin_assistant = ? OR
                            d.finance_officer = ? OR
                            d.head_oic_id = ? OR
                            d.director_id = ? OR
                            d.director_oic_id = ?
                            OR (d.id=non_travel_memos.division_id AND d.id=?)
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
                                  ->where('approval_order', \Illuminate\Support\Facades\DB::raw('non_travel_memos.approval_level'))
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
        
        // Create a paginator from the filtered collection
        $currentPage = request()->get('page', 1);
        $perPage = 20;
        $currentPageItems = $filteredMemos->forPage($currentPage, $perPage);
        
        $pendingMemos = new \Illuminate\Pagination\LengthAwarePaginator(
            $currentPageItems,
            $filteredMemos->count(),
            $perPage,
            $currentPage,
            ['path' => request()->url()]
        );

        // Get memos approved by current user
        $approvedByMeQuery = NonTravelMemo::with([
            'staff',
            'division',
            'nonTravelMemoCategory',
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
        $categories = \App\Models\NonTravelMemoCategory::orderBy('name')->get();

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

        return view('non-travel.pending-approvals', compact(
            'pendingMemos',
            'approvedByMe',
            'divisions',
            'categories',
            'getWorkflowInfo'
        ));
    }

    /**
     * Get detailed approval level information for the memo.
     */
    private function getApprovalLevels(NonTravelMemo $nonTravel): array
    {
        if (!$nonTravel->forward_workflow_id) {
            return [];
        }

        $levels = \App\Models\WorkflowDefinition::where('workflow_id', $nonTravel->forward_workflow_id)
            ->where('is_enabled', 1)
            ->orderBy('approval_order', 'asc')
            ->get();

        $approvalLevels = [];
        foreach ($levels as $level) {
            $isCurrentLevel = $level->approval_order == $nonTravel->approval_level;
            $isCompleted = $nonTravel->approval_level > $level->approval_order;
            $isPending = $nonTravel->approval_level == $level->approval_order && $nonTravel->overall_status === 'pending';
            
            $approver = null;
            if ($level->is_division_specific && $nonTravel->division) {
                $staffId = $nonTravel->division->{$level->division_reference_column} ?? null;
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
     * Get comprehensive workflow information for non-travel memo
     */
    private function getComprehensiveWorkflowInfo(NonTravelMemo $nonTravel)
    {
        $workflowInfo = [
            'current_level' => null,
            'current_approver' => null,
            'workflow_steps' => collect(),
            'approval_trail' => collect(),
            'matrix_approval_trail' => collect()
        ];

        if (!$nonTravel->forward_workflow_id) {
            return $workflowInfo;
        }

        // Get workflow definitions with category filtering for order 7
        $workflowDefinitions = \App\Models\WorkflowDefinition::where('workflow_id', $nonTravel->forward_workflow_id)
            ->where('is_enabled', 1)
            ->where(function($query) use ($nonTravel) {
                $query->where('approval_order', '!=', 7)
                      ->orWhere(function($subQuery) use ($nonTravel) {
                          $subQuery->where('approval_order', 7)
                                   ->where('category', $nonTravel->division->category ?? null);
                      });
            })
            ->orderBy('approval_order')
            ->with(['approvers.staff', 'approvers.oicStaff'])
            ->get();

        $workflowInfo['workflow_steps'] = $workflowDefinitions->map(function ($definition) use ($nonTravel) {
            $approvers = collect();

            if ($definition->is_division_specific && $nonTravel->division) {
                // Get approver from division table using division_reference_column
                $divisionColumn = $definition->division_reference_column;
                if ($divisionColumn && isset($nonTravel->division->$divisionColumn)) {
                    $staffId = $nonTravel->division->$divisionColumn;
                    if ($staffId) {
                        $staff = \App\Models\Staff::where('staff_id', $staffId)->first();
                        if ($staff) {
                            $approvers->push([
                                'staff' => [
                                    'id' => $staff->staff_id,
                                    'staff_id' => $staff->staff_id,
                                    'title' => $staff->title ?? 'N/A',
                                    'fname' => $staff->fname ?? '',
                                    'lname' => $staff->lname ?? '',
                                    'oname' => $staff->oname ?? '',
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
                            'staff_id' => $approver->staff->staff_id,
                            'title' => $approver->staff->title ?? 'N/A',
                            'fname' => $approver->staff->fname ?? '',
                            'lname' => $approver->staff->lname ?? '',
                            'oname' => $approver->staff->oname ?? '',
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
                            'staff_id' => $approver->oicStaff->staff_id,
                            'title' => $approver->oicStaff->title ?? 'N/A',
                            'fname' => $approver->oicStaff->fname ?? '',
                            'lname' => $approver->oicStaff->lname ?? '',
                            'oname' => $approver->oicStaff->oname ?? '',
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
                });
            }

            return [
                'order' => $definition->approval_order,
                'role' => $definition->role,
                'memo_print_section' => $definition->memo_print_section ?? 'through',
                'print_order' => $definition->print_order ?? $definition->approval_order,
                'approvers' => $approvers->toArray()
            ];
        })->values();

        // Get current approval level
        if ($nonTravel->approval_level) {
            $currentDefinition = $workflowDefinitions->where('approval_order', $nonTravel->approval_level)->first();
            if ($currentDefinition) {
                $workflowInfo['current_level'] = $currentDefinition->role;

                // Handle division-specific approvers
                if ($currentDefinition->is_division_specific && $nonTravel->division) {
                    $divisionColumn = $currentDefinition->division_reference_column;
                    if ($divisionColumn && isset($nonTravel->division->$divisionColumn)) {
                        $staffId = $nonTravel->division->$divisionColumn;
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

        // Get approval trail
        $approvalTrails = $nonTravel->approvalTrails()
            ->orderBy('created_at')
            ->with(['staff', 'oicStaff'])
            ->get();

        $workflowInfo['approval_trail'] = $approvalTrails->map(function ($trail) {
            return [
                'action' => $trail->action,
                'remarks' => $trail->remarks,
                'staff' => $trail->staff ? [
                    'name' => $trail->staff->fname . ' ' . $trail->staff->lname,
                    'job_title' => $trail->staff->job_name ?? $trail->staff->position ?? 'N/A',
                    'work_email' => $trail->staff->work_email ?? 'N/A',
                    'signature' => $trail->staff->signature ?? null
                ] : null,
                'oic_staff' => $trail->oicStaff ? [
                    'name' => $trail->oicStaff->fname . ' ' . $trail->oicStaff->lname,
                    'job_title' => $trail->oicStaff->job_name ?? $trail->oicStaff->position ?? 'N/A',
                    'work_email' => $trail->oicStaff->work_email ?? 'N/A',
                    'signature' => $trail->oicStaff->signature ?? null
                ] : null,
                'date' => $trail->created_at ? $trail->created_at->format('d/m/Y H:i:s') : 'N/A',
                'approval_order' => $trail->approval_order ?? null
            ];
        })->values();

        return $workflowInfo;
    }

    
    /**
     * Generate a printable PDF for a Non-Travel Memo.
     */
    public function print(NonTravelMemo $nonTravel)
    {
        // Eager load needed relations
        $nonTravel->load([
            'staff', 
            'nonTravelMemoCategory', 
            'division', 
            'fundType',
            'approvalTrails.staff',
            'approvalTrails.oicStaff',
            'approvalTrails.workflowDefinition'
        ]);

        // Decode JSON fields safely
        $locationIds = is_string($nonTravel->location_id)
            ? json_decode($nonTravel->location_id, true)
            : ($nonTravel->location_id ?? []);

        $budgetIds = is_string($nonTravel->budget_id)
            ? json_decode($nonTravel->budget_id, true)
            : ($nonTravel->budget_id ?? []);

        $attachments = is_string($nonTravel->attachment)
            ? json_decode($nonTravel->attachment, true)
            : ($nonTravel->attachment ?? []);
        $attachments = is_array($attachments) ? $attachments : [];

        $breakdown = $nonTravel->budget_breakdown;
        if (!is_array($breakdown)) {
            $decoded = json_decode($breakdown, true);
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }
            $breakdown = is_array($decoded) ? $decoded : [];
        }

        // Fetch related collections
        $locations = Location::whereIn('id', $locationIds ?: [])->get();
        $fundCodes = FundCode::whereIn('id', $budgetIds ?: [])->with('fundType')->get();

        // Get approval trails (not activity approval trails)
        $approvalTrails = $nonTravel->approvalTrails;

        // Get workflow information
        $workflowInfo = $this->getComprehensiveWorkflowInfo($nonTravel);
        $organizedWorkflowSteps = \App\Helpers\PrintHelper::organizeWorkflowStepsBySection($workflowInfo['workflow_steps']);

        // Use mPDF helper function
        $print = false;
        $pdf = mpdf_print('non-travel.memo-pdf-simple', [
            'nonTravel' => $nonTravel,
            'locations' => $locations,
            'fundCodes' => $fundCodes,
            'attachments' => $attachments,
            'budgetBreakdown' => $breakdown,
            'approval_trails' => $approvalTrails,
            'matrix_approval_trails' => $approvalTrails, // For compatibility with activities template
            'workflow_info' => $workflowInfo,
            'organized_workflow_steps' => $organizedWorkflowSteps
        ], ['preview_html' => $print]);

        // Generate filename
        $filename = 'Non_Travel_Memo_' . $nonTravel->id . '_' . now()->format('Y-m-d') . '.pdf';

        // Return PDF for display in browser using mPDF Output method
        return response($pdf->Output($filename, 'I'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"'
        ]);
    }

    /**
     * Export my submitted memos to CSV
     */
    public function exportMySubmittedCsv(Request $request)
    {
        $currentStaffId = user_session('staff_id');
        
        $query = NonTravelMemo::with([
            'staff', 
            'division', 
            'nonTravelMemoCategory'
        ])->where('staff_id', $currentStaffId);

        // Apply filters
        if ($request->filled('category_id')) {
            $query->where('non_travel_memo_category_id', $request->category_id);
        }
        if ($request->filled('division_id')) {
            $query->where('division_id', $request->division_id);
        }
        if ($request->filled('status')) {
            $query->where('overall_status', $request->status);
        }

        $memos = $query->latest()->get();

        $filename = 'my_submitted_non_travel_memos_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($memos) {
            $file = fopen('php://output', 'w');
            
            // CSV Headers
            fputcsv($file, [
                'ID', 'Activity Title', 'Activity Code', 'Category', 'Division', 
                'Memo Date', 'Status', 'Approval Level', 'Created Date', 'Updated Date'
            ]);

            // CSV Data
            foreach ($memos as $memo) {
                fputcsv($file, [
                    $memo->id,
                    $memo->activity_title ?? 'N/A',
                    $memo->workplan_activity_code ?? 'N/A',
                    $memo->nonTravelMemoCategory ? $memo->nonTravelMemoCategory->name : 'N/A',
                    $memo->division ? $memo->division->division_name : 'N/A',
                    $memo->memo_date ? \Carbon\Carbon::parse($memo->memo_date)->format('Y-m-d') : 'N/A',
                    $memo->overall_status ?? 'N/A',
                    $memo->approval_level ?? 'N/A',
                    $memo->created_at ? $memo->created_at->format('Y-m-d') : 'N/A',
                    $memo->updated_at ? $memo->updated_at->format('Y-m-d') : 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export all non-travel memos to CSV (for users with permission 87)
     */
    public function exportAllCsv(Request $request)
    {
        if (!in_array(87, user_session('permissions', []))) {
            abort(403, 'Unauthorized access');
        }

        $query = NonTravelMemo::with([
            'staff', 
            'division', 
            'nonTravelMemoCategory'
        ]);

        // Apply filters
        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }
        if ($request->filled('category_id')) {
            $query->where('non_travel_memo_category_id', $request->category_id);
        }
        if ($request->filled('division_id')) {
            $query->where('division_id', $request->division_id);
        }
        if ($request->filled('status')) {
            $query->where('overall_status', $request->status);
        }

        $memos = $query->latest()->get();

        $filename = 'all_non_travel_memos_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($memos) {
            $file = fopen('php://output', 'w');
            
            // CSV Headers
            fputcsv($file, [
                'ID', 'Activity Title', 'Activity Code', 'Category', 'Division', 
                'Staff', 'Memo Date', 'Status', 'Approval Level', 'Created Date', 'Updated Date'
            ]);

            // CSV Data
            foreach ($memos as $memo) {
                fputcsv($file, [
                    $memo->id,
                    $memo->activity_title ?? 'N/A',
                    $memo->workplan_activity_code ?? 'N/A',
                    $memo->nonTravelMemoCategory ? $memo->nonTravelMemoCategory->name : 'N/A',
                    $memo->division ? $memo->division->division_name : 'N/A',
                    $memo->staff ? ($memo->staff->fname . ' ' . $memo->staff->lname) : 'N/A',
                    $memo->memo_date ? \Carbon\Carbon::parse($memo->memo_date)->format('Y-m-d') : 'N/A',
                    $memo->overall_status ?? 'N/A',
                    $memo->approval_level ?? 'N/A',
                    $memo->created_at ? $memo->created_at->format('Y-m-d') : 'N/A',
                    $memo->updated_at ? $memo->updated_at->format('Y-m-d') : 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
