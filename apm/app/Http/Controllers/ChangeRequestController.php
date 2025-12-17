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
    public function index(Request $request): View|JsonResponse
    {
        $userStaffId = user_session('staff_id');
        $userDivisionId = user_session('division_id');
        
        // If no session data, show all change requests
        $showAllDueToNoSession = empty($userStaffId);

        // Get filter parameters
        $selectedYear = $request->get('year', now()->year);
        $selectedQuarter = $request->get('quarter', 'Q4');
        $selectedDivisionId = $request->get('division_id', $userDivisionId);
        $status = $request->get('status', 'all') ?: 'all';
        $documentNumber = $request->get('document_number');
        $staffId = (int) $request->get('staff_id');
        $memoType = $request->get('memo_type');
        $parentMemoId = $request->get('parent_memo_id');

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

        if ($status && $status !== 'all') {
            $baseQuery->where('overall_status', $status);
        }

        // Filter by memo type (parent_memo_model)
        if ($memoType) {
            $baseQuery->where('parent_memo_model', $memoType);
        }

        // Filter by parent memo ID (to show change requests for a specific parent memo)
        if ($parentMemoId) {
            $baseQuery->where('parent_memo_id', (int) $parentMemoId);
        }

        // Filter by division (only if explicitly set in URL)
        if ($request->filled('division_id') && !$showAllDueToNoSession) {
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
        if ($showAllDueToNoSession) {
            $myChangeRequests = $myChangeRequestsQuery->paginate(20)->withQueryString();
        } else {
        $myChangeRequests = $myChangeRequestsQuery->where('staff_id', $userStaffId)->paginate(20)->withQueryString();
        }

        // My Division Change Requests (change requests in user's division)
        $myDivisionChangeRequestsQuery = clone $baseQuery;
        if ($showAllDueToNoSession) {
            $myDivisionChangeRequests = $myDivisionChangeRequestsQuery->paginate(20)->withQueryString();
        } else {
        $myDivisionChangeRequests = $myDivisionChangeRequestsQuery->where('division_id', $userDivisionId)->paginate(20)->withQueryString();
        }

        // Shared Change Requests (where user is responsible person)
        $sharedChangeRequestsQuery = clone $baseQuery;
        if ($showAllDueToNoSession) {
            $sharedChangeRequests = $sharedChangeRequestsQuery->paginate(20)->withQueryString();
        } else {
        $sharedChangeRequests = $sharedChangeRequestsQuery->where('responsible_person_id', $userStaffId)->paginate(20)->withQueryString();
        }

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
    public function pendingApprovals(Request $request): View|JsonResponse
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
        ->where('overall_status', 'pending')
        ->where('forward_workflow_id', '!=', null)
        ->where('approval_level', '>', 0);

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
     * Show the form for editing a change request.
     * Redirects to the parent memo edit form with change_request parameters.
     */
    public function edit(ChangeRequest $changeRequest): RedirectResponse
    {
        // Check if change request is in draft or rejected status
        $allowedStatuses = [ChangeRequest::STATUS_DRAFT, ChangeRequest::STATUS_REJECTED];
        if (!in_array($changeRequest->overall_status, $allowedStatuses)) {
            return redirect()
                ->route('change-requests.show', $changeRequest)
                ->with('error', 'Only draft or rejected change requests can be edited.');
        }

        // Check if current user is the owner or responsible person
        $userStaffId = user_session('staff_id');
        $isOwner = $changeRequest->staff_id == $userStaffId;
        $isResponsiblePerson = $changeRequest->responsible_person_id == $userStaffId;
        
        if (!$isOwner && !$isResponsiblePerson) {
            return redirect()
                ->route('change-requests.show', $changeRequest)
                ->with('error', 'You are not authorized to edit this change request.');
        }

        // Get the parent memo
        $parentMemo = $this->getParentMemo($changeRequest->parent_memo_model, $changeRequest->parent_memo_id);
        
        if (!$parentMemo) {
            return redirect()
                ->route('change-requests.show', $changeRequest)
                ->with('error', 'Parent memo not found.');
        }

        // Determine the edit route based on parent memo type
        $editUrl = null;
        
        if ($parentMemo instanceof Activity) {
            // For activities, check if it's a single memo
            if ($parentMemo->is_single_memo) {
                $editUrl = route('activities.single-memos.edit', [
                    'matrix' => $parentMemo->matrix_id ?? 1,
                    'activity' => $parentMemo->id
                ]);
            } else {
                $editUrl = route('matrices.activities.edit', [
                    'matrix' => $parentMemo->matrix_id ?? 1,
                    'activity' => $parentMemo->id
                ]);
            }
        } elseif ($parentMemo instanceof NonTravelMemo) {
            $editUrl = route('non-travel.edit', $parentMemo->id);
        } elseif ($parentMemo instanceof SpecialMemo) {
            $editUrl = route('special-memo.edit', $parentMemo->id);
        } elseif ($parentMemo instanceof RequestArf) {
            $editUrl = route('request-arf.edit', $parentMemo->id);
        } elseif ($parentMemo instanceof ServiceRequest) {
            $editUrl = route('service-request.edit', $parentMemo->id);
        }

        if (!$editUrl) {
            return redirect()
                ->route('change-requests.show', $changeRequest)
                ->with('error', 'Unable to determine edit route for parent memo type.');
        }

        // Redirect to parent memo edit form with change request parameters
        $redirectUrl = $editUrl . '?change_request=1&change_request_id=' . $changeRequest->id;
        return redirect($redirectUrl);
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
        // Exclude Expired and Separated staff from dropdown
        $staff = Staff::whereNotIn('status', ['Expired', 'Separated'])
            ->orderBy('fname')->get();

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
                // Check if this is an update (editing existing change request)
                $changeRequestId = $request->input('change_request_id');
                $isUpdate = !empty($changeRequestId);
                $changeRequest = null;
                
                if ($isUpdate) {
                    $changeRequest = ChangeRequest::findOrFail($changeRequestId);
                    
                    // Check if change request is in draft or rejected status
                    $allowedStatuses = [ChangeRequest::STATUS_DRAFT, ChangeRequest::STATUS_REJECTED];
                    if (!in_array($changeRequest->overall_status, $allowedStatuses)) {
                        throw new \Exception('Only draft or rejected change requests can be updated.');
                    }
                    
                    // Check if current user is the owner or responsible person
                    $isOwner = $changeRequest->staff_id == $userStaffId;
                    $isResponsiblePerson = $changeRequest->responsible_person_id == $userStaffId;
                    
                    if (!$isOwner && !$isResponsiblePerson) {
                        throw new \Exception('You are not authorized to update this change request.');
                    }
                    
                    // Use the change request's parent memo info
                    $parentMemo = $this->getParentMemo($changeRequest->parent_memo_model, $changeRequest->parent_memo_id);
                } else {
                    // Validate required fields for new change request
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
                }
                
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

                // Prepare change request data
                $changeRequestData = [
                    'parent_memo_id' => $isUpdate ? $changeRequest->parent_memo_id : (int) $request->parent_memo_id,
                    'parent_memo_model' => $isUpdate ? $changeRequest->parent_memo_model : $request->parent_memo_model,
                    'activity_id' => $parentMemo instanceof Activity ? (int) $parentMemo->id : null,
                    'special_memo_id' => $parentMemo instanceof SpecialMemo ? (int) $parentMemo->id : null,
                    'non_travel_memo_id' => $parentMemo instanceof NonTravelMemo ? (int) $parentMemo->id : null,
                    'request_arf_id' => $parentMemo instanceof RequestArf ? (int) $parentMemo->id : null,
                    'service_request_id' => $parentMemo instanceof ServiceRequest ? (int) $parentMemo->id : null,
                    
                    // Change tracking flags
                    'has_budget_id_changed' => $changes['budget_id'],
                    'has_internal_participants_changed' => $changes['internal_participants'],
                    'has_number_of_participants_changed' => $changes['number_of_participants'],
                    'has_participant_days_changed' => $changes['participant_days'],
                    'has_request_type_id_changed' => $changes['request_type_id'],
                    'has_total_external_participants_changed' => $changes['total_external_participants'],
                    'has_location_changed' => $changes['location'],
                    'has_memo_date_changed' => $changes['memo_date'],
                    'has_date_stayed_quarter' => $changes['date_stayed_quarter'],
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
                    'supporting_reasons' => clean_unicode($request->input('supporting_reasons')),
                    'date_from' => $request->input('date_from', $parentMemo->date_from ?? now()->toDateString()),
                    'date_to' => $request->input('date_to', $parentMemo->date_to ?? now()->toDateString()),
                    'memo_date' => $request->input('memo_date', $parentMemo->memo_date ?? now()->toDateString()),
                    'location_id' => json_encode($request->input('location_id', [])),
                    'total_participants' => (int) $request->input('total_participants', 1),
                    'internal_participants' => json_encode($internalParticipants),
                    'total_external_participants' => (int) $request->input('total_external_participants', 0),
                    'division_staff_request' => $parentMemo->division_staff_request ?? null,
                    'budget_id' => json_encode($budgetCodes),
                    'key_result_area' => clean_unicode($request->input('key_result_area', $parentMemo->key_result_area ?? '')),
                    'non_travel_memo_category_id' => $parentMemo->non_travel_memo_category_id ?? null,
                    'request_type_id' => (int) $request->input('request_type_id', $parentMemo->request_type_id ?? 1),
                    'activity_title' => clean_unicode($request->input('activity_title')),
                    'background' => clean_unicode($request->input('background', $parentMemo->background ?? '')),
                    'activity_request_remarks' => clean_unicode($request->input('activity_request_remarks', $parentMemo->activity_request_remarks ?? '')),
                    'is_single_memo' => $request->input('is_single_memo', $parentMemo->is_single_memo ?? false),
                    'budget_breakdown' => json_encode($budgetItems),
                    'available_budget' => $totalBudget,
                    'attachment' => json_encode($attachments),
                    
                    // Status fields - preserve status if updating
                    'status' => $isUpdate ? $changeRequest->status : ChangeRequest::STATUS_DRAFT,
                    'fund_type_id' => $request->input('fund_type', $parentMemo->fund_type_id ?? 1),
                    'activity_ref' => $parentMemo->activity_ref ?? null,
                    'approval_level' => $isUpdate ? $changeRequest->approval_level : 0,
                    'next_approval_level' => $isUpdate ? $changeRequest->next_approval_level : null,
                    'overall_status' => $isUpdate ? $changeRequest->overall_status : ChangeRequest::STATUS_DRAFT,
                ];

                // Create or update the change request
                if ($isUpdate) {
                    $changeRequest->update($changeRequestData);
                    Log::info('Change request updated', ['change_request' => $changeRequest]);
                    $successMessage = 'Change request updated successfully.';
                } else {
                    $changeRequest = ChangeRequest::create($changeRequestData);
                Log::info('Change request created', ['change_request' => $changeRequest]);
                $successMessage = 'Change request created successfully.';
                }

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

        // Get the parent memo explicitly if not loaded
        $parentMemo = $changeRequest->parentMemo ?? $this->getParentMemo(
            $changeRequest->parent_memo_model,
            $changeRequest->parent_memo_id
        );

        // Load relationships for parent memo if it exists
        if ($parentMemo) {
            $parentMemo->load(['requestType', 'fundType']);
        }

        return view('change-requests.show', [
            'changeRequest' => $changeRequest,
            'parentMemo' => $parentMemo
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
            'date_stayed_quarter' => false,
            'activity_title' => false,
            'activity_request_remarks' => false,
            'is_single_memo' => false,
            'budget_breakdown' => false,
            'status' => false,
            'fund_type_id' => false,
            'number_of_participants' => false,
            'participant_days' => false,
        ];

        // 1. Check if new internal participant has been added but total participants unchanged
        $changes['internal_participants'] = $this->detectInternalParticipantsChange($parentMemo, $request);

        // 2. Check if total external participants changed (compare parent memo total vs incoming external participants)
        $changes['total_external_participants'] = $this->detectExternalParticipantsChange($parentMemo, $request);

        // 3. Check if memo dates changed and lie within the same quarter (calendar year)
        $dateChangeResult = $this->detectMemoDateChange($parentMemo, $request);
        $changes['memo_date'] = $dateChangeResult['date_changed'];
        $changes['date_stayed_quarter'] = $dateChangeResult['stayed_same_quarter'];
        
        // Debug: Log date comparison details
        Log::info('Date change detection debug', [
            'parent_date_from' => $parentMemo->date_from,
            'parent_date_to' => $parentMemo->date_to,
            'parent_memo_date' => $parentMemo->memo_date,
            'request_date_from' => $request->input('date_from'),
            'request_date_to' => $request->input('date_to'),
            'request_memo_date' => $request->input('memo_date'),
            'is_non_travel' => $parentMemo instanceof NonTravelMemo,
            'date_changed' => $dateChangeResult['date_changed'],
            'stayed_same_quarter' => $dateChangeResult['stayed_same_quarter']
        ]);

        // 4. Check if budget has changed (compare budget breakdown JSON fields)
        $changes['budget_breakdown'] = $this->detectBudgetChange($parentMemo, $request);

        // 5. Check if number of participants changed
        $changes['number_of_participants'] = $this->detectNumberOfParticipantsChange($parentMemo, $request);

        // 6. Check if participant days changed
        $changes['participant_days'] = $this->detectParticipantDaysChange($parentMemo, $request);

        // Other existing checks
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

        // Location change detection - compare unique location IDs only
        $parentLocations = is_array($parentMemo->location_id) ? $parentMemo->location_id : json_decode($parentMemo->location_id ?? '[]', true);
        $requestLocations = $request->input('location_id', []);
        
        // Convert to unique arrays and sort for comparison
        $parentUniqueLocations = array_unique(array_map('intval', $parentLocations));
        $requestUniqueLocations = array_unique(array_map('intval', $requestLocations));
        
        sort($parentUniqueLocations);
        sort($requestUniqueLocations);
        
        if ($parentUniqueLocations !== $requestUniqueLocations) {
            $changes['location'] = true;
        }

        // Activity request remarks change
        if ($parentMemo->activity_request_remarks !== $request->input('activity_request_remarks')) {
            $changes['activity_request_remarks'] = true;
        }

        // Single memo change
        if ($parentMemo->is_single_memo != $request->input('is_single_memo')) {
            $changes['is_single_memo'] = true;
        }

       // dd($changes);

        return $changes;
    }

    /**
     * Detect if internal participants have changed
     * Only checks if participants are added or removed (by ID), not their details
     * 
     * IMPORTANT: This method ONLY compares participant IDs (staff_id).
     * It does NOT consider:
     * - participant_start (start date)
     * - participant_end (end date)
     * - participant_days (number of days)
     * - international_travel (travel flag)
     * 
     * Changes to these fields are tracked separately via:
     * - detectParticipantDaysChange() for days
     * - Other change flags for other participant details
     */
    private function detectInternalParticipantsChange($parentMemo, Request $request): bool
    {
        // Get parent memo internal participants
        $parentParticipants = $parentMemo->internal_participants ?? [];
        if (is_string($parentParticipants)) {
            $parentParticipants = json_decode($parentParticipants, true) ?? [];
        }
        
        // Ensure we have an array
        if (!is_array($parentParticipants)) {
            $parentParticipants = [];
        }

        // Get request internal participants - only get the participant IDs (keys)
        // The keys of participant_start array are the staff IDs
        $participantStarts = $request->input('participant_start', []);

        // Ensure we have an array
        if (!is_array($participantStarts)) {
            $participantStarts = [];
        }

        // Extract ONLY participant IDs (staff_id) from both arrays
        // Convert to integers and ensure uniqueness
        $parentParticipantIds = array_unique(array_map('intval', array_keys($parentParticipants)));
        $requestParticipantIds = array_unique(array_map('intval', array_keys($participantStarts)));
        
        // Sort arrays for consistent comparison
        sort($parentParticipantIds);
        sort($requestParticipantIds);
        
        // Only check if participant IDs have changed (added or removed)
        // This returns true if:
        // - A participant was added (ID exists in request but not in parent)
        // - A participant was removed (ID exists in parent but not in request)
        // This returns false if:
        // - Same participants (even if their dates/days/travel flags changed)
        return $parentParticipantIds !== $requestParticipantIds;
    }

    /**
     * Normalize participant details for consistent comparison
     * Handles data type differences between database and form data
     */
    private function normalizeParticipantDetails($details): array
    {
        if (!is_array($details)) {
            return [];
        }

        return [
            'participant_start' => (string) ($details['participant_start'] ?? ''),
            'participant_end' => (string) ($details['participant_end'] ?? ''),
            'participant_days' => (string) ($details['participant_days'] ?? ''),
            'international_travel' => (int) ($details['international_travel'] ?? 0)
        ];
    }

    /**
     * Detect if total external participants have changed
     * Compares total participants from parent memo against incoming external participants
     */
    private function detectExternalParticipantsChange($parentMemo, Request $request): bool
    {
        $parentTotalParticipants = (int) ($parentMemo->total_participants ?? 0);
        $requestTotalParticipants = (int) $request->input('total_participants', 0);
        $requestExternalParticipants = (int) $request->input('total_external_participants', 0);

        // Calculate internal participants from request
        $participantStarts = $request->input('participant_start', []);
        $requestInternalCount = count($participantStarts);

        // Check if external participants changed
        $parentInternalParticipants = $parentMemo->internal_participants ?? [];
        if (is_string($parentInternalParticipants)) {
            $parentInternalParticipants = json_decode($parentInternalParticipants, true) ?? [];
        }
        $parentInternalCount = count($parentInternalParticipants);
        $parentExternalParticipants = $parentTotalParticipants - $parentInternalCount;
        
        return $parentExternalParticipants !== $requestExternalParticipants;
    }

    /**
     * Detect if memo dates have changed and lie within the same quarter
     * Uses calendar year quarters - focuses on date_to for travel memos, memo_date for non-travel
     * Returns array with both 'date_changed' and 'stayed_same_quarter' flags
     */
    private function detectMemoDateChange($parentMemo, Request $request): array
    {
        // Normalize dates to Y-m-d format for consistent comparison
        $parentDateFrom = $this->normalizeDate($parentMemo->date_from);
        $parentDateTo = $this->normalizeDate($parentMemo->date_to);
        $parentMemoDate = $this->normalizeDate($parentMemo->memo_date);
        $requestDateFrom = $this->normalizeDate($request->input('date_from'));
        $requestDateTo = $this->normalizeDate($request->input('date_to'));
        $requestMemoDate = $this->normalizeDate($request->input('memo_date'));

        // Check if this is a non-travel memo
        $isNonTravel = $parentMemo instanceof NonTravelMemo;

        if ($isNonTravel) {
            // For non-travel memos, use memo_date
            if ($parentMemoDate === $requestMemoDate) {
                return [
                    'date_changed' => false,
                    'stayed_same_quarter' => false
                ]; // No change
            }

            // If memo_date changed, check if they lie within the same quarter
            if ($requestMemoDate) {
                $parentQuarter = $this->getQuarterFromDate($parentMemoDate);
                $requestQuarter = $this->getQuarterFromDate($requestMemoDate);
                
                // If quarters are the same, date stayed in the same quarter
                $stayedSameQuarter = $parentQuarter === $requestQuarter;
                
                return [
                    'date_changed' => true,
                    'stayed_same_quarter' => $stayedSameQuarter
                ];
            }
        } else {
            // For travel memos, use date_to
            if ($parentDateFrom === $requestDateFrom && $parentDateTo === $requestDateTo) {
                return [
                    'date_changed' => false,
                    'stayed_same_quarter' => false
                ]; // No change
            }

            // If dates have changed, check if they lie within the same quarter using date_to
            if ($requestDateTo) {
                $parentQuarter = $this->getQuarterFromDate($parentDateTo);
                $requestQuarter = $this->getQuarterFromDate($requestDateTo);
                
                // If quarters are the same, date stayed in the same quarter
                $stayedSameQuarter = $parentQuarter === $requestQuarter;
                
                return [
                    'date_changed' => true,
                    'stayed_same_quarter' => $stayedSameQuarter
                ];
            }
        }

        return [
            'date_changed' => true,
            'stayed_same_quarter' => false
        ]; // Dates changed but couldn't determine quarter
    }

    /**
     * Normalize date to timestamp (int) for easier comparison
     */
    private function normalizeDate($date): ?int
    {
        if (!$date) {
            return null;
        }

        try {
            // Handle Carbon objects
            if ($date instanceof \Carbon\Carbon) {
                return $date->timestamp;
            }

            // Handle string dates
            if (is_string($date)) {
                return \Carbon\Carbon::parse($date)->timestamp;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Detect if budget has changed by comparing budget breakdown JSON fields
     * Handles NonTravelMemo, SpecialMemo, and Matrix memo JSON storage properly
     */
    private function detectBudgetChange($parentMemo, Request $request): bool
    {
        // Get parent memo budget breakdown
        $parentBudget = $parentMemo->budget_breakdown ?? [];
        
        // Handle different JSON storage formats
        if (is_string($parentBudget)) {
            // Try to decode JSON string
            $decoded = json_decode($parentBudget, true);
            if (is_string($decoded)) {
                // Handle double-encoded JSON (sometimes happens)
                $decoded = json_decode($decoded, true);
            }
            $parentBudget = is_array($decoded) ? $decoded : [];
        }

        // Get request budget breakdown
        $requestBudget = $request->input('budget', []);

        // Check if this is a Special Memo or Matrix memo (different structure)
        $isSpecialOrMatrixMemo = $parentMemo instanceof SpecialMemo || 
                                ($parentMemo instanceof Activity && $parentMemo->matrix_id);

        if ($isSpecialOrMatrixMemo) {
            return $this->detectSpecialMemoBudgetChange($parentBudget, $requestBudget, $parentMemo, $request);
        } else {
            // Handle NonTravelMemo format
            return $this->detectNonTravelMemoBudgetChange($parentBudget, $requestBudget);
        }
    }

    /**
     * Detect budget changes for Special Memo and Matrix memo format
     * Structure: {"75": [{"cost": "Tickets", "unit_cost": "1000", "units": "26", "days": "1", "description": "..."}], "grand_total": "83729.00"}
     */
    private function detectSpecialMemoBudgetChange($parentBudget, $requestBudget, $parentMemo, $request): bool
    {
        // Check budget_id changes (fund codes)
        $parentBudgetIds = $parentMemo->budget_id ?? [];
        if (is_string($parentBudgetIds)) {
            $parentBudgetIds = json_decode($parentBudgetIds, true) ?? [];
        }
        $requestBudgetIds = $request->input('budget_codes', []);
        
        if (json_encode($parentBudgetIds) !== json_encode($requestBudgetIds)) {
            return true; // Budget codes changed
        }

        // Check budget breakdown structure
        $parentNormalized = $this->normalizeSpecialMemoBudget($parentBudget);
        $requestNormalized = $this->normalizeSpecialMemoBudget($requestBudget);

        // Compare normalized structures
        return json_encode($parentNormalized) !== json_encode($requestNormalized);
    }

    /**
     * Detect budget changes for NonTravelMemo format
     */
    private function detectNonTravelMemoBudgetChange($parentBudget, $requestBudget): bool
    {
        $parentNormalized = $this->normalizeNonTravelMemoBudget($parentBudget);
        $requestNormalized = $this->normalizeNonTravelMemoBudget($requestBudget);

        return json_encode($parentNormalized) !== json_encode($requestNormalized);
    }

    /**
     * Normalize Special Memo budget array for consistent comparison
     * Handles structure: {"75": [{"cost": "Tickets", "unit_cost": "1000", "units": "26", "days": "1", "description": "..."}], "grand_total": "83729.00"}
     */
    private function normalizeSpecialMemoBudget($budget): array
    {
        if (!is_array($budget)) {
            return [];
        }

        $normalized = [];
        foreach ($budget as $codeId => $items) {
            if ($codeId === 'grand_total') {
                $normalized['grand_total'] = floatval($items);
                continue;
            }

            if (is_array($items)) {
                $normalized[$codeId] = [];
                foreach ($items as $item) {
                    if (is_array($item)) {
                        // Normalize Special Memo budget item structure
                        $normalizedItem = [
                            'cost' => $item['cost'] ?? '',
                            'unit_cost' => floatval($item['unit_cost'] ?? 0),
                            'units' => floatval($item['units'] ?? 1),
                            'days' => floatval($item['days'] ?? 1),
                            'description' => $item['description'] ?? ''
                        ];
                        $normalized[$codeId][] = $normalizedItem;
                    }
                }
            }
        }

        return $normalized;
    }

    /**
     * Normalize NonTravelMemo budget array for consistent comparison
     */
    private function normalizeNonTravelMemoBudget($budget): array
    {
        if (!is_array($budget)) {
            return [];
        }

        $normalized = [];
        foreach ($budget as $codeId => $items) {
            if (is_array($items)) {
                $normalized[$codeId] = [];
                foreach ($items as $item) {
                    if (is_array($item)) {
                        // Normalize NonTravelMemo budget item structure
                        $normalizedItem = [
                            'description' => $item['description'] ?? '',
                            'units' => floatval($item['units'] ?? 1),
                            'unit_cost' => floatval($item['unit_cost'] ?? 0),
                            'total' => floatval($item['total'] ?? 0)
                        ];
                        $normalized[$codeId][] = $normalizedItem;
                    }
                }
            }
        }

        return $normalized;
    }

    /**
     * Detect if number of participants changed
     */
    private function detectNumberOfParticipantsChange($parentMemo, Request $request): bool
    {
        // Get parent memo participants
        $parentParticipants = $parentMemo->internal_participants ?? [];
        if (is_string($parentParticipants)) {
            $parentParticipants = json_decode($parentParticipants, true) ?? [];
        }

        // Get request participants
        $participantStarts = $request->input('participant_start', []);
        $requestParticipants = is_array($participantStarts) ? array_keys($participantStarts) : [];
        
        $parentCount = count($parentParticipants);
        $requestCount = count($requestParticipants);

        return $parentCount !== $requestCount;
    }

    /**
     * Detect if participant days changed
     */
    private function detectParticipantDaysChange($parentMemo, Request $request): bool
    {
        // Get parent memo participants
        $parentParticipants = $parentMemo->internal_participants ?? [];
        if (is_string($parentParticipants)) {
            $parentParticipants = json_decode($parentParticipants, true) ?? [];
        }

        // Get request participants
        $participantDays = $request->input('participant_days', []);

        // Compare total days for each participant
        $parentTotalDays = 0;
        $requestTotalDays = 0;

        foreach ($parentParticipants as $staffId => $details) {
            $parentTotalDays += (int) ($details['participant_days'] ?? 0);
        }

        foreach ($participantDays as $staffId => $days) {
            $requestTotalDays += (int) $days;
        }

        return $parentTotalDays !== $requestTotalDays;
    }

    /**
     * Get quarter from date (calendar year)
     * Accepts both timestamps (int) and date strings
     */
    private function getQuarterFromDate($date): string
    {
        if (!$date) {
            return 'Q1';
        }

        // If it's already a timestamp (int), use it directly
        if (is_int($date)) {
            $month = (int) date('n', $date);
        } else {
            // Otherwise parse as string
            $month = (int) date('n', strtotime($date));
        }
        
        if ($month >= 1 && $month <= 3) {
            return 'Q1';
        } elseif ($month >= 4 && $month <= 6) {
            return 'Q2';
        } elseif ($month >= 7 && $month <= 9) {
            return 'Q3';
        } else {
            return 'Q4';
        }
    }

    /**
     * Remove the specified change request from storage.
     */
    public function destroy(ChangeRequest $changeRequest): RedirectResponse|JsonResponse
    {
        try {
            $userStaffId = user_session('staff_id');

            // Check if change request is in draft or returned status
            $allowedStatuses = [ChangeRequest::STATUS_DRAFT, ChangeRequest::STATUS_REJECTED];
            if (!in_array($changeRequest->overall_status, $allowedStatuses)) {
                $message = 'Only draft or returned change requests can be deleted.';
                
                if (request()->ajax()) {
                    return response()->json([
                        'success' => false,
                        'msg' => $message
                    ], 422);
                }

                return redirect()->back()->with([
                    'msg' => $message,
                    'type' => 'error'
                ]);
            }

            // Check if current user is the owner (staff_id) or the responsible person
            $isOwner = $changeRequest->staff_id == $userStaffId;
            $isResponsiblePerson = $changeRequest->responsible_person_id == $userStaffId;
            
            if (!$isOwner && !$isResponsiblePerson) {
                $message = 'You are not authorized to delete this change request. Only the owner or responsible person can delete it.';
                
                if (request()->ajax()) {
                    return response()->json([
                        'success' => false,
                        'msg' => $message
                    ], 403);
                }

                return redirect()->back()->with([
                    'msg' => $message,
                    'type' => 'error'
                ]);
            }

            // Delete the change request
            $changeRequest->delete();

            $message = 'Change request deleted successfully.';
            
            if (request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'msg' => $message
                ]);
            }

            return redirect()->route('change-requests.index')->with([
                'msg' => $message,
                'type' => 'success'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting change request', [
                'change_request_id' => $changeRequest->id,
                'exception' => $e
            ]);

            $message = 'Failed to delete change request: ' . $e->getMessage();
            
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'msg' => $message
                ], 500);
            }

            return redirect()->back()->with([
                'msg' => $message,
                'type' => 'error'
            ]);
        }
    }

    /**
     * Update change request approval status.
     */
    public function updateStatus(Request $request, ChangeRequest $changeRequest): RedirectResponse
    {
        $request->validate([
            'action' => 'required|in:approved,rejected,returned,cancelled',
            'comment' => 'nullable|string|max:1000',
            'remarks' => 'nullable|string|max:1000',
            'available_budget' => 'nullable|numeric|min:0'
        ]);

        // Use the generic approval system
        $genericController = app(\App\Http\Controllers\GenericApprovalController::class);
        return $genericController->updateStatus($request, 'ChangeRequest', $changeRequest->id);
    }

    /**
     * Submit change request for approval with workflow assignment based on change type.
     */
    public function submitForApproval(ChangeRequest $changeRequest): RedirectResponse
    {
        // Check if change request is in draft or returned status
        if (!in_array($changeRequest->overall_status, ['draft', 'returned'])) {
            return redirect()->back()->with([
                'msg' => 'Only draft or returned change requests can be submitted for approval.',
                'type' => 'error'
            ]);
        }

        // Check if user is authorized to submit
        $userStaffId = user_session('staff_id');
        $isOwner = $changeRequest->staff_id == $userStaffId;
        $isDivisionHead = $changeRequest->division && $changeRequest->division->division_head == $userStaffId;
        
        if (!$isOwner && !$isDivisionHead) {
            return redirect()->back()->with([
                'msg' => 'You are not authorized to submit this change request.',
                'type' => 'error'
            ]);
        }

        try {
            // Determine workflow ID based on change type
            $workflowId = $this->determineWorkflowId($changeRequest);
            
            // Update change request status and workflow
            $changeRequest->update([
                'overall_status' => 'pending',
                'approval_level' => 1,
                'next_approval_level' => 2,
                'forward_workflow_id' => $workflowId,
            ]);

            // Save approval trail
            if (method_exists($changeRequest, 'saveApprovalTrail')) {
                $changeRequest->saveApprovalTrail('Submitted for approval', 'submitted');
            }

            // Send notification
            if (function_exists('send_generic_email_notification')) {
                send_generic_email_notification($changeRequest, 'approval');
            }

            return redirect()->back()->with([
                'msg' => 'Change request submitted for approval successfully.',
                'type' => 'success'
            ]);

        } catch (\Exception $e) {
            Log::error('Error submitting change request for approval', [
                'change_request_id' => $changeRequest->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with([
                'msg' => 'Failed to submit change request: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }

    /**
     * Determine workflow ID based on change type.
     * 
     * Rules:
     * - Budget changes → workflow_id = 1
     * - Date change only (same quarter) → workflow_id = 6
     * - Date change (different quarter) OR/AND participant replacement → workflow_id = 7
     * - Other changes → workflow_id = 6
     */
    private function determineWorkflowId(ChangeRequest $changeRequest): int
    {
        // Check for budget changes
        $hasBudgetChanges = $changeRequest->has_budget_id_changed || $changeRequest->has_budget_breakdown_changed;
        if ($hasBudgetChanges) {
            return 1;
        }

        // Check for participant changes
        $hasParticipantChanges = $changeRequest->has_internal_participants_changed || 
                                $changeRequest->has_number_of_participants_changed || 
                                $changeRequest->has_participant_days_changed || 
                                $changeRequest->has_total_external_participants_changed;

        // Check for date changes
        $hasDateChanges = $changeRequest->has_memo_date_changed;
        $dateStayedInQuarter = $changeRequest->has_date_stayed_quarter;

        // Date change only (same quarter) → workflow_id = 6
        if ($hasDateChanges && $dateStayedInQuarter && !$hasParticipantChanges) {
            return 6;
        }

        // Date change (different quarter) OR/AND participant replacement → workflow_id = 7
        if (($hasDateChanges && !$dateStayedInQuarter) || $hasParticipantChanges) {
            return 7;
        }

        // Default workflow (for other changes) → workflow_id = 6
        return 6;
    }

    /**
     * Resubmit a returned change request for approval.
     */
    public function resubmit(Request $request, ChangeRequest $changeRequest): RedirectResponse
    {
        $request->validate([
            'comment' => 'nullable|string|max:1000'
        ]);

        // Check if the change request is in the correct status for resubmission
        if (!in_array($changeRequest->overall_status, ['returned', 'pending'])) {
            return redirect()->back()->with([
                'msg' => 'Only returned or pending change requests can be resubmitted.',
                'type' => 'error'
            ]);
        }

        // Check authorization - division head or owner
        $userStaffId = user_session('staff_id');
        $isDivisionHead = $changeRequest->division && $changeRequest->division->division_head == $userStaffId;
        $isOwner = $changeRequest->staff_id == $userStaffId;
        
        if (!$isDivisionHead && !$isOwner) {
            return redirect()->back()->with([
                'msg' => 'You are not authorized to resubmit this change request.',
                'type' => 'error'
            ]);
        }

        // Check if change request is at the correct level for resubmission (0 or 1)
        if ($changeRequest->approval_level > 1) {
            return redirect()->back()->with([
                'msg' => 'Change request must be at the correct level to be resubmitted.',
                'type' => 'error'
            ]);
        }

        try {
            // Handle resubmission based on current level
            if ($changeRequest->approval_level == 0) {
                // Change request was returned to creator - resubmit to first approver (level 1)
                $changeRequest->approval_level = 1;
                $changeRequest->next_approval_level = 2;
                // Keep the existing workflow ID
                if (!$changeRequest->forward_workflow_id) {
                    $changeRequest->forward_workflow_id = $this->determineWorkflowId($changeRequest);
                }
                $changeRequest->overall_status = 'pending';
                $changeRequest->save();
            } else {
                // Change request was returned by an approver - resubmit to that approver
                $lastApprovalTrail = \App\Models\ApprovalTrail::where('model_id', $changeRequest->id)
                    ->where('model_type', 'App\\Models\\ChangeRequest')
                    ->where('action', 'returned')
                    ->orderBy('created_at', 'desc')
                    ->first();

                if (!$lastApprovalTrail) {
                    return redirect()->back()->with([
                        'msg' => 'Could not find the approver who returned this change request.',
                        'type' => 'error'
                    ]);
                }

                // Set the change request back to the approver who returned it
                $changeRequest->approval_level = $lastApprovalTrail->approval_order;
                $changeRequest->next_approval_level = $lastApprovalTrail->approval_order + 1;
                if ($lastApprovalTrail->forward_workflow_id) {
                    $changeRequest->forward_workflow_id = $lastApprovalTrail->forward_workflow_id;
                }
                $changeRequest->overall_status = 'pending';
                $changeRequest->save();
            }

            // Create a new approval trail for the resubmission
            $comment = $request->input('comment', 'Resubmitted for approval');
            if (method_exists($changeRequest, 'saveApprovalTrail')) {
                $changeRequest->saveApprovalTrail($comment, 'submitted');
            }

            // Send notification
            if (function_exists('send_generic_email_notification')) {
                send_generic_email_notification($changeRequest, 'approval');
            }

            return redirect()->back()->with([
                'msg' => 'Change request resubmitted for approval successfully.',
                'type' => 'success'
            ]);

        } catch (\Exception $e) {
            Log::error('Error resubmitting change request', [
                'change_request_id' => $changeRequest->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with([
                'msg' => 'Failed to resubmit change request: ' . $e->getMessage(),
                'type' => 'error'
            ]);
        }
    }

    /**
     * Print change request as PDF
     */
    public function print(ChangeRequest $changeRequest)
    {
        // Eager load needed relations
        $changeRequest->load([
            'staff',
            'responsiblePerson',
            'division',
            'requestType',
            'fundType',
            'parentMemo',
            'forwardWorkflow',
            'approvalTrails.staff',
            'approvalTrails.workflowDefinition'
        ]);

        // Get the parent memo explicitly
        $parentMemo = $changeRequest->parentMemo ?? $this->getParentMemo(
            $changeRequest->parent_memo_model,
            $changeRequest->parent_memo_id
        );

        // Generate parent memo HTML based on type
        $parentPdfHtml = null;
        if ($parentMemo) {
            $parentPdfHtml = $this->generateParentMemoHtml($parentMemo, $changeRequest->parent_memo_model);
        }

        // Get workflow information for change request
        $workflowInfo = $this->getComprehensiveWorkflowInfo($changeRequest);
        $organizedWorkflowSteps = \App\Helpers\PrintHelper::organizeWorkflowStepsBySection($workflowInfo['workflow_steps'] ?? []);

        // Get list of changes
        $changes = $this->getChangesList($changeRequest, $parentMemo);

        // Use mPDF helper function for change request
        $print = false;
        $pdf = mpdf_print('change-requests.print', [
            'changeRequest' => $changeRequest,
            'parentMemo' => $parentMemo,
            'parentPdfHtml' => $parentPdfHtml,
            'changes' => $changes,
            'organized_workflow_steps' => $organizedWorkflowSteps,
        ], ['preview_html' => $print]);

        // Generate filename
        $filename = ($changeRequest->has_budget_id_changed || $changeRequest->has_budget_breakdown_changed ? 'Addendum' : 'Change_Request') . '_' . ($changeRequest->document_number ?? $changeRequest->id) . '_' . now()->format('Y-m-d') . '.pdf';

        // Return PDF for display in browser using mPDF Output method
        return response($pdf->Output($filename, 'I'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"'
        ]);
    }

    /**
     * Generate parent memo HTML based on model type
     */
    private function generateParentMemoHtml($parentMemo, string $parentMemoModel)
    {
        // Use ServiceRequestController methods as reference
        $serviceRequestController = app(\App\Http\Controllers\ServiceRequestController::class);
        
        if ($parentMemoModel === 'App\Models\Activity') {
            if ($parentMemo->matrix) {
                return $serviceRequestController->generateActivityMemoHtml($parentMemo->matrix, $parentMemo);
            }
        } elseif ($parentMemoModel === 'App\Models\SpecialMemo') {
            return $serviceRequestController->generateSpecialMemoHtml($parentMemo);
        } elseif ($parentMemoModel === 'App\Models\NonTravelMemo') {
            return $serviceRequestController->generateNonTravelMemoHtml($parentMemo);
        }
        
        return null;
    }

    /**
     * Get comprehensive workflow information for change request
     */
    private function getComprehensiveWorkflowInfo(ChangeRequest $changeRequest)
    {
        $workflowInfo = [
            'current_level' => null,
            'workflow_steps' => []
        ];

        if (!$changeRequest->forward_workflow_id) {
            return $workflowInfo;
        }

        // Get workflow definitions
        $workflowDefinitions = \App\Models\WorkflowDefinition::where('workflow_id', $changeRequest->forward_workflow_id)
            ->where('is_enabled', 1)
            ->orderBy('approval_order', 'asc')
            ->with(['approvers.staff'])
            ->get();

        $workflowSteps = [];
        foreach ($workflowDefinitions as $def) {
            $step = [
                'level' => $def->approval_order,
                'role' => $def->role,
                'is_current' => $def->approval_order == $changeRequest->approval_level,
                'is_completed' => $changeRequest->approval_level > $def->approval_order,
                'approvers' => []
            ];

            // Get approvers for this level
            foreach ($def->approvers as $approver) {
                if ($approver->staff) {
                    $step['approvers'][] = [
                        'name' => $approver->staff->fname . ' ' . $approver->staff->lname,
                        'title' => $approver->staff->job_name ?? '',
                    ];
                }
            }

            $workflowSteps[] = $step;
        }

        $workflowInfo['current_level'] = $changeRequest->approval_level;
        $workflowInfo['workflow_steps'] = $workflowSteps;

        return $workflowInfo;
    }

    /**
     * Get list of changes made in the change request
     */
    private function getChangesList(ChangeRequest $changeRequest, $parentMemo): array
    {
        $changes = [];

        if ($changeRequest->has_activity_title_changed) {
            $changes[] = [
                'type' => 'Activity Title',
                'original' => $parentMemo->activity_title ?? $parentMemo->title ?? 'N/A',
                'changed' => $changeRequest->activity_title ?? 'N/A'
            ];
        }

        if ($changeRequest->has_memo_date_changed || $changeRequest->has_date_stayed_quarter) {
            $originalDate = 'N/A';
            $changedDate = 'N/A';
            
            // Check if it's a non-travel memo to determine which date field to use
            $isNonTravel = $changeRequest->parent_memo_model === 'App\Models\NonTravelMemo';
            
            if ($parentMemo) {
                if ($isNonTravel && isset($parentMemo->memo_date)) {
                    // Non-travel memo uses memo_date
                    $originalDate = \Carbon\Carbon::parse($parentMemo->memo_date)->format('M d, Y');
                } elseif (!$isNonTravel && isset($parentMemo->date_from) && isset($parentMemo->date_to)) {
                    // Other memos use date_from and date_to
                    $originalDate = \Carbon\Carbon::parse($parentMemo->date_from)->format('M d, Y') . ' - ' . \Carbon\Carbon::parse($parentMemo->date_to)->format('M d, Y');
                }
            }
            
            // Get changed date from change request - check the same way
            if ($isNonTravel && $changeRequest->memo_date) {
                $changedDate = \Carbon\Carbon::parse($changeRequest->memo_date)->format('M d, Y');
            } elseif (!$isNonTravel && $changeRequest->date_from && $changeRequest->date_to) {
                $changedDate = \Carbon\Carbon::parse($changeRequest->date_from)->format('M d, Y') . ' - ' . \Carbon\Carbon::parse($changeRequest->date_to)->format('M d, Y');
            }
            
            $changes[] = [
                'type' => $isNonTravel ? 'Memo Date' : 'Date Range',
                'original' => $originalDate,
                'changed' => $changedDate
            ];
        }

        if ($changeRequest->has_location_changed) {
            // Get location names
            $originalLocation = 'N/A';
            $changedLocation = 'N/A';
            
            if ($parentMemo) {
                $parentLocationIds = $parentMemo->location_id ?? [];
                if (is_string($parentLocationIds)) {
                    $parentLocationIds = json_decode($parentLocationIds, true) ?? [];
                }
                if (is_array($parentLocationIds) && !empty($parentLocationIds)) {
                    $locations = \App\Models\Location::whereIn('id', $parentLocationIds)->pluck('location_name')->toArray();
                    $originalLocation = implode(', ', $locations);
                }
            }
            
            $changedLocationIds = $changeRequest->location_id ?? [];
            if (is_string($changedLocationIds)) {
                $changedLocationIds = json_decode($changedLocationIds, true) ?? [];
            }
            if (is_array($changedLocationIds) && !empty($changedLocationIds)) {
                $locations = \App\Models\Location::whereIn('id', $changedLocationIds)->pluck('location_name')->toArray();
                $changedLocation = implode(', ', $locations);
            }
            
            $changes[] = [
                'type' => 'Location',
                'original' => $originalLocation,
                'changed' => $changedLocation
            ];
        }

        if ($changeRequest->has_internal_participants_changed || $changeRequest->has_participant_days_changed) {
            // Count participants
            $originalCount = 0;
            $changedCount = 0;
            
            if ($parentMemo) {
                $parentParticipants = $parentMemo->internal_participants ?? [];
                if (is_string($parentParticipants)) {
                    $parentParticipants = json_decode($parentParticipants, true) ?? [];
                }
                if (is_string($parentParticipants)) {
                    $parentParticipants = json_decode($parentParticipants, true) ?? [];
                }
                $originalCount = is_array($parentParticipants) ? count($parentParticipants) : 0;
            }
            
            // Get participants from change request table directly
            $rawParticipants = DB::table('change_request')->where('id', $changeRequest->id)->value('internal_participants');
            $changedParticipants = [];
            if ($rawParticipants) {
                if (is_string($rawParticipants)) {
                    $firstDecode = json_decode($rawParticipants, true);
                    if (is_string($firstDecode)) {
                        $changedParticipants = json_decode($firstDecode, true) ?? [];
                    } elseif (is_array($firstDecode)) {
                        $changedParticipants = $firstDecode;
                    }
                } elseif (is_array($rawParticipants)) {
                    $changedParticipants = $rawParticipants;
                }
            }
            $changedCount = is_array($changedParticipants) ? count($changedParticipants) : 0;
            
            $changes[] = [
                'type' => 'Internal Participants',
                'original' => $originalCount . ' participant(s)',
                'changed' => $changedCount . ' participant(s)'
            ];
        }

        if ($changeRequest->has_total_external_participants_changed) {
            $changes[] = [
                'type' => 'External Participants',
                'original' => ($parentMemo->total_external_participants ?? 0) . ' participant(s)',
                'changed' => ($changeRequest->total_external_participants ?? 0) . ' participant(s)'
            ];
        }

        if ($changeRequest->has_number_of_participants_changed) {
            $originalCount = 0;
            if ($parentMemo) {
                $parentParticipants = $parentMemo->internal_participants ?? [];
                if (is_string($parentParticipants)) {
                    $parentParticipants = json_decode($parentParticipants, true) ?? [];
                }
                if (is_string($parentParticipants)) {
                    $parentParticipants = json_decode($parentParticipants, true) ?? [];
                }
                $originalCount = is_array($parentParticipants) ? count($parentParticipants) : 0;
            }
            
            $rawParticipants = DB::table('change_request')->where('id', $changeRequest->id)->value('internal_participants');
            $changedParticipants = [];
            if ($rawParticipants) {
                if (is_string($rawParticipants)) {
                    $firstDecode = json_decode($rawParticipants, true);
                    if (is_string($firstDecode)) {
                        $changedParticipants = json_decode($firstDecode, true) ?? [];
                    } elseif (is_array($firstDecode)) {
                        $changedParticipants = $firstDecode;
                    }
                } elseif (is_array($rawParticipants)) {
                    $changedParticipants = $rawParticipants;
                }
            }
            $changedCount = is_array($changedParticipants) ? count($changedParticipants) : 0;
            
            $changes[] = [
                'type' => 'Number of Participants',
                'original' => $originalCount,
                'changed' => $changedCount
            ];
        }

        if ($changeRequest->has_budget_id_changed || $changeRequest->has_budget_breakdown_changed) {
            // Get budget totals
            $originalTotal = 0;
            $changedTotal = 0;
            
            if ($parentMemo) {
                $parentBudget = $parentMemo->budget_breakdown ?? [];
                if (is_string($parentBudget)) {
                    $parentBudget = json_decode($parentBudget, true) ?? [];
                }
                $originalTotal = $parentBudget['grand_total'] ?? 0;
            }
            
            $changedBudget = $changeRequest->budget_breakdown ?? [];
            if (is_string($changedBudget)) {
                $changedBudget = json_decode($changedBudget, true) ?? [];
            }
            $changedTotal = $changedBudget['grand_total'] ?? 0;
            
            $changes[] = [
                'type' => 'Budget',
                'original' => 'KES ' . number_format($originalTotal, 2),
                'changed' => 'KES ' . number_format($changedTotal, 2)
            ];
        }

        if ($changeRequest->has_activity_request_remarks_changed && !empty($changeRequest->activity_request_remarks)) {
            $changedRemarks = strip_tags($changeRequest->activity_request_remarks ?? '');
            
            // Truncate if too long
            if (strlen($changedRemarks) > 200) {
                $changedRemarks = substr($changedRemarks, 0, 200) . '...';
            }
            
            $changes[] = [
                'type' => 'Request for Approval',
                'original' => '', // Don't display original remarks
                'changed' => $changedRemarks
            ];
        }

        return $changes;
    }
}