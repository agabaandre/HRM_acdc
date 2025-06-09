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
<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bx bx-info-circle me-2 text-primary"></i>Matrix Information</h5>
            </div>
            <div class="card-body p-4">
                <div class="row">
                    <div class="col-6 mb-3">
                        <div class="d-flex align-items-center">
                            <i class="bx bx-calendar-alt me-2 text-primary"></i>
                            <div>
                                <small class="text-muted d-block">Year</small>
                                <span>{{ $matrix->year }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="d-flex align-items-center">
                            <i class="bx bx-calendar-week me-2 text-primary"></i>
                            <div>
                                <small class="text-muted d-block">Quarter</small>
                                <span>{{ $matrix->quarter }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="d-flex align-items-center">
                            <i class="bx bx-building me-2 text-primary"></i>
                            <div>
                                <small class="text-muted d-block">Division</small>
                                <span>{{ $matrix->division->name }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="d-flex align-items-center">
                            <i class="bx bx-user-voice me-2 text-primary"></i>
                            <div>
                                <small class="text-muted d-block">Focal Person</small>
                                <span>{{ $matrix->focalPerson ? $matrix->focalPerson->name : 'Not assigned' }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="d-flex align-items-center">
                            <i class="bx bx-calendar-plus me-2 text-primary"></i>
                            <div>
                                <small class="text-muted d-block">Created At</small>
                                <span>{{ $matrix->created_at->format('Y-m-d H:i') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="d-flex align-items-center">
                            <i class="bx bx-calendar-edit me-2 text-primary"></i>
                            <div>
                                <small class="text-muted d-block">Last Update</small>
                                <span>{{ $matrix->updated_at->format('Y-m-d H:i') }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                @if($matrix->overall_status !=='approved')
                <div class="badge {{config('approval_states')[$matrix->overall_status]}} col-md-6">
                <i class="fa fa-clock text-bold"></i>   {{ ($matrix->workflow_definition)?$matrix->workflow_definition->role:strtoupper($matrix->overall_status) }}
                </div>
                @endif
                @if($matrix->overall_status =='approved')
                    <div class="badge bg-success col-md-6"><i class="bx bx-check text-bold"></i>  {{strtoupper($matrix->overall_status)}}</div>
                @endif
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bx bx-target-lock me-2 text-primary"></i>Key Result Areas</h5>
            </div>
            <div class="card-body p-4">
                @php
                    $keyResultAreas = is_array($matrix->key_result_area)
                        ? $matrix->key_result_area
                        : json_decode($matrix->key_result_area ?? '[]', true);
                @endphp

                @if(empty($keyResultAreas))
                    <div class="alert alert-info mb-0">
                        <i class="bx bx-info-circle me-2"></i> No key result areas have been added yet.
                    </div>
                @else
                    @foreach($keyResultAreas as $index => $area)
                        <div class="border-bottom pb-2 mb-3">
                            <h6 class="fw-bold text-success">
                                <i class="bx bx-bullseye me-1"></i> Area {{ $index + 1 }}
                            </h6>
                            <p class="mb-0 text-muted">
                                {{ $area['description'] ?? 'No description provided' }}
                            </p>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>
   
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
                                    <span class="badge bg-{{ ($activity->status === 'approved' || $activity->my_last_action->action=='passed') ? 'success' : ($activity->status === 'rejected' ? 'danger' : 'secondary') }}">
                                        {{ ucfirst($activity->my_last_action->action ?? ucfirst($activity->status) ) }}
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

             @if(can_take_action($matrix))
                <div class="col-md-4 mb-2 px-2 ms-auto">
                    @include('matrices.partials.approval-actions', ['matrix' => $matrix])
                 </div>
            @endif
        </div>
    </div>
</div>
 

</div>
@endsection
