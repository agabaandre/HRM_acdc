@extends('layouts.app')

@section('title', 'Fund Types')

@section('header', 'Fund Types')

@section('header-actions')
<a href="{{ route('fund-types.create') }}" class="btn btn-success">
    <i class="bx bx-plus"></i> Add Fund Type
</a>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bx bx-list-ul me-2 text-primary"></i>All Fund Types</h5>
        <div>
            <form action="{{ route('fund-types.index') }}" method="GET" class="d-flex">
                <input type="text" name="search" class="form-control me-2" placeholder="Search fund types..." value="{{ request('search') }}">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bx bx-search"></i>
                </button>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Fund Codes</th>
                        <th>Created At</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fundTypes as $fundType)
                        <tr>
                            <td>{{ $fundType->id }}</td>
                            <td>{{ $fundType->name }}</td>
                            <td>
                                <span class="badge bg-info">{{ $fundType->fundCodes->count() }} Codes</span>
                            </td>
                            <td>{{ $fundType->created_at->format('Y-m-d') }}</td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <a href="{{ route('fund-types.show', $fundType) }}" class="btn btn-sm btn-outline-info" data-bs-toggle="tooltip" title="View">
                                        <i class="bx bx-show"></i>
                                    </a>
                                    <a href="{{ route('fund-types.edit', $fundType) }}" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                        <i class="bx bx-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="d-flex flex-column align-items-center">
                                    <div class="mb-3">
                                        <i class="bx bx-folder-open text-muted" style="font-size: 3rem;"></i>
                                    </div>
                                    <h5 class="text-muted mb-3">No fund types found</h5>
                                    <p class="text-muted mb-4">Get started by adding your first fund type</p>
                                    <a href="{{ route('fund-types.create') }}" class="btn btn-primary">
                                        <i class="bx bx-plus"></i> Add Fund Type
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($fundTypes->hasPages())
        <div class="card-footer">
            {{ $fundTypes->links() }}
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
