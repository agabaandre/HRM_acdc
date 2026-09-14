@extends('layouts.app')

@section('title', isset($matrix) ? 'Matrix Activities - ' . $matrix->year . ' ' . $matrix->quarter : 'Activities Management')
@section('header', isset($matrix) ? 'Matrix Activities - ' . $matrix->year . ' ' . $matrix->quarter : 'Activities Management')

@section('header-actions')
@endsection

@section('content')
<style>
.table-responsive {
    font-size: 0.875rem;
}
.table th, .table td {
    padding: 0.5rem 0.25rem;
    vertical-align: middle;
}
.table th {
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
}
.text-wrap {
    word-wrap: break-word;
    word-break: break-word;
}
.badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
}
.btn-group .btn {
    padding: 0.25rem 0.5rem;
}
#activityFilters select.activities-filter-select.select2-hidden-accessible {
    position: absolute !important; width: 1px !important; height: 1px !important; opacity: 0 !important; pointer-events: none !important;
}
</style>
    @if(isset($matrix))
        <!-- Matrix-specific activities view -->
        <div class="card shadow-sm mb-4 border-0">
            <div class="card-body py-3 px-4 bg-light rounded-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
                    <h4 class="mb-0 text-success fw-bold">
                        <i class="bx bx-task me-2 text-success"></i> 
                        Activities for {{ $matrix->division->division_name ?? 'Division' }} - {{ $matrix->year }} {{ $matrix->quarter }}
                    </h4>
                    <div>
                        <a wire:navigate href="{{ route('matrices.show', $matrix) }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bx bx-arrow-back me-1"></i> Back to Matrix
                        </a>
                        @if($matrix->overall_status !== 'approved')
                            <a href="{{ route('matrices.activities.create', $matrix) }}" class="btn btn-success btn-sm">
                                <i class="bx bx-plus me-1"></i> Add Activity
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Matrix activities list -->
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-primary fw-bold">
                                <i class="bx bx-task me-2"></i> Matrix Activities
                            </h6>
                            <small class="text-muted">{{ $matrix->division->division_name ?? 'Division' }} - {{ $matrix->year }} {{ $matrix->quarter }}</small>
                        </div>
                    </div>
                    
                    @if($activities && $activities->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-primary">
                                    <tr>
                                        <th>#</th>
                                        <th>Activity Title</th>
                                        <th>Responsible Person</th>
                                        <th>Date Range</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $actCount = 1; @endphp
                                    @foreach($activities as $activity)
                                        <tr>
                                            <td>{{ $actCount++ }}</td>
                                            <td>
                                                <strong>{{ $activity->activity_title ?? 'Untitled Activity' }}</strong>
                                                @if($activity->is_single_memo)
                                                    <span class="badge bg-warning text-dark ms-2">Single Memo</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($activity->responsiblePerson)
                                                    {{ $activity->responsiblePerson->fname }} {{ $activity->responsiblePerson->lname }}
                                                @else
                                                    <span class="text-muted">Not assigned</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($activity->date_from && $activity->date_to)
                                                    {{ \Carbon\Carbon::parse($activity->date_from)->format('M d') }} - 
                                                    {{ \Carbon\Carbon::parse($activity->date_to)->format('M d, Y') }}
                                                @else
                                                    <span class="text-muted">Dates not set</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @php
                                                    $statusClass = $activity->status === 'PASSED' ? 'bg-success' : ($activity->status === 'pending' ? 'bg-warning' : 'bg-secondary');
                                                    if ($activity->is_single_memo) {
                                                        $statusMeta = $activity->memoIndexStatusMeta();
                                                        $workflowRole = $statusMeta['role'] ?? 'N/A';
                                                        $actorName = $activity->current_actor
                                                            ? trim(($activity->current_actor->fname ?? '').' '.($activity->current_actor->lname ?? ''))
                                                            : ($statusMeta['actor_name'] ?? 'N/A');
                                                    } else {
                                                        $workflowRole = $matrix->workflow_definition ? ($matrix->workflow_definition->role ?? 'N/A') : 'N/A';
                                                        $actorName = $matrix->current_actor ? ($matrix->current_actor->fname . ' ' . $matrix->current_actor->lname) : 'N/A';
                                                    }
                                                @endphp
                                                @if($activity->overall_status === 'pending' || $activity->status === 'pending')
                                                    <div class="text-center">
                                                        <span class="badge {{ $statusClass }} text-dark mb-1">{{ strtoupper($activity->status ?? 'pending') }}</span>
                                                        <br>
                                                        <small class="text-muted d-block">{{ $workflowRole }}</small>
                                                        @if($actorName !== 'N/A')
                                                            <small class="text-muted d-block">{{ $actorName }}</small>
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="badge {{ $statusClass }}">{{ strtoupper($activity->status ?? 'draft') }}</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex gap-2 justify-content-center flex-wrap activity-actions action-buttons-stacked">
                                                    <a wire:navigate href="{{ route('matrices.activities.show', [$matrix, $activity]) }}" 
                                                       class="btn btn-sm btn-outline-info activity-action-btn" title="Open">
                                                        <i class="bx bx-show me-1"></i>Open
                                                    </a>
                                                    @if($activity->responsible_person_id == user_session('staff_id') && in_array($activity->overall_status, ['draft', 'returned']))
                                                        <form action="{{ route('matrices.activities.destroy', [$matrix, $activity]) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this activity? This action cannot be undone.')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger activity-action-btn" title="Delete">
                                                                <i class="bx bx-trash me-1"></i>Delete
                                                            </button>
                                                        </form>
                                                    @endif
                                                    @if($activity->status === 'PASSED' && $matrix->overall_status === 'approved')
                                                        <a wire:navigate href="{{ route('matrices.activities.memo-pdf', [$matrix, $activity]) }}" 
                                                           class="btn btn-sm btn-outline-success activity-action-btn" title="Print PDF" target="_blank">
                                                            <i class="bx bx-printer me-1"></i>Print
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        @if($activities instanceof \Illuminate\Pagination\LengthAwarePaginator && $activities->hasPages())
                            <div class="d-flex justify-content-center mt-3">
                                {{ $activities->appends(array_merge(request()->query(), ['tab' => 'matrix']))->links() }}
                            </div>
                        @endif
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="bx bx-task fs-1 text-primary opacity-50"></i>
                            <p class="mb-0">No activities found for this matrix.</p>
                            @if($matrix->overall_status !== 'approved')
                                <small>Click "Add Activity" to create the first activity.</small>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @else
        @push('head-meta')
        <style>
            #activities-index-app .ai-vuetify-app { background: transparent !important; }
            #activities-index-app .v-application__wrap { min-height: 0 !important; }
            #activities-index-app .ai-list-table thead th {
                background: #f8fafc !important;
                color: rgba(0, 0, 0, 0.7) !important;
                font-weight: 600 !important;
                font-size: 0.75rem !important;
                text-transform: uppercase;
                letter-spacing: 0.03em;
            }
            #activities-index-app .ai-list-table tbody td {
                color: rgba(0, 0, 0, 0.87) !important;
                vertical-align: middle !important;
            }
        </style>
        @endpush
        <div id="activities-index-app" data-apm-vuetify-page="activities-index">
            <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
            <div class="text-center py-5 text-muted">
                <div class="spinner-border text-success" role="status"></div>
                <p class="mt-2 mb-0">Loading activities…</p>
            </div>
        </div>
    @endif
</div>
@endsection
