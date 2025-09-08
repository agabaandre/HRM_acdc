@extends('layouts.app')

@section('title', 'Audit Logs')

@section('header', 'Audit Logs')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('audit-logs.index', array_merge(request()->query(), ['export' => 'csv'])) }}" class="btn btn-success">
        <i class="bx bx-download"></i> Export CSV
    </a>
    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#cleanupModal">
        <i class="bx bx-trash"></i> Cleanup Old Logs
    </button>
</div>
@endsection

@section('content')
<div class="row">
    <!-- Statistics Cards -->
    <div class="col-12 mb-4">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="card-title text-primary">Total Logs</h6>
                        <h3 class="text-primary">{{ number_format($stats['total_logs']) }}</h3>
                        <small class="text-muted">All Time</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="card-title text-success">Recent Activity</h6>
                        <h3 class="text-success">{{ number_format($stats['recent_activity']) }}</h3>
                        <small class="text-muted">Last 24 Hours</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="card-title text-info">Top Action</h6>
                        <h3 class="text-info">{{ $stats['actions_count']->keys()->first() ?? 'N/A' }}</h3>
                        <small class="text-muted">{{ $stats['actions_count']->first() ?? 0 }} times</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="card-title text-warning">Top Table</h6>
                        <h3 class="text-warning">{{ $stats['tables_count']->keys()->first() ?? 'N/A' }}</h3>
                        <small class="text-muted">{{ $stats['tables_count']->first() ?? 0 }} records</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bx bx-filter me-2 text-primary"></i>Filters</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('audit-logs.index') }}" id="filterForm">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="{{ request('search') }}" placeholder="Search logs...">
                        </div>
                        <div class="col-md-2">
                            <label for="action" class="form-label">Action</label>
                            <select class="form-select" id="action" name="action">
                                <option value="">All Actions</option>
                                @foreach($actions as $action)
                                    <option value="{{ $action }}" {{ request('action') == $action ? 'selected' : '' }}>
                                        {{ $action }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="table" class="form-label">Table</label>
                            <select class="form-select" id="table" name="table">
                                <option value="">All Tables</option>
                                @foreach($tables as $table)
                                    <option value="{{ $table }}" {{ request('table') == $table ? 'selected' : '' }}>
                                        {{ $table }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="date_from" class="form-label">From</label>
                            <input type="text" class="form-control datepicker" id="date_from" name="date_from" 
                                   value="{{ request('date_from') }}" placeholder="Select start date">
                        </div>
                        <div class="col-md-2">
                            <label for="date_to" class="form-label">To</label>
                            <input type="text" class="form-control datepicker" id="date_to" name="date_to" 
                                   value="{{ request('date_to') }}" placeholder="Select end date">
                        </div>
                        <div class="col-md-2">
                            <label for="suspicious" class="form-label">Suspicious</label>
                            <select class="form-select" id="suspicious" name="suspicious">
                                <option value="">All</option>
                                <option value="1" {{ request('suspicious') == '1' ? 'selected' : '' }}>Suspicious Only</option>
                                <option value="0" {{ request('suspicious') == '0' ? 'selected' : '' }}>Not Suspicious</option>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-1">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="bx bx-search"></i>
                                </button>
                                <a href="{{ route('audit-logs.index') }}" class="btn btn-secondary btn-sm">
                                    <i class="bx bx-x"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Audit Logs Table -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bx bx-list-ul me-2 text-primary"></i>Audit Logs</h6>
                <small class="text-muted">Showing {{ $pagination['from'] }}-{{ $pagination['to'] }} of {{ $pagination['total'] }} logs</small>
            </div>
            <div class="card-body p-0">
                @if($paginatedLogs->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 audit-logs-table">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Action</th>
                                    <th>Entity</th>
                                    <th>Table</th>
                                    <th>Causer</th>
                                    <th>Division & Duty Station</th>
                                    <th>Source</th>
                                    <th>Suspicious</th>
                                    <th>Date/Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($paginatedLogs as $log)
                                    <tr>
                                        <td>
                                            <span class="badge bg-secondary">#{{ $log->id }}</span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $log->action == 'created' ? 'bg-success' : ($log->action == 'updated' ? 'bg-warning' : 'bg-danger') }}">
                                                {{ $log->action }}
                                            </span>
                                        </td>
                                        <td>
                                            <div>
                                                <div class="fw-semibold">ID: {{ $log->entity_id ?? 'N/A' }}</div>
                                            </div>
                                        </td>
                                        <td>
                                            <code class="small">{{ $log->source_table }}</code>
                                        </td>
                                        <td>
                                            <div>
                                                @if($log->causer_id)
                                                    <div class="fw-semibold">{{ $log->causer_name ?? 'Unknown User' }}</div>
                                                    <small class="text-muted">{{ $log->causer_job_title ?? 'N/A' }}</small>
                                                    <br>
                                                    <small class="text-muted">{{ $log->causer_email ?? 'N/A' }}</small>
                                                @else
                                                    <span class="text-muted">System</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="division-duty-station">
                                            <div>
                                                @if($log->causer_id)
                                                    <div class="mb-1">
                                                        <span class="badge bg-primary">{{ $log->causer_division_name ?? 'N/A' }}</span>
                                                    </div>
                                                    <div>
                                                        <span class="badge bg-secondary">{{ $log->causer_duty_station_name ?? 'N/A' }}</span>
                                                    </div>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">{{ $log->source ?? 'Unknown' }}</span>
                                        </td>
                                        <td>
                                            @if($log->is_suspicious ?? false)
                                                <span class="badge bg-danger suspicious-badge" title="{{ $log->suspicious_reasons ?? 'Suspicious activity detected' }}">
                                                    <i class="bx bx-shield-x"></i> Yes
                                                </span>
                                            @else
                                                <span class="badge bg-success suspicious-badge">
                                                    <i class="bx bx-shield-check"></i> No
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <div>
                                                <div class="fw-semibold">{{ \Carbon\Carbon::parse($log->created_at)->format('M j, Y') }}</div>
                                                <small class="text-muted">{{ \Carbon\Carbon::parse($log->created_at)->format('g:i A') }}</small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#auditLogModal" 
                                                        data-log-id="{{ $log->id }}" 
                                                        data-log-table="{{ $log->source_table }}"
                                                        data-log-action="{{ $log->action }}"
                                                        data-log-entity="{{ $log->entity_id ?? 'N/A' }}"
                                                        data-log-causer-type="{{ $log->causer_type }}"
                                                        data-log-causer-id="{{ $log->causer_id }}"
                                                        data-log-causer-name="{{ $log->causer_name ?? 'Unknown User' }}"
                                                        data-log-causer-email="{{ $log->causer_email ?? 'N/A' }}"
                                                        data-log-causer-job="{{ $log->causer_job_title ?? 'N/A' }}"
                                                        data-log-causer-division="{{ $log->causer_division_name ?? 'N/A' }}"
                                                        data-log-causer-duty-station="{{ $log->causer_duty_station_name ?? 'N/A' }}"
                                                        data-log-source="{{ $log->source }}"
                                                        data-log-suspicious="{{ $log->is_suspicious ? 'Yes' : 'No' }}"
                                                        data-log-suspicious-reasons="{{ $log->suspicious_reasons ?? '' }}"
                                                        data-log-created="{{ $log->created_at }}"
                                                        data-log-old-values="{{ $log->old_values }}"
                                                        data-log-new-values="{{ $log->new_values }}"
                                                        data-log-metadata="{{ $log->metadata }}"
                                                        title="View Details">
                                                    <i class="bx bx-show"></i>
                                                </button>
                                                @if(in_array(91, user_session('permissions')) && in_array($log->action, ['created', 'updated', 'deleted']))
                                                    <button type="button" class="btn btn-sm btn-outline-warning" 
                                                            data-bs-toggle="modal" data-bs-target="#reversalModal"
                                                            data-log-id="{{ $log->id }}"
                                                            data-log-table="{{ $log->source_table }}"
                                                            data-log-action="{{ $log->action }}"
                                                            data-log-entity="{{ $log->entity_id ?? 'N/A' }}"
                                                            title="Reverse Action">
                                                        <i class="bx bx-undo"></i>
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    @if($pagination['last_page'] > 1)
                        <div class="card-footer">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted">
                                    Showing {{ $pagination['from'] }} to {{ $pagination['to'] }} of {{ $pagination['total'] }} results
                                </div>
                                <nav aria-label="Audit logs pagination">
                                    <ul class="pagination pagination-sm mb-0">
                                        <!-- Previous Page Link -->
                                        @if($pagination['current_page'] > 1)
                                            <li class="page-item">
                                                <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $pagination['current_page'] - 1]) }}" aria-label="Previous">
                                                    <span aria-hidden="true">&laquo;</span>
                                                </a>
                                            </li>
                                        @endif
                                        
                                        <!-- Page Numbers -->
                                        @for($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['last_page'], $pagination['current_page'] + 2); $i++)
                                            <li class="page-item {{ $i == $pagination['current_page'] ? 'active' : '' }}">
                                                <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $i]) }}">{{ $i }}</a>
                                            </li>
                                        @endfor
                                        
                                        <!-- Next Page Link -->
                                        @if($pagination['current_page'] < $pagination['last_page'])
                                            <li class="page-item">
                                                <a class="page-link" href="{{ request()->fullUrlWithQuery(['page' => $pagination['current_page'] + 1]) }}" aria-label="Next">
                                                    <span aria-hidden="true">&raquo;</span>
                                                </a>
                                            </li>
                                        @endif
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    @endif
                @else
                    <div class="text-center py-5">
                        <i class="bx bx-clipboard text-muted" style="font-size: 3rem;"></i>
                        <h5 class="mt-3 text-muted">No audit logs found</h5>
                        <p class="text-muted">Try adjusting your filters or check back later.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Audit Log Details Modal -->
