<!--start page wrapper -->
<div class="page-wrapper">
    <div class="page-content">
        <!--breadcrumb-->
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item">
                            <a href="{{ url('/home') }}">
                                <i class="bx bx-home-alt"></i> Home
                            </a>
                        </li>
                        
                        @php
                            $segments = request()->segments();
                            $url = '';
                        @endphp
                        
                        @foreach($segments as $key => $segment)
                            @php
                                $url = $key === 0 ? "/$segment" : "$url/$segment";
                                $displayName = ucwords(str_replace(['-', '_'], ' ', $segment));
                                
                                // Special handling for resource names
                                $resourceNames = ['fund-types', 'fund-codes', 'directorates', 'staff', 'request-types', 'matrices', 'activities', 'non-travel', 'request-arf', 'special-memo', 'service-requests'];
                                
                                if (in_array($segment, $resourceNames) || in_array($segment, array_map(function($item) {
                                    return str_replace('-', ' ', $item);
                                }, $resourceNames))) {
                                    $displayName = ucwords(str_replace('-', ' ', $segment));
                                    
                                    // Handle plural to singular for better display
                                    if (Str::endsWith($displayName, 'ies')) {
                                        $displayName = substr($displayName, 0, -3) . 'y';
                                    } elseif (Str::endsWith($displayName, 's')) {
                                        $displayName = substr($displayName, 0, -1);
                                    }
                                }
                                
                                // Handle create/edit actions
                                if (in_array($segment, ['create', 'edit'])) {
                                    $displayName = ucfirst($segment);
                                    $url = ''; // Don't link action pages
                                }
                                
                                // Handle numeric segments (usually IDs)
                                if (is_numeric($segment)) {
                                    $previousSegment = $segments[$key - 1] ?? null;
                                    
                                    if ($previousSegment === 'matrices' && isset($matrix)) {
                                        // Format: YYYY Q1 - Division Name
                                        $divisionName = $matrix->division ? $matrix->division->name : 'Unknown Division';
                                        $displayName = $matrix->year . ' ' . $matrix->quarter . ' - ' . $divisionName;
                                    } elseif ($previousSegment === 'activities' && isset($activity)) {
                                        $displayName = $activity->activity_title ?? 'Activity #' . $segment;
                                    } elseif ($previousSegment === 'staff' && isset($staff)) {
                                        $displayName = $staff->full_name ?? 'Staff #' . $segment;
                                    } else {
                                        $displayName = 'Details';
                                    }
                                }
                            @endphp
                            
                            @if($loop->last || empty($url))
                                <li class="breadcrumb-item active" aria-current="page">
                                    {{ $displayName }}
                                </li>
                            @else
                                <li class="breadcrumb-item">
                                    <a href="{{ url($url) }}">
                                        {{ $displayName }}
                                    </a>
                                </li>
                            @endif
                        @endforeach
                        
                        @if(!count($segments) && isset($title))
                            <li class="breadcrumb-item active" aria-current="page">
                                {{ $title }}
                            </li>
                        @endif
                    </ol>
                </nav>
            </div>
            <div class="ms-auto">
                @yield('header-actions')
            </div>
        </div>
        <!--end breadcrumb-->
        <div id="preloader">
            <div id="status"></div>
        </div>
        <div class="card">
            <div class="card-body">