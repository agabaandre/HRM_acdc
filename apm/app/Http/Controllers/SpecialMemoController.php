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

class SpecialMemoController extends Controller
{
    public function index(Request $request): View
    {
        // Cache lookup tables for 60 minutes
        $staff = Cache::remember('special_memo_staff', 60 * 60, fn() => Staff::active()->get());
        $divisions = Cache::remember('special_memo_divisions', 60 * 60, fn() => \App\Models\Division::all());
        $requestTypes = Cache::remember('special_memo_request_types', 60 * 60, fn() => RequestType::all());

        // Get current user's staff ID
        $currentStaffId = user_session('staff_id');
        $userDivisionId = user_session('division_id');

        // Tab 1: My Submitted Special Memos (memos created by current user)
        $mySubmittedQuery = SpecialMemo::with([
            'staff', 
            'division', 
            'requestType', 
            'forwardWorkflow.workflowDefinitions.approvers.staff'
        ])
            ->where('staff_id', $currentStaffId);

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

        $mySubmittedMemos = $mySubmittedQuery->latest()->paginate(20)->withQueryString();

        // Tab 2: All Special Memos (visible to users with permission 87)
        $allMemos = collect();
        if (in_array(87, user_session('permissions', []))) {
            $allMemosQuery = SpecialMemo::with([
                'staff', 
                'division', 
                'requestType', 
                'forwardWorkflow.workflowDefinitions.approvers.staff'
            ]);

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

            $allMemos = $allMemosQuery->latest()->paginate(20)->withQueryString();
        }

        return view('special-memo.index', compact(
            'mySubmittedMemos', 
            'allMemos', 
            'staff', 
            'requestTypes', 
            'divisions',
            'currentStaffId',
            'userDivisionId'
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
    
        // Staff only from current matrix division
        $staff =  Staff::active()
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
    public function store(Request $request): RedirectResponse
    {
        $userStaffId = session('user.auth_staff_id');
        $userDivisionId = session('user.division_id');
    
        $validated = $request->validate([
            'activity_title' => 'required|string|max:255',
            'location_id' => 'required|array|min:1',
            'location_id.*' => 'exists:locations,id',
            'participant_start' => 'required|array',
            'participant_end' => 'required|array',
            'participant_days' => 'required|array',
        ]);
    
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
    
            // Determine status based on action
            $action = $request->input('action', 'draft');
            $isDraft = ($action === 'draft');
            $overallStatus = $isDraft ? 'draft' : 'pending';

            $specialMemo = SpecialMemo::create([
                'is_special_memo' => 1,
                'is_draft' => $isDraft,
                'staff_id' => $userStaffId,
                'division_id' => $userDivisionId,
                'responsible_person_id' => $request->input('responsible_person_id', 1),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'activity_title' => $request->input('activity_title'),
                'background' => $request->input('background', ''),
                'justification' => $request->input('justification', ''),
                'activity_request_remarks' => $request->input('activity_request_remarks', ''),
                'key_result_area' => $request->input('key_result_link', '-'),
                'request_type_id' => (int) $request->input('request_type_id', 1),
                'fund_type_id' => (int) $request->input('fund_type', 1),
                'status' => $overallStatus,
                'forward_workflow_id' => $isDraft ? null : 1, // Set workflow ID only when submitting
                'reverse_workflow_id' => $isDraft ? null : 1,
                'overall_status' => $overallStatus,
                'approval_level' => $isDraft ? 0 : 1, // Set approval level only when submitting
                'next_approval_level' => $isDraft ? null : 2, // Set next level only when submitting
                'total_participants' => (int) $request->input('total_participants', 0),
                'total_external_participants' => (int) $request->input('total_external_participants', 0),
    
                'location_id' => json_encode($request->input('location_id', [])),
                'internal_participants' => json_encode($internalParticipants),
    
                'budget_id' => json_encode($request->input('budget_codes', [])),
                'budget' => json_encode($request->input('budget', [])),
                'attachment' => json_encode($request->input('attachments', [])),
    
                'supporting_reasons' => $request->input('supporting_reasons', null),
            ]);
    
            DB::commit();
    
            $message = ($action === 'submit') 
                ? 'Special Memo created and submitted for approval successfully.'
                : 'Special Memo saved as draft successfully.';
    
            return redirect()->route('special-memo.index')->with([
                'msg' => $message,
                'type' => 'success',
            ]);
    
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Error creating special memo', ['exception' => $e]);
    
            return redirect()->back()->withInput()->with([
                'msg' => 'An error occurred while creating the special memo. Please try again.',
                'type' => 'error',
            ]);
        }
    }
    

    /**
     * Display the specified special memo.
     */
    public function show(SpecialMemo $specialMemo): View
    {
        $specialMemo->load(['staff', 'division', 'staff.division']);
        
        return view('special-memo.show', compact('specialMemo'));
    }

    /**
     * Show the form for editing the specified special memo.
     */
    public function edit(SpecialMemo $specialMemo): View
    {
        ini_set('memory_limit', '1024M');
        $division_id = user_session('division_id');
      
        // Request Types
        $requestTypes = RequestType::all();
    
        // Staff only from current matrix division
        $staff =  Staff::active()
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

        // dd($specialMemo->budget);

        // Fix for potentially double-encoded or malformed JSON in budget
        $budget = $specialMemo->budget;

        if (!is_array($budget)) {
            $decoded = json_decode($budget, true);
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }
            $budget = is_array($decoded) ? $decoded : [];
        }

        // Replace original budget on the model (optional, for view consistency)
        $specialMemo->budget = $budget;

    
        return view('special-memo.edit', [
            'specialMemo' => $specialMemo,
            'requestTypes' => $requestTypes,
            'staff' => $staff,
            'allStaffGroupedByDivision' => $allStaff,
            'locations' => $locations,
            'fundTypes' => $fundTypes,
            'budgetCodes' => $budgetCodes,
            'costItems' => $costItems,
            'title' => 'Edit Special Memo',
            'editing' => true,
        ]);
    }

    /**
     * Update the specified special memo.
     */
    public function update(Request $request, SpecialMemo $specialMemo): RedirectResponse
    {
        $validated = $request->validate([
            'activity_title' => 'required|string|max:255',
            'location_id' => 'required|array|min:1',
            'location_id.*' => 'exists:locations,id',
            'participant_start' => 'required|array',
            'participant_end' => 'required|array',
            'participant_days' => 'required|array',
        ]);
    
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
    
            // Determine status based on action
            $action = $request->input('action', 'draft');
            $isDraft = ($action === 'draft');
            $overallStatus = $isDraft ? 'draft' : 'pending';

            $updateData = [
                'responsible_person_id' => $request->input('responsible_person_id', 1),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'activity_title' => $request->input('activity_title'),
                'background' => $request->input('background', ''),
                'justification' => $request->input('justification', ''),
                'activity_request_remarks' => $request->input('activity_request_remarks', ''),
                'key_result_area' => $request->input('key_result_link', '-'),
                'request_type_id' => (int) $request->input('request_type_id', 1),
                'fund_type_id' => (int) $request->input('fund_type', 1),
                'is_draft' => $isDraft,
                'status' => $overallStatus,
                'overall_status' => $overallStatus,
                'total_participants' => (int) $request->input('total_participants', 0),
                'total_external_participants' => (int) $request->input('total_external_participants', 0),
    
                'location_id' => json_encode($request->input('location_id', [])),
                'internal_participants' => json_encode($internalParticipants),
    
                'budget_id' => json_encode($request->input('budget_codes', [])),
                'budget' => json_encode($request->input('budget', [])),
                'attachment' => json_encode($request->input('attachments', [])),
    
                'supporting_reasons' => $request->input('supporting_reasons', null),
            ];

            // Add workflow fields only when submitting for approval
            if (!$isDraft) {
                $updateData['forward_workflow_id'] = 1;
                $updateData['approval_level'] = 1;
                $updateData['next_approval_level'] = 2;
            } else {
                $updateData['forward_workflow_id'] = null;
                $updateData['approval_level'] = 0;
                $updateData['next_approval_level'] = null;
            }

            $specialMemo->update($updateData);
    
            DB::commit();
    
            $message = ($request->input('action') === 'submit') 
                ? 'Special Memo updated and submitted for approval successfully.'
                : 'Special Memo updated and saved as draft successfully.';
    
            return redirect()->route('special-memo.index')->with([
                'msg' => $message,
                'type' => 'success',
            ]);
    
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Error updating special memo', ['exception' => $e]);
    
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
        // Delete related attachments from storage
        if (!empty($specialMemo->attachment)) {
            foreach ($specialMemo->attachment as $attachment) {
                if (isset($attachment['path'])) {
                    Storage::disk('public')->delete($attachment['path']);
                }
            }
        }
        
        $specialMemo->delete();
        
        return redirect()
            ->route('special-memo.index')
            ->with('success', 'Special memo deleted successfully.');
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
        if ($specialMemo->overall_status !== 'draft') {
            return redirect()->back()->with([
                'msg' => 'Only draft special memos can be submitted for approval.',
                'type' => 'error',
            ]);
        }

        // Update the memo status directly
        $specialMemo->overall_status = 'pending';
        $specialMemo->approval_level = 1;
        $specialMemo->forward_workflow_id = 1;
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
            'action' => 'required|in:approved,rejected,returned',
            'comment' => 'nullable|string|max:1000',
        ]);

