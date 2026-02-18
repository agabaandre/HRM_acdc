@if($allArfs && $allArfs->count() > 0)
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-primary">
                <tr>
                    <th>#</th>
                    <th>ARF Number</th>
                    <th>Title</th>
                    <th>Staff</th>
                    <th>Division</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @php $count = ($allArfs->currentPage() - 1) * $allArfs->perPage() + 1; @endphp
                @foreach($allArfs as $index => $arf)
                    <tr>
                        <td>{{ $count++ }}</td>
                        <td>
                            <span class="badge bg-info">{{ $arf->document_number ?? $arf->arf_number ?? 'N/A' }}</span>
                        </td>
                        <td style="width: 25%;">
                            <div class="text-wrap" style="word-wrap: break-word; overflow-wrap: break-word; white-space: normal;" title="{{ $arf->display_title }}">
                                {{ $arf->display_title }}
                            </div>
                        </td>
                        <td>{{ $arf->staff->name ?? 'N/A' }}</td>
                        <td>{{ $arf->division->division_name ?? 'N/A' }}</td>
                        <td class="text-center">
                            @php
                                $statusClass = match($arf->overall_status) {
                                    'draft' => 'bg-secondary',
                                    'pending' => 'bg-warning',
                                    'approved' => 'bg-success',
                                    'rejected' => 'bg-danger',
                                    'returned' => 'bg-info',
                                    default => 'bg-secondary'
                                };
                                $approvalLevel = $arf->approval_level ?? 'N/A';
                                $workflowRole = $arf->workflow_definition ? ($arf->workflow_definition->role ?? 'N/A') : 'N/A';
                                $actorName = $arf->current_actor ? ($arf->current_actor->fname . ' ' . $arf->current_actor->lname) : 'N/A';
                            @endphp
                            @if($arf->overall_status === 'pending')
                                <div class="text-center">
                                    <span class="badge {{ $statusClass }} text-dark mb-1">{{ strtoupper($arf->overall_status) }}</span>
                                    <br>
                                    <small class="text-muted d-block">Level {{ $approvalLevel }}</small>
                                    <small class="text-muted d-block">{{ $workflowRole }}</small>
                                    @if($actorName !== 'N/A')
                                        <small class="text-muted d-block">{{ $actorName }}</small>
                                    @endif
                                </div>
                            @else
                                <span class="badge {{ $statusClass }}">{{ strtoupper($arf->overall_status ?? 'draft') }}</span>
                            @endif
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
        <i class="bx bx-grid fs-1 text-primary opacity-50"></i>
        <p class="mb-0">No ARF requests found.</p>
        <small>All ARF requests in the system will appear here.</small>
    </div>
@endif
