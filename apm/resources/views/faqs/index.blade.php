@extends('layouts.app')

@section('title', 'Manage FAQs')
@section('header', 'Manage FAQs')

@section('header-actions')
<a href="{{ route('faqs.create') }}" class="btn btn-success">
    <i class="bx bx-plus"></i> Add FAQ
</a>
<a href="{{ route('faq.index') }}" class="btn btn-outline-primary" target="_blank" rel="noopener">
    <i class="bx bx-show"></i> View public page
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
    <div class="card-header bg-light d-flex flex-wrap gap-2 align-items-center">
        <h5 class="mb-0"><i class="bx bx-list-ul me-2 text-primary"></i>FAQs</h5>
        <form action="{{ route('faqs.index') }}" method="GET" class="d-flex flex-wrap gap-2 ms-auto">
            <input type="text" name="search" class="form-control form-control-sm" style="max-width: 220px;" placeholder="Search question or answer..." value="{{ request('search') }}">
            <select name="active" class="form-select form-select-sm" style="max-width: 120px;">
                <option value="">All</option>
                <option value="1" {{ request('active') === '1' ? 'selected' : '' }}>Active</option>
                <option value="0" {{ request('active') === '0' ? 'selected' : '' }}>Inactive</option>
            </select>
            <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bx bx-search"></i></button>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Question</th>
                        <th style="width: 80px;">Order</th>
                        <th style="width: 90px;">Status</th>
                        <th class="text-end" style="width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($faqs as $faq)
                        <tr>
                            <td>{{ $faq->id }}</td>
                            <td>
                                <span class="text-dark">{{ Str::limit($faq->question, 70) }}</span>
                                @if($faq->search_keywords)
                                    <br><small class="text-muted">{{ Str::limit($faq->search_keywords, 50) }}</small>
                                @endif
                            </td>
                            <td>{{ $faq->sort_order }}</td>
                            <td>
                                @if($faq->is_active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <a href="{{ route('faqs.edit', $faq) }}" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit">
                                        <i class="bx bx-edit"></i>
                                    </a>
                                    <form action="{{ route('faqs.destroy', $faq) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this FAQ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Delete">
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
                                    <i class="bx bx-help-circle text-muted mb-2" style="font-size: 2.5rem;"></i>
                                    <p class="text-muted mb-0">No FAQs found.</p>
                                    <a href="{{ route('faqs.create') }}" class="btn btn-primary mt-3"><i class="bx bx-plus me-1"></i>Add FAQ</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($faqs->hasPages())
        <div class="card-footer">
            {{ $faqs->links() }}
        </div>
    @endif
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
