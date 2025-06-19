@extends('layouts.app')

@section('title', 'Cost Items')

@section('header', 'Cost Items Management')

@section('header-actions')
    <a href="{{ route('cost-items.create') }}" class="btn btn-success">
        <i class="bx bx-plus"></i> Add New Cost Item
    </a>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-md-0"><i class="bx bx-dollar me-2 text-primary"></i>All Cost Items</h5>
            </div>
            <div class="col-md-6">
                <form class="d-flex gap-2 justify-content-md-end">
                    <div class="input-group">
                        <input type="text"
                               class="form-control"
                               id="searchInput"
                               placeholder="Search cost items...">
                        <button class="btn btn-outline-primary" type="button">
                            <i class="bx bx-search"></i>
                        </button>
                    </div>

                    <select class="form-select" id="typeFilter">
                        <option value="">All Types</option>
                        <option value="Individual Cost">Individual Cost</option>
                        <option value="Other Cost">Other Cost</option>
                    </select>
                </form>
            </div>
        </div>
    </div>
    
    <div class="card-body p-0">
        @if (session('success'))
            <div class="alert alert-success m-3">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
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
                        <th>Cost Type</th>
                        <th>Created At</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @php $count = 1; @endphp
                    @forelse ($costItems as $item)
                        <tr data-type="{{ $item->cost_type }}">
                            <td>{{ $count++ }}</td>
                            <td>
                                <div class="fw-bold">{{ $item->name }}</div>
                            </td>
                            <td>
                                <span class="badge {{ $item->cost_type == 'Individual Cost' ? 'bg-primary' : 'bg-secondary' }}">
                                    {{ $item->cost_type }}
                                </span>
                            </td>
                            <td>{{ $item->created_at->format('M d, Y') }}</td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <a href="{{ route('cost-items.show', $item->id) }}"
                                       class="btn btn-sm btn-info"
                                       data-bs-toggle="tooltip"
                                       title="View Details">
                                        <i class="bx bx-show"></i>
                                    </a>
                                    <a href="{{ route('cost-items.edit', $item->id) }}"
                                       class="btn btn-sm btn-warning"
                                       data-bs-toggle="tooltip"
                                       title="Edit">
                                        <i class="bx bx-edit"></i>
                                    </a>
                                    <form action="{{ route('cost-items.destroy', $item->id) }}" 
                                          method="POST" 
                                          class="d-inline"
                                          onsubmit="return confirm('Are you sure you want to delete this item?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="btn btn-sm btn-danger"
                                                data-bs-toggle="tooltip"
                                                title="Delete">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bx bx-package fs-1"></i>
                                    <p class="mt-2 mb-0">No cost items found.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    
    @if($costItems->hasPages())
        <div class="card-footer">
            {{ $costItems->links() }}
        </div>
    @endif
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Filter functionality
        $('#searchInput').on('keyup', function() {
            var value = $(this).val().toLowerCase();
            $('tbody tr').filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
            });
        });

        $('#typeFilter').on('change', function() {
            var type = $(this).val();
            if (type === '') {
                $('tbody tr').show();
            } else {
                $('tbody tr').each(function() {
                    var rowType = $(this).data('type');
                    $(this).toggle(rowType === type);
                });
            }
        });
    });
</script>
@endpush
@endsection
