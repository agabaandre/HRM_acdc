@extends('layouts.app')

@section('title', 'FAQ Categories')
@section('header', 'FAQ Categories')

@section('header-actions')
<a href="{{ route('faqs.index') }}" class="btn btn-outline-secondary">
    <i class="bx bx-list-ul"></i> FAQs
</a>
<a href="{{ route('faq-categories.create') }}" class="btn btn-success">
    <i class="bx bx-plus"></i> Add category
</a>
@endsection

@section('content')
@if(session('msg'))
    <div class="alert alert-{{ session('type', 'success') }} alert-dismissible fade show" role="alert">
        {{ session('msg') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="bx bx-category me-2 text-primary"></i>Categories</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 60px;">Order</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th style="width: 80px;">FAQs</th>
                        <th style="width: 90px;">Status</th>
                        <th class="text-end" style="width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $cat)
                        <tr>
                            <td>{{ $cat->sort_order }}</td>
                            <td>{{ $cat->name }}</td>
                            <td><code class="small">{{ $cat->slug }}</code></td>
                            <td>{{ $cat->faqs_count }}</td>
                            <td>
                                @if($cat->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <a href="{{ route('faq-categories.edit', $cat) }}" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                        <i class="bx bx-edit"></i>
                                    </a>
                                    <form action="{{ route('faq-categories.destroy', $cat) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this category?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Delete" {{ $cat->faqs_count > 0 ? 'disabled' : '' }}>
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <p class="text-muted mb-0">No categories yet.</p>
                                <a href="{{ route('faq-categories.create') }}" class="btn btn-primary mt-3"><i class="bx bx-plus me-1"></i>Add category</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (el) { return new bootstrap.Tooltip(el); });
    });
</script>
@endpush
