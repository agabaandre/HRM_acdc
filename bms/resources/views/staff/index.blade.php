@extends('layouts.app')

@section('title', 'Staff Management')

@section('header', 'Staff Management')

@section('header-actions')
<a href="{{ route('staff.create') }}" class="btn btn-success">
    <i class="bx bx-plus"></i> Add New Staff
</a>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-3"><i class="bx bx-user-circle me-2 text-primary"></i>Staff Directory</h5>
        
        <form action="{{ route('staff.index') }}" method="GET" class="row g-3">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Search by name or ID..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="division_id" class="form-select">
                    <option value="">All Divisions</option>
                    @foreach($divisions as $division)
                        <option value="{{ $division->id }}" {{ request('division_id') == $division->id ? 'selected' : '' }}>
                            {{ $division->division_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="directorate_id" class="form-select">
                    <option value="">All Directorates</option>
                    @foreach($directorates as $directorate)
                        <option value="{{ $directorate->id }}" {{ request('directorate_id') == $directorate->id ? 'selected' : '' }}>
                            {{ $directorate->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="duty_station_id" class="form-select">
                    <option value="">All Duty Stations</option>
                    @foreach($dutyStations as $dutyStation)
                        <option value="{{ $dutyStation->id }}" {{ request('duty_station_id') == $dutyStation->id ? 'selected' : '' }}>
                            {{ $dutyStation->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bx bx-filter-alt"></i>
                </button>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Staff ID</th>
                        <th>Name</th>
                        <th>Division</th>
                        <th>Job Title</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($staffMembers as $staff)
                        <tr>
                            <td>{{ $staff->staff_id }}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <!-- <div class="avatar avatar-sm me-2 bg-light rounded-circle">
                                        <span class="avatar-text">{{ strtoupper(substr($staff->fname, 0, 1)) }}{{ strtoupper(substr($staff->lname, 0, 1)) }}</span>
                                    </div> -->
                                    <div>
                                        <strong>{{ $staff->fname }} {{ $staff->lname }}</strong>
                                        @if($staff->email)
                                            <small class="d-block text-muted">{{ $staff->email }}</small>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>{{ $staff->division->division_name ?? 'N/A' }}</td>
                            <td>{{ $staff->title }}</td>
                            <td>{{ $staff->tel_1 }}</td>
                            <td>
                                @if($staff->active == 1)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-danger">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <a href="{{ route('staff.show', $staff) }}" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="View">
                                        <i class="bx bx-show"></i>
                                    </a>
                                    <a href="{{ route('staff.edit', $staff) }}" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="Edit">
                                        <i class="bx bx-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal{{ $staff->id }}" data-bs-toggle="tooltip" title="Delete">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                </div>

                                <!-- Delete Modal -->
                                <div class="modal fade" id="deleteModal{{ $staff->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Delete Staff Record</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete <strong>{{ $staff->firstname }} {{ $staff->lastname }}</strong> from the staff records?</p>
                                                <p class="text-danger"><small>This action cannot be undone. All related records will also be affected.</small></p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <form action="{{ route('staff.destroy', $staff) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bx bx-folder-open fs-1"></i>
                                    <p class="mt-2">No staff records found</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($staffMembers->hasPages())
        <div class="card-footer">
            {{ $staffMembers->appends(request()->except('page'))->links() }}
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    });
</script>
@endpush
