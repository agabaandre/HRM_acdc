@extends('layouts.app')

@section('title', 'Memo Details')

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title">Memo Details</h4>
                        <a href="{{ route('memos.index') }}" class="btn btn-secondary">Back to Memos</a>
                    </div>
                    <div class="card-body">
                        @if(session('success'))
                            <div class="alert alert-success">
                                {{ session('success') }}
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
                                <div class="col-md-3 fw-bold">Document ID:</div>
                                <div class="col-md-9">{{ $memo->document_id }}</div>
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

                        <div class="d-flex gap-2">
                            <a href="{{ route('memos.edit', $memo->id) }}" class="btn btn-warning">Edit Memo</a>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal"
                                data-bs-target="#deleteMemo">Delete Memo</button>
                        </div>

                        <!-- Delete Modal -->
                        <div class="modal fade" id="deleteMemo" tabindex="-1" aria-labelledby="deleteMemoLabel"
                            aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="deleteMemoLabel">Confirm Delete</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        Are you sure you want to delete this memo: <strong>{{ $memo->title }}</strong>?
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">Cancel</button>
                                        <form action="{{ route('memos.destroy', $memo->id) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection