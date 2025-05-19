@php
$workflowMenuItems = [
    [
        'route' => 'workflows.index',
        'icon' => 'bx bx-git-branch',
        'title' => 'Workflows'
    ],
    [
        'route' => 'approvals.index',
        'icon' => 'bx bx-check-circle',
        'title' => 'Approvals'
    ]
];

$settingsMenuItems = [
    [
        'route' => 'fund-types.index',
        'icon' => 'bx bx-wallet',
        'title' => 'Fund Types'
    ],
    [
        'route' => 'fund-codes.index',
        'icon' => 'bx bx-barcode',
        'title' => 'Fund Codes'
    ],
    [
        'route' => 'divisions.index',
        'icon' => 'bx bx-building-house',
        'title' => 'Divisions'
    ],
    [
        'route' => 'directorates.index',
        'icon' => 'bx bx-sitemap',
        'title' => 'Directorates'
    ],
    [
        'route' => 'staff.index',
        'icon' => 'bx bx-user',
        'title' => 'Staff'
    ],
    [
        'route' => 'request-types.index',
        'icon' => 'bx bx-spreadsheet',
        'title' => 'Request Types'
    ]
];
@endphp

<div class="nav-container primary-menu">
    <nav class="navbar navbar-expand-xl w-100">
        <ul class="navbar-nav justify-content-start flex-grow-1 gap-1">
            <!-- Home -->
            <li class="nav-item">
                <a href="{{ str_replace('bms/', '', url('home/index')) }}"
                   class="nav-link {{ Request::is('home/index') ? 'active' : '' }}">
                    <div class="parent-icon"><i class="bx bx-home-circle"></i></div>
                    <div class="menu-title">Home</div>
                </a>
            </li>

            <!-- Workflow Management Dropdown -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle {{ Request::is('memos*') || Request::is('workflows*') || Request::is('approvals*') ? 'active' : '' }}"
                   href="#" data-bs-toggle="dropdown">
                    <div class="parent-icon"><i class="bx bx-list-check"></i></div>
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

            <!-- Settings Dropdown -->
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle {{ Request::is('fund-types*') || Request::is('fund-codes*') || Request::is('divisions*') || Request::is('directorates*') || Request::is('staff*') || Request::is('request-types*') ? 'active' : '' }}"
                   href="#" data-bs-toggle="dropdown">
                    <div class="parent-icon"><i class="bx bx-cog"></i></div>
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