<div class="modal fade" id="auditLogModal" tabindex="-1" aria-labelledby="auditLogModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="auditLogModalLabel">
                    <i class="bx bx-info-circle me-2 text-primary"></i>
                    Audit Log Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-4">
                    <!-- Basic Information -->
                    <div class="col-md-6">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="card-title text-primary mb-3">
                                    <i class="bx bx-info-circle me-2"></i>Basic Information
                                </h6>
                                <div class="row g-2">
                                    <div class="col-4"><strong>ID:</strong></div>
                                    <div class="col-8" id="modal-log-id">-</div>
                                    
                                    <div class="col-4"><strong>Action:</strong></div>
                                    <div class="col-8">
                                        <span class="badge" id="modal-log-action">-</span>
                                    </div>
                                    
                                    <div class="col-4"><strong>Entity ID:</strong></div>
                                    <div class="col-8" id="modal-log-entity">-</div>
                                    
                                    <div class="col-4"><strong>Table:</strong></div>
                                    <div class="col-8"><code id="modal-log-table">-</code></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Causer Information -->
                    <div class="col-md-6">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="card-title text-primary mb-3">
                                    <i class="bx bx-user me-2"></i>Causer Information
                                </h6>
                                <div class="row g-2">
                                    <div class="col-4"><strong>Name:</strong></div>
                                    <div class="col-8" id="modal-log-causer-name">-</div>
                                    
                                    <div class="col-4"><strong>Email:</strong></div>
                                    <div class="col-8" id="modal-log-causer-email">-</div>
                                    
                                    <div class="col-4"><strong>Job Title:</strong></div>
                                    <div class="col-8" id="modal-log-causer-job">-</div>
                                    
                                    <div class="col-4"><strong>Staff ID:</strong></div>
                                    <div class="col-8" id="modal-log-causer-id">-</div>
                                    
                                    <div class="col-4"><strong>Division:</strong></div>
                                    <div class="col-8" id="modal-log-causer-division">-</div>
                                    
                                    <div class="col-4"><strong>Duty Station:</strong></div>
                                    <div class="col-8" id="modal-log-causer-duty-station">-</div>
                                    
                                    <div class="col-4"><strong>Source:</strong></div>
                                    <div class="col-8">
                                        <span class="badge bg-info" id="modal-log-source">-</span>
                                    </div>
                                    
                                    <div class="col-4"><strong>Suspicious:</strong></div>
                                    <div class="col-8" id="modal-log-suspicious">-</div>
                                    
                                    <div class="col-4"><strong>Suspicious Reasons:</strong></div>
                                    <div class="col-8" id="modal-log-suspicious-reasons">-</div>
                                    
                                    <div class="col-4"><strong>Created:</strong></div>
                                    <div class="col-8" id="modal-log-created">-</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Data Changes -->
                    <div class="col-12" id="data-changes-section" style="display: none;">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="card-title text-primary mb-3">
                                    <i class="bx bx-data me-2"></i>Data Changes
                                </h6>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-danger">Old Values</h6>
                                        <pre class="bg-white p-3 rounded border" id="modal-log-old-values"><code>-</code></pre>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h6 class="text-success">New Values</h6>
                                        <pre class="bg-white p-3 rounded border" id="modal-log-new-values"><code>-</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Metadata -->
                    <div class="col-12" id="metadata-section" style="display: none;">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="card-title text-primary mb-3">
                                    <i class="bx bx-cog me-2"></i>Metadata
                                </h6>
                                <pre class="bg-white p-3 rounded border" id="modal-log-metadata"><code>-</code></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Cleanup Modal -->
