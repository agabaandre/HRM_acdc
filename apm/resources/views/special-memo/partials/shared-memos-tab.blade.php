@if($sharedMemos && $sharedMemos->count() > 0)
<div class="table-responsive">
    <table class="table table-hover mb-0 special-memo-index-table">
        <thead class="table-info">
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 27%;">Title</th>
                <th style="width: 7%;">Request Type</th>
                <th style="width: 10%;">Responsible Person</th>
                <th style="width: 8%;">Division</th>
                <th style="width: 11%;">Fund Type</th>
                <th style="width: 6%;">Date</th>
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
                    </td>
                    <td>
                        <span class="badge bg-info text-dark">
                            <i class="bx bx-category me-1"></i>
                            {{ $memo->requestType->name ?? 'N/A' }}
                        </span>
                    </td>
                    <td>
                        <div class="text-wrap" style="max-width: 100px;">
                            @if($memo->responsiblePerson)
                                {{ Str::limit($memo->responsiblePerson->fname . ' ' . $memo->responsiblePerson->lname, 15) }}
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
                        @if($memo->date_from && $memo->date_to)
                            <div class="small">
                                <div class="fw-bold text-primary">{{ \Carbon\Carbon::parse($memo->date_from)->format('M d, Y') }}</div>
                                <div class="text-muted">to {{ \Carbon\Carbon::parse($memo->date_to)->format('M d, Y') }}</div>
                            </div>
                        @elseif($memo->date_from)
                            <div class="small">
                                <div class="fw-bold text-primary">{{ \Carbon\Carbon::parse($memo->date_from)->format('M d, Y') }}</div>
                                <div class="text-muted">to N/A</div>
                            </div>
                        @else
                            <span class="text-muted">N/A</span>
                        @endif
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
                        <div class="btn-group-vertical btn-group-sm" role="group">
                            <a wire:navigate href="{{ route('special-memo.show', $memo) }}" 
                               class="btn btn-sm btn-outline-info" title="View">
                                <i class="bx bx-show me-1"></i>View
                            </a>
                            @if($memo->overall_status === 'approved')
                                <a href="{{ route('special-memo.print', $memo) }}" 
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
@if($sharedMemos instanceof \Illuminate\Pagination\LengthAwarePaginator && $sharedMemos->hasPages())
    <div class="d-flex justify-content-center mt-3">
        {{ $sharedMemos->appends(request()->query())->links() }}
    </div>
@endif
@else
<div class="text-center py-4 text-muted">
    <i class="bx bx-share fs-1 text-info opacity-50"></i>
    <p class="mb-0">No shared special memos found.</p>
    <small>Special memos where you have been added as a participant will appear here.</small>
</div>
@endif
