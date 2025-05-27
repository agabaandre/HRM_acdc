<!--start page wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!--breadcrumb-->
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="breadcrumb-title pe-3">
                <a href="{{ session('baseUrl', '') }}{{ request()->segment(1) }}" style="color:#947645;">
                {{ isset($module) ? ucwords($module) : 'Home' }}


                </a>
            </div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item"><a href="javascript:;"></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            {{ ucwords(str_replace('-', ' ', $title ?? '')) }}
                        </li>
                    </ol>
                </nav>
            </div>
            <div class="ms-auto">
                @yield('header-actions')
            </div>
        </div>
        <!--end breadcrumb-->
        <div id="preloader">
            <div id="status">
            </div>
        </div>
        <div class="card">
            <div class="card-body">