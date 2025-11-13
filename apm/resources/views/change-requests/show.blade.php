@extends('layouts.app')

@section('title', 'Change Request Details')

@section('header', 'Change Request Details')

@section('content')
@php
    // Determine if this is an Addendum based on budget changes
    $hasBudgetChanges = $changeRequest->has_budget_id_changed || $changeRequest->has_budget_breakdown_changed;
    $titlePrefix = $hasBudgetChanges ? 'Addendum' : 'Change Request';
@endphp

<div class="min-h-screen bg-gray-50">
    <!-- Enhanced Header -->
    <div class="bg-white border-b border-gray-200 shadow-sm">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center py-4">
                <div>
                    <h1 class="h2 fw-bold text-dark mb-0">{{ $titlePrefix }}: {{ $changeRequest->document_number ?? 'Draft' }}</h1>
                    <p class="text-muted mb-0">Review and manage change request details</p>
                </div>
                <div class="d-flex gap-3">
                    <a href="{{ route('change-requests.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2">
                        <i class="bx bx-arrow-back"></i>
                        <span>Back to List</span>
                    </a>
                    
                    @if($changeRequest->parent_memo_model && $changeRequest->parent_memo_id)
                        @php
                            $parentMemoDocNumber = $changeRequest->parent_memo_document_number;
                            $parentMemoUrl = $changeRequest->parent_memo_url;
                        @endphp
                        @if($parentMemoUrl && $parentMemoDocNumber)
                            <a href="{{ $parentMemoUrl }}" class="btn btn-secondary d-flex align-items-center gap-2" title="View Parent Memo: {{ $parentMemoDocNumber }}">
                                <i class="fas fa-eye"></i>
                                <span>View Parent Memo</span>
                            </a>
                        @endif
                    @endif
                    
                    @if(can_print_memo($changeRequest))
                        <a href="{{ route('change-requests.print', $changeRequest) }}" target="_blank" class="btn btn-primary d-flex align-items-center gap-2">
                            <i class="bx bx-printer"></i>
                            <span>Print PDF</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid py-4">

