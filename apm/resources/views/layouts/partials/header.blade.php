
        <div class="wrapper">
         
            <!--start header -->
            <header>
                <div class="topbar d-flex">
                    <nav class="navbar navbar-expand">
                        <div class="topbar-logo-header">
                            <div class="">
                                <img src="{{ asset('assets/images/AU_CDC_Logo-800.png') }}" width="200"
                                    style="filter: brightness(0) invert(1);">
                            </div>
                        </div>
                        <div class="mobile-toggle-menu"><i class='bx bx-menu'></i></div>
                        <div class="search-bar flex-grow-1" style="display:none;">
                            <div class="position-relative search-bar-box">
                                <input type="text" class="form-control search-control" placeholder="Type to search...">
                                <span class="position-absolute top-50 search-show translate-middle-y"><i
                                        class='bx bx-search'></i></span>
                                <span class="position-absolute top-50 search-close translate-middle-y"><i
                                        class='bx bx-x'></i></span>
                            </div>
                        </div>
                        <div class="top-menu ms-auto">
                            <ul class="navbar-nav align-items-center">
                                @php
                                    $session = (array) session('user');
                                    $session['base_url'] = session('user.base_url', env('BASE_URL', 'http://localhost/staff'));
                                    // Remove trailing slash and 'apm' if present
                                    $staffBaseUrl = rtrim($session['base_url'], '/');
                                    $staffBaseUrl = str_replace('/apm', '', $staffBaseUrl);
                                    $staffPortalUrl = $staffBaseUrl . '/auth/profile';
                                    $permissions = session('permissions', []);
                                    
                                    // Finance URL: Use domain/finance in production (reverse proxy), localhost:3002 in development
                                    // Only show Finance link if user has permission 92
                                    $financeUrl = '';
                                    if (in_array(92, $permissions)) {
                                        $financeToken = urlencode(base64_encode(json_encode($session)));
                                        $host = request()->getHost();
                                        if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
                                            $financeUrl = 'http://localhost:3002?token=' . $financeToken;
                                        } else {
                                            // In production, use the domain directly with /finance/ (reverse proxy)
                                            $scheme = request()->getScheme();
                                            $financeUrl = $scheme . '://' . $host . '/finance?token=' . $financeToken;
                                        }
                                    }
                                @endphp
                                
                                <!-- Staff Portal Link -->
                                <li class="nav-item">
                                    <a 
                                        class="nav-link" 
                                        href="{{ $staffPortalUrl }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        style="font-size: 0.875rem;"
                                    >
                                        <i class='bx bx-user' style="color:#FFF; font-size: 1.1rem;"></i>
                                        <span class="ms-2 d-none d-md-inline" style="color:#FFF; font-size: 0.875rem;">Staff Portal</span>
                                    </a>
                                </li>
                                
                                <!-- Finance Management Link -->
                                @if(in_array(92, $permissions) && !empty($financeUrl))
                                <li class="nav-item">
                                    <a 
                                        class="nav-link" 
                                        href="{{ $financeUrl }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        style="font-size: 0.875rem;"
                                    >
                                        <i class='bx bx-wallet' style="color:#FFF; font-size: 1.1rem;"></i>
                                        <span class="ms-2 d-none d-md-inline" style="color:#FFF; font-size: 0.875rem;">Finance</span>
                                    </a>
                                </li>
                                @endif

                                <li class="nav-item  dropdown-large">
                                    <a class="nav-link dropdown-toggle dropdown-toggle-nocaret" href="#" role="button"
                                        data-bs-toggle="dropdown" aria-expanded="false"> <i class='bx bx-category'
                                            style="color:#FFF;"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <div class="row row-cols-3 g-3 p-3">
                                            <div class="col text-center">
                                                <div class="app-box mx-auto bg-gradient-cosmic text-white"><i
                                                        class='bx bx-group'></i>
                                                </div>
                                                <div class="app-title">Divisions</div>
                                            </div>
                                            <div class="col text-center">
                                                <div class="app-box mx-auto bg-gradient-burning text-white"><i
                                                        class='bx bx-atom'></i>
                                                </div>
                                                <div class="app-title">Projects</div>
                                            </div>
                                            <div class="col text-center">
                                                <div class="app-box mx-auto bg-gradient-lush text-white"><i
                                                        class='bx bx-shield'></i>
                                                </div>
                                                <div class="app-title">RCCS</div>
                                            </div>
                                            <div class="col text-center">
                                                <div class="app-box mx-auto bg-gradient-kyoto text-dark"><i
                                                        class='bx bx-notification'></i>
                                                </div>
                                                <div class="app-title">Leave</div>
                                            </div>
                                            <div class="col text-center">
                                                <div class="app-box mx-auto bg-gradient-blues text-dark"><i
                                                        class='bx bx-file'></i>
                                                </div>
                                                <div class="app-title">Appraisal</div>
                                            </div>
                                            <div class="col text-center">
                                                <div class="app-box mx-auto bg-gradient-moonlit text-white"><i
                                                        class='bx bx-filter-alt'></i>
                                                </div>
                                                <div class="app-title">Travel</div>
                                            </div>
                                        </div>
                                    </div>
                                </li>

                                <!-- Pending Approvals Icon with Counter -->
                                <li class="nav-item dropdown" style="border:none !important;">
                                    <a class="nav-link dropdown-toggle dropdown-toggle-nocaret position-relative"
                                        href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <span class="alert-count" id="pending-approvals-count">0</span>
                                        <i class='bx bx-message-square-dots' style="color:#FFF;"></i>
                                    </a>

                                    <!-- Dropdown -->
                                    <div class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3"
                                        style="min-width: 400px;">
                                        <div
                                            class="dropdown-header d-flex justify-content-between align-items-center px-3 pt-2">
                                            <span class="fw-semibold">Pending Approvals</span>
                                            <a href="{{ route('pending-approvals.index') }}" class="small text-primary">View All</a>
                                        </div>

                                        <!-- Pending Approvals List -->
                                        <div class="header-message-list ps-2 pe-2 pt-2" id="pending-approvals-list"
                                            style="max-height: 300px; overflow-y: auto;">
                                            <!-- Pending approvals will be injected here via JS -->
                                            <div class="text-center text-muted py-3">
                                                <div class="spinner-border spinner-border-sm" role="status">
                                                    <span class="visually-hidden">Loading...</span>
                                                </div>
                                                <div class="mt-2">Loading pending approvals...</div>
                                            </div>
                                        </div>

                                        <!-- Footer -->
                                        <div class="dropdown-footer text-center border-top py-2">
                                            <a href="{{ route('pending-approvals.index') }}"
                                                class="text-decoration-none btn btn-primary btn-sm">View All Pending Approvals</a>
                                        </div>
                                    </div>
                                </li>


                            </ul>
                        </div>
                        <div class="user-box dropdown">
                            <a class="d-flex align-items-center nav-link dropdown-toggle dropdown-toggle-nocaret"
                                href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                {!!user_info()!!}

                                <div class="user-info ps-3">
                                    <p class="user-name mb-0">{{ session('user.name', '') }}</p>
                                    <p class="designattion mb-0"></p>
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                               
                          
                                @if(session('original_user'))
                                    <a href="{{ session('user.base_url', '') }}auth/revert" class="btn btn-sm btn-danger">
                                        <i class="fa fa-undo"></i> Revert to Admin
                                    </a>
                                    <li>
                                    <div class="dropdown-divider mb-0"></div>
                                    </li>
                                @endif
                             

                                <li><a class="dropdown-item" href="{{ session('user.base_url', env('BASE_URL', 'http://localhost/staff')) }}/auth/logout"><i
                                            class="bx bx-log-out-circle"></i><span>Logout</span></a>
                                </li>
                            </ul>
                        </div>

                    </nav>
                </div>
            </header>
            <!--end header -->

            <!-- Pending Approvals JavaScript -->
            <script>
            $(document).ready(function() {
                // Load pending approvals data
                function loadPendingApprovals() {
                    $.get('{{ route("pending-approvals.recent") }}', function(response) {
                        if (response.success) {
                            // Update counter
                            $('#pending-approvals-count').text(response.data.summary.total_pending);
                            
                            // Update dropdown content
                            updatePendingApprovalsDropdown(response.data);
                        }
                    }).fail(function() {
                        $('#pending-approvals-list').html('<div class="text-center text-muted py-3">Error loading pending approvals</div>');
                    });
                }
                
                function updatePendingApprovalsDropdown(data) {
                    let html = '';
                    
                    if (data.summary.total_pending === 0) {
                        html = '<div class="text-center text-muted py-3"><i class="fas fa-check-circle fa-2x mb-2"></i><div>No pending approvals</div></div>';
                    } else {
                        const recentItems = data.recent_items || [];
                        
                        if (recentItems.length === 0) {
                            html = '<div class="text-center text-muted py-3">No recent pending approvals</div>';
                        } else {
                            recentItems.forEach(item => {
                                const timeAgo = getTimeAgo(item.date_received || item.created_at);
                                html += `
                                    <div class="dropdown-item-text px-3 py-2 border-bottom">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <div class="fw-semibold text-dark">${item.title}</div>
                                                <small class="text-muted">${item.category} • ${item.division}</small>
                                                <div class="mt-1">
                                                    <span class="badge bg-warning me-1">${item.workflow_role}</span>
                                                    <span class="badge bg-info">Level ${item.approval_level}</span>
                                                </div>
                                            </div>
                                            <small class="text-muted">${timeAgo}</small>
                                        </div>
                                    </div>
                                `;
                            });
                            
                            if (data.summary.total_pending > recentItems.length) {
                                html += `<div class="dropdown-item-text text-center text-primary fw-semibold">+${data.summary.total_pending - recentItems.length} more items</div>`;
                            }
                        }
                    }
                    
                    $('#pending-approvals-list').html(html);
                }
                
                function getTimeAgo(dateString) {
                    if (!dateString) return 'Unknown';
                    
                    const date = new Date(dateString);
                    const now = new Date();
                    const diffInSeconds = Math.floor((now - date) / 1000);
                    
                    if (diffInSeconds < 60) return 'Just now';
                    if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + 'm ago';
                    if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + 'h ago';
                    return Math.floor(diffInSeconds / 86400) + 'd ago';
                }
                
                // Load data on page load
                loadPendingApprovals();
                
                // Refresh data every 30 seconds
                setInterval(loadPendingApprovals, 30000);
                
                // Load data when dropdown is opened
                $('#pending-approvals-list').closest('.dropdown').on('show.bs.dropdown', function() {
                    loadPendingApprovals();
                });
            });
            </script>
            