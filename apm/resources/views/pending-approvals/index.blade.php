@extends('layouts.app')

@section('title', 'Pending Approvals')

@section('header', 'Pending Approvals Dashboard')

@section('header-actions')
<div class="d-flex gap-2 flex-wrap">
    <button type="button" class="btn btn-primary" id="refreshData">
        <i class="fas fa-sync-alt me-1"></i> Refresh
    </button>
    <button type="button" class="btn btn-outline-secondary" id="exportData">
        <i class="fas fa-download me-1"></i> Export
    </button>
</div>
@endsection

@section('content')
<div class="pending-approvals-page">
<style>
  .pending-approvals-page {
    --pa-primary: #119a48;
    --pa-primary-dark: #0d7a3a;
    --pa-surface: #f8faf9;
    --pa-border: #e2e8e4;
    --pa-text: #1e293b;
    --pa-muted: #64748b;
  }

  /* Status Cards — calmer motion, clearer scan for approvers */
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
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
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
    font-size: 1.35rem;
    color: var(--stat-color);
    margin-bottom: 0.35rem;
    display: block;
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

  /* Toolbar & category tables */
  .pa-toolbar-card {
    border: 1px solid var(--pa-border) !important;
    border-radius: 12px;
    background: #fff;
  }
  .pa-toolbar-header {
    background: linear-gradient(135deg, rgba(17, 154, 72, 0.08) 0%, rgba(17, 154, 72, 0.02) 100%);
    border-bottom: 1px solid var(--pa-border);
    border-radius: 12px 12px 0 0;
  }
  .pa-stale-alert {
    border-radius: 12px;
    border: 1px solid rgba(245, 158, 11, 0.45);
    background: linear-gradient(90deg, rgba(255, 251, 235, 0.95) 0%, #fff 12%);
    border-left: 4px solid #f59e0b !important;
  }
  .pa-category-card {
    border: 1px solid var(--pa-border);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(15, 23, 42, 0.06);
  }
  .pa-category-header {
    background: linear-gradient(135deg, var(--pa-primary-dark) 0%, var(--pa-primary) 100%);
    border: none;
    padding: 0.85rem 1.25rem;
  }
  .pa-category-header .pa-cat-title {
    font-size: 1.05rem;
    font-weight: 600;
    letter-spacing: 0.02em;
  }
  .pa-table-approvals thead th {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--pa-muted) !important;
    font-weight: 600;
    border-bottom: 2px solid var(--pa-border) !important;
    padding: 0.65rem 0.75rem;
    vertical-align: middle;
    background: #f1f5f4 !important;
  }
  .pa-table-approvals tbody td {
    padding: 0.65rem 0.75rem;
    vertical-align: middle;
    border-color: var(--pa-border);
  }
  .pa-table-approvals tbody tr:hover {
    background-color: rgba(17, 154, 72, 0.04);
  }
  .pa-table-approvals .pa-title-cell {
    line-height: 1.35;
  }
  .pa-table-approvals .btn-view-open {
    min-width: 5.5rem;
    font-weight: 600;
    border-radius: 8px;
  }
  .pa-empty-card {
    border-radius: 12px;
    border: 1px dashed var(--pa-border);
    background: var(--pa-surface);
  }
  .pa-help-details > summary {
    list-style: none;
    cursor: pointer;
  }
  .pa-help-details > summary::-webkit-details-marker { display: none; }
  .pa-help-chevron { transition: transform 0.15s ease; display: inline-block; }
  .pa-help-details[open] .pa-help-chevron { transform: rotate(90deg); }

  .pa-help-details.card {
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid var(--pa-border) !important;
  }

  .pending-approvals-page .btn-primary {
    background-color: var(--pa-primary);
    border-color: var(--pa-primary);
  }
  .pending-approvals-page .btn-primary:hover,
  .pending-approvals-page .btn-primary:focus {
    background-color: var(--pa-primary-dark);
    border-color: var(--pa-primary-dark);
  }

  .stat-number-circle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 52px;
    height: 52px;
    border-radius: 50%;
    color: #0f172a !important;
    font-size: 1.45rem;
    font-weight: 700;
    margin-bottom: 0.35rem;
    margin-top: 0.35rem;
    box-shadow: 0 1px 6px rgba(15, 23, 42, 0.08);
    background: rgba(255, 255, 255, 0.95);
  }

  .pa-approver-card {
    border: 1px solid var(--pa-border);
    border-radius: 12px;
    border-left: 4px solid var(--pa-primary);
    box-shadow: 0 2px 12px rgba(15, 23, 42, 0.05);
  }
  .pa-approver-card .badge-role {
    font-weight: 500;
    background: rgba(17, 154, 72, 0.12) !important;
    color: var(--pa-primary-dark) !important;
    border: 1px solid rgba(17, 154, 72, 0.25);
  }

  .pending-approvals-page .table th {
    background-color: #f1f5f4;
    color: var(--pa-text) !important;
    border-color: var(--pa-border);
    font-weight: 600;
    padding: 0.65rem 0.75rem;
    font-size: 0.75rem;
  }
