@if($mySubmittedArfs && $mySubmittedArfs->count() > 0)
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-success">
                <tr>
                    <th>#</th>
                    <th>ARF Number</th>
                    <th>Title</th>
                    <th>Division</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @php $count = ($mySubmittedArfs->currentPage() - 1) * $mySubmittedArfs->perPage() + 1; @endphp
                @foreach($mySubmittedArfs as $index => $arf)
                    <tr>
                        <td>{{ $count++ }}</td>
                        <td>
                            <span class="badge bg-info">{{ $arf->arf_number ?? 'N/A' }}</span>
                        </td>
                        <td>
                            <div class="text-truncate" style="max-width: 200px;" title="{{ $arf->title ?? 'No title' }}">
                                {{ $arf->title ?? 'No title' }}
                            </div>
                        </td>
                        <td>{{ $arf->division->division_name ?? 'N/A' }}</td>
                        <td>
                            @php
                                $statusClass = match($arf->overall_status) {
                                    'draft' => 'bg-secondary',
                                    'pending' => 'bg-warning',
                                    'approved' => 'bg-success',
                                    'rejected' => 'bg-danger',
                                    'returned' => 'bg-info',
                                    default => 'bg-secondary'
                                };
                            @endphp
                            <span class="badge {{ $statusClass }}">{{ strtoupper($arf->overall_status ?? 'draft') }}</span>
                        </td>
                        <td>{{ $arf->created_at ? $arf->created_at->format('M d, Y') : 'N/A' }}</td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="{{ route('request-arf.show', $arf) }}" class="btn btn-sm btn-outline-info" title="View">
                                    <i class="bx bx-show"></i>
                                </a>
                                @if($arf->overall_status === 'draft' || $arf->overall_status === 'returned')
                                    <a href="{{ route('request-arf.edit', $arf) }}" class="btn btn-sm btn-outline-warning" title="Edit">
                                        <i class="bx bx-edit"></i>
                                    </a>
                                @endif
                                @if($arf->overall_status === 'draft' || $arf->overall_status === 'returned')
                                    <form action="{{ route('request-arf.destroy', $arf) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this ARF request?')">
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
        <p class="mb-0">No ARF requests found.</p>
        <small>Your submitted ARF requests will appear here.</small>
    </div>
@endif
