@extends('layouts.app')

@section('title', 'Divisions')

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title">Divisions</h4>
                        <a href="{{ route('divisions.create') }}" class="btn btn-primary">Create New Division</a>
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
                                        <th>Name</th>
                                        <th>Division Head</th>
                                        <th>Focal Person</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($divisions as $division)
                                        <tr>
                                            <td>{{ $division->division_id }}</td>
                                            <td>{{ $division->division_name }}</td>
                                            <td>{{ $division->division_head }}</td>
                                            <td>{{ $division->focal_person }}</td>
                                            <td>
                                                <a href="{{ route('divisions.show', $division->division_id) }}"
                                                    class="btn btn-info btn-sm">View</a>
                                                <a href="{{ route('divisions.edit', $division->division_id) }}"
                                                    class="btn btn-warning btn-sm">Edit</a>
                                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal"
                                                    data-bs-target="#deleteDivision{{ $division->division_id }}">Delete</button>

                                                <!-- Delete Modal -->
                                                <div class="modal fade" id="deleteDivision{{ $division->division_id }}"
                                                    tabindex="-1" aria-labelledby="deleteDivisionLabel" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="deleteDivisionLabel">Confirm Delete
                                                                </h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                                    aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                Are you sure you want to delete this division:
                                                                <strong>{{ $division->division_name }}</strong>?
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary"
                                                                    data-bs-dismiss="modal">Cancel</button>
                                                                <form
                                                                    action="{{ route('divisions.destroy', $division->division_id) }}"
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
                                            <td colspan="5" class="text-center">No divisions found</td>
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