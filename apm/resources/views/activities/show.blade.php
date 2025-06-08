@extends('layouts.app')

@section('title', 'View Activity')

@section('header', 'Activity Details')

@section('header-actions')
<div class="d-flex gap-1">
    @if(still_with_creator($matrix))
        <a href="{{ route('matrices.activities.edit', [$matrix, $activity]) }}" class="btn btn-warning">
            <i class="bx bx-edit"></i> Edit Activity
        </a>
    @endif
    <a href="{{ route('matrices.show', $matrix) }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to Matrix
    </a>
</div>
@endsection

@php
//dd($fundCodes);
@endphp

@section('content')
<div class="row">
    <div class="col-md-9">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">{{ $activity->activity_title }}</h5>
                <small class="text-muted">Activity Code: {{ $activity->workplan_activity_code }}</small>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Background:</strong>
                        <p>{{ $activity->background }}</p>
                    </div>
                    <div class="col-md-6">
                        <strong>Key Result Area:</strong>
                        <p>{{ $matrix->key_result_area[intval($activity->key_result_area)]['description'] ?? '' }}</p>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Date From:</strong><br>{{ $activity->date_from->format('Y-m-d') }}
                    </div>
                    <div class="col-md-4">
                        <strong>Date To:</strong><br>{{ $activity->date_to->format('Y-m-d') }}
                    </div>
                    <div class="col-md-4">
                        <strong>Status:</strong><br>{{ ucfirst($activity->status) }}
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Request Type:</strong><br>{{ $activity->requestType->name ?? '-' }}
                    </div>
                    <div class="col-md-4">
                        <strong>Fund Type:</strong><br>{{ $activity->fundType->name ?? '-' }}
                    </div>
                    <div class="col-md-4">
                        <strong>Responsible Staff:</strong><br>{{ $activity->staff->name ?? '-' }}
                    </div>
                </div>

                <div class="mb-3">
                    <strong>Locations:</strong><br>
                    @foreach($locations as $loc)
                        <span class="badge bg-info">{{ $loc->name }}</span>
                    @endforeach
                </div>

                <div class="mb-3">
                    <strong>Internal Participants ({{ count($internalParticipants) }})</strong>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Days</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($internalParticipants as $entry)
                                    <tr>
                                        <td>{{ $entry['staff']->name ?? 'N/A' }}</td>
                                        <td>{{ $entry['participant_start'] ?? '-' }}</td>
                                        <td>{{ $entry['participant_end'] ?? '-' }}</td>
                                        <td>{{ $entry['participant_days'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mb-3">
                    <strong>Remarks:</strong>
                    <p>{{ $activity->activity_request_remarks }}</p>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">Budget Details</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Unit Cost</th>
                                <th>Units</th>
                                <th>Days</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($fundCodes ?? [] as $fundCode )
                            @php
                              $items = $budgetItems[$fundCode->id];
                            @endphp
                            @if($items)
                            <tr>
                                <th colspan="5">{{ $fundCode->description  }}- {{ $fundCode->code }}</th>
                            </tr>
                            @foreach($items ?? [] as $item)
                            @php
                            $total = $item['unit_cost'] * $item['units'] * $item['days'];
                            @endphp
                                <tr>
                                    <td>{{ $item['description'] }}</td>
                                    <td class="text-end">{{ number_format($item['unit_cost'], 2) }}</td>
                                    <td class="text-end">{{ $item['units'] }}</td>
                                    <td class="text-end">{{ $item['days'] }}</td>
                                    <td class="text-end">{{ number_format($total, 2) }}</td>
                                </tr>
                            @endforeach
                            @endif
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end">Grand Total</th>
                                <th class="text-end">{{ $budgetItems ? number_format($budgetItems['grand_total'] ?? 0, 2) : 0 }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            @if(can_take_action($matrix))
            <div class="col-md-4 mb-2 px-2 ms-auto">
              @include('activities.partials.approval-actions',['activity'=>$activity,'matrix'=>$matrix])
            </div>
            @endif
     

            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">Timeline</h5>
            </div>
            <div class="card-body p-3">
                <div class="list-group list-group-flush">
                    <div class="list-group-item">
                        <strong>Created:</strong> {{ $activity->created_at->format('Y-m-d H:i') }}<br>
                        <small>By {{ $activity->staff->name ?? '-' }}</small>
                    </div>
                    @foreach($activity->activityApprovalTrails as $trail)
                        <div class="list-group-item">
                            <small class="badge bg-info">{{ucwords($trail->action)}}</small><br>
                            <small>{{ $trail->created_at->format('Y-m-d H:i') }} - {{ $trail->staff->name }}</small><br>
                            <p class="mb-0 text-muted">{{ $trail->remarks }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">Service Requests</h5>
            </div>
            <div class="card-body">
                @forelse($activity->serviceRequests as $request)
                    <div class="mb-3">
                        <strong>{{ $request->service_type }}</strong><br>
                        <small>{{ $request->created_at->format('Y-m-d H:i') }}</small><br>
                        <span class="badge bg-{{ $request->status === 'approved' ? 'success' : ($request->status === 'rejected' ? 'danger' : 'warning') }}">
                            {{ ucfirst($request->status) }}
                        </span>
                    </div>
                @empty
                    <div class="text-muted">No service requests found.</div>
                @endforelse
            </div>
        </div>
      
    </div>
</div>
@endsection