</style>

@php
    $staleList = $stalePendingItems ?? [];
    $staleCount = is_array($staleList) ? count($staleList) : 0;
    $warnDays = (int) ($approvalWarningDays ?? 7);
    $canOpenSystemSettings = in_array(89, user_session('permissions', []), true);
@endphp
@if($staleCount > 0)
<div class="row mb-3">
    <div class="col-12">
        <div class="alert pa-stale-alert shadow-sm d-flex align-items-start mb-0" role="alert">
            <i class="fas fa-hourglass-end fa-lg me-3 mt-1 text-warning"></i>
            <div class="flex-grow-1">
                <h6 class="alert-heading mb-2">Friendly reminder</h6>
                <p class="mb-2 small">
                    You have <strong>{{ $staleCount }}</strong> item(s) that have been waiting at your approval level for more than <strong>{{ $warnDays }}</strong> day(s)
                    (from when each item reached you: last approval or hand-off from the <strong>previous</strong> step, or submission to your level if you are first in line — not the original document creation date).
                    @if($canOpenSystemSettings)
                        You can change this threshold under
                        <a href="{{ route('system-settings.index') }}" class="alert-link">System settings</a>
                        (<strong>approval_warning_days</strong>).
                    @else
                        The threshold is set in System settings as <strong>approval_warning_days</strong> (default 7).
                    @endif
                </p>
                <p class="mb-0 small">
                    When you are ready, please <strong>approve</strong> so the document can proceed to the next person in the approval trail,
                    or <strong>return</strong> it if it should be corrected or set aside for archiving. Once an item is no longer pending at your level, reminders for that item stop.
                </p>
            </div>
        </div>
    </div>
</div>
@endif

<div class="row mb-4 g-3">
    <!-- Summary Cards -->
    @php
        $memoCardCount = ($summaryStats['by_category']['Special Memo'] ?? 0)
            + ($summaryStats['by_category']['Non-Travel Memo'] ?? 0)
            + ($summaryStats['by_category']['Single Memo'] ?? 0)
            + ($summaryStats['by_category']['Other Memo'] ?? 0);
        $requestsCardCount = ($summaryStats['by_category']['Service Request'] ?? 0)
            + ($summaryStats['by_category']['ARF'] ?? 0)
            + ($summaryStats['by_category']['Change Request'] ?? 0);
        $totalPending = (int) ($summaryStats['total_pending'] ?? 0);
    @endphp
    <div class="col-lg-3 col-md-6 col-sm-6 d-flex">
        <div class="stat-item total w-100">
            <i class="fas fa-clock text-danger stat-icon"></i>
            <span class="stat-number-circle">{{ $summaryStats['total_pending'] }}</span>
            <span class="stat-label">Total Pending</span>
            <div class="stat-progress">
                <div class="stat-progress-bar" style="width: 100%"></div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 col-sm-6 d-flex">
        <div class="stat-item matrices w-100">
            <i class="fas fa-calendar-alt stat-icon"></i>
            <span class="stat-number-circle">{{ $summaryStats['by_category']['Matrix'] ?? 0 }}</span>
            <span class="stat-label">Matrices</span>
            <div class="stat-progress">
                <div class="stat-progress-bar" style="width: {{ $totalPending > 0 ? (($summaryStats['by_category']['Matrix'] ?? 0) / $totalPending) * 100 : 0 }}%"></div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 col-sm-6 d-flex">
        <div class="stat-item memos w-100">
            <i class="fas fa-file-alt stat-icon"></i>
            <span class="stat-number-circle">{{ $memoCardCount }}</span>
            <span class="stat-label">Memos</span>
            <div class="stat-progress">
                <div class="stat-progress-bar" style="width: {{ $totalPending > 0 ? ($memoCardCount / $totalPending) * 100 : 0 }}%"></div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 col-sm-6 d-flex">
        <div class="stat-item requests w-100">
            <i class="fas fa-cogs stat-icon"></i>
            <span class="stat-number-circle">{{ $requestsCardCount }}</span>
            <span class="stat-label">Requests</span>
            <div class="stat-progress">
                <div class="stat-progress-bar" style="width: {{ $totalPending > 0 ? ($requestsCardCount / $totalPending) * 100 : 0 }}%"></div>
            </div>
        </div>
    </div>
