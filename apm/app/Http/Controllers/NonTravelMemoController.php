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

class NonTravelMemoController extends Controller
{
    /** List all memos with optional filters */
    public function index(Request $request): View
    {
        // Cache lookup tables for 60 minutes
        $staff  = Cache::remember('non_travel_staff', 60 * 60, fn() => Staff::active()->get());
        $categories = Cache::remember('non_travel_categories', 60 * 60, fn() => NonTravelMemoCategory::all());
        $divisions = Cache::remember('non_travel_divisions', 60 * 60, fn() => \App\Models\Division::all());

        // Base query with eager loads
        $query = NonTravelMemo::with(['staff', 'division', 'nonTravelMemoCategory']);

        // Apply division filter only if user is not a division-specific approver
        if (!isDivisionApprover() || !empty(user_session('division_id'))) {
            $query->whereHas('staff', function($q) {
                $q->where('division_id', user_session('division_id'));
            });
        }else{
            //check approval workflow
            $approvers = Approver::where('staff_id',user_session('staff_id'))->get();
            $approvers = $approvers->pluck('workflow_dfn_id')->toArray();
            $workflow_dfns = WorkflowDefinition::whereIn('id',$approvers)->get();
            $query->whereIn('approval_level',$workflow_dfns->pluck('approval_order')->toArray());
        }

        // Apply filters when present
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

        // Paginate and preserve filters in the query string
        $nonTravelMemos = $query->latest()->paginate(10)->withQueryString();

        return view('non-travel.index', compact('nonTravelMemos', 'staff', 'categories', 'divisions'));
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
    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        //dd($request->all());

        $data = $request->validate([
            //'staff_id'                     => 'required|exists:staff,id',
            'date_required'                => 'required|date',
            'location_id'                  => 'required|array|min:1',
            'location_id.*'                => 'exists:locations,id',
            'non_travel_memo_category_id'  => 'required|exists:non_travel_memo_categories,id',
            'title'                        => 'required|string|max:255',
            'approval'                     => 'required|string',
            'background'                   => 'required|string',
            'description'                  => 'required|string',
            'other_information'            => 'nullable|string',
            //'attachments'                  => 'nullable|array',
           // 'attachments.*'                => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'budget_codes'                 => 'required|array|min:1',
            'budget_codes.*'               => 'exists:fund_codes,id',
            'budget'                       => 'required|array',
            //'budget.*'                     => 'array',
        ]);

        $data['staff_id'] = user_session('staff_id');
        $data['division_id'] = user_session('division_id');

        // Handle attachments
        $files = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $f) {
                $path = $f->store('non-travel/attachments', 'public');
                $files[] = [
                    'name' => $f->getClientOriginalName(),
                    'path' => $path,
                    'size' => $f->getSize(),
                    'mime_type' => $f->getMimeType(),
                    'uploaded_at' => now()->toDateTimeString(),
                ];
            }
        }

        // Prepare JSON columns
        $locationJson = json_encode($data['location_id']);
        $budgetIdJson = json_encode($data['budget_codes']);
        $budgetBreakdownJson = json_encode($data['budget']);
        $attachmentsJson = json_encode($files);

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
            'activity_request_remarks' => $data['approval'],
            'justification' => $data['description'],
            'budget_breakdown' => $budgetBreakdownJson,
            'attachment' => $attachmentsJson,
            'forward_workflow_id' => 1,
            'approval_level' => 0,
            'next_approval_level' => 1,
            'overall_status' => 'draft',
        ]);

        // Deduct budget code balances using the helper
        $budgetCodes = $data['budget_codes'];
        $budgetItems = $data['budget'];
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
                reduce_fund_code_balance($codeId, $total);
            }
        }

        // Always return a valid redirect response
        return redirect()->route('non-travel.index')
            ->with('success', 'Request submitted successfully.');
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

        // Retrieve other necessary data
        $categories = NonTravelMemoCategory::all();
        $locations = Location::all();
        $workflows = WorkflowDefinition::all();
        $staff = Staff::active()->get(); // Retrieve active staff members

        return view('non-travel.edit', compact(
            'nonTravel', 
            'budgets', 
            'selectedBudgetCodes', 
            'categories', 
            'locations', 
            'workflows', 
            'staff' // Pass staff to the view
        ));
    }

    /** Update memo */
    public function update(Request $request, NonTravelMemo $nonTravel): RedirectResponse
    {
        $data = $request->validate([
            'memo_date'                    => 'required|date',
            'location_id'                  => 'required|array|min:1',
            'location_id.*'                => 'exists:locations,id',
            'non_travel_memo_category_id'  => 'required|exists:non_travel_memo_categories,id',
            'activity_title'               => 'required|string|max:255',
            'activity_request_remarks'     => 'required|string',
            'background'                   => 'required|string',
            'justification'                => 'required|string',
            'other_information'            => 'nullable|string',
            'budget_breakdown'             => 'required|array|min:1',
        ]);

        // Handle attachments
        $files = $nonTravel->attachment ?? [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $f) {
                $path = $f->store('non-travel/attachments', 'public');
                $files[] = [
                    'name' => $f->getClientOriginalName(),
                    'path' => $path,
                    'size' => $f->getSize(),
                    'mime_type' => $f->getMimeType(),
                    'uploaded_at' => now()->toDateTimeString(),
                ];
            }
        }

        // Prepare JSON columns
        $locationJson = json_encode($data['location_id']);
        $budgetBreakdownJson = json_encode($data['budget_breakdown']);
        $attachmentsJson = json_encode($files);

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

        return redirect()->route('non-travel.show', $nonTravel)
            ->with('success', 'Non-travel memo updated successfully.');
    }

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
        if ($nonTravel->overall_status !== 'draft') {
            return redirect()->back()->with([
                'msg' => 'Only draft non-travel memos can be submitted for approval.',
                'type' => 'error',
            ]);
        }

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
        $request->validate([
            'action' => 'required|in:approved,rejected,returned',
            'comment' => 'nullable|string|max:1000',
        ]);

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
     * Generate a printable PDF for a Non-Travel Memo.
     */
    public function print(NonTravelMemo $nonTravel)
    {
        // Eager load needed relations
        $nonTravel->load(['staff', 'nonTravelMemoCategory']);

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
        $fundCodes = FundCode::whereIn('id', $budgetIds ?: [])->get();

        // Render HTML for PDF
        $html = view('non-travel.print', [
            'nonTravel' => $nonTravel,
            'locations' => $locations,
            'fundCodes' => $fundCodes,
            'attachments' => $attachments,
            'breakdown' => $breakdown,
        ])->render();

        // Use dompdf wrapper to generate and stream PDF
        $pdf = app('dompdf.wrapper');
        $pdf->loadHTML($html)->setPaper('A4', 'portrait');

        $filename = 'non_travel_memo_'.$nonTravel->id.'.pdf';
        return $pdf->stream($filename);
    }
}
