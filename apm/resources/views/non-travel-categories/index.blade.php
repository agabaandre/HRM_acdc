@extends('layouts.app')

@section('title', 'Non-Travel Memo Categories')

@section('header', 'Non-Travel Memo Categories')

@section('header-actions')
    <a href="{{ route('non-travel-categories.create') }}" class="btn btn-primary">
        <i class="bx bx-plus"></i> Add Category
    </a>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Created At</th>
                        <th>Updated At</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $category->name }}</td>
                            <td>{{ $category->created_at->format('M d, Y') }}</td>
                            <td>{{ $category->updated_at->format('M d, Y') }}</td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <a href="{{ route('non-travel-categories.show', $category->id) }}" 
                                       class="btn btn-sm btn-outline-primary" 
                                       data-bs-toggle="tooltip" 
                                       title="View">
                                        <i class="bx bx-show"></i>
                                    </a>
                                    <a href="{{ route('non-travel-categories.edit', $category->id) }}" 
                                       class="btn btn-sm btn-outline-secondary" 
                                       data-bs-toggle="tooltip" 
                                       title="Edit">
                                        <i class="bx bx-edit"></i>
                                    </a>
                                    <form action="{{ route('non-travel-categories.destroy', $category->id) }}" 
                                          method="POST" 
                                          class="d-inline"
                                          onsubmit="return confirm('Are you sure you want to delete this category? This action cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="btn btn-sm btn-outline-danger" 
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
                            <td colspan="5" class="text-center py-5">
                                <div class="d-flex flex-column align-items-center">
                                    <div class="mb-3">
                                        <i class="bx bx-package text-muted" style="font-size: 3rem;"></i>
                                    </div>
                                    <h5 class="text-muted mb-3">No categories found</h5>
                                    <p class="text-muted mb-4">Get started by creating a new category</p>
                                    <a href="{{ route('non-travel-categories.create') }}" class="btn btn-primary">
                                        <i class="bx bx-plus"></i> Add Category
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $categories->links() }}
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Enable Bootstrap tooltips
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>
@endpush
@endsection
