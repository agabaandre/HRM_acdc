<div class="d-flex align-items-center justify-content-between mb-3">
    <div>
        <h6 class="mb-0 text-success fw-bold">
            <i class="bx bx-file-doc me-2"></i> My Division Single Memos
        </h6>
        <small class="text-muted">Single memos from your division, sorted by most recent quarter and year</small>
    </div>
</div>

@if($myMemos->count() > 0)
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-success">
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 31.5%;">Title</th>
                    <th style="width: 10%;">Responsible Person</th>
                    <th style="width: 8%;">Division</th>
                    <th style="width: 6%;">Date Range</th>
                    <th style="width: 11.5%;">Fund Type</th>
                    <th style="width: 8%;">Status</th>
                    <th style="width: 8%;" class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @php $count = ($myMemos->currentPage() - 1) * $myMemos->perPage() + 1; @endphp
                @foreach($myMemos as $memo)
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
                                <div class="fw-bold text-dark">{{ Str::limit(($memo->responsiblePerson->fname ?? 'N/A') . ' ' . ($memo->responsiblePerson->lname ?? ''), 15) }}</div>
                                <small class="text-muted">{{ Str::limit($memo->responsiblePerson->work_email ?? 'N/A', 20) }}</small>
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
                        <td class="fund-type-cell">
                            @php
                                $fundCodes = $memo->fundCodes ?? collect();
                                $budgetCodeLabels = $fundCodes->isNotEmpty() ? $fundCodes->pluck('code')->filter()->unique()->values()->all() : [];
                            @endphp
                            <div class="text-start">
                                <span class="badge bg-warning text-dark mb-1">
                                    <i class="bx bx-money me-1"></i>{{ $memo->fundType->name ?? 'N/A' }}
                                </span>
                                @if(count($budgetCodeLabels) > 0)
                                    <small class="text-muted d-block">{{ implode(', ', $budgetCodeLabels) }}</small>
                                @endif
                            </div>
                        </td>
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
                                $statusMeta = in_array($memo->overall_status, ['pending', 'returned'], true)
                                    ? $memo->memoIndexStatusMeta()
                                    : null;
                            @endphp
                            
                            @if($statusMeta)
                                <div class="text-start">
                                    <span class="badge {{ $statusClass }} mb-1">
                                        {{ strtoupper($memo->overall_status) }}
                                    </span>
                                    <br>
                                    <small class="text-muted d-block">Level {{ $statusMeta['level'] }}</small>
                                    <small class="text-muted d-block">{{ $statusMeta['role'] }}</small>
                                    @if($statusMeta['actor_name'] !== 'N/A')
                                        <small class="text-muted d-block">{{ $statusMeta['actor_name'] }}</small>
                                    @endif
                                </div>
                            @else
                                <span class="badge {{ $statusClass }}">
                                    {{ strtoupper($memo->overall_status ?? 'draft') }}
                                </span>
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="d-flex gap-2 justify-content-center action-buttons-stacked">
                                <a wire:navigate href="{{ route('activities.single-memos.show', $memo) }}" 
                                   class="btn btn-sm btn-outline-info" title="View">
                                    <i class="bx bx-show me-1"></i>View
                                </a>
                                @if($memo->responsible_person_id == user_session('staff_id') && in_array($memo->overall_status, ['draft', 'returned']))
                                    <form action="{{ route('activities.single-memos.destroy', $memo) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this single memo? This action cannot be undone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="bx bx-trash me-1"></i>Delete
                                        </button>
                                    </form>
                                @endif
                                @if($memo->overall_status === 'approved')
                                    <a wire:navigate href="{{ route('activities.single-memos.show', $memo) }}" 
                                       class="btn btn-sm btn-outline-success" title="Print" target="_blank">
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
    @if($myMemos instanceof \Illuminate\Pagination\LengthAwarePaginator && $myMemos->hasPages())
        <div class="d-flex justify-content-center mt-3">
            {{ $myMemos->appends(['tab' => 'mySubmitted', 'staff_id' => request('staff_id'), 'division_id' => request('division_id'), 'status' => request('status'), 'document_number' => request('document_number'), 'search' => $searchTerm, 'year' => $selectedYear, 'quarter' => $selectedQuarter])->links() }}
        </div>
    @endif
@else
    <div class="text-center py-4 text-muted">
        <i class="bx bx-file-doc fs-1 text-success opacity-50"></i>
        <p class="mb-0">No single memos found.</p>
        <small>Your single memos will appear here once they are created.</small>
    </div>
@endif
