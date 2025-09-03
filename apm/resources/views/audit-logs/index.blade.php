@extends('layouts.app')

@section('title', 'Audit Logs')

@section('header', 'Audit Logs')

@section('header-actions')
<div class="d-flex gap-2">
    <button type="button" class="btn btn-warning" onclick="cleanupLogs()">
        <i class="bx bx-trash"></i> Cleanup Old Logs
    </button>
    <a href="{{ route('audit-logs.export', request()->query()) }}" class="btn btn-success">
        <i class="bx bx-download"></i> Export CSV
    </a>
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
                        <h3 class="text-info">{{ $stats['actions_count']->first()->action ?? 'N/A' }}</h3>
                        <small class="text-muted">{{ $stats['actions_count']->first()->count ?? 0 }} times</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="card-title text-warning">Top Resource</h6>
                        <h3 class="text-warning">{{ $stats['resource_types_count']->first()->resource_type ?? 'N/A' }}</h3>
                        <small class="text-muted">{{ $stats['resource_types_count']->first()->count ?? 0 }} times</small>
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
                            <label for="resource_type" class="form-label">Resource Type</label>
                            <select class="form-select" id="resource_type" name="resource_type">
                                <option value="">All Types</option>
                                @foreach($resourceTypes as $type)
                                    <option value="{{ $type }}" {{ request('resource_type') == $type ? 'selected' : '' }}>
                                        {{ $type }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="user_id" class="form-label">User</label>
                            <select class="form-select" id="user_id" name="user_id">
                                <option value="">All Users</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                        {{ $user->fname }} {{ $user->lname }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-1">
                            <label for="date_from" class="form-label">From</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" 
                                   value="{{ request('date_from') }}">
                        </div>
                        <div class="col-md-1">
                            <label for="date_to" class="form-label">To</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" 
                                   value="{{ request('date_to') }}">
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
                <small class="text-muted">Showing {{ $auditLogs->count() }} of {{ $auditLogs->total() }} logs</small>
            </div>
            <div class="card-body p-0">
                @if($auditLogs->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Resource</th>
                                    <th>Description</th>
                                    <th>IP Address</th>
                                    <th>Date/Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($auditLogs as $log)
                                    <tr>
                                        <td>
                                            <span class="badge bg-secondary">#{{ $log->id }}</span>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-primary rounded-circle d-flex align-items-center justify-content-center me-2">
                                                    <i class="bx bx-user text-white"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold">{{ $log->user_name ?: 'System' }}</div>
                                                    @if($log->user_email)
                                                        <small class="text-muted">{{ $log->user_email }}</small>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $log->action_badge_class }}">
                                                <i class="bx {{ $log->action_icon }} me-1"></i>
                                                {{ $log->action }}
                                            </span>
                                        </td>
                                        <td>
                                            <div>
                                                <div class="fw-semibold">{{ $log->resource_type }}</div>
                                                @if($log->resource_id)
                                                    <small class="text-muted">ID: {{ $log->resource_id }}</small>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 200px;" title="{{ $log->description }}">
                                                {{ $log->description }}
                                            </div>
                                        </td>
                                        <td>
                                            <code class="small">{{ $log->ip_address }}</code>
                                        </td>
                                        <td>
                                            <div>
                                                <div class="fw-semibold">{{ $log->created_at->format('M j, Y') }}</div>
                                                <small class="text-muted">{{ $log->created_at->format('g:i A') }}</small>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="{{ route('audit-logs.show', $log) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="bx bx-show"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="card-footer">
                        {{ $auditLogs->links() }}
                    </div>
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
@endsection

@push('scripts')
<script>
function cleanupLogs() {
    if (confirm('Are you sure you want to clean up old audit logs? This action cannot be undone.')) {
        fetch('{{ route("audit-logs.cleanup") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while cleaning up logs.');
        });
    }
}

// Auto-submit form on filter change
document.getElementById('filterForm').addEventListener('change', function() {
    this.submit();
});
</script>
@endpush
