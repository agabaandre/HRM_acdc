@extends('layouts.app')

@section('title', 'Memos')

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title">Memos</h4>
                        <a href="{{ route('memos.create') }}" class="btn btn-primary">Create New Memo</a>
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
                                    @forelse($memos as $memo)
                                        <tr>
                                            <td>{{ $memo->id }}</td>
                                            <td>{{ $memo->title }}</td>
                                            <td>{{ $memo->country }}</td>
                                            <td>{{ $memo->workflow->workflow_name }}</td>
                                            <td>{{ date('M d, Y H:i', strtotime($memo->created_at)) }}</td>
                                            <td>
                                                <a href="{{ route('memos.show', $memo->id) }}"
                                                    class="btn btn-info btn-sm">View</a>
                                                <a href="{{ route('memos.edit', $memo->id) }}"
                                                    class="btn btn-warning btn-sm">Edit</a>
                                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal"
                                                    data-bs-target="#deleteMemo{{ $memo->id }}">Delete</button>

                                                <!-- Delete Modal -->
                                                <div class="modal fade" id="deleteMemo{{ $memo->id }}" tabindex="-1"
                                                    aria-labelledby="deleteMemoLabel" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="deleteMemoLabel">Confirm Delete</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                                    aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                Are you sure you want to delete this memo:
                                                                <strong>{{ $memo->title }}</strong>?
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary"
                                                                    data-bs-dismiss="modal">Cancel</button>
                                                                <form action="{{ route('memos.destroy', $memo->id) }}"
                                                                    method="POST">
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
                                            <td colspan="6" class="text-center">No memos found</td>
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