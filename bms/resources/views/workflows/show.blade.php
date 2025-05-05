@extends('layouts.app')

@section('title', 'Workflow Details')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title">Workflow Details</h4>
                    <a href="{{ route('workflows.index') }}" class="btn btn-secondary">Back to Workflows</a>
                </div>
                <div class="card-body">
                    @if(session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                    @endif

                    <div class="mb-4">
                        <h5 class="border-bottom pb-2">Basic Information</h5>
                        <div class="row">
                            <div class="col-md-3 fw-bold">ID:</div>
                            <div class="col-md-9">{{ $workflow->id }}</div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 fw-bold">Name:</div>
                            <div class="col-md-9">{{ $workflow->workflow_name }}</div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 fw-bold">Description:</div>
                            <div class="col-md-9">{{ $workflow->Description }}</div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 fw-bold">Status:</div>
                            <div class="col-md-9">
                                @if($workflow->is_active)
                                <span class="badge bg-success">Active</span>
                                @else
                                <span class="badge bg-danger">Inactive</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="border-bottom pb-2">Workflow Definitions</h5>
                            <a href="{{ route('workflows.add-definition', $workflow->id) }}" class="btn btn-primary">Add Definition</a>
                        </div>

                        <div class="table-responsive mt-3">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Role</th>
                                        <th>Approval Order</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($workflowDefinitions as $definition)
                                    <tr>
                                        <td>{{ $definition->id }}</td>
                                        <td>{{ $definition->role }}</td>
                                        <td>{{ $definition->approval_order }}</td>
                                        <td>
                                            @if($definition->is_enabled)
                                            <span class="badge bg-success">Enabled</span>
                                            @else
                                            <span class="badge bg-danger">Disabled</span>
                                            @endif
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-warning btn-sm">Edit</button>
                                            <button type="button" class="btn btn-danger btn-sm">Delete</button>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="text-center">No workflow definitions found</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
