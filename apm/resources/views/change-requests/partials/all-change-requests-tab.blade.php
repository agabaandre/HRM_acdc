<div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead class="table-light">
            <tr>
                <th class="text-center">#</th>
                <th class="text-center">Document #</th>
                <th class="text-center">Title</th>
                <th class="text-center">Parent Memo</th>
                <th class="text-center">Division</th>
                <th class="text-center">Date Range</th>
                <th class="text-center">Changes</th>
                <th class="text-center">Status</th>
                <th class="text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($allChangeRequests as $index => $changeRequest)
                <tr>
                    <td class="text-center fw-bold">{{ $allChangeRequests->firstItem() + $index }}</td>
                    <td class="text-center">
                        @if($changeRequest->document_number)
                            <span class="badge bg-primary">{{ $changeRequest->document_number }}</span>
                        @else
                            <span class="text-muted">Pending</span>
                        @endif
                    </td>
                    <td class="table-title-cell">
                        <div class="fw-semibold text-dark">{{ $changeRequest->activity_title }}</div>
                        @if($changeRequest->supporting_reasons)
                            <small class="text-muted">{{ Str::limit($changeRequest->supporting_reasons, 50) }}</small>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($changeRequest->parentMemo)
                            <span class="badge bg-info">{{ class_basename($changeRequest->parent_memo_model) }}</span>
                            <br>
                            <small class="text-muted">#{{ $changeRequest->parent_memo_id }}</small>
                        @else
                            <span class="text-muted">N/A</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="fw-semibold">{{ $changeRequest->division->division_name ?? 'N/A' }}</span>
                    </td>
                    <td class="text-center">
                        <div class="small">
                            <div class="fw-semibold">{{ \Carbon\Carbon::parse($changeRequest->date_from)->format('M d') }}</div>
                            <div class="text-muted">to</div>
                            <div class="fw-semibold">{{ \Carbon\Carbon::parse($changeRequest->date_to)->format('M d, Y') }}</div>
                        </div>
                    </td>
                    <td class="text-center">
                        @if($changeRequest->hasAnyChanges())
                            <div class="d-flex flex-wrap gap-1 justify-content-center">
                                @if($changeRequest->has_budget_id_changed)
                                    <span class="badge bg-warning text-dark">Budget</span>
                                @endif
                                @if($changeRequest->has_activity_title_changed)
                                    <span class="badge bg-warning text-dark">Title</span>
                                @endif
                                @if($changeRequest->has_location_changed)
                                    <span class="badge bg-warning text-dark">Location</span>
                                @endif
                                @if($changeRequest->has_internal_participants_changed)
                                    <span class="badge bg-warning text-dark">Participants</span>
                                @endif
                                @if($changeRequest->has_request_type_id_changed)
                                    <span class="badge bg-warning text-dark">Type</span>
                                @endif
                                @if($changeRequest->has_fund_type_id_changed)
                                    <span class="badge bg-warning text-dark">Fund</span>
                                @endif
                            </div>
                        @else
                            <span class="text-muted">No changes</span>
                        @endif
                    </td>
                    <td class="text-center">
                        @switch($changeRequest->overall_status)
                            @case('draft')
                                <span class="badge bg-secondary">Draft</span>
                                @break
                            @case('submitted')
                                <span class="badge bg-warning text-dark">Submitted</span>
                                @break
                            @case('approved')
                                <span class="badge bg-success">Approved</span>
                                @break
                            @case('rejected')
                                <span class="badge bg-danger">Rejected</span>
                                @break
                            @default
                                <span class="badge bg-light text-dark">{{ ucfirst($changeRequest->overall_status) }}</span>
                        @endswitch
                    </td>
                    <td class="text-center">
                        <div class="btn-group" role="group">
                            <a href="{{ route('change-requests.show', $changeRequest) }}" 
                               class="btn btn-sm btn-outline-primary" 
                               title="View Details">
                                <i class="bx bx-show"></i>
                            </a>
                            @if($changeRequest->overall_status === 'draft')
                                <a href="{{ route('change-requests.edit', $changeRequest) }}" 
                                   class="btn btn-sm btn-outline-warning" 
                                   title="Edit">
                                    <i class="bx bx-edit"></i>
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center py-4">
                        <div class="text-muted">
                            <i class="bx bx-info-circle fs-1 d-block mb-2"></i>
                            <h5>No Change Requests Found</h5>
                            <p>No change requests match your current filters.</p>
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($allChangeRequests->hasPages())
    <div class="card-footer bg-white border-top-0">
        <div class="d-flex justify-content-between align-items-center">
            <div class="text-muted">
                Showing {{ $allChangeRequests->firstItem() }} to {{ $allChangeRequests->lastItem() }} of {{ $allChangeRequests->total() }} results
            </div>
            <div>
                {{ $allChangeRequests->links() }}
            </div>
        </div>
    </div>
@endif
