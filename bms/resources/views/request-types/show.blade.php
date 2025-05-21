@extends('layouts.app')

@section('title', 'Request Type Details')

@section('header', 'Request Type Details')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('request-types.edit', $requestType) }}" class="btn btn-warning">
        <i class="bx bx-edit"></i> Edit
    </a>
    <a href="{{ route('request-types.index') }}" class="btn btn-outline-secondary">
        <i class="bx bx-arrow-back"></i> Back to List
    </a>
</div>
@endsection

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bx bx-info-circle me-2"></i>Request Type Information</h5>
            </div>
            <div class="card-body p-4">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-1">Request Type Name</h6>
                        <h4 class="mb-0">{{ $requestType->request_type }}</h4>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <h6 class="text-muted mb-1">Status</h6>
                        @if($requestType->is_active)
                            <span class="badge bg-success fs-6 px-3 py-2">Active</span>
                        @else
                            <span class="badge bg-danger fs-6 px-3 py-2">Inactive</span>
                        @endif
                    </div>
                </div>

                @if($requestType->description)
                <div class="mb-4">
                    <h6 class="text-muted mb-2">Description</h6>
                    <div class="p-3 bg-light rounded">
                        {{ $requestType->description }}
                    </div>
                </div>
                @endif

                @if($requestType->workflow)
                <div class="mb-0">
                    <h6 class="text-muted mb-1">Associated Workflow</h6>
                    <div class="d-flex align-items-center mt-2">
                        <span class="badge bg-primary me-2">
                            <i class="bx bx-git-branch me-1"></i> Workflow
                        </span>
                        <h5 class="mb-0">{{ $requestType->workflow->workflow_name }}</h5>
                    </div>
                </div>
                @endif
            </div>
        </div>

        @if(isset($activities) && $activities->count() > 0)
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bx bx-list-check me-2 text-primary"></i>Related Activities</h5>
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

        @if(isset($memos) && $memos->count() > 0)
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bx bx-file me-2 text-primary"></i>Related Memos</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Memo ID</th>
                                <th>Subject</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($memos as $memo)
                            <tr>
                                <td>{{ $memo->id }}</td>
                                <td>{{ Str::limit($memo->subject, 50) }}</td>
                                <td>{{ $memo->created_at->format('M d, Y') }}</td>
                                <td>
                                    <span class="badge bg-{{ $memo->status == 'approved' ? 'success' : ($memo->status == 'pending' ? 'warning' : 'info') }}">
                                        {{ ucfirst($memo->status) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('memos.show', $memo) }}" class="btn btn-sm btn-info">
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
    </div>
    
    <div class="col-md-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bx bx-time me-2 text-primary"></i>Timestamps</h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Created At</span>
                        <span>{{ $requestType->created_at->format('Y-m-d H:i') }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Last Updated</span>
                        <span>{{ $requestType->updated_at->format('Y-m-d H:i') }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bx bx-cog me-2 text-primary"></i>Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="{{ route('request-types.edit', $requestType) }}" class="btn btn-warning">
                        <i class="bx bx-edit me-2"></i> Edit Request Type
                    </a>
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                        <i class="bx bx-trash me-2"></i> Delete Request Type
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Request Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the request type <strong>{{ $requestType->request_type }}</strong>?</p>
                <p class="text-danger"><small>This action cannot be undone. Please ensure this request type is not in use before deleting.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="{{ route('request-types.destroy', $requestType) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
