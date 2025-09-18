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
                        
                        // Helper function to get the correct division name for display
                        // This ensures intelligent division display based on context
                        $getDisplayDivisionName = function($matrix = null, $context = 'default') {
                            $userDivisionId = user_session('division_id');
                            $userDivisionName = user_session('division_name');
                            
                            // Safety check - ensure we have user session data
                            if (!$userDivisionId || !$userDivisionName) {
                                return null;
                            }
                            
                            if ($matrix && $matrix->division) {
                                // For matrix details/preview/approval, always show matrix division name
                                if ($context === 'matrix_details' || $context === 'matrix_approval') {
                                    return $matrix->division->division_name;
                                }
                                
                                // For other contexts, show matrix division only if it belongs to user's division
                                if ($matrix->division_id == $userDivisionId) {
                                    return $matrix->division->division_name;
                                }
                            }
                            
                            // Otherwise, show user's division name
                            return $userDivisionName;
                        };
                        @endphp

                        @foreach($segments as $key => $segment)
                        @php
                        $displayName = ucwords(str_replace(['-', '_'], ' ', $segment));
                        $segmentLower = strtolower($segment);
                        $previousSegment = $segments[$key - 1] ?? null;
                        
                        // Only build URLs for simple routes, avoid complex parameterized routes
                        if ($key === 0) {
                            $url = "/$segment";
                        } elseif (in_array($segmentLower, ['matrix']) && $previousSegment === 'activities') {
                            // Don't build URL for matrix parameter in staff activities route
                            $url = '';
                        } else {
                            $url = "$url/$segment";
                        }

                        // Handle special case: matrice or matrices
                        if (in_array($segmentLower, ['matrice', 'matrices'])) {
                            $displayName = 'Quarterly Matrix';
                            
                            // Only add division name if this is the main matrices page (not a specific matrix)
                            // Division will be shown in the numeric segment for specific matrices
                            if (!isset($matrix)) {
                                $divisionName = $getDisplayDivisionName(null);
                                if ($divisionName) {
                                    $displayName .= ' - ' . $divisionName;
                                }
                            }
                        }

                        // Handle numeric segments
                        elseif (is_numeric($segment)) {
                            if ($previousSegment === 'matrices' && isset($matrix)) {
                                $displayName = $matrix->year . ' ' . ($matrix->quarter ?? '');
                                
                                // Add division info for specific matrices - always show matrix division for details
                                $divisionName = $getDisplayDivisionName($matrix, 'matrix_details');
                                if ($divisionName) {
                                    $displayName .= ' - ' . $divisionName;
                                }
                            } elseif ($previousSegment === 'activities' && isset($activity)) {
                                $displayName = $activity->activity_title ?? 'Activity #' . $segment;
                            } elseif ($previousSegment === 'staff' && isset($staff)) {
                                $displayName = $staff->full_name ?? 'Staff #' . $segment;
                            } elseif ($previousSegment === 'matrix' && isset($matrix)) {
                                // Handle staff activities matrix route
                                $displayName = $matrix->year . ' ' . ($matrix->quarter ?? '');
                                $url = ''; // Don't make this clickable
                            } else {
                                $displayName = 'Details';
                            }
                        }

                        // Handle create/edit actions
                        elseif (in_array($segmentLower, ['create', 'edit'])) {
                            $displayName = ucfirst($segment);

                            // Check if previous was matrices
                            if (in_array(strtolower($previousSegment), ['matrice', 'matrices'])) {
                                // For create/edit actions, show the action without division (division shown in previous segment)
                                $displayName = ucfirst($segment);
                            }

                            $url = ''; // avoid link on action
                        }

                        // Handle special route patterns
                        elseif ($segmentLower === 'matrix' && $previousSegment === 'activities') {
                            // This is the staff activities matrix route - don't make it clickable
                            $displayName = 'Matrix';
                            $url = '';
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
                            {{  Str::limit($displayName,50) }}
                        </li>
                        @else
                        <li class="breadcrumb-item">
                            <a href="{{ url($url) }}" title="{{$displayName}}">
                                {{  Str::limit($displayName,100) }}
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