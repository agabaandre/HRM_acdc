@if($approvedByMe && $approvedByMe->count() > 0)
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="approvedTable">
            <thead class="table-success">
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Request Type</th>
                    <th>Staff Member</th>
                    <th>Division</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @php $count = 1; @endphp
                @foreach($approvedByMe as $memo)
                    <tr>
                        <td>{{ $count++ }}</td>
                        <td>
                            @if($memo->document_number)
                                <small class="text-muted d-block">#{{ $memo->document_number }}</small>
                            @endif
                            <div class="fw-bold text-primary">{{ $memo->activity_title }}</div>
                        </td>
                        <td>
                            <span class="badge bg-info text-dark">
                                <i class="bx bx-category me-1"></i>
                                {{ $memo->requestType->name ?? 'N/A' }}
                            </span>
                        </td>
                        <td>
                            @if($memo->staff)
                                {{ $memo->staff->fname }} {{ $memo->staff->lname }}
                            @else
                                <span class="text-muted">Not assigned</span>
                            @endif
                        </td>
                        <td>{{ $memo->division->division_name ?? 'N/A' }}</td>
                        <td>{{ $memo->date_from ? \Carbon\Carbon::parse($memo->date_from)->format('M d, Y') : 'N/A' }}</td>
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
                                
                                // Get workflow information using helper function
                                $workflowInfo = $getWorkflowInfo($memo);
                                $approvalLevel = $workflowInfo['approvalLevel'];
                                $workflowRole = $workflowInfo['workflowRole'];
                                $actorName = $workflowInfo['actorName'];
                            @endphp
                            
                            @if($memo->overall_status === 'pending')
                                <!-- Structured display for pending status -->
                                <div class="text-center">
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
                                <a href="{{ route('activities.single-memos.show', $memo) }}" 
                                   class="btn btn-sm btn-outline-info" title="View">
                                    <i class="bx bx-show"></i>
                                </a>
                                @if(can_edit_memo($memo))
                                    <a href="{{ route('activities.single-memos.edit', [$memo->matrix, $memo]) }}" 
                                       class="btn btn-sm btn-outline-warning" title="Edit">
                                        <i class="bx bx-edit"></i>
                                    </a>
                                @endif
                                @if($memo->responsible_person_id == user_session('staff_id') && in_array($memo->overall_status, ['draft', 'returned']))
                                    <form action="{{ route('activities.single-memos.destroy', $memo) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this single memo? This action cannot be undone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </form>
                                @endif
                                @if($memo->overall_status === 'approved')
                                    <a href="{{ route('matrices.activities.memo-pdf', [$memo->matrix, $memo]) }}" 
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
    @if($approvedByMe instanceof \Illuminate\Pagination\LengthAwarePaginator && $approvedByMe->hasPages())
        <div class="d-flex justify-content-center mt-3">
            {{ $approvedByMe->appends(request()->query())->links() }}
        </div>
    @endif
@else
    <div class="text-center py-4 text-muted">
        <i class="bx bx-check-circle fs-1 text-success opacity-50"></i>
        <p class="mb-0">No approved single memos found.</p>
        <small>Single memos you have approved will appear here.</small>
    </div>
@endif
