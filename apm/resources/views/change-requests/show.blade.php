@extends('layouts.app')

@section('title', 'Change Request Details')

@section('header', 'Change Request Details')

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0">
            <i class="fas fa-edit me-2"></i>
            Change Request: {{ $changeRequest->activity_title }}
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-success">Basic Information</h6>
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Document Number:</strong></td>
                        <td>
                            @if($changeRequest->document_number)
                                <span class="badge bg-success">{{ $changeRequest->document_number }}</span>
                            @else
                                <span class="text-muted">Pending Assignment</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td>
                            @switch($changeRequest->overall_status)
                                @case('draft')
                                    <span class="badge bg-secondary">Draft</span>
                                    @break
                                @case('submitted')
                                    <span class="badge bg-warning text-dark">Submitted</span>
                                    @break
                                @case('approved')
                                    <span class="badge bg-success">Approved</span>
                                    @break
                                @case('rejected')
                                    <span class="badge bg-danger">Rejected</span>
                                    @break
                                @default
                                    <span class="badge bg-light text-dark">{{ ucfirst($changeRequest->overall_status) }}</span>
                            @endswitch
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Parent Memo:</strong></td>
                        <td>
                            @if($parentMemo)
                                <span class="badge bg-success">{{ class_basename($changeRequest->parent_memo_model) }}</span>
                                <small class="text-muted">#{{ $changeRequest->parent_memo_id }}</small>
                            @else
                                <span class="text-muted">N/A</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Division:</strong></td>
                        <td>{{ $changeRequest->division->division_name ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Submitted By:</strong></td>
                        <td>{{ $changeRequest->staff ? $changeRequest->staff->fname . ' ' . $changeRequest->staff->lname : 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Date Range:</strong></td>
                        <td>
                            @if($changeRequest->date_from && $changeRequest->date_to)
                            {{ \Carbon\Carbon::parse($changeRequest->date_from)->format('M d, Y') }} - 
                            {{ \Carbon\Carbon::parse($changeRequest->date_to)->format('M d, Y') }}
                            @else
                                <span class="text-muted">N/A</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-success">Changes Made</h6>
                @if($changeRequest->hasAnyChanges())
                    <div class="d-flex flex-wrap gap-2">
                        @if($changeRequest->has_budget_id_changed)
                            <span class="badge bg-warning text-dark">Budget Code</span>
                        @endif
                        @if($changeRequest->has_activity_title_changed)
                            <span class="badge bg-warning text-dark">Activity Title</span>
                        @endif
                        @if($changeRequest->has_location_changed)
                            <span class="badge bg-warning text-dark">Location</span>
                        @endif
                        @if($changeRequest->has_internal_participants_changed)
                            <span class="badge bg-warning text-dark">Participants</span>
                        @endif
                        @if($changeRequest->has_request_type_id_changed)
                            <span class="badge bg-warning text-dark">Request Type</span>
                        @endif
                        @if($changeRequest->has_fund_type_id_changed)
                            <span class="badge bg-warning text-dark">Fund Type</span>
                        @endif
                        @if($changeRequest->has_total_external_participants_changed)
                            <span class="badge bg-warning text-dark">External Participants</span>
                        @endif
                        @if($changeRequest->has_memo_date_changed)
                            <span class="badge bg-warning text-dark">Memo Date</span>
                        @endif
                        @if($changeRequest->has_date_stayed_quarter)
                            <span class="badge bg-info text-dark">Date Stayed Quarter</span>
                        @endif
                        @if($changeRequest->has_number_of_participants_changed)
                            <span class="badge bg-warning text-dark">Number of Participants</span>
                        @endif
                        @if($changeRequest->has_participant_days_changed)
                            <span class="badge bg-warning text-dark">Participant Days</span>
                        @endif
                        @if($changeRequest->has_activity_request_remarks_changed)
                            <span class="badge bg-warning text-dark">Remarks</span>
                        @endif
                        @if($changeRequest->has_is_single_memo_changed)
                            <span class="badge bg-warning text-dark">Single Memo Status</span>
                        @endif
                        @if($changeRequest->has_budget_breakdown_changed)
                            <span class="badge bg-warning text-dark">Budget Breakdown</span>
                        @endif
                        @if($changeRequest->has_status_changed)
                            <span class="badge bg-warning text-dark">Status</span>
                        @endif
                    </div>
                @else
                    <span class="text-muted">No changes detected</span>
                @endif
            </div>
        </div>

        @if($parentMemo && ($parentMemo->background || $parentMemo->activity_request_remarks))
            <div class="mt-4">
                <h6 class="text-success">Background</h6>
                <div class="bg-light p-3 rounded">
                    @if($parentMemo->background)
                        <div class="mb-3">
                            <strong>Original Background:</strong>
                            <div class="mt-1">{!! $parentMemo->background !!}</div>
                        </div>
                    @endif
                    @if($parentMemo->activity_request_remarks)
                        <div>
                            <strong>Original Activity Remarks:</strong>
                            <div class="mt-1">{!! $parentMemo->activity_request_remarks !!}</div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if($changeRequest->supporting_reasons)
            <div class="mt-4">
                <h6 class="text-success">Request for Approval</h6>
                <div class="bg-light p-3 rounded">
                    {!! $changeRequest->supporting_reasons !!}
                </div>
            </div>
        @endif

        <!-- Detailed Changes List -->
        @if($changeRequest->hasAnyChanges())
            <div class="mt-4">
                <h6 class="text-success">Detailed Changes</h6>
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            @if($changeRequest->has_activity_title_changed)
                                <div class="col-md-6 mb-3">
                                    <div class="border rounded p-3">
                                        <h6 class="text-muted mb-2 bg-light p-2 rounded"><i class="bx bx-edit me-1"></i>Activity Title</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">Original:</small>
                                                <div class="text-danger">{{ $parentMemo->activity_title ?? $parentMemo->title ?? 'N/A' }}</div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Changed to:</small>
                                                <div class="text-success">{{ $changeRequest->activity_title }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if($changeRequest->has_memo_date_changed)
                                <div class="col-md-6 mb-3">
                                    <div class="border rounded p-3">
                                        <h6 class="text-muted mb-2 bg-light p-2 rounded"><i class="bx bx-calendar me-1"></i>Memo Date</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">Original:</small>
                                                <div class="text-danger">{{ $parentMemo->memo_date ?? 'N/A' }}</div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Changed to:</small>
                                                <div class="text-success">{{ $changeRequest->memo_date ?? 'N/A' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if($changeRequest->has_location_changed)
                                <div class="col-md-6 mb-3">
                                    <div class="border rounded p-3">
                                        <h6 class="text-muted mb-2 bg-light p-2 rounded"><i class="bx bx-map me-1"></i>Locations</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">Original:</small>
                                                <div class="text-danger">
                                                    @php
                                                        $parentLocations = $parentMemo->location_id ?? [];
                                                        if (is_string($parentLocations)) {
                                                            $parentLocations = json_decode($parentLocations, true) ?? [];
                                                        }
                                                        $parentLocationNames = \App\Models\Location::whereIn('id', $parentLocations)->pluck('name')->join(', ');
                                                    @endphp
                                                    {{ $parentLocationNames ?: 'None' }}
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Changed to:</small>
                                                <div class="text-success">
                                                    @php
                                                        $currentLocations = $changeRequest->location_id ?? [];
                                                        if (is_string($currentLocations)) {
                                                            $currentLocations = json_decode($currentLocations, true) ?? [];
                                                        }
                                                        $currentLocationNames = \App\Models\Location::whereIn('id', $currentLocations)->pluck('name')->join(', ');
                                                    @endphp
                                                    {{ $currentLocationNames ?: 'None' }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if($changeRequest->has_total_external_participants_changed)
                                <div class="col-md-6 mb-3">
                                    <div class="border rounded p-3">
                                        <h6 class="text-muted mb-2 bg-light p-2 rounded"><i class="bx bx-group me-1"></i>External Participants</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">Original:</small>
                                                <div class="text-danger">{{ $parentMemo->total_external_participants ?? 0 }}</div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Changed to:</small>
                                                <div class="text-success">{{ $changeRequest->total_external_participants ?? 0 }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if($changeRequest->has_internal_participants_changed)
                                <div class="col-md-12 mb-3">
                                    <div class="border rounded p-3">
                                        <h6 class="text-muted mb-3 bg-light p-2 rounded"><i class="bx bx-user me-1"></i>Internal Participants</h6>
                                        
                                        <div class="row">
                                            <!-- Original Participants -->
                                            <div class="col-md-6">
                                                <h6 class="text-muted mb-2"><strong>Original Participants</strong></h6>
                                                @php
                                                    $parentParticipants = $parentMemo->internal_participants ?? [];
                                                    if (is_string($parentParticipants)) {
                                                        $parentParticipants = json_decode($parentParticipants, true) ?? [];
                                                    }
                                                @endphp
                                                
                                                @if(count($parentParticipants) > 0)
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-bordered">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th>Name</th>
                                                                    <th>Days</th>
                                                                    <th>Travel</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach($parentParticipants as $key => $details)
                                                                    @php
                                                                        $staffName = 'Unknown';
                                                                        $staffId = $key;
                                                                        
                                                                        // Handle different data structures
                                                                        if (isset($details['staff'])) {
                                                                            $staffName = $details['staff']->fname . ' ' . $details['staff']->lname;
                                                                        } elseif (is_numeric($key)) {
                                                                            $staff = \App\Models\Staff::find($key);
                                                                            if ($staff) {
                                                                                $staffName = $staff->fname . ' ' . $staff->lname;
                                                                            }
                                                                        }
                                                                    @endphp
                                                                    <tr>
                                                                        <td>{{ $staffName }}</td>
                                                                        <td class="text-end">{{ $details['participant_days'] ?? 'N/A' }}</td>
                                                                        <td class="text-center">
                                                                            @if($details['international_travel'] ?? 0)
                                                                                <span class="badge bg-danger">Yes</span>
                                                                            @else
                                                                                <span class="badge bg-secondary">No</span>
                                                                            @endif
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @else
                                                    <div class="text-muted">No participants</div>
                                                @endif
                                            </div>

                                            <!-- Changed Participants -->
                                            <div class="col-md-6">
                                                <h6 class="text-muted mb-2"><strong>Changed Participants</strong></h6>
                                                @php
                                                    $currentParticipants = $changeRequest->internal_participants ?? [];
                                                    if (is_string($currentParticipants)) {
                                                        $currentParticipants = json_decode($currentParticipants, true) ?? [];
                                                    }
                                                @endphp
                                                
                                                @if(count($currentParticipants) > 0)
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-bordered">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th>Name</th>
                                                                    <th>Days</th>
                                                                    <th>Travel</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach($currentParticipants as $key => $details)
                                                                    @php
                                                                        $staffName = 'Unknown';
                                                                        $staffId = $key;
                                                                        
                                                                        // Handle different data structures
                                                                        if (isset($details['staff'])) {
                                                                            $staffName = $details['staff']->fname . ' ' . $details['staff']->lname;
                                                                        } elseif (is_numeric($key)) {
                                                                            $staff = \App\Models\Staff::find($key);
                                                                            if ($staff) {
                                                                                $staffName = $staff->fname . ' ' . $staff->lname;
                                                                            }
                                                                        }
                                                                    @endphp
                                                                    <tr>
                                                                        <td>{{ $staffName }}</td>
                                                                        <td class="text-end">{{ $details['participant_days'] ?? 'N/A' }}</td>
                                                                        <td class="text-center">
                                                                            @if($details['international_travel'] ?? 0)
                                                                                <span class="badge bg-danger">Yes</span>
                                                                            @else
                                                                                <span class="badge bg-secondary">No</span>
                                                                            @endif
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @else
                                                    <div class="text-muted">No participants</div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if($changeRequest->has_number_of_participants_changed)
                                <div class="col-md-6 mb-3">
                                    <div class="border rounded p-3">
                                        <h6 class="text-muted mb-2 bg-light p-2 rounded"><i class="bx bx-user-circle me-1"></i>Number of Participants</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">Original:</small>
                                                <div class="text-danger">
                                                    @php
                                                        $parentParticipants = $parentMemo->internal_participants ?? [];
                                                        if (is_string($parentParticipants)) {
                                                            $parentParticipants = json_decode($parentParticipants, true) ?? [];
                                                        }
                                                        echo count($parentParticipants);
                                                    @endphp
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Changed to:</small>
                                                <div class="text-success">
                                                    @php
                                                        $currentParticipants = $changeRequest->internal_participants ?? [];
                                                        if (is_string($currentParticipants)) {
                                                            $currentParticipants = json_decode($currentParticipants, true) ?? [];
                                                        }
                                                        echo count($currentParticipants);
                                                    @endphp
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if($changeRequest->has_participant_days_changed)
                                <div class="col-md-6 mb-3">
                                    <div class="border rounded p-3">
                                        <h6 class="text-muted mb-2 bg-light p-2 rounded"><i class="bx bx-calendar-check me-1"></i>Participant Days</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">Original:</small>
                                                <div class="text-danger">
                                                    @php
                                                        $parentParticipants = $parentMemo->internal_participants ?? [];
                                                        if (is_string($parentParticipants)) {
                                                            $parentParticipants = json_decode($parentParticipants, true) ?? [];
                                                        }
                                                        $parentTotalDays = array_sum(array_column($parentParticipants, 'participant_days'));
                                                    @endphp
                                                    {{ $parentTotalDays }}
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Changed to:</small>
                                                <div class="text-success">
                                                    @php
                                                        $currentParticipants = $changeRequest->internal_participants ?? [];
                                                        if (is_string($currentParticipants)) {
                                                            $currentParticipants = json_decode($currentParticipants, true) ?? [];
                                                        }
                                                        $currentTotalDays = array_sum(array_column($currentParticipants, 'participant_days'));
                                                    @endphp
                                                    {{ $currentTotalDays }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if($changeRequest->has_date_stayed_quarter)
                                <div class="col-md-6 mb-3">
                                    <div class="border rounded p-3">
                                        <h6 class="text-info mb-2"><i class="bx bx-calendar me-1"></i>Date Stayed in Same Quarter</h6>
                                        <div class="text-info">
                                            <i class="bx bx-check-circle me-1"></i>Yes, the date change kept the memo within the same calendar quarter.
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if($changeRequest->has_budget_id_changed)
                                <div class="col-md-6 mb-3">
                                    <div class="border rounded p-3">
                                        <h6 class="text-muted mb-2 bg-light p-2 rounded"><i class="bx bx-money me-1"></i>Budget Code</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">Original:</small>
                                                <div class="text-danger">
                                                    @php
                                                        $parentBudgetIds = $parentMemo->budget_id ?? [];
                                                        if (is_string($parentBudgetIds)) {
                                                            $parentBudgetIds = json_decode($parentBudgetIds, true) ?? [];
                                                        }
                                                        $parentBudgetNames = \App\Models\FundCode::whereIn('id', $parentBudgetIds)->pluck('code')->join(', ');
                                                    @endphp
                                                    {{ $parentBudgetNames ?: 'None' }}
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Changed to:</small>
                                                <div class="text-success">
                                                    @php
                                                        $currentBudgetIds = $changeRequest->budget_id ?? [];
                                                        if (is_string($currentBudgetIds)) {
                                                            $currentBudgetIds = json_decode($currentBudgetIds, true) ?? [];
                                                        }
                                                        $currentBudgetNames = \App\Models\FundCode::whereIn('id', $currentBudgetIds)->pluck('code')->join(', ');
                                                    @endphp
                                                    {{ $currentBudgetNames ?: 'None' }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if($changeRequest->has_request_type_id_changed)
                                <div class="col-md-6 mb-3">
                                    <div class="border rounded p-3">
                                        <h6 class="text-muted mb-2 bg-light p-2 rounded"><i class="bx bx-file me-1"></i>Request Type</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">Original:</small>
                                                <div class="text-danger">{{ $parentMemo->requestType->name ?? 'N/A' }}</div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Changed to:</small>
                                                <div class="text-success">{{ $changeRequest->requestType->name ?? 'N/A' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if($changeRequest->has_fund_type_id_changed)
                                <div class="col-md-6 mb-3">
                                    <div class="border rounded p-3">
                                        <h6 class="text-muted mb-2 bg-light p-2 rounded"><i class="bx bx-credit-card me-1"></i>Fund Type</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">Original:</small>
                                                <div class="text-danger">{{ $parentMemo->fundType->name ?? 'N/A' }}</div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Changed to:</small>
                                                <div class="text-success">{{ $changeRequest->fundType->name ?? 'N/A' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if($changeRequest->has_activity_request_remarks_changed)
                                <div class="col-md-12 mb-3">
                                    <div class="border rounded p-3">
                                        <h6 class="text-muted mb-2 bg-light p-2 rounded"><i class="bx bx-message me-1"></i>Activity Remarks</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">Original:</small>
                                                <div class="text-danger">{{ $parentMemo->activity_request_remarks ?? 'N/A' }}</div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Changed to:</small>
                                                <div class="text-success">{{ $changeRequest->activity_request_remarks ?? 'N/A' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if($changeRequest->has_is_single_memo_changed)
                                <div class="col-md-6 mb-3">
                                    <div class="border rounded p-3">
                                        <h6 class="text-muted mb-2 bg-light p-2 rounded"><i class="bx bx-check-square me-1"></i>Single Memo Status</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">Original:</small>
                                                <div class="text-danger">{{ $parentMemo->is_single_memo ? 'Yes' : 'No' }}</div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Changed to:</small>
                                                <div class="text-success">{{ $changeRequest->is_single_memo ? 'Yes' : 'No' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if($changeRequest->has_budget_breakdown_changed)
                                <div class="col-md-12 mb-3">
                                    <div class="border rounded p-3">
                                        <h6 class="text-muted mb-3 bg-light p-2 rounded"><i class="bx bx-calculator me-1"></i>Budget Breakdown</h6>
                                        
                                        <div class="row">
                                            <!-- Original Budget -->
                                            <div class="col-md-6">
                                                <h6 class="text-muted mb-2"><strong>Original Budget</strong></h6>
                                                @php
                                                    $parentBudgetBreakdown = $parentMemo->budget_breakdown ?? [];
                                                    if (is_string($parentBudgetBreakdown)) {
                                                        $parentBudgetBreakdown = json_decode($parentBudgetBreakdown, true) ?? [];
                                                    }
                                                    $parentTotal = $parentBudgetBreakdown['grand_total'] ?? 0;
                                                    unset($parentBudgetBreakdown['grand_total']);
                                                @endphp
                                                
                                                @if(count($parentBudgetBreakdown) > 0)
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-bordered">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th>Fund Code</th>
                                                                    <th>Item</th>
                                                                    <th class="text-end">Amount</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach($parentBudgetBreakdown as $fundCodeId => $items)
                                                                    @if(is_array($items))
                                                                        @foreach($items as $item)
                                                                            @php
                                                                                $fundCode = \App\Models\FundCode::find($fundCodeId);
                                                                                $cost = $item['unit_cost'] ?? $item['cost'] ?? 0;
                                                                                $units = $item['units'] ?? $item['days'] ?? 1;
                                                                                $total = $cost * $units;
                                                                            @endphp
                                                                            <tr>
                                                                                <td>{{ $fundCode->code ?? 'N/A' }}</td>
                                                                                <td>{{ $item['cost'] ?? $item['description'] ?? 'N/A' }}</td>
                                                                                <td class="text-end">{{ number_format($total, 2) }}</td>
                                                                            </tr>
                                                                        @endforeach
                                                                    @endif
                                                                @endforeach
                                                                <tr class="table-warning">
                                                                    <th colspan="2" class="text-end">Total:</th>
                                                                    <th class="text-end">{{ number_format($parentTotal, 2) }}</th>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @else
                                                    <div class="text-muted">No budget breakdown available</div>
                                                @endif
                                            </div>

                                            <!-- Changed Budget -->
                                            <div class="col-md-6">
                                                <h6 class="text-muted mb-2"><strong>Changed Budget</strong></h6>
                                                @php
                                                    $currentBudgetBreakdown = $changeRequest->budget_breakdown ?? [];
                                                    if (is_string($currentBudgetBreakdown)) {
                                                        $currentBudgetBreakdown = json_decode($currentBudgetBreakdown, true) ?? [];
                                                    }
                                                    $currentTotal = $currentBudgetBreakdown['grand_total'] ?? 0;
                                                    unset($currentBudgetBreakdown['grand_total']);
                                                @endphp
                                                
                                                @if(count($currentBudgetBreakdown) > 0)
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-bordered">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th>Fund Code</th>
                                                                    <th>Item</th>
                                                                    <th class="text-end">Amount</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach($currentBudgetBreakdown as $fundCodeId => $items)
                                                                    @if(is_array($items))
                                                                        @foreach($items as $item)
                                                                            @php
                                                                                $fundCode = \App\Models\FundCode::find($fundCodeId);
                                                                                $cost = $item['unit_cost'] ?? $item['cost'] ?? 0;
                                                                                $units = $item['units'] ?? $item['days'] ?? 1;
                                                                                $total = $cost * $units;
                                                                            @endphp
                                                                            <tr>
                                                                                <td>{{ $fundCode->code ?? 'N/A' }}</td>
                                                                                <td>{{ $item['cost'] ?? $item['description'] ?? 'N/A' }}</td>
                                                                                <td class="text-end">{{ number_format($total, 2) }}</td>
                                                                            </tr>
                                                                        @endforeach
                                                                    @endif
                                                                @endforeach
                                                                <tr class="table-success">
                                                                    <th colspan="2" class="text-end">Total:</th>
                                                                    <th class="text-end">{{ number_format($currentTotal, 2) }}</th>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @else
                                                    <div class="text-muted">No budget breakdown available</div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif


        <div class="mt-4">
            <h6 class="text-success">Actions</h6>
            <div class="btn-group" role="group">
                <a href="{{ route('change-requests.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to List
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
