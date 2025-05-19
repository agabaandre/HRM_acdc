@extends('layouts.app')

@section('title', 'View Activity')

@section('header', 'Activity Details')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('matrices.activities.edit', [$matrix, $activity]) }}" class="btn btn-warning">
        <i class="bx bx-edit"></i> Edit Activity
    </a>
    <a href="{{ route('matrices.show', $matrix) }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to Matrix
    </a>
</div>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ $activity->activity_title }}</h5>
                    <span class="badge bg-warning">Pending</span>
                </div>
                <div class="text-muted small">
                    Activity Code: {{ $activity->workplan_activity_code }}
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6>Background</h6>
                        <p class="text-justify">{{ $activity->background }}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Key Result Area</h6>
                        <p class="text-justify">{{ $activity->key_result_area }}</p>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6>Date Range</h6>
                        <p class="mb-0">
                            <strong>From:</strong> {{ $activity->date_from->format('Y-m-d') }}<br>
                            <strong>To:</strong> {{ $activity->date_to->format('Y-m-d') }}
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6>Locations</h6>
                        <div>
                            @foreach($activity->location_id as $location)
                                <span class="badge bg-info me-1">{{ $location }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6>Request Type</h6>
                        <p>{{ $activity->requestType->name }}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Responsible Staff</h6>
                        <p>{{ $activity->staff->name }}</p>
                    </div>
                </div>

                <div class="mb-4">
                    <h6>Participants ({{ $activity->total_participants }} Total)</h6>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="d-flex flex-wrap gap-2">
                                @foreach($activity->internal_participants as $participantId)
                                    @php
                                        $participant = $staff->firstWhere('id', $participantId);
                                    @endphp
                                    @if($participant)
                                        <span class="badge bg-secondary">{{ $participant->name }}</span>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h6>Remarks</h6>
                    <p class="mb-0">{{ $activity->activity_request_remarks }}</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Budget Details</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">Quantity</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($activity->budget['items'] ?? [] as $item)
                                <tr>
                                    <td>{{ $item['description'] }}</td>
                                    <td class="text-end">${{ number_format($item['amount'], 2) }}</td>
                                    <td class="text-end">{{ $item['quantity'] ?? 1 }}</td>
                                    <td class="text-end">${{ number_format(($item['amount'] * ($item['quantity'] ?? 1)), 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Total:</th>
                                <th class="text-end">${{ number_format($activity->budget['total'] ?? 0, 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Timeline</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">Created</h6>
                            <small>{{ $activity->created_at->format('Y-m-d H:i') }}</small>
                        </div>
                        <p class="mb-1">Activity created by {{ $activity->staff->name }}</p>
                    </div>
                    @foreach($activity->activityApprovalTrails as $trail)
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">{{ $trail->status }}</h6>
                                <small>{{ $trail->created_at->format('Y-m-d H:i') }}</small>
                            </div>
                            <p class="mb-1">{{ $trail->remarks }}</p>
                            <small>By {{ $trail->staff->name }}</small>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Service Requests</h5>
            </div>
            <div class="card-body">
                @forelse($activity->serviceRequests as $request)
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="mb-1">{{ $request->service_type }}</h6>
                            <small class="text-muted">{{ $request->created_at->format('Y-m-d H:i') }}</small>
                        </div>
                        <span class="badge bg-{{ $request->status === 'approved' ? 'success' : ($request->status === 'rejected' ? 'danger' : 'warning') }}">
                            {{ ucfirst($request->status) }}
                        </span>
                    </div>
                    @if(!$loop->last)
                        <hr>
                    @endif
                @empty
                    <div class="text-center text-muted">
                        <i class="bx bx-package fs-1"></i>
                        <p class="mt-2">No service requests yet</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
