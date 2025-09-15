@extends('layouts.app')

@section('title', 'Pending Approvals')

@section('header', 'Pending Approvals Dashboard')

@section('header-actions')
<div class="d-flex gap-2">
    <button type="button" class="btn btn-outline-primary" id="refreshData">
        <i class="fas fa-sync-alt me-1"></i> Refresh
    </button>
    <button type="button" class="btn btn-outline-info" id="exportData">
        <i class="fas fa-download me-1"></i> Export
    </button>
</div>
@endsection

@section('content')
<div class="row mb-4">
    <!-- Summary Cards -->
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0">{{ $summaryStats['total_pending'] }}</h4>
                        <p class="mb-0">Total Pending</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0">{{ $summaryStats['by_category']['Matrix'] ?? 0 }}</h4>
                        <p class="mb-0">Matrices</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calendar-alt fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0">{{ ($summaryStats['by_category']['Special Memo'] ?? 0) + ($summaryStats['by_category']['Non-Travel Memo'] ?? 0) + ($summaryStats['by_category']['Single Memo'] ?? 0) }}</h4>
                        <p class="mb-0">Memos</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-file-alt fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="mb-0">{{ count($summaryStats['by_division']) }}</h4>
                        <p class="mb-0">Divisions</p>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-building fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="categoryFilter" class="form-label">Category</label>
                <select id="categoryFilter" class="form-select">
                    @foreach($categories as $cat)
                        <option value="{{ $cat['value'] }}" {{ $category === $cat['value'] ? 'selected' : '' }}>
                            {{ $cat['label'] }} ({{ $cat['count'] }})
                        </option>
                    @endforeach
                </select>
            </div>
            
            <div class="col-md-4">
                <label for="divisionFilter" class="form-label">Division</label>
                <select id="divisionFilter" class="form-select">
                    <option value="all" {{ $division === 'all' ? 'selected' : '' }}>All Divisions</option>
                    @foreach($divisions as $div)
                        <option value="{{ $div->id }}" {{ $division === $div->id ? 'selected' : '' }}>
                            {{ $div->division_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            
        </div>
    </div>
</div>

<!-- Pending Approvals by Category -->
@foreach($pendingApprovals as $categoryName => $items)
    @if(count($items) > 0)
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-folder me-2"></i>
                    {{ $categoryName }}
                    <span class="badge bg-primary ms-2">{{ count($items) }}</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Title</th>
                                <th>Division</th>
                                <th>Submitted By</th>
                                <th>Date Received</th>
                                <th>Current Level</th>
                                <th>Workflow Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
                                <tr data-item-id="{{ $item['item_id'] }}" data-item-type="{{ $item['item_type'] }}">
                                    <td>
                                        <div class="fw-semibold">{{ $item['title'] }}</div>
                                        <small class="text-muted">{{ $item['category'] }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">{{ $item['division'] }}</span>
                                    </td>
                                    <td>{{ $item['submitted_by'] }}</td>
                                    <td>
                                        <div>{{ $item['date_received'] ? \Carbon\Carbon::parse($item['date_received'])->format('M d, Y') : 'N/A' }}</div>
                                        <small class="text-muted">{{ $item['date_received'] ? \Carbon\Carbon::parse($item['date_received'])->diffForHumans() : '' }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">Level {{ $item['approval_level'] }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning">{{ $item['workflow_role'] }}</span>
                                    </td>
                                    <td>
                                        <a href="{{ $item['view_url'] }}" class="btn btn-sm btn-outline-primary" target="_blank">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endforeach

@if(empty($pendingApprovals) || collect($pendingApprovals)->flatten(1)->isEmpty())
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
            <h4>No Pending Approvals</h4>
            <p class="text-muted">You have no pending approvals at this time.</p>
        </div>
    </div>
@endif


@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Filter functionality
    $('#categoryFilter, #divisionFilter').on('change', function() {
        const category = $('#categoryFilter').val();
        const division = $('#divisionFilter').val();
        
        // Reload page with new filters
        const url = new URL(window.location);
        url.searchParams.set('category', category);
        url.searchParams.set('division', division);
        window.location.href = url.toString();
    });
    
    // Refresh data
    $('#refreshData').on('click', function() {
        location.reload();
    });
    
    // Export data
    $('#exportData').on('click', function() {
        // Implement export functionality
        show_notification('Export functionality coming soon', 'info');
    });
    
});
</script>
@endpush