        // Use the generic approval system
        $genericController = app(\App\Http\Controllers\GenericApprovalController::class);
        return $genericController->updateStatus($request, 'SpecialMemo', $specialMemo->id);
    }

    /**
     * Show approval status page.
     */
    public function status(SpecialMemo $specialMemo): View
    {
        $specialMemo->load(['staff', 'division', 'forwardWorkflow']);
        
        // Get approval level information
        $approvalLevels = $this->getApprovalLevels($specialMemo);
        
        return view('special-memo.status', compact('specialMemo', 'approvalLevels'));
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
        // Eager load relations (exclude unknown relationships)
        $specialMemo->load(['staff', 'division', 'requestType']);

        // Decode JSON fields safely
        $locationIds = is_string($specialMemo->location_id)
            ? json_decode($specialMemo->location_id, true)
            : ($specialMemo->location_id ?? []);

        $budgetIds = is_string($specialMemo->budget_id)
            ? json_decode($specialMemo->budget_id, true)
            : ($specialMemo->budget_id ?? []);

        $budgetItems = $specialMemo->budget;
        if (!is_array($budgetItems)) {
            $decoded = json_decode($budgetItems, true);
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }
            $budgetItems = is_array($decoded) ? $decoded : [];
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
            $staffDetails = Staff::whereIn('staff_id', array_keys($rawParticipants))
                ->get()
                ->keyBy('staff_id');

            foreach ($rawParticipants as $staffId => $participantData) {
                $internalParticipants[] = [
                    'staff' => $staffDetails[$staffId] ?? null,
                    'participant_start' => $participantData['participant_start'] ?? null,
                    'participant_end' => $participantData['participant_end'] ?? null,
                    'participant_days' => $participantData['participant_days'] ?? null,
                ];
            }
        }

        // Fetch related collections
        $locations = Location::whereIn('id', $locationIds ?: [])->get();
        $fundCodes = FundCode::whereIn('id', $budgetIds ?: [])->get();

        // Render HTML for PDF
        $html = view('special-memo.print', [
            'specialMemo' => $specialMemo,
            'locations' => $locations,
            'fundCodes' => $fundCodes,
            'internalParticipants' => $internalParticipants,
            'budgetItems' => $budgetItems,
            'attachments' => $attachments,
        ])->render();

        // Use dompdf wrapper (barryvdh/laravel-dompdf)
        $pdf = app('dompdf.wrapper');
        $pdf->loadHTML($html)->setPaper('A4', 'portrait');

        $filename = 'special_memo_'.$specialMemo->id.'.pdf';
        return $pdf->stream($filename);
    }
}
