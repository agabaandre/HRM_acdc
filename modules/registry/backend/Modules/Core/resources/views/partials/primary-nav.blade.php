@php
    $staffId = (int) (session('user.staff_id') ?? 0);
@endphp
<div class="nav-container primary-menu">
    <div class="mobile-topbar-header d-xl-none">
        <div>
            <img src="{{ \App\Support\CbpAsset::url('images/AU_CDC_Logo-800.png') }}" class="logo-icon" alt="Africa CDC" style="filter: brightness(0) invert(1); max-width: 120px;">
        </div>
        <div><h5 class="logo-text text-white mb-0">Staff Portal</h5></div>
        <div class="toggle-icon ms-auto"><i class="bx bx-arrow-to-left"></i></div>
    </div>
    <nav class="navbar navbar-expand-xl w-100">
        <ul class="navbar-nav justify-content-start flex-wrap">
            @if (portal_can(76))
                <li class="nav-item">
                    <a href="{{ route('dashboard.index') }}" class="nav-link {{ nav_active('dashboard') }}">
                        <div><i class="bx bx-home"></i></div>
                        <div class="menu-title">Dashboard</div>
                    </a>
                </li>
            @endif

            @if (portal_can(72) || portal_can(41))
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ nav_active('staff') }}" href="#" data-bs-toggle="dropdown" role="button" aria-expanded="false">
                        <div class="parent-icon"><i class="fa fa-user"></i></div>
                        <div class="menu-title">Staff Profile</div>
                    </a>
                    <ul class="dropdown-menu">
                        @if (portal_can(72) && portal_can(71))
                            <li><a class="dropdown-item" href="{{ route('staff.search') }}"><i class="bx bx-right-arrow-alt"></i>Quick search</a></li>
                            <li><a class="dropdown-item" href="{{ route('staff.index') }}"><i class="bx bx-right-arrow-alt"></i>Current staff list</a></li>
                            <li><a class="dropdown-item" href="{{ route('staff.all') }}"><i class="bx bx-right-arrow-alt"></i>All staff</a></li>
                            <li><a class="dropdown-item" href="{{ route('staff.contract-status', ['preset' => 'due']) }}"><i class="bx bx-right-arrow-alt"></i>Contracts due</a></li>
                            <li><a class="dropdown-item" href="{{ route('staff.contract-status', ['preset' => 'expired']) }}"><i class="bx bx-right-arrow-alt"></i>Contracts expired</a></li>
                            <li><a class="dropdown-item" href="{{ route('staff.contract-status', ['preset' => 'former']) }}"><i class="bx bx-right-arrow-alt"></i>Former staff</a></li>
                            <li><a class="dropdown-item" href="{{ route('staff.contract-status', ['preset' => 'renewal']) }}"><i class="bx bx-right-arrow-alt"></i>Under renewal</a></li>
                            <li><a class="dropdown-item" href="{{ route('staff.data-quality') }}"><i class="bx bx-right-arrow-alt"></i>Data quality report</a></li>
                        @endif
                        @if (portal_can(41))
                            <li><a class="dropdown-item" href="{{ route('staff.birthdays') }}"><i class="bx bx-right-arrow-alt"></i>Staff birthdays</a></li>
                        @endif
                    </ul>
                </li>
            @endif

            @if ($staffId > 0 && portal_can(37))
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ nav_active('leave') }}" href="#" data-bs-toggle="dropdown" role="button" aria-expanded="false">
                        <div class="parent-icon"><i class="bx bx-calendar-check"></i></div>
                        <div class="menu-title">Leave Application</div>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('leave.apply') }}"><i class="bx bx-right-arrow-alt"></i>Apply</a></li>
                        <li><a class="dropdown-item" href="{{ route('leave.index', ['view' => 'approvals']) }}"><i class="bx bx-right-arrow-alt"></i>Approve leave</a></li>
                        <li><a class="dropdown-item" href="{{ route('leave.index', ['view' => 'requests']) }}"><i class="bx bx-right-arrow-alt"></i>My leave status</a></li>
                        @if (portal_can(77))
                            <li><a class="dropdown-item" href="{{ route('leave.index', ['view' => 'all']) }}"><i class="bx bx-right-arrow-alt"></i>Leave status (all)</a></li>
                        @endif
                    </ul>
                </li>
            @endif

            @if ($staffId > 0 && portal_can(74))
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ nav_active('performance') }}" href="{{ route('performance.ppa-dashboard') }}" data-bs-toggle="dropdown" role="button" aria-expanded="false">
                        <div class="parent-icon"><i class="fa fa-line-chart"></i></div>
                        <div class="menu-title">Performance</div>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('performance.ppa-dashboard') }}"><i class="fa-solid fa-chart-pie me-1"></i> PPA dashboard</a></li>
                        <li><a class="dropdown-item" href="{{ route('performance.my-ppas') }}"><i class="fa-solid fa-folder-open me-1"></i> My PPAs</a></li>
                        <li><a class="dropdown-item" href="{{ route('performance.pending') }}"><i class="fa-solid fa-inbox me-1"></i> Pending action</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><span class="dropdown-item-text small text-muted">Workflow review (PPA / midterm / endterm)</span></li>
                    </ul>
                </li>
            @endif

            @if (portal_can(78))
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ nav_active('workplan', 'tasks', 'weektasks') }}" href="#" data-bs-toggle="dropdown" role="button" aria-expanded="false">
                        <div class="parent-icon"><i class="bx bx-task"></i></div>
                        <div class="menu-title">Weekly Task Planner</div>
                    </a>
                    <ul class="dropdown-menu">
                        @if (portal_can(79))
                            <li><a class="dropdown-item" href="{{ route('workplan.index') }}"><i class="bx bx-right-arrow-alt"></i>Workplan</a></li>
                        @endif
                        @if (portal_can(81))
                            <li><a class="dropdown-item" href="{{ route('tasks.activities') }}"><i class="bx bx-right-arrow-alt"></i>Sub activities</a></li>
                        @endif
                        @if (portal_can(75))
                            <li><a class="dropdown-item" href="{{ route('tasks.weekly') }}"><i class="bx bx-right-arrow-alt"></i>Weekly tasks</a></li>
                        @endif
                    </ul>
                </li>
            @endif

            @if (portal_can(77))
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ nav_active('admanager') }}" href="#" data-bs-toggle="dropdown" role="button" aria-expanded="false">
                        <div class="parent-icon"><i class="fa fa-shield-alt"></i></div>
                        <div class="menu-title">Domain Controller</div>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('admanager.expired') }}"><i class="bx bx-right-arrow-alt"></i>Accounts to disable</a></li>
                        <li><a class="dropdown-item" href="{{ route('admanager.report') }}"><i class="bx bx-right-arrow-alt"></i>Disabled accounts</a></li>
                    </ul>
                </li>
            @endif

            @if (portal_can(15))
                <li class="nav-item">
                    <a href="{{ route('settings.hub') }}" class="nav-link {{ nav_active('settings') }}">
                        <div class="parent-icon"><i class="fa fa-cog"></i></div>
                        <div class="menu-title">Settings</div>
                    </a>
                </li>
            @endif

            @if (portal_can(17))
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ nav_active('permissions', 'auth') }}" href="#" data-bs-toggle="dropdown" role="button" aria-expanded="false">
                        <div class="parent-icon"><i class="fa fa-users"></i></div>
                        <div class="menu-title">Users</div>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('auth.users') }}"><i class="bx bx-right-arrow-alt"></i>Manage users</a></li>
                        <li><a class="dropdown-item" href="{{ route('permissions.index') }}"><i class="bx bx-right-arrow-alt"></i>Access permissions</a></li>
                        <li><a class="dropdown-item" href="{{ route('auth.logs') }}"><i class="bx bx-right-arrow-alt"></i>Audit logs</a></li>
                    </ul>
                </li>
            @endif
        </ul>
    </nav>
</div>
