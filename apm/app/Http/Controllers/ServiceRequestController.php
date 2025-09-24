<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use App\Models\Staff;
use App\Models\Workflow;
use App\Models\Division;
use App\Models\Activity;
use App\Models\NonTravelMemo;
use App\Models\SpecialMemo;
use App\Models\CostItem;
use App\Models\FundType;
use App\Models\WorkflowModel;
use App\Models\WorkflowDefinition;
use App\Models\Approver;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

class ServiceRequestController extends Controller
{
    protected ApprovalService $approvalService;

    public function __construct(?ApprovalService $approvalService = null)
    {
        $this->approvalService = $approvalService ?? app(ApprovalService::class);
    }

    /**
     * Display a listing of service requests.
     */
    public function index(Request $request): View
    {
        $currentStaffId = user_session('staff_id');
        
        // Base query for filtering
        $baseQuery = ServiceRequest::with(['staff', 'responsiblePerson', 'division', 'workflowDefinition'])
            ->latest();
            
        // Apply filters
        if ($request->has('staff_id') && $request->staff_id) {
            $baseQuery->where('staff_id', $request->staff_id);
        }
        
        if ($request->has('division_id') && $request->division_id) {
            $baseQuery->where('division_id', $request->division_id);
        }
        
        if ($request->has('service_type') && $request->service_type) {
            $baseQuery->where('service_type', $request->service_type);
        }
        
        if ($request->has('status') && $request->status) {
            $baseQuery->where('overall_status', $request->status);
        }
        
        // My Submitted Requests (current user's requests)
        $mySubmittedQuery = clone $baseQuery;
        $mySubmittedRequests = $mySubmittedQuery->where('staff_id', $currentStaffId)->get();
        
        // All Requests (for users with permission)
        $allRequests = null;
        if (in_array(87, user_session('permissions', []))) {
            $allRequests = $baseQuery->get();
        }
        
        $staff = Staff::all();
        $divisions = Division::all();
        
        return view('service-requests.index', compact('mySubmittedRequests', 'allRequests', 'staff', 'divisions'));
    }

    /**
     * Show the form for creating a new service request.
     */
    public function create(Request $request): View
    {
        try {
        $staff = Staff::all();
            
            // Ensure we have at least one staff member for the dropdown
            if ($staff->isEmpty()) {
                $staff = Staff::take(10)->get(); // Fallback to any 10 staff members
            }
        $divisions = Division::all();
        $workflows = Workflow::all();
        $activities = Activity::all();
        
            // Handle source data if provided
            $sourceData = null;
            $sourceType = $request->get('source_type');
            $sourceId = $request->get('source_id');
            $budgetBreakdown = null;
            $originalTotalBudget = 0;
            $internalParticipants = [];
            
            if ($sourceType && $sourceId) {
                $sourceData = $this->getSourceDataForForm($sourceType, $sourceId);
                
                // Process budget breakdown from source data
                if ($sourceData) {
                    $budgetBreakdown = $this->processBudgetDataFromSource($sourceData, $sourceType);
                    $originalTotalBudget = $budgetBreakdown['grand_total'] ?? 0;
                    
                    // Process internal participants from source data
                    $internalParticipants = $this->processInternalParticipantsFromSource($sourceData, $sourceType);
                }
            }
            
            // Get cost items that exist in budget breakdown and filter by type
            $costItems = collect();
            $otherCostItems = collect();
            
            if ($budgetBreakdown && is_array($budgetBreakdown)) {
                // Extract cost item names from budget breakdown
                $costItemNames = [];
                foreach ($budgetBreakdown as $fundCode => $items) {
                    if (is_array($items)) {
                        foreach ($items as $item) {
                            if (isset($item['cost']) && !in_array($item['cost'], $costItemNames)) {
                                $costItemNames[] = $item['cost'];
                            }
                        }
                    }
                }
                
                if (!empty($costItemNames)) {
                    // Get Individual Cost items that exist in budget breakdown
                    $individualCosts = CostItem::whereIn('name', $costItemNames)
                                              ->where('cost_type', 'Individual Cost')
                                              ->get();
                    
                    // Get Other Cost items that exist in budget breakdown
                    $otherCosts = CostItem::whereIn('name', $costItemNames)
                                         ->where('cost_type', 'Other Cost')
                                         ->get();
                    
                    $costItems = $individualCosts;
                    $otherCostItems = $otherCosts;
                }
            }
            
            // Fallback to all Individual Cost items if no budget breakdown
            if ($costItems->isEmpty()) {
                $costItems = CostItem::where('cost_type', 'Individual Cost')->get();
            }
            
            // Generate a unique request number with actual activity parameters (like ARF)
            $requestNumber = $this->generateServiceRequestNumber($sourceData, $sourceType);
            
            // Get participant names from internal participants JSON
            $participantNames = [];
            if (!empty($internalParticipants) && is_array($internalParticipants)) {
                // Check if participants are already processed (have staff objects) or raw JSON
                if (isset($internalParticipants[0]) && isset($internalParticipants[0]['staff'])) {
                    // Participants are already processed - use them directly (same as special memo)
                    foreach ($internalParticipants as $index => $participant) {
                        if (isset($participant['staff']) && $participant['staff']) {
                            $staffMember = $participant['staff'];
                            $divisionName = $staffMember->division_name ?? 'No Division';
                            
                            $participantNames[] = [
                                'id' => $staffMember->staff_id,
                                'text' => $staffMember->fname . ' ' . $staffMember->lname . ' (' . ($staffMember->job_name ?? 'Staff') . ') - ' . $divisionName,
                                'name' => $staffMember->fname . ' ' . $staffMember->lname,
                                'position' => $staffMember->job_name ?? 'Staff',
                                'division' => $divisionName
                            ];
                        }
                    }
                } else {
                    // Participants are raw JSON - need to process them (original logic)
                    // Get staff IDs from the keys of the internal participants array
                    $staffIds = array_map('intval', array_keys($internalParticipants));
                    
                    // Fetch staff details using the converted integer IDs
                    $staffDetails = Staff::with('division')->whereIn('staff_id', $staffIds)->get()->keyBy('staff_id');
                    
                    foreach ($internalParticipants as $participantId => $participantData) {
                        // Convert string key to integer for database lookup
                        $staffIdInt = (int) $participantId;
                        
                        // Check if staff exists in our fetched data
                        if (isset($staffDetails[$staffIdInt])) {
                            $staffMember = $staffDetails[$staffIdInt];
                            $divisionName = $staffMember->division ? ($staffMember->division->name ?? $staffMember->division->division_name ?? 'No Division') : 'No Division';
                            
                            $participantNames[] = [
                                'id' => $staffMember->staff_id,
                                'text' => $staffMember->fname . ' ' . $staffMember->lname . ' (' . ($staffMember->position ?? 'Staff') . ') - ' . $divisionName,
                                'name' => $staffMember->fname . ' ' . $staffMember->lname,
                                'position' => $staffMember->position ?? 'Staff',
                                'division' => $divisionName
                            ];
                        }
                    }
                }
            }
            
            return view('service-requests.create', compact('staff', 'divisions', 'workflows', 'activities', 'costItems', 'otherCostItems', 'requestNumber', 'sourceData', 'sourceType', 'sourceId', 'budgetBreakdown', 'originalTotalBudget', 'internalParticipants', 'participantNames'));
        } catch (\Exception $e) {
            // Return a fallback view with minimal data
            return view('service-requests.create', [
                'staff' => collect(),
                'divisions' => collect(),
                'workflows' => collect(),
                'activities' => collect(),
                'costItems' => collect(),
                'otherCostItems' => collect(),
                'requestNumber' => 'AU/CDC/SRV-' . date('Ymd') . '-001',
                'sourceData' => null,
                'sourceType' => null,
                'sourceId' => null,
                'budgetBreakdown' => null,
                'originalTotalBudget' => 0,
                'internalParticipants' => [],
                'participantNames' => []
            ]);
        }
    }

