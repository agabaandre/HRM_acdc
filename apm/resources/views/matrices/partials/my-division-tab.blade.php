@if($myDivisionMatrices && $myDivisionMatrices->count() > 0)
    <!-- Pagination Info -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="pagination-info text-muted small">
            Showing {{ $myDivisionMatrices->firstItem() ?? 0 }} to {{ $myDivisionMatrices->lastItem() ?? 0 }} of {{ $myDivisionMatrices->total() }} results
        </div>
        <div class="text-muted small">
            Page {{ $myDivisionMatrices->currentPage() }} of {{ $myDivisionMatrices->lastPage() }}
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-warning">
                <tr>
                    <th>#</th>
                    <th>Year</th>
                    <th>Quarter</th>
                    <th>Division</th>
                    <th>Focal Person</th>       
                    <th>Key Result Areas</th>
                    <th>Activities</th>
                    <th>Level</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($myDivisionMatrices as $index => $matrix)
                    <tr>    
                        <td>{{ $myDivisionMatrices->firstItem() + $index }}</td>
                        <td>{{ $matrix->year }}</td>
                        <td>{{ $matrix->quarter }}</td>
                        <td>{{ $matrix->division->division_name ?? 'N/A' }}</td>
                        <td>{{ $matrix->focalPerson->name ?? 'N/A' }}</td>
                        <td>    
                            @php
                                $kras = is_string($matrix->key_result_area)
                                    ? json_decode($matrix->key_result_area, true)
                                    : $matrix->key_result_area;
                            @endphp
                            <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal"  
                                data-bs-target="#kraModalMyDiv{{ $matrix->id }}">
                                <i class="bx bx-list-check me-1"></i> {{ is_array($kras) ? count($kras) : 0 }}
                                Area(s)
                            </button>

                            <!-- Modal -->
                            <div class="modal fade" id="kraModalMyDiv{{ $matrix->id }}" tabindex="-1"
                                aria-labelledby="kraModalLabelMyDiv{{ $matrix->id }}" aria-hidden="true">
                                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="kraModalLabelMyDiv{{ $matrix->id }}">
                                                Key Result Areas - {{ $matrix->year }} {{ $matrix->quarter }}
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            @if (is_array($kras) && count($kras))
                                                <ul class="list-group">
                                                    @foreach ($kras as $kra)
                                                        <li class="list-group-item">
                                                            <i class="bx bx-check-circle text-success me-2"></i>
                                                            {{ $kra['description'] ?? '' }}
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <p class="text-muted">No key result areas defined.</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @php
                                $activities = $matrix->activities;
                            @endphp
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                data-bs-target="#activitiesModalMyDiv{{ $matrix->id }}">
                                <i class="bx bx-list-ul me-1"></i> {{ $activities->count() }} Activity(ies)
                            </button>

                            <!-- Activities Modal -->
                            <div class="modal fade" id="activitiesModalMyDiv{{ $matrix->id }}" tabindex="-1"
                                aria-labelledby="activitiesModalLabelMyDiv{{ $matrix->id }}" aria-hidden="true">
                                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="activitiesModalLabelMyDiv{{ $matrix->id }}">
                                                Activities - {{ $matrix->year }} {{ $matrix->quarter }}
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            @if ($activities->count() > 0)
                                                <div class="table-responsive">
                                                    <table class="table table-sm">
                                                        <thead>
                                                            <tr>
                                                                <th>Activity Title</th>
                                                                <th>Participants</th>
                                                                <th>Budget</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach ($activities as $activity)
                                                                <tr>
                                                                    <td style="width: 80%; word-wrap: break-word; white-space: normal;">{{ $activity->activity_title }}</td>
                                                                    <td>{{ $activity->total_participants ?? 0 }}</td>
                                                                    <td>
                                                                        @php
                                                                            $totalBudget = 0;
                                                                            
                                                                            // First try to get from total_budget field if it exists
                                                                            if (isset($activity->total_budget) && $activity->total_budget > 0) {
                                                                                $totalBudget = floatval($activity->total_budget);
                                                                            } else {
                                                                                // Try to calculate from budget_breakdown
                                                                                $budgetBreakdown = $activity->budget_breakdown;
                                                                                
                                                                                // Handle JSON string
                                                                                if (is_string($budgetBreakdown)) {
                                                                                    $budgetBreakdown = json_decode($budgetBreakdown, true);
                                                                                }
                                                                                
                                                                                if (is_array($budgetBreakdown)) {
                                                                                    foreach ($budgetBreakdown as $key => $entries) {
                                                                                        if ($key === 'grand_total') continue;
                                                                                        
                                                                                        if (is_array($entries)) {
                                                                                            foreach ($entries as $item) {
                                                                                                $unitCost = floatval($item['unit_cost'] ?? 0);
                                                                                                $units = floatval($item['units'] ?? 0);
                                                                                                $days = floatval($item['days'] ?? 1);
                                                                                                
                                                                                                if ($days > 1) {
                                                                                                    $totalBudget += $unitCost * $units * $days;
                                                                                                } else {
                                                                                                    $totalBudget += $unitCost * $units;
                                                                                                }
                                                                                            }
                                                                                        }
                                                                                    }
                                                                                }
                                                                            }
                                                                        @endphp
                                                                        ${{ number_format($totalBudget, 2) }}
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @else
                                                <p class="text-muted">No activities defined.</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-info">{{ $matrix->approval_level ?? 'N/A' }}</span>
                        </td>
                        <td>
                            @php
                                $statusClass = match($matrix->overall_status) {
                                    'draft' => 'bg-secondary',
                                    'pending' => 'bg-warning',
                                    'approved' => 'bg-success',
                                    'rejected' => 'bg-danger',
                                    'returned' => 'bg-info',
                                    default => 'bg-secondary'
                                };
                            @endphp
                            @php
                                $statusColor = match($matrix->overall_status) {
                                    'pending' => 'text-warning',
                                    'approved' => 'text-success',
                                    'rejected' => 'text-danger',
                                    'returned' => 'text-info',
                                    'draft' => 'text-secondary',
                                    default => 'text-secondary'
                                };
                            @endphp
                            <div class="fw-bold text-uppercase mb-1 {{ $statusColor }}">
                                {{ $matrix->overall_status ?? 'draft' }}
                            </div>
                            @if($matrix->workflow_definition)
                                <div class="fw-semibold text-dark mb-1">
                                    {{ $matrix->workflow_definition->role ?? 'N/A' }}
                                    @if($matrix->current_actor)
                                        <br><span class="text-muted small">{{ $matrix->current_actor->fname }} {{ $matrix->current_actor->lname }}</span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="{{ route('matrices.show', $matrix) }}" class="btn btn-sm btn-outline-info" title="View">
                                    <i class="bx bx-show"></i>
                                </a>
                                @if(in_array($matrix->overall_status, ['draft', 'returned']))
                                    <a href="{{ route('matrices.edit', $matrix) }}" class="btn btn-sm btn-outline-warning" title="Edit">
                                        <i class="bx bx-edit"></i>
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
    @if($myDivisionMatrices->hasPages())
        <div class="d-flex justify-content-center mt-3">
            {{ $myDivisionMatrices->appends(request()->query())->links() }}
        </div>
    @endif
@else
    <div class="text-center py-4 text-muted">
        <i class="bx bx-home fs-1 text-success opacity-50"></i>
        <p class="mb-0">No matrices found in your division.</p>
        <small>Matrices for your division will appear here.</small>
    </div>
@endif
