<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Business Management System'))</title>

    @include('partials.css')
    <!-- @stack('styles') -->
</head>

<body>
    @include('partials.header')

    <!-- Include the breadcrumbs partial -->
    @include('partials.breadcrumbs')


            <!--Add our workflow management menu items to the navigation-->
            @php
            // Add these menu items to the navigation
            $workflowMenuItems = [
            [
            'route' => 'memos.index',
            'icon' => 'bx bx-file',
            'title' => 'Memos'
            ],
            [
            'route' => 'workflows.index',
            'icon' => 'bx bx-git-branch',
            'title' => 'Workflows'
            ],
            [
            'route' => 'approvals.index',
            'icon' => 'bx bx-check-circle',
            'title' => 'Approvals'
            ],
            [
            'route' => 'divisions.index',
            'icon' => 'bx bx-building',
            'title' => 'Divisions'
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
                            <a class="nav-link dropdown-toggle {{ Request::is('memos*') || Request::is('workflows*') || Request::is('approvals*') || Request::is('divisions*') ? 'active' : '' }}"
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
                    </ul>
                </nav>
            </div>

            <!-- Content Area -->
            @yield('content')
        </div>
    </div>
    <!--end page wrapper -->

    @include('partials.footer')
    @stack('scripts')
</body>

</html>