@if($allMemos && $allMemos->count() > 0)
<div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead class="table-primary">
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 28%;">Title</th>
                <th style="width: 12%;">Responsible Staff</th>
                <th style="width: 10%;">Division</th>
                <th style="width: 8%;">Fund Type</th>
                <th style="width: 8%;">Date</th>
                <th style="width: 9%;">Status</th>
                <th style="width: 10%;" class="text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
            @php $count = ($allMemos->currentPage() - 1) * $allMemos->perPage() + 1; @endphp
            @foreach($allMemos as $memo)
                <tr>
                    <td>{{ $count++ }}</td>
                    <td>
                        <div class="text-wrap" style="max-width: 280px;">
                            @if($memo->document_number)
                                <small class="text-muted d-block">#{{ $memo->document_number }}</small>
                            @endif
                            <div class="fw-bold text-primary">{{ Str::limit($memo->activity_title, 70) }}</div>
                            @if($memo->nonTravelMemoCategory)
                                <small class="text-muted">({{ $memo->nonTravelMemoCategory->name }})</small>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="text-wrap" style="max-width: 120px;">
                            @if($memo->staff)
                                {{ Str::limit($memo->staff->fname . ' ' . $memo->staff->lname, 15) }}
                            @else
                                <span class="text-muted">Not assigned</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        <div class="text-wrap" style="max-width: 120px;">
                            {{ Str::limit($memo->division->division_name ?? 'N/A', 15) }}
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-warning text-dark">
                            <i class="bx bx-money me-1"></i>
                            {{ $memo->fundType->name ?? 'N/A' }}
                        </span>
                    </td>
                    <td>{{ $memo->memo_date ? \Carbon\Carbon::parse($memo->memo_date)->format('M d, Y') : 'N/A' }}</td>
                    <td>
                        @php
                            $statusBadgeClass = [
                                'draft' => 'bg-secondary',
                                'pending' => 'bg-warning',
                                'approved' => 'bg-success',
                                'rejected' => 'bg-danger',
                                'returned' => 'bg-info',
                            ];
                            $statusClass = $statusBadgeClass[$memo->overall_status] ?? 'bg-secondary';
                            
                            // Get workflow information
                            $approvalLevel = $memo->approval_level ?? 'N/A';
                            $workflowRole = $memo->workflow_definition ? ($memo->workflow_definition->role ?? 'N/A') : 'N/A';
                            $actorName = $memo->current_actor ? ($memo->current_actor->fname . ' ' . $memo->current_actor->lname) : 'N/A';
                        @endphp
                        
                        @if($memo->overall_status === 'pending')
                            <!-- Structured display for pending status -->
                            <div class="text-start">
                                <span class="badge {{ $statusClass }} mb-1">
                                    {{ strtoupper($memo->overall_status) }}
                                </span>
                                <br>
                              
                                <small class="text-muted d-block">{{ $workflowRole }}</small>
                                @if($actorName !== 'N/A')
                                    <small class="text-muted d-block">{{ $actorName }}</small>
                                @endif
                            </div>
                        @else
                            <!-- Standard badge for other statuses -->
                            <span class="badge {{ $statusClass }}">
                                {{ strtoupper($memo->overall_status ?? 'draft') }}
                            </span>
                        @endif
                    </td>
                    <td class="text-center">
                        <div class="btn-group">
                            <a href="{{ route('non-travel.show', $memo) }}" 
                               class="btn btn-sm btn-outline-info" title="View">
                                <i class="bx bx-show"></i>
                            </a>
                            @if(($memo->overall_status == 'draft' || $memo->overall_status == 'returned') && $memo->staff_id == user_session('staff_id'))
                                <a href="{{ route('non-travel.edit', $memo) }}" 
                                   class="btn btn-sm btn-outline-warning" title="Edit">
                                    <i class="bx bx-edit"></i>
                                </a>
                                <form action="{{ route('non-travel.destroy', $memo) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this memo? This action cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                </form>
                            @endif
                            @if($memo->overall_status === 'approved')
                                <a href="{{ route('non-travel.print', $memo) }}" 
                                   class="btn btn-sm btn-outline-success" title="Print" target="_blank">
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
@if($allMemos instanceof \Illuminate\Pagination\LengthAwarePaginator && $allMemos->hasPages())
    <div class="d-flex justify-content-center mt-3">
        {{ $allMemos->appends(request()->query())->links() }}
    </div>
@endif
@else
<div class="text-center py-4 text-muted">
    <i class="bx bx-grid fs-1 text-primary opacity-50"></i>
    <p class="mb-0">No non-travel memos found.</p>
    <small>Non-travel memos will appear here once they are created.</small>
</div>
@endif