@if(session('msg'))
    <div class="alert alert-{{ session('type') === 'error' ? 'danger' : (session('type') === 'success' ? 'success' : 'info') }} alert-dismissible fade show" role="alert">
        {{ session('msg') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card shadow-sm border-0">
    <div class="card-header text-dark">
        <h5 class="mb-0 text-dark">
            <i class="fas fa-edit me-2 text-dark"></i>
            {{ $changeRequest->activity_title }}
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
                            @if($changeRequest->parent_memo_document_number)
                                <br>
                                <small class="text-muted">Parent Document: {{ $changeRequest->parent_memo_document_number }}</small>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Status:</strong></td>
                        <td>
                        {{-- {{ dd($parentMemo) }} --}}
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
                            @php
                                $isNonTravel = $changeRequest->parent_memo_model === 'App\Models\NonTravelMemo';
                            @endphp
                            @if($isNonTravel)
                                {{-- Non-Travel Memo: Use memo_date --}}
                                @if($changeRequest->memo_date)
                                    {{ \Carbon\Carbon::parse($changeRequest->memo_date)->format('M d, Y') }}
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            @else
                                {{-- Other Memos: Use date_from and date_to --}}
                                @if($changeRequest->date_from && $changeRequest->date_to)
                            {{ \Carbon\Carbon::parse($changeRequest->date_from)->format('M d, Y') }} - 
                            {{ \Carbon\Carbon::parse($changeRequest->date_to)->format('M d, Y') }}
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
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

        @if($changeRequest->overall_status === 'draft')
            <div class="mt-4">
                <div class="alert alert-info border-info">
                    <h6 class="alert-heading mb-3">
                        <i class="fas fa-info-circle me-2"></i>Approval Workflow Information
                    </h6>
                    @php
                        $hasBudgetChanges = $changeRequest->has_budget_id_changed || $changeRequest->has_budget_breakdown_changed;
                        $hasParticipantChanges = $changeRequest->has_internal_participants_changed || 
                                                $changeRequest->has_number_of_participants_changed || 
                                                $changeRequest->has_participant_days_changed || 
                                                $changeRequest->has_total_external_participants_changed;
                        $hasDateChanges = $changeRequest->has_memo_date_changed;
                        $dateStayedInQuarter = $changeRequest->has_date_stayed_quarter;
                    @endphp
                    
                    @if($hasBudgetChanges)
                        <div class="mb-2">
                            <strong class="text-primary"><i class="fas fa-exclamation-triangle me-1"></i>Budget Changes Detected - Addendum Required:</strong>
                            <p class="mb-0 mt-2">
                                This change request includes budget modifications and will be processed as an <strong>Addendum</strong>. 
                                It will go through <strong>all approval levels</strong> in the general workflow, following the same approval steps as the original memo with all original approval levels.
                            </p>
                        </div>
                    @elseif($hasParticipantChanges)
                        <div class="mb-2">
                            <strong class="text-primary"><i class="fas fa-users me-1"></i>Participant Changes Detected:</strong>
                            <p class="mb-0 mt-2">
                                @if($hasDateChanges && $dateStayedInQuarter)
                                    This change request includes participant modifications and date changes within the same calendar quarter.
                                @else
                                    This change request includes participant modifications.
                                @endif
                                <br>
                                <strong>Approval Workflow:</strong> <strong>Head of Division (HOD) → Executive Office</strong>
                            </p>
                        </div>
                    @elseif($hasDateChanges && $dateStayedInQuarter)
                        <div class="mb-2">
                            <strong class="text-primary"><i class="fas fa-calendar me-1"></i>Date Changes Only (Same Quarter):</strong>
                            <p class="mb-0 mt-2">
                                This change request only includes date modifications within the same calendar quarter.
                                <br>
                                <strong>Approval Workflow:</strong> <strong>Head of Division (HOD) → Director of Administration</strong>
                            </p>
                        </div>
                    @else
                        <div class="mb-2">
                            <strong class="text-primary"><i class="fas fa-info-circle me-1"></i>Other Changes:</strong>
                            <p class="mb-0 mt-2">
                                This change request will follow the standard approval workflow based on the type of changes made.
                            </p>
                        </div>
                    @endif
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
                                                <div class="text-danger">
                                                    @if($parentMemo)
                                                        {{ $parentMemo->activity_title ?? $parentMemo->title ?? 'N/A' }}
                                                    @else
                                                        N/A
                                                    @endif
                                                </div>
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
                                        @php
                                            $isNonTravel = $changeRequest->parent_memo_model === 'App\Models\NonTravelMemo';
                                        @endphp
                                        <h6 class="text-muted mb-2 bg-light p-2 rounded">
                                            <i class="bx bx-calendar me-1"></i>
                                            @if($isNonTravel)
                                                Memo Date
                                            @else
                                                Date Range
                                            @endif
                                        </h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">Original:</small>
                                                <div class="text-danger">
                                                    @if($parentMemo)
                                                        @if($isNonTravel)
                                                            {{ $parentMemo->memo_date ? \Carbon\Carbon::parse($parentMemo->memo_date)->format('M d, Y') : 'N/A' }}
                                                        @else
                                                            @if($parentMemo->date_from && $parentMemo->date_to)
                                                                {{ \Carbon\Carbon::parse($parentMemo->date_from)->format('M d, Y') }} - 
                                                                {{ \Carbon\Carbon::parse($parentMemo->date_to)->format('M d, Y') }}
                                                            @else
                                                                N/A
                                                            @endif
                                                        @endif
                                                    @else
                                                        N/A
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Changed to:</small>
                                                <div class="text-success">
                                                    @if($isNonTravel)
                                                        {{ $changeRequest->memo_date ? \Carbon\Carbon::parse($changeRequest->memo_date)->format('M d, Y') : 'N/A' }}
                                                    @else
                                                        @if($changeRequest->date_from && $changeRequest->date_to)
                                                            {{ \Carbon\Carbon::parse($changeRequest->date_from)->format('M d, Y') }} - 
                                                            {{ \Carbon\Carbon::parse($changeRequest->date_to)->format('M d, Y') }}
                                                        @else
                                                            N/A
                                                        @endif
                                                    @endif
                                                </div>
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
                                                    @if($parentMemo)
                                                        @php
                                                            $parentLocations = $parentMemo->location_id ?? [];
                                                            if (is_string($parentLocations)) {
                                                                $parentLocations = json_decode($parentLocations, true) ?? [];
                                                            }
                                                            $parentLocationNames = \App\Models\Location::whereIn('id', $parentLocations)->pluck('name')->join(', ');
                                                        @endphp
                                                        {{ $parentLocationNames ?: 'None' }}
                                                    @else
                                                        N/A
                                                    @endif
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
                                                <div class="text-danger">
                                                    @if($parentMemo)
                                                        {{ $parentMemo->total_external_participants ?? 0 }}
                                                    @else
                                                        N/A
                                                    @endif
                                                </div>
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
                                                    // Get raw JSON from parent memo database to preserve the structure with international_travel
                                                    $parentModelTable = \DB::table('information_schema.tables')
                                                        ->where('table_schema', \DB::getDatabaseName())
                                                        ->whereIn('table_name', ['activities', 'special_memo', 'non_travel_memo', 'request_arf', 'service_request'])
                                                        ->where('table_name', 'like', '%' . strtolower(class_basename($changeRequest->parent_memo_model)) . '%')
                                                        ->value('table_name');
                                                    
                                                    // Try to get raw JSON from the appropriate table
                                                    $rawParentParticipants = null;
                                                    if ($parentMemo) {
                                                        // Get the table name based on model
                                                        $modelClass = $changeRequest->parent_memo_model;
                                                        $tableName = (new $modelClass)->getTable();
                                                        $rawParentParticipants = \DB::table($tableName)->where('id', $changeRequest->parent_memo_id)->value('internal_participants');
                                                    }
                                                    
                                                    $parentParticipants = [];
                                                    
                                                    if ($rawParentParticipants) {
                                                        if (is_string($rawParentParticipants)) {
                                                            // First decode
                                                            $firstDecode = json_decode($rawParentParticipants, true);
                                                            // If result is still a string, decode again (double-encoded JSON)
                                                            if (is_string($firstDecode)) {
                                                                $parentParticipants = json_decode($firstDecode, true) ?? [];
                                                            } elseif (is_array($firstDecode)) {
                                                                $parentParticipants = $firstDecode;
                                                            }
                                                        } elseif (is_array($rawParentParticipants)) {
                                                            $parentParticipants = $rawParentParticipants;
                                                        }
                                                    } elseif ($parentMemo) {
                                                        // Fallback to model accessor
                                                        $fallbackParticipants = $parentMemo->internal_participants ?? [];
                                                        if (is_string($fallbackParticipants)) {
                                                            $firstDecode = json_decode($fallbackParticipants, true);
                                                            if (is_string($firstDecode)) {
                                                                $parentParticipants = json_decode($firstDecode, true) ?? [];
                                                            } elseif (is_array($firstDecode)) {
                                                                $parentParticipants = $firstDecode;
                                                            }
                                                        } elseif (is_array($fallbackParticipants)) {
                                                            $parentParticipants = $fallbackParticipants;
                                                        }
                                                    }
                                                    
                                                    // Ensure it's always an array
                                                    if (!is_array($parentParticipants)) {
                                                        $parentParticipants = [];
                                                    }
                                                @endphp
                                                
                                                @if(is_array($parentParticipants) && count($parentParticipants) > 0)
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
                                                                        
                                                                        // Handle international_travel: can be 1, "1", true, or "true"
                                                                        $internationalTravel = $details['international_travel'] ?? 0;
                                                                        // Convert to integer and check if it equals 1
                                                                        $hasInternationalTravel = (intval($internationalTravel) === 1);
                                                                    @endphp
                                                                    <tr>
                                                                        <td>{{ $staffName }}</td>
                                                                        <td class="text-end">{{ $details['participant_days'] ?? 'N/A' }}</td>
                                                                        <td class="text-center">
                                                                            @if($hasInternationalTravel)
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
                                                    // IMPORTANT: Get participants from CHANGE REQUEST, not parent memo
                                                    // Get raw JSON from change_request table to preserve the structure with international_travel
                                                    // Note: JSON may be double-encoded, so we decode twice if needed
                                                    $rawParticipants = \DB::table('change_request')->where('id', $changeRequest->id)->value('internal_participants');
                                                    $currentParticipants = [];
                                                    
                                                    if ($rawParticipants) {
                                                        if (is_string($rawParticipants)) {
                                                            // First decode
                                                            $firstDecode = json_decode($rawParticipants, true);
                                                            // If result is still a string, decode again (double-encoded JSON)
                                                            if (is_string($firstDecode)) {
                                                                $currentParticipants = json_decode($firstDecode, true) ?? [];
                                                            } elseif (is_array($firstDecode)) {
                                                                $currentParticipants = $firstDecode;
                                                            }
                                                        } elseif (is_array($rawParticipants)) {
                                                            $currentParticipants = $rawParticipants;
                                                        }
                                                    }
                                                    
                                                    // Ensure it's always an array
                                                    if (!is_array($currentParticipants)) {
                                                        $currentParticipants = [];
                                                    }
                                                    
                                                    // Build a map of original participants for comparison
                                                    $originalParticipantMap = [];
                                                    foreach ($parentParticipants as $key => $details) {
                                                        $originalParticipantMap[$key] = [
                                                            'days' => $details['participant_days'] ?? 0,
                                                        ];
                                                    }
                                                @endphp
                                                
                                                @if(is_array($currentParticipants) && count($currentParticipants) > 0)
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
                                                                        
                                                                        // Handle international_travel: can be 1, "1", true, or "true"
                                                                        $internationalTravel = $details['international_travel'] ?? 0;
                                                                        // Convert to integer and check if it equals 1
                                                                        $hasInternationalTravel = (intval($internationalTravel) === 1);
                                                                        
                                                                        // Check if this participant should be highlighted
                                                                        $shouldHighlight = false;
                                                                        $currentDays = (int)($details['participant_days'] ?? 0);
                                                                        
                                                                        // Check if it's a new participant (not in original)
                                                                        if (!isset($originalParticipantMap[$key])) {
                                                                            $shouldHighlight = true;
                                                                        } else {
                                                                            // Check if days have changed
                                                                            $originalDays = (int)($originalParticipantMap[$key]['days'] ?? 0);
                                                                            if ($currentDays != $originalDays) {
                                                                                $shouldHighlight = true;
                                                                            }
                                                                        }
                                                                    @endphp
                                                                    <tr @if($shouldHighlight) style="background-color: #ffe6e6;" @endif>
                                                                        <td>{{ $staffName }}</td>
                                                                        <td class="text-end">{{ $details['participant_days'] ?? 'N/A' }}</td>
                                                                        <td class="text-center">
                                                                            @if($hasInternationalTravel)
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
                                                    @if($parentMemo)
                                                        @php
                                                            $parentParticipants = $parentMemo->internal_participants ?? [];
                                                            if (is_string($parentParticipants)) {
                                                                $parentParticipants = json_decode($parentParticipants, true) ?? [];
                                                            }
                                                            echo is_array($parentParticipants) ? count($parentParticipants) : 0;
                                                        @endphp
                                                    @else
                                                        N/A
                                                    @endif
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
                                                    @if($parentMemo)
                                                        @php
                                                            $parentParticipants = $parentMemo->internal_participants ?? [];
                                                            if (is_string($parentParticipants)) {
                                                                $parentParticipants = json_decode($parentParticipants, true) ?? [];
                                                            }
                                                            $parentTotalDays = is_array($parentParticipants) ? array_sum(array_column($parentParticipants, 'participant_days')) : 0;
                                                        @endphp
                                                        {{ $parentTotalDays }}
                                                    @else
                                                        N/A
                                                    @endif
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
                                                    @if($parentMemo)
                                                        @php
                                                            $parentBudgetIds = $parentMemo->budget_id ?? [];
                                                            if (is_string($parentBudgetIds)) {
                                                                $parentBudgetIds = json_decode($parentBudgetIds, true) ?? [];
                                                            }
                                                            $parentBudgetNames = \App\Models\FundCode::whereIn('id', $parentBudgetIds)->pluck('code')->join(', ');
                                                        @endphp
                                                        {{ $parentBudgetNames ?: 'None' }}
                                                    @else
                                                        N/A
                                                    @endif
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
                                                <div class="text-danger">
                                                    @if($parentMemo)
                                                        {{ $parentMemo->requestType->name ?? 'N/A' }}
                                                    @else
                                                        N/A
                                                    @endif
                                                </div>
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
                                                <div class="text-danger">
                                                    @if($parentMemo)
                                                        {{ $parentMemo->fundType->name ?? 'N/A' }}
                                                    @else
                                                        N/A
                                                    @endif
                                                </div>
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
                                                <div class="text-danger">{!! $parentMemo->activity_request_remarks ?? 'N/A' !!}</div>
                                            </div>
                                            <div class="col-6">
                                                <small class="text-muted">Changed to:</small>
                                                <div class="text-success">{!! $changeRequest->activity_request_remarks ?? 'N/A' !!}</div>
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
                                                    
                                                    // Build a map of original budget items for comparison
                                                    // Key: fundCodeId_itemName, Value: amount
                                                    $originalBudgetMap = [];
                                                    foreach ($parentBudgetBreakdown as $fundCodeId => $items) {
                                                        if (is_array($items)) {
                                                            foreach ($items as $item) {
                                                                $itemName = $item['cost'] ?? $item['description'] ?? '';
                                                                $cost = $item['unit_cost'] ?? $item['cost'] ?? 0;
                                                                $units = $item['units'] ?? $item['days'] ?? 1;
                                                                $amount = $cost * $units;
                                                                $key = $fundCodeId . '_' . $itemName;
                                                                $originalBudgetMap[$key] = $amount;
                                                            }
                                                        }
                                                    }
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
                                                                                $itemName = $item['cost'] ?? $item['description'] ?? '';
                                                                                $cost = $item['unit_cost'] ?? $item['cost'] ?? 0;
                                                                                $units = $item['units'] ?? $item['days'] ?? 1;
                                                                                $total = $cost * $units;
                                                                                
                                                                                // Check if this budget item should be highlighted
                                                                                $shouldHighlight = false;
                                                                                $key = $fundCodeId . '_' . $itemName;
                                                                                
                                                                                // Check if it's a new item (not in original)
                                                                                if (!isset($originalBudgetMap[$key])) {
                                                                                    $shouldHighlight = true;
                                                                                } else {
                                                                                    // Check if amount has changed (with small tolerance for floating point)
                                                                                    $originalAmount = $originalBudgetMap[$key];
                                                                                    if (abs($total - $originalAmount) > 0.01) {
                                                                                        $shouldHighlight = true;
                                                                                    }
                                                                                }
                                                                            @endphp
                                                                            <tr @if($shouldHighlight) style="background-color: #ffe6e6;" @endif>
                                                                                <td>{{ $fundCode->code ?? 'N/A' }}</td>
                                                                                <td>{{ $itemName ?: 'N/A' }}</td>
                                                                                <td class="text-end">{{ number_format($total, 2) }}</td>
                                                                            </tr>
                                                                        @endforeach
                                                                    @endif
                                                                @endforeach
                                                                <tr style="background-color: #d4edda;">
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


                    <div class="col-lg-12">
                <!-- Enhanced Memo Information Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold">
                            <i class="bx bx-info-circle me-2 text-success"></i>Approval Information
                        </h6>
                </div>
                    <div class="card-body">

                        @if($changeRequest->overall_status !== 'approved')
                            <div class="mt-3 p-3"
                                style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-radius: 0.5rem; border: 1px solid #bbf7d0;">
                                
                                <!-- Compact Approval Info Row -->
                                <div class="row g-3">
                                    <!-- Status -->
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="p-2" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                                                <i class="bx bx-badge-check text-white"></i>
                                            </div>
                                            <div>
                                                <div class="fw-semibold text-dark small">Status</div>
                                                <div class="fw-bold text-purple">{{ ucfirst($changeRequest->overall_status ?? 'draft') }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Current Approver -->
                                    @if($changeRequest->overall_status !== 'draft' && $changeRequest->current_actor)
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="p-2" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);">
                                                <i class="bx bx-user text-white"></i>
                                            </div>
                                            <div>
                                                <div class="fw-semibold text-dark small">Current Approver</div>
                                                <div class="fw-bold text-info">{{ $changeRequest->current_actor->fname . ' ' . $changeRequest->current_actor->lname }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    @endif

                                    <!-- Approval Role -->
                                    @if($changeRequest->workflow_definition)
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="p-2" style="background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);">
                                                <i class="bx bx-crown text-white"></i>
                                            </div>
                                            <div>
                                                <div class="fw-semibold text-dark small">Approval Role</div>
                                                <div class="fw-bold text-orange">{{ $changeRequest->workflow_definition->role ?? 'Not specified' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                </div>

                                <!-- Additional Info (if needed) -->
                                @if($changeRequest->overall_status === 'pending')
                                <div class="mt-3 p-2 bg-info bg-opacity-10 rounded">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bx bx-info-circle text-info"></i>
                                        <span class="text-info fw-medium small">This change request is currently awaiting approval from the supervisor above.</span>
                                    </div>
                                </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Enhanced Approval Actions -->
                @if(can_take_action_generic($changeRequest) || (is_with_creator_generic($changeRequest) && $changeRequest->overall_status != 'draft') || (isdivision_head($changeRequest) && $changeRequest->overall_status == 'returned'))
                    <div class="card border-0 shadow-lg mt-4" style="background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">
                        <div class="card-header bg-transparent border-0 py-4" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-radius: 12px 12px 0 0;">
                            <h6 class="mb-0 fw-bold text-gray-800 d-flex align-items-center gap-2" style="color: #1f2937;">
                                <i class="bx bx-check-circle" style="color: #059669;"></i>
                                Approval Actions - Level {{ $changeRequest->approval_level ?? 0 }}
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info mb-3">
                                <i class="bx bx-info-circle me-2"></i>
                                            <strong>Current Level:</strong> {{ $changeRequest->approval_level ?? 0 }}
                                @if ($changeRequest->workflow_definition)
                                    - <strong>Role:</strong>
                                    {{ $changeRequest->workflow_definition->role ?? 'Not specified' }}
                                @endif
                            </div>
                            
                            <form action="{{ route('change-requests.update-status', $changeRequest) }}" method="POST" id="approvalForm">
                                @csrf
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="mb-3">
                                            <label for="remarks" class="form-label fw-semibold">
                                                <i class="bx bx-message-square-detail me-1"></i>Comments
                                            </label>
                                            <textarea class="form-control" id="remarks" name="remarks" rows="3"
                                                placeholder="Add your comments here..."></textarea>
                                        </div>
                                        @if($changeRequest->approval_level=='5')
                                        <div class="mb-3">
                                            <label for="available_budget" class="form-label">Available Budget <span class="text-danger">*</span></label>
                                            <input type="number" name="available_budget" class="form-control" placeholder="Available Budget" required>
                                        </div>
                                        @endif
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-grid gap-2 mt-4">
                                            @php
                                                $isHOD = isdivision_head($changeRequest);
                                                $isReturnedToHOD = $isHOD && $changeRequest->overall_status == 'returned' && $changeRequest->approval_level == 1;
                                                $isPendingAtHOD = $isHOD && $changeRequest->overall_status == 'pending' && $changeRequest->approval_level == 1;
                                            @endphp
                                            
                                            {{-- Show Approve button only if not returned to HOD --}}
                                            @if(!$isReturnedToHOD)
                                                <button type="submit" name="action" value="approved" class="btn btn-success w-100 d-flex align-items-center justify-content-center gap-1">
                                                    <i class="bx bx-check"></i>
                                                    Approve
                                                </button>
                                            @endif
                                            
                                            {{-- Always show Return button --}}
                                            <button type="submit" name="action" value="returned" class="btn btn-warning w-100 d-flex align-items-center justify-content-center gap-1">
                                                <i class="bx bx-undo"></i>
                                                Return
                                            </button>
                                            
                                            {{-- Show Cancel button only for HOD at level 1 --}}
                                            @if($isHOD && $changeRequest->approval_level == 1)
                                                <button type="submit" name="action" value="cancelled" class="btn btn-danger w-100 d-flex align-items-center justify-content-center gap-2">
                                                    <i class="bx bx-x"></i>
                                                    Cancel
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif

                <!-- Submit for Approval Section -->
                @if($changeRequest->overall_status === 'draft' && ($changeRequest->staff_id == user_session('staff_id') || ($changeRequest->division && $changeRequest->division->division_head == user_session('staff_id'))))
                    <div class="card sidebar-card border-0 mt-4"
                        style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
                        <div class="card-header bg-transparent border-0 py-3">
                            <h6 class="mb-0 fw-bold text-success d-flex align-items-center gap-2">
                                <i class="bx bx-send"></i>
                                Submit for Approval
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">Ready to submit this change request for approval?</p>
                            <form action="{{ route('change-requests.submit-for-approval', $changeRequest) }}" method="POST">
                                @csrf
                                <button type="submit"
                                    class="btn btn-success w-100 d-flex align-items-center justify-content-center gap-2" style="white-space: nowrap;">
                                    <i class="bx bx-send"></i>
                                    <span>Submit for Approval</span>
                                </button>
                            </form>
                            <div class="mt-3 p-3 bg-light rounded">
                                <small class="text-muted">
                                    <i class="bx bx-info-circle me-1"></i>
                                    <strong>Note:</strong> Once submitted, you won't be able to edit this change request until
                                    it's returned for revision.
                                </small>
                            </div>
                        </div>
                    </div>
                @endif

            </div> 
            <!-- End container-fluid -->

            <div class="col-lg-12">
                <!-- Resubmission Section for HODs when returned -->
                @if(($changeRequest->overall_status === 'returned' || $changeRequest->overall_status === 'pending') && isdivision_head($changeRequest) && $changeRequest->approval_level <= 1)
                    <div class="card sidebar-card border-0 mb-4"
                        style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);">
                        <div class="card-header bg-transparent border-0 py-3">
                            <h6 class="mb-0 fw-bold text-success d-flex align-items-center gap-2">
                                <i class="bx bx-undo"></i>
                                Resubmit for Approval
                            </h6>
                        </div>
                        <div class="card-body">
                                            <p class="text-muted mb-3">This change request was returned for revision. Ready to resubmit?</p>
                            <button type="button" class="btn btn-success w-100 d-flex align-items-center justify-content-center gap-2" 
                                    data-bs-toggle="modal" data-bs-target="#resubmitModal" style="white-space: nowrap;">
                                <i class="bx bx-undo"></i>
                                <span>Resubmit for Approval</span>
                            </button>
                            <div class="mt-3 p-3 bg-light rounded">
                                <small class="text-muted">
                                    <i class="bx bx-info-circle me-1"></i>
                                    <strong>Note:</strong> This will resubmit the change request to the approver who returned it.
                                </small>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Approval Trail Section -->
            
                @if(isset($changeRequest->approvalTrails) && $changeRequest->approvalTrails->count() > 0)
                    @include('partials.approval-trail', ['resource' => $changeRequest])
                @else
                    <div class="card sidebar-card border-0 mb-4">
                        <div class="card-header bg-transparent border-0 py-3">
                            <h6 class="mb-0 fw-bold text-warning d-flex align-items-center gap-2">
                                <i class="bx bx-history"></i>
                                Approval Trail
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="text-center text-muted py-4">
                                <i class="bx bx-time bx-lg mb-3"></i>
                                <p class="mb-0">No approval actions have been taken yet.</p>
                                @if($changeRequest->overall_status === 'draft')
                                    <small>Submit this change request for approval to start the approval trail.</small>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Modal for preview --}}
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="previewModalLabel">Attachment Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="previewModalBody" style="min-height:60vh;display:flex;align-items:center;justify-content:center;">
        <div class="text-center w-100">Loading preview...</div>
      </div>
    </div>
  </div>
</div>

{{-- Resubmit Modal --}}
<div class="modal fade" id="resubmitModal" tabindex="-1" aria-labelledby="resubmitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resubmitModalLabel">Resubmit for Approval</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('change-requests.resubmit', $changeRequest) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle me-2"></i>
                                <strong>Note:</strong> This will resubmit the change request to the approver who returned it for revision.
                    </div>
                    <div class="mb-3">
                        <label for="resubmitComment" class="form-label">Comments (Optional)</label>
                        <textarea class="form-control" id="resubmitComment" name="comment" rows="3" 
                                  placeholder="Add any comments about the changes made..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bx bx-undo me-1"></i>Resubmit for Approval
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>



        @if($changeRequest->overall_status === 'draft' || $changeRequest->overall_status === 'rejected')
            <div class="mt-4">
                <h6 class="text-success">Actions</h6>
                <div class="btn-group" role="group">
                    <a href="{{ route('change-requests.edit', $changeRequest) }}" class="btn btn-outline-warning">
                        <i class="fas fa-edit me-1"></i> Edit
                    </a>
                    @if($changeRequest->staff_id == user_session('staff_id') || $changeRequest->responsible_person_id == user_session('staff_id'))
                        <button type="button" 
                                class="btn btn-outline-danger" 
                                onclick="deleteChangeRequest({{ $changeRequest->id }})">
                            <i class="fas fa-trash me-1"></i> Delete
                        </button>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
</div>
@endsection

@push('scripts')
<script>
// Delete change request function
function deleteChangeRequest(changeRequestId) {
    if (!confirm('Are you sure you want to delete this change request? This action cannot be undone.')) {
        return;
    }

    // Get CSRF token
    const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    
    // Create form data
    const formData = new FormData();
    formData.append('_method', 'DELETE');
    formData.append('_token', token);

    // Send delete request
    fetch(`/apm/change-requests/${changeRequestId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            if (data.msg) {
                alert(data.msg);
            }
            // Redirect to index page
            window.location.href = '{{ route("change-requests.index") }}';
        } else {
            alert(data.msg || 'Failed to delete change request');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the change request');
    });
}
</script>
@endpush
