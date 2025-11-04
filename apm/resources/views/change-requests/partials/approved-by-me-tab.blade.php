@if($approvedByMe && $approvedByMe->count() > 0)
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="approvedTable">
            <thead class="table-success">
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
                @php $count = ($approvedByMe->currentPage() - 1) * $approvedByMe->perPage() + 1; @endphp
                @foreach($approvedByMe as $changeRequest)
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
                            <span class="badge bg-success">
                                {{ strtoupper($changeRequest->overall_status ?? 'approved') }}
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="{{ route('change-requests.show', $changeRequest) }}" 
                                   class="btn btn-sm btn-outline-info" title="View">
                                    <i class="bx bx-show"></i>
                                </a>
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
        <p class="mb-0">No approved change requests found.</p>
        <small>Change requests you have approved will appear here.</small>
    </div>
@endif
