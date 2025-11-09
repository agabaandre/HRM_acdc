@if($mySubmittedRequests && $mySubmittedRequests->count() > 0)
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-success">
                <tr>
                    <th>#</th>
                    <th>Document Number</th>
                    <th>Description</th>
                    <th>Responsible Person</th>
                    <th>Division</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @php $count = ($mySubmittedRequests->currentPage() - 1) * $mySubmittedRequests->perPage() + 1; @endphp
                @foreach($mySubmittedRequests as $index => $request)
                    <tr>
                        <td>{{ $count++ }}</td>
                        <td>
                            <span class="badge bg-info">{{ $request->document_number ?? 'N/A' }}</span>
                        </td>
                        <td style="width: 25%;">
                            <div class="text-wrap" style="word-wrap: break-word; overflow-wrap: break-word; white-space: normal;" title="{{ $request->service_title ?? 'No title' }}">
                                {{ $request->service_title ?? 'No title' }}
                            </div>
                        </td>
                        <td>{{ $request->responsiblePerson ? ($request->responsiblePerson->fname . ' ' . $request->responsiblePerson->lname) : 'N/A' }}</td>
                        <td>{{ $request->division->division_name ?? 'N/A' }}</td>
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
                        <td>{{ $request->created_at ? $request->created_at->format('M d, Y') : 'N/A' }}</td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="{{ route('service-requests.show', $request) }}" class="btn btn-sm btn-outline-info" title="View">
                                    <i class="bx bx-show"></i>
                                </a>
                                @if($request->overall_status === 'draft' || $request->overall_status === 'returned')
                                    <a href="{{ route('service-requests.edit', $request) }}" class="btn btn-sm btn-outline-warning" title="Edit">
                                        <i class="bx bx-edit"></i>
                                    </a>
                                @endif
                                @if($request->overall_status === 'draft' || $request->overall_status === 'returned')
                                    <form action="{{ route('service-requests.destroy', $request) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this service request?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <div class="text-center py-4 text-muted">
        <i class="bx bx-file-alt fs-1 text-success opacity-50"></i>
        <p class="mb-0">No service requests found.</p>
        <small>Your submitted service requests will appear here.</small>
    </div>
@endif
