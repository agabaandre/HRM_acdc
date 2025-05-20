@extends('layouts.app')

@section('title', 'Non-Travel Memos')

@section('header', 'Non-Travel Memos')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('non-travel.create') }}" class="btn btn-success shadow-sm">
        <i class="bx bx-plus-circle me-1"></i> Create New Memo
    </a>
</div>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-0"><i class="bx bx-list-ul me-2 text-primary"></i>All Non-Travel Memos</h5>
            </div>
            <div class="col-md-6">
                <form action="{{ route('non-travel.index') }}" method="GET" class="d-flex gap-2 justify-content-end">
                    <select name="category_id" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    
                    <select name="staff_id" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                        <option value="">All Staff</option>
                        @foreach($staff as $member)
                            <option value="{{ $member->id }}" {{ request('staff_id') == $member->id ? 'selected' : '' }}>
                                {{ $member->name }}
                            </option>
                        @endforeach
                    </select>
                    
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        <i class="bx bx-filter-alt"></i> Filter
                    </button>
                    
                    <a href="{{ route('non-travel.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bx bx-reset"></i> Reset
                    </a>
                </form>
            </div>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="fw-semibold">Title</th>
                        <th class="fw-semibold">Category</th>
                        <th class="fw-semibold">Staff</th>
                        <th class="fw-semibold">Date</th>
                        <th class="fw-semibold text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($nonTravelMemos as $memo)
                        <tr>
                            <td>
                                <div class="fw-bold text-primary">{{ $memo->activity_title }}</div>
                                <small class="text-muted">{{ $memo->workplan_activity_code }}</small>
                            </td>
                            <td>
                                <span class="badge bg-info text-dark">
                                    <i class="bx bx-category me-1"></i>
                                    {{ $memo->nonTravelMemoCategory->name ?? 'N/A' }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar avatar-sm me-2 bg-light rounded-circle">
                                        <span class="avatar-text">{{ substr($memo->staff->name ?? 'U', 0, 1) }}</span>
                                    </div>
                                    <span>{{ $memo->staff->name ?? 'Unknown' }}</span>
                                </div>
                            </td>
                            <td>{{ $memo->memo_date->format('M d, Y') }}</td>
                            <td>
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="{{ route('non-travel.show', $memo) }}" 
                                       class="btn btn-sm btn-info"
                                       data-bs-toggle="tooltip"
                                       title="View Details">
                                        <i class="bx bx-show-alt"></i>
                                    </a>
                                    <a href="{{ route('non-travel.edit', $memo) }}"
                                       class="btn btn-sm btn-warning"
                                       data-bs-toggle="tooltip"
                                       title="Edit Memo">
                                        <i class="bx bx-edit"></i>
                                    </a>
                                    <button type="button" 
                                            class="btn btn-sm btn-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteModal{{ $memo->id }}"
                                            data-bs-toggle="tooltip"
                                            title="Delete Memo">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                </div>

                                <!-- Delete Modal -->
                                <div class="modal fade" id="deleteModal{{ $memo->id }}" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header bg-danger text-white">
                                                <h5 class="modal-title"><i class="bx bx-trash me-1"></i> Delete Non-Travel Memo</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="alert alert-warning mb-3">
                                                    <i class="bx bx-error me-1"></i> Are you sure you want to delete this memo? This action cannot be undone.
                                                </div>
                                                <div class="card border">
                                                    <div class="card-body p-3">
                                                        <p class="mb-1"><strong><i class="bx bx-heading me-1 text-primary"></i> Title:</strong> {{ $memo->activity_title }}</p>
                                                        <p class="mb-0"><strong><i class="bx bx-calendar me-1 text-primary"></i> Date:</strong> {{ $memo->memo_date->format('Y-m-d') }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <form action="{{ route('non-travel.destroy', $memo) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger">
                                                        <i class="bx bx-trash me-1"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bx bx-file-blank fs-1 mb-3"></i>
                                    <p class="h5 text-muted">No non-travel memos found</p>
                                    <p class="small mt-2 text-muted">Click the "Create New Memo" button to create your first memo</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="card-footer bg-white">
        <div class="d-flex justify-content-end">
            {{ $nonTravelMemos->links() }}
        </div>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        // Initialize tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
    });
</script>
@endpush
@endsection