<div class="modal fade" id="cleanupModal" tabindex="-1" aria-labelledby="cleanupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="cleanupModalLabel">
                    <i class="bx bx-trash me-2"></i>
                    Cleanup Old Audit Logs
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="bx bx-info-circle me-2"></i>
                    <strong>Warning:</strong> This action will permanently delete old audit log entries. This cannot be undone.
                </div>
                
                <div id="cleanup-stats" class="mb-3">
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="card border-primary">
                                <div class="card-body">
                                    <h6 class="card-title text-primary">Total Logs</h6>
                                    <h4 class="text-primary" id="total-logs">-</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="card border-warning">
                                <div class="card-body">
                                    <h6 class="card-title text-warning">Old Logs</h6>
                                    <h4 class="text-warning" id="old-logs">-</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="card border-info">
                                <div class="card-body">
                                    <h6 class="card-title text-info">Retention</h6>
                                    <h4 class="text-info" id="retention-days">-</h4>
                                    <small class="text-muted">days</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <form id="cleanup-form">
                    <div class="mb-3">
                        <label for="retention-days-input" class="form-label">Retention Period (Days)</label>
                        <input type="number" class="form-control" id="retention-days-input" name="retention_days" 
                               min="30" max="3650" value="365" required>
                        <div class="form-text">Logs older than this number of days will be deleted.</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="confirm-cleanup" required>
                            <label class="form-check-label" for="confirm-cleanup">
                                I understand that this action cannot be undone and will permanently delete old audit logs.
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirm-cleanup-btn" disabled>
                    <i class="bx bx-trash me-1"></i> Cleanup Old Logs
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Reversal Modal -->
<div class="modal fade" id="reversalModal" tabindex="-1" aria-labelledby="reversalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="reversalModalLabel">
                    <i class="bx bx-undo me-2"></i>
                    Reverse Audit Log Action
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="bx bx-info-circle me-2"></i>
                    <strong>Warning:</strong> This action will create a reversal entry in the audit log. This action cannot be undone.
                </div>
                
                <div id="reversal-log-details" class="mb-3">
                    <div class="card border-primary">
                        <div class="card-header bg-light">
                            <h6 class="mb-0 text-primary">Log Details to be Reversed</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <strong>Action:</strong> <span id="reversal-action" class="badge bg-primary">-</span>
                                </div>
                                <div class="col-6">
                                    <strong>Entity ID:</strong> <span id="reversal-entity">-</span>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-12">
                                    <strong>Table:</strong> <span id="reversal-table" class="text-muted">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <form id="reversal-form">
                    <input type="hidden" id="reversal-log-id" name="log_id">
                    <input type="hidden" id="reversal-table-name" name="table">
                    
                    <div class="mb-3">
                        <label for="reversal-reason" class="form-label">Reason for Reversal <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reversal-reason" name="reason" rows="4" 
                                  placeholder="Please provide a detailed reason for reversing this audit log action..." 
                                  minlength="10" maxlength="500" required></textarea>
                        <div class="form-text">Minimum 10 characters, maximum 500 characters.</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="confirm-reversal" required>
                            <label class="form-check-label" for="confirm-reversal">
                                I understand that this action will create a permanent reversal entry in the audit log and cannot be undone.
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirm-reversal-btn" disabled>
                    <i class="bx bx-undo me-1"></i> Reverse Action
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.audit-logs-table td {
    vertical-align: middle;
}

