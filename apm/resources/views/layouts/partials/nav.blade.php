@php
    $workflowMenuItems = [
        [
            'route' => 'workflows.index',
            'icon' => 'fas fa-project-diagram',
            'title' => 'Workflows',
        ],
        [
            'route' => 'workflows.assign-models',
            'icon' => 'fas fa-link',
            'title' => 'Assign Models to Workflows',
        ],
    ];

    $settingsMenuItems = [
        [
            'route' => 'memo-type-definitions.index',
            'icon' => 'fas fa-file-signature',
            'title' => 'Other memo types',
        ],
        [
            'route' => 'fund-types.index',
            'icon' => 'fas fa-hand-holding-usd',
            'title' => 'Fund Types',
        ],
        [
            'route' => 'partners.index',
            'icon' => 'fas fa-users',
            'title' => 'Partners',
        ],
        [
            'route' => 'fund-codes.index',
            'icon' => 'fas fa-barcode',
            'title' => 'Fund Codes',
        ],
        [
            'route' => 'funders.index',
            'icon' => 'fas fa-handshake',
            'title' => 'Funders',
        ],
        [
            'route' => 'divisions.index',
            'icon' => 'fas fa-building',
            'title' => 'Divisions',
        ],
        [
            'route' => 'directorates.index',
            'icon' => 'fas fa-network-wired',
            'title' => 'Directorates',
        ],
        [
            'route' => 'request-types.index',
            'icon' => 'fas fa-file-alt',
            'title' => 'Request Types',
        ],
        [
            'route' => 'non-travel-categories.index',
            'icon' => 'fas fa-list',
            'title' => 'Non Travel Categories',
        ],
        [
            'route' => 'locations.index',
            'icon' => 'fas fa-map-marker-alt',
            'title' => 'Locations',
        ],
        [
            'route' => 'cost-items.index',
            'icon' => 'fas fa-coins',
            'title' => 'Cost Items',
        ],
        [
            'route' => 'apm-api-users.index',
            'icon' => 'fas fa-key',
            'title' => 'API users',
        ],
        [
            'route' => 'jobs.index',
            'icon' => 'fas fa-tasks',
            'title' => 'Jobs',
        ],
        [
            'url' => url('systemd-monitor'),
            'icon' => 'fas fa-server',
            'title' => 'Systemd Monitor',
        ],
        [
            'route' => 'system-settings.index',
            'icon' => 'fas fa-sliders-h',
            'title' => 'App Settings',
        ],
        [
            'route' => 'audit-logs.index',
            'icon' => 'fas fa-clipboard-list',
            'title' => 'Audit Logs',
        ],
        [
            'route' => 'backups.index',
            'icon' => 'fas fa-database',
            'title' => 'Database Backups',
        ],
        [
            'route' => 'faqs.index',
            'icon' => 'fas fa-question-circle',
            'title' => 'FAQs',
        ],
        [
            'route' => 'faq-categories.index',
            'icon' => 'fas fa-folder',
            'title' => 'FAQ Categories',
        ],
    ];
@endphp

