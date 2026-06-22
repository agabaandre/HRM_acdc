@php
    $auditLogsUrl = route('system-configs.index', ['tab' => 'audit-logs']);
@endphp
<div class="sys-config-audit">
<div class="row">
    <!-- Statistics Cards -->
    <div class="col-12 mb-4">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <div class="mb-2">
                            <i class="fas fa-list-alt fa-2x text-primary"></i>
                        </div>
                        <h6 class="card-title text-primary">Total Logs</h6>
                        <h3 class="text-primary" id="auditStatsTotal">{{ number_format($stats['total_logs']) }}</h3>
                        <small class="text-muted">All Time</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <div class="mb-2">
                            <i class="fas fa-clock fa-2x text-success"></i>
                        </div>
                        <h6 class="card-title text-success">Recent Activity</h6>
                        <h3 class="text-success" id="auditStatsRecent">{{ number_format($stats['recent_activity']) }}</h3>
                        <small class="text-muted">Last 24 Hours</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <div class="mb-2">
                            <i class="fas fa-chart-line fa-2x text-info"></i>
                        </div>
                        <h6 class="card-title text-info">Top Action</h6>
                        <h3 class="text-info" id="auditStatsTopAction">{{ $stats['top_action'] ?? ($stats['actions_count']->keys()->first() ?? 'N/A') }}</h3>
                        <small class="text-muted" id="auditStatsTopActionCount">{{ $stats['top_action_count'] ?? ($stats['actions_count']->first() ?? 0) }} times</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <div class="mb-2">
                            <i class="fas fa-database fa-2x text-warning"></i>
                        </div>
                        <h6 class="card-title text-dark">Top Table</h6>
                        <h4 class="text-dark" id="auditStatsTopTable">{{ str_replace('audit_', '', $stats['top_table'] ?? ($stats['tables_count']->keys()->first() ?? 'N/A')) }}</h4>
                        <small class="text-muted" id="auditStatsTopTableCount">{{ $stats['top_table_count'] ?? ($stats['tables_count']->first() ?? 0) }} records</small>
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
                <form method="GET" action="{{ $auditLogsUrl }}" id="filterForm" onsubmit="return false;">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="{{ request('search') }}" placeholder="Staff name, email, action, table, entity ID…">
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
                                <button type="button" class="btn btn-primary btn-sm" id="auditFilterApply">
                                    <i class="bx bx-search"></i>
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm" id="auditFilterReset">
                                    <i class="bx bx-x"></i>
                                </button>
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
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0"><i class="bx bx-list-ul me-2 text-primary"></i>Audit Logs</h6>
                <small class="text-muted" id="auditTableRange">Loading…</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 audit-logs-table w-100" id="auditLogsTable">
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
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Audit Log Details Modal -->
<div class="modal fade" id="auditLogModal" tabindex="-1" aria-labelledby="auditLogModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
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
                    <span id="reversal-modal-title">Action</span>
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
                            <h6 class="mb-0 text-primary">Log Details</h6>
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
                                    <label for="reversal-model-table" class="form-label"><strong>Model Table:</strong></label>
                                    <input type="text" class="form-control" id="reversal-model-table" name="model_table" 
                                           placeholder="e.g., change_requests" required>
                                    <div class="form-text">The actual database table name for the model (auto-detected from audit table).</div>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-12">
                                    <strong>Audit Table:</strong> <span id="reversal-table" class="text-muted">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <form id="reversal-form">
                    <input type="hidden" id="reversal-log-id" name="log_id">
                    <input type="hidden" id="reversal-table-name" name="table">
                    <input type="hidden" id="reversal-log-action" name="log_action">
                    
                    <div class="mb-3">
                        <label class="form-label"><strong>Select Action Type <span class="text-danger">*</span></strong></label>
                        <div class="card border-primary">
                            <div class="card-body">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="action_type" id="action-restore" value="restore" required>
                                    <label class="form-check-label" for="action-restore">
                                        <strong class="text-success"><i class="bx bx-refresh me-1"></i> Restore Record</strong>
                                        <div class="form-text text-muted">Restore/re-insert a deleted record or restore previous values for an updated record.</div>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="action_type" id="action-delete" value="delete" required>
                                    <label class="form-check-label" for="action-delete">
                                        <strong class="text-danger"><i class="bx bx-trash me-1"></i> Delete Record</strong>
                                        <div class="form-text text-muted">Permanently delete a record from the database.</div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reversal-reason" class="form-label">Reason for Action <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reversal-reason" name="reason" rows="4" 
                                  placeholder="Please provide a detailed reason for this action..." 
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
                    <i class="bx bx-undo me-1" id="reversal-btn-icon"></i> <span id="reversal-btn-text">Action</span>
                </button>
            </div>
        </div>
    </div>
