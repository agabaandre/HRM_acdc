@extends('layouts.app')

@section('title', 'Quarterly Travel Matrices')
@section('header', 'Quarterly Travel Matrices')

@push('styles')
<style>
/* Modal content wrapping styles */
.modal-body .list-group-item {
    word-wrap: break-word;
    word-break: break-word;
    white-space: normal;
    overflow-wrap: break-word;
    hyphens: auto;
}

.modal-body .list-group-item p {
    word-wrap: break-word;
    word-break: break-word;
    white-space: normal;
    overflow-wrap: break-word;
    hyphens: auto;
}

/* Ensure modal content doesn't exceed width */
.modal-body {
    max-width: 100%;
    overflow-x: hidden;
}

/* Better spacing for modal content */
.modal-body .list-group {
    margin-bottom: 0;
}

.modal-body .list-group-item {
    border-left: none;
    border-right: none;
    padding: 0.75rem 1rem;
}

.modal-body .list-group-item:first-child {
    border-top: none;
}

.modal-body .list-group-item:last-child {
    border-bottom: none;
}

/* Key result area descriptions */
.modal-body .fw-bold {
    word-wrap: break-word;
    word-break: break-word;
    white-space: normal;
}

/* Activity titles */
.modal-body .list-group-item span {
    word-wrap: break-word;
    word-break: break-word;
    white-space: normal;
}

/* Table column wrapping for better fit */
.table th:nth-child(4),
.table td:nth-child(4) {
    max-width: 150px;
    word-wrap: break-word;
    word-break: break-word;
    white-space: normal;
    overflow-wrap: break-word;
    hyphens: auto;
}

/* Focal Person column wrapping */
.table th:nth-child(5),
.table td:nth-child(5) {
    max-width: 120px;
    word-wrap: break-word;
    word-break: break-word;
    white-space: normal;
    overflow-wrap: break-word;
    hyphens: auto;
}

/* Ensure table fits without horizontal scroll */
.table-responsive {
    overflow-x: auto;
    max-width: 100%;
    margin: 0 8px 0 8px; /* Add 8px margin on left and right (compensating for p-3) */
    border: 0;
}

/* Adjust tab pane padding */
.tab-pane > div > div.d-flex {
    padding-left: 1rem;
    padding-right: 1rem;
}

/* Better spacing for table cells */
.table td {
    vertical-align: middle;
    padding: 0.75rem 0.5rem;
}

.table th {
    padding: 0.75rem 0.5rem;
    font-size: 0.9rem;
    font-weight: 600;
}
</style>
@endpush

@section('header-actions')
    @php $isFocal = isfocal_person(); @endphp

@endsection

@php
    //dd($matrices->toArray());
@endphp



