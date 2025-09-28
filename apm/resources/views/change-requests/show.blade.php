@extends('layouts.app')

@section('title', 'Change Request Details')

@section('header', 'Change Request Details')

@section('content')
<div class="card shadow-sm border-0">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">
            <i class="fas fa-edit me-2"></i>
            Change Request: {{ $changeRequest->activity_title }}
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-primary">Basic Information</h6>
                <table class="table table-borderless">
                    <tr>
                        <td><strong>Document Number:</strong></td>
                        <td>
                            @if($changeRequest->document_number)
                                <span class="badge bg-primary">{{ $changeRequest->document_number }}</span>
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
                            @if($changeRequest->parentMemo)
                                <span class="badge bg-info">{{ class_basename($changeRequest->parent_memo_model) }}</span>
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
                        <td>{{ $changeRequest->staff->fname }} {{ $changeRequest->staff->lname }}</td>
                    </tr>
                    <tr>
                        <td><strong>Date Range:</strong></td>
                        <td>
                            {{ \Carbon\Carbon::parse($changeRequest->date_from)->format('M d, Y') }} - 
                            {{ \Carbon\Carbon::parse($changeRequest->date_to)->format('M d, Y') }}
                        </td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-primary">Changes Made</h6>
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

        @if($changeRequest->supporting_reasons)
            <div class="mt-4">
                <h6 class="text-primary">Supporting Reasons</h6>
                <div class="bg-light p-3 rounded">
                    {{ $changeRequest->supporting_reasons }}
                </div>
            </div>
        @endif

        @if($changeRequest->activity_request_remarks)
            <div class="mt-4">
                <h6 class="text-primary">Activity Remarks</h6>
                <div class="bg-light p-3 rounded">
                    {{ $changeRequest->activity_request_remarks }}
                </div>
            </div>
        @endif

        <div class="mt-4">
            <h6 class="text-primary">Actions</h6>
            <div class="btn-group" role="group">
                <a href="{{ route('change-requests.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to List
                </a>
                @if($changeRequest->overall_status === 'draft')
                    <a href="{{ route('change-requests.edit', $changeRequest) }}" class="btn btn-outline-warning">
                        <i class="fas fa-edit me-1"></i> Edit
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