</div>
</div>

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

/* Causer column - reduce width by 5% and add text wrapping */
.audit-logs-table td:nth-child(5) {
    width: 15%; /* Reduced from ~20% to 15% (5% reduction) */
    max-width: 150px;
    word-wrap: break-word;
    word-break: break-word;
    white-space: normal;
}

/* Source column - make much smaller and add text wrapping */
.audit-logs-table td:nth-child(7) {
    width: 8%; /* Much smaller width */
    max-width: 80px;
    word-wrap: break-word;
    word-break: break-word;
    white-space: normal;
    padding: 0.5rem 0.25rem; /* Reduce padding */
}

/* Ensure badges in source column wrap properly */
.audit-logs-table td:nth-child(7) .badge {
    display: block;
    margin-bottom: 2px;
    white-space: normal;
    word-wrap: break-word;
    word-break: break-word;
    font-size: 0.7rem; /* Smaller font size */
    padding: 0.25rem 0.4rem; /* Smaller padding */
    line-height: 1.2; /* Tighter line height */
    text-align: center; /* Center the text */
}

/* Ensure causer content wraps properly */
.audit-logs-table td:nth-child(5) .fw-semibold,
.audit-logs-table td:nth-child(5) .text-muted {
    display: block;
    word-wrap: break-word;
    word-break: break-word;
    white-space: normal;
}

/* Source column header styling */
.audit-logs-table th:nth-child(7) {
    width: 8%;
    max-width: 80px;
    padding: 0.5rem 0.25rem;
    font-size: 0.85rem;
    text-align: center;
}

/* Summary cards text size and color improvements */
.card-body h6.card-title {
    font-size: 0.9rem !important;
    font-weight: 600;
    color: #2c3e50 !important;
    margin-bottom: 0.5rem;
}

.card-body h3 {
    font-size: 1.8rem !important;
    font-weight: 700;
    color: #2c3e50 !important;
    margin-bottom: 0.25rem;
}

.card-body small.text-muted {
    font-size: 0.75rem !important;
    color: #6c757d !important;
    font-weight: 500;
}

/* Improve card border colors for better contrast */
.card.border-primary {
    border-color: #0d6efd !important;
}

.card.border-success {
    border-color: #198754 !important;
}

.card.border-info {
    border-color: #0dcaf0 !important;
}

.card.border-warning {
    border-color: #ffc107 !important;
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

/* Modal centering improvements */
.modal-dialog-centered {
    display: flex;
    align-items: center;
    min-height: calc(100% - 1rem);
}

@media (min-width: 576px) {
    .modal-dialog-centered {
        min-height: calc(100% - 3.5rem);
    }
}

/* Ensure modal content doesn't exceed viewport height */
.modal-dialog-scrollable .modal-content {
    max-height: 90vh;
    overflow: hidden;
}

.modal-dialog-scrollable .modal-body {
    overflow-y: auto;
    max-height: calc(90vh - 120px); /* Account for header and footer */
}

/* Better spacing for modal content */
#auditLogModal .modal-body {
    padding: 1.5rem;
}

#auditLogModal .card {
    margin-bottom: 1rem;
}

#auditLogModal .card:last-child {
    margin-bottom: 0;
}
</style>
@endpush

@push('scripts')
<script>
const auditLogsDataUrl = @json(route('audit-logs.data'));
const auditLogsCanReverse = @json(in_array(91, user_session('permissions', [])));
let auditLogsTable = null;

function escapeAuditHtml(value) {
    if (value == null) return '';
    const div = document.createElement('div');
    div.textContent = String(value);
    return div.innerHTML;
}

function getAuditFilterParams() {
    return {
        search: document.getElementById('search')?.value || '',
        action: document.getElementById('action')?.value || '',
        table: document.getElementById('table')?.value || '',
        date_from: document.getElementById('date_from')?.value || '',
        date_to: document.getElementById('date_to')?.value || '',
        suspicious: document.getElementById('suspicious')?.value || ''
    };
}