@section('content')
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-body py-3 px-4 bg-light rounded-3">

            <div class="card-header bg-white d-flex justify-content-between align-items-center border-bottom-0 rounded-top">
                <h4 class="mb-0 text-success fw-bold"><i class="bx bx-grid-alt me-2 text-success"></i> Matrix Details</h4>
                @if ($isFocal)
                    <a href="{{ route('matrices.create') }}" class="btn btn-success">
                        <i class="bx bx-plus"></i> Create New Matrix
                    </a>
                @endif
            </div>

            <div class="row g-3 align-items-end" id="matrixFilters" autocomplete="off">
                <div class="col-md-2">
                    <label for="yearFilter" class="form-label fw-semibold mb-1"><i
                            class="bx bx-calendar me-1 text-success"></i> Year</label>
                    <div class="input-group w-100">
                        <span class="input-group-text bg-white"><i class="bx bx-calendar"></i></span>
                        <select class="form-select" id="yearFilter">
                            <option value="">All Years</option>
                            @foreach (range(date('Y') + 1, date('Y') - 5) as $year)
                                <option value="{{ $year }}" {{ $selectedYear == $year ? 'selected' : '' }}>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="quarterFilter" class="form-label fw-semibold mb-1"><i
                            class="bx bx-time-five me-1 text-success"></i> Quarter</label>
                    <div class="input-group w-100">
                        <span class="input-group-text bg-white"><i class="bx bx-time-five"></i></span>
                        <select class="form-select" id="quarterFilter">
                            <option value="">All Quarters</option>
                            @foreach (['Q1', 'Q2', 'Q3', 'Q4'] as $quarter)
                                <option value="{{ $quarter }}" {{ $selectedQuarter == $quarter ? 'selected' : '' }}>{{ $quarter }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="divisionFilter" class="form-label fw-semibold mb-1"><i
                            class="bx bx-building me-1 text-success"></i> Division</label>
                    <div class="input-group select2-flex w-100">

                        <select class="form-select select2" id="divisionFilter">
                            <option value="">All Divisions</option>
                            @foreach ($divisions as $division)
                                <option value="{{ $division->id }}">{{ $division->division_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="focalFilter" class="form-label fw-semibold mb-1"><i
                            class="bx bx-user-pin me-1 text-success"></i> Focal Person</label>
                    <div class="input-group select2-flex w-100">
                        <select class="form-select select2" id="focalFilter">
                            <option value="">All Focal Persons</option>
                            @foreach ($focalPersons as $person)
                                <option value="{{ $person->staff_id }}">{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-success w-100 fw-bold" id="applyFilters">
                        <i class="bx bx-search-alt-2 me-1"></i> Filter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <!-- Bootstrap Tabs Navigation -->
            <ul class="nav nav-tabs nav-fill" id="matrixTabs" role="tablist">
                @if($myDivisionMatrices->count() > 0)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="myDivision-tab" data-bs-toggle="tab" data-bs-target="#myDivision" type="button" role="tab" aria-controls="myDivision" aria-selected="true">
                            <i class="bx bx-home me-2"></i> My Division Matrices 
                            <span class="badge bg-success text-dark ms-2">{{ $myDivisionMatrices->count() }}</span>
                        </button>
                    </li>
                @endif
                @if(in_array(87, user_session('permissions', [])))
                    <li class="nav-item" role="presentation">
                        <button class="nav-link {{ $myDivisionMatrices->count() == 0 ? 'active' : '' }}" id="allMatrices-tab" data-bs-toggle="tab" data-bs-target="#allMatrices" type="button" role="tab" aria-controls="allMatrices" aria-selected="{{ $myDivisionMatrices->count() == 0 ? 'true' : 'false' }}">
                            <i class="bx bx-grid me-2"></i> All Matrices
                            <span class="badge bg-primary text-white ms-2">{{ $allMatrices->count() ?? 0 }}</span>
                        </button>
                    </li>
                @endif
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="matrixTabsContent">
                <!-- My Division Matrices Tab -->
                <div class="tab-pane fade show active" id="myDivision" role="tabpanel" aria-labelledby="myDivision-tab">
                    <div class="p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="mb-0 text-success fw-bold">
                                    <i class="bx bx-home me-2"></i> My Division Matrices
                                </h6>
                                <small class="text-muted">Matrices in your division and divisions where you are the head</small>
                            </div>
                            <div>
                                <a href="{{ route('matrices.export.division-csv') }}" class="btn btn-outline-success btn-sm">
                                    <i class="bx bx-download me-1"></i> Export to CSV
                                </a>
                            </div>
                        </div>
                        
                        @include('matrices.partials.my-division-tab')
                    </div>
                </div>

                <!-- All Matrices Tab -->
                @if(in_array(87, user_session('permissions', [])))
                <div class="tab-pane fade {{ $myDivisionMatrices->count() == 0 ? 'show active' : '' }}" id="allMatrices" role="tabpanel" aria-labelledby="allMatrices-tab">
                    <div class="p-3">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h6 class="mb-0 text-primary fw-bold">
                                    <i class="bx bx-grid me-2"></i> All Matrices
                                </h6>
                                <small class="text-muted">All matrices in the system</small>
                            </div>
                            <div>
                                <a href="{{ route('matrices.export.csv') }}" class="btn btn-outline-primary btn-sm">
                                    <i class="bx bx-download me-1"></i> Export to CSV
                                </a>
                            </div>
                        </div>
                        
                        @include('matrices.partials.all-matrices-tab')
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            // Pre-fill filters from URL
            const params = new URLSearchParams(window.location.search);
            // Default to current year if no year parameter exists (initial page load)
            // If year parameter exists but is empty, use empty string (explicit "All Years" selection)
            const currentYear = new Date().getFullYear();
            const yearParam = params.get('year');
            $('#yearFilter').val(yearParam !== null ? yearParam : currentYear);
            $('#quarterFilter').val(params.get('quarter') || '');
            $('#divisionFilter').val(params.get('division') || '');
            $('#focalFilter').val(params.get('focal_person') || '');

            // Apply Select2
            $('.select2').select2({
                width: '100%'
            });

            // AJAX filtering - auto-update when filters change
            function applyFilters() {
                const activeTab = document.querySelector('.tab-pane.active');
                if (activeTab) {
                    const tabId = activeTab.id;
                    loadTabData(tabId);
                }
            }
            
            // Manual filter button click
            if (document.getElementById('applyFilters')) {
                document.getElementById('applyFilters').addEventListener('click', applyFilters);
            }
            
            // Auto-apply filters when they change
            if (document.getElementById('yearFilter')) {
                document.getElementById('yearFilter').addEventListener('change', applyFilters);
            }
            
            if (document.getElementById('quarterFilter')) {
                document.getElementById('quarterFilter').addEventListener('change', applyFilters);
            }
            
            if (document.getElementById('divisionFilter')) {
                document.getElementById('divisionFilter').addEventListener('change', applyFilters);
            }
            
            if (document.getElementById('focalFilter')) {
                document.getElementById('focalFilter').addEventListener('change', applyFilters);
            }

            // Function to load tab data via AJAX
            function loadTabData(tabId, page = 1) {
                console.log('Loading matrices tab data for:', tabId, 'page:', page);
                
                const currentUrl = new URL(window.location);
                currentUrl.searchParams.set('page', page);
                currentUrl.searchParams.set('tab', tabId);
                
                // Include current filter values (include empty values to clear filters)
                const year = document.getElementById('yearFilter')?.value || '';
                const quarter = document.getElementById('quarterFilter')?.value || '';
                const division = document.getElementById('divisionFilter')?.value || '';
                const focalPerson = document.getElementById('focalFilter')?.value || '';
                
                // Always set parameters, even if empty, to properly handle "All Years" and "All Quarters"
                currentUrl.searchParams.set('year', year);
                currentUrl.searchParams.set('quarter', quarter);
                if (division) currentUrl.searchParams.set('division', division);
                if (focalPerson) currentUrl.searchParams.set('focal_person', focalPerson);
                
                console.log('Matrices request URL:', currentUrl.toString());
                
                // Show loading indicator
                const tabContent = document.getElementById(tabId);
                if (tabContent) {
                    tabContent.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
                }
                
                fetch(currentUrl.toString(), {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(response => {
                    console.log('Matrices response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Matrices response data:', data);
                    if (data.html) {
                        if (tabContent) {
                            tabContent.innerHTML = data.html;
                            attachPaginationHandlers(tabId);
                        }
                    } else {
                        console.error('No HTML data received for matrices');
                        if (tabContent) {
                            tabContent.innerHTML = '<div class="text-center py-4 text-warning">No data received.</div>';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading matrices tab data:', error);
                    if (tabContent) {
                        tabContent.innerHTML = '<div class="text-center py-4 text-danger">Error loading data. Please try again.</div>';
                    }
                });
            }
            
            function attachPaginationHandlers(tabId) {
                const tabContent = document.getElementById(tabId);
                if (!tabContent) return;
                
                const paginationLinks = tabContent.querySelectorAll('.pagination a');
                paginationLinks.forEach(link => {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        const url = new URL(this.href);
                        const page = url.searchParams.get('page') || 1;
                        loadTabData(tabId, page);
                    });
                });
            }

            // Handle tab switching with AJAX
            $('#matrixTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                const target = $(e.target).attr('data-bs-target');
                const tabId = target.replace('#', '');
                
                // Load tab data via AJAX
                loadTabData(tabId);
            });

            // Show pagination info
            function updatePaginationInfo() {
                $('.pagination-info').each(function() {
                    const $pagination = $(this).closest('.tab-pane').find('.pagination');
                    if ($pagination.length > 0) {
                        const $paginationLinks = $pagination.find('a, span');
                        const currentPage = $paginationLinks.filter('.active').text();
                        const totalPages = $paginationLinks.filter('.page-link').length;
                        
                        if (currentPage && totalPages > 1) {
                            $(this).text(`Page ${currentPage} of ${totalPages}`);
                        }
                    }
                });
            }
            
            updatePaginationInfo();
        });
    </script>
@endpush
