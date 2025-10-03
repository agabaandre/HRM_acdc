@if($allMatrices && $allMatrices->count() > 0)
    <!-- Pagination Info -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="pagination-info text-muted small">
            Showing {{ $allMatrices->firstItem() ?? 0 }} to {{ $allMatrices->lastItem() ?? 0 }} of {{ $allMatrices->total() }} results
        </div>
        <div class="text-muted small">
            Page {{ $allMatrices->currentPage() }} of {{ $allMatrices->lastPage() }}
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-primary">
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
                @foreach($allMatrices as $index => $matrix)
                    <tr>
                        <td>{{ $allMatrices->firstItem() + $index }}</td>
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
                                data-bs-target="#kraModalAll{{ $matrix->id }}">
                                <i class="bx bx-list-check me-1"></i> {{ is_array($kras) ? count($kras) : 0 }}
                                Area(s)
                            </button>

                            <!-- Modal -->
                            <div class="modal fade" id="kraModalAll{{ $matrix->id }}" tabindex="-1"
                                aria-labelledby="kraModalLabelAll{{ $matrix->id }}" aria-hidden="true">
                                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="kraModalLabelAll{{ $matrix->id }}">
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
                                data-bs-target="#activitiesModalAll{{ $matrix->id }}">
                                <i class="bx bx-list-ul me-1"></i> {{ $activities->count() }} Activity(ies)
                            </button>

                            <!-- Activities Modal -->
                            <div class="modal fade" id="activitiesModalAll{{ $matrix->id }}" tabindex="-1"
                                aria-labelledby="activitiesModalLabelAll{{ $matrix->id }}" aria-hidden="true">
                                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="activitiesModalLabelAll{{ $matrix->id }}">
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
                            <span class="badge {{ $statusClass }}">{{ strtoupper($matrix->overall_status ?? 'draft') }}</span>
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
    @if($allMatrices->hasPages())
        <div class="d-flex justify-content-center mt-3">
            {{ $allMatrices->appends(request()->query())->links() }}
        </div>
    @endif
@else
    <div class="text-center py-4 text-muted">
        <i class="bx bx-grid fs-1 text-primary opacity-50"></i>
        <p class="mb-0">No matrices found.</p>
        <small>All matrices in the system will appear here.</small>
    </div>
@endif
