@extends('layouts.app')

@section('title', 'Matrix Status')

@section('header', 'Matrix Approval Status')

@section('content')
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- Header Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bx bx-info-circle me-2 text-primary"></i>Approval Status & Workflow
                    </h5>
                    <div>
                        <a href="{{ route('matrices.show', $matrix) }}" class="btn btn-outline-primary btn-sm">
                            <i class="bx bx-arrow-back me-1"></i> Back to Matrix
                        </a>
                        @if($matrix->overall_status === 'approved')
                            <a href="{{ route('matrices.show', $matrix) }}" class="btn btn-success btn-sm">
                                <i class="bx bx-check-circle me-1"></i> View Approved Matrix
                            </a>
                        @endif
                        @if(still_with_creator($matrix))
                            <a href="{{ route('matrices.edit', $matrix) }}" class="btn btn-warning btn-sm">
                                <i class="bx bx-edit me-1"></i> Edit Matrix
                            </a>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <strong class="text-muted">Matrix ID:</strong> 
                                <span class="fw-bold">#{{ $matrix->id }}</span>
                            </div>
                            <div class="mb-3">
                                <strong class="text-muted">Title:</strong> 
                                <span class="fw-bold">{{ $matrix->title ?? 'Not specified' }}</span>
                            </div>
                            <div class="mb-3">
                                <strong class="text-muted">Focal Person:</strong> 
                                <span class="fw-bold">{{ $matrix->focalPerson ? ($matrix->focalPerson->fname . ' ' . $matrix->focalPerson->lname) : 'Not assigned' }}</span>
                            </div>
                            <div class="mb-3">
                                <strong class="text-muted">Division:</strong> 
                                <span class="fw-bold">{{ $matrix->division ? $matrix->division->division_name : 'Not assigned' }}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <strong class="text-muted">Status:</strong> 
                                <span class="badge bg-{{ $matrix->overall_status === 'approved' ? 'success' : ($matrix->overall_status === 'pending' ? 'warning' : ($matrix->overall_status === 'rejected' ? 'danger' : 'secondary')) }} fs-6">
                                    {{ ucfirst($matrix->overall_status ?? 'draft') }}
                                </span>
                            </div>
                            <div class="mb-3">
                                <strong class="text-muted">Approval Level:</strong> 
                                <span class="badge bg-primary fs-6">{{ $matrix->approval_level ?? 0 }}</span>
                            </div>
                            <div class="mb-3">
                                <strong class="text-muted">Current Workflow:</strong> 
                                <span class="fw-bold">{{ $matrix->forwardWorkflow->workflow_name ?? 'Not assigned' }}</span>
                            </div>
                            <div class="mb-3">
                                <strong class="text-muted">Created:</strong> 
                                <span class="fw-bold">{{ $matrix->created_at ? $matrix->created_at->format('M d, Y H:i') : 'Not available' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Supervisor Information -->
            @if($matrix->overall_status !== 'approved' && $matrix->overall_status !== 'rejected' && $matrix->current_actor)
                <div class="card shadow-sm mb-4" style="border-left: 4px solid #3b82f6;">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="bx bx-user-check me-2 text-primary"></i>Current Supervisor Information
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <strong class="text-muted">Supervisor Name:</strong> 
                                    <span class="fw-bold text-primary fs-5">{{ $matrix->current_actor->fname . ' ' . $matrix->current_actor->lname }}</span>
                                </div>
                                @if($matrix->current_actor->job_name)
                                    <div class="mb-3">
                                        <strong class="text-muted">Job Title:</strong> 
                                        <span class="fw-bold">{{ $matrix->current_actor->job_name }}</span>
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-6">
                                @if($matrix->current_actor->division_name)
                                    <div class="mb-3">
                                        <strong class="text-muted">Division:</strong> 
                                        <span class="fw-bold">{{ $matrix->current_actor->division_name }}</span>
                                    </div>
                                @endif
                                @if($matrix->workflow_definition)
                                    <div class="mb-3">
                                        <strong class="text-muted">Approval Role:</strong> 
                                        <span class="badge bg-info">{{ $matrix->workflow_definition->role ?? 'Not specified' }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="mt-3 p-3 bg-primary bg-opacity-10 rounded">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bx bx-info-circle text-primary"></i>
                                <span class="text-primary fw-medium">This matrix is currently awaiting approval from the supervisor above.</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Approval Levels Overview -->
            @if(!empty($approvalLevels))
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="bx bx-layer-group me-2 text-primary"></i>Approval Levels Overview
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach($approvalLevels as $level)
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card border-0 h-100 {{ $level['is_current'] ? 'border-primary border-2' : '' }}" 
                                         style="background: {{ $level['is_completed'] ? '#d1fae5' : ($level['is_pending'] ? '#fef3c7' : '#f3f4f6') }};">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <span class="badge bg-{{ $level['is_completed'] ? 'success' : ($level['is_pending'] ? 'warning' : 'secondary') }} fs-6">
                                                    Level {{ $level['order'] }}
                                                </span>
                                                @if($level['is_current'])
                                                    <span class="badge bg-primary">Current</span>
                                                @elseif($level['is_completed'])
                                                    <span class="badge bg-success">✓ Completed</span>
                                                @elseif($level['is_pending'])
                                                    <span class="badge bg-warning">⏳ Pending</span>
                                                @else
                                                    <span class="badge bg-secondary">Waiting</span>
                                                @endif
                                            </div>
                                            
                                            <h6 class="fw-bold mb-2">{{ $level['role'] ?? 'Role Not Specified' }}</h6>
                                            
                                            @if($level['approver'])
                                                <div class="mb-2">
                                                    <small class="text-muted">Approver:</small><br>
                                                    <strong>{{ $level['approver']->fname . ' ' . $level['approver']->lname }}</strong>
                                                </div>
                                            @else
                                                <div class="mb-2">
                                                    <small class="text-muted">Approver:</small><br>
                                                    <span class="text-muted">Not assigned</span>
                                                </div>
                                            @endif
                                            
                                            @if($level['is_division_specific'])
                                                <div class="mb-2">
                                                    <span class="badge bg-info">Division Specific</span>
                                                    @if($level['division_reference'])
                                                        <br><small class="text-muted">{{ ucfirst(str_replace('_', ' ', $level['division_reference'])) }}</small>
                                                    @endif
                                                </div>
                                            @endif
                                            
                                            @if($level['category'])
                                                <div class="mb-2">
                                                    <small class="text-muted">Category:</small><br>
                                                    <span class="fw-medium">{{ $level['category'] }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        <!-- Current Level Summary -->
                        @if($matrix->overall_status !== 'approved' && $matrix->overall_status !== 'rejected')
                            <div class="mt-4 p-3 bg-light rounded">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong class="text-muted">Current Approval Level:</strong> 
                                        <span class="badge bg-primary fs-6">{{ $matrix->approval_level ?? 0 }}</span>
                                    </div>
                                    <div class="col-md-6">
                                        <strong class="text-muted">Total Levels:</strong> 
                                        <span class="badge bg-secondary fs-6">{{ count($approvalLevels) }}</span>
                                    </div>
                                </div>
                                @if($matrix->workflow_definition)
                                    <div class="mt-2">
                                        <strong class="text-muted">Current Role:</strong> 
                                        <span class="fw-bold">{{ $matrix->workflow_definition->role ?? 'Not specified' }}</span>
                                    </div>
                                    @if($matrix->current_actor)
                                        <div class="mt-2">
                                            <strong class="text-muted">Current Approver:</strong> 
                                            <span class="fw-bold">{{ $matrix->current_actor->fname . ' ' . $matrix->current_actor->lname }}</span>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Approval Actions -->
            @if(can_take_action($matrix))
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="bx bx-check-circle me-2 text-success"></i>Take Action - Level {{ $matrix->approval_level ?? 0 }}
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-3">
                            <i class="bx bx-info-circle me-2"></i>
                            <strong>Current Level:</strong> {{ $matrix->approval_level ?? 0 }}
                            @if($matrix->workflow_definition)
                                - <strong>Role:</strong> {{ $matrix->workflow_definition->role ?? 'Not specified' }}
                            @endif
                        </div>
                        
                        <form action="{{ route('matrices.status', $matrix) }}" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="comment" class="form-label">Comments (Optional)</label>
                                        <textarea class="form-control" id="comment" name="comment" rows="3" placeholder="Add any comments about your decision..."></textarea>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="action" value="approved" class="btn btn-success">
                                            <i class="bx bx-check me-1"></i> Approve
                                        </button>
                                        <button type="submit" name="action" value="returned" class="btn btn-warning">
                                            <i class="bx bx-undo me-1"></i> Return for Revision
                                        </button>
                                        <button type="submit" name="action" value="rejected" class="btn btn-danger">
                                            <i class="bx bx-x me-1"></i> Reject
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            <!-- Approval Trail -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="bx bx-history me-2 text-primary"></i>Approval Trail
                    </h6>
                </div>
                <div class="card-body">
                    @php 
                        $approvalTrails = $matrix->approvalTrails()->orderBy('created_at', 'asc')->get();
                    @endphp
                    
                    @if($approvalTrails->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Action</th>
                                        <th>Approver</th>
                                        <th>Comments</th>
                                        <th>Approval Level</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($approvalTrails as $trail)
                                        <tr>
                                            <td>
                                                <small class="text-muted">
                                                    {{ $trail->created_at->format('M d, Y') }}<br>
                                                    <strong>{{ $trail->created_at->format('H:i') }}</strong>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $trail->action === 'approved' ? 'success' : ($trail->action === 'returned' ? 'warning' : ($trail->action === 'rejected' ? 'danger' : 'info')) }}">
                                                    {{ ucfirst($trail->action) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($trail->staff)
                                                    <strong>{{ $trail->staff->fname . ' ' . $trail->staff->lname }}</strong><br>
                                                    <small class="text-muted">{{ $trail->staff->job_name ?? 'Staff' }}</small>
                                                    @if($trail->staff->division_name)
                                                        <br><small class="text-muted">{{ $trail->staff->division_name }}</small>
                                                    @endif
                                                @else
                                                    <span class="text-muted">Unknown</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($trail->remarks)
                                                    <span class="text-dark">{{ $trail->remarks }}</span>
                                                @else
                                                    <span class="text-muted">No comments</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">{{ $trail->approval_order ?? 'N/A' }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-secondary text-center">
                            <i class="bx bx-info-circle me-2"></i>
                            No approval actions taken yet. This matrix is still in draft status.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
