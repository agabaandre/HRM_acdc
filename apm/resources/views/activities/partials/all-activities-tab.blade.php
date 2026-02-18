<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h6 class="mb-0 text-primary fw-bold">
            <i class="bx bx-grid me-2"></i> All Activities
        </h6>
        <small class="text-muted">All activities across all divisions for {{ $selectedQuarter }} {{ $selectedYear }}, sorted by most recent quarter and year</small>
    </div>
</div>

@if($allActivities && $allActivities->count() > 0)
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-primary">
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 20%;">Activity Title</th>
                    <th style="width: 8%;">Matrix</th>
                    <th style="width: 12%;">Division</th>
                    <th style="width: 10%;">Document #</th>
                    <th style="width: 10%;">Responsible Person</th>
                    <th style="width: 10%;">Date Range</th>
                    <th style="width: 8%;">Fund Type</th>
                    <th style="width: 7%;">Status</th>
                    <th style="width: 10%;" class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @php $actCount = ($allActivities->currentPage() - 1) * $allActivities->perPage() + 1; @endphp
                @foreach($allActivities as $activity)
                    <tr>
                        <td>{{ $actCount++ }}</td>
                        <td>
                            <div class="text-wrap" style="max-width: 200px;">
                                <strong>{{ Str::limit($activity->activity_title ?? 'Untitled Activity', 50) }}</strong>
                                @if($activity->is_single_memo)
                                    <span class="badge bg-warning text-dark ms-1">SM</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <a href="{{ route('matrices.show', $activity->matrix) }}" class="text-decoration-none">
                                {{ $activity->matrix->year }} {{ $activity->matrix->quarter }}
                            </a>
                        </td>
                        <td>
                            <div class="text-wrap" style="max-width: 150px;">
                                {{ Str::limit($activity->matrix->division->division_name ?? 'N/A', 20) }}
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-info text-white">
                                {{ $activity->document_number ?? 'N/A' }}
                            </span>
                        </td>
                        <td>
                            <div class="text-wrap" style="max-width: 120px;">
                                @if($activity->responsiblePerson)
                                    {{ Str::limit($activity->responsiblePerson->fname . ' ' . $activity->responsiblePerson->lname, 15) }}
                                @else
                                    <span class="text-muted">Not assigned</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            @if($activity->date_from && $activity->date_to)
                                {{ \Carbon\Carbon::parse($activity->date_from)->format('M d') }} - 
                                {{ \Carbon\Carbon::parse($activity->date_to)->format('M d, Y') }}
                            @else
                                <span class="text-muted">Dates not set</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-warning text-dark">
                                <i class="bx bx-money me-1"></i>
                                {{ $activity->fundType->name ?? 'N/A' }}
                            </span>
                        </td>
                        <td class="text-center">
                            @php
                                $statusClass = $activity->overall_status === 'approved' ? 'bg-success' : ($activity->overall_status === 'pending' ? 'bg-warning' : 'bg-secondary');
                                $workflowRole = $activity->matrix?->workflow_definition ? ($activity->matrix->workflow_definition->role ?? 'N/A') : 'N/A';
                                $actorName = $activity->matrix?->current_actor ? ($activity->matrix->current_actor->fname . ' ' . $activity->matrix->current_actor->lname) : 'N/A';
                            @endphp
                            @if($activity->overall_status === 'pending')
                                <div class="text-center">
                                    <span class="badge {{ $statusClass }} text-dark mb-1">{{ strtoupper($activity->overall_status) }}</span>
                                    <br>
                                    <small class="text-muted d-block">Approver pending</small>
                                    <small class="text-muted d-block">{{ $workflowRole }}</small>
                                    @if($actorName !== 'N/A')
                                        <small class="text-muted d-block">{{ $actorName }}</small>
                                    @endif
                                </div>
                            @else
                                <span class="badge {{ $statusClass }}">{{ strtoupper($activity->overall_status ?? 'draft') }}</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="d-flex gap-2 justify-content-center">
                                <a href="{{ route('matrices.activities.show', [$activity->matrix, $activity]) }}" 
                                   class="btn btn-sm btn-outline-info" title="View">
                                    <i class="bx bx-show"></i>
                                </a>
                                @if($activity->responsible_person_id == user_session('staff_id') && in_array($activity->overall_status, ['draft', 'returned']))
                                    <form action="{{ route('matrices.activities.destroy', [$activity->matrix, $activity]) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this activity? This action cannot be undone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </form>
                                @endif
                                @if($activity->overall_status === 'approved')
                                    <a href="{{ route('matrices.activities.show', [$activity->matrix, $activity]) }}?print=pdf" 
                                       class="btn btn-sm btn-outline-success" title="Print PDF" target="_blank">
                                        <i class="bx bx-printer"></i>
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
    @if($allActivities instanceof \Illuminate\Pagination\LengthAwarePaginator && $allActivities->hasPages())
        <div class="d-flex justify-content-center mt-3">
            {{ $allActivities->appends(['tab' => 'all-activities', 'year' => $selectedYear, 'quarter' => $selectedQuarter, 'division_id' => $selectedDivisionId, 'staff_id' => $selectedStaffId, 'document_number' => $selectedDocumentNumber, 'search' => $searchTerm])->links() }}
        </div>
    @endif
@else
    <div class="text-center py-4 text-muted">
        <i class="bx bx-task fs-1 text-primary opacity-50"></i>
        <p class="mb-0">No activities found.</p>
        <small>Activities will appear here once they are created in matrices.</small>
    </div>
@endif
