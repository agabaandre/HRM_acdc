@if($approvedByMe && $approvedByMe->count() > 0)
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="approvedTable">
            <thead class="table-success">
                <tr>
                    <th style="width: 40px;">#</th>
                    <th style="width: 120px;">Document Number</th>
                    <th style="width: 280px;">Title</th>
                    <th style="width: 120px;">Responsible Person</th>
                    <th style="width: 120px;">Division</th>
                    <th style="width: 100px;">Request Date</th>
                    <th style="width: 100px;">Total Budget</th>
                    <th style="width: 100px;">Status</th>
                    <th class="text-center" style="width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @php $count = ($approvedByMe->currentPage() - 1) * $approvedByMe->perPage() + 1; @endphp
                @foreach($approvedByMe as $request)
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
                        <td>
                            @php
                                $statusClass = match($request->overall_status) {
                                    'draft' => 'bg-secondary',
                                    'pending' => 'bg-warning',
                                    'approved' => 'bg-success',
                                    'rejected' => 'bg-danger',
                                    'returned' => 'bg-info',
                                    default => 'bg-secondary'
                                };
                            @endphp
                            <span class="badge {{ $statusClass }}">{{ strtoupper($request->overall_status ?? 'draft') }}</span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group" role="group">
                                <a href="{{ route('service-requests.show', $request) }}" class="btn btn-outline-primary btn-sm" title="View Details">
                                    <i class="bx bx-show"></i>
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
        <i class="bx bx-check-circle display-1 text-muted"></i>
        <h5 class="text-muted mt-3">No Approved Service Requests</h5>
        <p class="text-muted">You haven't approved any service requests yet.</p>
    </div>
@endif
