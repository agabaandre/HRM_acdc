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

class NonTravelMemoController extends Controller
{
    /** List all memos with optional filters */
    public function index(Request $request): View
    {
        // Cache lookup tables for 60 minutes
        $staff  = Cache::remember('non_travel_staff', 60 * 60, fn() => Staff::active()->get());
        $categories = Cache::remember('non_travel_categories', 60 * 60, fn() => NonTravelMemoCategory::all());

        // Base query with eager loads
        $query = NonTravelMemo::with(['staff', 'nonTravelMemoCategory']);

        // Apply filters when present
        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }
        if ($request->filled('category_id')) {
            $query->where('non_travel_memo_category_id', $request->category_id);
        }

        // Paginate and preserve filters in the query string
        $nonTravelMemos = $query->latest()->paginate(10)->withQueryString();

        return view('non-travel.index', compact('nonTravelMemos', 'staff', 'categories'));
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
            'next_approval_level' => 2,
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
        return view('non-travel.show', compact('nonTravel'));
    }

    /** Show edit form */
    public function edit(NonTravelMemo $nonTravel): View
    {
        ini_set('memory_limit', '1024M');

        // Cache locations for 60 minutes to avoid reloading a large dataset
        $locations = Cache::remember('non_travel_locations', 60 * 60, function () {
            return Location::all();
        });
        $fundTypes = FundType::all();

        return view('non-travel.edit', [
            'nonTravel' => $nonTravel,
            'categories' => NonTravelMemoCategory::all(),
            'staff'  => Staff::active()->get(),
            'locations'  => $locations,
            'budgets'    => FundCode::all(),
            'fundTypes' => $fundTypes,
            'workflows' => \App\Models\Workflow::all()
        ]);
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
     * Update approval status.
     */
    public function updateStatus(Request $request, NonTravelMemo $nonTravel): RedirectResponse
    {
        $request->validate([
            'action' => 'required|in:approved,rejected,returned',
            'comment' => 'nullable|string|max:1000',
        ]);

        $approvalService = app(ApprovalService::class);
        if (!$approvalService->canTakeAction($nonTravel, user_session('staff_id'))) {
            return redirect()->back()->with([
                'msg' => 'You are not authorized to take this action.',
                'type' => 'error',
            ]);
        }

        $nonTravel->updateApprovalStatus($request->action, $request->comment);

        $message = match($request->action) {
            'approved' => 'Non-travel memo approved successfully.',
            'rejected' => 'Non-travel memo rejected.',
            'returned' => 'Non-travel memo returned for revision.',
            default => 'Status updated successfully.'
        };

        return redirect()->route('non-travel.show', $nonTravel)->with([
            'msg' => $message,
            'type' => 'success',
        ]);
    }

    /**
     * Show approval status page.
     */
    public function status(NonTravelMemo $nonTravel): View
    {
        $nonTravel->load(['staff']);
        return view('non-travel.status', compact('nonTravel'));
    }
}
