@if($allRequests && $allRequests->count() > 0)
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-primary">
                <tr>
                    <th>#</th>
                    <th>Service Type</th>
                    <th>Description</th>
                    <th>Staff</th>
                    <th>Division</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($allRequests as $index => $request)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            <span class="badge bg-info">{{ $request->service_type ?? 'N/A' }}</span>
                        </td>
                        <td>
                            <div class="text-truncate" style="max-width: 200px;" title="{{ $request->description ?? 'No description' }}">
                                {{ $request->description ?? 'No description' }}
                            </div>
                        </td>
                        <td>{{ $request->staff->name ?? 'N/A' }}</td>
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
        <i class="bx bx-grid fs-1 text-primary opacity-50"></i>
        <p class="mb-0">No service requests found.</p>
        <small>All service requests in the system will appear here.</small>
    </div>
@endif
