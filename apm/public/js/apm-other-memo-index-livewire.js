/**
 * Other memos index — Livewire wire:navigate friendly.
 * Uses AbortController so re-visiting the page does not stack duplicate listeners.
 */
(function () {
    'use strict';

    function otherMemosIndexPagePresent() {
        return !!document.querySelector('[data-apm-livewire-page="other-memos-index"]');
    }

    function bootOtherMemosIndex() {
        if (!otherMemosIndexPagePresent()) return;
        var tabs = document.getElementById('otherMemoTabs');
        var filtersEl = document.getElementById('otherMemoFilters');
        if (!tabs || !filtersEl) return;

        var root = document.querySelector('[data-apm-livewire-page="other-memos-index"]');
        if (root && root._apmOtherMemosIndexAbort) {
            root._apmOtherMemosIndexAbort.abort();
        }
        var ctrl = new AbortController();
        if (root) root._apmOtherMemosIndexAbort = ctrl;
        var sig = { signal: ctrl.signal };

        if (window.APMFilters) {
            APMFilters.clearInited('#otherMemoFilters');
            APMFilters.init('#otherMemoFilters', {
                fields: [
                    { param: 'year', id: 'year', default: APMFilters.currentYear },
                    { param: 'staff_id', id: 'staff_id' },
                    { param: 'division_id', id: 'division_id' },
                    { param: 'status', id: 'memo_status' },
                    { param: 'document_number', id: 'document_number' },
                    { param: 'search', id: 'search' }
                ],
                tabParam: 'filter_tab',
                tabDefault: 'mySubmitted',
                selectSelector: '.apm-filter-select'
            });
        }

        function applyFilters() {
            setTimeout(function () {
                var activeTab = document.querySelector('#otherMemoTabsContent .tab-pane.active');
                if (activeTab) loadOtherMemoTabData(activeTab.id);
            }, 0);
        }

        function getYearValue() {
            var currentYear = String(new Date().getFullYear());
            if (typeof window.$ !== 'undefined' && $('#year').length) {
                var jqVal = $('#year').val();
                if (jqVal != null && jqVal !== '') return String(jqVal).trim();
            }
            var sel = document.getElementById('year');
            if (!sel) return currentYear;
            var idx = sel.selectedIndex;
            if (idx < 0 || !sel.options[idx]) return currentYear;
            var v = (sel.options[idx].value || '').trim();
            return v || currentYear;
        }

        function mapPaneIdToTabParam(paneId) {
            if (paneId === 'otherAllMemos') return 'allMemos';
            if (paneId === 'otherMyDivision') return 'myDivision';
            return 'mySubmitted';
        }

        function rebuildOtherMemoTabShell(paneId, innerHtml) {
            if (paneId === 'otherAllMemos') {
                return '<div class="d-flex align-items-center justify-content-between mb-3"><div><h6 class="mb-0 text-primary fw-bold"><i class="bx bx-grid me-2"></i> All Other Memos</h6><small class="text-muted">All other memos in the system</small></div></div>' + innerHtml;
            }
            if (paneId === 'otherMyDivision') {
                return '<div class="d-flex align-items-center justify-content-between mb-3"><div><h6 class="mb-0 text-info fw-bold"><i class="bx bx-building me-2"></i> My Division Memos</h6><small class="text-muted">Other memos in your division (latest first)</small></div></div>' + innerHtml;
            }
            return '<div class="d-flex align-items-center justify-content-between mb-3"><div><h6 class="mb-0 text-success fw-bold"><i class="bx bx-file-alt me-2"></i> My Submitted Memos</h6><small class="text-muted">Other memos you have created</small></div></div>' + innerHtml;
        }

        function loadOtherMemoTabData(paneId, page) {
            page = page || 1;
            var tabParam = mapPaneIdToTabParam(paneId);
            var currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('page', page);
            currentUrl.searchParams.set('tab', tabParam);
            var year = getYearValue();
            var documentNumber = (document.getElementById('document_number') && document.getElementById('document_number').value) ? document.getElementById('document_number').value.trim() : '';
            var staffId = document.getElementById('staff_id') ? (document.getElementById('staff_id').value || '') : '';
            var divisionId = document.getElementById('division_id') ? (document.getElementById('division_id').value || '') : '';
            var status = document.getElementById('memo_status') ? (document.getElementById('memo_status').value || '') : '';
            var search = document.getElementById('search') ? (document.getElementById('search').value || '').trim() : '';
            currentUrl.searchParams.set('year', year);
            if (documentNumber) currentUrl.searchParams.set('document_number', documentNumber);
            else currentUrl.searchParams.delete('document_number');
            if (staffId) currentUrl.searchParams.set('staff_id', staffId);
            else currentUrl.searchParams.delete('staff_id');
            if (divisionId) currentUrl.searchParams.set('division_id', divisionId);
            else currentUrl.searchParams.delete('division_id');
            if (status) currentUrl.searchParams.set('status', status);
            else currentUrl.searchParams.delete('status');
            if (search) currentUrl.searchParams.set('search', search);
            else currentUrl.searchParams.delete('search');
            window.history.replaceState({}, '', currentUrl.toString());
            var tabContent = document.getElementById(paneId);
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
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.html && tabContent) {
                        tabContent.innerHTML = '<div class="p-3">' + rebuildOtherMemoTabShell(paneId, data.html) + '</div>';
                        attachOtherMemoPaginationHandlers(paneId);
                    } else if (!data.html && tabContent) {
                        tabContent.innerHTML = '<div class="text-center py-4 text-warning">No data received.</div>';
                    }
                    if (data.count_my_submitted !== undefined) {
                        var badgeMy = document.getElementById('badge-other-mySubmitted');
                        if (badgeMy) badgeMy.textContent = data.count_my_submitted;
                    }
                    if (data.count_my_division !== undefined) {
                        var badgeDivision = document.getElementById('badge-other-myDivision');
                        if (badgeDivision) badgeDivision.textContent = data.count_my_division;
                    }
                    if (data.count_all_memos !== undefined) {
                        var badgeAll = document.getElementById('badge-other-allMemos');
                        if (badgeAll) badgeAll.textContent = data.count_all_memos;
                    }
                })
                .catch(function (error) {
                    console.error('Error loading other memo tab data:', error);
                    if (tabContent) {
                        tabContent.innerHTML = '<div class="text-center py-4 text-danger">Error loading data. Please try again.</div>';
                    }
                });
        }

        function attachOtherMemoPaginationHandlers(paneId) {
            var tabContent = document.getElementById(paneId);
            if (!tabContent) return;
            tabContent.querySelectorAll('.pagination a').forEach(function (link) {
                link.addEventListener('click', function (e) {
                    e.preventDefault();
                    var url = new URL(this.href);
                    var p = url.searchParams.get('page') || 1;
                    loadOtherMemoTabData(paneId, p);
                });
            });
        }

        var applyBtn = document.getElementById('applyOtherMemoFilters');
        if (applyBtn) {
            applyBtn.addEventListener('click', function (e) {
                e.preventDefault();
                applyFilters();
            }, sig);
        }
        var form = document.getElementById('otherMemoFiltersForm');
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                applyFilters();
            }, sig);
        }
        ['staff_id', 'division_id', 'memo_status', 'year'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('change', applyFilters, sig);
        });
        var docNum = document.getElementById('document_number');
        if (docNum) {
            var documentNumberTimeout;
            docNum.addEventListener('input', function () {
                clearTimeout(documentNumberTimeout);
                documentNumberTimeout = setTimeout(applyFilters, 1000);
            }, sig);
            docNum.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    clearTimeout(documentNumberTimeout);
                    applyFilters();
                }
            }, sig);
        }

        var filterTabInput = document.getElementById('filter_tab');
        document.querySelectorAll('#otherMemoTabs [data-bs-toggle="tab"]').forEach(function (button) {
            button.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelectorAll('#otherMemoTabs .nav-link').forEach(function (btn) { btn.classList.remove('active'); });
                document.querySelectorAll('#otherMemoTabsContent .tab-pane').forEach(function (pane) { pane.classList.remove('active', 'show'); });
                this.classList.add('active');
                var tabId = this.getAttribute('aria-controls');
                if (filterTabInput) filterTabInput.value = mapPaneIdToTabParam(tabId);
                var tabPane = document.getElementById(tabId);
                if (tabPane) tabPane.classList.add('active', 'show');
                loadOtherMemoTabData(tabId);
            }, sig);
        });

        var urlTab = new URLSearchParams(window.location.search).get('tab');
        if (urlTab) {
            setTimeout(function () {
                var tabEl = (urlTab === 'allMemos')
                    ? document.getElementById('otherAllMemos-tab')
                    : ((urlTab === 'myDivision') ? document.getElementById('otherMyDivision-tab') : document.getElementById('otherMySubmitted-tab'));
                if (tabEl && typeof bootstrap !== 'undefined') {
                    document.querySelectorAll('#otherMemoTabs .nav-link').forEach(function (btn) { btn.classList.remove('active'); });
                    document.querySelectorAll('#otherMemoTabsContent .tab-pane').forEach(function (pane) { pane.classList.remove('active', 'show'); });
                    tabEl.classList.add('active');
                    var pane = document.getElementById(tabEl.getAttribute('aria-controls'));
                    if (pane) {
                        pane.classList.add('active', 'show');
                        loadOtherMemoTabData(pane.id);
                    }
                }
            }, 50);
        } else {
            var activeTabButton = document.querySelector('#otherMemoTabs .nav-link.active');
            if (activeTabButton) {
                loadOtherMemoTabData(activeTabButton.getAttribute('aria-controls'));
            }
        }
    }

    document.addEventListener('DOMContentLoaded', bootOtherMemosIndex);
    document.addEventListener('livewire:navigated', function () {
        setTimeout(bootOtherMemosIndex, 0);
    });
})();