function updateAuditStats(stats) {
    if (!stats) return;
    const totalEl = document.getElementById('auditStatsTotal');
    const recentEl = document.getElementById('auditStatsRecent');
    const topActionEl = document.getElementById('auditStatsTopAction');
    const topActionCountEl = document.getElementById('auditStatsTopActionCount');
    const topTableEl = document.getElementById('auditStatsTopTable');
    const topTableCountEl = document.getElementById('auditStatsTopTableCount');

    if (totalEl) totalEl.textContent = Number(stats.total_logs || 0).toLocaleString();
    if (recentEl) recentEl.textContent = Number(stats.recent_activity || 0).toLocaleString();
    if (topActionEl) topActionEl.textContent = stats.top_action || 'N/A';
    if (topActionCountEl) topActionCountEl.textContent = (stats.top_action_count || 0) + ' times';
    if (topTableEl) {
        topTableEl.textContent = String(stats.top_table || 'N/A').replace(/^audit_/, '');
    }
    if (topTableCountEl) topTableCountEl.textContent = (stats.top_table_count || 0) + ' records';
}

function updateAuditTableRange(start, total, pageCount) {
    const el = document.getElementById('auditTableRange');
    if (!el) return;
    if (!total) {
        el.textContent = 'No logs match the current filters';
        return;
    }
    const from = start + 1;
    const to = start + pageCount;
    el.textContent = 'Showing ' + from + '–' + to + ' of ' + Number(total).toLocaleString() + ' logs';
}

function reversalActionMeta(action) {
    if (action === 'created') return { text: 'Delete', icon: 'bx-trash' };
    if (action === 'deleted') return { text: 'Recover', icon: 'bx-refresh' };
    if (action === 'updated') return { text: 'Restore', icon: 'bx-reset' };
    return { text: 'Action', icon: 'bx-undo' };
}

function formatAuditCreatedAt(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return escapeAuditHtml(value);
    return '<div class="fw-semibold">' + date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) + '</div>'
        + '<small class="text-muted">' + date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' }) + '</small>';
}

function formatAuditJsonBlock(value) {
    if (value == null || value === '') {
        return null;
    }
    if (typeof value === 'object') {
        return JSON.stringify(value, null, 2);
    }
    const text = String(value).trim();
    if (!text) {
        return null;
    }
    try {
        return JSON.stringify(JSON.parse(text), null, 2);
    } catch (e) {
        return text;
    }
}

function setAuditModalCodeContent(elementId, value, emptyLabel) {
    const container = document.getElementById(elementId);
    if (!container) return;
    const formatted = formatAuditJsonBlock(value);
    let code = container.querySelector('code');
    if (!code) {
        container.innerHTML = '<code></code>';
        code = container.querySelector('code');
    }
    code.textContent = formatted ?? emptyLabel;
}

function renderAuditActions(row) {
    let html = '<div class="btn-group" role="group">'
        + '<button type="button" class="btn btn-sm btn-outline-primary audit-log-view-btn" data-bs-toggle="modal" data-bs-target="#auditLogModal">'
        + '<i class="bx bx-show me-1"></i>View</button>';

    if (auditLogsCanReverse && ['created', 'updated', 'deleted'].includes(row.action)) {
        const meta = reversalActionMeta(row.action);
        html += '<button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#reversalModal" '
            + 'data-log-id="' + escapeAuditHtml(row.id) + '" '
            + 'data-log-table="' + escapeAuditHtml(row.source_table) + '" '
            + 'data-log-action="' + escapeAuditHtml(row.action) + '" '
            + 'data-log-entity="' + escapeAuditHtml(row.entity_id ?? 'N/A') + '" '
            + 'title="' + escapeAuditHtml(meta.text) + '">'
            + '<i class="bx ' + meta.icon + ' me-1"></i>' + escapeAuditHtml(meta.text) + '</button>';
    }

    html += '</div>';
    return html;
}

