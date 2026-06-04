<div>
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h4 class="text-success fw-bold mb-0">Leave management</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('leave.apply') }}" class="btn btn-success btn-sm"><i class="bx bx-plus"></i> Apply for leave</a>
            @if ($isHr)
                <a href="{{ route('settings.leave') }}" class="btn btn-outline-secondary btn-sm">Leave settings</a>
            @endif
        </div>
    </div>

    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <ul class="nav nav-pills mb-3">
        <li class="nav-item"><button type="button" class="nav-link @if($view==='balances') active @endif" wire:click="$set('view','balances')">My balances</button></li>
        <li class="nav-item"><button type="button" class="nav-link @if($view==='requests') active @endif" wire:click="$set('view','requests')">My requests</button></li>
        <li class="nav-item"><button type="button" class="nav-link @if($view==='approvals') active @endif" wire:click="$set('view','approvals')">Approvals</button></li>
    </ul>

    @if ($view === 'balances')
        <div class="row g-3">
            @forelse ($balanceRows as $row)
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="fw-semibold text-success">{{ $row['type']->leave_name }}</h6>
                            <p class="display-6 fw-bold mb-1">{{ $row['balance']['available'] }}</p>
                            <p class="small text-muted mb-0">days available ({{ $row['balance']['year'] }})</p>
                            <hr class="my-2">
                            <div class="small">
                                <div class="d-flex justify-content-between"><span>Opening</span><span>{{ $row['balance']['opening'] }}</span></div>
                                <div class="d-flex justify-content-between"><span>Carried forward</span><span>{{ $row['balance']['carried_forward'] }}</span></div>
                                <div class="d-flex justify-content-between"><span>Accrued</span><span>{{ $row['balance']['accrued'] }}</span></div>
                                <div class="d-flex justify-content-between"><span>Used</span><span>{{ $row['balance']['used'] }}</span></div>
                                <div class="d-flex justify-content-between"><span>Pending</span><span>{{ $row['balance']['pending'] }}</span></div>
                                @if ($row['balance']['compensatory'] > 0)
                                    <div class="d-flex justify-content-between text-primary"><span>Compensatory</span><span>{{ $row['balance']['compensatory'] }}</span></div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-muted">No leave types configured. Ask HR to set up leave types in Settings.</div>
            @endforelse
        </div>
    @endif

    @if ($view === 'requests')
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <select class="form-select" wire:model.live="statusFilter">
                            <option value="">All statuses</option>
                            <option value="Pending">Pending</option>
                            <option value="Approved">Approved</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3"><input type="date" class="form-control" wire:model.live="startDate"></div>
                    <div class="col-md-3"><input type="date" class="form-control" wire:model.live="endDate"></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($requests as $req)
                                <tr>
                                    <td>{{ $req->leaveType?->leave_name }}</td>
                                    <td>{{ $req->start_date?->format('d M Y') }}</td>
                                    <td>{{ $req->end_date?->format('d M Y') }}</td>
                                    <td>{{ $req->requested_days }}</td>
                                    <td><span class="badge bg-{{ $req->overall_status === 'Approved' ? 'success' : ($req->overall_status === 'Rejected' ? 'danger' : 'warning') }}">{{ $req->overall_status }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted text-center">No leave requests found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if ($view === 'approvals')
        <div class="table-responsive card border-0 shadow-sm">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr><th>Staff</th><th>Leave</th><th>Days</th><th>Period</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($pendingApprovals as $req)
                        <tr>
                            <td>{{ $req->staff?->fname }} {{ $req->staff?->lname }}</td>
                            <td>{{ $req->leaveType?->leave_name }}</td>
                            <td>{{ $req->requested_days }}</td>
                            <td>{{ $req->start_date?->format('d M Y') }} – {{ $req->end_date?->format('d M Y') }}</td>
                            <td class="text-nowrap">
                                @if ($isHr)
                                    <button type="button" class="btn btn-sm btn-success" wire:click="approve({{ $req->request_id }}, 'hr', 'approve')">HR ✓</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="approve({{ $req->request_id }}, 'hr', 'reject')">HR ✗</button>
                                @endif
                                <button type="button" class="btn btn-sm btn-outline-success" wire:click="approve({{ $req->request_id }}, 'supervisor', 'approve')">Supervisor</button>
                                <button type="button" class="btn btn-sm btn-outline-primary" wire:click="approve({{ $req->request_id }}, 'hod', 'approve')">HOD</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted text-center py-4">No pending approvals.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>
