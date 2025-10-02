@if($approvedByMe && $approvedByMe->count() > 0)
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="approvedTable">
            <thead class="table-success">
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
                @php $count = ($approvedByMe->currentPage() - 1) * $approvedByMe->perPage() + 1; @endphp
                @foreach($approvedByMe as $arf)
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
                            <span class="badge bg-success">
                                {{ strtoupper($arf->overall_status ?? 'approved') }}
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="{{ route('request-arf.show', $arf) }}" 
                                   class="btn btn-sm btn-outline-info" title="View">
                                    <i class="bx bx-show"></i>
                                </a>
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
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="approvedTable">
            <thead class="table-success">
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
                @php $count = ($approvedByMe->currentPage() - 1) * $approvedByMe->perPage() + 1; @endphp
                @foreach($approvedByMe as $arf)
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
                            <span class="badge bg-success">
                                {{ strtoupper($arf->overall_status ?? 'approved') }}
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="{{ route('request-arf.show', $arf) }}" 
                                   class="btn btn-sm btn-outline-info" title="View">
                                    <i class="bx bx-show"></i>
                                </a>
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
