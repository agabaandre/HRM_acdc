<?php

namespace App\Http\Controllers;

use App\Models\NonTravelMemo;
use App\Models\NonTravelMemoCategory;
use App\Models\Staff;
use App\Models\Workflow;
use App\Models\Budget;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class NonTravelMemoController extends Controller
{
    /**
     * Display a listing of non-travel memos.
     */
    public function index(Request $request): View
    {
        $query = NonTravelMemo::with(['staff', 'nonTravelMemoCategory'])
            ->latest();
            
        // Filter by staff if provided
        if ($request->has('staff_id') && $request->staff_id) {
            $query->where('staff_id', $request->staff_id);
        }
        
        // Filter by category if provided
        if ($request->has('category_id') && $request->category_id) {
            $query->where('non_travel_memo_category_id', $request->category_id);
        }
        
        $nonTravelMemos = $query->paginate(10);
        $categories = NonTravelMemoCategory::all();
        $staff = Staff::active()->get();
        
        return view('non-travel.index', compact('nonTravelMemos', 'categories', 'staff'));
    }

    /**
     * Show the form for creating a new non-travel memo.
     */
    public function create(): View
    {
        $categories = NonTravelMemoCategory::all();
        $staff = Staff::active()->get();
        $workflows = Workflow::all();
        $locations = Location::all();
        $budgets = Budget::all();
        
        return view('non-travel.create', compact('categories', 'staff', 'workflows', 'locations', 'budgets'));
    }

    /**
     * Store a newly created non-travel memo.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'forward_workflow_id' => 'required|exists:workflows,id',
            'reverse_workflow_id' => 'required|exists:workflows,id',
            'workplan_activity_code' => 'required|string|max:255',
            'staff_id' => 'required|exists:staff,id',
            'memo_date' => 'required|date',
            'location_id' => 'required|array',
            'non_travel_memo_category_id' => 'required|exists:non_travel_memo_categories,id',
            'budget_id' => 'required|array',
            'activity_title' => 'required|string|max:255',
            'background' => 'required|string',
            'activity_request_remarks' => 'required|string',
            'justification' => 'required|string',
            'budget_breakdown' => 'required|array',
            'attachment' => 'nullable|array',
            'attachment.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);
        
        // Handle file attachments
        $attachments = [];
        if ($request->hasFile('attachment')) {
            foreach ($request->file('attachment') as $file) {
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('non-travel-attachments', $filename, 'public');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ];
            }
        }
        
        $validated['attachment'] = $attachments;
        
        NonTravelMemo::create($validated);
        
        return redirect()
            ->route('non-travel.index')
            ->with('success', 'Non-travel memo created successfully.');
    }

    /**
     * Display the specified non-travel memo.
     */
    public function show(NonTravelMemo $nonTravel): View
    {
        $nonTravel->load(['staff', 'nonTravelMemoCategory']);
        
        return view('non-travel.show', compact('nonTravel'));
    }

    /**
     * Show the form for editing the specified non-travel memo.
     */
    public function edit(NonTravelMemo $nonTravel): View
    {
        $categories = NonTravelMemoCategory::all();
        $staff = Staff::active()->get();
        $workflows = Workflow::all();
        $locations = Location::all();
        $budgets = Budget::all();
        
        return view('non-travel.edit', compact('nonTravel', 'categories', 'staff', 'workflows', 'locations', 'budgets'));
    }

    /**
     * Update the specified non-travel memo.
     */
    public function update(Request $request, NonTravelMemo $nonTravel): RedirectResponse
    {
        $validated = $request->validate([
            'forward_workflow_id' => 'required|exists:workflows,id',
            'reverse_workflow_id' => 'required|exists:workflows,id',
            'workplan_activity_code' => 'required|string|max:255',
            'staff_id' => 'required|exists:staff,id',
            'memo_date' => 'required|date',
            'location_id' => 'required|array',
            'non_travel_memo_category_id' => 'required|exists:non_travel_memo_categories,id',
            'budget_id' => 'required|array',
            'activity_title' => 'required|string|max:255',
            'background' => 'required|string',
            'activity_request_remarks' => 'required|string',
            'justification' => 'required|string',
            'budget_breakdown' => 'required|array',
            'attachment' => 'nullable|array',
            'attachment.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);
        
        // Handle attachments update
        $existingAttachments = $nonTravel->attachment ?? [];
        $attachments = $existingAttachments;
        
        // Process new attachments
        if ($request->hasFile('attachment')) {
            foreach ($request->file('attachment') as $file) {
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('non-travel-attachments', $filename, 'public');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ];
            }
        }
        
        $validated['attachment'] = $attachments;
        
        $nonTravel->update($validated);
        
        return redirect()
            ->route('non-travel.index')
            ->with('success', 'Non-travel memo updated successfully.');
    }

    /**
     * Remove the specified non-travel memo.
     */
    public function destroy(NonTravelMemo $nonTravel): RedirectResponse
    {
        // Delete related attachments from storage
        if (!empty($nonTravel->attachment)) {
            foreach ($nonTravel->attachment as $attachment) {
                if (isset($attachment['path'])) {
                    Storage::disk('public')->delete($attachment['path']);
                }
            }
        }
        
        $nonTravel->delete();
        
        return redirect()
            ->route('non-travel.index')
            ->with('success', 'Non-travel memo deleted successfully.');
    }
    
    /**
     * Remove a specific attachment from a non-travel memo.
     */
    public function removeAttachment(Request $request, NonTravelMemo $nonTravel): RedirectResponse
    {
        $validated = $request->validate([
            'attachment_index' => 'required|integer',
        ]);
        
        $index = $validated['attachment_index'];
        $attachments = $nonTravel->attachment ?? [];
        
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
            $nonTravel->update(['attachment' => $attachments]);
            
            return redirect()
                ->back()
                ->with('success', 'Attachment removed successfully.');
        }
        
        return redirect()
            ->back()
            ->with('error', 'Attachment not found.');
    }
}