function initializeAuditLogsTable() {
    if (typeof $ === 'undefined' || !$.fn.DataTable) return;

    const $table = $('#auditLogsTable');
    if (!$table.length) return;

    if ($.fn.DataTable.isDataTable($table[0])) {
        $table.DataTable().destroy();
    }

    auditLogsTable = $table.DataTable({
        processing: true,
        serverSide: true,
        searching: false,
        ordering: false,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        ajax: function(data, callback) {
            const params = Object.assign({}, data, getAuditFilterParams());
            $.get(auditLogsDataUrl, params)
                .done(function(json) {
                    if (json && json.stats) {
                        updateAuditStats(json.stats);
                    }
                    const rows = (json && json.data) ? json.data : [];
                    updateAuditTableRange(data.start || 0, json.recordsFiltered || 0, rows.length);
                    callback(json);
                })
                .fail(function() {
                    updateAuditTableRange(0, 0, 0);
                    callback({ draw: data.draw, recordsTotal: 0, recordsFiltered: 0, data: [] });
                });
        },
        columns: [
            {
                data: 'id',
                render: function(data) {
                    return '<span class="badge bg-secondary">#' + escapeAuditHtml(data) + '</span>';
                }
            },
            {
                data: 'action',
                render: function(data) {
                    const cls = data === 'created' ? 'bg-success' : (data === 'updated' ? 'bg-warning' : 'bg-danger');
                    return '<span class="badge ' + cls + '">' + escapeAuditHtml(data) + '</span>';
                }
            },
            {
                data: 'entity_id',
                render: function(data) {
                    return '<div class="fw-semibold">ID: ' + escapeAuditHtml(data ?? 'N/A') + '</div>';
                }
            },
            {
                data: 'source_table',
                render: function(data) {
                    return '<code class="small">' + escapeAuditHtml(data) + '</code>';
                }
            },
            {
                data: null,
                render: function(data, type, row) {
                    if (!row.causer_id) {
                        return '<span class="text-muted">System</span>';
                    }
                    return '<div class="fw-semibold">' + escapeAuditHtml(row.causer_name || 'Unknown User') + '</div>'
                        + '<small class="text-muted">' + escapeAuditHtml(row.causer_job_title || 'N/A') + '</small><br>'
                        + '<small class="text-muted">' + escapeAuditHtml(row.causer_email || 'N/A') + '</small>';
                }
            },
            {
                data: null,
                className: 'division-duty-station',
                render: function(data, type, row) {
                    if (!row.causer_id) return '<span class="text-muted">-</span>';
                    return '<div class="mb-1"><span class="badge bg-primary">' + escapeAuditHtml(row.causer_division_name || 'N/A') + '</span></div>'
                        + '<div><span class="badge bg-secondary">' + escapeAuditHtml(row.causer_duty_station_name || 'N/A') + '</span></div>';
                }
            },
            {
                data: 'source',
                render: function(data) {
                    return '<span class="badge bg-info">' + escapeAuditHtml(data || 'Unknown') + '</span>';
                }
            },
            {
                data: 'is_suspicious',
                render: function(data, type, row) {
                    if (data) {
                        return '<span class="badge bg-danger suspicious-badge" title="' + escapeAuditHtml(row.suspicious_reasons || 'Suspicious activity detected') + '"><i class="bx bx-shield-x"></i> Yes</span>';
                    }
                    return '<span class="badge bg-success suspicious-badge"><i class="bx bx-shield-check"></i> No</span>';
                }
            },
            {
                data: 'created_at',
                render: function(data) {
                    return formatAuditCreatedAt(data);
                }
            },
            {
                data: null,
                orderable: false,
                render: function(data, type, row) {
                    return renderAuditActions(row);
                }
            }
        ],
        language: {
            emptyTable: 'No audit logs found for the current filters',
            processing: '<i class="bx bx-loader-alt bx-spin"></i> Loading audit logs…'
        }
    });
}

$(document).ready(function() {
    $('.datepicker').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        todayHighlight: true
    });

    initializeAuditLogsTable();

    document.getElementById('auditFilterApply')?.addEventListener('click', function() {
        if (auditLogsTable) auditLogsTable.ajax.reload();
    });

    document.getElementById('auditFilterReset')?.addEventListener('click', function() {
        const form = document.getElementById('filterForm');
        if (form) form.reset();
        if (auditLogsTable) auditLogsTable.ajax.reload();
    });

    document.getElementById('filterForm')?.addEventListener('change', function() {
        if (auditLogsTable) auditLogsTable.ajax.reload();
    });
});

