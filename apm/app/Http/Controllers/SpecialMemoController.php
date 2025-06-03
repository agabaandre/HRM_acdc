<?php

namespace App\Http\Controllers;

use App\Models\SpecialMemo;
use App\Models\Staff;
use App\Models\Workflow;
use App\Models\Division;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class SpecialMemoController extends Controller
{
    /**
     * Display a listing of special memos.
     */
    public function index(Request $request): View
    {
        $query = SpecialMemo::with(['staff', 'division'])
            ->latest();
            
        // Filter by staff if provided
        if ($request->has('staff_id') && $request->staff_id) {
            $query->where('staff_id', $request->staff_id);
        }
        
        // Filter by division if provided
        if ($request->has('division_id') && $request->division_id) {
            $query->where('division_id', $request->division_id);
        }
        
        // Filter by priority if provided
        if ($request->has('priority') && $request->priority) {
            $query->where('priority', $request->priority);
        }
        
        // Filter by status if provided
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }
        
        $specialMemos = $query->paginate(10);
        $divisions = Division::all();
        $staff = Staff::active()->get();
        
        return view('special-memo.index', compact('specialMemos', 'divisions', 'staff'));
    }

    /**
     * Show the form for creating a new special memo.
     */
    public function create(): View
    {
        $staff = Staff::active()->get();
        $divisions = Division::all();
        $workflows = Workflow::all();
        
        // Generate a unique memo number
        $memoNumber = SpecialMemo::generateMemoNumber();
        
        return view('special-memo.create', compact('staff', 'divisions', 'workflows', 'memoNumber'));
    }

    /**
     * Store a newly created special memo.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'forward_workflow_id' => 'required|exists:workflows,id',
            'reverse_workflow_id' => 'required|exists:workflows,id',
            'memo_number' => 'required|string|unique:special_memos,memo_number',
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
        
        // Handle file attachments
        $attachments = [];
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
        
        // Set default status if not provided
        if (!isset($validated['status'])) {
            $validated['status'] = 'draft';
        }
        
        SpecialMemo::create($validated);
        
        return redirect()
            ->route('special-memo.index')
            ->with('success', 'Special memo created successfully.');
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
