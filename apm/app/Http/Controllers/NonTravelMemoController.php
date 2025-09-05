<?php

namespace App\Http\Controllers;

use App\Models\NonTravelMemo;
use App\Models\NonTravelMemoCategory;
use App\Models\Staff;
use App\Models\Location;
use App\Models\FundType;
use App\Models\FundCode;
use App\Models\CostItem;
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
    public function index(Request $request): View
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
        if ($request->filled('status')) {
            $mySubmittedQuery->where('overall_status', $request->status);
        }

        $mySubmittedMemos = $mySubmittedQuery->latest()->paginate(20)->withQueryString();

        // Tab 2: All Non-Travel Memos (visible to users with permission 87)
        $allMemos = collect();
        if (in_array(87, user_session('permissions', []))) {
            $allMemosQuery = NonTravelMemo::with([
                'staff', 
                'division', 
                'nonTravelMemoCategory', 
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

            $allMemos = $allMemosQuery->latest()->paginate(20)->withQueryString();
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

        $data = $request->validate([
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
            'budget_codes'                 => 'required|array|min:1',
            'budget_codes.*'               => 'exists:fund_codes,id',
            'budget_breakdown'             => 'required|array',
            //'budget.*'                     => 'array',
        ]);

        $data['staff_id'] = user_session('staff_id');
        $data['division_id'] = user_session('division_id');

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

        // Save to DB
        $memo = NonTravelMemo::create([
            'reverse_workflow_id' => (int)($request->input('reverse_workflow_id', 1)),
            'workplan_activity_code' => $request->input('activity_code', ''),
            'staff_id' => (int)$data['staff_id'],
            'division_id' => (int)$data['division_id'],
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
            'forward_workflow_id' => $isDraft ? null : 1,
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
       
        $nonTravel->load(['staff', 'nonTravelMemoCategory']);
        
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
        // Check if memo is in draft status - only allow editing of drafts
        if ($nonTravel->overall_status !== 'draft') {
            return redirect()
                ->route('non-travel.show', $nonTravel)
                ->with('error', 'Cannot edit memo. Only draft memos can be edited.');
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
        // Check if memo is in draft status - only allow updating of drafts
        if ($nonTravel->overall_status !== 'draft') {
            return redirect()
                ->route('non-travel.show', $nonTravel)
                ->with('error', 'Cannot update memo. Only draft memos can be updated.');
        }

        $data = $request->validate([
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
            'budget_breakdown'             => 'required|array|min:1',
        ]);

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
        // Prepare JSON columns
        $locationJson = json_encode($data['location_id']);
        $budgetBreakdownJson = json_encode($data['budget_breakdown']);
        $attachmentsJson = json_encode($attachments);

        // Update the memo
        $nonTravel->update([
            'memo_date' => $data['memo_date'],
            'location_id' => $locationJson,
            'non_travel_memo_category_id' => $data['non_travel_memo_category_id'],
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
            'action' => 'required|in:approved,rejected,returned',
            'comment' => 'nullable|string|max:1000',
        ]);

        // Debug: Log validation passed
        \Illuminate\Support\Facades\Log::info('Validation passed, calling generic approval controller');

        // Use the generic approval system
        $genericController = app(\App\Http\Controllers\GenericApprovalController::class);
        return $genericController->updateStatus($request, 'NonTravelMemo', $nonTravel->id);
    }

    /**
     * Show approval status page.
     */
    public function status(NonTravelMemo $nonTravel): View
    {
        $nonTravel->load(['staff', 'division', 'forwardWorkflow']);
        
        // Get approval level information
        $approvalLevels = $this->getApprovalLevels($nonTravel);
        
        return view('non-travel.status', compact('nonTravel', 'approvalLevels'));
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
            'workflow_steps' => [],
            'approval_trails' => []
        ];

        if (!$nonTravel->forward_workflow_id) {
            return $workflowInfo;
        }

        // Get workflow definitions
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

        // Get approval trails
        $approvalTrails = $nonTravel->approvalTrails()->with(['staff', 'oicStaff', 'workflowDefinition'])->get();

        foreach ($workflowDefinitions as $definition) {
            $approvers = [];
            
            if ($definition->is_division_specific && $nonTravel->division) {
                $staffId = $nonTravel->division->{$definition->division_reference_column} ?? null;
                if ($staffId) {
                    $staff = \App\Models\Staff::where('staff_id', $staffId)->first();
                    if ($staff) {
                        $approvers[] = [
                            'staff' => $staff->toArray(),
                            'oic_staff' => null
                        ];
                    }
                }
            } else {
                foreach ($definition->approvers as $approver) {
                    $approvers[] = [
                        'staff' => $approver->staff ? $approver->staff->toArray() : null,
                        'oic_staff' => $approver->oicStaff ? $approver->oicStaff->toArray() : null
                    ];
                }
            }

            $workflowInfo['workflow_steps'][] = [
                'order' => $definition->approval_order,
                'role' => $definition->role,
                'approvers' => $approvers,
                'is_division_specific' => $definition->is_division_specific,
                'category' => $definition->category
            ];
        }

        $workflowInfo['approval_trails'] = $approvalTrails;
        $workflowInfo['current_level'] = $nonTravel->approval_level;

        return $workflowInfo;
    }

    /**
     * Organize workflow steps by section (to, through, from)
     */
    private function organizeWorkflowSteps($workflowInfo)
    {
        $organized = [
            'to' => collect(),
            'through' => collect(),
            'from' => collect(),
            'others' => collect()
        ];

        foreach ($workflowInfo['workflow_steps'] as $step) {
            $section = 'others'; // default
            
            // Determine section based on approval order
            if ($step['order'] <= 2) {
                $section = 'to';
            } elseif ($step['order'] <= 6) {
                $section = 'through';
            } elseif ($step['order'] <= 8) {
                $section = 'from';
            }

            $organized[$section]->push($step);
        }

        return $organized;
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
        $organizedWorkflowSteps = $this->organizeWorkflowSteps($workflowInfo);

        // Use mPDF helper function
        $print = false;
        $pdf = mpdf_print('non-travel.memo-pdf-simple', [
            'nonTravel' => $nonTravel,
            'locations' => $locations,
            'fundCodes' => $fundCodes,
            'attachments' => $attachments,
            'budgetBreakdown' => $breakdown,
            'approval_trails' => $approvalTrails,
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
