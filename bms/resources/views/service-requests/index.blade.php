@extends('layouts.app')

@section('title', 'Service Requests')

@section('content')
<div class="container-fluid p-0">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 fw-bold text-primary">Service Requests</h6>
                    <a href="{{ route('service-requests.create') }}" class="btn btn-primary btn-sm px-3">
                        <i class="bx bx-plus-circle me-1"></i> New Service Request
                    </a>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <form action="{{ route('service-requests.index') }}" method="GET" class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label fw-semibold small mb-1">Staff</label>
                                <select name="staff_id" class="form-select form-select-sm">
                                    <option value="">All Staff</option>
                                    @foreach($staff as $s)
                                        <option value="{{ $s->id }}" {{ request('staff_id') == $s->id ? 'selected' : '' }}>
                                            {{ $s->first_name }} {{ $s->last_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-semibold small mb-1">Division</label>
                                <select name="division_id" class="form-select form-select-sm">
                                    <option value="">All Divisions</option>
                                    @foreach($divisions as $division)
                                        <option value="{{ $division->id }}" {{ request('division_id') == $division->id ? 'selected' : '' }}>
                                            {{ $division->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-semibold small mb-1">Service Type</label>
                                <select name="service_type" class="form-select form-select-sm">
                                    <option value="">All Types</option>
                                    <option value="it" {{ request('service_type') == 'it' ? 'selected' : '' }}>IT</option>
                                    <option value="maintenance" {{ request('service_type') == 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                                    <option value="procurement" {{ request('service_type') == 'procurement' ? 'selected' : '' }}>Procurement</option>
                                    <option value="travel" {{ request('service_type') == 'travel' ? 'selected' : '' }}>Travel</option>
                                    <option value="other" {{ request('service_type') == 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-semibold small mb-1">Priority</label>
                                <select name="priority" class="form-select form-select-sm">
                                    <option value="">All Priorities</option>
                                    <option value="low" {{ request('priority') == 'low' ? 'selected' : '' }}>Low</option>
                                    <option value="medium" {{ request('priority') == 'medium' ? 'selected' : '' }}>Medium</option>
                                    <option value="high" {{ request('priority') == 'high' ? 'selected' : '' }}>High</option>
                                    <option value="urgent" {{ request('priority') == 'urgent' ? 'selected' : '' }}>Urgent</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-semibold small mb-1">Status</label>
                                <select name="status" class="form-select form-select-sm">
                                    <option value="">All Statuses</option>
                                    <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                                    <option value="submitted" {{ request('status') == 'submitted' ? 'selected' : '' }}>Submitted</option>
                                    <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                    <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                                    <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="bx bx-filter-alt me-1"></i> Filter
                                    </button>
                                    <a href="{{ route('service-requests.index') }}" class="btn btn-outline-secondary btn-sm">
                                        <i class="bx bx-reset me-1"></i> Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th width="90">Request #</th>
                                    <th width="100">Date</th>
                                    <th>Service Title</th>
                                    <th>Requestor</th>
                                    <th width="120">Service Type</th>
                                    <th width="100">Required By</th>
                                    <th width="90">Priority</th>
                                    <th width="90">Status</th>
                                    <th class="text-center" width="120">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($serviceRequests as $request)
                                    <tr>
                                        <td>
                                            <span class="fw-medium">{{ $request->request_number }}</span>
                                        </td>
                                        <td>{{ $request->request_date->format('d-M-Y') }}</td>
                                        <td>
                                            <a href="{{ route('service-requests.show', $request) }}" class="text-decoration-none fw-medium text-dark">
                                                {{ Str::limit($request->service_title, 50) }}
                                            </a>
                                        </td>
                                        <td>{{ $request->staff->first_name ?? '' }} {{ $request->staff->last_name ?? '' }}</td>
                                        <td>
                                            @php
                                                $typeLabels = [
                                                    'it' => '<span class="badge bg-info">IT</span>',
                                                    'maintenance' => '<span class="badge bg-secondary">Maintenance</span>',
                                                    'procurement' => '<span class="badge bg-primary">Procurement</span>',
                                                    'travel' => '<span class="badge bg-success">Travel</span>',
                                                    'other' => '<span class="badge bg-light text-dark">Other</span>',
                                                ];
                                                echo $typeLabels[$request->service_type] ?? '<span class="badge bg-light text-dark">Other</span>';
                                            @endphp
                                        </td>
                                        <td>{{ $request->required_by_date->format('d-M-Y') }}</td>
                                        <td>
                                            @php
                                                $priorityBadgeClass = [
                                                    'low' => 'bg-light text-dark',
                                                    'medium' => 'bg-info',
                                                    'high' => 'bg-warning',
                                                    'urgent' => 'bg-danger',
                                                ][$request->priority] ?? 'bg-secondary';
                                            @endphp
                                            <span class="badge {{ $priorityBadgeClass }}">
                                                {{ ucfirst($request->priority) }}
                                            </span>
                                        </td>
                                        <td>
                                            @php
                                                $statusBadgeClass = [
                                                    'draft' => 'bg-secondary',
                                                    'submitted' => 'bg-primary',
                                                    'in_progress' => 'bg-info',
                                                    'approved' => 'bg-success',
                                                    'rejected' => 'bg-danger',
                                                    'completed' => 'bg-dark',
                                                ][$request->status] ?? 'bg-secondary';
                                            @endphp
                                            <span class="badge {{ $statusBadgeClass }}">
                                                {{ ucfirst(str_replace('_', ' ', $request->status)) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-inline-flex">
                                                <a href="{{ route('service-requests.show', $request) }}" class="btn btn-sm btn-icon btn-outline-primary me-1" data-bs-toggle="tooltip" title="View Details">
                                                    <i class="bx bx-show"></i>
                                                </a>
                                                <a href="{{ route('service-requests.edit', $request) }}" class="btn btn-sm btn-icon btn-outline-primary me-1" data-bs-toggle="tooltip" title="Edit">
                                                    <i class="bx bx-edit"></i>
                                                </a>
                                                <form action="{{ route('service-requests.destroy', $request) }}" method="POST" class="d-inline delete-form">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" data-bs-toggle="tooltip" title="Delete">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <div class="d-flex flex-column align-items-center">
                                                <i class="bx bx-file-find text-secondary mb-2" style="font-size: 2rem;"></i>
                                                <h6 class="text-muted mb-1">No service requests found</h6>
                                                <p class="text-muted small">Try adjusting your search or create a new service request</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        {{ $serviceRequests->appends(request()->except('page'))->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // Initialize Select2
        $('.form-select').select2({
            width: '100%',
            dropdownAutoWidth: true,
        });
        
        // Initialize tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
        
        // Setup delete confirmation
        $('.delete-form').on('submit', function(e) {
            e.preventDefault();
            const form = this;
            
            Swal.fire({
                title: 'Are you sure?',
                text: "This will permanently delete this service request.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="bx bx-trash me-1"></i> Yes, delete it!',
                cancelButtonText: '<i class="bx bx-x me-1"></i> Cancel',
                reverseButtons: true,
                customClass: {
                    confirmButton: 'btn btn-danger',
                    cancelButton: 'btn btn-secondary'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });
</script>
@endpush
