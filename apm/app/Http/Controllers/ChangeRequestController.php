<?php

namespace App\Http\Controllers;

use App\Models\ChangeRequest;
use App\Models\Activity;
use App\Models\SpecialMemo;
use App\Models\NonTravelMemo;
use App\Models\RequestArf;
use App\Models\ServiceRequest;
use App\Models\RequestType;
use App\Models\FundType;
use App\Models\Location;
use App\Models\Staff;
use App\Models\Division;
use App\Models\Matrix;
use Exception;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use App\Services\ApprovalService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class ChangeRequestController extends Controller
{
    /**
     * Display a listing of change requests.
     */
    public function index(Request $request): View
    {
        $userStaffId = user_session('staff_id');
        $userDivisionId = user_session('division_id');

        // Get filter parameters
        $selectedYear = $request->get('year', now()->year);
        $selectedQuarter = $request->get('quarter', 'Q4');
        $selectedDivisionId = $request->get('division_id', $userDivisionId);
        $status = $request->get('status', 'all');
        $documentNumber = $request->get('document_number');
        $staffId = (int) $request->get('staff_id');
        $memoType = $request->get('memo_type');

        // Base query with relationships
        $baseQuery = ChangeRequest::with([
            'staff',
            'responsiblePerson',
            'division',
            'requestType',
            'fundType',
            'parentMemo'
        ]);

        // Apply common filters
        if ($documentNumber) {
            $baseQuery->where('document_number', 'like', '%' . $documentNumber . '%');
        }

        if ($staffId) {
            $baseQuery->where(function ($q) use ($staffId) {
                $q->where('staff_id', $staffId)
                  ->orWhere('responsible_person_id', $staffId);
            });
        }

        if ($selectedYear) {
            $baseQuery->whereYear('created_at', $selectedYear);
        }

        if ($status !== 'all') {
            $baseQuery->where('overall_status', $status);
        }

        // Filter by memo type (parent_memo_model)
        if ($memoType) {
            $baseQuery->where('parent_memo_model', $memoType);
        }

        // Filter by division
        if ($selectedDivisionId) {
            $baseQuery->where('division_id', (int) $selectedDivisionId);
        }

        // Filter by search term
        if ($request->filled('search')) {
            $baseQuery->where('activity_title', 'like', '%' . $request->search . '%');
        }

        // Order by most recent first
        $baseQuery->orderBy('created_at', 'desc');

        // My Change Requests (created by current user)
        $myChangeRequestsQuery = clone $baseQuery;
        $myChangeRequests = $myChangeRequestsQuery->where('staff_id', $userStaffId)->paginate(20)->withQueryString();

        // My Division Change Requests (change requests in user's division)
        $myDivisionChangeRequestsQuery = clone $baseQuery;
        $myDivisionChangeRequests = $myDivisionChangeRequestsQuery->where('division_id', $userDivisionId)->paginate(20)->withQueryString();

        // Shared Change Requests (where user is responsible person)
        $sharedChangeRequestsQuery = clone $baseQuery;
        $sharedChangeRequests = $sharedChangeRequestsQuery->where('responsible_person_id', $userStaffId)->paginate(20)->withQueryString();

        // All Change Requests (for users with permission)
        $allChangeRequests = null;
        if (in_array(87, user_session('permissions', []))) {
            $allChangeRequests = $baseQuery->paginate(20)->withQueryString();
        }

        // Get filter options
        $years = range(now()->year - 2, now()->year + 2);
        $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
        $divisions = Division::orderBy('division_name')->get();
        $staff = Staff::orderBy('fname')->get();
        $statuses = [
            'all' => 'All Status',
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'approved' => 'Approved',
            'rejected' => 'Rejected'
        ];

        // Handle AJAX requests for tab content
        if ($request->ajax()) {
            $tab = $request->get('tab', '');
            $html = '';
            
            switch($tab) {
                case 'myChangeRequests':
                    $html = view('change-requests.partials.my-change-requests-tab', compact('myChangeRequests'))->render();
                    break;
                case 'myDivisionChangeRequests':
                    $html = view('change-requests.partials.my-division-change-requests-tab', compact('myDivisionChangeRequests'))->render();
                    break;
                case 'sharedChangeRequests':
                    $html = view('change-requests.partials.shared-change-requests-tab', compact('sharedChangeRequests'))->render();
                    break;
                case 'allChangeRequests':
                    $html = view('change-requests.partials.all-change-requests-tab', compact('allChangeRequests'))->render();
                    break;
            }
            
            return response()->json(['html' => $html]);
        }

        return view('change-requests.index', [
            'myChangeRequests' => $myChangeRequests,
            'myDivisionChangeRequests' => $myDivisionChangeRequests,
            'sharedChangeRequests' => $sharedChangeRequests,
            'allChangeRequests' => $allChangeRequests,
            'years' => $years,
            'quarters' => $quarters,
            'divisions' => $divisions,
            'staff' => $staff,
            'statuses' => $statuses,
            'selectedYear' => $selectedYear,
            'selectedQuarter' => $selectedQuarter,
            'selectedDivisionId' => $selectedDivisionId,
            'selectedStatus' => $status,
            'selectedMemoType' => $memoType,
            'userDivisionId' => $userDivisionId
        ]);
    }

    /**
     * Show pending approvals for change requests
     */
    public function pendingApprovals(Request $request): View
    {
        $userStaffId = user_session('staff_id');

        // Check if we have valid session data
        if (!$userStaffId) {
            return view('change-requests.pending-approvals', [
                'pendingChangeRequests' => collect(),
                'approvedByMe' => collect(),
                'divisions' => collect(),
                'error' => 'No session data found. Please log in again.'
            ]);
        }

        // Use the exact same logic as the home helper for consistency
        $userDivisionId = user_session('division_id');
        
        // Get filter parameters
        $memoType = $request->get('memo_type');
        $divisionId = $request->get('division_id');
        $staffId = $request->get('staff_id');
        
        // Base query for pending change requests
        $pendingQuery = ChangeRequest::with([
            'staff',
            'division',
            'requestType',
            'parentMemo'
        ])
        ->where('overall_status', 'submitted');

        // Apply filters
        if ($memoType) {
            $pendingQuery->where('parent_memo_model', $memoType);
        }
        
        if ($divisionId) {
            $pendingQuery->where('division_id', $divisionId);
        }
        
        if ($staffId) {
            $pendingQuery->where('staff_id', $staffId);
        }

        // Get pending change requests
        $pendingChangeRequests = $pendingQuery->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        // Get approved by me change requests
        $approvedByMeQuery = ChangeRequest::with([
            'staff',
            'division',
            'requestType',
            'parentMemo'
        ])
        ->where('overall_status', 'approved')
        ->whereHas('approvalTrails', function($q) use ($userStaffId) {
            $q->where('staff_id', $userStaffId)
              ->where('action', 'approved');
        });

        // Apply same filters to approved by me
        if ($memoType) {
            $approvedByMeQuery->where('parent_memo_model', $memoType);
        }
        
        if ($divisionId) {
            $approvedByMeQuery->where('division_id', $divisionId);
        }
        
        if ($staffId) {
            $approvedByMeQuery->where('staff_id', $staffId);
        }

        $approvedByMe = $approvedByMeQuery->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        // Get divisions for filter
        $divisions = Division::orderBy('division_name')->get();

        // Handle AJAX requests for tab content
        if ($request->ajax()) {
            $tab = $request->get('tab', '');
            $html = '';
            
            switch($tab) {
                case 'pending':
                    $html = view('change-requests.partials.pending-approvals-tab', compact('pendingChangeRequests'))->render();
                    break;
                case 'approved':
                    $html = view('change-requests.partials.approved-by-me-tab', compact('approvedByMe'))->render();
                    break;
            }
            
            return response()->json(['html' => $html]);
        }

        return view('change-requests.pending-approvals', [
            'pendingChangeRequests' => $pendingChangeRequests,
            'approvedByMe' => $approvedByMe,
            'divisions' => $divisions
        ]);
    }

    /**
     * Show the form for creating a new change request.
     */
    public function create(Request $request): View
    {
        $parentMemoId = $request->get('parent_memo_id');
        $parentMemoModel = $request->get('parent_memo_model');

        // Get the parent memo
        $parentMemo = null;
        if ($parentMemoId && $parentMemoModel) {
            $parentMemo = $this->getParentMemo($parentMemoModel, $parentMemoId);
        }

        // Get form data
        $requestTypes = RequestType::all();
        $fundTypes = FundType::all();
        $locations = Location::all();
        $divisions = Division::orderBy('division_name')->get();
        $staff = Staff::orderBy('fname')->get();

        return view('change-requests.create', [
            'parentMemo' => $parentMemo,
            'parentMemoId' => $parentMemoId,
            'parentMemoModel' => $parentMemoModel,
            'requestTypes' => $requestTypes,
            'fundTypes' => $fundTypes,
            'locations' => $locations,
            'divisions' => $divisions,
            'staff' => $staff
        ]);
    }

    /**
     * Store a newly created change request.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $userStaffId = user_session('staff_id');
        $userDivisionId = user_session('division_id');

        return DB::transaction(function () use ($request, $userStaffId, $userDivisionId) {
            try {
                // Validate required fields
                $validated = $request->validate([
                    'parent_memo_id' => 'required|integer',
                    'parent_memo_model' => 'required|string',
                    'activity_title' => 'required|string|max:255',
                    'supporting_reasons' => 'required|string',
                    'location_id' => 'required|array|min:1',
                    'location_id.*' => 'exists:locations,id',
                ]);

        // Get the parent memo to copy data from
        $parentMemo = $this->getParentMemo($request->parent_memo_model, (int) $request->parent_memo_id);
        if (!$parentMemo) {
            throw new \Exception('Parent memo not found');
        }

                // Calculate total budget from budget items
                $totalBudget = 0;
                $budgetItems = $request->input('budget', []);
                $fundTypeId = (int) $request->input('fund_type', 1);
                
                if (!empty($budgetItems)) {
                    foreach ($budgetItems as $codeId => $items) {
                        if (is_array($items)) {
                            foreach ($items as $item) {
                                $qty = isset($item['units']) ? floatval($item['units']) : 1;
                                $unitCost = isset($item['unit_cost']) ? floatval($item['unit_cost']) : 0;
                                $totalBudget += $qty * $unitCost;
                            }
                        }
                    }
                }

                // Build internal_participants array
                $participantStarts = $request->input('participant_start', []);
                $participantEnds = $request->input('participant_end', []);
                $participantDays = $request->input('participant_days', []);
                $internationalTravel = $request->input('international_travel', []);

                $internalParticipants = [];
                foreach ($participantStarts as $staffId => $startDate) {
                    $internalParticipants[$staffId] = [
                        'participant_start' => $startDate,
                        'participant_end' => $participantEnds[$staffId] ?? null,
                        'participant_days' => $participantDays[$staffId] ?? null,
                        'international_travel' => isset($internationalTravel[$staffId]) ? 1 : 0,
                    ];
                }

                $budgetCodes = $request->input('budget_codes', []);
                
                // Handle file uploads for attachments
                $attachments = [];
                if ($request->hasFile('attachments')) {
                    $uploadedFiles = $request->file('attachments');
                    $attachmentTypes = $request->input('attachments', []);
                    
                    foreach ($uploadedFiles as $index => $file) {
                        if ($file && $file->isValid()) {
                            $type = $attachmentTypes[$index]['type'] ?? 'Document';
                            
                            // Generate unique filename
                            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                            
                            // Store file in public/uploads/change-requests directory
                            $path = $file->storeAs('uploads/change-requests', $filename, 'public');
                            
                            $attachments[] = [
                                'type' => $type,
                                'filename' => $filename,
                                'original_name' => $file->getClientOriginalName(),
                                'path' => $path,
                                'size' => $file->getSize(),
                                'mime_type' => $file->getMimeType(),
                                'uploaded_at' => now()->toDateTimeString()
                            ];
                        }
                    }
                }

                // Determine which fields have changed
                $changes = $this->detectChanges($parentMemo, $request);

               // dd($changes);

                // Create the change request record
                $changeRequest = ChangeRequest::create([
                    'parent_memo_id' => (int) $request->parent_memo_id,
                    'parent_memo_model' => $request->parent_memo_model,
                    'activity_id' => $parentMemo instanceof Activity ? (int) $parentMemo->id : null,
                    'special_memo_id' => $parentMemo instanceof SpecialMemo ? (int) $parentMemo->id : null,
                    'non_travel_memo_id' => $parentMemo instanceof NonTravelMemo ? (int) $parentMemo->id : null,
                    'request_arf_id' => $parentMemo instanceof RequestArf ? (int) $parentMemo->id : null,
                    'service_request_id' => $parentMemo instanceof ServiceRequest ? (int) $parentMemo->id : null,
                    
                    // Change tracking flags
                    'has_budget_id_changed' => $changes['budget_id'],
                    'has_internal_participants_changed' => $changes['internal_participants'],
                    'has_request_type_id_changed' => $changes['request_type_id'],
                    'has_total_external_participants_changed' => $changes['total_external_participants'],
                    'has_location_changed' => $changes['location'],
                    'has_memo_date_changed' => $changes['memo_date'],
                    'has_activity_title_changed' => $changes['activity_title'],
                    'has_activity_request_remarks_changed' => $changes['activity_request_remarks'],
                    'has_is_single_memo_changed' => $changes['is_single_memo'],
                    'has_budget_breakdown_changed' => $changes['budget_breakdown'],
                    'has_status_changed' => $changes['status'],
                    'has_fund_type_id_changed' => $changes['fund_type_id'],
                    
                    // Document and workflow fields
                    'forward_workflow_id' => $parentMemo->forward_workflow_id ?? null,
                    'matrix_id' => $parentMemo->matrix_id ?? null,
                    'division_id' => $parentMemo->division_id ?? $userDivisionId,
                    'staff_id' => $userStaffId,
                    'responsible_person_id' => $request->input('responsible_person_id'),
                    
                    // Content fields
                    'supporting_reasons' => $request->input('supporting_reasons'),
                    'date_from' => $request->input('date_from', $parentMemo->date_from ?? now()->toDateString()),
                    'date_to' => $request->input('date_to', $parentMemo->date_to ?? now()->toDateString()),
                    'memo_date' => $request->input('memo_date', $parentMemo->memo_date ?? now()->toDateString()),
                    'location_id' => json_encode($request->input('location_id', [])),
                    'total_participants' => (int) $request->input('total_participants', 1),
                    'internal_participants' => json_encode($internalParticipants),
                    'total_external_participants' => (int) $request->input('total_external_participants', 0),
                    'division_staff_request' => $parentMemo->division_staff_request ?? null,
                    'budget_id' => json_encode($budgetCodes),
                    'key_result_area' => $request->input('key_result_area', $parentMemo->key_result_area ?? ''),
                    'non_travel_memo_category_id' => $parentMemo->non_travel_memo_category_id ?? null,
                    'request_type_id' => (int) $request->input('request_type_id', $parentMemo->request_type_id ?? 1),
                    'activity_title' => $request->input('activity_title'),
                    'background' => $request->input('background', $parentMemo->background ?? ''),
                    'activity_request_remarks' => $request->input('activity_request_remarks', $parentMemo->activity_request_remarks ?? ''),
                    'is_single_memo' => $request->input('is_single_memo', $parentMemo->is_single_memo ?? false),
                    'budget_breakdown' => json_encode($budgetItems),
                    'available_budget' => $totalBudget,
                    'attachment' => json_encode($attachments),
                    
                    // Status fields
                    'status' => ChangeRequest::STATUS_DRAFT,
                    'fund_type_id' => $request->input('fund_type', $parentMemo->fund_type_id ?? 1),
                    'activity_ref' => $parentMemo->activity_ref ?? null,
                    'approval_level' => 0,
                    'next_approval_level' => null,
                    'overall_status' => ChangeRequest::STATUS_DRAFT,
                ]);

                Log::info('Change request created', ['change_request' => $changeRequest]);

                $successMessage = 'Change request created successfully.';
                $redirectUrl = route('change-requests.show', $changeRequest);
                
                if ($request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'msg' => $successMessage,
                        'redirect_url' => $redirectUrl,
                        'change_request' => [
                            'id' => (int) $changeRequest->id,
                            'title' => $changeRequest->activity_title,
                            'status' => $changeRequest->overall_status,
                            'document_number' => $changeRequest->document_number
                        ]
                    ]);
                }

                return redirect($redirectUrl)
                    ->with([
                        'msg' => $successMessage,
                        'type' => 'success'
                    ]);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error creating change request', ['exception' => $e]);

                $errorMessage = 'Failed to create change request: ' . $e->getMessage();
                
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'msg' => $errorMessage
                    ], 422);
                }

                return redirect()->back()->withInput()->with([
                    'msg' => $errorMessage,
                    'type' => 'error'
                ]);
            }
        });
    }

    /**
     * Display the specified change request.
     */
    public function show(ChangeRequest $changeRequest): View
    {
        $changeRequest->load([
            'staff',
            'responsiblePerson',
            'division',
            'requestType',
            'fundType',
            'parentMemo',
            'forwardWorkflow',
            'approvalTrails.staff'
        ]);

        return view('change-requests.show', [
            'changeRequest' => $changeRequest
        ]);
    }

    /**
     * Get parent memo based on model and ID
     */
    private function getParentMemo(string $model, int $id)
    {
        $modelClass = "{$model}";

        //dd($modelClass);
        
        if (!class_exists($modelClass)) {
            return null;
        }

       // dd($modelClass::find($id));

        return $modelClass::find($id);
    }

    /**
     * Detect changes between parent memo and request data
     */
    private function detectChanges($parentMemo, Request $request): array
    {
        $changes = [
            'budget_id' => false,
            'internal_participants' => false,
            'request_type_id' => false,
            'total_external_participants' => false,
            'location' => false,
            'memo_date' => false,
            'activity_title' => false,
            'activity_request_remarks' => false,
            'is_single_memo' => false,
            'budget_breakdown' => false,
            'status' => false,
            'fund_type_id' => false,
        ];

        // Check each field for changes
        if ($parentMemo->budget_id !== json_encode($request->input('budget_codes', []))) {
            $changes['budget_id'] = true;
        }

        if ($parentMemo->activity_title !== $request->input('activity_title')) {
            $changes['activity_title'] = true;
        }

        if ($parentMemo->request_type_id != $request->input('request_type_id')) {
            $changes['request_type_id'] = true;
        }

        if ($parentMemo->fund_type_id != $request->input('fund_type')) {
            $changes['fund_type_id'] = true;
        }
       // if($parentMemo->internal_participants !== json_encode($request->input('internal_participants')) )
          // dd($parentMemo->internal_participants);
           //dd($parentMemo->internal_participants);
        // Add more change detection logic as needed

        return $changes;
    }
}