.audit-logs-table .division-duty-station {
    max-width: 200px;
    word-wrap: break-word;
    word-break: break-word;
    white-space: normal;
}

.audit-logs-table .division-duty-station .badge {
    display: inline-block;
    margin-bottom: 2px;
    white-space: normal;
    word-wrap: break-word;
}

.audit-logs-table .suspicious-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.audit-logs-table .suspicious-badge.bg-danger {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}
</style>
@endpush

@push('scripts')
<script>
// Initialize datepicker for custom date fields
$(document).ready(function() {
    $('.datepicker').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        todayHighlight: true
    });
});

// Auto-submit form on filter change
document.getElementById('filterForm').addEventListener('change', function() {
    this.submit();
});

// Cleanup Modal Functionality
document.getElementById('cleanupModal').addEventListener('show.bs.modal', function () {
    // Load cleanup statistics
    fetch('{{ route("audit-logs.cleanup-modal") }}')
        .then(response => response.json())
        .then(data => {
            document.getElementById('total-logs').textContent = data.total_logs.toLocaleString();
            document.getElementById('old-logs').textContent = data.old_logs.toLocaleString();
            document.getElementById('retention-days').textContent = data.retention_days;
            document.getElementById('retention-days-input').value = data.retention_days;
        })
        .catch(error => {
            console.error('Error loading cleanup stats:', error);
            show_notification('Error loading cleanup statistics', 'error');
        });
});

