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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class SpecialMemoController extends Controller
{
    public function index(Request $request): View
    {
        $query = SpecialMemo::with(['staff','division'])->latest();

     
    
        if ($request->has('staff_id') && $request->staff_id) {
            $query->where('staff_id', $request->staff_id);
        }
    
        if ($request->has('division_id') && $request->division_id) {
            $query->where('division_id', $request->division_id);
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }
        //dd($query);
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
    
            SpecialMemo::create([
                'is_special_memo' => 1,
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
                'status' => Activity::STATUS_DRAFT,
                'forward_workflow_id' => 3,
                'reverse_workflow_id' => null,
    
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
    
            return redirect()->route('special-memo.index')->with([
                'msg' => 'Special Memo created successfully.',
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
        $staff = Staff::active()->get();
        $divisions = Division::all();
        $workflows = Workflow::all();
        
        return view('special-memo.edit', compact('specialMemo', 'staff', 'divisions', 'workflows'));
    }

    /**
     * Update the specified special memo.
     */
    public function update(Request $request, SpecialMemo $specialMemo): RedirectResponse
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'forward_workflow_id' => 'required|exists:workflows,id',
            'reverse_workflow_id' => 'required|exists:workflows,id',
            'memo_number' => 'required|string|unique:special_memos,memo_number,' . $specialMemo->id,
            'memo_date' => 'required|date',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'division_id' => 'required|exists:divisions,id',
            'recipients' => 'sometimes|array',
            'attachment' => 'nullable|array',
            'attachment.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'priority' => 'required|in:low,medium,high,urgent',
            'status' => 'sometimes|in:draft,submitted,approved,rejected',
            'remarks' => 'nullable|string',
        ]);
        
        // Handle attachments update
        $existingAttachments = $specialMemo->attachment ?? [];
        $attachments = $existingAttachments;
        
        // Process new attachments
        if ($request->hasFile('attachment')) {
            foreach ($request->file('attachment') as $file) {
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('special-memo-attachments', $filename, 'public');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ];
            }
        }
        
        $validated['attachment'] = $attachments;
        
        $specialMemo->update($validated);
        
        return redirect()
            ->route('special-memo.index')
            ->with('success', 'Special memo updated successfully.');
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
}
