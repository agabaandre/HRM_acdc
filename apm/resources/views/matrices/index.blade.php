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

@section('content')
@include('pages.matrices-index-content', compact('title', 'module', 'divisions', 'focalPersons', 'selectedYear', 'selectedQuarter', 'selectedStatus', 'myDivisionTotalCount', 'allMatricesTotalCount'))
@endsection

@push('scripts')
    <script>
        function matricesIndexPageQueryParam(tabId) {
            return tabId === 'myDivision' ? 'my_division_page' : 'all_matrices_page';
        }

        function initMatricesIndexPage() {
            if (!document.getElementById('yearFilter')) return;
            const params = new URLSearchParams(window.location.search);
            const currentYear = new Date().getFullYear();
            const yearParam = params.get('year');
            $('#yearFilter').val(yearParam !== null ? yearParam : currentYear);
            $('#quarterFilter').val(params.get('quarter') || '');
            $('#divisionFilter').val(params.get('division') || '');
            $('#focalFilter').val(params.get('focal_person') || '');
            $('#statusFilter').val(params.get('status') || 'active');

            $('.select2').select2({
                width: '100%'
            });

            function loadTabData(tabId, page) {
                const mount = document.getElementById(tabId + '-ajax-body');
                if (!mount) return;

                if (page === undefined || page === null) {
                    const sp = new URLSearchParams(window.location.search);
                    const key = matricesIndexPageQueryParam(tabId);
                    page = parseInt(sp.get(key) || sp.get('page') || '1', 10) || 1;
                }

                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('tab', tabId);
                currentUrl.searchParams.delete('page');
                currentUrl.searchParams.delete('my_division_page');
                currentUrl.searchParams.delete('all_matrices_page');
                currentUrl.searchParams.set(matricesIndexPageQueryParam(tabId), String(page));

                const year = document.getElementById('yearFilter')?.value || '';
                const quarter = document.getElementById('quarterFilter')?.value || '';
                const division = document.getElementById('divisionFilter')?.value || '';
                const focalPerson = document.getElementById('focalFilter')?.value || '';
                const status = document.getElementById('statusFilter')?.value || 'active';

                currentUrl.searchParams.set('year', year);
                currentUrl.searchParams.set('quarter', quarter);
                currentUrl.searchParams.set('status', status);
                if (division) currentUrl.searchParams.set('division', division); else currentUrl.searchParams.delete('division');
                if (focalPerson) currentUrl.searchParams.set('focal_person', focalPerson); else currentUrl.searchParams.delete('focal_person');

                mount.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div></div>';

                fetch(currentUrl.toString(), {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.html) {
                        mount.innerHTML = data.html;
                        attachPaginationHandlers(tabId);
                    } else {
                        mount.innerHTML = '<div class="text-center py-4 text-warning">No data received.</div>';
                    }
                })
                .catch(function () {
                    mount.innerHTML = '<div class="text-center py-4 text-danger">Error loading data. Please try again.</div>';
                });
            }

            function attachPaginationHandlers(tabId) {
                const mount = document.getElementById(tabId + '-ajax-body');
                if (!mount) return;
                mount.querySelectorAll('.pagination a').forEach(function (link) {
                    link.addEventListener('click', function (e) {
                        e.preventDefault();
                        const url = new URL(link.getAttribute('href'), window.location.origin);
                        const key = matricesIndexPageQueryParam(tabId);
                        const p = parseInt(url.searchParams.get(key) || url.searchParams.get('page') || '1', 10) || 1;
                        loadTabData(tabId, p);
                    });
                });
            }

            function applyFilters() {
                const activeTab = document.querySelector('#matrixTabsContent .tab-pane.active');
                if (activeTab) {
                    loadTabData(activeTab.id, 1);
                }
            }

            if (document.getElementById('applyFilters')) {
                document.getElementById('applyFilters').addEventListener('click', applyFilters);
            }
            ['yearFilter', 'quarterFilter', 'divisionFilter', 'focalFilter', 'statusFilter'].forEach(function (id) {
                const el = document.getElementById(id);
                if (el) el.addEventListener('change', applyFilters);
            });

            $('#matrixTabs button[data-bs-toggle="tab"]').off('shown.bs.tab.matricesIndex').on('shown.bs.tab.matricesIndex', function (e) {
                const target = $(e.target).attr('data-bs-target');
                if (!target) return;
                const tabId = target.replace('#', '');
                loadTabData(tabId);
            });

            function scheduleMatricesIndexLazyLoad(tabId) {
                const scrollRoot = document.getElementById('matrixTabsContent');
                const mount = document.getElementById(tabId + '-ajax-body');
                if (!mount || !scrollRoot) {
                    if (mount) loadTabData(tabId);
                    return;
                }
                var fired = false;
                var obs = null;
                function run() {
                    if (fired) return;
                    fired = true;
                    if (obs) {
                        try { obs.disconnect(); } catch (err) {}
                    }
                    loadTabData(tabId);
                }
                if (typeof IntersectionObserver !== 'undefined') {
                    obs = new IntersectionObserver(function (entries) {
                        entries.forEach(function (entry) {
                            if (entry.isIntersecting) run();
                        });
                    }, { root: null, rootMargin: '140px 0px', threshold: 0 });
                    obs.observe(scrollRoot);
                }
                requestAnimationFrame(function () {
                    var r = scrollRoot.getBoundingClientRect();
                    var vh = window.innerHeight || document.documentElement.clientHeight || 700;
                    if (r.top < vh + 200) run();
                });
                if (typeof IntersectionObserver === 'undefined') {
                    setTimeout(run, 100);
                }
                setTimeout(run, 12000);
            }

            const activePane = document.querySelector('#matrixTabsContent .tab-pane.active');
            if (activePane && activePane.id) {
                scheduleMatricesIndexLazyLoad(activePane.id);
            }
        }
        $(document).ready(initMatricesIndexPage);
        document.addEventListener('livewire:navigated', function () {
            if (document.getElementById('matrixTabs')) initMatricesIndexPage();
        });
    </script>
@endpush