// Enable/disable cleanup button based on checkbox
document.getElementById('confirm-cleanup').addEventListener('change', function() {
    document.getElementById('confirm-cleanup-btn').disabled = !this.checked;
});

// Handle cleanup form submission
document.getElementById('confirm-cleanup-btn').addEventListener('click', function() {
    const retentionDays = document.getElementById('retention-days-input').value;
    const confirmCheckbox = document.getElementById('confirm-cleanup');
    
    if (!confirmCheckbox.checked) {
        show_notification('Please confirm that you understand the consequences', 'warning');
        return;
    }
    
    if (!retentionDays || retentionDays < 30) {
        show_notification('Please enter a valid retention period (minimum 30 days)', 'warning');
        return;
    }
    
    // Show loading state
    const btn = this;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bx bx-loader-alt bx-spin me-1"></i> Cleaning up...';
    
    // Submit cleanup request
    fetch('{{ route("audit-logs.cleanup") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            retention_days: retentionDays
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            show_notification(data.message, 'success');
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('cleanupModal')).hide();
            // Reload page to show updated statistics
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            show_notification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error during cleanup:', error);
        show_notification('An error occurred during cleanup', 'error');
    })
    .finally(() => {
        // Reset button state
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});

// Reversal Modal Functionality
document.getElementById('reversalModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    
    // Get data from the button
    const logId = button.getAttribute('data-log-id');
    const table = button.getAttribute('data-log-table');
    const action = button.getAttribute('data-log-action');
    const entity = button.getAttribute('data-log-entity');
    
    // Populate the modal with log details
    document.getElementById('reversal-action').textContent = action;
    document.getElementById('reversal-entity').textContent = entity;
    document.getElementById('reversal-table').textContent = table;
    document.getElementById('reversal-log-id').value = logId;
    document.getElementById('reversal-table-name').value = table;
    
    // Reset form
    document.getElementById('reversal-reason').value = '';
    document.getElementById('confirm-reversal').checked = false;
    document.getElementById('confirm-reversal-btn').disabled = true;
});

