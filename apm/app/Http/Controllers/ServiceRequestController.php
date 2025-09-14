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
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
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
        $baseQuery = ServiceRequest::with(['staff', 'division', 'workflowDefinition', 'currentActor'])
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
        \Log::info('ServiceRequestController::create method called');
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
                    \Log::info('Source data loaded', [
                        'source_type' => $sourceType,
                        'source_id' => $sourceId,
                        'has_budget_breakdown' => isset($sourceData->budget_breakdown),
                        'budget_breakdown' => $sourceData->budget_breakdown ?? 'not set',
                        'has_internal_participants' => isset($sourceData->internal_participants),
                        'internal_participants' => $sourceData->internal_participants ?? 'not set'
                    ]);
                    
                    $budgetBreakdown = $this->processBudgetDataFromSource($sourceData, $sourceType);
                    $originalTotalBudget = $budgetBreakdown['grand_total'] ?? 0;
                    
                    // Process internal participants from source data
                    $internalParticipants = $this->processInternalParticipantsFromSource($sourceData, $sourceType);
                    
                    \Log::info('Processed budget breakdown', [
                        'budget_breakdown' => $budgetBreakdown,
                        'original_total' => $originalTotalBudget
                    ]);
                    
                    \Log::info('Processed internal participants', [
                        'internal_participants' => $internalParticipants
                    ]);
                } else {
                    \Log::warning('Source data is null', [
                        'source_type' => $sourceType,
                        'source_id' => $sourceId
                    ]);
                }
            }
            
            // Get cost items that exist in budget breakdown and filter by type
            \Log::info('Starting cost items extraction');
            $costItems = collect();
            $otherCostItems = collect();
            
            if ($budgetBreakdown && is_array($budgetBreakdown)) {
                \Log::info('Cost items extraction - budget breakdown exists: ' . ($budgetBreakdown ? 'yes' : 'no'));
                \Log::info('Cost items extraction - budget breakdown type: ' . gettype($budgetBreakdown));
                
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
                    \Log::info('Cost item names extracted from budget breakdown: ' . json_encode($costItemNames));
                    
                    // Get Individual Cost items that exist in budget breakdown
                    $individualCosts = CostItem::whereIn('name', $costItemNames)
                                              ->where('cost_type', 'Individual Cost')
                                              ->get();
                    \Log::info('Found ' . $individualCosts->count() . ' Individual Cost items from budget breakdown');
                    
                    // Get Other Cost items that exist in budget breakdown
                    $otherCosts = CostItem::whereIn('name', $costItemNames)
                                         ->where('cost_type', 'Other Cost')
                                         ->get();
                    \Log::info('Found ' . $otherCosts->count() . ' Other Cost items from budget breakdown');
                    
                    $costItems = $individualCosts;
                    $otherCostItems = $otherCosts;
                }
            }
            
            // Fallback to all Individual Cost items if no budget breakdown
            if ($costItems->isEmpty()) {
                $costItems = CostItem::where('cost_type', 'Individual Cost')->get();
                \Log::info('Using fallback: all Individual Cost items (' . $costItems->count() . ' items)');
            }
            
            // Only show Other Cost items that exist in budget breakdown
            // No fallback - if no Other Cost items in budget, show empty list
            if ($otherCostItems->isEmpty()) {
                \Log::info('No Other Cost items found in budget breakdown - showing empty list');
            }
            
            // Generate a unique request number with actual activity parameters (like ARF)
            $requestNumber = $this->generateServiceRequestNumber($sourceData, $sourceType);
            
            // Get participant names from internal participants JSON
            $participantNames = [];
            if (!empty($internalParticipants) && is_array($internalParticipants)) {
                foreach ($internalParticipants as $participantId => $participantData) {
                    // Get staff member details for each participant
                    $staffMember = Staff::where('staff_id', $participantId)->first();
                    if ($staffMember) {
                        $participantNames[] = [
                            'id' => $staffMember->staff_id,
                            'text' => $staffMember->fname . ' ' . $staffMember->lname . ' (' . ($staffMember->position ?? 'Staff') . ')',
                            'name' => $staffMember->fname . ' ' . $staffMember->lname,
                            'position' => $staffMember->position ?? 'Staff'
                        ];
                    }
                }
            }
            
            return view('service-requests.create', compact('staff', 'divisions', 'workflows', 'activities', 'costItems', 'otherCostItems', 'requestNumber', 'sourceData', 'sourceType', 'sourceId', 'budgetBreakdown', 'originalTotalBudget', 'internalParticipants', 'participantNames'));
        } catch (\Exception $e) {
            \Log::error('Error in ServiceRequestController::create: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
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
            'external_participants_cost' => 'nullable|string',
            'other_costs' => 'nullable|array',
            'internal_participants' => 'nullable|array',
            'external_participants' => 'nullable|array',
        ]);
        $validated['staff_id'] = user_session('staff_id');
        $validated['division_id'] = user_session('division_id');
        $validated['request_number'] = ServiceRequest::generateRequestNumber();
        
        // Process budget data
        $budgetData = $this->processBudgetData($request);
        
        // Process specifications array if provided
        if (!isset($validated['specifications']) || !is_array($validated['specifications'])) {
            $validated['specifications'] = [];
        }
        
        // Set default status if not provided
        if (!isset($validated['status'])) {
            $validated['status'] = 'draft';
        }
        
        // Merge budget data with validated data
        $validated = array_merge($validated, $budgetData);
       
        ServiceRequest::create($validated);
        
        return redirect()
            ->route('service-requests.index')
            ->with('success', 'Service request created successfully.');
    }

    /**
     * Process budget data from the form
     */
    private function processBudgetData(Request $request): array
    {
        $budgetData = [];
        
        // Process internal participants
        $internalParticipants = $request->input('internal_participants', []);
        $internalCosts = [];
        $internalTotal = 0;
        
        foreach ($internalParticipants as $participant) {
            if (!empty($participant['staff_id'])) {
                $staffId = $participant['staff_id'];
                $costs = $participant['costs'] ?? [];
                $costType = $participant['cost_type'] ?? 'Daily Rate';
                $description = $participant['description'] ?? '';
                
                // Calculate total from costs array
                $total = 0;
                foreach ($costs as $costValue) {
                    $total += floatval($costValue);
                }
                
                $internalCosts[] = [
                    'staff_id' => $staffId,
                    'cost_type' => $costType,
                    'costs' => $costs,
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
        
        foreach ($externalParticipants as $participant) {
            if (!empty($participant['name'])) {
                $name = $participant['name'];
                $email = $participant['email'] ?? '';
                $costs = $participant['costs'] ?? [];
                $costType = $participant['cost_type'] ?? 'Daily Rate';
                $description = $participant['description'] ?? '';
                
                // Calculate total from costs array
                $total = 0;
                foreach ($costs as $costValue) {
                    $total += floatval($costValue);
                }
                
                $externalCosts[] = [
                    'name' => $name,
                    'email' => $email,
                    'cost_type' => $costType,
                    'costs' => $costs,
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
            \Log::info('Processing budget data from source', [
                'source_type' => $sourceType,
                'source_data_exists' => $sourceData !== null,
                'source_data_class' => $sourceData ? get_class($sourceData) : 'null'
            ]);
            
            switch ($sourceType) {
                case 'activity':
                    if ($sourceData && isset($sourceData->budget_breakdown)) {
                        \Log::info('Activity budget breakdown found', [
                            'budget_breakdown' => $sourceData->budget_breakdown,
                            'is_string' => is_string($sourceData->budget_breakdown)
                        ]);
                        
                        $budget = is_string($sourceData->budget_breakdown) 
                            ? json_decode($sourceData->budget_breakdown, true) 
                            : $sourceData->budget_breakdown;
                        
                        \Log::info('Decoded budget data', [
                            'decoded_budget' => $budget,
                            'is_array' => is_array($budget)
                        ]);
                        
                        if (is_array($budget)) {
                            return $budget;
                        }
                    } else {
                        \Log::warning('Activity budget breakdown not found', [
                            'has_source_data' => $sourceData !== null,
                            'has_budget_breakdown' => $sourceData ? isset($sourceData->budget_breakdown) : false
                        ]);
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
            Log::error('Error processing budget data from source: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Process internal participants from source (activity, memo, etc.)
     */
    private function processInternalParticipantsFromSource($sourceData, string $sourceType): array
    {
        try {
            \Log::info('Processing internal participants from source', [
                'source_type' => $sourceType,
                'source_data_exists' => $sourceData !== null,
                'source_data_class' => $sourceData ? get_class($sourceData) : 'null'
            ]);
            
            switch ($sourceType) {
                case 'activity':
                    if ($sourceData && isset($sourceData->internal_participants)) {
                        \Log::info('Activity internal participants found', [
                            'internal_participants' => $sourceData->internal_participants,
                            'is_string' => is_string($sourceData->internal_participants)
                        ]);
                        
                        $participants = is_string($sourceData->internal_participants) 
                            ? json_decode($sourceData->internal_participants, true) 
                            : $sourceData->internal_participants;
                        
                        \Log::info('Decoded internal participants', [
                            'decoded_participants' => $participants,
                            'is_array' => is_array($participants)
                        ]);
                        
                        if (is_array($participants)) {
                            return $participants;
                        }
                    } else {
                        \Log::warning('Activity internal participants not found', [
                            'has_source_data' => $sourceData !== null,
                            'has_internal_participants' => $sourceData ? isset($sourceData->internal_participants) : false
                        ]);
                    }
                    break;
                    
                case 'non_travel_memo':
                case 'special_memo':
                    // Non-travel and special memos don't have internal participants
                    \Log::info('Non-travel/Special memo - no internal participants expected');
                    return [];
            }
            
            return [];
        } catch (\Exception $e) {
            Log::error('Error processing internal participants from source: ' . $e->getMessage());
            return [];
        }
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
            Log::error('Error getting source data for service request: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading source data: ' . $e->getMessage()
            ], 500);
        }
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
                'external_participants_cost' => 'nullable|array',
                'other_costs' => 'nullable|array',
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
                $assignedWorkflowId = 3; // Default workflow ID
                Log::warning('No workflow assignment found for ServiceRequest model, using default workflow ID: 3');
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
                'external_participants_cost' => $validated['external_participants_cost'] ?? [],
                'other_costs' => $validated['other_costs'] ?? [],
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
            Log::error('Error creating service request from modal: ' . $e->getMessage());
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
            Log::error('Error getting cost items: ' . $e->getMessage());
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
            Log::error('Error getting source data: ' . $e->getMessage());
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
}
