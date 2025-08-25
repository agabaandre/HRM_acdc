@extends('layouts.app')

@section('title', 'Divisions')

@section('header', 'Divisions')

@section('header-actions')
<!-- <a href="{{ route('divisions.create') }}" class="btn btn-success">
    <i class="bx bx-plus"></i> Add Division
</a> -->
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bx bx-building-house me-2 text-primary"></i>All Divisions</h5>
        <div>
            <form action="{{ route('divisions.index') }}" method="GET" class="d-flex">
                <input type="text" name="search" class="form-control me-2" placeholder="Search divisions..." value="{{ request('search') }}">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bx bx-search"></i>
                </button>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        @if(session('success'))
            <div class="alert alert-success m-3">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger m-3">
                {{ session('error') }}
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Division Head</th>
                        <th>Focal Person</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($divisions as $division)
                        <tr>
                            <td>{{ $division->id }}</td>
                            <td>{{ $division->division_name }}</td>
                            <td>{{ $division->divisionHead ? $division->divisionHead->fname . ' ' . $division->divisionHead->lname : 'N/A' }}</td>
                            <td>{{ $division->focalPerson ? $division->focalPerson->fname . ' ' . $division->focalPerson->lname : 'N/A' }}</td>
                          
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <a href="{{ route('divisions.show', $division->id) }}" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="View">
                                        <i class="bx bx-show"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bx bx-folder-open fs-1"></i>
                                    <p class="mt-2">No divisions found</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if(isset($divisions) && method_exists($divisions, 'hasPages') && $divisions->hasPages())
        <div class="card-footer">
            {{ $divisions->links() }}
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    });
</script>
@endpush