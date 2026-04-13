@extends('layouts.app')

@section('title', 'API users')

@section('header', 'APM API users')

@section('content')
@if(session('msg'))
    <div class="alert alert-{{ session('type', 'info') }} alert-dismissible fade show" role="alert">
        {{ session('msg') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if(!$staffDbLinked)
    <div class="alert alert-warning">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Staff database not linked.</strong> Set <code>STAFF_DB_DATABASE</code> (and optional <code>STAFF_DB_HOST</code>, <code>STAFF_DB_USERNAME</code>, <code>STAFF_DB_PASSWORD</code>) in <code>.env</code> to mirror this flag to the CodeIgniter <code>user</code> table.
        Otherwise, use <strong>Staff → User Management</strong> as the source of truth; <code>php artisan users:sync</code> will refresh from <code>/share/users</code>.
    </div>
@endif

<div class="card shadow-sm">
    <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="mb-0"><i class="fas fa-mobile-alt me-2 text-primary"></i>Mobile / API users (<code>apm_api_users</code>)</h5>
        <form method="get" action="{{ route('apm-api-users.index') }}" class="d-flex gap-2">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Email, name, user or staff ID…" value="{{ request('search') }}" style="min-width: 220px;">
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bx bx-search"></i></button>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>User ID</th>
                        <th>Staff ID</th>
                        <th>Email</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Email login</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $u)
                        <tr>
                            <td>{{ $u->user_id }}</td>
                            <td>{{ $u->auth_staff_id }}</td>
                            <td class="text-break">{{ $u->email ?? '—' }}</td>
                            <td>{{ $u->name ?? '—' }}</td>
                            <td>
                                @if($u->status)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td>
                                @if($u->allow_email_login)
                                    <span class="badge bg-success">Allowed</span>
                                @else
                                    <span class="badge bg-secondary">Off</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <form method="post" action="{{ route('apm-api-users.update-allow-email-login', $u) }}" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="allow_email_login" value="{{ $u->allow_email_login ? '0' : '1' }}">
                                    <button type="submit" class="btn btn-sm {{ $u->allow_email_login ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                        @if($u->allow_email_login)
                                            Disable email login
                                        @else
                                            Enable email login
                                        @endif
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No API users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($users->hasPages())
        <div class="card-footer">{{ $users->links() }}</div>
    @endif
</div>
@endsection
