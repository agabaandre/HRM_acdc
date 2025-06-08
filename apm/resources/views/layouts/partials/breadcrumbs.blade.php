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
                        $segmentLower = strtolower($segment);
                        $previousSegment = $segments[$key - 1] ?? null;

                        // Handle special case: matrice or matrices
                        if (in_array($segmentLower, ['matrice', 'matrices'])) {
                        $displayName = 'Quarterly Matrix';

                        // Attach division if available
                        if (isset($matrix) && $matrix->division) {
                        $displayName .= ' - ' . $matrix->division->name;
                        } elseif (session('division_name')) {
                        $displayName .= ' - ' . session('division_name');
                        }
                        }

                        // Handle numeric segments
                        elseif (is_numeric($segment)) {
                        if ($previousSegment === 'matrices' && isset($matrix)) {
                        $displayName = $matrix->year . ' ' . $matrix->quarter . ' - ' . ($matrix->division->name ?? 'Unknown Division');
                        } elseif ($previousSegment === 'activities' && isset($activity)) {
                        $displayName = $activity->activity_title ?? 'Activity #' . $segment;
                        } elseif ($previousSegment === 'staff' && isset($staff)) {
                        $displayName = $staff->full_name ?? 'Staff #' . $segment;
                        } else {
                        $displayName = 'Details';
                        }
                        }

                        // Handle create/edit actions
                        elseif (in_array($segmentLower, ['create', 'edit'])) {
                        $displayName = ucfirst($segment);

                        // Check if previous was matrices
                        if (in_array(strtolower($previousSegment), ['matrice', 'matrices'])) {
                        if (isset($matrix) && $matrix->division) {
                        $displayName = 'Quarterly Matrix - ' . $matrix->division->name;
                        } elseif (session('division_name')) {
                        $displayName = 'Quarterly Matrix - ' . session('division_name');
                        }
                        }

                        $url = ''; // avoid link on action
                        }

                        // Handle resource names
                        else {
                        $resourceNames = ['fund-types', 'fund-codes', 'directorates', 'staff', 'request-types', 'activities', 'non-travel', 'request-arf', 'special-memo', 'service-requests'];
                        if (in_array($segmentLower, $resourceNames)) {
                        $displayName = ucwords(str_replace('-', ' ', $segmentLower));

                        // Singularize
                        if (Str::endsWith($displayName, 'ies')) {
                        $displayName = substr($displayName, 0, -3) . 'y';
                        } elseif (Str::endsWith($displayName, 's')) {
                        $displayName = substr($displayName, 0, -1);
                        }
                        }
                        }
                        @endphp

                        @if($loop->last || empty($url))
                        <li class="breadcrumb-item active" aria-current="page" title="{{$displayName}}">
                            {{  Str::limit($displayName,30) }}
                        </li>
                        @else
                        <li class="breadcrumb-item">
                            <a href="{{ url($url) }}" title="{{$displayName}}">
                                {{  Str::limit($displayName,30) }}
                            </a>
                        </li>
                        @endif
                        @endforeach
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