// Enable/disable reversal button based on checkbox and reason
document.getElementById('confirm-reversal').addEventListener('change', function() {
    const reason = document.getElementById('reversal-reason').value.trim();
    const confirmCheckbox = this.checked;
    
    document.getElementById('confirm-reversal-btn').disabled = !(confirmCheckbox && reason.length >= 10);
});

document.getElementById('reversal-reason').addEventListener('input', function() {
    const reason = this.value.trim();
    const confirmCheckbox = document.getElementById('confirm-reversal').checked;
    
    document.getElementById('confirm-reversal-btn').disabled = !(confirmCheckbox && reason.length >= 10);
});

// Handle reversal form submission
document.getElementById('confirm-reversal-btn').addEventListener('click', function() {
    const logId = document.getElementById('reversal-log-id').value;
    const table = document.getElementById('reversal-table-name').value;
    const reason = document.getElementById('reversal-reason').value.trim();
    const confirmCheckbox = document.getElementById('confirm-reversal').checked;
    
    if (!confirmCheckbox) {
        show_notification('Please confirm that you understand the consequences', 'warning');
        return;
    }
    
    if (reason.length < 10) {
        show_notification('Please provide a detailed reason (minimum 10 characters)', 'warning');
        return;
    }
    
    // Show loading state
    const btn = this;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bx bx-loader-alt bx-spin me-1"></i> Reversing...';
    
    // Submit reversal request
    fetch('{{ route("audit-logs.reverse") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            log_id: logId,
            table: table,
            reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            show_notification(data.message, 'success');
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('reversalModal')).hide();
            // Reload page to show updated audit logs
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            show_notification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error during reversal:', error);
        show_notification('An error occurred during reversal', 'error');
    })
    .finally(() => {
        // Reset button state
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});