</div>

<details class="pa-help-details card border-0 bg-light mb-4">
    <summary class="card-body py-3 d-flex align-items-center justify-content-between gap-2 list-unstyled">
        <span class="fw-semibold text-dark">
            <span class="pa-help-chevron text-secondary me-2" aria-hidden="true">&#9654;</span>
            <i class="fas fa-info-circle me-1 text-secondary"></i>How these numbers are derived
        </span>
        <span class="small text-muted">Expand for details</span>
    </summary>
    <div class="card-body border-top pt-3 pb-3">
        <ul class="small text-muted mb-0 ps-3">
            <li><strong>Total Pending</strong> is the count of every item where <strong>you can take action now</strong> (same rules as the rows below): matrices, memos (special, non-travel, single, and other memos), service requests, ARF, and change requests. Returned items that are back in your queue are included.</li>
            <li><strong>Matrices</strong>, <strong>Memos</strong>, and <strong>Requests</strong> split that total by type. Memos include <strong>Other Memo</strong>. The bars show each group’s share of total pending.</li>
            <li>The <strong>category</strong> dropdown only filters the <strong>tables</strong> below; the top counts stay for your <strong>full</strong> queue so you always see workload at a glance.</li>
            @if(!empty($isAdminAssistant))
                <li><strong>Admin assistants</strong> also see items for approvers they support; rows marked “View Only” are not assigned to you as the primary approver.</li>
            @endif
            @if(isset($avgApprovalTimeDisplay) && (float)($avgApprovalTimeHours ?? 0) > 0)
                <li><strong>Average approval time</strong> (when shown) blends <strong>completed</strong> approvals with <strong>time elapsed so far</strong> on items still waiting for your action, from when each item reached your current level until you approve (or until now if still open). Optional <strong>year</strong> / <strong>month</strong> query parameters align this with the approver dashboard filters when you open this page from there.</li>
            @endif
            <li><strong>Aging reminders</strong> (banner above and email at 11:00 daily) use <strong>approval_warning_days</strong> in System settings (default 7): items are flagged when they have been at your level for <em>more than</em> that many days since they were <em>handed to your step</em> (previous approver’s action or submit to your level). Reminders repeat each day until those items are cleared from your queue.</li>
        </ul>
    </div>
</details>

@if(isset($avgApprovalTimeDisplay) && (float)($avgApprovalTimeHours ?? 0) > 0)
@php
    $timingReportUrl = null;
    if (!empty($canViewApprovalTimingReport)) {
        $timingReportUrl = route('reports.approver-document-timing.index', array_filter([
            'staff_id' => !empty($staffId) ? $staffId : null,
            'year' => $year ?? null,
            'month' => $month ?? null,
        ]));
    }
@endphp
<div class="row mb-3 justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="card shadow-sm {{ $timingReportUrl ? 'border border-success border-opacity-50' : '' }}">
            <div class="card-body text-center py-3">
                <p class="mb-2" style="font-size: 1.05rem; color: #495057;">
                    @if(!empty($staffId))
                        This approver takes an average of <strong>{{ $avgApprovalTimeDisplay }}</strong> to approve.
                    @else
                        You take an average of <strong>{{ $avgApprovalTimeDisplay }}</strong> to approve.
                    @endif
                    <i class="fas fa-info-circle ms-1 text-info" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="This average goes down when you approve incoming memos promptly. Approving items sooner reduces your average approval time." aria-label="Info"></i>
                </p>
                @if(!empty($timingReportUrl))
                    <a href="{{ $timingReportUrl }}" wire:navigate class="btn btn-sm btn-success"><i class="fas fa-table me-1"></i> Average time per document (detail)</a>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

