@extends('layouts.app')

@section('title', 'Request for ARF')

@section('header', 'Request for ARF')

@section('header-actions')
<!-- Create functionality removed - requests will be handled from activities -->
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-0"><i class="bx bx-list-ul me-2 text-primary"></i>ARF Requests</h5>
            </div>
            <div class="col-md-6">
                <form action="{{ route('request-arf.index') }}" method="GET" class="d-flex gap-2 justify-content-end">
                    <select name="division_id" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                        <option value="">All Divisions</option>
                        @foreach($divisions as $division)
                            <option value="{{ $division->id }}" {{ request('division_id') == $division->id ? 'selected' : '' }}>
                                {{ $division->division_name }}
                            </option>
                        @endforeach
                    </select>
                    
                    <select name="staff_id" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                        <option value="">All Staff</option>
                        @foreach($staff as $member)
                            <option value="{{ $member->id }}" {{ request('staff_id') == $member->id ? 'selected' : '' }}>
                                {{ $member->name }}
                            </option>
                        @endforeach
                    </select>
                    
                    <select name="status" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="submitted" {{ request('status') == 'submitted' ? 'selected' : '' }}>Submitted</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                    </select>
                    
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        <i class="bx bx-filter-alt"></i> Filter
                    </button>
                    
                    <a href="{{ route('request-arf.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bx bx-reset"></i> Reset
                    </a>
                </form>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <!-- Bootstrap Tabs Navigation -->
        <ul class="nav nav-tabs nav-fill" id="arfTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="myArfs-tab" data-bs-toggle="tab" data-bs-target="#myArfs" type="button" role="tab" aria-controls="myArfs" aria-selected="true">
                    <i class="bx bx-home me-2"></i> My Requests 
                    <span class="badge bg-success text-dark ms-2">{{ $myArfs->count() }}</span>
                </button>
            </li>
            @if(in_array(87, user_session('permissions', [])))
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="allArfs-tab" data-bs-toggle="tab" data-bs-target="#allArfs" type="button" role="tab" aria-controls="allArfs" aria-selected="false">
                        <i class="bx bx-grid me-2"></i> All Requests
                        <span class="badge bg-primary text-white ms-2">{{ $allArfs->count() ?? 0 }}</span>
                    </button>
                </li>
            @endif
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="arfTabsContent">
            <!-- My Requests Tab -->
            <div class="tab-pane fade show active" id="myArfs" role="tabpanel" aria-labelledby="myArfs-tab">
                <div class="p-3">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="mb-0 text-success fw-bold">
                                <i class="bx bx-home me-2"></i> My Requests
                            </h6>
                            <small class="text-muted">All ARF requests you have created</small>
                        </div>
                        <div>
                            <a href="{{ route('request-arf.export.my-csv') }}" class="btn btn-outline-success btn-sm">
                                <i class="bx bx-download me-1"></i> Export to CSV
                            </a>
                        </div>
                    </div>
                    
                    @if($myArfs->count() > 0)
        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-warning">
                                    <tr>
                                        <th>ARF Number</th>
                                        <th>Title</th>
                                        <th>Staff</th>
                                        <th>Division</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                                    @foreach($myArfs as $arf)
                        <tr>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    {{ $arf->arf_number }}
                                </span>
                            </td>
                            <td>
                                <div class="fw-bold text-primary">{{ $arf->activity_title }}</div>
                                <small class="text-muted">{{ $arf->request_date->format('M d, Y') }}</small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar avatar-sm me-2 bg-light rounded-circle">
                                        <span class="avatar-text">{{ substr($arf->staff->name ?? 'U', 0, 1) }}</span>
                                    </div>
                                    <span>{{ $arf->staff->name ?? 'Unknown' }}</span>
                                </div>
                            </td>
                            <td>{{ $arf->division->division_name ?? 'N/A' }}</td>
                            <td class="fw-bold">{{ number_format($arf->requested_amount, 2) }}</td>
                            <td>
                                @php
                                    $statusClass = [
                                        'draft' => 'bg-secondary',
                                        'submitted' => 'bg-info',
                                        'approved' => 'bg-success',
                                        'rejected' => 'bg-danger'
                                    ][$arf->status] ?? 'bg-secondary';
                                @endphp
                                <span class="badge {{ $statusClass }}">
                                    {{ ucfirst($arf->status) }}
                                </span>
                            </td>
                                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="{{ route('request-arf.show', $arf) }}" 
                                       class="btn btn-sm btn-info"
                                       data-bs-toggle="tooltip"
                                       title="View Details">
                                        <i class="bx bx-show-alt"></i>
                                    </a>
                                    <a href="{{ route('request-arf.edit', $arf) }}"
                                       class="btn btn-sm btn-warning"
                                       data-bs-toggle="tooltip"
                                       title="Edit Request">
                                        <i class="bx bx-edit"></i>
                                    </a>
                                    <button type="button" 
                                            class="btn btn-sm btn-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteModal{{ $arf->id }}"
                                            data-bs-toggle="tooltip"
                                            title="Delete Request">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                </div>

                                <!-- Delete Modal -->
                                <div class="modal fade" id="deleteModal{{ $arf->id }}" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header bg-danger text-white">
                                                <h5 class="modal-title"><i class="bx bx-trash me-1"></i> Delete ARF Request</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="alert alert-warning mb-3">
                                                    <i class="bx bx-error me-1"></i> Are you sure you want to delete this ARF request? This action cannot be undone.
                                                </div>
                                                <div class="card border">
                                                    <div class="card-body p-3">
                                                        <p class="mb-1"><strong><i class="bx bx-hash me-1 text-primary"></i> ARF Number:</strong> {{ $arf->arf_number }}</p>
                                                        <p class="mb-1"><strong><i class="bx bx-heading me-1 text-primary"></i> Title:</strong> {{ $arf->activity_title }}</p>
                                                        <p class="mb-0"><strong><i class="bx bx-money me-1 text-primary"></i> Amount:</strong> {{ number_format($arf->requested_amount, 2) }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <form action="{{ route('request-arf.destroy', $arf) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger">
                                                        <i class="bx bx-trash me-1"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <div class="text-muted">
                                <i class="bx bx-file-blank fs-1 mb-3"></i>
                                <p class="h5 text-muted">No ARF requests found</p>
                                <p class="small mt-2 text-muted">ARF requests will be created from activities</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- All Requests Tab (Permission 87 only) -->
            @if(in_array(87, user_session('permissions', [])))
                <div class="tab-pane fade" id="allArfs" role="tabpanel" aria-labelledby="allArfs-tab">
                    <div class="p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="mb-0 text-primary fw-bold">
                                    <i class="bx bx-grid me-2"></i> All Requests
                                </h6>
                                <small class="text-muted">All ARF requests across the system</small>
                            </div>
                            <div>
                                <a href="{{ route('request-arf.export.all-csv') }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bx bx-download me-1"></i> Export to CSV
                                </a>
                            </div>
                        </div>
                        
                        @if($allArfs->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-primary">
                                        <tr>
                                            <th>ARF Number</th>
                                            <th>Title</th>
                                            <th>Staff</th>
                                            <th>Division</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th class="text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($allArfs as $arf)
                                            <tr>
                                                <td>
                                                    <span class="badge bg-light text-dark border">
                                                        {{ $arf->arf_number }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-primary">{{ $arf->activity_title }}</div>
                                                    <small class="text-muted">{{ $arf->request_date->format('M d, Y') }}</small>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar avatar-sm me-2 bg-light rounded-circle">
                                                            <span class="avatar-text">{{ substr($arf->staff->name ?? 'U', 0, 1) }}</span>
                                                        </div>
                                                        <span>{{ $arf->staff->name ?? 'Unknown' }}</span>
                                                    </div>
                                                </td>
                                                <td>{{ $arf->division->division_name ?? 'N/A' }}</td>
                                                <td class="fw-bold">{{ number_format($arf->requested_amount, 2) }}</td>
                                                <td>
                                                    @php
                                                        $statusClass = [
                                                            'draft' => 'bg-secondary',
                                                            'submitted' => 'bg-info',
                                                            'approved' => 'bg-success',
                                                            'rejected' => 'bg-danger'
                                                        ][$arf->status] ?? 'bg-secondary';
                                                    @endphp
                                                    <span class="badge {{ $statusClass }}">
                                                        {{ ucfirst($arf->status) }}
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center gap-1">
                                                        <a href="{{ route('request-arf.show', $arf) }}" 
                                                           class="btn btn-sm btn-info"
                                                           data-bs-toggle="tooltip"
                                                           title="View Details">
                                                            <i class="bx bx-show-alt"></i>
                                                        </a>
                                                        <a href="{{ route('request-arf.edit', $arf) }}"
                                                           class="btn btn-sm btn-warning"
                                                           data-bs-toggle="tooltip"
                                                           title="Edit Request">
                                                            <i class="bx bx-edit"></i>
                                                        </a>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-danger"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#deleteModalAll{{ $arf->id }}"
                                                                data-bs-toggle="tooltip"
                                                                title="Delete Request">
                                                            <i class="bx bx-trash"></i>
                                                        </button>
                                                    </div>

                                                    <!-- Delete Modal -->
                                                    <div class="modal fade" id="deleteModalAll{{ $arf->id }}" tabindex="-1">
                                                        <div class="modal-dialog modal-dialog-centered">
                                                            <div class="modal-content">
                                                                <div class="modal-header bg-danger text-white">
                                                                    <h5 class="modal-title"><i class="bx bx-trash me-1"></i> Delete ARF Request</h5>
                                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="alert alert-warning mb-3">
                                                                        <i class="bx bx-error me-1"></i> Are you sure you want to delete this ARF request? This action cannot be undone.
                                                                    </div>
                                                                    <div class="card border">
                                                                        <div class="card-body p-3">
                                                                            <p class="mb-1"><strong><i class="bx bx-hash me-1 text-primary"></i> ARF Number:</strong> {{ $arf->arf_number }}</p>
                                                                            <p class="mb-1"><strong><i class="bx bx-heading me-1 text-primary"></i> Title:</strong> {{ $arf->activity_title }}</p>
                                                                            <p class="mb-0"><strong><i class="bx bx-money me-1 text-primary"></i> Amount:</strong> {{ number_format($arf->requested_amount, 2) }}</p>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <form action="{{ route('request-arf.destroy', $arf) }}" method="POST" class="d-inline">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit" class="btn btn-danger">
                                                                            <i class="bx bx-trash me-1"></i> Delete
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bx bx-file-blank fs-1 mb-3"></i>
                                    <p class="h5 text-muted">No ARF requests found</p>
                                    <p class="small mt-2 text-muted">No ARF requests have been created yet</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
    
    <div class="card-footer bg-white">
        <div class="d-flex justify-content-between">
            <div>
                <small class="text-muted">Showing {{ $myArfs->firstItem() ?? 0 }} to {{ $myArfs->lastItem() ?? 0 }} of {{ $myArfs->total() }} results</small>
            </div>
            <div>
                {{ $myArfs->links() }}
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        // Initialize tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
    });
</script>
@endpush
@endsection
