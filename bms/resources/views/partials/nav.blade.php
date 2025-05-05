<div class="nav-container primary-menu">
    <div class="mobile-topbar-header">
        <div>
            <img src="{{ asset('assets/images/au_emblem.png') }}" class="logo-icon" alt="logo icon">
        </div>
        <div>
            <h5 class="logo-text">Staff Portal</h5>
        </div>
        <div class="toggle-icon ms-auto"><i class='bx bx-arrow-to-left'></i>
        </div>
    </div>
    <nav class="navbar navbar-expand-xl w-100">
        <ul class="navbar-nav justify-content-start flex-grow-1 gap-1">

            <!-- Dashboard -->
            @if(in_array('76', session('permissions', [])))
                <li class="nav-item">
                    <a href="{{ session('base_url', '') . 'dashboard' }}"
                        class="nav-link {{ request()->is('dashboard') ? 'active' : '' }}">
                        <div class="parent-icon"><i class="bx bx-category"></i></div>
                        <div class="menu-title">Staff Dashboard</div>
                    </a>
                </li>
            @endif

            <!-- Staff Profile -->
            @if(in_array('72', session('permissions', [])) || in_array('41', session('permissions', [])))
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->is('staff*') ? 'active' : '' }}" href="#"
                        data-bs-toggle="dropdown">
                        <div class="parent-icon"><i class="fa fa-user"></i></div>
                        <div class="menu-title">Staff Profile</div>
                    </a>
                    <ul class="dropdown-menu">
                        @if(in_array('72', session('permissions', [])))
                            @if(in_array('71', session('permissions', [])))
                                <li><a class="dropdown-item" href="{{ session('base_url', '') . 'staff/search' }}"><i
                                            class="bx bx-right-arrow-alt"></i>Quick Search</a></li>
                                <li><a class="dropdown-item" href="{{ session('base_url', '') . 'staff/new' }}"><i
                                            class="bx bx-right-arrow-alt"></i>Add New Staff</a></li>
                                <li><a class="dropdown-item" href="{{ session('base_url', '') . 'staff/all_staff' }}"><i
                                            class="bx bx-right-arrow-alt"></i>All Staff</a></li>
                                <li><a class="dropdown-item" href="{{ session('base_url', '') . 'staff/index' }}"><i
                                            class="bx bx-right-arrow-alt"></i>Current Staff List</a></li>
                                <li><a class="dropdown-item" href="{{ session('base_url', '') . 'staff/contract_status/2' }}"><i
                                            class="bx bx-right-arrow-alt"></i>Contracts Due</a></li>
                                <li><a class="dropdown-item" href="{{ session('base_url', '') . 'staff/contract_status/3' }}"><i
                                            class="bx bx-right-arrow-alt"></i>Contracts Expired</a></li>
                                <li><a class="dropdown-item" href="{{ session('base_url', '') . 'staff/contract_status/4' }}"><i
                                            class="bx bx-right-arrow-alt"></i>Former Staff</a></li>
                                <li><a class="dropdown-item" href="{{ session('base_url', '') . 'staff/contract_status/7' }}"><i
                                            class="bx bx-right-arrow-alt"></i>Under Renewal</a></li>
                            @endif
                        @endif
                        @if(in_array('41', session('permissions', [])))
                            <li><a class="dropdown-item" href="{{ session('base_url', '') . 'staff/staff_birthday' }}"><i
                                        class="bx bx-right-arrow-alt"></i>Staff Birthdays</a></li>
                        @endif
                    </ul>
                </li>
            @endif

            <!-- Attendance -->
            @if(session('user.staff_id') != 0 && in_array('83', session('permissions', [])))
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->is('attendance*') ? 'active' : '' }}" href="#"
                        data-bs-toggle="dropdown">
                        <div class="parent-icon"><i class="fa fa-clock"></i></div>
                        <div class="menu-title">Attendance</div>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ session('base_url', '') . 'attendance/upload' }}"><i
                                    class="bx bx-right-arrow-alt"></i>Upload Data</a></li>
                        <li><a class="dropdown-item" href="{{ session('base_url', '') . 'attendance/person' }}"><i
                                    class="bx bx-right-arrow-alt"></i>Person Attendance</a></li>
                        <li><a class="dropdown-item" href="{{ session('base_url', '') . 'attendance/status' }}"><i
                                    class="bx bx-right-arrow-alt"></i>Time Logs</a></li>
                        <li><a class="dropdown-item" href="{{ session('base_url', '') . 'attendance/time_sheet' }}"><i
                                    class="bx bx-right-arrow-alt"></i>Time Sheet</a></li>
                    </ul>
                </li>
            @endif

            <!-- Leave -->
            @if(session('user.staff_id') != 0 && in_array('37', session('permissions', [])))
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->is('leave*') ? 'active' : '' }}" href="#"
                        data-bs-toggle="dropdown">
                        <div class="parent-icon"><i class="fa fa-plane-departure"></i></div>
                        <div class="menu-title">Leave Application</div>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ session('base_url', '') . 'leave/request' }}"><i
                                    class="bx bx-right-arrow-alt"></i>Apply</a></li>
                        <li><a class="dropdown-item" href="{{ session('base_url', '') . 'leave/approve_leave' }}"><i
                                    class="bx bx-right-arrow-alt"></i>Approve Leave</a></li>
                        <li><a class="dropdown-item" href="{{ session('base_url', '') . 'leave/status' }}"><i
                                    class="bx bx-right-arrow-alt"></i>My Leave Status</a></li>
                        @if(in_array('77', session('permissions', [])))
                            <li><a class="dropdown-item" href="{{ session('base_url', '') . 'leave/status/all' }}"><i
                                        class="bx bx-right-arrow-alt"></i>Leave Status</a></li>
                        @endif
                    </ul>
                </li>
            @endif

            <!-- Performance -->
            @if(session('user.staff_id') != 0 && in_array('74', session('permissions', [])))
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->is('performance*') ? 'active' : '' }}"
                        href="{{ session('base_url', '') . 'performance/ppa_dashboard' }}" data-bs-toggle="dropdown">
                        <div class="parent-icon"><i class="fa fa-line-chart"></i></div>
                        <div class="menu-title">Performance</div>
                    </a>
                    <ul class="dropdown-menu">
                        @if(in_array('38', session('permissions', [])) && !isset($ppa_exists))
                            <li><a class="dropdown-item" href="{{ session('base_url', '') . 'performance' }}"><i
                                        class="bx bx-right-arrow-alt"></i>Create PPA</a></li>
                        @endif

                        @if(in_array('82', session('permissions', [])))
                            <li><a class="dropdown-item" href="{{ session('base_url', '') . 'performance/ppa_dashboard' }}"><i
                                        class="bx bx-right-arrow-alt"></i>PPA Dashboard</a></li>
                            <li><a class="dropdown-item" href="{{ session('base_url', '') . 'performance/all_ppas' }}"><i
                                        class="bx bx-right-arrow-alt"></i>All PPAs Status</a></li>
                        @endif

                        @if(in_array('38', session('permissions', [])) && isset($ppa_exists))
                            <li><a class="dropdown-item"
                                    href="{{ session('base_url', '') . "performance/recent_ppa/{$ppa_entryid}/" . session('user.staff_id') }}"><i
                                        class="bx bx-right-arrow-alt"></i>My Current PPA</a></li>
                        @endif
                        <li><a class="dropdown-item" href="{{ session('base_url', '') . 'performance/my_ppas' }}"><i
                                    class="bx bx-right-arrow-alt"></i>My PPAs</a></li>
                        <li><a class="dropdown-item"
                                href="{{ session('base_url', '') . 'performance/pending_approval' }}"><i
                                    class="bx bx-right-arrow-alt"></i>Pending Action <span
                                    class="badge bg-danger ms-1">{{ isset($pending_ppa_count) ? $pending_ppa_count : 0 }}</span></a>
                        </li>
                        <li><a class="dropdown-item" href="{{ session('base_url', '') . 'performance/approved_by_me' }}"><i
                                    class="bx bx-right-arrow-alt"></i>All Approved PPAs</a></li>

                    </ul>
                </li>
            @endif

            <!-- Weekly Task Planner -->
            @if(in_array('78', session('permissions', [])))
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->is('weektasks*') ? 'active' : '' }}" href="#"
                        data-bs-toggle="dropdown">
                        <div class="parent-icon"><i class="fa fa-bar-chart"></i></div>
                        <div class="menu-title">Weekly Task Planner</div>
                    </a>
                    <ul class="dropdown-menu">
                        @if(in_array('79', session('permissions', [])))
                            <li><a class="dropdown-item" href="{{ session('base_url', '') . 'workplan' }}"><i
                                        class="bx bx-right-arrow-alt"></i>Workplan</a></li>
                        @endif
                        @if(in_array('81', session('permissions', [])))
                            <li><a class="dropdown-item" href="{{ session('base_url', '') . 'tasks/activity' }}"><i
                                        class="bx bx-right-arrow-alt"></i>Sub Activities</a></li>
                        @endif
                        @if(in_array('75', session('permissions', [])))
                            <li><a class="dropdown-item" href="{{ session('base_url', '') . 'weektasks/tasks' }}"><i
                                        class="bx bx-right-arrow-alt"></i>Weekly Tasks</a></li>
                        @endif
                    </ul>
                </li>
            @endif

            <!-- Domain Controller -->
            @if(in_array('77', session('permissions', [])))
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <div class="parent-icon"><i class="fa fa-shield-alt"></i></div>
                        <div class="menu-title">Domain Controller</div>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ session('base_url', '') . 'admanager/expired_accounts' }}"><i
                                    class="bx bx-right-arrow-alt"></i>Accounts to Disable</a></li>
                        <li><a class="dropdown-item" href="{{ session('base_url', '') . 'admanager/report' }}"><i
                                    class="bx bx-right-arrow-alt"></i>Disabled Accounts</a></li>
                    </ul>
                </li>
            @endif

            <!-- Settings -->
            @if(in_array('15', session('permissions', [])))
                <li class="nav-item">
                    <a href="{{ session('base_url', '') . 'settings' }}"
                        class="nav-link {{ request()->is('settings') ? 'active' : '' }}">
                        <div class="parent-icon"><i class="fa fa-cog"></i></div>
                        <div class="menu-title">Settings</div>
                    </a>
                </li>
            @endif

            <!-- Users -->
            @if(in_array('17', session('permissions', [])))
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->is('auth*') || request()->is('permissions*') ? 'active' : '' }}"
                        href="#" data-bs-toggle="dropdown">
                        <div class="parent-icon"><i class="fa fa-users"></i></div>
                        <div class="menu-title">Users</div>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ session('base_url', '') . 'auth/users' }}"><i
                                    class="bx bx-right-arrow-alt"></i>Manage Users</a></li>
                        <li><a class="dropdown-item" href="{{ session('base_url', '') . 'permissions' }}"><i
                                    class="bx bx-right-arrow-alt"></i>Group Permissions</a></li>
                        <li><a class="dropdown-item" href="{{ session('base_url', '') . 'permissions/user' }}"><i
                                    class="bx bx-right-arrow-alt"></i>User Permissions</a></li>
                        <li><a class="dropdown-item" href="{{ session('base_url', '') . 'auth/logs' }}"><i
                                    class="bx bx-right-arrow-alt"></i>Audit Logs</a></li>
                    </ul>
                </li>
            @endif

            <!-- Return to Main System -->
            <li class="nav-item">
                <a href="{{ session('base_url', '') }}" class="nav-link">
                    <div class="parent-icon"><i class="bx bx-arrow-back"></i></div>
                    <div class="menu-title">Back to Main System</div>
                </a>
            </li>

        </ul>
    </nav>
</div>