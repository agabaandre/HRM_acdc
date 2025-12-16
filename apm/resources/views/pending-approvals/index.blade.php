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
<style>
  /* Status Cards Styling - Same as Week Tasks */
  .stat-item {
    text-align: center;
    padding: 1.5rem 1rem;
    border-radius: 15px;
    background: white;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
  }

  .stat-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--stat-color), var(--stat-color-light));
  }

  .stat-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
  }

  .stat-item.total {
    --stat-color: #17a2b8;
    --stat-color-light: #20c997;
  }

  .stat-item.matrices {
    --stat-color: #ffc107;
    --stat-color-light: #ffed4e;
  }

  .stat-item.arf {
    --stat-color: #28a745;
    --stat-color-light: #34ce57;
  }

  .stat-item.memos {
    --stat-color:#272935;
    --stat-color-light:hsl(240, 5.30%, 36.90%);
  }

  .stat-item.requests {
    --stat-color: #007bff;
    --stat-color-light: #4dabf7;
  }

  .stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: var(--stat-color);
    display: block;
    margin-bottom: 0.5rem;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    animation: countUp 1s ease-out;
  }

  .stat-label {
    font-size: 0.9rem;
    color: #495057;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 700;
    margin-bottom: 0.25rem;
  }

  .stat-icon {
    font-size: 1.5rem;
    color: var(--stat-color);
    margin-bottom: 0.5rem;
    display: block;
    animation: pulse 2s infinite;
  }

  @keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
  }

  @keyframes countUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .stat-progress {
    height: 4px;
    background: rgba(0, 0, 0, 0.1);
    border-radius: 2px;
    margin-top: 0.5rem;
    overflow: hidden;
  }

  .stat-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, var(--stat-color), var(--stat-color-light));
    border-radius: 2px;
    transition: width 1s ease-out;
    position: relative;
    overflow: hidden;
  }

  .stat-progress-bar::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    animation: shimmer 2s infinite;
  }

  @keyframes shimmer {
    0% { left: -100%; }
    100% { left: 100%; }
  }

  /* Table Header Styling */
  .table th {
    background-color: white !important;
    color: #2c3f51 !important;
    border: none;
    font-weight: 600;
    padding: 1rem 0.75rem;
    font-size: 0.9rem;
  }
</style>

