@extends('layouts.app')

@section('title', 'Directorates')

@section('header', 'Directorates')

@section('header-actions')
<a href="{{ route('directorates.create') }}" class="btn btn-success">
    <i class="bx bx-plus"></i> Add Directorate
</a>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-3"><i class="bx bx-list-ul me-2 text-primary"></i>Directorates Management</h5>
        
        <form action="{{ route('directorates.index') }}" method="GET" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bx bx-filter-alt"></i>
                </button>
            </div>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Updated At</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($directorates as $directorate)
                        <tr>
                            <td>{{ $directorate->id }}</td>
                            <td><strong>{{ $directorate->name }}</strong></td>
                            <td>
                                @if($directorate->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-danger">Inactive</span>
                                @endif
                            </td>
                            <td>{{ $directorate->created_at }}</td>
                            <td>{{ $directorate->updated_at }}</td>
                            <td class="text-end">
                                <a href="{{ route('directorates.show', $directorate) }}" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="View">
                                    <i class="bx bx-show"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bx bx-folder-open fs-1"></i>
                                    <p class="mt-2">No directorates found</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($directorates->hasPages())
        <div class="card-footer">
            {{ $directorates->appends(request()->except('page'))->links() }}
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
