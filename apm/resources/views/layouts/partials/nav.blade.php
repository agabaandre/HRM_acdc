@php
$workflowMenuItems = [
[
'route' => 'workflows.index',
'icon' => 'fas fa-project-diagram',
'title' => 'Workflows'
],
[
'route' => 'workflows.assign-models',
'icon' => 'fas fa-link',
'title' => 'Assign Models to Workflows'
]
];

$settingsMenuItems = [
[
'route' => 'fund-types.index',
'icon' => 'fas fa-hand-holding-usd',
'title' => 'Fund Types'
],
[
'route' => 'fund-codes.index',
'icon' => 'fas fa-barcode',
'title' => 'Fund Codes'
],
[
'route' => 'funders.index',
'icon' => 'fas fa-handshake',
'title' => 'Funders'
],
[
'route' => 'divisions.index',
'icon' => 'fas fa-building',
'title' => 'Divisions'
],
[
'route' => 'directorates.index',
'icon' => 'fas fa-network-wired',
'title' => 'Directorates'
],
[
'route' => 'request-types.index',
'icon' => 'fas fa-file-alt',
'title' => 'Request Types'
],
[
'route' => 'non-travel-categories.index',
'icon' => 'fas fa-list',
'title' => 'Non Travel Categories'
],
[
'route' => 'locations.index',
'icon' => 'fas fa-map-marker-alt',
'title' => 'Locations'
],
[
'route' => 'cost-items.index',
'icon' => 'fas fa-coins',
'title' => 'Cost Items'
],
[
'route' => 'jobs.index',
'icon' => 'fas fa-tasks',
'title' => 'Jobs'
],
[
'route' => 'audit-logs.index',
'icon' => 'fas fa-clipboard-list',
'title' => 'Audit Logs'
],
];
@endphp

<div class="nav-container primary-menu">
    <nav class="navbar navbar-expand-xl w-100">
        <ul class="navbar-nav justify-content-start">
            <!-- Approver Dashboard -->
            <li class="nav-item">
                <a href="{{ route('approver-dashboard.index') }}"
                    class="nav-link {{ Request::is('approver-dashboard*') ? 'active' : '' }}">
                    <div class="parent-icon"><i class="fas fa-tachometer-alt"></i></div>
                    <div class="menu-title">Approver Dashboard</div>
                </a>
            </li>

            <!-- Pending Approvals -->
            <li class="nav-item">
                <a href="{{ route('pending-approvals.index') }}"
                    class="nav-link {{ Request::is('pending-approvals*') ? 'active' : '' }}">
                    <div class="parent-icon"><i class="fas fa-clock"></i></div>
                    <div class="menu-title">Pending Approvals</div>
                </a>
            </li>

            <!-- Staff Portal Menu -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle {{ Request::is('staff*') || Request::is('home/index') ? 'active' : '' }}"
                    href="#" data-bs-toggle="dropdown">
                    <div class="parent-icon"><i class="fas fa-users"></i></div>
                    <div class="menu-title">Staff Portal</div>
                </a>
                <ul class="dropdown-menu">
                    <li>
                        <a class="dropdown-item {{ Request::is('home/index') ? 'active' : '' }}" 
                           href="{{ str_replace('apm/', '', url('dashboard')) }}" target="_blank">
                            <i class="fas fa-external-link-alt me-2"></i>External Dashboard
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item {{ Request::is('staff*') ? 'active' : '' }}" 
                           href="{{ route('staff.index') }}">
                            <i class="fas fa-user-cog me-2"></i>Staff Management
                        </a>
                    </li>
                </ul>
            </li>

            <!-- APMS Home -->
            <li class="nav-item">
                <a href="{{ url('home') }}"
                    class="nav-link {{ Request::is('home') ? 'active' : '' }}">
                    <div class="parent-icon"><i class="fas fa-sitemap"></i></div>
                    <div class="menu-title">APM Home</div>
                </a>
            </li>

            <!-- Quarterly Matrix -->
            <li class="nav-item">
                <a href="{{ route('matrices.index') }}" class="nav-link {{ Request::is('matrices*') ? 'active' : '' }}">
                    <div class="parent-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="menu-title">Quarterly Matrix</div>
                </a>
            </li>

         

         

            <!-- Memos Menu -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle {{ Request::is('activities*') || Request::is('non-travel*') || Request::is('special-memo*') || Request::is('single-memos*') ? 'active' : '' }}"
                    href="#" data-bs-toggle="dropdown">
                    <div class="parent-icon"><i class="fas fa-envelope-open-text"></i></div>
                    <div class="menu-title">Memos</div>
                </a>
                <ul class="dropdown-menu">
                    <li>
                        <a class="dropdown-item {{ Request::is('activities*') ? 'active' : '' }}" href="{{ route('activities.index') }}">
                            Matrix Memos
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item {{ Request::is('single-memos*') ? 'active' : '' }}" href="{{ route('activities.single-memos.index') }}">
                            Matrix Single Memos
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item {{ Request::is('non-travel*') ? 'active' : '' }}" href="{{ url('non-travel') }}">
                            Non-Travel Memos
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item {{ Request::is('special-memo*') ? 'active' : '' }}" href="{{ url('special-memo') }}">
                            Special Travel Memos
                        </a>
                    </li>
                </ul>
            </li>

               <!-- Requests Menu -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle {{ Request::is('service-requests*') || Request::is('request-arf*') ? 'active' : '' }}"
                    href="#" data-bs-toggle="dropdown">
                    <div class="parent-icon"><i class="fas fa-boxes"></i></div>
                    <div class="menu-title">Request for Services</div>
                </a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item {{ Request::is('service-requests*') ? 'active' : '' }}" href="{{ url('service-requests') }}">Request DSA,  Imprest and Ticket</a></li>
                    <li><a class="dropdown-item {{ Request::is('request-arf*') ? 'active' : '' }}" href="{{ url('request-arf') }}">Request for ARF</a></li>
                   
                </ul>
            </li>

            <!-- Workflow Management -->
            @if(in_array(89, user_session('permissions', [])))
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle {{ Request::is('workflows*') || Request::is('approvals*') ? 'active' : '' }}"
                    href="#" data-bs-toggle="dropdown">
                    <div class="parent-icon"><i class="fas fa-tasks"></i></div>
                    <div class="menu-title">Workflow Management</div>
                </a>
                <ul class="dropdown-menu">
                    @foreach($workflowMenuItems as $item)
                    <li>
                        <a class="dropdown-item" href="{{ route($item['route']) }}">
                            <i class="{{ $item['icon'] }}"></i> {{ $item['title'] }}
                        </a>
                    </li>
                    @endforeach
                </ul>
            </li>
            @endif


            <!-- Settings -->
            @if(in_array(89, user_session('permissions', [])))
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle {{ Request::is('fund-types*') || Request::is('fund-codes*') || Request::is('funders*') || Request::is('divisions*') || Request::is('directorates*') || Request::is('request-types*') || Request::is('jobs*') ? 'active' : '' }}"
                    href="#" data-bs-toggle="dropdown">
                    <div class="parent-icon"><i class="fas fa-cogs"></i></div>
                    <div class="menu-title">Settings</div>
                </a>
                <ul class="dropdown-menu">
                    @foreach($settingsMenuItems as $item)
                    <li>
                        <a class="dropdown-item" href="{{ route($item['route']) }}">
                            <i class="{{ $item['icon'] }}"></i> {{ $item['title'] }}
                        </a>
                    </li>
                    @endforeach
                </ul>
            </li>
            @endif
        </ul>
    </nav>
</div>