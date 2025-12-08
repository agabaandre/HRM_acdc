@if($approvedByMe && $approvedByMe->count() > 0)
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="approvedTable" style="table-layout: fixed; width: 100%;">
            <thead class="table-success">
                <tr>
                    <th style="width: 40px;">#</th>
                    <th style="max-width: 274px; width: 274px;">Activity Details</th>
                    <th style="width: 100px;">Staff Member</th>
                    <th style="width: 164px;">Division</th>
                    <th style="width: 100px;">Request Date</th>
                    <th style="width: 80px;">Status</th>
                    <th class="text-center" style="width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @php $count = ($approvedByMe->currentPage() - 1) * $approvedByMe->perPage() + 1; @endphp
                @foreach($approvedByMe as $arf)
                    <tr>
                        <td>{{ $count++ }}</td>
                        <td style="max-width: 274px; width: 274px; word-wrap: break-word; white-space: normal;">
                            <div class="mb-1">
                                <span class="badge bg-primary">{{ $arf->document_number ?? $arf->arf_number }}</span>
                            </div>
                            <div class="fw-bold text-primary" style="word-wrap: break-word; word-break: break-word; max-width: 274px; line-height: 1.3; white-space: normal; overflow-wrap: break-word;">{{ $arf->activity_title }}</div>
                            <small class="text-muted">{{ Str::limit($arf->purpose, 50) }}</small>
                        </td>
                        <td>
                            @if($arf->staff)
                                {{ $arf->staff->fname }} {{ $arf->staff->lname }}
                            @else
                                <span class="text-muted">Not assigned</span>
                            @endif
                        </td>
                        <td>{{ $arf->division->division_name ?? 'N/A' }}</td>
                        <td>{{ $arf->request_date ? \Carbon\Carbon::parse($arf->request_date)->format('M d, Y') : 'N/A' }}</td>
                        <td>
                            @php
                                $statusBadgeClass = [
                                    'draft' => 'bg-secondary',
                                    'pending' => 'bg-warning',
                                    'approved' => 'bg-success',
                                    'rejected' => 'bg-danger',
                                    'returned' => 'bg-info',
                                ];
                                $statusClass = $statusBadgeClass[$arf->overall_status] ?? 'bg-secondary';
                            @endphp
                            
                            @if($arf->overall_status === 'approved')
                                <div class="text-center">
                                    <span class="badge {{ $statusClass }} mb-1">
                                        {{ strtoupper($arf->overall_status) }}
                                    </span>
                                </div>
                            @else
                                <span class="badge {{ $statusClass }}">
                                    {{ strtoupper($arf->overall_status ?? 'approved') }}
                                </span>
                            @endif
                        </td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="{{ route('request-arf.show', $arf) }}" 
                                   class="btn btn-sm btn-outline-info" title="View">
                                    <i class="bx bx-show"></i>
                                </a>
                                @if($arf->overall_status === 'draft' && $arf->staff_id === user_session('staff_id'))
                                    <a href="{{ route('request-arf.edit', $arf) }}" 
                                       class="btn btn-sm btn-outline-warning" title="Edit">
                                        <i class="bx bx-edit"></i>
                                    </a>
                                @endif
                                @if($arf->overall_status === 'approved')
                                    <a href="{{ route('request-arf.print', $arf) }}" 
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
        <p class="mb-0">No approved ARF requests found.</p>
        <small>ARF requests you have approved will appear here.</small>
    </div>
@endif