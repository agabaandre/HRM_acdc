@extends('layouts.app')

@section('title', 'View Matrix')

@section('header', 'Matrix Details')

@section('header-actions')
<div class="d-flex gap-2">
   @if(still_with_creator($matrix))
        <a href="{{ route('matrices.activities.create', $matrix) }}" class="btn btn-success btn-sm shadow-sm">
            <i class="bx bx-plus-circle me-1"></i> Add Activity
        </a>
        <a href="{{ route('matrices.edit', $matrix) }}" class="btn btn-warning btn-sm shadow-sm">
            <i class="bx bx-edit me-1"></i> Edit Matrix
        </a>
    @endif
    <a href="{{ route('matrices.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="bx bx-arrow-back me-1"></i> Back
    </a>
</div>
@endsection

@section('content')

@include('matrices.partials.matrix-metadata')
   
<div class="col-md-12">
    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bx bx-calendar-event me-2 text-primary"></i>Activities</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Title</th>
                            <th>Date Range</th>
                            <th>Participants</th>
                            <th>Budget (USD)</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activities as $activity)
                            <tr>
                                <td>{{ $activity->activity_title }}</td>
                                <td>{{ \Carbon\Carbon::parse($activity->date_from)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($activity->date_to)->format('M d, Y') }}</td>
                                <td>{{ $activity->total_participants }}</td>
                                <td>
                                    @php
                                        $budget = is_array($activity->budget) ? $activity->budget : json_decode($activity->budget, true);
                                        $totalBudget = 0;
                                        if (is_array($budget)) {
                                            foreach ($budget as $item) {
                                                $totalBudget += ($item['unit_cost'] ?? 0) * ($item['units'] ?? 0) * ($item['days'] ?? 1);
                                            }
                                        }
                                    @endphp
                                    {{ number_format($totalBudget, 2) }}
                                </td>
                                <td>
                                    <span class="badge bg-{{ ($activity->status === 'approved' || ($activity->my_last_action && $activity->my_last_action->action=='passed')) ? 'success' : ($activity->status === 'rejected' ? 'danger' : 'secondary') }}">
                                        {{ ucfirst(($activity->my_last_action)?$activity->my_last_action->action : ucfirst($activity->status) ) }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('matrices.activities.show', [$matrix, $activity]) }}" class="btn btn-outline-primary btn-sm">
                                        <i class="bx bx-show"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No activities found for this matrix.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-3">
                {{ $activities->withQueryString()->links() }}
            </div>

        </div>
    </div>
</div>

@if($matrix->matrixApprovalTrails)
    @include('matrices.partials.approval-trail')
@endif


@if(can_take_action($matrix) && activities_approved_by_me($matrix))
   <div class="col-md-4 mb-2 px-2 ms-auto">
    @include('matrices.partials.approval-actions', ['matrix' => $matrix])
    </div>
@endif

@if($matrix->activities->count()>0 && still_with_creator($matrix) && ($matrix->staff_id == session('user')['staff_id']))
 <div class="col-md-4 mb-2 px-2 ms-auto">
    <a href="{{ route('matrices.request_approval', $matrix) }}"  class="btn btn-success"><i class="bx bx-save me-2"></i> Request Approval</a>
 </div>
 @endif
 
</div>
@endsection
