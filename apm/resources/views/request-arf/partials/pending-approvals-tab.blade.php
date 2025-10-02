@if($pendingArfs && $pendingArfs->count() > 0)
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="pendingTable">
            <thead class="table-warning">
                <tr>
                    <th>#</th>
                    <th>ARF Number</th>
                    <th>Activity Title</th>
                    <th>Staff Member</th>
                    <th>Division</th>
                    <th>Request Date</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @php $count = ($pendingArfs->currentPage() - 1) * $pendingArfs->perPage() + 1; @endphp
                @foreach($pendingArfs as $arf)
                    <tr>
                        <td>{{ $count++ }}</td>
                        <td>
                            <span class="badge bg-primary">{{ $arf->arf_number }}</span>
                        </td>
                        <td>
                            <div class="fw-bold text-primary">{{ $arf->activity_title }}</div>
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
                            
                            @if($arf->overall_status === 'pending')
                                <div class="text-center">
                                    <span class="badge {{ $statusClass }} mb-1">
                                        {{ strtoupper($arf->overall_status) }}
                                    </span>
                                </div>
                            @else
                                <span class="badge {{ $statusClass }}">
                                    {{ strtoupper($arf->overall_status ?? 'draft') }}
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
    @if($pendingArfs instanceof \Illuminate\Pagination\LengthAwarePaginator && $pendingArfs->hasPages())
        <div class="d-flex justify-content-center mt-3">
            {{ $pendingArfs->appends(request()->query())->links() }}
        </div>
    @endif
@else
    <div class="text-center py-4 text-muted">
        <i class="bx bx-time fs-1 text-warning opacity-50"></i>
        <p class="mb-0">No pending ARF requests found.</p>
        <small>ARF requests awaiting your approval will appear here.</small>
    </div>
@endif