    /**
     * Store a newly created service request.
     */
    public function store(Request $request): RedirectResponse
    {
        
        $validated = $request->validate([
            'request_date' => 'required|date',
            'service_title' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'status' => 'sometimes|in:draft,submitted,in_progress,approved,rejected,completed',
            'remarks' => 'nullable|string',
            'activity_id' => 'nullable|integer',
            // New budget fields
            'source_type' => 'required|string',
            'source_id' => 'required|integer',
            'model_type' => 'required|string',
            'fund_type_id' => 'required|integer',
            'responsible_person_id' => 'required|exists:staff,staff_id',
            'budget_id' => 'nullable|string',
            'original_total_budget' => 'required|numeric|min:0',
            'new_total_budget' => 'required|numeric|min:0',
            'budget_breakdown' => 'nullable|string',
            'internal_participants_cost' => 'nullable|string',
            'internal_participants_comment' => 'nullable|string',
            'external_participants_cost' => 'nullable|string',
            'external_participants_comment' => 'nullable|string',
            'other_costs' => 'nullable|array',
            'other_costs_comment' => 'nullable|string',
            'internal_participants' => 'nullable|array',
            'external_participants' => 'nullable|array',
        ]);
        $validated['staff_id'] = user_session('staff_id');
        $validated['division_id'] = user_session('division_id');
        $validated['request_number'] = ServiceRequest::generateRequestNumber();
        
        // Process budget data
        $budgetData = $this->processBudgetData($request);

        // Get assigned workflow ID for ServiceRequest model
        $assignedWorkflowId = WorkflowModel::getWorkflowIdForModel('ServiceRequest');
        if (!$assignedWorkflowId) {
            $assignedWorkflowId = 3; // Default workflow ID for ServiceRequest
        }
        
        // Set approval levels and workflow IDs for immediate submission
        $approvalLevel = 1; // Start at level 1 for pending
        $nextApprovalLevel = 2; // Next level to be approved
        $overallStatus = 'pending'; // Set to pending immediately
        $forwardWorkflowId = $assignedWorkflowId; // Set the assigned workflow ID
        $reverseWorkflowId = $assignedWorkflowId; // Set the same for reverse workflow
        
        
        // Process specifications array if provided
        if (!isset($validated['specifications']) || !is_array($validated['specifications'])) {
            $validated['specifications'] = [];
        }
        
        // Merge budget data with validated data
        $validated = array_merge($validated, $budgetData);
        
        // Add approval and workflow fields
        $validated['approval_level'] = $approvalLevel;
        $validated['next_approval_level'] = $nextApprovalLevel;
        $validated['overall_status'] = $overallStatus;
        $validated['forward_workflow_id'] = $forwardWorkflowId;
        $validated['reverse_workflow_id'] = $reverseWorkflowId;
        
        $serviceRequest = ServiceRequest::create($validated);
        
        // Save approval trail for ServiceRequest creation and submission
        $serviceRequest->saveApprovalTrail('Service request created and submitted for approval', 'submitted');
        
        // Send email notification for approval request (if function exists)
        if (function_exists('send_service_request_email_notification')) {
            send_service_request_email_notification($serviceRequest, 'approval');
        }
        
        return redirect()
            ->route('service-requests.index')
            ->with('success', 'Service request created and submitted for approval successfully! Status: Pending');
    }

    /**
     * Process budget data from the form
     */
    private function processBudgetData(Request $request): array
    {
        $budgetData = [];
        
        // Get cost items mapping (ID to name)
        $costItems = CostItem::where('cost_type', 'Individual Cost')->get()->keyBy('id');
        $costItemMapping = $costItems->mapWithKeys(function ($item) {
            return [$item->id => $item->name];
        })->toArray();
        
        // Process internal participants
        $internalParticipants = $request->input('internal_participants', []);
        $internalCosts = [];
        $internalTotal = 0;
        
        // Ensure internalParticipants is an array
        if (!is_array($internalParticipants)) {
            $internalParticipants = [];
        }
        
        foreach ($internalParticipants as $participant) {
            if (!empty($participant['staff_id'])) {
                $staffId = $participant['staff_id'];
                $costs = $participant['costs'] ?? [];
                $costType = $participant['cost_type'] ?? 'Daily Rate';
                $description = $participant['description'] ?? '';
                
                // Convert cost IDs to cost names and calculate total
                $costsWithNames = [];
                $total = 0;
                foreach ($costs as $costId => $costValue) {
                    $costName = $costItemMapping[$costId] ?? "Unknown Cost (ID: $costId)";
                    $costsWithNames[$costName] = floatval($costValue);
                    $total += floatval($costValue);
                }
                
                $internalCosts[] = [
                    'staff_id' => $staffId,
                    'cost_type' => $costType,
                    'costs' => $costsWithNames,
                    'description' => $description,
                    'total' => $total
                ];
                
                $internalTotal += $total;
            }
        }
        
        // Process external participants
        $externalParticipants = $request->input('external_participants', []);
        $externalCosts = [];
        $externalTotal = 0;
        
        // Ensure externalParticipants is an array
        if (!is_array($externalParticipants)) {
            $externalParticipants = [];
        }
        
        foreach ($externalParticipants as $participant) {
            if (!empty($participant['name'])) {
                $name = $participant['name'];
                $email = $participant['email'] ?? '';
                $costs = $participant['costs'] ?? [];
                $costType = $participant['cost_type'] ?? 'Daily Rate';
                $description = $participant['description'] ?? '';
                
                // Convert cost IDs to cost names and calculate total
                $costsWithNames = [];
                $total = 0;
                foreach ($costs as $costId => $costValue) {
                    $costName = $costItemMapping[$costId] ?? "Unknown Cost (ID: $costId)";
                    $costsWithNames[$costName] = floatval($costValue);
                    $total += floatval($costValue);
                }
                
                $externalCosts[] = [
                    'name' => $name,
                    'email' => $email,
                    'cost_type' => $costType,
                    'costs' => $costsWithNames,
                    'description' => $description,
                    'total' => $total
                ];
                
                $externalTotal += $total;
            }
        }
        
        // Process other costs
        $otherCosts = $request->input('other_costs', []);
        $otherCostsData = [];
        $otherTotal = 0;
        
        // Ensure otherCosts is an array
        if (!is_array($otherCosts)) {
            $otherCosts = [];
        }
        
        foreach ($otherCosts as $cost) {
            if (!empty($cost['cost_type'])) {
                $unitCost = floatval($cost['unit_cost'] ?? 0);
                $days = intval($cost['days'] ?? 0);
                $total = $unitCost * $days;
                
                $otherCostsData[] = [
                    'cost_type' => $cost['cost_type'],
                    'unit_cost' => $unitCost,
                    'days' => $days,
                    'description' => $cost['description'] ?? '',
                    'total' => $total
                ];
                
                $otherTotal += $total;
            }
        }
        
        // Calculate totals
        $newTotalBudget = $internalTotal + $externalTotal + $otherTotal;
        $originalTotalBudget = floatval($request->input('original_total_budget', 0));
        
        // Create budget breakdown structure
        $budgetBreakdown = [
            'internal_participants' => $internalCosts,
            'external_participants' => $externalCosts,
            'other_costs' => $otherCostsData,
            'internal_total' => $internalTotal,
            'external_total' => $externalTotal,
            'other_total' => $otherTotal,
            'new_total' => $newTotalBudget,
            'original_total' => $originalTotalBudget,
            'difference' => $newTotalBudget - $originalTotalBudget
        ];
        
        return [
            'internal_participants_cost' => json_encode($internalCosts),
            'external_participants_cost' => json_encode($externalCosts),
            'other_costs' => json_encode($otherCostsData),
            'original_total_budget' => $originalTotalBudget,
            'new_total_budget' => $newTotalBudget,
            'budget_breakdown' => json_encode($budgetBreakdown),
            'title' => $request->input('service_title'),
            'source_type' => $request->input('source_type'),
            'source_id' => $request->input('source_id'),
            'model_type' => $request->input('model_type'),
            'fund_type_id' => $request->input('fund_type_id'),
            'responsible_person_id' => $request->input('responsible_person_id'),
            'budget_id' => $request->input('budget_id'),
        ];
    }

