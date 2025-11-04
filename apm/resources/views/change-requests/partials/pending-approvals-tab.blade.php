@if($pendingChangeRequests && $pendingChangeRequests->count() > 0)
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="pendingTable">
            <thead class="table-warning">
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Parent Memo</th>
                    <th>Staff Member</th>
                    <th>Division</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @php $count = ($pendingChangeRequests->currentPage() - 1) * $pendingChangeRequests->perPage() + 1; @endphp
                @foreach($pendingChangeRequests as $changeRequest)
                    <tr>
                        <td>{{ $count++ }}</td>
                        <td>
                            <div class="fw-bold text-primary">{{ $changeRequest->activity_title }}</div>
                        </td>
                        <td>
                            @if($changeRequest->parent_memo_model && $changeRequest->parent_memo_id)
                                <span class="badge bg-info">{{ class_basename($changeRequest->parent_memo_model) }}</span>
                                <br>
                                @if($changeRequest->parent_memo_url && $changeRequest->parent_memo_document_number)
                                    <a href="{{ $changeRequest->parent_memo_url }}" class="text-decoration-none" title="View Parent Memo">
                                        <small class="text-primary fw-semibold">{{ $changeRequest->parent_memo_document_number }}</small>
                                    </a>
                                @else
                                    <small class="text-muted">#{{ $changeRequest->parent_memo_id }}</small>
                                @endif
                            @else
                                <span class="text-muted">N/A</span>
                            @endif
                        </td>
                        <td>
                            @if($changeRequest->staff)
                                {{ $changeRequest->staff->fname }} {{ $changeRequest->staff->lname }}
                            @else
                                <span class="text-muted">Not assigned</span>
                            @endif
                        </td>
                        <td>{{ $changeRequest->division->division_name ?? 'N/A' }}</td>
                        <td>{{ $changeRequest->date_from ? \Carbon\Carbon::parse($changeRequest->date_from)->format('M d, Y') : 'N/A' }}</td>
                        <td>
                            @php
                                $statusBadgeClass = [
                                    'draft' => 'bg-secondary',
                                    'submitted' => 'bg-warning',
                                    'approved' => 'bg-success',
                                    'rejected' => 'bg-danger',
                                ];
                                $statusClass = $statusBadgeClass[$changeRequest->overall_status] ?? 'bg-secondary';
                            @endphp
                            
                            @if($changeRequest->overall_status === 'submitted')
                                <div class="text-center">
                                    <span class="badge {{ $statusClass }} mb-1">
                                        {{ strtoupper($changeRequest->overall_status) }}
                                    </span>
                                </div>
                            @else
                                <span class="badge {{ $statusClass }}">
                                    {{ strtoupper($changeRequest->overall_status ?? 'draft') }}
                                </span>
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="{{ route('change-requests.show', $changeRequest) }}" 
                                   class="btn btn-sm btn-outline-info" title="View">
                                    <i class="bx bx-show"></i>
                                </a>
                                @if($changeRequest->overall_status === 'draft' && $changeRequest->staff_id === user_session('staff_id'))
                                    <a href="{{ route('change-requests.edit', $changeRequest) }}" 
                                       class="btn btn-sm btn-outline-warning" title="Edit">
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
    @if($pendingChangeRequests instanceof \Illuminate\Pagination\LengthAwarePaginator && $pendingChangeRequests->hasPages())
        <div class="d-flex justify-content-center mt-3">
            {{ $pendingChangeRequests->appends(request()->query())->links() }}
        </div>
    @endif
@else
    <div class="text-center py-4 text-muted">
        <i class="bx bx-time fs-1 text-warning opacity-50"></i>
        <p class="mb-0">No pending change requests found.</p>
        <small>Change requests awaiting your approval will appear here.</small>
    </div>
@endif
