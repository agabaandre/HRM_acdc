<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h6 class="mb-0 text-info fw-bold">
            <i class="bx bx-share me-2"></i> Shared Single Memos
        </h6>
        <small class="text-muted">Single memos from other divisions where you're involved, sorted by most recent quarter and year</small>
    </div>
</div>

@if($sharedMemos->count() > 0)
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-info">
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 30%;">Title</th>
                    <th style="width: 10%;">Responsible Person</th>
                    <th style="width: 8%;">Division</th>
                    <th style="width: 6%;">Date Range</th>
                    <th style="width: 8%;">Fund Type</th>
                    <th style="width: 8%;">Status</th>
                    <th style="width: 8%;" class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @php $count = ($sharedMemos->currentPage() - 1) * $sharedMemos->perPage() + 1; @endphp
                @foreach($sharedMemos as $memo)
                    <tr>
                        <td>{{ $count++ }}</td>
                        <td class="table-title-cell">
                            @if($memo->document_number)
                                <small class="text-muted d-block">#{{ $memo->document_number }}</small>
                            @endif
                            <div class="fw-bold text-primary">{!! $memo->activity_title !!}</div>
                            <small class="text-muted">{{ Str::limit(strip_tags($memo->background), 50) }}</small>
                        </td>
                        <td>
                            <div class="text-wrap" style="max-width: 100px;">
                                @if($memo->responsiblePerson)
                                    <div class="fw-bold text-primary">{{ Str::limit($memo->responsiblePerson->fname . ' ' . $memo->responsiblePerson->lname, 15) }}</div>
                                    <small class="text-muted">Responsible Person</small>
                                @else
                                    <span class="text-muted">N/A</span>
                                @endif
                            </div>
                        </td>
                        <td>{{ $memo->matrix->division->division_name ?? 'N/A' }}</td>
                        <td>
                            <small>
                                {{ $memo->date_from ? $memo->date_from->format('M d, Y') : 'N/A' }}<br>
                                <span class="text-muted">to</span><br>
                                {{ $memo->date_to ? $memo->date_to->format('M d, Y') : 'N/A' }}
                            </small>
                        </td>
                        <td>
                            <span class="badge bg-warning text-dark">
                                <i class="bx bx-money me-1"></i>
                                {{ $memo->fundType->name ?? 'N/A' }}
                            </span>
                        </td>
                        <td>
                            @php
                                $statusClass = match($memo->overall_status) {
                                    'draft' => 'bg-secondary',
                                    'pending' => 'bg-warning',
                                    'approved' => 'bg-success',
                                    'returned' => 'bg-danger',
                                    'cancelled' => 'bg-dark',
                                    default => 'bg-secondary'
                                };
                            @endphp
                            <span class="badge {{ $statusClass }} text-white">
                                {{ ucfirst($memo->overall_status) }}
                            </span>
                        </td>
                        <td>
                            <div class="d-flex gap-2 justify-content-center">
                                <a href="{{ route('activities.single-memos.show', $memo) }}" 
                                   class="btn btn-sm btn-outline-info" title="View">
                                    <i class="bx bx-show"></i>
                                </a>
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
                                    <a href="{{ route('activities.single-memos.show', $memo) }}" 
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
    @if($sharedMemos instanceof \Illuminate\Pagination\LengthAwarePaginator && $sharedMemos->hasPages())
        <div class="d-flex justify-content-center mt-3">
            {{ $sharedMemos->appends(['tab' => 'sharedMemos', 'staff_id' => request('staff_id'), 'division_id' => request('division_id'), 'status' => request('status'), 'document_number' => request('document_number'), 'search' => $searchTerm, 'year' => $selectedYear, 'quarter' => $selectedQuarter])->links() }}
        </div>
    @endif
@else
    <div class="text-center py-4 text-muted">
        <i class="bx bx-share fs-1 text-info opacity-50"></i>
        <p class="mb-0">No shared single memos found.</p>
        <small>Single memos from other divisions where you're involved will appear here.</small>
    </div>
@endif
