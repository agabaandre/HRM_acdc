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
                        // This ensures intelligent division display based on context:
                        // - For matrix details/preview: Always show matrix division name
                        // - For approval workflows: Always show matrix division name  
                        // - For other contexts: Show matrix division only if it belongs to user's division
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
                        
                        // Helper function to detect if we're in an approval context
                        $isApprovalContext = function() use ($segments) {
                            $approvalKeywords = ['approve', 'approval', 'status', 'trail', 'workflow', 'pending', 'review', 'decision', 'action'];
                            foreach ($segments as $segment) {
                                if (in_array(strtolower($segment), $approvalKeywords)) {
                                    return true;
                                }
                            }
                            return false;
                        };
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
                                
                                // Determine context for division display
                                $context = $isApprovalContext() ? 'matrix_approval' : 'matrix_details';
                                
                                // Add division info for specific matrices - always show matrix division for details/approval
                                $divisionName = $getDisplayDivisionName($matrix, $context);
                                if ($divisionName) {
                                    $displayName .= ' - ' . $divisionName;
                                }
                            } elseif ($previousSegment === 'activities' && isset($activity)) {
                                $displayName = $activity->activity_title ?? 'Activity #' . $segment;
                                
                                // If we're viewing matrix activities, add matrix division context
                                if (isset($matrix) && $matrix->division) {
                                    $displayName .= ' - ' . $matrix->division->division_name;
                                }
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
                                // For create/edit actions, show the action without division (division shown in previous segment)
                                $displayName = ucfirst($segment);
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
                        
                        // Handle approval-related segments
                        if (in_array($segmentLower, ['approve', 'approval', 'status', 'trail'])) {
                            $displayName = ucwords(str_replace('-', ' ', $segmentLower));
                            
                            // If we're in an approval context and have a matrix, show matrix division
                            if (isset($matrix) && $matrix->division) {
                                $displayName .= ' - ' . $matrix->division->division_name;
                            }
                        }
                        
                        // Special handling for matrix approval contexts
                        if (in_array($segmentLower, ['approval-trail', 'approval-trails', 'approval_trail', 'approval_trails'])) {
                            $displayName = 'Approval Trail';
                            
                            // Always show matrix division in approval trail context
                            if (isset($matrix) && $matrix->division) {
                                $displayName .= ' - ' . $matrix->division->division_name;
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