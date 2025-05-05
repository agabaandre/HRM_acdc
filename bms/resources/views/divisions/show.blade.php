@extends('layouts.app')

@section('title', 'Division Details')

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="card-title">Division Details</h4>
                        <a href="{{ route('divisions.index') }}" class="btn btn-secondary">Back to Divisions</a>
                    </div>
                    <div class="card-body">
                        @if(session('success'))
                            <div class="alert alert-success">
                                {{ session('success') }}
                            </div>
                        @endif

                        <div class="mb-4">
                            <h5 class="border-bottom pb-2">Division Information</h5>
                            <div class="row">
                                <div class="col-md-3 fw-bold">ID:</div>
                                <div class="col-md-9">{{ $division->division_id }}</div>
                            </div>
                            <div class="row">
                                <div class="col-md-3 fw-bold">Name:</div>
                                <div class="col-md-9">{{ $division->division_name }}</div>
                            </div>
                            <div class="row">
                                <div class="col-md-3 fw-bold">Division Head (User ID):</div>
                                <div class="col-md-9">{{ $division->division_head }}</div>
                            </div>
                            <div class="row">
                                <div class="col-md-3 fw-bold">Focal Person (User ID):</div>
                                <div class="col-md-9">{{ $division->focal_person }}</div>
                            </div>
                            <div class="row">
                                <div class="col-md-3 fw-bold">Admin Assistant (User ID):</div>
                                <div class="col-md-9">{{ $division->admin_assistant }}</div>
                            </div>
                            <div class="row">
                                <div class="col-md-3 fw-bold">Finance Officer (User ID):</div>
                                <div class="col-md-9">{{ $division->finance_officer }}</div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <a href="{{ route('divisions.edit', $division->division_id) }}" class="btn btn-warning">Edit
                                Division</a>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal"
                                data-bs-target="#deleteDivision">Delete Division</button>
                        </div>

                        <!-- Delete Modal -->
                        <div class="modal fade" id="deleteDivision" tabindex="-1" aria-labelledby="deleteDivisionLabel"
                            aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="deleteDivisionLabel">Confirm Delete</h5>
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
                                        <form action="{{ route('divisions.destroy', $division->division_id) }}"
                                            method="POST">
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