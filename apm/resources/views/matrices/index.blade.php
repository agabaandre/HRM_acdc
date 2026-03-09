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
@include('pages.matrices-index-content', compact('matrices', 'myDivisionMatrices', 'allMatrices', 'title', 'module', 'divisions', 'focalPersons', 'selectedYear', 'selectedQuarter'))
@endsection

@push('scripts')
    <script>
        function initMatricesIndexPage() {
            if (!document.getElementById('yearFilter')) return;
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
        }
        $(document).ready(initMatricesIndexPage);
        document.addEventListener('livewire:navigated', function() {
            if (document.getElementById('matrixTabs')) initMatricesIndexPage();
        });
    </script>
@endpush