<!-- Filters -->
<div class="card pa-toolbar-card mb-4 shadow-sm">
    <div class="card-header pa-toolbar-header py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h6 class="mb-0 fw-semibold text-dark"><i class="fas fa-filter me-2" style="color: var(--pa-primary);"></i>Queue filters</h6>
            <small class="text-muted">Category and division apply immediately when changed.</small>
        </div>
    </div>
    <div class="card-body pt-3 pb-3">
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

@if(isset($approverInfo) && $approverInfo)
<!-- Approver Information -->
<div class="card pa-approver-card mb-4">
    <div class="card-body py-3">
        <div class="d-flex align-items-start flex-wrap gap-3">
            <div class="flex-shrink-0">
                <i class="fas fa-user-circle fa-3x" style="color: var(--pa-primary);"></i>
            </div>
            <div class="flex-grow-1 min-w-0">
                <h5 class="mb-1 fw-semibold">
                    <i class="fas fa-user me-2" style="color: var(--pa-primary);"></i>
                    {{ $approverInfo['name'] }}
                </h5>
                <p class="mb-1 text-muted small">
                    <i class="fas fa-envelope me-1"></i>{{ $approverInfo['email'] }}
                </p>
                <p class="mb-1 text-muted small">
                    <i class="fas fa-building me-1"></i>{{ $approverInfo['division_name'] }}
                </p>
                @if(!empty($approverInfo['roles']))
                    <div class="mt-2 d-flex flex-wrap gap-1 align-items-center">
                        <span class="small fw-semibold text-muted me-1">Roles:</span>
                        @foreach($approverInfo['roles'] as $role)
                            <span class="badge badge-role me-0">{{ $role }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

<!-- Pending Approvals by Category -->
@foreach($pendingApprovals as $categoryName => $items)
    @if(count($items) > 0)
        <div class="card pa-category-card mb-4">
            <div class="card-header pa-category-header text-white">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h5 class="mb-0 pa-cat-title text-white d-flex align-items-center flex-wrap gap-2">
                        <i class="fas fa-folder-open opacity-90"></i>
                        <span>{{ $categoryName }}</span>
                    </h5>
                    <span class="badge rounded-pill bg-light text-dark fw-semibold">{{ count($items) }} open</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 pa-table-approvals" style="table-layout: fixed; width: 100%;">
                        <thead>
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
                                    <td class="pa-title-cell" style="max-width: 250px; width: 250px; word-wrap: break-word; white-space: normal;">
                                        <div class="fw-semibold" style="word-wrap: break-word; word-break: break-word; max-width: 250px; white-space: normal; overflow-wrap: break-word;">{{ to_title_case($item['title']) }}</div>
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
                                        <a href="{{ $item['view_url'] }}" class="btn btn-sm btn-primary btn-view-open" wire:navigate>
                                            <i class="fas fa-arrow-right me-1"></i>Open
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
    <div class="card pa-empty-card shadow-none">
        <div class="card-body text-center py-5 px-3">
            <i class="fas fa-check-circle fa-3x mb-3" style="color: var(--pa-primary);"></i>
            <h4 class="fw-semibold mb-2">No pending approvals</h4>
            <p class="text-muted mb-0">Nothing in this queue with the current filters. Try another category or division, or check back later.</p>
        </div>
    </div>
@endif

</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Initialize Bootstrap tooltips (e.g. average approval time info)
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function(el) { new bootstrap.Tooltip(el); });

    // Filter functionality
    $('#categoryFilter, #divisionFilter').on('change', function() {
        const category = $('#categoryFilter').val();
        const division = $('#divisionFilter').val();
        
        // Reload page with new filters
        const url = new URL(window.location);
        url.searchParams.set('category', category);
        url.searchParams.set('division', division);
        
        const prev = new URLSearchParams(window.location.search);
        const staffId = prev.get('staff_id');
        if (staffId) {
            url.searchParams.set('staff_id', staffId);
        }
        const year = prev.get('year');
        const month = prev.get('month');
        if (year) {
            url.searchParams.set('year', year);
        }
        if (month) {
            url.searchParams.set('month', month);
        }

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
