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
        <h5 class="mb-0">Cost Items</h5>
    </div>
    
    <div class="card-body">
        
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
                                       class="btn btn-sm btn-outline-info"
                                       data-bs-toggle="tooltip"
                                       title="View">
                                        <i class="bx bx-show"></i>
                                    </a>
                                    <a href="{{ route('cost-items.edit', $item->id) }}"
                                       class="btn btn-sm btn-outline-primary"
                                       data-bs-toggle="tooltip"
                                       title="Edit">
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
                                        <i class="bx bx-package text-muted" style="font-size: 3rem;"></i>
                                    </div>
                                    <h5 class="text-muted mb-3">No cost items found</h5>
                                    <p class="text-muted mb-4">Get started by adding your first cost item</p>
                                    <a href="{{ route('cost-items.create') }}" class="btn btn-primary">
                                        <i class="bx bx-plus"></i> Add Cost Item
                                    </a>
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