<div class="nav-container primary-menu">
    <nav class="navbar navbar-expand-xl w-100">
        <ul class="navbar-nav justify-content-start">

            <!-- Approver Dashboard -->
            <li class="nav-item">
                <a class="nav-link {{ Request::is('approver-dashboard*') ? 'active' : '' }}"
                    href="{{ route('approver-dashboard.index') }}" wire:navigate>
                    <div class="parent-icon"><i class="fas fa-tachometer-alt"></i></div>
                    <div class="menu-title">Dashboard</div>
                </a>
            </li>

            <!-- APMS Home -->
            <li class="nav-item">
                <a class="nav-link {{ Request::is('home') ? 'active' : '' }}"
                    href="{{ route('home') }}" wire:navigate>
                    <div class="parent-icon"><i class="fas fa-sitemap"></i></div>
                    <div class="menu-title">APM Home</div>
                </a>
            </li>

            @if (!empty($cbpPlatformNavItems))
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="parent-icon"><i class="fas fa-th-large"></i></div>
                        <div class="menu-title">CBP</div>
                    </a>
                    <ul class="dropdown-menu">
                        @foreach ($cbpPlatformNavItems as $item)
                            <li>
                                <a class="dropdown-item" href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer"
                                    title="{{ $item['description'] }}">
                                    <i class="{{ $item['icon'] }} me-1"></i>{{ $item['title'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </li>
            @endif

            <!-- Pending Approvals -->
            <li class="nav-item">
                <a href="{{ route('returned-memos.index') }}" wire:navigate
                    class="nav-link {{ Request::is('returned-memos*') ? 'active' : '' }}">
                    <div class="parent-icon"><i class="fas fa-clock"></i></div>
                    <div class="menu-title">Returns</div>
                    @php
                        $pendingCount = 0;
                        if (user_session('staff_id')) {
                            $pendingCount =
                                get_my_returned_matrices_count(user_session('staff_id')) +
                                get_my_returned_special_memo_count(user_session('staff_id')) +
                                get_my_returned_non_travel_memo_count(user_session('staff_id')) +
                                get_my_returned_other_memo_count(user_session('staff_id')) +
                                get_my_returned_single_memo_count(user_session('staff_id')) +
                                get_my_returned_service_requests_count(user_session('staff_id')) +
                                get_my_returned_request_arf_count(user_session('staff_id')) +
                                get_my_returned_change_request_count(user_session('staff_id'));
                        }
                    @endphp
                    @if ($pendingCount > 0)
                        <span class="badge bg-danger ms-2">{{ $pendingCount }}</span>
                    @endif
                </a>
            </li>

            <!-- Pending Approvals -->
            <li class="nav-item">
                <a href="{{ route('pending-approvals.index') }}" wire:navigate
                    class="nav-link {{ Request::is('pending-approvals*') ? 'active' : '' }}">
                    <div class="parent-icon"><i class="fas fa-clock"></i></div>
                    <div class="menu-title">Approvals</div>
                    @php
                        $pendingCount = 0;
                        if (user_session('staff_id')) {
                            $pendingCount =
                                get_pending_matrices_count(user_session('staff_id')) +
                                get_pending_special_memo_count(user_session('staff_id')) +
                                get_pending_non_travel_memo_count(user_session('staff_id')) +
                                get_pending_single_memo_count(user_session('staff_id')) +
                                get_pending_service_requests_count(user_session('staff_id')) +
                                get_pending_request_arf_count(user_session('staff_id')) +
                                get_pending_change_request_count(user_session('staff_id')) +
                                get_pending_other_memo_count(user_session('staff_id'));
                        }
                    @endphp
                    @if ($pendingCount > 0)
                        <span class="badge bg-danger ms-2">{{ $pendingCount }}</span>
                    @endif
                </a>
            </li>






            <!-- Memos Menu -->
            @php
                // group all relevant sections for "active" state detection
                $isMemosActive =
                    Request::is('matrices*') ||
                    Request::is('activities*') ||
                    Request::is('single-memos*') ||
                    Request::is('non-travel*') ||
                    Request::is('special-memo*') ||
                    Request::is('service-requests*') ||
                    Request::is('request-arf*') ||
                    Request::is('other-memos*') ||
                    Request::is('change-requests*');
            @endphp
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle {{ $isMemosActive ? 'active' : '' }}"
                    href="#" data-bs-toggle="dropdown">
                    <div class="parent-icon"><i class="fas fa-envelope-open-text"></i></div>
                    <div class="menu-title">Memos</div>
                </a>
                <ul class="dropdown-menu">
                    <li>
                        <a class="dropdown-item {{ Request::is('matrices*') ? 'active' : '' }}"
                            href="{{ route('matrices.index') }}" wire:navigate>
                            Quarterly Matrix
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item {{ Request::is('activities*') ? 'active' : '' }}"
                            href="{{ route('activities.index') }}" wire:navigate>
                            Matrix Memos
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item {{ Request::is('single-memos*') ? 'active' : '' }}"
                            href="{{ route('activities.single-memos.index') }}" wire:navigate>
                            Matrix Single Memos
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item {{ Request::is('non-travel*') ? 'active' : '' }}"
                            href="{{ url('non-travel') }}" wire:navigate>
                            Non-Travel Memos
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item {{ Request::is('special-memo*') ? 'active' : '' }}"
                            href="{{ url('special-memo') }}" wire:navigate>
                            Special Travel Memos
                        </a>
                    </li>
                     <li>
                        <a class="dropdown-item {{ Request::is('change-requests*') ? 'active' : '' }}"
                            href="{{ url('change-requests') }}" wire:navigate>
                            Change Requests / Addendums
                        </a>
                    </li>
                    <li>        
                        <a class="dropdown-item {{ Request::is('service-requests*') ? 'active' : '' }}"
                            href="{{ url('service-requests') }}" wire:navigate>Request DSA, Imprest and Ticket</a>
                    </li>
                    <li>
                        <a class="dropdown-item {{ Request::is('request-arf*') ? 'active' : '' }}"
                            href="{{ url('request-arf') }}" wire:navigate>Request for ARF</a>
                    </li>
                    <li>
                        <a class="dropdown-item {{ Request::is('other-memos*') ? 'active' : '' }}"
                            href="{{ route('other-memos.index') }}" wire:navigate>Other memo types</a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item {{ Request::is('signature-verify*') ? 'active' : '' }}"
                            href="{{ route('signature-verify.index') }}" wire:navigate>
                            <i class="fas fa-fingerprint me-1"></i> Validate APM Document Signature Hashes
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Staff List -->
            <li class="nav-item">
                <a class="nav-link {{ Request::is('staff*') ? 'active' : '' }}"
                    href="{{ route('staff.index') }}" wire:navigate>
                    <div class="parent-icon"><i class="fas fa-user-cog"></i></div>
                    <div class="menu-title">Staff List</div>
                </a>
            </li>

            <!-- Reports -->
            <li class="nav-item">
                <a class="nav-link {{ Request::is('reports*') ? 'active' : '' }}"
                    href="{{ route('reports.index') }}" wire:navigate>
                    <div class="parent-icon"><i class="fas fa-chart-bar"></i></div>
                    <div class="menu-title">Reports</div>
                </a>
            </li>

            <!-- Workflow Management -->
            @if (in_array(89, user_session('permissions', [])))
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ Request::is('workflows*') || Request::is('approvals*') ? 'active' : '' }}"
                        href="#" data-bs-toggle="dropdown">
                        <div class="parent-icon"><i class="fas fa-tasks"></i></div>
                        <div class="menu-title">Workflows</div>
                    </a>
                    <ul class="dropdown-menu">
                        @foreach ($workflowMenuItems as $item)
                            <li>
                                {{-- Normal link (no wire:navigate) to avoid prefetch sandbox cookie error (Livewire store) --}}
                                <a class="dropdown-item" href="{{ route($item['route']) }}">
                                    <i class="{{ $item['icon'] }}"></i> {{ $item['title'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </li>
            @endif


            <!-- Settings -->
            @if (in_array(89, user_session('permissions', [])))
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ Request::is('memo-type-definitions*') || Request::is('fund-types*') || Request::is('partners*') || Request::is('fund-codes*') || Request::is('funders*') || Request::is('divisions*') || Request::is('directorates*') || Request::is('request-types*') || Request::is('non-travel-categories*') || Request::is('locations*') || Request::is('cost-items*') || Request::is('apm-api-users*') || Request::is('audit-logs*') || Request::is('jobs*') || Request::is('system-settings*') || Request::is('backups*') || Request::is('faqs*') || Request::is('faq-categories*') ? 'active' : '' }}"
                        href="#" data-bs-toggle="dropdown">
                        <div class="parent-icon"><i class="fas fa-cogs"></i></div>
                        <div class="menu-title">Settings</div>
                    </a>
                    <ul class="dropdown-menu">
                        @foreach ($settingsMenuItems as $item)
                            <li>
                                <a class="dropdown-item"
                                    href="{{ isset($item['url']) ? $item['url'] : route($item['route']) }}" wire:navigate>
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
