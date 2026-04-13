@if($mySubmittedMemos && $mySubmittedMemos->count() > 0)
<div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead class="table-success">
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 32%;">Title / type</th>
                <th style="width: 12%;">Division</th>
                <th style="width: 10%;">Created</th>
                <th style="width: 12%;">Status</th>
                <th style="width: 12%;" class="text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
            @php $count = ($mySubmittedMemos->currentPage() - 1) * $mySubmittedMemos->perPage() + 1; @endphp
            @foreach($mySubmittedMemos as $memo)
                <tr>
                    <td>{{ $count++ }}</td>
                    <td>
                        <div class="text-wrap" style="max-width: 350px;">
                            @if($memo->document_number)
                                <small class="text-muted d-block">#{{ $memo->document_number }}</small>
                            @endif
                            <div class="fw-bold text-primary">{{ Str::limit(data_get($memo->payload, 'title') ?: $memo->memo_type_name_snapshot, 80) }}</div>
                            <small class="text-muted">({{ $memo->memo_type_name_snapshot }})</small>
                        </div>
                    </td>
                    <td>
                        <div class="text-wrap" style="max-width: 150px;">
                            {{ Str::limit($memo->division->division_name ?? 'N/A', 24) }}
                        </div>
                    </td>
                    <td>{{ $memo->created_at ? $memo->created_at->format('M d, Y') : 'N/A' }}</td>
                    <td>
                        @php
                            $statusBadgeClass = [
                                'draft' => 'bg-secondary',
                                'pending' => 'bg-warning',
                                'approved' => 'bg-success',
                                'returned' => 'bg-info',
                                'cancelled' => 'bg-danger',
                            ];
                            $statusClass = $statusBadgeClass[$memo->overall_status] ?? 'bg-secondary';
                            $actorName = $memo->currentApprover ? trim(($memo->currentApprover->title ? $memo->currentApprover->title . ' ' : '') . $memo->currentApprover->fname . ' ' . $memo->currentApprover->lname) : 'N/A';
                        @endphp
                        @if($memo->overall_status === 'pending')
                            <div class="text-start">
                                <span class="badge {{ $statusClass }} mb-1 text-dark">{{ strtoupper($memo->overall_status) }}</span>
                                <br>
                                <small class="text-muted d-block">Current approver</small>
                                @if($actorName !== 'N/A')
                                    <small class="text-muted d-block">{{ $actorName }}</small>
                                @endif
                            </div>
                        @else
                            <span class="badge {{ $statusClass }}">{{ strtoupper($memo->overall_status ?? 'draft') }}</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <div class="btn-group">
                            <a wire:navigate href="{{ route('other-memos.show', $memo) }}" class="btn btn-sm btn-outline-info" title="View">
                                <i class="bx bx-show me-1"></i>View
                            </a>
                            @if(($memo->overall_status === 'draft' || $memo->overall_status === 'returned') && (int) $memo->staff_id === (int) user_session('staff_id'))
                                <a wire:navigate href="{{ route('other-memos.edit', $memo) }}" class="btn btn-sm btn-outline-warning" title="Edit">
                                    <i class="bx bx-edit me-1"></i>Edit
                                </a>
                                @if($memo->overall_status === 'draft')
                                    <form action="{{ route('other-memos.destroy', $memo) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this draft? This cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="bx bx-trash me-1"></i>Delete
                                        </button>
                                    </form>
                                @endif
                            @endif
                            @if($memo->overall_status === 'approved' && (int) $memo->staff_id === (int) user_session('staff_id'))
                                <a href="{{ route('other-memos.print', $memo) }}" class="btn btn-sm btn-outline-success" title="Print" target="_blank">
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

@if($mySubmittedMemos instanceof \Illuminate\Pagination\LengthAwarePaginator && $mySubmittedMemos->hasPages())
    <div class="d-flex justify-content-center mt-3">
        {{ $mySubmittedMemos->appends(request()->query())->links() }}
    </div>
@endif
@else
<div class="text-center py-4 text-muted">
    <i class="bx bx-file-alt fs-1 text-success opacity-50"></i>
    <p class="mb-0">No other memos found.</p>
    <small>Memos you create will appear here.</small>
</div>
@endif