// Initialize datepicker for custom date fields
function show_reversal_error(message) {
    var text = (message && String(message).trim()) ? String(message) : 'An error occurred. No details available.';
    function escapeHtml(s) {
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }
    var id = 'reversal-error-' + Date.now();
    var copyBtnId = 'copy-reversal-error-' + Date.now();
    var html = '<div class="mb-2"><pre id="' + id + '" class="p-3 bg-light border rounded small mb-2" style="user-select: all; white-space: pre-wrap; word-break: break-word; max-height: 280px; overflow: auto;">' + escapeHtml(text) + '</pre></div>' +
        '<button type="button" class="btn btn-sm btn-outline-primary" id="' + copyBtnId + '"><i class="bx bx-copy me-1"></i> Copy full error</button>';
    if (typeof Lobibox !== 'undefined' && Lobibox.alert) {
        Lobibox.alert('error', {
            title: 'Restore / Reversal Error',
            msg: html,
            width: 520
        });
        setTimeout(function() {
            var btn = document.getElementById(copyBtnId);
            var pre = document.getElementById(id);
            if (btn && pre) {
                btn.onclick = function() {
                    var toCopy = pre.textContent || text;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(toCopy).then(function() {
                            btn.innerHTML = '<i class="bx bx-check me-1"></i> Copied!';
                            setTimeout(function() { btn.innerHTML = '<i class="bx bx-copy me-1"></i> Copy full error'; }, 2000);
                        }).catch(function() {
                            btn.innerHTML = '<i class="bx bx-x me-1"></i> Copy failed';
                            setTimeout(function() { btn.innerHTML = '<i class="bx bx-copy me-1"></i> Copy full error'; }, 2000);
                        });
                    } else {
                        var ta = document.createElement('textarea');
                        ta.value = toCopy;
                        ta.style.position = 'fixed'; ta.style.left = '-9999px';
                        document.body.appendChild(ta);
                        ta.select();
                        try {
                            document.execCommand('copy');
                            btn.innerHTML = '<i class="bx bx-check me-1"></i> Copied!';
                            setTimeout(function() { btn.innerHTML = '<i class="bx bx-copy me-1"></i> Copy full error'; }, 2000);
                        } catch (e) {
                            btn.innerHTML = '<i class="bx bx-x me-1"></i> Copy failed';
                        }
                        document.body.removeChild(ta);
                    }
                };
            }
        }, 350);
    } else {
        show_notification(text, 'error');
    }
}

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

// Function to detect model table name from audit table name
function detectModelTable(auditTable) {
    // Remove 'audit_' prefix if present
    let modelTable = auditTable.replace(/^audit_/, '');
    // Remove '_logs' suffix if present
    modelTable = modelTable.replace(/_logs$/, '');
    
    // Try to detect common patterns
    // e.g., audit_change_requests_logs -> change_requests
    // e.g., audit_change_request_logs -> change_request
    // e.g., audit_users_logs -> users
    
    // If it ends with 's', it might be plural, but we'll keep it as is
    // The user can edit it if needed
    
    return modelTable;
}

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
    document.getElementById('reversal-log-action').value = action;
    
    // Auto-detect and populate model table name
    const detectedModelTable = detectModelTable(table);
    document.getElementById('reversal-model-table').value = detectedModelTable;
    
    // Set default action type based on log action
    // For 'created' logs, default to 'delete' (to delete the created record)
    // For 'deleted' logs, default to 'restore' (to restore the deleted record)
    // For 'updated' logs, default to 'restore' (to restore previous values)
    let defaultActionType = 'restore';
    if (action === 'created') {
        defaultActionType = 'delete';
    }
    
    // Set default radio button
    document.getElementById('action-restore').checked = (defaultActionType === 'restore');
    document.getElementById('action-delete').checked = (defaultActionType === 'delete');
    
    // Update button text and icon based on default selection
    updateActionButton(defaultActionType);
    
    // Reset form
    document.getElementById('reversal-reason').value = '';
    document.getElementById('confirm-reversal').checked = false;
    document.getElementById('confirm-reversal-btn').disabled = true;
});

// Function to update action button text and icon based on selected action type
function updateActionButton(actionType) {
    const btnText = document.getElementById('reversal-btn-text');
    const btnIcon = document.getElementById('reversal-btn-icon');
    const modalTitle = document.getElementById('reversal-modal-title');
    
    if (actionType === 'restore') {
        btnText.textContent = 'Restore';
        btnIcon.className = 'bx bx-refresh me-1';
        modalTitle.textContent = 'Restore Record';
    } else if (actionType === 'delete') {
        btnText.textContent = 'Delete';
        btnIcon.className = 'bx bx-trash me-1';
        modalTitle.textContent = 'Delete Record';
    }
}