// Handle modal data population
document.getElementById('auditLogModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    
    // Get data attributes
    const logId = button.getAttribute('data-log-id');
    const logTable = button.getAttribute('data-log-table');
    const logAction = button.getAttribute('data-log-action');
    const logEntity = button.getAttribute('data-log-entity');
    const logCauserType = button.getAttribute('data-log-causer-type');
    const logCauserId = button.getAttribute('data-log-causer-id');
    const logCauserName = button.getAttribute('data-log-causer-name');
    const logCauserEmail = button.getAttribute('data-log-causer-email');
    const logCauserJob = button.getAttribute('data-log-causer-job');
    const logCauserDivision = button.getAttribute('data-log-causer-division');
    const logCauserDutyStation = button.getAttribute('data-log-causer-duty-station');
    const logSource = button.getAttribute('data-log-source');
    const logSuspicious = button.getAttribute('data-log-suspicious');
    const logSuspiciousReasons = button.getAttribute('data-log-suspicious-reasons');
    const logCreated = button.getAttribute('data-log-created');
    const logOldValues = button.getAttribute('data-log-old-values');
    const logNewValues = button.getAttribute('data-log-new-values');
    const logMetadata = button.getAttribute('data-log-metadata');
    
    // Populate basic information
    document.getElementById('modal-log-id').textContent = logId;
    document.getElementById('modal-log-entity').textContent = logEntity;
    document.getElementById('modal-log-table').textContent = logTable;
    document.getElementById('modal-log-causer-name').textContent = logCauserName || 'Unknown User';
    document.getElementById('modal-log-causer-email').textContent = logCauserEmail || 'N/A';
    document.getElementById('modal-log-causer-job').textContent = logCauserJob || 'N/A';
    document.getElementById('modal-log-causer-id').textContent = logCauserId || 'N/A';
    document.getElementById('modal-log-causer-division').textContent = logCauserDivision || 'N/A';
    document.getElementById('modal-log-causer-duty-station').textContent = logCauserDutyStation || 'N/A';
    document.getElementById('modal-log-source').textContent = logSource || 'Unknown';
    
    // Set suspicious status
    const suspiciousElement = document.getElementById('modal-log-suspicious');
    if (logSuspicious === 'Yes') {
        suspiciousElement.innerHTML = '<span class="badge bg-danger"><i class="bx bx-shield-x"></i> Yes</span>';
    } else {
        suspiciousElement.innerHTML = '<span class="badge bg-success"><i class="bx bx-shield-check"></i> No</span>';
    }
    
    // Set suspicious reasons
    document.getElementById('modal-log-suspicious-reasons').textContent = logSuspiciousReasons || 'None';
    
    // Format and set action badge
    const actionBadge = document.getElementById('modal-log-action');
    actionBadge.textContent = logAction;
    actionBadge.className = 'badge ' + (logAction === 'created' ? 'bg-success' : (logAction === 'updated' ? 'bg-warning' : 'bg-danger'));
    
    // Format and set created date
    if (logCreated) {
        const createdDate = new Date(logCreated);
        document.getElementById('modal-log-created').innerHTML = 
            createdDate.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) + 
            ' ' + createdDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) +
            '<br><small class="text-muted">(' + getRelativeTime(createdDate) + ')</small>';
    }
    
    // Handle old/new values
    const dataChangesSection = document.getElementById('data-changes-section');
    if (logOldValues || logNewValues) {
        dataChangesSection.style.display = 'block';
        
        if (logOldValues) {
            try {
                const oldValues = JSON.parse(logOldValues);
                document.getElementById('modal-log-old-values').innerHTML = '<code>' + JSON.stringify(oldValues, null, 2) + '</code>';
            } catch (e) {
                document.getElementById('modal-log-old-values').innerHTML = '<code>' + logOldValues + '</code>';
            }
        } else {
            document.getElementById('modal-log-old-values').innerHTML = '<code>No old values</code>';
        }
        
        if (logNewValues) {
            try {
                const newValues = JSON.parse(logNewValues);
                document.getElementById('modal-log-new-values').innerHTML = '<code>' + JSON.stringify(newValues, null, 2) + '</code>';
            } catch (e) {
                document.getElementById('modal-log-new-values').innerHTML = '<code>' + logNewValues + '</code>';
            }
        } else {
            document.getElementById('modal-log-new-values').innerHTML = '<code>No new values</code>';
        }
    } else {
        dataChangesSection.style.display = 'none';
    }
    
    // Handle metadata
    const metadataSection = document.getElementById('metadata-section');
    if (logMetadata) {
        metadataSection.style.display = 'block';
        try {
            const metadata = JSON.parse(logMetadata);
            document.getElementById('modal-log-metadata').innerHTML = '<code>' + JSON.stringify(metadata, null, 2) + '</code>';
        } catch (e) {
            document.getElementById('modal-log-metadata').innerHTML = '<code>' + logMetadata + '</code>';
        }
    } else {
        metadataSection.style.display = 'none';
    }
});

// Helper function for relative time
function getRelativeTime(date) {
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) return 'just now';
    if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + ' minutes ago';
    if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + ' hours ago';
    return Math.floor(diffInSeconds / 86400) + ' days ago';
}
</script>
@endpush