    /**
     * Process budget data from source (activity, memo, etc.)
     */
    private function processBudgetDataFromSource($sourceData, string $sourceType): array
    {
        try {
            switch ($sourceType) {
                case 'activity':
                    if ($sourceData && isset($sourceData->budget_breakdown)) {
                        $budget = is_string($sourceData->budget_breakdown) 
                            ? json_decode($sourceData->budget_breakdown, true) 
                            : $sourceData->budget_breakdown;
                        
                        if (is_array($budget)) {
                            return $budget;
                        }
                    }
                    break;
                    
                case 'non_travel_memo':
                    if ($sourceData && isset($sourceData->budget_breakdown)) {
                        $budget = is_string($sourceData->budget_breakdown) 
                            ? json_decode($sourceData->budget_breakdown, true) 
                            : $sourceData->budget_breakdown;
                        
                        if (is_array($budget)) {
                            return $budget;
                        }
                    }
                    break;
                    
                case 'special_memo':
                    if ($sourceData && isset($sourceData->budget_breakdown)) {
                        $budget = is_string($sourceData->budget_breakdown) 
                            ? json_decode($sourceData->budget_breakdown, true) 
                            : $sourceData->budget_breakdown;
                        
                        if (is_array($budget)) {
                            return $budget;
                        }
                    }
                    break;
            }
            
            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Process internal participants from source (activity, memo, etc.)
     */
    private function processInternalParticipantsFromSource($sourceData, string $sourceType): array
    {
        try {
            switch ($sourceType) {
                case 'activity':
                    if ($sourceData && isset($sourceData->internal_participants)) {
                        $participants = is_string($sourceData->internal_participants) 
                            ? json_decode($sourceData->internal_participants, true) 
                            : $sourceData->internal_participants;
                        
                        if (is_array($participants)) {
                            return $participants;
                        }
                    }
                    break;
                    
                case 'non_travel_memo':
                case 'special_memo':
                    if ($sourceData && isset($sourceData->internal_participants)) {
                        $participants = is_string($sourceData->internal_participants) 
                            ? json_decode($sourceData->internal_participants, true) 
                            : $sourceData->internal_participants;
                        
                        if (is_array($participants) && !empty($participants)) {
                            // Process participants the same way as special memo show
                            $staffIds = array_map('intval', array_keys($participants));
                            
                            return $participants; // Return the raw participants data
                        }
                    }
                    break;
            }
            
            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Display the specified service request.
     */
    public function show(ServiceRequest $serviceRequest): View
    {
        $serviceRequest->load(['staff', 'division', 'activity', 'forwardWorkflow', 'reverseWorkflow', 'serviceRequestApprovalTrails.staff', 'serviceRequestApprovalTrails.approverRole']);
        
        // Load source data if available
        $sourceData = null;
        if ($serviceRequest->source_type && $serviceRequest->source_id) {
            $sourceData = $this->getSourceDataForForm($serviceRequest->source_type, $serviceRequest->source_id);
        }
        
        // Get approval levels for progress bar
        $approvalLevels = $this->getApprovalLevels($serviceRequest);
        
        return view('service-requests.show', compact('serviceRequest', 'sourceData', 'approvalLevels'));
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

    /**
     * Get source data for service request modal
     */
    public function getSourceData(Request $request): JsonResponse
    {
        $sourceType = $request->input('sourceType');
        $sourceId = $request->input('sourceId');

        try {
            $sourceData = null;
            $internalParticipants = [];
            $budgetBreakdown = [];
            $originalTotalBudget = 0;

            switch ($sourceType) {
                case 'activity':
                    $activity = Activity::with(['matrix.division', 'internal_participants_details.staff.division'])
                        ->find($sourceId);
                    
                    if ($activity) {
                        $sourceData = [
                            'id' => $activity->id,
                            'title' => $activity->activity_title,
                            'description' => $activity->background,
                            'start_date' => $activity->date_from,
                            'end_date' => $activity->date_to,
                            'location' => $activity->location,
                            'division_id' => $activity->matrix ? $activity->matrix->division_id : null,
                            'division_name' => $activity->matrix ? $activity->matrix->division->name : null,
                            'fund_type_id' => $activity->fund_type_id,
                            'overall_status' => $activity->matrix ? $activity->matrix->overall_status : 'draft',
                        ];

                        // Get internal participants
                        if ($activity->internal_participants) {
                            $participants = is_string($activity->internal_participants) 
                                ? json_decode($activity->internal_participants, true) 
                                : $activity->internal_participants;
                            
                            if (is_array($participants)) {
                                foreach ($participants as $participantId) {
                                    $staff = Staff::with('division')->find($participantId);
                                    if ($staff) {
                                        $internalParticipants[] = [
                                            'id' => $staff->staff_id,
                                            'name' => $staff->fname . ' ' . $staff->lname,
                                            'division' => $staff->division->name ?? 'N/A',
                                            'duty_station' => $staff->duty_station_name ?? 'N/A',
                                        ];
                                    }
                                }
                            }
                        }

                        // Get budget breakdown
                        if ($activity->budget_breakdown) {
                            $budget = is_string($activity->budget_breakdown) 
                                ? json_decode($activity->budget_breakdown, true) 
                                : $activity->budget_breakdown;
                            
                            if (is_array($budget)) {
                                $budgetBreakdown = $budget;
                                $originalTotalBudget = $budget['grand_total'] ?? 0;
                            }
                        }
                    }
                    break;

                case 'non_travel_memo':
                    $memo = NonTravelMemo::with(['division', 'fundType'])
                        ->find($sourceId);
                    
                    if ($memo) {
                        $sourceData = [
                            'id' => $memo->id,
                            'title' => $memo->activity_title,
                            'description' => $memo->background,
                            'start_date' => $memo->date_from,
                            'end_date' => $memo->date_to,
                            'location' => $memo->location,
                            'division_id' => $memo->division_id,
                            'division_name' => $memo->division->name ?? 'N/A',
                            'fund_type_id' => $memo->fund_type_id,
                            'overall_status' => 'approved', // Non-travel memos are already approved when created
                        ];

                        // Get budget breakdown
                        if ($memo->budget_breakdown) {
                            $budget = is_string($memo->budget_breakdown) 
                                ? json_decode($memo->budget_breakdown, true) 
                                : $memo->budget_breakdown;
                            
                            if (is_array($budget)) {
                                $budgetBreakdown = $budget;
                                $originalTotalBudget = $budget['grand_total'] ?? 0;
                            }
                        }
                    }
                    break;

                case 'special_memo':
                    $memo = SpecialMemo::with(['division', 'fundType'])
                        ->find($sourceId);
                    
                    if ($memo) {
                        $sourceData = [
                            'id' => $memo->id,
                            'title' => $memo->activity_title,
                            'description' => $memo->background,
                            'start_date' => $memo->date_from,
                            'end_date' => $memo->date_to,
                            'location' => $memo->location,
                            'division_id' => $memo->division_id,
                            'division_name' => $memo->division->name ?? 'N/A',
                            'fund_type_id' => $memo->fund_type_id,
                            'overall_status' => 'approved', // Special memos are already approved when created
                        ];

                        // Get internal participants
                        if ($memo->internal_participants) {
                            $participants = is_string($memo->internal_participants) 
                                ? json_decode($memo->internal_participants, true) 
                                : $memo->internal_participants;
                            
                            if (is_array($participants)) {
                                foreach ($participants as $participantId) {
                                    $staff = Staff::with('division')->find($participantId);
                                    if ($staff) {
                                        $internalParticipants[] = [
                                            'id' => $staff->staff_id,
                                            'name' => $staff->fname . ' ' . $staff->lname,
                                            'division' => $staff->division->name ?? 'N/A',
                                            'duty_station' => $staff->duty_station_name ?? 'N/A',
                                        ];
                                    }
                                }
                            }
                        }

                        // Get budget breakdown
                        if ($memo->budget) {
                            $budget = is_string($memo->budget) 
                                ? json_decode($memo->budget, true) 
                                : $memo->budget;
                            
                            if (is_array($budget)) {
                                $budgetBreakdown = $budget;
                                $originalTotalBudget = $budget['grand_total'] ?? 0;
                            }
                        }
                    }
                    break;
            }

            return response()->json([
                'success' => true,
                'sourceData' => $sourceData,
                'internalParticipants' => $internalParticipants,
                'budgetBreakdown' => $budgetBreakdown,
                'originalTotalBudget' => $originalTotalBudget,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading source data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display pending service requests for approvers
     */
    public function pendingApprovals(Request $request): View
    {
        $userStaffId = user_session('staff_id');

        // Check if we have valid session data
        if (!$userStaffId) {
            return view('service-requests.pending-approvals', [
                'pendingRequests' => collect(),
                'approvedByMe' => collect(),
                'divisions' => collect(),
                'error' => 'No session data found. Please log in again.'
            ]);
        }

        // Use the exact same logic as the home helper for consistency
        $userDivisionId = user_session('division_id');
        
        $pendingQuery = ServiceRequest::with([
            'staff',
            'responsiblePerson',
            'division',
            'forwardWorkflow.workflowDefinitions.approvers.staff',
            'forwardWorkflow.workflowDefinitions'
        ])
        ->where('overall_status', 'pending')
        ->where('forward_workflow_id', '!=', null)
        ->where('approval_level', '>', 0);

        $pendingQuery->where(function($q) use ($userDivisionId, $userStaffId) {
            // Check if user can approve at current level
            $q->whereHas('forwardWorkflow.workflowDefinitions', function($workflowQuery) use ($userDivisionId, $userStaffId) {
                $workflowQuery->whereColumn('approval_order', 'service_requests.approval_level')
                ->where(function($approverQuery) use ($userDivisionId, $userStaffId) {
                    // Division-specific approvers
                    $approverQuery->where(function($divQuery) use ($userDivisionId, $userStaffId) {
                        $divQuery->where('is_division_specific', 1)
                            ->whereHas('approvers', function($approverSubQuery) use ($userStaffId) {
                                $approverSubQuery->where('staff_id', $userStaffId);
                            });
                    })
                    // General approvers
                    ->orWhere(function($genQuery) use ($userStaffId) {
                        $genQuery->where('is_division_specific', 0)
                            ->whereHas('approvers', function($approverSubQuery) use ($userStaffId) {
                                $approverSubQuery->where('staff_id', $userStaffId);
                            });
                    });
                });
            });
        });

        // Apply filters
        if ($request->filled('division')) {
            $pendingQuery->whereHas('division', function($q) use ($request) {
                $q->where('division_name', 'like', '%' . $request->division . '%');
            });
        }

        if ($request->filled('staff')) {
            $pendingQuery->whereHas('responsiblePerson', function($q) use ($request) {
                $q->where(function($query) use ($request) {
                    $query->where('fname', 'like', '%' . $request->staff . '%')
                          ->orWhere('lname', 'like', '%' . $request->staff . '%')
                          ->orWhereRaw("CONCAT(fname, ' ', lname) LIKE ?", ['%' . $request->staff . '%']);
                });
            });
        }

        if ($request->filled('document')) {
            $pendingQuery->where('document_number', 'like', '%' . $request->document . '%');
        }

        if ($request->filled('title')) {
            $pendingQuery->where('title', 'like', '%' . $request->title . '%');
        }

        $pendingRequests = $pendingQuery->paginate(20);

        // Get approved by me
        $approvedByMeQuery = ServiceRequest::with(['staff', 'responsiblePerson', 'division'])
            ->whereHas('serviceRequestApprovalTrails', function($query) use ($userStaffId) {
                $query->where('staff_id', $userStaffId)
                    ->where('action', 'approved');
            })
            ->where('overall_status', 'approved');

        $approvedByMe = $approvedByMeQuery->paginate(20);

        // Get divisions for filter
        $divisions = Division::orderBy('division_name')->get();

        // Helper function to get workflow info
        $getWorkflowInfo = function($request) {
            $approvalLevel = $request->approval_level ?? 0;
            $workflowRole = 'N/A';
            $actorName = 'N/A';

            if ($request->forward_workflow_id && $approvalLevel > 0) {
                $currentDefinition = WorkflowDefinition::where('workflow_id', $request->forward_workflow_id)
                    ->where('approval_order', $approvalLevel)
                    ->first();
                    
                if ($currentDefinition) {
                    $workflowRole = $currentDefinition->role ?? 'N/A';
                    
                    // Get actor name
                    if ($currentDefinition->is_division_specific && $request->division) {
                        $staffId = $request->division->{$currentDefinition->division_reference_column} ?? null;
                        if ($staffId) {
                            $actor = Staff::where('staff_id', $staffId)->first();
                            if ($actor) {
                                $actorName = $actor->fname . ' ' . $actor->lname;
                            }
                        }
                    } else {
                        $approver = Approver::where('workflow_dfn_id', $currentDefinition->id)->first();
                        if ($approver) {
                            $actor = Staff::where('staff_id', $approver->staff_id)->first();
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

        return view('service-requests.pending-approvals', compact(
            'pendingRequests',
            'approvedByMe',
            'divisions',
            'getWorkflowInfo'
        ));
    }

    /**
     * Store service request from modal
     */
    public function storeFromModal(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'sourceType' => 'required|string|in:activity,non_travel_memo,special_memo',
                'sourceId' => 'required|integer',
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'justification' => 'required|string',
                'required_by_date' => 'required|date',
                'location' => 'nullable|string|max:255',
                'priority' => 'required|in:low,medium,high,urgent',
                'service_type' => 'required|in:it,maintenance,procurement,travel,other',
                'internal_participants_cost' => 'nullable|array',
                'internal_participants_comment' => 'nullable|string',
                'external_participants_cost' => 'nullable|array',
                'external_participants_comment' => 'nullable|string',
                'other_costs' => 'nullable|array',
                'other_costs_comment' => 'nullable|string',
                'original_total_budget' => 'required|numeric|min:0',
                'new_total_budget' => 'required|numeric|min:0',
            ]);

            // Get source data
            $sourceData = $this->getSourceData($request);
            $sourceDataJson = $sourceData->getData();

            if (!$sourceDataJson->success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error loading source data'
                ], 400);
            }

            $source = $sourceDataJson->sourceData;

            // Generate request number
            $requestNumber = ServiceRequest::generateRequestNumber();

            // Get assigned workflow ID for ServiceRequest model
            $assignedWorkflowId = WorkflowModel::getWorkflowIdForModel('ServiceRequest');
            if (!$assignedWorkflowId) {
                $assignedWorkflowId = 3; // Default workflow ID for ServiceRequest
            }

            // Create service request
            $serviceRequest = ServiceRequest::create([
                'request_number' => $requestNumber,
                'request_date' => now()->toDateString(),
                'staff_id' => user_session('staff_id'),
                'forward_workflow_id' => $assignedWorkflowId,
                'reverse_workflow_id' => $assignedWorkflowId,
                'division_id' => $source->division_id,
                'service_title' => $validated['title'],
                'description' => $validated['description'],
                'justification' => $validated['justification'],
                'required_by_date' => $validated['required_by_date'],
                'location' => $validated['location'],
                'estimated_cost' => $validated['new_total_budget'],
                'priority' => $validated['priority'],
                'service_type' => $validated['service_type'],
                'status' => 'submitted',
                'remarks' => '',
                // New budget and approval columns
                'budget_breakdown' => $sourceDataJson->budgetBreakdown,
                'internal_participants_cost' => $validated['internal_participants_cost'] ?? [],
                'internal_participants_comment' => $validated['internal_participants_comment'] ?? '',
                'external_participants_cost' => $validated['external_participants_cost'] ?? [],
                'external_participants_comment' => $validated['external_participants_comment'] ?? '',
                'other_costs' => $validated['other_costs'] ?? [],
                'other_costs_comment' => $validated['other_costs_comment'] ?? '',
                'original_total_budget' => $validated['original_total_budget'],
                'new_total_budget' => $validated['new_total_budget'],
                'fund_type_id' => $source->fund_type_id,
                'title' => $validated['title'],
                'responsible_person_id' => user_session('staff_id'),
                'budget_id' => [],
                'model_type' => $this->getModelType($validated['sourceType']),
                'source_id' => $validated['sourceId'],
                'source_type' => $validated['sourceType'],
                'approval_level' => 1,
                'next_approval_level' => 2,
            ]);

            // Save approval trail
            $this->approvalService->saveApprovalTrail(
                $serviceRequest,
                user_session('staff_id'),
                'submitted',
                'Service request submitted for approval'
            );

            return response()->json([
                'success' => true,
                'message' => 'Service request created successfully',
                'service_request_id' => $serviceRequest->id,
                'redirect_url' => route('service-requests.show', $serviceRequest)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating service request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cost items for other costs section
     */
    public function getCostItems(): JsonResponse
    {
        try {
            $costItems = CostItem::where('cost_type', 'other')->get();
            
            return response()->json([
                'success' => true,
                'costItems' => $costItems
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading cost items'
            ], 500);
        }
    }

    /**
     * Generate service request number with actual activity parameters (like ARF)
     */
    private function generateServiceRequestNumber($sourceData, $modelType = null)
    {
        $divisionCode = 'DHIS';
        $quarter = 'Q1';
        $year = date('Y');
        $activityId = $sourceData->id ?? 1;
        
        // For activities, get division code and quarter from matrix
        if ($modelType === 'activity' && $sourceData) {
            if (method_exists($sourceData, 'matrix') && $sourceData->matrix) {
                $matrix = $sourceData->matrix;
                
                // Get division code
                if ($matrix->division) {
                    $divisionCode = ServiceRequest::generateShortCodeFromDivision($matrix->division->division_name);
                }
                
                // Get quarter
                $quarter = $matrix->quarter ?? 'Q1';
                
                // Get year from activity start date or matrix year
                if ($sourceData->date_from) {
                    $year = $sourceData->date_from->format('Y');
                } elseif ($matrix->year) {
                    $year = $matrix->year;
                }
            }
        }
        
        // For memos, get division code
        if (in_array($modelType, ['non_travel_memo', 'special_memo']) && $sourceData) {
            if (method_exists($sourceData, 'division') && $sourceData->division) {
                $divisionCode = ServiceRequest::generateShortCodeFromDivision($sourceData->division->division_name);
            }
            
            // Get year from start date if available
            if (method_exists($sourceData, 'date_from') && $sourceData->date_from) {
                $year = $sourceData->date_from->format('Y');
            }
        }
        
        return ServiceRequest::generateRequestNumber($divisionCode, $quarter, $year, $activityId);
    }

    /**
     * Get source data for pre-populating the form
     */
    private function getSourceDataForForm(string $sourceType, int $sourceId)
    {
        try {
            switch ($sourceType) {
                case 'activity':
                    $source = Activity::find($sourceId);
                    break;
                case 'non_travel_memo':
                    $source = NonTravelMemo::find($sourceId);
                    break;
                case 'special_memo':
                    $source = SpecialMemo::find($sourceId);
                    break;
                default:
                    return null;
            }
            
            return $source;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get model type for source
     */
    private function getModelType(string $sourceType): string
    {
        return match($sourceType) {
            'activity' => 'App\\Models\\Activity',
            'non_travel_memo' => 'App\\Models\\NonTravelMemo',
            'special_memo' => 'App\\Models\\SpecialMemo',
            default => 'App\\Models\\Activity'
        };
    }

    /**
     * Export my submitted service requests to Excel
     */
    public function exportMySubmitted(Request $request)
    {
        $currentStaffId = user_session('staff_id');
        
        $query = ServiceRequest::with(['staff', 'division', 'workflowDefinition', 'currentActor'])
            ->where('staff_id', $currentStaffId)
            ->latest();
            
        // Apply filters
        if ($request->has('staff_id') && $request->staff_id) {
            $query->where('staff_id', $request->staff_id);
        }
        
        if ($request->has('division_id') && $request->division_id) {
            $query->where('division_id', $request->division_id);
        }
        
        if ($request->has('service_type') && $request->service_type) {
            $query->where('service_type', $request->service_type);
        }
        
        if ($request->has('status') && $request->status) {
            $query->where('overall_status', $request->status);
        }
        
        $serviceRequests = $query->get();
        
        // For now, return a simple CSV export
        $filename = 'my_submitted_service_requests_' . date('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        
        $callback = function() use ($serviceRequests) {
            $file = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($file, [
                'Request Number',
                'Title',
                'Service Type',
                'Division',
                'Request Date',
                'Total Budget',
                'Status',
                'Created At'
            ]);
            
            // CSV data
            foreach ($serviceRequests as $request) {
                fputcsv($file, [
                    $request->request_number,
                    $request->title ?? 'N/A',
                    $request->service_type,
                    $request->division->division_name ?? 'N/A',
                    $request->request_date ? \Carbon\Carbon::parse($request->request_date)->format('M d, Y') : 'N/A',
                    $request->new_total_budget ?? 0,
                    $request->overall_status,
                    $request->created_at->format('M d, Y H:i')
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export all service requests to Excel
     */
    public function exportAll(Request $request)
    {
        if (!in_array(87, user_session('permissions', []))) {
            abort(403, 'Unauthorized');
        }
        
        $query = ServiceRequest::with(['staff', 'division', 'workflowDefinition', 'currentActor'])
            ->latest();
            
        // Apply filters
        if ($request->has('staff_id') && $request->staff_id) {
            $query->where('staff_id', $request->staff_id);
        }
        
        if ($request->has('division_id') && $request->division_id) {
            $query->where('division_id', $request->division_id);
        }
        
        if ($request->has('service_type') && $request->service_type) {
            $query->where('service_type', $request->service_type);
        }
        
        if ($request->has('status') && $request->status) {
            $query->where('overall_status', $request->status);
        }
        
        $serviceRequests = $query->get();
        
        // For now, return a simple CSV export
        $filename = 'all_service_requests_' . date('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        
        $callback = function() use ($serviceRequests) {
            $file = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($file, [
                'Request Number',
                'Title',
                'Staff',
                'Service Type',
                'Division',
                'Request Date',
                'Total Budget',
                'Status',
                'Created At'
            ]);
            
            // CSV data
            foreach ($serviceRequests as $request) {
                fputcsv($file, [
                    $request->request_number,
                    $request->title ?? 'N/A',
                    $request->staff->name ?? 'N/A',
                    $request->service_type,
                    $request->division->division_name ?? 'N/A',
                    $request->request_date ? \Carbon\Carbon::parse($request->request_date)->format('M d, Y') : 'N/A',
                    $request->new_total_budget ?? 0,
                    $request->overall_status,
                    $request->created_at->format('M d, Y H:i')
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }

    /**
     * Submit service request for approval.
     */
    public function submitForApproval(ServiceRequest $serviceRequest): RedirectResponse
    {
        $serviceRequest->submitForApproval();

        return redirect()->route('service-requests.show', $serviceRequest)->with([
            'msg' => 'Service request submitted for approval successfully.',
            'type' => 'success',
        ]);
    }

    /**
     * Update approval status using generic approval system.
     */
    public function updateStatus(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $request->validate([
            'action' => 'required|in:approved,rejected,returned',
            'comment' => 'nullable|string|max:1000',
        ]);

        //(dd($request->all()));

        // Use the generic approval system
        $genericController = app(\App\Http\Controllers\GenericApprovalController::class);
        return $genericController->updateStatus($request, 'ServiceRequest', $serviceRequest->id);
    }

    /**
     * Show approval status page.
     */
    public function status(ServiceRequest $serviceRequest): View
    {
        $serviceRequest->load(['staff', 'division', 'forwardWorkflow', 'activity']);
        
        return view('service-requests.status', compact('serviceRequest'));
    }

    /**
     * Print service request as PDF.
     */
    public function print(ServiceRequest $serviceRequest)
    {
        // Eager load needed relations
        $serviceRequest->load([
            'staff', 
            'division', 
            'activity',
            'forwardWorkflow',
            'reverseWorkflow',
            'serviceRequestApprovalTrails.staff',
            'serviceRequestApprovalTrails.approverRole'
        ]);

        // Get source data if available
        $sourceData = null;
        $sourcePdfHtml = null;
        
        if ($serviceRequest->source_type && $serviceRequest->source_id) {
            $sourceData = $this->getSourceDataForForm($serviceRequest->source_type, $serviceRequest->source_id);
            
            // Generate source memo HTML using existing controllers' data preparation
            if ($serviceRequest->source_type === 'activity') {
                $activity = \App\Models\Activity::find($serviceRequest->source_id);
                if ($activity && $activity->matrix) {
                    $sourcePdfHtml = $this->generateActivityMemoHtml($activity->matrix, $activity);
                }
            } elseif ($serviceRequest->source_type === 'special_memo') {
                $specialMemo = \App\Models\SpecialMemo::find($serviceRequest->source_id);
                if ($specialMemo) {
                    $sourcePdfHtml = $this->generateSpecialMemoHtml($specialMemo);
                }
            } elseif ($serviceRequest->source_type === 'non_travel_memo') {
                $nonTravelMemo = \App\Models\NonTravelMemo::find($serviceRequest->source_id);
                if ($nonTravelMemo) {
                    $sourcePdfHtml = $this->generateNonTravelMemoHtml($nonTravelMemo);
                }
            }
        }

        // Get workflow information for service request
        $workflowInfo = $this->getComprehensiveWorkflowInfo($serviceRequest);
        $organizedWorkflowSteps = $this->organizeWorkflowStepsBySection($workflowInfo['workflow_steps']);

        // Use mPDF helper function for service request
        $print = false;
        $pdf = mpdf_print('service-requests.print', [
            'serviceRequest' => $serviceRequest,
            'sourceData' => $sourceData,
            'sourcePdfHtml' => $sourcePdfHtml,
            'organized_workflow_steps' => $organizedWorkflowSteps,
        ], ['preview_html' => $print]);

        // Generate filename
        $filename = 'Service_Request_' . $serviceRequest->request_number . '_' . now()->format('Y-m-d') . '.pdf';

        // Return PDF for display in browser using mPDF Output method
        return response($pdf->Output($filename, 'I'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"'
        ]);
    }

    /**
     * Get approval levels for progress bar calculation.
     */
    private function getApprovalLevels($serviceRequest)
    {
        if (!$serviceRequest->forward_workflow_id) {
            return [];
        }

        $levels = \App\Models\WorkflowDefinition::where('workflow_id', $serviceRequest->forward_workflow_id)
            ->where('is_enabled', 1)
            ->orderBy('approval_order', 'asc')
            ->get();

        $approvalLevels = [];
        foreach ($levels as $level) {
            $isCurrentLevel = $level->approval_order == $serviceRequest->approval_level;
            $isCompleted = $serviceRequest->approval_level > $level->approval_order;
            $isPending = $serviceRequest->approval_level == $level->approval_order && $serviceRequest->overall_status === 'pending';
            
            // Get approver information
            $approver = null;
            if (!$level->is_division_specific) {
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
     * Get comprehensive workflow information for service request
     */
    private function getComprehensiveWorkflowInfo(ServiceRequest $serviceRequest)
    {
        $workflowInfo = [
            'current_level' => null,
            'current_approver' => null,
            'workflow_steps' => collect(),
            'approval_trail' => collect(),
            'matrix_approval_trail' => collect()
        ];

        if (!$serviceRequest->forward_workflow_id) {
            return $workflowInfo;
        }

        // Get workflow definitions with category filtering
        $workflowDefinitions = \App\Models\WorkflowDefinition::where('workflow_id', $serviceRequest->forward_workflow_id)
            ->where('is_enabled', 1)
            ->where(function($query) use ($serviceRequest) {
                $query->where('approval_order', '!=', 7)
                      ->orWhere(function($subQuery) use ($serviceRequest) {
                          $subQuery->where('approval_order', 7)
                                   ->where('category', $serviceRequest->division->category ?? null);
                      });
            })
            ->orderBy('approval_order')
            ->with(['approvers.staff', 'approvers.oicStaff'])
            ->get();

        $workflowInfo['workflow_steps'] = $workflowDefinitions->map(function ($definition) use ($serviceRequest) {
            $approvers = collect();

            if ($definition->is_division_specific && $serviceRequest->division) {
                // Get approver from division table using division_reference_column
                $divisionColumn = $definition->division_reference_column;
                if ($divisionColumn && isset($serviceRequest->division->$divisionColumn)) {
                    $staffId = $serviceRequest->division->$divisionColumn;
                    if ($staffId) {
                        $staff = \App\Models\Staff::where('staff_id', $staffId)->first();
                        if ($staff) {
                            $approvers->push([
                                'staff' => [
                                    'id' => $staff->staff_id,
                                    'staff_id' => $staff->staff_id,
                                    'title' => $staff->title ?? 'N/A',
                                    'fname' => $staff->fname ?? '',
                                    'lname' => $staff->lname ?? '',
                                    'oname' => $staff->oname ?? '',
                                    'name' => $staff->fname . ' ' . $staff->lname,
                                    'job_title' => $staff->job_name ?? $staff->position ?? 'N/A',
                                    'position' => $staff->position ?? 'N/A',
                                    'work_email' => $staff->work_email ?? 'N/A',
                                    'signature' => $staff->signature ?? null,
                                ]
                            ]);
                        }
                    }
                }
            } else {
                // Get regular approvers
                foreach ($definition->approvers as $approver) {
                    $approverData = [
                        'staff' => [
                            'id' => $approver->staff->staff_id ?? null,
                            'staff_id' => $approver->staff->staff_id ?? null,
                            'title' => $approver->staff->title ?? 'N/A',
                            'fname' => $approver->staff->fname ?? '',
                            'lname' => $approver->staff->lname ?? '',
                            'oname' => $approver->staff->oname ?? '',
                            'name' => ($approver->staff->fname ?? '') . ' ' . ($approver->staff->lname ?? ''),
                            'job_title' => $approver->staff->job_name ?? $approver->staff->position ?? 'N/A',
                            'position' => $approver->staff->position ?? 'N/A',
                            'work_email' => $approver->staff->work_email ?? 'N/A',
                            'signature' => $approver->staff->signature ?? null,
                        ]
                    ];

                    // Add OIC staff if available
                    if ($approver->oicStaff) {
                        $approverData['oic_staff'] = [
                            'id' => $approver->oicStaff->staff_id ?? null,
                            'staff_id' => $approver->oicStaff->staff_id ?? null,
                            'title' => $approver->oicStaff->title ?? 'N/A',
                            'fname' => $approver->oicStaff->fname ?? '',
                            'lname' => $approver->oicStaff->lname ?? '',
                            'oname' => $approver->oicStaff->oname ?? '',
                            'name' => ($approver->oicStaff->fname ?? '') . ' ' . ($approver->oicStaff->lname ?? ''),
                            'job_title' => $approver->oicStaff->job_name ?? $approver->oicStaff->position ?? 'N/A',
                            'position' => $approver->oicStaff->position ?? 'N/A',
                            'work_email' => $approver->oicStaff->work_email ?? 'N/A',
                            'signature' => $approver->oicStaff->signature ?? null,
                        ];
                    }

                    $approvers->push($approverData);
                }
            }

            return [
                'order' => $definition->approval_order,
                'role' => $definition->role,
                'approvers' => $approvers,
                'is_division_specific' => $definition->is_division_specific,
                'division_reference_column' => $definition->division_reference_column,
                'category' => $definition->category,
            ];
        });

        return $workflowInfo;
    }

    /**
     * Organize workflow steps by section (to, through, from)
     */
    private function organizeWorkflowStepsBySection($workflowSteps)
    {
        $organized = [
            'to' => collect(),
            'through' => collect(),
            'from' => collect(),
            'others' => collect()
        ];

        foreach ($workflowSteps as $step) {
            $order = $step['order'];
            $role = $step['role'];

            // Determine section based on approval order
            if ($order <= 2) {
                $section = 'to';
            } elseif ($order <= 4) {
                $section = 'through';
            } elseif ($order <= 6) {
                $section = 'from';
            } else {
                $section = 'others';
            }

            $organized[$section]->push($step);
        }

        return $organized;
    }

    /**
     * Generate Activity Memo HTML using the same data preparation as ActivityController
     */
    private function generateActivityMemoHtml($matrix, $activity)
    {
        // Load comprehensive relationships for the activity
        $activity->load([
            'matrix.division.divisionHead',
            'matrix.division.focalPerson',
            'requestType',
            'fundType',
            'activityApprovalTrails.staff',
            'matrix.matrixApprovalTrails.staff',
            'responsiblePerson',
            'staff',
            'activity_budget.fundcode.fundType',
            'focalPerson'
        ]);

        // Load matrix with comprehensive relationships
        $matrix->load([
            'division.divisionHead',
            'division.focalPerson',
            'matrixApprovalTrails.staff',
            'activities' => function ($query) {
                $query->with(['staff', 'focalPerson', 'responsiblePerson', 'activity_budget.fundcode.fundType']);
            }
        ]);

        // Decode JSON fields
        $locationIds = is_string($activity->location_id)
            ? json_decode($activity->location_id, true)
            : ($activity->location_id ?? []);

        $budgetIds = is_string($activity->budget_id)
            ? json_decode($activity->budget_id, true)
            : ($activity->budget_id ?? []);

        $budgetItems = is_string($activity->budget_breakdown)
            ? json_decode($activity->budget_breakdown, true)
            : ($activity->budget_breakdown ?? []);

        $attachments = is_string($activity->attachment)
            ? json_decode($activity->attachment, true)
            : ($activity->attachment ?? []);

        // Decode internal participants (new format)
        $rawParticipants = is_string($activity->internal_participants)
            ? json_decode($activity->internal_participants, true)
            : ($activity->internal_participants ?? []);

        // Extract staff details and append date/days info
        $internalParticipants = [];
        if (!empty($rawParticipants)) {
            $staffDetails = \App\Models\Staff::whereIn('staff_id', array_keys($rawParticipants))->get()->keyBy('staff_id');

            foreach ($rawParticipants as $staffId => $participantData) {
                if (isset($staffDetails[$staffId])) {
                    $internalParticipants[] = [
                        'staff' => $staffDetails[$staffId],
                        'participant_start' => $participantData['participant_start'] ?? null,
                        'participant_end' => $participantData['participant_end'] ?? null,
                        'participant_days' => $participantData['participant_days'] ?? null,
                    ];
                }
            }
        }

        // Fetch related data
        $locations = \App\Models\Location::whereIn('id', $locationIds ?: [])->get();
        $fundCodes = \App\Models\FundCode::whereIn('id', $budgetIds ?: [])->get();

        // Get comprehensive workflow information
        $workflowInfo = $this->getComprehensiveWorkflowInfoForActivity($activity, $matrix);
        $organizedWorkflowSteps = $this->organizeWorkflowStepsBySection($workflowInfo['workflow_steps']);

        // Get matrix approval trails with staff details
        $matrixApprovals = $matrix->matrixApprovalTrails()->with('staff')->get();

        // Get activity approval trails with staff details and workflow definition
        $activityApprovals = $activity->activityApprovalTrails()->with(['staff', 'oicStaff', 'workflowDefinition'])->get();

        // Generate HTML using the same template
        return view('activities.memo-pdf-simple', [
            'activity' => $activity,
            'matrix' => $matrix,
            'locations' => $locations,
            'fundCodes' => $fundCodes, 
            'internalParticipants' => $internalParticipants,
            'budget_items' => $budgetItems,
            'attachments' => $attachments,
            'matrix_approval_trails' => $matrixApprovals,
            'activity_approval_trails' => $activityApprovals,
            'staff' => $activity->staff,
            'workflow_info' => $workflowInfo,
            'organized_workflow_steps' => $organizedWorkflowSteps
        ])->render();
    }

    /**
     * Generate Special Memo HTML using the same data preparation as SpecialMemoController
     */
    private function generateSpecialMemoHtml($specialMemo)
    {
        // Eager load relations
        $specialMemo->load([
            'staff', 
            'division', 
            'requestType',
            'approvalTrails.staff',
            'approvalTrails.oicStaff',
            'approvalTrails.workflowDefinition'
        ]);

        // Decode JSON fields safely
        $locationIds = is_string($specialMemo->location_id)
            ? json_decode($specialMemo->location_id, true)
            : ($specialMemo->location_id ?? []);

        $budgetIds = is_string($specialMemo->budget_id)
            ? json_decode($specialMemo->budget_id, true)
            : ($specialMemo->budget_id ?? []);

        $budgetBreakdown = $specialMemo->budget_breakdown;
        if (!is_array($budgetBreakdown)) {
            $decoded = json_decode($budgetBreakdown, true);
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }
            $budgetBreakdown = is_array($decoded) ? $decoded : [];
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
            // Check if participants are already processed (have 'staff' key)
            if (isset($rawParticipants[0]) && isset($rawParticipants[0]['staff'])) {
                // Participants are already processed, use as is
                $internalParticipants = $rawParticipants;
            } else {
                // Participants need to be processed - get staff details
                $staffIds = [];
                foreach ($rawParticipants as $participantData) {
                    if (isset($participantData['staff_id'])) {
                        $staffIds[] = $participantData['staff_id'];
                    }
                }
                
                if (!empty($staffIds)) {
                    $staffDetails = \App\Models\Staff::whereIn('staff_id', $staffIds)
                ->get()
                ->keyBy('staff_id');

                    foreach ($rawParticipants as $participantData) {
                        $staffId = $participantData['staff_id'] ?? null;
                $internalParticipants[] = [
                            'staff' => $staffId ? ($staffDetails[$staffId] ?? null) : null,
                    'participant_start' => $participantData['participant_start'] ?? null,
                    'participant_end' => $participantData['participant_end'] ?? null,
                    'participant_days' => $participantData['participant_days'] ?? null,
                ];
                    }
                }
            }
        }

        // Fetch related collections
        $locations = \App\Models\Location::whereIn('id', $locationIds ?: [])->get();
        $fundCodes = \App\Models\FundCode::whereIn('id', $budgetIds ?: [])->with('fundType')->get();

        // Get approval trails
        $approvalTrails = $specialMemo->approvalTrails;

        // Get workflow information
        $workflowInfo = $this->getComprehensiveWorkflowInfo($specialMemo);
        $organizedWorkflowSteps = $this->organizeWorkflowStepsBySection($workflowInfo['workflow_steps']);

        // Generate HTML using the same template
        return view('special-memo.memo-pdf-simple', [
            'specialMemo' => $specialMemo,
            'locations' => $locations,
            'fundCodes' => $fundCodes,
            'attachments' => $attachments,
            'budgetBreakdown' => $budgetBreakdown,
            'internalParticipants' => $internalParticipants,
            'approval_trails' => $approvalTrails,
            'matrix_approval_trails' => $approvalTrails, // For compatibility with activities template
            'workflow_info' => $workflowInfo,
            'organized_workflow_steps' => $organizedWorkflowSteps
        ])->render();
    }

    /**
     * Generate Non-Travel Memo HTML using the same data preparation as NonTravelMemoController
     */
    private function generateNonTravelMemoHtml($nonTravel)
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
        $locations = \App\Models\Location::whereIn('id', $locationIds ?: [])->get();
        $fundCodes = \App\Models\FundCode::whereIn('id', $budgetIds ?: [])->with('fundType')->get();

        // Get approval trails (not activity approval trails)
        $approvalTrails = $nonTravel->approvalTrails;

        // Get workflow information
        $workflowInfo = $this->getComprehensiveWorkflowInfo($nonTravel);
        $organizedWorkflowSteps = $this->organizeWorkflowStepsBySection($workflowInfo['workflow_steps']);

        // Generate HTML using the same template
        return view('non-travel.memo-pdf-simple', [
            'nonTravel' => $nonTravel,
            'locations' => $locations,
            'fundCodes' => $fundCodes,
            'attachments' => $attachments,
            'budgetBreakdown' => $breakdown,
            'approval_trails' => $approvalTrails,
            'matrix_approval_trails' => $approvalTrails, // For compatibility with activities template
            'workflow_info' => $workflowInfo,
            'organized_workflow_steps' => $organizedWorkflowSteps
        ])->render();
    }

    /**
     * Get comprehensive workflow information for activity (copied from ActivityController)
     */
    private function getComprehensiveWorkflowInfoForActivity($activity, $matrix)
    {
        $workflowInfo = [
            'current_level' => null,
            'current_approver' => null,
            'workflow_steps' => collect(),
            'approval_trail' => collect(),
            'matrix_approval_trail' => collect()
        ];

        // Get matrix workflow information
        if ($matrix->forward_workflow_id) {
            // Get workflow definition with category filtering for order 7
            $workflowDefinitions = \App\Models\WorkflowDefinition::where('workflow_id', $matrix->forward_workflow_id)
                ->where('is_enabled', 1)
                ->where(function($query) use ($matrix) {
                    $query->where('approval_order', '!=', 7)
                          ->orWhere(function($subQuery) use ($matrix) {
                              $subQuery->where('approval_order', 7)
                                       ->where('category', $matrix->division->category ?? null);
                          });
                })
                ->orderBy('approval_order')
                ->with(['approvers.staff', 'approvers.oicStaff'])
                ->get();

            $workflowInfo['workflow_steps'] = $workflowDefinitions->map(function ($definition) use ($matrix) {
                $approvers = collect();

                if ($definition->is_division_specific && $matrix->division) {
                    // Get approver from division table using division_reference_column
                    $divisionColumn = $definition->division_reference_column;
                    if ($divisionColumn && isset($matrix->division->$divisionColumn)) {
                        $staffId = $matrix->division->$divisionColumn;
                        if ($staffId) {
                            $staff = \App\Models\Staff::where('staff_id', $staffId)->first();
                            if ($staff) {
                                $approvers->push([
                                    'staff' => [
                                        'id' => $staff->staff_id,
                                        'staff_id' => $staff->staff_id,
                                        'title' => $staff->title ?? 'N/A',
                                        'fname' => $staff->fname ?? '',
                                        'lname' => $staff->lname ?? '',
                                        'oname' => $staff->oname ?? '',
                                        'name' => $staff->fname . ' ' . $staff->lname,
                                        'job_title' => $staff->job_name ?? $staff->position ?? 'N/A',
                                        'position' => $staff->position ?? 'N/A',
                                        'work_email' => $staff->work_email ?? 'N/A',
                                        'signature' => $staff->signature ?? null,
                                    ]
                                ]);
                            }
                        }
                    }
                } else {
                    // Get regular approvers
                    foreach ($definition->approvers as $approver) {
                        $approverData = [
                            'staff' => [
                                'id' => $approver->staff->staff_id ?? null,
                                'staff_id' => $approver->staff->staff_id ?? null,
                                'title' => $approver->staff->title ?? 'N/A',
                                'fname' => $approver->staff->fname ?? '',
                                'lname' => $approver->staff->lname ?? '',
                                'oname' => $approver->staff->oname ?? '',
                                'name' => ($approver->staff->fname ?? '') . ' ' . ($approver->staff->lname ?? ''),
                                'job_title' => $approver->staff->job_name ?? $approver->staff->position ?? 'N/A',
                                'position' => $approver->staff->position ?? 'N/A',
                                'work_email' => $approver->staff->work_email ?? 'N/A',
                                'signature' => $approver->staff->signature ?? null,
                            ]
                        ];

                        // Add OIC staff if available
                        if ($approver->oicStaff) {
                            $approverData['oic_staff'] = [
                                'id' => $approver->oicStaff->staff_id ?? null,
                                'staff_id' => $approver->oicStaff->staff_id ?? null,
                                'title' => $approver->oicStaff->title ?? 'N/A',
                                'fname' => $approver->oicStaff->fname ?? '',
                                'lname' => $approver->oicStaff->lname ?? '',
                                'oname' => $approver->oicStaff->oname ?? '',
                                'name' => ($approver->oicStaff->fname ?? '') . ' ' . ($approver->oicStaff->lname ?? ''),
                                'job_title' => $approver->oicStaff->job_name ?? $approver->oicStaff->position ?? 'N/A',
                                'position' => $approver->oicStaff->position ?? 'N/A',
                                'work_email' => $approver->oicStaff->work_email ?? 'N/A',
                                'signature' => $approver->oicStaff->signature ?? null,
                            ];
                        }

                        $approvers->push($approverData);
                    }
                }

                return [
                    'order' => $definition->approval_order,
                    'role' => $definition->role,
                    'approvers' => $approvers,
                    'is_division_specific' => $definition->is_division_specific,
                    'division_reference_column' => $definition->division_reference_column,
                    'category' => $definition->category,
                ];
            });
        }

        return $workflowInfo;
    }
}
