<?php

namespace App\Http\Controllers;

use App\Models\RequestARF;
use App\Models\Staff;
use App\Models\Workflow;
use App\Models\Division;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class RequestARFController extends Controller
{
    /**
     * Display a listing of ARF requests.
     */
    public function index(Request $request): View
    {
        $currentStaffId = user_session('staff_id');
        
        // Get My ARFs (created by current user)
        $myArfsQuery = RequestARF::with(['staff', 'division'])
            ->where('staff_id', $currentStaffId)
            ->latest();
            
        // Apply filters to My ARFs
        if ($request->has('division_id') && $request->division_id) {
            $myArfsQuery->where('division_id', $request->division_id);
        }
        
        if ($request->has('status') && $request->status) {
            $myArfsQuery->where('status', $request->status);
        }
        
        $myArfs = $myArfsQuery->paginate(10);
        
        // Get All ARFs (only for users with permission 87)
        $allArfs = collect();
        if (in_array(87, user_session('permissions', []))) {
            $allArfsQuery = RequestARF::with(['staff', 'division'])
                ->latest();
                
            // Apply filters to All ARFs
            if ($request->has('division_id') && $request->division_id) {
                $allArfsQuery->where('division_id', $request->division_id);
            }
            
            if ($request->has('staff_id') && $request->staff_id) {
                $allArfsQuery->where('staff_id', $request->staff_id);
            }
            
            if ($request->has('status') && $request->status) {
                $allArfsQuery->where('status', $request->status);
            }
            
            $allArfs = $allArfsQuery->paginate(10);
        }
        
        $divisions = Division::all();
        $staff = Staff::active()->get();
        
        return view('request-arf.index', compact('myArfs', 'allArfs', 'divisions', 'staff'));
    }

    /**
     * Show the form for creating a new ARF request.
     */
    public function create(): View
    {
        $staff = Staff::active()->get();
        $divisions = Division::all();
        $workflows = Workflow::all();
        $locations = Location::all();
        
        // Generate a unique ARF number
        $arfNumber = RequestARF::generateARFNumber();
        
        return view('request-arf.create', compact('staff', 'divisions', 'workflows', 'locations', 'arfNumber'));
    }

    /**
     * Store a newly created ARF request.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'forward_workflow_id' => 'required|exists:workflows,id',
            'reverse_workflow_id' => 'required|exists:workflows,id',
            'arf_number' => 'required|string|unique:request_arfs,arf_number',
            'request_date' => 'required|date',
            'division_id' => 'required|exists:divisions,id',
            'location_id' => 'required|array',
            'activity_title' => 'required|string|max:255',
            'purpose' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'requested_amount' => 'required|numeric|min:0',
            'accounting_code' => 'required|string|max:255',
            'budget_breakdown' => 'required|array',
            'attachment' => 'nullable|array',
            'attachment.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'status' => 'sometimes|in:draft,submitted,approved,rejected',
        ]);
        
        // Handle file attachments
        $attachments = [];
        if ($request->hasFile('attachment')) {
            foreach ($request->file('attachment') as $file) {
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('arf-attachments', $filename, 'public');
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
        
        RequestARF::create($validated);
        
        return redirect()
            ->route('request-arf.index')
            ->with('success', 'ARF request created successfully.');
    }

    /**
     * Display the specified ARF request.
     */
    public function show(RequestARF $requestARF): View
    {
        $requestARF->load(['staff', 'division']);
        
        return view('request-arf.show', compact('requestARF'));
    }

    /**
     * Show the form for editing the specified ARF request.
     */
    public function edit(RequestARF $requestARF): View
    {
        $staff = Staff::active()->get();
        $divisions = Division::all();
        $workflows = Workflow::all();
        $locations = Location::all();
        
        return view('request-arf.edit', compact('requestARF', 'staff', 'divisions', 'workflows', 'locations'));
    }

    /**
     * Update the specified ARF request.
     */
    public function update(Request $request, RequestARF $requestARF): RedirectResponse
    {
        $validated = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'forward_workflow_id' => 'required|exists:workflows,id',
            'reverse_workflow_id' => 'required|exists:workflows,id',
            'arf_number' => 'required|string|unique:request_arfs,arf_number,' . $requestARF->id,
            'request_date' => 'required|date',
            'division_id' => 'required|exists:divisions,id',
            'location_id' => 'required|array',
            'activity_title' => 'required|string|max:255',
            'purpose' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'requested_amount' => 'required|numeric|min:0',
            'accounting_code' => 'required|string|max:255',
            'budget_breakdown' => 'required|array',
            'attachment' => 'nullable|array',
            'attachment.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'status' => 'sometimes|in:draft,submitted,approved,rejected',
        ]);
        
        // Handle attachments update
        $existingAttachments = $requestARF->attachment ?? [];
        $attachments = $existingAttachments;
        
        // Process new attachments
        if ($request->hasFile('attachment')) {
            foreach ($request->file('attachment') as $file) {
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('arf-attachments', $filename, 'public');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ];
            }
        }
        
        $validated['attachment'] = $attachments;
        
        $requestARF->update($validated);
        
        return redirect()
            ->route('request-arf.index')
            ->with('success', 'ARF request updated successfully.');
    }

    /**
     * Remove the specified ARF request.
     */
    public function destroy(RequestARF $requestARF): RedirectResponse
    {
        // Delete related attachments from storage
        if (!empty($requestARF->attachment)) {
            foreach ($requestARF->attachment as $attachment) {
                if (isset($attachment['path'])) {
                    Storage::disk('public')->delete($attachment['path']);
                }
            }
        }
        
        $requestARF->delete();
        
        return redirect()
            ->route('request-arf.index')
            ->with('success', 'ARF request deleted successfully.');
    }
    
    /**
     * Remove a specific attachment from an ARF request.
     */
    public function removeAttachment(Request $request, RequestARF $requestARF): RedirectResponse
    {
        $validated = $request->validate([
            'attachment_index' => 'required|integer',
        ]);
        
        $index = $validated['attachment_index'];
        $attachments = $requestARF->attachment ?? [];
        
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
            $requestARF->update(['attachment' => $attachments]);
            
            return redirect()
                ->back()
                ->with('success', 'Attachment removed successfully.');
        }
        
        return redirect()
            ->back()
            ->with('error', 'Attachment not found.');
    }
}
