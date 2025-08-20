
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

                                <!-- Notification Icon with Counter -->
                                <li class="nav-item dropdown" style="border:none !important;">
                                    <a class="nav-link dropdown-toggle dropdown-toggle-nocaret position-relative"
                                        href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <span class="alert-count" id="message-count">0</span>
                                        <i class='bx bx-comment' style="color:#FFF;"></i>
                                    </a>

                                    <!-- Dropdown -->
                                    <div class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3"
                                        style="min-width: 340px;">
                                        <div
                                            class="dropdown-header d-flex justify-content-between align-items-center px-3 pt-2">
                                            <span class="fw-semibold">Messages</span>
                                            <a href="javascript:;" class="small text-muted">Mark all as read</a>
                                        </div>

                                        <!-- Message List -->
                                        <div class="header-message-list ps-2 pe-2 pt-2" id="ajax-messages"
                                            style="max-height: 300px; overflow-y: auto;">
                                            <!-- Messages will be injected here via JS -->
                                            <div class="text-center text-muted py-3">Loading messages...</div>
                                        </div>

                                        <!-- Footer -->
                                        <div class="dropdown-footer text-center border-top py-2">
                                            <a href="{{ session('baseUrl', '') }}dashboard/all_messages"
                                                class="text-decoration-none">View
                                                All Messages</a>
                                        </div>
                                    </div>
                                </li>


                            </ul>
                        </div>
                        <div class="user-box dropdown">
                            <a class="d-flex align-items-center nav-link dropdown-toggle dropdown-toggle-nocaret"
                                href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">

                                @php
                                    $userName = trim(session('user.name', ''));
                                    $nameParts = preg_split('/\s+/', $userName, 2);
                                    $firstName = $nameParts[0] ?? '';
                                    $lastName = $nameParts[1] ?? '';
                                    // Define a set of professional color classes
                                    $avatarColors = [
                                        'bg-primary', 'bg-success', 'bg-info', 'bg-warning', 'bg-danger', 'bg-secondary'
                                    ];
                                    // Pick color based on first letter of first name
                                    $colorIndex = (ord(strtoupper($firstName[0] ?? 'A')) - 65) % count($avatarColors);
                                    $avatarColor = $avatarColors[$colorIndex];
                                @endphp
                                @if(session('user.photo'))
                                    <img src="{{ session('user.base_url', '') }}uploads/staff/{{ session('user.photo') }}"
                                        class="user-img" alt="user avatar">
                                @else
                                    <div class="user-avatar {{ $avatarColor }} text-white d-flex align-items-center justify-content-center" style="font-weight:600; font-size:1.1rem; width:40px; height:40px; border-radius:50%;">
                                        @if($firstName && $lastName)
                                            <span>{{ strtoupper(substr($firstName,0,1)) }}{{ strtoupper(substr($lastName,0,1)) }}</span>
                                        @elseif($firstName)
                                            <span>{{ strtoupper(substr($firstName,0,2)) }}</span>
                                        @else
                                            <span>U</span>
                                        @endif
                                    </div>
                                @endif

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
                             

                                <li><a class="dropdown-item" href="{{ session('user.base_url', '') }}auth/logout"><i
                                            class="bx bx-log-out-circle"></i><span>Logout</span></a>
                                </li>
                            </ul>
                        </div>

                    </nav>
                </div>
            </header>
            <!--end header -->

            