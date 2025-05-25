@extends('layouts.app')

@section('title', 'Review Memo')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title">Review Memo</h4>
                    <a href="{{ route('approvals.index') }}" class="btn btn-secondary">Back to Approvals</a>
                </div>
                <div class="card-body">
                    @if(session('error'))
                    <div class="alert alert-danger">
                        {{ session('error') }}
                    </div>
                    @endif

                    <div class="mb-4">
                        <h5 class="border-bottom pb-2">Memo Information</h5>
                        <div class="row">
                            <div class="col-md-3 fw-bold">ID:</div>
                            <div class="col-md-9">{{ $memo->id }}</div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 fw-bold">Title:</div>
                            <div class="col-md-9">{{ $memo->title }}</div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 fw-bold">Country:</div>
                            <div class="col-md-9">{{ $memo->country }}</div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 fw-bold">Workflow:</div>
                            <div class="col-md-9">{{ $workflow->workflow_name }}</div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 fw-bold">Created:</div>
                            <div class="col-md-9">{{ date('M d, Y H:i', strtotime($memo->created_at)) }}</div>
                        </div>
                        @if($memo->update_at)
                        <div class="row">
                            <div class="col-md-3 fw-bold">Last Updated:</div>
                            <div class="col-md-9">{{ date('M d, Y H:i', strtotime($memo->update_at)) }}</div>
                        </div>
                        @endif
                    </div>

                    <div class="mb-4">
                        <h5 class="border-bottom pb-2">Description</h5>
                        <div class="p-2 border rounded">
                            {{ $memo->description }}
                        </div>
                    </div>

                    <div class="mb-4">
                        <h5 class="border-bottom pb-2">Workflow Steps</h5>
                        <div class="table-responsive mt-3">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($workflowDefinitions as $definition)
                                    <tr>
                                        <td>{{ $definition->approval_order }}</td>
                                        <td>{{ $definition->role }}</td>
                                        <td>
                                            <span class="badge bg-warning">Pending</span>
                                            <!-- In a real implementation, you would check the approval status here -->
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="3" class="text-center">No workflow steps defined</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if($canApprove)
                    <div class="mb-4">
                        <h5 class="border-bottom pb-2">Take Action</h5>
                        <form action="{{ route('approvals.approve', $memo->id) }}" method="POST">
                            @csrf

                            <div class="mb-3">
                                <label for="comments" class="form-label">Comments</label>
                                <textarea class="form-control" id="comments" name="comments" rows="3"></textarea>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" name="action" value="approve" class="btn btn-success">Approve</button>
                                <button type="submit" name="action" value="return" class="btn btn-warning">Return for Correction</button>
                                <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
                            </div>
                        </form>
                    </div>
                    @else
                    <div class="alert alert-info">
                        You do not have permission to approve this memo.
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
