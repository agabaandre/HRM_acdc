@extends('layouts.app')

@section('title', 'Pending Approvals')

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Pending Approvals</h4>
                    </div>
                    <div class="card-body">
                        @if(session('success'))
                            <div class="alert alert-success">
                                {{ session('success') }}
                            </div>
                        @endif

                        @if(session('error'))
                            <div class="alert alert-danger">
                                {{ session('error') }}
                            </div>
                        @endif

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Country</th>
                                        <th>Workflow</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($pendingApprovals as $memo)
                                        <tr>
                                            <td>{{ $memo->id }}</td>
                                            <td>{{ $memo->title }}</td>
                                            <td>{{ $memo->country }}</td>
                                            <td>{{ $memo->workflow->workflow_name }}</td>
                                            <td>{{ date('M d, Y H:i', strtotime($memo->created_at)) }}</td>
                                            <td>
                                                <a href="{{ route('approvals.show', $memo->id) }}"
                                                    class="btn btn-primary btn-sm">Review</a>
                                                <a href="{{ route('approvals.history', $memo->id) }}"
                                                    class="btn btn-info btn-sm">History</a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center">No pending approvals found</td>
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
@endsection