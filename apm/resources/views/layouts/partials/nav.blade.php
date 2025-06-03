@php
$workflowMenuItems = [
    [
        'route' => 'workflows.index',
        'icon' => 'fas fa-project-diagram',
        'title' => 'Workflows'
    ],
    [
        'route' => 'approvals.index',
        'icon' => 'fas fa-check-circle',
        'title' => 'Approvals'
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
        'route' => 'staff.index',
        'icon' => 'fas fa-users',
        'title' => 'Staff'
    ],
    [
        'route' => 'request-types.index',
        'icon' => 'fas fa-file-alt',
        'title' => 'Request Types'
    ]
];
@endphp

<div class="nav-container primary-menu">
    <nav class="navbar navbar-expand-xl w-100">
        <ul class="navbar-nav justify-content-start flex-grow-1 gap-1">
            <!-- Start Page -->
            <li class="nav-item">
                <a href="{{ str_replace('apm/', '', url('home/index')) }}"
                    class="nav-link {{ Request::is('home/index') ? 'active' : '' }}">
                    <div class="parent-icon"><i class="fas fa-home"></i></div>
                    <div class="menu-title">Start Page</div>
                </a>
            </li>

            <!-- APMS Home -->
            <li class="nav-item">
                <a href="{{ url('home') }}"
                    class="nav-link {{ Request::is('home') ? 'active' : '' }}">
                    <div class="parent-icon"><i class="fas fa-th"></i></div>
                    <div class="menu-title">APM Home</div>
                </a>
            </li>

            <!-- Workflow Management -->
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

            <!-- Settings -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle {{ Request::is('fund-types*') || Request::is('fund-codes*') || Request::is('divisions*') || Request::is('directorates*') || Request::is('staff*') || Request::is('request-types*') ? 'active' : '' }}"
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
        </ul>
    </nav>
</div>
