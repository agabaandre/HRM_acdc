/**
 * Matrix show — Activities, Single memos, and Participants tables (Vue 3 + Vuetify 3)
 */
(function () {
    'use strict';

    const MOUNT_ID = 'matrix-show-app';

    function sanitizeNumber(value, fallback = 0) {
        if (value == null || value === '') return fallback;
        const n = parseFloat(String(value).replace(/,/g, ''));
        return Number.isFinite(n) ? n : fallback;
    }

    function calculateBudget(budgetBreakdown) {
        if (!budgetBreakdown) return 0;

        let budget = budgetBreakdown;
        if (typeof budgetBreakdown === 'string') {
            try {
                budget = JSON.parse(budgetBreakdown);
            } catch (e) {
                return 0;
            }
        }

        if (Array.isArray(budget)) return 0;
        if (!budget || typeof budget !== 'object') return 0;

        let totalBudget = 0;
        Object.keys(budget).forEach((key) => {
            if (key === 'grand_total' || key === 'total') return;

            const entries = budget[key];
            if (Array.isArray(entries)) {
                entries.forEach((item) => {
                    const unitCost = sanitizeNumber(item.unit_cost ?? item.unit_price ?? 0);
                    const units = sanitizeNumber(item.units ?? item.quantity ?? 0);
                    const days = sanitizeNumber(item.days ?? 1, 1);
                    totalBudget += unitCost * units * days;
                });
            } else if (entries && typeof entries === 'object') {
                Object.values(entries).forEach((item) => {
                    if (!item || typeof item !== 'object') return;
                    const unitCost = sanitizeNumber(item.unit_cost ?? item.unit_price ?? 0);
                    const units = sanitizeNumber(item.units ?? item.quantity ?? 0);
                    const days = sanitizeNumber(item.days ?? 1, 1);
                    totalBudget += unitCost * units * days;
                });
            } else if (typeof entries === 'number' && !isNaN(entries)) {
                totalBudget += entries;
            } else if (typeof entries === 'string' && entries.trim() !== '') {
                totalBudget += sanitizeNumber(entries);
            }
        });

        return totalBudget;
    }

    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function formatCurrency(amount) {
        return '$' + new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount || 0);
    }

    function participantCountTitle(count) {
        return (Number(count) || 0) + ' participants';
    }

    function getActivityStatus(matrixStatus, activity) {
        if (matrixStatus === 'approved') {
            const overallStatus = activity.overall_status || 'pending';
            switch (overallStatus) {
                case 'approved':
                    return { text: 'Approved', color: 'success' };
                case 'pending':
                    return { text: 'Pending', color: 'secondary' };
                case 'returned':
                    return { text: 'Returned', color: 'warning' };
                case 'rejected':
                    return { text: 'Rejected', color: 'error' };
                default:
                    return { text: overallStatus.charAt(0).toUpperCase() + overallStatus.slice(1), color: 'info' };
            }
        }

        if (activity.allow_print) {
            return { text: 'Passed', color: 'success' };
        }
        if (activity.has_passed_at_current_level) {
            return { text: 'Passed', color: 'success' };
        }
        if (activity.my_current_level_action) {
            const action = activity.my_current_level_action.action;
            if (action === 'approved') return { text: 'Approved', color: 'success' };
            if (action === 'rejected') return { text: 'Rejected', color: 'error' };
            if (action === 'returned') return { text: 'Returned', color: 'warning' };
            return { text: action.charAt(0).toUpperCase() + action.slice(1), color: 'info' };
        }
        return { text: 'Pending', color: 'secondary' };
    }

    function getSingleMemoStatus(memo) {
        const status = (memo.overall_status || 'pending').toLowerCase();
        switch (status) {
            case 'approved':
            case 'passed':
                return { text: 'Approved', color: 'success' };
            case 'rejected':
                return { text: 'Rejected', color: 'error' };
            case 'returned':
                return { text: 'Returned', color: 'warning' };
            case 'draft':
                return { text: 'Draft', color: 'secondary' };
            default:
                return { text: 'Pending', color: 'info' };
        }
    }

    function getFunderInfo(activity) {
        if (activity.activity_budget && activity.activity_budget.length > 0
            && activity.activity_budget[0].fundcode && activity.activity_budget[0].fundcode.funder) {
            return activity.activity_budget[0].fundcode.funder;
        }
        return activity.funder_from_budget_breakdown || null;
    }

    function isIntramuralFunding(activity) {
        const fundTypeId = Number(activity.fund_type_id ?? activity.fund_type?.id ?? 0);
        if (fundTypeId === 1) return true;
        const name = String(activity.fund_type?.name || '').toLowerCase();
        return name.includes('intramural');
    }

    function getIntramuralBudgetCodes(activity) {
        if (!isIntramuralFunding(activity)) return [];

        const codes = new Set();
        if (Array.isArray(activity.activity_budget)) {
            activity.activity_budget.forEach((entry) => {
                const code = entry?.fundcode?.code;
                if (code) codes.add(String(code).trim());
            });
        }

        const breakdownCode = activity.fund_code_from_budget_breakdown?.code;
        if (breakdownCode) codes.add(String(breakdownCode).trim());

        if (Array.isArray(activity.fund_codes_from_budget_breakdown)) {
            activity.fund_codes_from_budget_breakdown.forEach((fundCode) => {
                if (fundCode?.code) codes.add(String(fundCode.code).trim());
            });
        }

        return Array.from(codes).filter(Boolean);
    }

    function singleMemoRowBackground(memo) {
        const status = String(memo?.overall_status || '').toLowerCase();
        if (status !== 'approved' && status !== 'passed') {
            return '#ffe8b8';
        }
        return '#b8dfc4';
    }

    function canShowSingleMemoDeleteButton(memo, cfg) {
        if (!memo || memo.overall_status !== 'draft') return false;
        const userId = cfg.currentUserId;
        return memo.responsible_person_id == userId
            || memo.staff_id == userId
            || cfg.matrixDivisionHead == userId
            || cfg.matrixFocalPerson == userId;
    }

    function triggerBootstrapModal(modalId, attrs) {
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.style.display = 'none';
        trigger.setAttribute('data-bs-toggle', 'modal');
        trigger.setAttribute('data-bs-target', '#' + modalId);
        Object.entries(attrs || {}).forEach(([key, value]) => {
            trigger.setAttribute(key, String(value ?? ''));
        });
        document.body.appendChild(trigger);
        trigger.click();
        setTimeout(() => trigger.remove(), 100);
    }

    window.ApmMatrixShow = {
        formatCurrency,
    };

    function bootMatrixShow(mountEl, cfg) {
        if (!mountEl || !cfg) {
            window.ApmVuetifyPage.destroy(MOUNT_ID);
            return;
        }

        window.ApmVuetifyPage.destroy(MOUNT_ID);
        mountEl.innerHTML = '';

        const { createApp, ref, computed, watch, onMounted, onBeforeUnmount } = Vue;
        const vuetify = window.ApmVuetifyMemoList
            ? window.ApmVuetifyMemoList.createVuetify()
            : Vuetify.createVuetify();

        const permissions = cfg.permissions || {};
        const routes = cfg.routes || {};
        const defaults = cfg.defaults || {};
        const summary = cfg.activitySummary || {};

        const app = createApp({
            setup() {
                const snackbar = ref({ show: false, text: '', color: 'error' });

                const activitySearch = ref(defaults.activitiesSearch || '');
                const activityDocument = ref(defaults.activitiesDocumentNumber || '');
                const activityPage = ref(1);
                const activityPerPage = ref(defaults.activitiesPerPage || 50);
                const activities = ref([]);
                const activityPagination = ref({ total: 0, from: 0, to: 0, last_page: 1 });
                const activitiesLoading = ref(false);
                const activitySearchStatus = ref('');
                const selectedActivities = ref([]);
                const passDialogOpen = ref(false);
                const batchSubmitting = ref(false);

                const singleMemoSearch = ref(defaults.singleMemosSearch || '');
                const singleMemoDocument = ref(defaults.singleMemosDocumentNumber || '');
                const singleMemoPage = ref(1);
                const singleMemoPerPage = ref(defaults.singleMemosPerPage || 10);
                const singleMemos = ref([]);
                const singleMemoPagination = ref({ total: 0, from: 0, to: 0, last_page: 1 });
                const singleMemosLoading = ref(false);
                const singleMemoSearchStatus = ref('');
                const singleMemosCount = ref(cfg.approvedSingleMemosCount || 0);
                const singleMemosLoaded = ref(false);
                const singleMemosCardRef = ref(null);
                let singleMemosObserver = null;

                const participantSearch = ref('');
                const participantPage = ref(1);
                const participantPerPage = ref(defaults.participantsPerPage || 25);
                const participants = ref([]);
                const participantPagination = ref({ recordsTotal: 0, totalPages: 1, currentPage: 1 });
                const participantSummary = ref({ total_staff: 0, total_division_days: 0, over_limit_count: 0 });
                const participantsLoading = ref(false);
                const participantsLoaded = ref(false);
                const participantsMountReady = ref(false);
                let participantsObserver = null;

                const activityHeaders = computed(() => {
                    const base = [
                        { title: '#', key: 'row_num', sortable: false, width: 56 },
                        { title: 'Document #', key: 'document_number', sortable: false, width: 130 },
                        { title: 'Title', key: 'activity_title', sortable: false, minWidth: 200 },
                        { title: 'Date Range', key: 'date_range', sortable: false, width: 150 },
                        { title: 'Owner', key: 'responsible_person', sortable: false, width: 150 },
                        { title: 'Participants & Funding', key: 'participants_funding', sortable: false, width: 200, align: 'center' },
                        { title: 'Budget (Est./Avail.)', key: 'budget', sortable: false, width: 140, align: 'center' },
                        { title: 'Status', key: 'status', sortable: false, width: 110, align: 'center' },
                        { title: 'Actions', key: 'actions', sortable: false, width: 150, align: 'center' },
                    ];
                    if (permissions.canShowCheckbox) {
                        return [{
                            title: 'Pass all',
                            key: 'data-table-select',
                            sortable: false,
                            width: 72,
                            align: 'center',
                        }, ...base];
                    }
                    return base;
                });

                const singleMemoHeaders = [
                    { title: '#', key: 'row_num', sortable: false, width: 56 },
                    { title: 'Document #', key: 'document_number', sortable: false, width: 130 },
                    { title: 'Title', key: 'activity_title', sortable: false, minWidth: 200 },
                    { title: 'Date Range', key: 'date_range', sortable: false, width: 150 },
                    { title: 'Owner', key: 'responsible_person', sortable: false, width: 150 },
                    { title: 'Participants & Funding', key: 'participants_funding', sortable: false, width: 200, align: 'center' },
                    { title: 'Budget (Est./Avail.)', key: 'budget', sortable: false, width: 140, align: 'center' },
                    { title: 'Status', key: 'status', sortable: false, width: 110, align: 'center' },
                    { title: 'Actions', key: 'actions', sortable: false, width: 130, align: 'center' },
                ];

                const participantHeaders = [
                    { title: '#', key: 'row_num', sortable: false, width: 56 },
                    { title: 'Staff Name', key: 'staff_name', sortable: false, minWidth: 180 },
                    { title: 'Position', key: 'job_name', sortable: false, minWidth: 180 },
                    { title: 'Division Days', key: 'division_days', sortable: false, width: 120, align: 'center' },
                    { title: 'Other Divisions', key: 'other_days', sortable: false, width: 130, align: 'center' },
                    { title: 'Total Days', key: 'total_days', sortable: false, width: 120, align: 'center' },
                ];

                const activitySummaryText = computed(() => {
                    let text = `${summary.total || 0} activities in this matrix`;
                    if ((summary.singleMemos || 0) > 0) {
                        const n = summary.singleMemos;
                        text += ` (${n} single memo${n > 1 ? 's' : ''})`;
                    }
                    const parts = [];
                    if (summary.intramural) parts.push(`${summary.intramural} intramural`);
                    if (summary.extramural) parts.push(`${summary.extramural} extramural`);
                    if (summary.external) parts.push(`${summary.external} external source`);
                    if (parts.length) text += ' - ' + parts.join(', ');
                    return text;
                });

                const activityShowingRange = computed(() => {
                    const p = activityPagination.value;
                    if (!p.total) return 'Showing 0-0 of 0 activities';
                    return `Showing ${p.from || 0}-${p.to || 0} of ${p.total} activities`;
                });

                const singleMemoShowingRange = computed(() => {
                    const p = singleMemoPagination.value;
                    if (!singleMemosLoaded.value) return 'Not loaded yet — scroll here or wait a few seconds';
                    if (!p.total) return 'Showing 0-0 of 0 single memos';
                    return `Showing ${p.from || 0}-${p.to || 0} of ${p.total} single memos`;
                });

                const participantShowingRange = computed(() => {
                    const total = participantPagination.value.recordsTotal || 0;
                    if (!total) return '0-0';
                    const start = (participantPage.value - 1) * participantPerPage.value + 1;
                    const end = Math.min(participantPage.value * participantPerPage.value, total);
                    return `${start}-${end}`;
                });

                const selectedActivityRows = computed(() => {
                    const selected = selectedActivities.value || [];
                    if (!selected.length) return [];

                    if (typeof selected[0] === 'object' && selected[0] !== null) {
                        return selected;
                    }

                    const idSet = new Set(selected.map((id) => Number(id)));
                    return activities.value.filter((activity) => idSet.has(Number(activity.id)));
                });

                const selectedActivityCount = computed(() => selectedActivityRows.value.length);

                const showApproveBar = computed(() => (
                    selectedActivityCount.value > 0 && !permissions.isLevel5Approver
                ));

                function notify(text, color = 'error') {
                    snackbar.value = { show: true, text, color };
                }

                function submitBatchStatus(action) {
                    const rows = selectedActivityRows.value;
                    if (!rows.length || batchSubmitting.value) return;

                    const url = routes.batchStatusUrl;
                    if (!url) {
                        notify('Batch approval URL is not configured.', 'error');
                        return;
                    }

                    batchSubmitting.value = true;

                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = url;
                    form.style.display = 'none';

                    const addField = (name, value) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = name;
                        input.value = value;
                        form.appendChild(input);
                    };

                    addField('_token', cfg.csrf || '');
                    addField('matrix_id', String(cfg.matrixId));
                    addField('action', action);
                    addField('activity_ids[]', rows.map((row) => String(row.id)).join(','));

                    document.body.appendChild(form);
                    form.submit();
                }

                function mapActivityRow(activity, index) {
                    const status = getActivityStatus(cfg.matrixStatus, activity);
                    const budget = calculateBudget(activity.budget_breakdown);
                    const funder = getFunderInfo(activity);
                    const selectable = !!(activity.can_approve && !activity.user_has_passed && !permissions.isLevel5Approver);

                    return {
                        ...activity,
                        row_num: ((activityPage.value - 1) * activityPerPage.value) + index + 1,
                        _selectable: selectable,
                        _status: status,
                        _budget: budget,
                        _funder: funder,
                        _budgetCodes: getIntramuralBudgetCodes(activity),
                        _rowClass: '',
                    };
                }

                function mapSingleMemoRow(memo, index) {
                    const status = getSingleMemoStatus(memo);
                    const budget = calculateBudget(memo.budget_breakdown);
                    const rowClass = memo.overall_status !== 'approved' ? 'mx-row-single-memo mx-row-warning' : 'mx-row-single-memo mx-row-approved';
                    return {
                        ...memo,
                        row_num: ((singleMemoPage.value - 1) * singleMemoPerPage.value) + index + 1,
                        _status: status,
                        _budget: budget,
                        _rowClass: rowClass,
                        _rowStyle: { backgroundColor: singleMemoRowBackground(memo) },
                        _canDelete: canShowSingleMemoDeleteButton(memo, cfg),
                    };
                }

                function mapParticipantRow(staff, index) {
                    const fullName = `${staff.title || ''} ${staff.fname || ''} ${staff.lname || ''}`.trim();
                    return {
                        ...staff,
                        row_num: ((participantPage.value - 1) * participantPerPage.value) + index + 1,
                        staff_name: fullName,
                        staff_url: `${routes.staffShowBase}/${staff.staff_id}/activities/matrix/${cfg.matrixId}`,
                        _rowClass: staff.is_over_limit ? 'mx-row-over-limit' : '',
                    };
                }

                async function loadActivities() {
                    activitiesLoading.value = true;
                    activitySearchStatus.value = '';
                    try {
                        const params = new URLSearchParams({
                            page: String(activityPage.value),
                            per_page: String(activityPerPage.value),
                        });
                        if (activitySearch.value.trim()) params.set('search', activitySearch.value.trim());
                        if (activityDocument.value.trim()) params.set('document_number', activityDocument.value.trim());

                        const res = await fetch(`${routes.activitiesAjax}?${params.toString()}`, {
                            headers: { Accept: 'application/json' },
                        });
                        const data = await res.json();
                        if (!res.ok) throw new Error(data.error || 'Failed to load activities.');

                        const rows = data.activities?.data || [];
                        activities.value = rows.map(mapActivityRow);
                        activityPagination.value = {
                            total: data.pagination?.total ?? 0,
                            from: data.pagination?.from ?? 0,
                            to: data.pagination?.to ?? 0,
                            last_page: data.pagination?.last_page ?? 1,
                        };

                        if (activitySearch.value.trim() || activityDocument.value.trim()) {
                            const total = activityPagination.value.total;
                            if (total === 0) {
                                activitySearchStatus.value = 'No results found for your search. Try different keywords.';
                            } else {
                                activitySearchStatus.value = `Found ${total} result${total !== 1 ? 's' : ''} (showing ${activityPagination.value.from}-${activityPagination.value.to} of ${total})`;
                            }
                        }

                        selectedActivities.value = [];
                    } catch (e) {
                        activities.value = [];
                        activityPagination.value = { total: 0, from: 0, to: 0, last_page: 1 };
                        notify(e.message || 'Failed to load activities.');
                    } finally {
                        activitiesLoading.value = false;
                    }
                }

                async function loadSingleMemos() {
                    if (!cfg.approvedSingleMemosCount) return;
                    singleMemosLoading.value = true;
                    singleMemoSearchStatus.value = '';
                    singleMemosLoaded.value = true;
                    try {
                        const params = new URLSearchParams({
                            page: String(singleMemoPage.value),
                            per_page: String(singleMemoPerPage.value),
                        });
                        if (singleMemoSearch.value.trim()) params.set('search', singleMemoSearch.value.trim());
                        if (singleMemoDocument.value.trim()) params.set('document_number', singleMemoDocument.value.trim());

                        const res = await fetch(`${routes.singleMemosAjax}?${params.toString()}`, {
                            headers: { Accept: 'application/json' },
                        });
                        const data = await res.json();
                        if (!res.ok) throw new Error(data.error || 'Failed to load single memos.');

                        const rows = data.single_memos?.data || [];
                        singleMemos.value = rows.map(mapSingleMemoRow);
                        singleMemoPagination.value = {
                            total: data.pagination?.total ?? 0,
                            from: data.pagination?.from ?? 0,
                            to: data.pagination?.to ?? 0,
                            last_page: data.pagination?.last_page ?? 1,
                        };
                        singleMemosCount.value = data.pagination?.total ?? singleMemosCount.value;

                        if (singleMemoSearch.value.trim() || singleMemoDocument.value.trim()) {
                            const total = singleMemoPagination.value.total;
                            if (total === 0) {
                                singleMemoSearchStatus.value = 'No results found for your search. Try different keywords.';
                            } else {
                                singleMemoSearchStatus.value = `Found ${total} result${total !== 1 ? 's' : ''} (showing ${singleMemoPagination.value.from}-${singleMemoPagination.value.to} of ${total})`;
                            }
                        }
                    } catch (e) {
                        singleMemos.value = [];
                        singleMemoPagination.value = { total: 0, from: 0, to: 0, last_page: 1 };
                        notify(e.message || 'Failed to load single memos.');
                    } finally {
                        singleMemosLoading.value = false;
                    }
                }

                async function loadParticipants() {
                    if (!participantsLoaded.value) {
                        participantsLoaded.value = true;
                    }
                    participantsLoading.value = true;
                    try {
                        const params = new URLSearchParams({
                            page: String(participantPage.value),
                            pageSize: String(participantPerPage.value),
                        });
                        if (participantSearch.value.trim()) params.set('search', participantSearch.value.trim());

                        const res = await fetch(`${routes.divisionStaffAjax}?${params.toString()}`, {
                            headers: { Accept: 'application/json' },
                        });
                        const data = await res.json();
                        if (!res.ok) throw new Error(data.error || data.message || 'Failed to load staff data.');

                        participants.value = (data.data || []).map(mapParticipantRow);
                        participantPagination.value = {
                            recordsTotal: data.recordsTotal ?? 0,
                            totalPages: data.totalPages ?? 1,
                            currentPage: data.currentPage ?? participantPage.value,
                        };
                        participantSummary.value = data.summary || { total_staff: 0, total_division_days: 0, over_limit_count: 0 };
                    } catch (e) {
                        participants.value = [];
                        participantPagination.value = { recordsTotal: 0, totalPages: 1, currentPage: 1 };
                        notify(e.message || 'Failed to load participants.');
                    } finally {
                        participantsLoading.value = false;
                    }
                }

                function resetActivitySearch() {
                    activitySearch.value = '';
                    activityDocument.value = '';
                    activityPage.value = 1;
                    loadActivities();
                }

                function resetSingleMemoSearch() {
                    singleMemoSearch.value = '';
                    singleMemoDocument.value = '';
                    singleMemoPage.value = 1;
                    loadSingleMemos();
                }

                function activityShowUrl(id) {
                    return `${routes.activityShowBase}/${id}`;
                }

                function singleMemoShowUrl(id) {
                    return `${routes.singleMemoShowBase}/${id}`;
                }

                function canCopyActivity(activity) {
                    return (activity.overall_status === 'draft' || activity.overall_status === 'pending')
                        && permissions.canShowCopyButton;
                }

                function openCopyModal(activity) {
                    triggerBootstrapModal('copyActivityModal', {
                        'data-activity-id': activity.id,
                        'data-activity-title': activity.activity_title || '',
                    });
                }

                function openDeleteActivityModal(activity) {
                    triggerBootstrapModal('deleteActivityModal', {
                        'data-activity-id': activity.id,
                        'data-activity-title': activity.activity_title || '',
                    });
                }

                function openDeleteSingleMemoModal(memo) {
                    triggerBootstrapModal('deleteSingleMemoModal', {
                        'data-memo-id': memo.id,
                        'data-memo-title': memo.activity_title || '',
                    });
                }

                function setupParticipantsLazyLoad() {
                    const target = document.getElementById('matrix-show-participants-mount');
                    if (!target) {
                        setTimeout(() => {
                            participantsLoaded.value = true;
                            loadParticipants();
                        }, 200);
                        return;
                    }

                    const trigger = () => {
                        if (participantsLoaded.value) return;
                        loadParticipants();
                        if (participantsObserver) {
                            participantsObserver.disconnect();
                            participantsObserver = null;
                        }
                    };

                    if (typeof IntersectionObserver !== 'undefined') {
                        participantsObserver = new IntersectionObserver((entries) => {
                            entries.forEach((entry) => {
                                if (entry.isIntersecting) trigger();
                            });
                        }, { root: null, rootMargin: '120px 0px', threshold: 0 });
                        participantsObserver.observe(target);
                        requestAnimationFrame(() => {
                            const rect = target.getBoundingClientRect();
                            const vh = window.innerHeight || 800;
                            if (rect.top < vh + 200) trigger();
                        });
                    } else {
                        setTimeout(trigger, 200);
                    }

                    setTimeout(() => {
                        if (!participantsLoaded.value) trigger();
                    }, 12000);
                }

                function setupSingleMemosLazyLoad() {
                    if (!cfg.approvedSingleMemosCount || singleMemosLoaded.value) return;

                    const trigger = () => {
                        if (singleMemosLoaded.value) return;
                        loadSingleMemos();
                        if (singleMemosObserver) {
                            singleMemosObserver.disconnect();
                            singleMemosObserver = null;
                        }
                    };

                    const card = singleMemosCardRef.value;
                    if (!card) {
                        setTimeout(trigger, 200);
                        return;
                    }

                    if (typeof IntersectionObserver !== 'undefined') {
                        singleMemosObserver = new IntersectionObserver((entries) => {
                            entries.forEach((entry) => {
                                if (entry.isIntersecting) trigger();
                            });
                        }, { root: null, rootMargin: '200px 0px', threshold: 0 });
                        singleMemosObserver.observe(card);
                        requestAnimationFrame(() => {
                            const rect = card.getBoundingClientRect();
                            const vh = window.innerHeight || 800;
                            if (rect.top < vh + 240 && rect.bottom > -240) trigger();
                        });
                    } else {
                        setTimeout(trigger, 200);
                    }

                    setTimeout(() => {
                        if (!singleMemosLoaded.value) trigger();
                    }, 15000);
                }

                let activitySearchTimer = null;
                let singleMemoSearchTimer = null;
                let participantSearchTimer = null;

                watch(activityPage, () => loadActivities());
                watch(activityPerPage, () => {
                    activityPage.value = 1;
                    loadActivities();
                });
                watch(singleMemoPage, () => {
                    if (singleMemosLoaded.value) loadSingleMemos();
                });
                watch(singleMemoPerPage, () => {
                    singleMemoPage.value = 1;
                    if (singleMemosLoaded.value) loadSingleMemos();
                });

                watch(activitySearch, () => {
                    clearTimeout(activitySearchTimer);
                    activitySearchTimer = setTimeout(() => {
                        activityPage.value = 1;
                        loadActivities();
                    }, 500);
                });
                watch(activityDocument, () => {
                    clearTimeout(activitySearchTimer);
                    activitySearchTimer = setTimeout(() => {
                        activityPage.value = 1;
                        loadActivities();
                    }, 500);
                });
                watch(singleMemoSearch, () => {
                    if (!singleMemosLoaded.value) return;
                    clearTimeout(singleMemoSearchTimer);
                    singleMemoSearchTimer = setTimeout(() => {
                        singleMemoPage.value = 1;
                        loadSingleMemos();
                    }, 500);
                });
                watch(singleMemoDocument, () => {
                    if (!singleMemosLoaded.value) return;
                    clearTimeout(singleMemoSearchTimer);
                    singleMemoSearchTimer = setTimeout(() => {
                        singleMemoPage.value = 1;
                        loadSingleMemos();
                    }, 500);
                });
                watch(participantSearch, () => {
                    if (!participantsLoaded.value) return;
                    clearTimeout(participantSearchTimer);
                    participantSearchTimer = setTimeout(() => {
                        participantPage.value = 1;
                        loadParticipants();
                    }, 500);
                });

                watch(participantPage, () => {
                    if (participantsLoaded.value) loadParticipants();
                });
                watch(participantPerPage, () => {
                    if (!participantsLoaded.value) return;
                    participantPage.value = 1;
                    loadParticipants();
                });

                onMounted(() => {
                    participantsMountReady.value = !!document.getElementById('matrix-show-participants-mount');
                    loadActivities();
                    if (cfg.approvedSingleMemosCount > 0) {
                        loadSingleMemos();
                    } else {
                        setTimeout(setupSingleMemosLazyLoad, 100);
                    }
                    setTimeout(setupParticipantsLazyLoad, 50);
                });

                onBeforeUnmount(() => {
                    if (singleMemosObserver) {
                        singleMemosObserver.disconnect();
                        singleMemosObserver = null;
                    }
                    if (participantsObserver) {
                        participantsObserver.disconnect();
                        participantsObserver = null;
                    }
                });

                return {
                    cfg,
                    permissions,
                    summary,
                    snackbar,
                    activitySearch,
                    activityDocument,
                    activityPage,
                    activityPerPage,
                    activities,
                    activityPagination,
                    activitiesLoading,
                    activitySearchStatus,
                    selectedActivities,
                    selectedActivityRows,
                    selectedActivityCount,
                    showApproveBar,
                    passDialogOpen,
                    batchSubmitting,
                    submitBatchStatus,
                    activityHeaders,
                    activitySummaryText,
                    activityShowingRange,
                    singleMemoSearch,
                    singleMemoDocument,
                    singleMemoPage,
                    singleMemoPerPage,
                    singleMemos,
                    singleMemoPagination,
                    singleMemosLoading,
                    singleMemoSearchStatus,
                    singleMemosCount,
                    singleMemosLoaded,
                    singleMemosCardRef,
                    singleMemoHeaders,
                    singleMemoShowingRange,
                    participantSearch,
                    participantPage,
                    participantPerPage,
                    participants,
                    participantPagination,
                    participantSummary,
                    participantsLoading,
                    participantsLoaded,
                    participantsMountReady,
                    participantHeaders,
                    participantShowingRange,
                    loadActivities,
                    loadSingleMemos,
                    resetActivitySearch,
                    resetSingleMemoSearch,
                    activityShowUrl,
                    singleMemoShowUrl,
                    canCopyActivity,
                    openCopyModal,
                    openDeleteActivityModal,
                    openDeleteSingleMemoModal,
                    formatDate,
                    formatCurrency,
                    participantCountTitle,
                };
            },
            template: `
<v-app class="mx-show-vuetify-app" theme="apmLight">
  <v-container fluid class="pa-0">
    <v-card class="mb-0 mx-section-card mx-activities-section elevation-2">
      <v-card-title class="mx-section-head d-flex flex-wrap align-center justify-space-between gap-3 py-4 px-4">
        <div>
          <div class="text-h6 font-weight-bold d-flex align-center gap-2">
            <v-icon icon="mdi-calendar-range" color="primary" />
            Activities
          </div>
          <div class="text-body-2 text-medium-emphasis mt-1">{{ activitySummaryText }}</div>
        </div>
      </v-card-title>

      <v-card-text class="mx-filter-bar px-4 py-3">
        <div class="mx-filter-row d-flex align-center">
          <v-text-field
            v-model="activitySearch"
            label="Search activities"
            prepend-inner-icon="mdi-magnify"
            clearable
            density="compact"
            variant="outlined"
            hide-details
            class="mx-filter-field mx-filter-grow"
          />
          <v-text-field
            v-model="activityDocument"
            label="Document #"
            prepend-inner-icon="mdi-pound"
            clearable
            density="compact"
            variant="outlined"
            hide-details
            class="mx-filter-field mx-filter-doc"
          />
          <v-select
            v-model="activityPerPage"
            :items="[{ title: '10', value: 10 }, { title: '20', value: 20 }, { title: '50', value: 50 }, { title: '100', value: 100 }]"
            item-title="title"
            item-value="value"
            label="Show"
            density="compact"
            variant="outlined"
            hide-details
            class="mx-filter-field mx-filter-page-size"
          />
          <div class="mx-filter-actions">
            <v-btn color="primary" variant="flat" class="mx-filter-btn" prepend-icon="mdi-magnify" @click="activityPage = 1; loadActivities()">Search</v-btn>
            <v-btn variant="outlined" class="mx-filter-btn" prepend-icon="mdi-close" @click="resetActivitySearch">Reset</v-btn>
          </div>
        </div>
      </v-card-text>

      <v-alert v-if="activitySearchStatus" type="info" variant="tonal" class="mx-4 mb-0 mt-2" density="compact">
        {{ activitySearchStatus }}
      </v-alert>

      <div class="mx-table-meta px-4 py-2">
        <span>{{ activityShowingRange }}</span>
        <span v-if="permissions.canShowCheckbox" class="ms-2 text-caption text-medium-emphasis">
          Use <strong>Pass all</strong> or row checkboxes to select activities for bulk approval.
        </span>
      </div>

      <v-data-table
        v-model="selectedActivities"
        :headers="activityHeaders"
        :items="activities"
        :loading="activitiesLoading"
        :items-per-page="-1"
        hide-default-footer
        :show-select="permissions.canShowCheckbox"
        :item-selectable="(item) => item._selectable"
        item-value="id"
        class="apm-list-table mx-matrix-table"
        density="comfortable"
      >
        <template #header.data-table-select="{ allSelected, someSelected, selectAll }">
          <div class="mx-pass-all-header">
            <v-checkbox-btn
              :model-value="allSelected"
              :indeterminate="someSelected && !allSelected"
              color="success"
              density="compact"
              hide-details
              aria-label="Pass all activities on this page"
              @update:model-value="selectAll"
            />
            <span class="mx-pass-all-label">Pass all</span>
          </div>
        </template>
        <template #item.document_number="{ item }">
          <v-chip color="primary" variant="flat" class="mx-doc-chip" label>{{ item.document_number || 'N/A' }}</v-chip>
        </template>
        <template #item.activity_title="{ item }">
          <div class="font-weight-medium">{{ item.activity_title }}</div>
        </template>
        <template #item.date_range="{ item }">
          <div class="text-body-2">
            <div class="font-weight-bold text-primary">{{ formatDate(item.date_from) }}</div>
            <div class="text-medium-emphasis">to {{ formatDate(item.date_to) }}</div>
            <div v-if="item.locations && item.locations.length" class="mt-2 pt-2 border-t">
              <div class="text-caption font-weight-bold text-medium-emphasis mb-1">
                <v-icon icon="mdi-map-marker" size="x-small" class="me-1" />Location
              </div>
              <div v-for="loc in item.locations" :key="loc.id" class="text-caption text-medium-emphasis">{{ loc.name || 'N/A' }}</div>
            </div>
          </div>
        </template>
        <template #item.responsible_person="{ item }">
          <div v-if="item.responsible_person">
            <div class="font-weight-medium">{{ item.responsible_person.fname }} {{ item.responsible_person.lname }}</div>
            <div class="text-caption text-medium-emphasis">{{ item.responsible_person.job_name || 'N/A' }}</div>
          </div>
          <span v-else class="text-medium-emphasis">Not assigned</span>
        </template>
        <template #item.participants_funding="{ item }">
          <div class="mx-participants-funding-cell">
            <div class="mx-participant-count" :title="participantCountTitle(item.total_participants)">
              <v-icon icon="mdi-account-group" size="18" color="primary" />
              <span class="mx-participant-count__num">{{ item.total_participants || 0 }}</span>
            </div>
            <div class="mx-funding-cell">
              <v-chip color="primary" variant="flat" size="small" class="mx-funding-cell__type">
                {{ item.fund_type ? item.fund_type.name : 'N/A' }}
              </v-chip>
              <div v-if="item._funder" class="mx-funding-cell__funder">{{ item._funder.name }}</div>
              <div v-else-if="!item.fund_type" class="mx-funding-cell__code">Not specified</div>
              <div v-if="item._funder && item._funder.code && item._funder.code !== item._funder.name" class="mx-funding-cell__code">
                {{ item._funder.code }}
              </div>
              <div v-if="item._budgetCodes && item._budgetCodes.length" class="mx-funding-cell__budget-codes">
                <v-chip
                  v-for="code in item._budgetCodes"
                  :key="code"
                  size="x-small"
                  color="primary"
                  variant="outlined"
                  class="mx-budget-code-chip"
                >{{ code }}</v-chip>
              </div>
            </div>
          </div>
        </template>
        <template #item.budget="{ item }">
          <div class="font-weight-bold text-success">{{ formatCurrency(item._budget) }} USD</div>
          <div v-if="item.available_budget" class="text-caption text-medium-emphasis">Available: {{ formatCurrency(item.available_budget) }} USD</div>
        </template>
        <template #item.status="{ item }">
          <v-chip :color="item._status.color" size="small" variant="flat">{{ item._status.text }}</v-chip>
          <v-icon v-if="item.user_has_passed && !item.allow_print" icon="mdi-check-circle" color="success" size="small" class="ms-1" title="You have already approved this activity" />
        </template>
        <template #item.actions="{ item }">
          <div class="d-flex flex-column gap-1 align-center">
            <v-btn size="small" variant="outlined" color="primary" prepend-icon="mdi-eye" :href="activityShowUrl(item.id)">Open</v-btn>
            <v-btn v-if="canCopyActivity(item)" size="small" variant="outlined" color="info" prepend-icon="mdi-content-copy" @click="openCopyModal(item)">Copy</v-btn>
            <v-btn v-if="permissions.canShowDeleteButton" size="small" variant="outlined" color="error" prepend-icon="mdi-delete" @click="openDeleteActivityModal(item)">Delete</v-btn>
          </div>
        </template>
        <template #no-data>
          <div class="text-center py-8 text-medium-emphasis">
            <v-icon icon="mdi-calendar-remove" size="48" class="mb-2" />
            <div>No activities found for this matrix.</div>
          </div>
        </template>
      </v-data-table>

      <div v-if="activityPagination.last_page > 1" class="d-flex justify-center py-3 border-t">
        <v-pagination v-model="activityPage" :length="activityPagination.last_page" :total-visible="7" color="primary" rounded="lg" />
      </div>

      <v-alert v-if="permissions.showApprovalGuidelines" type="info" variant="tonal" class="ma-4" border="start">
        <div class="font-weight-bold mb-2">Approval guidelines</div>
        <ul class="ps-4 mb-0 text-body-2">
          <li v-if="permissions.canShowCheckbox" class="mb-1">Use the <strong>Pass all</strong> checkbox in the table header (or individual row checkboxes) to select activities, then click <strong>Pass Selected Activities</strong>.</li>
          <li class="mb-1"><strong>Return entire matrix</strong> when <strong>more than 50%</strong> of the activities have an issue.</li>
          <li class="mb-1"><strong>Do not</strong> return every activity as a single memo.</li>
          <li>When <strong>around 30% or fewer</strong> activities have issues, pass the rest and return only problematic ones as single memos.</li>
        </ul>
      </v-alert>

      <v-alert v-if="permissions.showFinanceNotice" type="warning" variant="tonal" class="ma-4" border="start">
        <strong>Finance Officer Notice:</strong>
        As a Finance Officer, you must approve activities individually to enter the available budget for each activity. Bulk approval is not available for your approval level.
      </v-alert>
    </v-card>

    <v-card v-if="showApproveBar" class="mx-section-card elevation-2 mt-0 mb-4">
      <v-card-actions class="px-4 py-4 d-flex flex-wrap align-center justify-space-between gap-3">
        <div class="d-flex align-center gap-2 text-body-1 text-medium-emphasis">
          <v-icon icon="mdi-check-circle" color="success" size="large" />
          <span class="font-weight-medium">{{ selectedActivityCount }} {{ selectedActivityCount === 1 ? 'activity' : 'activities' }} selected</span>
        </div>
        <v-btn color="success" variant="flat" size="large" prepend-icon="mdi-check" @click="passDialogOpen = true">
          Pass Selected Activities
        </v-btn>
      </v-card-actions>
    </v-card>

    <v-dialog v-model="passDialogOpen" max-width="560" scrollable>
      <v-card>
        <v-card-title class="d-flex align-center bg-success text-white py-4 px-4">
          <v-icon icon="mdi-check" class="me-2" />
          Pass Selected Activities
          <v-spacer />
          <v-btn icon="mdi-close" variant="text" color="white" :disabled="batchSubmitting" @click="passDialogOpen = false" />
        </v-card-title>
        <v-card-text class="pt-4">
          <p class="mb-3">Are you sure you want to pass the selected activities?</p>
          <v-alert type="info" variant="tonal" density="compact" class="mb-4">
            <strong>Note:</strong> This action will mark all selected activities as passed.
          </v-alert>
          <v-list v-if="selectedActivityRows.length" density="compact" class="py-0">
            <v-list-item
              v-for="item in selectedActivityRows"
              :key="item.id"
              class="px-0"
            >
              <template #prepend>
                <v-icon icon="mdi-check-circle" color="success" size="small" />
              </template>
              <v-list-item-title class="text-body-2 font-weight-medium">
                {{ item.activity_title || 'Untitled activity' }}
              </v-list-item-title>
              <v-list-item-subtitle v-if="item.document_number" class="text-caption">
                Document #{{ item.document_number }}
              </v-list-item-subtitle>
            </v-list-item>
          </v-list>
        </v-card-text>
        <v-card-actions class="px-4 pb-4">
          <v-spacer />
          <v-btn variant="outlined" prepend-icon="mdi-close" :disabled="batchSubmitting" @click="passDialogOpen = false">Cancel</v-btn>
          <v-btn color="success" variant="flat" prepend-icon="mdi-check" :loading="batchSubmitting" @click="submitBatchStatus('passed')">
            Yes, Pass Activities
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <div v-if="cfg.approvedSingleMemosCount > 0" ref="singleMemosCardRef" class="mt-4 mx-single-memos-wrap">
    <v-card class="mx-section-card mx-single-memos-section elevation-2">
      <v-card-title class="mx-section-head d-flex flex-wrap align-center justify-space-between gap-3 py-4 px-4">
        <div>
          <div class="text-h6 font-weight-bold d-flex align-center gap-2">
            <v-icon icon="mdi-file-document-outline" color="primary" />
            Single Memos
          </div>
          <div class="text-body-2 text-medium-emphasis mt-1">
            <span id="single-memos-count">{{ singleMemosCount }}</span> approved single memos in this matrix
          </div>
        </div>
      </v-card-title>

      <v-card-text class="mx-filter-bar px-4 py-3">
        <div class="mx-filter-row d-flex align-center">
          <v-text-field
            v-model="singleMemoSearch"
            label="Search single memos"
            prepend-inner-icon="mdi-magnify"
            clearable
            density="compact"
            variant="outlined"
            hide-details
            class="mx-filter-field mx-filter-grow"
          />
          <v-text-field
            v-model="singleMemoDocument"
            label="Document #"
            prepend-inner-icon="mdi-pound"
            clearable
            density="compact"
            variant="outlined"
            hide-details
            class="mx-filter-field mx-filter-doc"
          />
          <v-select
            v-model="singleMemoPerPage"
            :items="[{ title: '10', value: 10 }, { title: '20', value: 20 }, { title: '50', value: 50 }, { title: '100', value: 100 }]"
            item-title="title"
            item-value="value"
            label="Show"
            density="compact"
            variant="outlined"
            hide-details
            class="mx-filter-field mx-filter-page-size"
          />
          <div class="mx-filter-actions">
            <v-btn color="primary" variant="flat" class="mx-filter-btn" prepend-icon="mdi-magnify" @click="singleMemoPage = 1; loadSingleMemos()">Search</v-btn>
            <v-btn variant="outlined" class="mx-filter-btn" prepend-icon="mdi-close" @click="resetSingleMemoSearch">Reset</v-btn>
          </div>
        </div>
      </v-card-text>

      <v-alert v-if="singleMemoSearchStatus" type="info" variant="tonal" class="mx-4 mb-0 mt-2" density="compact">
        {{ singleMemoSearchStatus }}
      </v-alert>

      <div class="mx-table-meta px-4 py-2">{{ singleMemoShowingRange }}</div>

      <v-data-table
        :headers="singleMemoHeaders"
        :items="singleMemos"
        :loading="singleMemosLoading"
        :items-per-page="-1"
        hide-default-footer
        :row-props="(data) => ({ class: data.item._rowClass, style: data.item._rowStyle })"
        :cell-props="({ item }) => ({ style: item._rowStyle || {} })"
        class="apm-list-table mx-matrix-table mx-single-memos-table"
        density="comfortable"
      >
        <template #item.document_number="{ item }">
          <v-chip color="primary" variant="flat" class="mx-doc-chip" label>{{ item.document_number || 'N/A' }}</v-chip>
        </template>
        <template #item.activity_title="{ item }">
          <div class="font-weight-medium">{{ item.activity_title }}</div>
        </template>
        <template #item.date_range="{ item }">
          <div class="text-body-2">
            <div class="font-weight-bold text-primary">{{ formatDate(item.date_from) }}</div>
            <div class="text-medium-emphasis">to {{ formatDate(item.date_to) }}</div>
            <div v-if="item.locations && item.locations.length" class="mt-2 pt-2 border-t">
              <div class="text-caption font-weight-bold text-medium-emphasis mb-1">Location</div>
              <div v-for="loc in item.locations" :key="loc.id" class="text-caption text-medium-emphasis">{{ loc.name || 'N/A' }}</div>
            </div>
          </div>
        </template>
        <template #item.responsible_person="{ item }">
          <div v-if="item.responsible_person">
            <div class="font-weight-medium">{{ item.responsible_person.fname }} {{ item.responsible_person.lname }}</div>
            <div class="text-caption text-medium-emphasis">{{ item.responsible_person.job_name || 'N/A' }}</div>
          </div>
          <span v-else class="text-medium-emphasis">Not assigned</span>
        </template>
        <template #item.participants_funding="{ item }">
          <div class="mx-participants-funding-cell">
            <div class="mx-participant-count" :title="participantCountTitle(item.total_participants)">
              <v-icon icon="mdi-account-group" size="18" color="primary" />
              <span class="mx-participant-count__num">{{ item.total_participants || 0 }}</span>
            </div>
            <div class="mx-funding-cell">
              <v-chip color="primary" variant="flat" size="small" class="mx-funding-cell__type">
                {{ item.fund_type ? item.fund_type.name : 'N/A' }}
              </v-chip>
            </div>
          </div>
        </template>
        <template #item.budget="{ item }">
          <div class="font-weight-bold text-success">{{ formatCurrency(item._budget) }} USD</div>
          <div v-if="item.available_budget" class="text-caption text-medium-emphasis">Available: {{ formatCurrency(item.available_budget) }} USD</div>
        </template>
        <template #item.status="{ item }">
          <v-chip :color="item._status.color" size="small" variant="flat">{{ item._status.text }}</v-chip>
        </template>
        <template #item.actions="{ item }">
          <div class="d-flex flex-column gap-1 align-center">
            <v-btn size="small" variant="outlined" color="primary" prepend-icon="mdi-eye" :href="singleMemoShowUrl(item.id)">Open</v-btn>
            <v-btn v-if="item._canDelete" size="small" variant="outlined" color="error" prepend-icon="mdi-delete" @click="openDeleteSingleMemoModal(item)">Delete</v-btn>
          </div>
        </template>
        <template #no-data>
          <div class="text-center py-6 text-medium-emphasis">
            <span v-if="!singleMemosLoaded">Single memos load when you scroll to this section.</span>
            <span v-else>No single memos match your search.</span>
          </div>
        </template>
      </v-data-table>

      <div v-if="singleMemoPagination.last_page > 1" class="d-flex justify-center py-3 border-t">
        <v-pagination v-model="singleMemoPage" :length="singleMemoPagination.last_page" :total-visible="7" color="primary" rounded="lg" />
      </div>
    </v-card>
    </div>

    <Teleport to="#matrix-show-participants-mount" :disabled="!participantsMountReady">
      <v-card class="mx-section-card elevation-2">
        <v-card-title class="mx-section-head d-flex flex-wrap align-center justify-space-between gap-3 py-4 px-4">
          <div>
            <div class="text-h6 font-weight-bold d-flex align-center gap-2">
              <v-icon icon="mdi-calendar-account" color="primary" />
              Division Schedule - {{ (cfg.quarter || '').toUpperCase() }} {{ cfg.year }}
            </div>
            <div class="text-body-2 text-medium-emphasis mt-1">
              Staff schedule for {{ cfg.divisionName }} in {{ (cfg.quarter || '').toUpperCase() }} {{ cfg.year }}
            </div>
          </div>
        </v-card-title>

        <v-card-text class="mx-filter-bar px-4 py-3">
          <div class="mx-filter-row d-flex align-center">
            <v-text-field
              v-model="participantSearch"
              label="Search by name, position, or duty station"
              prepend-inner-icon="mdi-magnify"
              clearable
              density="compact"
              variant="outlined"
              hide-details
              class="mx-filter-field mx-filter-grow-wide"
            />
            <v-select
              v-model="participantPerPage"
              :items="[{ title: '10', value: 10 }, { title: '25', value: 25 }, { title: '50', value: 50 }, { title: '100', value: 100 }]"
              item-title="title"
              item-value="value"
              label="Show"
              density="compact"
              variant="outlined"
              hide-details
              class="mx-filter-field mx-filter-page-size"
            />
          </div>
        </v-card-text>

        <v-data-table
          :headers="participantHeaders"
          :items="participants"
          :loading="participantsLoading && participantsLoaded"
          :items-per-page="-1"
          hide-default-footer
          :row-props="(data) => ({ class: data.item._rowClass })"
          class="apm-list-table mx-matrix-table"
          density="comfortable"
        >
          <template #item.staff_name="{ item }">
            <a :href="item.staff_url" class="text-primary font-weight-medium text-decoration-none">{{ item.staff_name }}</a>
          </template>
          <template #item.job_name="{ item }">
            <div>{{ item.job_name || 'Not specified' }}</div>
            <div v-if="item.duty_station_name" class="text-caption text-medium-emphasis">{{ item.duty_station_name }}</div>
          </template>
          <template #item.total_days="{ item }">
            <div v-if="item.is_over_limit" class="text-error font-weight-bold">
              <v-icon icon="mdi-alert" size="small" class="me-1" />{{ item.total_days }}
              <div class="text-caption">Over limit</div>
            </div>
            <span v-else class="font-weight-bold">{{ item.total_days }}</span>
          </template>
          <template #no-data>
            <div class="text-center py-6 text-medium-emphasis">
              <v-progress-circular v-if="participantsLoading && participantsLoaded" indeterminate color="primary" size="28" class="mb-2" />
              <div v-if="!participantsLoaded">Division schedule loads when you scroll to this section.</div>
              <div v-else>No staff found. Try adjusting your search.</div>
            </div>
          </template>
        </v-data-table>

        <v-card-actions class="px-4 py-3 border-t flex-wrap gap-4">
          <div class="d-flex align-center flex-wrap gap-3">
            <span class="text-body-2 text-medium-emphasis">
              Showing <span id="showingRange">{{ participantShowingRange }}</span> of
              <span id="totalRecords">{{ participantPagination.recordsTotal }}</span> staff
            </span>
            <v-pagination
              v-if="participantPagination.totalPages > 1"
              v-model="participantPage"
              :length="participantPagination.totalPages"
              :total-visible="5"
              color="primary"
              density="compact"
              rounded="lg"
            />
          </div>
          <v-spacer />
          <div class="d-flex flex-wrap gap-3">
            <div class="mx-stat-chip">
              <div class="text-h6 font-weight-bold text-success" id="totalStaff">{{ participantSummary.total_staff }}</div>
              <div class="text-caption text-medium-emphasis">Total Staff</div>
            </div>
            <div class="mx-stat-chip">
              <div class="text-h6 font-weight-bold text-primary" id="totalDivisionDays">{{ participantSummary.total_division_days }}</div>
              <div class="text-caption text-medium-emphasis">Division Days</div>
            </div>
            <div class="mx-stat-chip">
              <div class="text-h6 font-weight-bold text-error" id="overLimitCount">{{ participantSummary.over_limit_count }}</div>
              <div class="text-caption text-medium-emphasis">Over Limit</div>
            </div>
          </div>
        </v-card-actions>
      </v-card>
    </Teleport>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="5000" location="top">
      {{ snackbar.text }}
    </v-snackbar>
  </v-container>
</v-app>
            `,
        });

        app.use(vuetify);
        app.mount(mountEl);
        window.ApmVuetifyPage.register(MOUNT_ID, app);
    }

    window.ApmVuetifyPage.bind(MOUNT_ID, bootMatrixShow);
})();