<div class="row mb-4">
    <!-- Summary Cards -->
    <style>
        .stat-number-circle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            color: #032 !important;
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            margin-top: 0.5rem;
            box-shadow: 0 2px 8px rgba(44,63,81,0.08);
        }
    </style>
    <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="stat-item total">
            <i class="fas fa-clock text-danger stat-icon"></i>
            <span class="stat-number-circle">{{ $summaryStats['total_pending'] }}</span>
            <span class="stat-label">Total Pending</span>
            <div class="stat-progress">
                <div class="stat-progress-bar" style="width: 100%"></div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="stat-item matrices">
            <i class="fas fa-calendar-alt stat-icon"></i>
            <span class="stat-number-circle">{{ $summaryStats['by_category']['Matrix'] ?? 0 }}</span>
            <span class="stat-label">Matrices</span>
            <div class="stat-progress">
                <div class="stat-progress-bar" style="width: {{ $summaryStats['total_pending'] > 0 ? (($summaryStats['by_category']['Matrix'] ?? 0) / $summaryStats['total_pending']) * 100 : 0 }}%"></div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="stat-item memos">
            <i class="fas fa-file-alt stat-icon"></i>
            <span class="stat-number-circle">{{ ($summaryStats['by_category']['Special Memo'] ?? 0) + ($summaryStats['by_category']['Non-Travel Memo'] ?? 0) + ($summaryStats['by_category']['Single Memo'] ?? 0) }}</span>
            <span class="stat-label">Memos</span>
            <div class="stat-progress">
                <div class="stat-progress-bar" style="width: {{ $summaryStats['total_pending'] > 0 ? ((($summaryStats['by_category']['Special Memo'] ?? 0) + ($summaryStats['by_category']['Non-Travel Memo'] ?? 0) + ($summaryStats['by_category']['Single Memo'] ?? 0)) / $summaryStats['total_pending']) * 100 : 0 }}%"></div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 col-sm-6">
        <div class="stat-item requests">
            <i class="fas fa-cogs stat-icon"></i>
            <span class="stat-number-circle">{{ ($summaryStats['by_category']['Service Request'] ?? 0) + ($summaryStats['by_category']['ARF'] ?? 0) + ($summaryStats['by_category']['Change Request'] ?? 0) }}</span>
            <span class="stat-label">Requests</span>
            <div class="stat-progress">
                <div class="stat-progress-bar" style="width: {{ $summaryStats['total_pending'] > 0 ? ((($summaryStats['by_category']['Service Request'] ?? 0) + ($summaryStats['by_category']['ARF'] ?? 0) + ($summaryStats['by_category']['Change Request'] ?? 0)) / $summaryStats['total_pending']) * 100 : 0 }}%"></div>
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
                    @foreach($groupedCategories as $cat)
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
            <div class="card-header bg-success">
                <h5 class="mb-0 text-white">
                    <i class="fas fa-folder me-2 text-white"></i>
                    {{ $categoryName }}
                    <span class="badge bg-white text-success ms-2">{{ count($items) }}</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="table-layout: fixed; width: 100%;">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th style="width: 250px; max-width: 250px;">Title</th>
                                <th style="width: 171px; max-width: 171px;">Division</th>
                                <th style="width: 144px;">Submitted By</th>
                                <th style="width: 120px;">Date Received</th>
                                <th style="width: 100px;">Current Level</th>
                                <th style="width: 120px; max-width: 120px;">Workflow Role</th>
                                <th style="width: 100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $index => $item)
                                <tr data-item-id="{{ $item['item_id'] }}" data-item-type="{{ $item['item_type'] }}">
                                    <td style="width: 50px; text-align: center;">
                                        <strong>{{ $loop->iteration }}</strong>
                                    </td>
                                    <td style="max-width: 250px; width: 250px; word-wrap: break-word; white-space: normal;">
                                        <div class="fw-semibold" style="word-wrap: break-word; word-break: break-word; max-width: 250px; line-height: 1.3; white-space: normal; overflow-wrap: break-word;">{{ to_title_case($item['title']) }}</div>
                                        <small class="text-muted">{{ $item['category'] }}</small>
                                    </td>
                                    <td style="max-width: 171px; width: 171px; word-wrap: break-word; word-break: break-word; white-space: normal; overflow-wrap: break-word;">
                                        <span class="badge bg-secondary" style="word-wrap: break-word; word-break: break-word; white-space: normal; overflow-wrap: break-word; max-width: 100%; display: inline-block;">{{ $item['division'] }}</span>
                                    </td>
                                    <td style="word-wrap: break-word; white-space: normal; overflow-wrap: break-word;">{{ $item['submitted_by'] }}</td>
                                    <td>
                                        <div>{{ $item['date_received'] ? \Carbon\Carbon::parse($item['date_received'])->format('M d, Y') : 'N/A' }}</div>
                                        <small class="text-muted">{{ $item['date_received'] ? \Carbon\Carbon::parse($item['date_received'])->diffForHumans() : '' }}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">Level {{ $item['approval_level'] }}</span>
                                    </td>
                                    <td style="max-width: 120px; width: 120px; word-wrap: break-word; word-break: break-word; white-space: normal; overflow-wrap: break-word;">
                                        <span class="badge bg-warning" style="word-wrap: break-word; word-break: break-word; white-space: normal; overflow-wrap: break-word; max-width: 100%; display: inline-block;">{{ $item['workflow_role'] }}</span>
                                    </td>
                                    <td>
                                        <a href="{{ $item['view_url'] }}" class="btn btn-sm btn-outline-primary" target="_blank">
                                            <i class="fas fa-eye me-1"></i> View
                                        </a>
                                        @if(isset($isAdminAssistant) && $isAdminAssistant && isset($item['is_admin_assistant_view']) && $item['is_admin_assistant_view'])
                                            <small class="d-block text-muted mt-1">
                                                <i class="fas fa-info-circle"></i> View Only
                                            </small>
                                        @endif
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
