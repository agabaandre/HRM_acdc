<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use App\Models\Staff;
use App\Models\Workflow;
use App\Models\Division;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class ServiceRequestController extends Controller
{
    /**
     * Display a listing of service requests.
     */
    public function index(Request $request): View
    {
        $query = ServiceRequest::with(['staff', 'division', 'activity'])
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
        
        // Filter by service type if provided
        if ($request->has('service_type') && $request->service_type) {
            $query->where('service_type', $request->service_type);
        }
        
        // Filter by status if provided
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }
        
        $serviceRequests = $query->paginate(10);
        $staff = Staff::active()->get();
        $divisions = Division::all();
        
        return view('service-requests.index', compact('serviceRequests', 'staff', 'divisions'));
    }

    /**
     * Show the form for creating a new service request.
     */
    public function create(): View
    {
        $staff = Staff::active()->get();
        $divisions = Division::all();
        $workflows = Workflow::all();
        $activities = Activity::all();
        
        // Generate a unique request number
        $requestNumber = ServiceRequest::generateRequestNumber();
        
        return view('service-requests.create', compact('staff', 'divisions', 'workflows', 'activities', 'requestNumber'));
    }

    /**
     * Store a newly created service request.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'request_number' => 'required|string|unique:service_requests,request_number',
            'request_date' => 'required|date',
            'staff_id' => 'required|exists:staff,id',
            'activity_id' => 'nullable|exists:activities,id',
            'workflow_id' => 'required|exists:workflows,id',
            'reverse_workflow_id' => 'required|exists:workflows,id',
            'division_id' => 'required|exists:divisions,id',
            'service_title' => 'required|string|max:255',
            'description' => 'required|string',
            'justification' => 'required|string',
            'required_by_date' => 'required|date|after_or_equal:request_date',
            'location' => 'nullable|string|max:255',
            'estimated_cost' => 'required|numeric|min:0',
            'priority' => 'required|in:low,medium,high,urgent',
            'service_type' => 'required|in:it,maintenance,procurement,travel,other',
            'specifications' => 'nullable|array',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'status' => 'sometimes|in:draft,submitted,in_progress,approved,rejected,completed',
            'remarks' => 'nullable|string',
        ]);
        
        // Handle file attachments
        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('service-request-attachments', $filename, 'public');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ];
            }
        }
        
        $validated['attachments'] = $attachments;
        
        // Process specifications array if provided
        if (!isset($validated['specifications']) || !is_array($validated['specifications'])) {
            $validated['specifications'] = [];
        }
        
        // Set default status if not provided
        if (!isset($validated['status'])) {
            $validated['status'] = 'draft';
        }
        
        ServiceRequest::create($validated);
        
        return redirect()
            ->route('service-requests.index')
            ->with('success', 'Service request created successfully.');
    }

    /**
     * Display the specified service request.
     */
    public function show(ServiceRequest $serviceRequest): View
    {
        $serviceRequest->load(['staff', 'division', 'activity', 'workflow', 'reverseWorkflow']);
        
        return view('service-requests.show', compact('serviceRequest'));
    }

    /**
     * Show the form for editing the specified service request.
     */
    public function edit(ServiceRequest $serviceRequest): View
    {
        $staff = Staff::active()->get();
        $divisions = Division::all();
        $workflows = Workflow::all();
        $activities = Activity::all();
        
        return view('service-requests.edit', compact('serviceRequest', 'staff', 'divisions', 'workflows', 'activities'));
    }

    /**
     * Update the specified service request.
     */
    public function update(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $validated = $request->validate([
            'request_number' => 'required|string|unique:service_requests,request_number,' . $serviceRequest->id,
            'request_date' => 'required|date',
            'staff_id' => 'required|exists:staff,id',
            'activity_id' => 'nullable|exists:activities,id',
            'workflow_id' => 'required|exists:workflows,id',
            'reverse_workflow_id' => 'required|exists:workflows,id',
            'division_id' => 'required|exists:divisions,id',
            'service_title' => 'required|string|max:255',
            'description' => 'required|string',
            'justification' => 'required|string',
            'required_by_date' => 'required|date|after_or_equal:request_date',
            'location' => 'nullable|string|max:255',
            'estimated_cost' => 'required|numeric|min:0',
            'priority' => 'required|in:low,medium,high,urgent',
            'service_type' => 'required|in:it,maintenance,procurement,travel,other',
            'specifications' => 'nullable|array',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
            'status' => 'sometimes|in:draft,submitted,in_progress,approved,rejected,completed',
            'remarks' => 'nullable|string',
        ]);
        
        // Handle attachments update
        $existingAttachments = $serviceRequest->attachments ?? [];
        $attachments = $existingAttachments;
        
        // Process new attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('service-request-attachments', $filename, 'public');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ];
            }
        }
        
        $validated['attachments'] = $attachments;
        
        // Process specifications array if provided
        if (!isset($validated['specifications']) || !is_array($validated['specifications'])) {
            $validated['specifications'] = [];
        }
        
        $serviceRequest->update($validated);
        
        return redirect()
            ->route('service-requests.index')
            ->with('success', 'Service request updated successfully.');
    }

    /**
     * Remove the specified service request.
     */
    public function destroy(ServiceRequest $serviceRequest): RedirectResponse
    {
        // Delete related attachments from storage
        if (!empty($serviceRequest->attachments)) {
            foreach ($serviceRequest->attachments as $attachment) {
                if (isset($attachment['path'])) {
                    Storage::disk('public')->delete($attachment['path']);
                }
            }
        }
        
        $serviceRequest->delete();
        
        return redirect()
            ->route('service-requests.index')
            ->with('success', 'Service request deleted successfully.');
    }
    
    /**
     * Remove a specific attachment from a service request.
     */
    public function removeAttachment(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $validated = $request->validate([
            'attachment_index' => 'required|integer',
        ]);
        
        $index = $validated['attachment_index'];
        $attachments = $serviceRequest->attachments ?? [];
        
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
            $serviceRequest->update(['attachments' => $attachments]);
            
            return redirect()
                ->back()
                ->with('success', 'Attachment removed successfully.');
        }
        
        return redirect()
            ->back()
            ->with('error', 'Attachment not found.');
    }
}
