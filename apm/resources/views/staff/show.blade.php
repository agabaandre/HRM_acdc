@extends('layouts.app')

@section('title', 'Staff Details')

@section('header', 'Staff Details')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('staff.edit', $staff) }}" class="btn btn-warning">
        <i class="bx bx-edit"></i> Edit
    </a>
    <a href="{{ route('staff.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to List
    </a>
</div>
@endsection

@section('content')
<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm">
            <div class="card-body text-center p-4">
                @if($staff->profile_photo)
                    <img src="{{ asset('storage/' . $staff->profile_photo) }}" class="rounded-circle mb-3" width="150" height="150" alt="Profile Photo">
                @else
                    <div class="avatar avatar-lg bg-primary rounded-circle mb-3 mx-auto">
                        <span class="avatar-text fs-2">{{ strtoupper(substr($staff->fname, 0, 1)) }}{{ strtoupper(substr($staff->lname, 0, 1)) }}</span>
                    </div>
                @endif
                
                <h4 class="mb-1">{{ $staff->fname }} {{ $staff->oname ? $staff->oname . ' ' : '' }}{{ $staff->lname }}</h4>
                <p class="text-muted mb-3">{{ $staff->title }}</p>
                
                <div class="d-flex justify-content-center mb-3">
                    @if($staff->active == 1)
                        <span class="badge bg-success px-3 py-2 fs-6">Active</span>
                    @else
                        <span class="badge bg-danger px-3 py-2 fs-6">Inactive</span>
                    @endif
                </div>
                
                <div class="d-grid gap-2 mt-4">
                    <a href="mailto:{{ $staff->email }}" class="btn btn-outline-primary">
                        <i class="bx bx-envelope me-2"></i> Send Email
                    </a>
                    @if($staff->tel_1)
                    <a href="tel:{{ $staff->tel_1 }}" class="btn btn-outline-info">
                        <i class="bx bx-phone me-2"></i> Call
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <!-- Personal Information Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bx bx-user me-2"></i>Personal Information</h5>
            </div>
            <div class="card-body p-4">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Staff ID</h6>
                        <p class="fs-5">{{ $staff->staff_id }}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Full Name</h6>
                        <p class="fs-5">{{ $staff->fname }} {{ $staff->oname ? $staff->oname . ' ' : '' }}{{ $staff->lname }}</p>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Email</h6>
                        <p class="fs-5">{{ $staff->email }}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Phone</h6>
                        <p class="fs-5">{{ $staff->tel_1 ?? 'N/A' }}</p>
                    </div>
                </div>
                
                <div class="row mb-0">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Gender</h6>
                        <p class="fs-5">{{ $staff->gender ?? 'N/A' }}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Date of Birth</h6>
                        <p class="fs-5">{{ $staff->date_of_birth ? $staff->date_of_birth->format('M d, Y') : 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Employment Information Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bx bx-briefcase me-2"></i>Employment Information</h5>
            </div>
            <div class="card-body p-4">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Division</h6>
                        <p class="fs-5">{{ $staff->division->division_name ?? 'N/A' }}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Directorate</h6>
                        <p class="fs-5">{{ $staff->directorate->name ?? 'N/A' }}</p>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Duty Station</h6>
                        <p class="fs-5">{{ $staff->dutyStation->name ?? 'N/A' }}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Job Title</h6>
                        <p class="fs-5">{{ $staff->title ?? 'N/A' }}</p>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Designation</h6>
                        <p class="fs-5">{{ $staff->designation ?? 'N/A' }}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Employment Status</h6>
                        <p class="fs-5">{{ $staff->employment_status ?? 'N/A' }}</p>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Hire Date</h6>
                        <p class="fs-5">{{ $staff->hire_date ? $staff->hire_date->format('M d, Y') : 'N/A' }}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Supervisor</h6>
                        <p class="fs-5">
                            @if($staff->supervisor)
                                {{ $staff->supervisor->firstname }} {{ $staff->supervisor->lastname }}
                            @else
                                N/A
                            @endif
                        </p>
                    </div>
                </div>
                
                @if($staff->remarks)
                <div class="mb-0">
                    <h6 class="text-muted mb-1">Remarks</h6>
                    <div class="p-3 bg-light rounded">
                        {{ $staff->remarks }}
                    </div>
                </div>
                @endif
            </div>
        </div>
        
        <!-- System Information Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bx bx-cog me-2"></i>System Information</h5>
            </div>
            <div class="card-body p-4">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">System Access</h6>
                        <p class="fs-5">
                            @if($staff->access_level == 3)
                                <span class="badge bg-danger">Administrator</span>
                            @elseif($staff->access_level == 2)
                                <span class="badge bg-warning">Advanced User</span>
                            @elseif($staff->access_level == 1)
                                <span class="badge bg-info">Basic User</span>
                            @else
                                <span class="badge bg-secondary">No Access</span>
                            @endif
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Last Updated</h6>
                        <p class="fs-5">{{ $staff->updated_at->format('M d, Y g:i A') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Related Activities/Transactions Card, if applicable -->
@if(isset($activities) && $activities->count() > 0)
<div class="card shadow-sm mt-4">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="bx bx-list-check me-2 text-primary"></i>Recent Activities</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Activity ID</th>
                        <th>Description</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($activities as $activity)
                    <tr>
                        <td>{{ $activity->id }}</td>
                        <td>{{ Str::limit($activity->activity_desc, 50) }}</td>
                        <td>{{ $activity->created_at->format('M d, Y') }}</td>
                        <td>
                            <span class="badge bg-{{ $activity->status == 'completed' ? 'success' : ($activity->status == 'in-progress' ? 'warning' : 'info') }}">
                                {{ ucfirst($activity->status) }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('activities.show', $activity) }}" class="btn btn-sm btn-info">
                                <i class="bx bx-show"></i> View
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Staff Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong>{{ $staff->fname }} {{ $staff->lname }}</strong> from the staff records?</p>
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
@endsection
