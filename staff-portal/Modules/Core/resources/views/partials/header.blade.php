@php
    $user = session('user', []);
    $userName = $user['name'] ?? auth()->user()?->name ?? 'User';
    $cbpNav = \Modules\Core\Support\PortalNavigation::cbpModulesPayload();
@endphp
<header>
    <div class="topbar d-flex">
        <nav class="navbar navbar-expand">
            <div class="topbar-logo-header">
                <a href="{{ route('core.home') }}">
                    <img src="{{ \App\Support\CbpAsset::url('images/AU_CDC_Logo-800.png') }}" width="200" alt="Africa CDC" style="filter: brightness(0) invert(1);">
                </a>
            </div>
            <div class="mobile-toggle-menu d-xl-none"><i class="bx bx-menu"></i></div>
            <div class="search-bar flex-grow-1 d-none">
                <div class="position-relative search-bar-box">
                    <input type="text" class="form-control search-control" placeholder="Type to search…">
                </div>
            </div>
            <div class="top-menu ms-auto">
                <ul class="navbar-nav align-items-center">
                    @include('core::partials.cbp-modules-dropdown', $cbpNav)

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle dropdown-toggle-nocaret position-relative" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="alert-count d-none">0</span>
                            <i class="bx bx-comment" style="color:#FFF;"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3" style="min-width: 280px;">
                            <div class="dropdown-header px-3 pt-2 fw-semibold">Messages</div>
                            <div class="px-3 py-3 text-muted small">No new messages.</div>
                        </div>
                    </li>
                </ul>
            </div>
            <div class="user-box dropdown">
                <a class="d-flex align-items-center nav-link dropdown-toggle dropdown-toggle-nocaret" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="user-img bg-light rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                        <i class="bx bx-user text-success fs-4"></i>
                    </div>
                    <div class="user-info ps-3 d-none d-sm-block">
                        <p class="user-name mb-0 text-white">{{ $userName }}</p>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <form method="POST" action="{{ route('auth.logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item"><i class="bx bx-log-out-circle"></i><span>Logout</span></button>
                        </form>
                    </li>
                </ul>
            </div>
        </nav>
    </div>
</header>
