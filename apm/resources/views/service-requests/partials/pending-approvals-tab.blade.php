@if($pendingRequests && $pendingRequests->count() > 0)
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="pendingTable">
            <thead class="table-warning">
                <tr>
                    <th style="width: 40px;">#</th>
                    <th style="width: 120px;">Document Number</th>
                    <th style="width: 280px;">Title</th>
                    <th style="width: 120px;">Staff</th>
                    <th style="width: 120px;">Division</th>
                    <th style="width: 100px;">Request Date</th>
                    <th style="width: 100px;">Total Budget</th>
                    <th style="width: 150px;">Current Approver</th>
                    <th class="text-center" style="width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @php $count = ($pendingRequests->currentPage() - 1) * $pendingRequests->perPage() + 1; @endphp
                @foreach($pendingRequests as $request)
                    @php
                        $workflowInfo = $getWorkflowInfo($request);
                        $approvalLevel = $workflowInfo['approvalLevel'];
                        $workflowRole = $workflowInfo['workflowRole'];
                        $actorName = $workflowInfo['actorName'];
                    @endphp
                    <tr>
                        <td>{{ $count++ }}</td>
                        
                        <td style="width: 120px;">
                            <div class="text-muted small">{{ $request->document_number ?? 'N/A' }}</div>
                        </td>
                        <td style="width: 280px;">
                            <div class="fw-bold text-primary" style="word-wrap: break-word; white-space: normal; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; line-height: 1.2; max-height: 3.6em;" title="{{ $request->title ?? 'N/A' }}">{{ $request->title ?? 'N/A' }}</div>
                        </td>
                        <td>{{ $request->responsiblePerson ? ($request->responsiblePerson->fname . ' ' . $request->responsiblePerson->lname) : 'N/A' }}</td>
                        <td style="width: 150px; word-wrap: break-word; white-space: normal;">
                            <div>{{ $request->division->division_name ?? 'N/A' }}</div>
                        </td>
                        <td>{{ $request->request_date ? \Carbon\Carbon::parse($request->request_date)->format('M d, Y') : 'N/A' }}</td>
                        <td>
                            <span class="fw-bold text-success">
                                ${{ number_format($request->new_total_budget ?? 0, 2) }}
                            </span>
                        </td>
                        <td style="width: 150px;">
                            <div class="d-flex flex-column">
                                <span class="badge bg-info mb-1">{{ $workflowRole }}</span>
                                <small class="text-muted">{{ $actorName }}</small>
                            </div>
                        </td>
                        <td class="text-center">
                            <div class="btn-group" role="group">
                                <a href="{{ route('service-requests.show', $request) }}" class="btn btn-outline-primary btn-sm" title="View Details">
                                    <i class="bx bx-show"></i>
                                </a>
                                <a href="{{ route('service-requests.status', $request) }}" class="btn btn-outline-success btn-sm" title="Approve/Reject">
                                    <i class="bx bx-check-circle"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <div class="text-center py-5">
        <i class="bx bx-time display-1 text-muted"></i>
        <h5 class="text-muted mt-3">No Pending Service Requests</h5>
        <p class="text-muted">There are no service requests pending your approval at the moment.</p>
    </div>
@endif
