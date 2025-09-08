@extends('layouts.app')

@section('title', $title)

@section('header', $title)

@section('styles')
<style>
    .matrix-card {
        background: #fff;
        border-radius: 1.25rem;
        box-shadow: 0 4px 24px rgba(17,154,72,0.08);
        border: none;
    }
    .matrix-card .card-header {
        border-radius: 1.25rem 1.25rem 0 0;
        background: linear-gradient(90deg, #e9f7ef 0%, #fff 100%);
        border-bottom: 1px solid #e9f7ef;
    }
    .matrix-card .card-body {
        border-radius: 0 0 1.25rem 1.25rem;
    }
    
    .approval-level-badge {
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        font-weight: 600;
        font-size: 0.875rem;
    }
    
    .status-badge {
        font-size: 0.875rem;
        font-weight: 600;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        text-transform: capitalize;
    }
    
    .status-approved { background: #d1fae5; color: #059669; border: 1px solid #a7f3d0; }
    .status-rejected { background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; }
    .status-pending { background: #fef3c7; color: #d97706; border: 1px solid #fcd34d; }
    .status-draft { background: #f3f4f6; color: #6b7280; border: 1px solid #d1d5db; }
    .status-returned { background: #dbeafe; color: #2563eb; border: 1px solid #93c5fd; }
    
    .gradient-header {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    }
    
    /* Timeline Styles */
    .timeline {
        position: relative;
        margin: 0;
        padding: 0;
        list-style: none;
        max-height: 50vh;
        overflow-y: auto;
    }
    .timeline:before {
        content: '';
        position: absolute;
        left: 30px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e9ecef;
    }
    .timeline-item {
        position: relative;
        margin-bottom: 30px;
        padding-left: 60px;
    }
    .timeline-item:last-child {
        margin-bottom: 0;
    }
    .timeline-badge {
        position: absolute;
        left: 18px;
        top: 0;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: #fff;
        border: 2px solid #28a745;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
    }
    .timeline-badge.approved {
        border-color: #28a745;
        color: #28a745;
    }
    .timeline-badge.rejected {
        border-color: #dc3545;
        color: #dc3545;
    }
    .timeline-badge.returned {
        border-color: rgb(217, 136, 15);
        color: rgb(208, 149, 12);
    }
    .timeline-badge.submitted {
        border-color: rgb(17, 166, 211);
        color: rgb(27, 143, 216);
    }
    .timeline-time {
        font-size: 0.9rem;
        color: #888;
        margin-bottom: 2px;
    }
    .timeline-title {
        font-weight: 600;
        margin-bottom: 2px;
    }
    .timeline-remarks {
        color: #555;
        font-size: 0.95rem;
    }
    
    /* Hover effects */
    .card:hover {
        transform: translateY(-2px);
        transition: all 0.3s ease;
    }
    
    /* Button hover effects */
    .btn:hover {
        transform: translateY(-1px);
        transition: all 0.2s ease;
    }
</style>
@endsection

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('activities.single-memos.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to List
    </a>
    
    @if($activity->overall_status === 'draft' && $activity->staff_id === user_session('staff_id'))
        <a href="{{ route('activities.single-memos.edit', $activity) }}" class="btn btn-warning">
            <i class="bx bx-edit"></i> Edit
        </a>
    @endif
    
</div>
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            
            <!-- Metadata Section -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">{{ $activity->activity_title }}</h5>
                    <small class="text-muted">Single Memo Ref: {{ $activity->activity_ref }}</small>
                    @if($activity->my_last_action)
                        <p> Your Action: <small class=" text-white rounded p-1 {{($activity->my_last_action->action=='passed')?'bg-success':'bg-danger'}}">{{strtoupper($activity->my_last_action->action)}}</small></p>
                    @endif
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Background:</strong>
                            <div class="html-content">{!! $activity->background !!}</div>
                        </div>
                        <div class="col-md-6">
                            <strong>Key Result Area:</strong>
                            <p>{{ $activity->matrix->key_result_area[intval($activity->key_result_area)]['description'] ?? '' }}</p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Status: </strong>
                            <span class="badge {{ $activity->status_badge_class }}">{{ ucwords($activity->overall_status) }}</span>
                        </div>
                        <div class="col-md-4">
                            <strong>Request Type: </strong>{{ $activity->requestType->name ?? '-' }}
                        </div>
                        <div class="col-md-4">
                            <strong>Fund Type: </strong>{{ $activity->fundType->name ?? '-' }}
                        </div>
                    </div>

                    @if($activity->overall_status != 'approved')
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <strong>Current Approval Level: </strong>{{ $activity->approval_level_display }}
                        </div>
                        <div class="col-md-8"></div>
                    </div>
                    @endif

                    @if($activity->overall_status == 'draft' && $activity->staff_id == user_session('staff_id'))
                        <div class="mt-3">
                        <form action="{{ route('activities.single-memos.submit-for-approval', $activity) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="bx bx-send me-1"></i>Submit for Approval
                            </button>
                        </form>
                        </div>
                    @endif
                </div>
            </div>


            <!-- Main Content -->
            <div class="row">
                <div class="col-md-8">
                    <!-- Basic Information -->
                    <div class="card matrix-card mb-4">
                        <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                            <h6 class="m-0 fw-semibold text-primary"><i class="bx bx-info-circle me-2"></i>Basic Information</h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Activity Title</label>
                                    <p class="mb-0">{{ $activity->activity_title }}</p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Request Type </label>
                                    <p class="mb-0">{{ $activity->requestType->name ?? 'N/A' }}</p>
                                </div>
                            </div>
                            

                            <div class=" row mb-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Fund Type</label>
                                <p class="mb-0">{{ $activity->fundType->fund_type_name ?? 'N/A' }}</p>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Key Result Area</label>
                                <p class="mb-0">{{ $activity->key_result_area ?: 'N/A' }}</p>
                            </div>

                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Start Date</label>
                                    <p class="mb-0">{{ $activity->date_from->format('F d, Y') }}</p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">End Date</label>
                                    <p class="mb-0">{{ $activity->date_to->format('F d, Y') }}</p>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Total Participants</label>
                                    <p class="mb-0">{{ $activity->total_participants }}</p>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">External Participants</label>
                                    <p class="mb-0">{{ $activity->total_external_participants }}</p>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Background</label>
                                <div class="html-content mb-0">{!! $activity->background ?: 'No background provided' !!}</div>
                            </div>
                            
                            @if($activity->activity_request_remarks)
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Remarks</label>
                                <div class="html-content mb-0">{!! $activity->activity_request_remarks !!}</div>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Participants -->
                    @if(count($internalParticipants) > 0)
                    <div class="card matrix-card mb-4">
                        <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                            <h6 class="m-0 fw-semibold text-info"><i class="bx bx-users me-2"></i>Internal Participants</h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Division</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Days</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($internalParticipants as $participant)
                                        <tr>
                                            <td>{{ $participant['staff']->fname }} {{ $participant['staff']->lname }}</td>
                                            <td>{{ $participant['staff']->division_name }}</td>
                                            <td>{{ $participant['participant_start'] ? \Carbon\Carbon::parse($participant['participant_start'])->format('M d, Y') : 'N/A' }}</td>
                                            <td>{{ $participant['participant_end'] ? \Carbon\Carbon::parse($participant['participant_end'])->format('M d, Y') : 'N/A' }}</td>
                                            <td>{{ $participant['participant_days'] ?? 'N/A' }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Budget Information -->
                    @if($activity->activity_budget && count($activity->activity_budget) > 0)
                    <div class="card matrix-card mb-4">
                        <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                            <h6 class="m-0 fw-semibold text-success"><i class="bx bx-money me-2"></i>Budget Information</h6>
                        </div>
                        <div class="card-body p-4">
                            @php
                                // Parse budget structure and organize by fund codes
                                $budgetByFundCode = [];
                                $totalBudget = 0;
                                
                                if (!empty($budgetItems)) {
                                    foreach ($budgetItems as $key => $item) {
                                        if ($key === 'grand_total') {
                                            $totalBudget = floatval($item);
                                        } elseif (is_array($item)) {
                                            // Handle array of budget items (like "29" => [{...}])
                                            $fundCodeId = $key;
                                            $budgetByFundCode[$fundCodeId] = $item;
                                        } elseif (is_numeric($item)) {
                                            $totalBudget += floatval($item);
                                        }
                                    }
                                }
                                
                                // If no grand_total found, calculate from items
                                if ($totalBudget == 0 && !empty($budgetByFundCode)) {
                                    foreach ($budgetByFundCode as $fundCodeId => $items) {
                                        foreach ($items as $item) {
                                            if (isset($item['unit_cost']) && isset($item['units'])) {
                                                $totalBudget += floatval($item['unit_cost']) * floatval($item['units']);
                                            }
                                        }
                                    }
                                }
                            @endphp
                            
                            @if(!empty($budgetByFundCode))
                                @foreach($budgetByFundCode as $fundCodeId => $items)
                                    @php
                                        $fundCode = $fundCodes->where('id', $fundCodeId)->first();
                                        $fundCodeTotal = 0;
                                        foreach ($items as $item) {
                                            if (isset($item['unit_cost']) && isset($item['units'])) {
                                                $fundCodeTotal += floatval($item['unit_cost']) * floatval($item['units']);
                                            }
                                        }
                                    @endphp
                                    
                                    <div class="mb-4">
                                        <div class="d-flex justify-content-between align-items-center mb-3 p-3" 
                                             style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border: 1px solid #bae6fd; border-radius: 0.5rem;">
                                            <div>
                                                <h6 class="mb-1 fw-bold text-primary">
                                                    @if($fundCode)
                                                        Fund Code: {{ $fundCode->fund_code }}
                                                    @else
                                                        Fund Code ID: {{ $fundCodeId }}
                                                    @endif
                                                </h6>
                                                @if($fundCode && $fundCode->fundType)
                                                    <div class="small text-muted">
                                                        Type: {{ $fundCode->fundType->fund_type_name ?? 'N/A' }}
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="text-end">
                                                <span class="fw-bold text-success fs-6">${{ number_format($fundCodeTotal, 2) }}</span>
                                                <small class="text-muted d-block">Fund Total</small>
                                            </div>
                                        </div>
                                        
                            <div class="table-responsive">
                                            <table class="table table-sm table-bordered">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Cost Item</th>
                                                        <th>Description</th>
                                                        <th>Unit Cost</th>
                                                        <th>Units</th>
                                                        <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                                    @foreach($items as $item)
                                                        @php
                                                            $itemTotal = 0;
                                                            if (isset($item['unit_cost']) && isset($item['units'])) {
                                                                $itemTotal = floatval($item['unit_cost']) * floatval($item['units']);
                                                            }
                                                        @endphp
                                                        <tr>
                                                            <td>{{ $item['cost'] ?? 'N/A' }}</td>
                                                            <td>{{ $item['description'] ?? 'N/A' }}</td>
                                                            <td class="text-end">${{ number_format(floatval($item['unit_cost'] ?? 0), 2) }}</td>
                                                            <td class="text-end">{{ $item['units'] ?? 'N/A' }}</td>
                                                            <td class="text-end fw-bold">${{ number_format($itemTotal, 2) }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                                    </div>
                                @endforeach
                                
                                <!-- Grand Total Row -->
                                @if($totalBudget > 0)
                                    <div class="mt-4 p-3 rounded" style="background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0 fw-bold">Grand Total (All Fund Codes)</h6>
                                            <span class="fw-bold fs-5">${{ number_format($totalBudget, 2) }}</span>
                                        </div>
                                    </div>
                                @endif
                            @else
                                <!-- Fallback: Show budget as key-value pairs if structure is different -->
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Budget Item</th>
                                                <th>Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($budgetItems as $key => $value)
                                                @if($key !== 'grand_total')
                                                    <tr>
                                                        <td>{{ $key }}</td>
                                                        <td>
                                                            @if(is_array($value))
                                                                <pre class="mb-0">{{ json_encode($value, JSON_PRETTY_PRINT) }}</pre>
                                                            @elseif(is_numeric($value))
                                                                ${{ number_format(floatval($value), 2) }}
                                                            @else
                                                                {{ $value }}
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endif
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                    @endif

                    <!-- Attachments -->
                    @if(count($attachments) > 0)
                    <div class="card matrix-card mb-4">
                        <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                            <h6 class="m-0 fw-semibold text-warning"><i class="bx bx-paperclip me-2"></i>Attachments</h6>
                        </div>
                        <div class="card-body p-4">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Type</th>
                                            <th>File Name</th>
                                            <th>Size</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($attachments as $index => $attachment)
                                        @php
                                            $ext = strtolower(pathinfo($attachment['original_name'] ?? $attachment['filename'] ?? '', PATHINFO_EXTENSION));
                                            $fileUrl = url('storage/'.($attachment['path'] ?? $attachment['file_path'] ?? ''));
                                            $isOffice = in_array($ext, ['ppt','pptx','xls','xlsx','doc','docx']);
                                        @endphp
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $attachment['type'] ?? 'Document' }}</td>
                                            <td>{{ $attachment['original_name'] ?? $attachment['filename'] ?? $attachment['name'] ?? 'Unknown' }}</td>
                                            <td>{{ isset($attachment['size']) ? round($attachment['size']/1024, 2).' KB' : 'N/A' }}</td>
                                            <td>
                                                @if($attachment['path'] ?? $attachment['file_path'])
                                                    @if($isOffice)
                                                        <a href="{{ $fileUrl }}" class="btn btn-sm btn-outline-primary" target="_blank">
                                                            <i class="bx bx-download"></i> Download
                                                        </a>
                                                    @else
                                                        <a href="{{ $fileUrl }}" class="btn btn-sm btn-outline-info" target="_blank">
                                                            <i class="bx bx-show"></i> View
                                                        </a>
                                                    @endif
                                                @else
                                                    <span class="text-muted">File not available</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    @endif

                    <!-- Current Supervisor Information -->
                    @if($activity->overall_status !== 'approved' && $activity->overall_status !== 'rejected' && $activity->current_actor)
                        <div class="card matrix-card mb-4" style="border-left: 4px solid #3b82f6;">
                            <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                                <h6 class="m-0 fw-semibold text-primary"><i class="bx bx-user-check me-2"></i>Current Approver Information</h6>
                            </div>
                            <div class="card-body p-4">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <strong class="text-muted">Name:</strong> 
                                            <span class="fw-bold text-primary fs-5">{{ $activity->current_actor->fname . ' ' . $activity->current_actor->lname }}</span>
                                        </div>
                                        @if($activity->current_actor->job_name)
                                            <div class="mb-3">
                                                <strong class="text-muted">Job Title:</strong> 
                                                <span class="fw-bold">{{ $activity->current_actor->job_name }}</span>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="col-md-6">
                                        @if($activity->current_actor->division_name)
                                            <div class="mb-3">
                                                <strong class="text-muted">Division:</strong> 
                                                <span class="fw-bold">{{ $activity->current_actor->division_name }}</span>
                                            </div>
                                        @endif
                                        @if($activity->workflow_definition)
                                            <div class="mb-3">
                                                <strong class="text-muted">Approval Role:</strong> 
                                                <span class="badge bg-info">{{ $activity->workflow_definition->role ?? 'Not specified' }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="mt-3 p-3 bg-primary bg-opacity-10 rounded">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bx bx-info-circle text-primary"></i>
                                        <span class="text-primary fw-medium">This single memo is currently awaiting approval from the supervisor above.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Enhanced Approval Actions -->
                    @php
                        $approvalService = app(\App\Services\ApprovalService::class);
                        $canTakeAction = $approvalService->canTakeAction($activity, user_session('staff_id'));
                        
                        // Debug information
                        $debugInfo = [
                            'activity_status' => $activity->overall_status,
                            'approval_level' => $activity->approval_level,
                            'forward_workflow_id' => $activity->forward_workflow_id,
                            'user_staff_id' => user_session('staff_id'),
                            'can_take_action' => $canTakeAction
                        ];
                    @endphp
                    
                    {{-- Debug info (remove in production) --}}
                    @if(config('app.debug'))
                    <div class="alert alert-info mb-3">
                        <small><strong>Debug Info:</strong> {{ json_encode($debugInfo) }}</small>
                    </div>
                    @endif
                    
                    @if($canTakeAction)
                        <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
                            <div class="card-header bg-transparent border-0 py-3">
                                <h6 class="mb-0 fw-bold text-success d-flex align-items-center gap-2">
                                    <i class="bx bx-check-circle"></i>
                                    Approval Actions - Level {{ $activity->approval_level ?? 0 }}
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info mb-3">
                                    <i class="bx bx-info-circle me-2"></i>
                                    <strong>Current Level:</strong> {{ $activity->approval_level ?? 0 }}
                                    @if($activity->workflow_definition)
                                        - <strong>Role:</strong> {{ $activity->workflow_definition->role ?? 'Not specified' }}
                                    @endif
                                    @if($activity->current_actor)
                                        <br><strong>Name:</strong> {{ $activity->current_actor->fname . ' ' . $activity->current_actor->lname }}
                                        @if($activity->current_actor->job_name)
                                            ({{ $activity->current_actor->job_name }})
                                        @endif
                                    @endif
                                </div>
                                
                                <form action="{{ route('activities.single-memos.update-status', $activity) }}" method="POST" id="singleMemoApprovalForm">
                                    @csrf
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="mb-3">
                                                <label for="comment" class="form-label">Comments (Optional)</label>
                                                <textarea class="form-control" id="comment" name="comment" rows="3" placeholder="Add any comments about your decision..."></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="d-grid gap-2">
                                                <button type="submit" name="action" value="approved" class="btn btn-success w-100 d-flex align-items-center justify-content-center gap-2">
                                                    <i class="bx bx-check"></i>
                                                    Approve
                                                </button>
                                                <button type="submit" name="action" value="returned" class="btn btn-warning w-100 d-flex align-items-center justify-content-center gap-2">
                                                    <i class="bx bx-undo"></i>
                                                    Return for Revision
                                                </button>
                                                <button type="submit" name="action" value="rejected" class="btn btn-danger w-100 d-flex align-items-center justify-content-center gap-2">
                                                    <i class="bx bx-x"></i>
                                                    Reject
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @else
                        {{-- Show why approval actions are not available --}}
                        <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);">
                            <div class="card-header bg-transparent border-0 py-3">
                                <h6 class="mb-0 fw-bold text-warning d-flex align-items-center gap-2">
                                    <i class="bx bx-info-circle"></i>
                                    Approval Status Information
                                </h6>
                            </div>
                            <div class="card-body">
                                @if($activity->overall_status === 'draft')
                                    <p class="mb-2">📝 This single memo is still in <span class="badge bg-secondary">Draft</span> status.</p>
                                    @if($activity->staff_id === user_session('staff_id'))
                                        <p class="mb-0">You can submit it for approval using the "Submit for Approval" button above.</p>
                                    @else
                                        <p class="mb-0">Only the creator can submit this memo for approval.</p>
                                    @endif
                                @elseif($activity->overall_status === 'approved')
                                    <p class="mb-0">✅ This single memo has been <span class="badge bg-success">Approved</span>. No further actions are needed.</p>
                                @elseif($activity->overall_status === 'pending')
                                    <p class="mb-0">⏳ This single memo is <span class="badge bg-info">Pending</span> approval.</p>
                                @elseif($activity->overall_status === 'returned')
                                    <p class="mb-0">🔄 This single memo has been <span class="badge bg-warning">Returned</span> for revision.</p>
                                @elseif(!$activity->forward_workflow_id)
                                    <p class="mb-0">⚠️ No approval workflow is configured for this memo.</p>
                                @else
                                    <p class="mb-0">ℹ️ You are not authorized to take approval actions on this memo at this time.</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Enhanced Submit for Approval Section -->
                    @if($activity->overall_status == 'draft' && $activity->staff_id == user_session('staff_id'))
                        <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);">
                            <div class="card-header bg-transparent border-0 py-3">
                                <h6 class="mb-0 fw-bold text-primary d-flex align-items-center gap-2">
                                    <i class="bx bx-send"></i>
                                    Submit for Approval
                                </h6>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">Ready to submit this single memo for approval?</p>
                                <form action="{{ route('activities.single-memos.submit-for-approval', $activity) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                                        <i class="bx bx-send"></i>
                                        Submit for Approval
                                    </button>
                                </form>
                                <div class="mt-3 p-3 bg-light rounded">
                                    <small class="text-muted">
                                        <i class="bx bx-info-circle me-1"></i>
                                        <strong>Note:</strong> Once submitted, you won't be able to edit this memo until it's returned for revision.
                                    </small>
                                </div>
                            </div>
                        </div>
                    @endif

                </div>

                <!-- Sidebar -->
                <div class="col-md-4">
                    <!-- Enhanced Approval Trail -->
                    @if($activity->approvalTrails && $activity->approvalTrails->count() > 0)
                                @include('partials.approval-trail', ['resource' => $activity])
                    @else
                        <div class="card matrix-card mb-4">
                            <div class="card-header bg-opacity-10 d-flex align-items-center rounded-top">
                                <h6 class="m-0 fw-semibold text-warning"><i class="bx bx-history me-2"></i>Approval Trail</h6>
                            </div>
                            <div class="card-body p-4">
                                <div class="text-center text-muted py-4">
                                    <i class="bx bx-time bx-lg mb-3"></i>
                                    <p class="mb-0">No approval actions have been taken yet.</p>
                                    @if($activity->overall_status == 'draft')
                                        <small>Submit this single memo for approval to start the approval trail.</small>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
