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

class SpecialMemoController extends Controller
{
    public function index(Request $request): View
    {
        $query = SpecialMemo::with(['staff','division'])->latest();
        
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
        
        // Hide draft memos from non-creators
        $query->where(function($q) use ($currentStaffId) {
            $q->where('is_draft', false)  // Show non-draft memos to everyone
              ->orWhere('staff_id', $currentStaffId);  // Show all memos to their creator
        });
        
        $specialMemos = $query->paginate(10);
        $staff = Staff::active()->get();
    
        // Get distinct divisions from staff table
        $divisions = Staff::select('division_id', 'division_name')
            ->whereNotNull('division_id')
            ->distinct()
            ->orderBy('division_name')
            ->get();
    
        return view('special-memo.index', compact('specialMemos', 'staff', 'divisions'));
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
            $status = ($action === 'submit') ? SpecialMemo::STATUS_SUBMITTED : SpecialMemo::STATUS_DRAFT;
            $overallStatus = ($action === 'submit') ? SpecialMemo::STATUS_SUBMITTED : SpecialMemo::STATUS_DRAFT;

            $specialMemo = SpecialMemo::create([
                'is_special_memo' => 1,
                'is_draft' => ($action === 'draft'),
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
                'status' => $status,
                'forward_workflow_id' => 3,
                'reverse_workflow_id' => null,
                'overall_status' => $overallStatus,
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
        $specialMemo->load(['staff', 'division']);
        
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
            $status = ($action === 'submit') ? SpecialMemo::STATUS_SUBMITTED : SpecialMemo::STATUS_DRAFT;
            $overallStatus = ($action === 'submit') ? SpecialMemo::STATUS_SUBMITTED : SpecialMemo::STATUS_DRAFT;

            $specialMemo->update([
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
                'is_draft' => ($action === 'draft'),
                'status' => $status,
                'overall_status' => $overallStatus,
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
        if (!$specialMemo->is_draft) {
            return redirect()->back()->with([
                'msg' => 'Only draft special memos can be submitted for approval.',
                'type' => 'error',
            ]);
        }

        $specialMemo->submitForApproval();

        return redirect()->route('special-memo.show', $specialMemo)->with([
            'msg' => 'Special memo submitted for approval successfully.',
            'type' => 'success',
        ]);
    }

    /**
     * Update approval status.
     */
    public function updateStatus(Request $request, SpecialMemo $specialMemo): RedirectResponse
    {
        $request->validate([
            'action' => 'required|in:approved,rejected,returned',
            'comment' => 'nullable|string|max:1000',
        ]);

        $approvalService = app(ApprovalService::class);
        
        if (!$approvalService->canTakeAction($specialMemo, user_session('staff_id'))) {
            return redirect()->back()->with([
                'msg' => 'You are not authorized to take this action.',
                'type' => 'error',
            ]);
        }

        $specialMemo->updateApprovalStatus($request->action, $request->comment);

        $message = match($request->action) {
            'approved' => 'Special memo approved successfully.',
            'rejected' => 'Special memo rejected.',
            'returned' => 'Special memo returned for revision.',
            default => 'Status updated successfully.'
        };

        return redirect()->route('special-memo.show', $specialMemo)->with([
            'msg' => $message,
            'type' => 'success',
        ]);
    }

    /**
     * Show approval status page.
     */
    public function status(SpecialMemo $specialMemo): View
    {
        $specialMemo->load(['staff', 'division']);
        
        return view('special-memo.status', compact('specialMemo'));
    }
}