// Listen for radio button changes (use event delegation since modal content is dynamic)
document.addEventListener('change', function(e) {
    if (e.target && e.target.name === 'action_type') {
        updateActionButton(e.target.value);
    }
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
    const modelTable = document.getElementById('reversal-model-table').value.trim();
    const reason = document.getElementById('reversal-reason').value.trim();
    const confirmCheckbox = document.getElementById('confirm-reversal').checked;
    
    // Get selected action type
    const actionTypeRadio = document.querySelector('input[name="action_type"]:checked');
    if (!actionTypeRadio) {
        show_notification('Please select an action type (Restore or Delete)', 'warning');
        return;
    }
    const actionType = actionTypeRadio.value;
    
    if (!modelTable) {
        show_notification('Please enter the model table name', 'warning');
        return;
    }
    
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
    btn.innerHTML = '<i class="bx bx-loader-alt bx-spin me-1"></i> Processing...';
    
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
            model_table: modelTable,
            action_type: actionType,
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
            show_reversal_error(data.message);
        }
    })
    .catch(error => {
        console.error('Error during reversal:', error);
        show_reversal_error(error.message || 'An error occurred during reversal');
    })
    .finally(() => {
        // Reset button state
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
});

// Handle modal data population — read full row from DataTable (JSON cannot live in HTML attributes)
document.getElementById('auditLogModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const row = (auditLogsTable && button)
        ? auditLogsTable.row($(button).closest('tr')).data()
        : null;

    if (!row) {
        return;
    }

    document.getElementById('modal-log-id').textContent = row.id ?? '-';
    document.getElementById('modal-log-entity').textContent = row.entity_id ?? 'N/A';
    document.getElementById('modal-log-table').textContent = row.source_table ?? '-';
    document.getElementById('modal-log-causer-name').textContent = row.causer_name || 'Unknown User';
    document.getElementById('modal-log-causer-email').textContent = row.causer_email || 'N/A';
    document.getElementById('modal-log-causer-job').textContent = row.causer_job_title || 'N/A';
    document.getElementById('modal-log-causer-id').textContent = row.causer_id || 'N/A';
    document.getElementById('modal-log-causer-division').textContent = row.causer_division_name || 'N/A';
    document.getElementById('modal-log-causer-duty-station').textContent = row.causer_duty_station_name || 'N/A';
    document.getElementById('modal-log-source').textContent = row.source || 'Unknown';

    const suspiciousElement = document.getElementById('modal-log-suspicious');
    if (row.is_suspicious) {
        suspiciousElement.innerHTML = '<span class="badge bg-danger"><i class="bx bx-shield-x"></i> Yes</span>';
    } else {
        suspiciousElement.innerHTML = '<span class="badge bg-success"><i class="bx bx-shield-check"></i> No</span>';
    }

    document.getElementById('modal-log-suspicious-reasons').textContent = row.suspicious_reasons || 'None';

    const logAction = row.action || '-';
    const actionBadge = document.getElementById('modal-log-action');
    actionBadge.textContent = logAction;
    actionBadge.className = 'badge ' + (logAction === 'created' ? 'bg-success' : (logAction === 'updated' ? 'bg-warning' : 'bg-danger'));

    if (row.created_at) {
        const createdDate = new Date(row.created_at);
        document.getElementById('modal-log-created').innerHTML =
            createdDate.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) +
            ' ' + createdDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) +
            '<br><small class="text-muted">(' + getRelativeTime(createdDate) + ')</small>';
    } else {
        document.getElementById('modal-log-created').textContent = '-';
    }

    const dataChangesSection = document.getElementById('data-changes-section');
    const hasOldValues = formatAuditJsonBlock(row.old_values) !== null;
    const hasNewValues = formatAuditJsonBlock(row.new_values) !== null;
    if (hasOldValues || hasNewValues) {
        dataChangesSection.style.display = 'block';
        setAuditModalCodeContent('modal-log-old-values', row.old_values, 'No old values');
        setAuditModalCodeContent('modal-log-new-values', row.new_values, 'No new values');
    } else {
        dataChangesSection.style.display = 'none';
    }

    const metadataSection = document.getElementById('metadata-section');
    if (formatAuditJsonBlock(row.metadata) !== null) {
        metadataSection.style.display = 'block';
        setAuditModalCodeContent('modal-log-metadata', row.metadata, 'No metadata');
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
