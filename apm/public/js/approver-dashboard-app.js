/**
 * Approver Dashboard — Vue 3 + Vuetify 3
 * @see https://vuetifyjs.com/en/components/data-tables/
 */
(function () {
    'use strict';

    const MOUNT_ID = 'approver-dashboard-app';
    const CACHE_TTL_MS = 5 * 60 * 1000;
    const CACHE_KEY_PREFIX = 'approverDashboard_';

    const WF_ROLE_HINTS = {
        fund_type: { icon: 'mdi-cash-multiple', color: 'warning', title: 'Fund type: this level may only run for certain fund types (others can skip it).' },
        funder: { icon: 'mdi-hand-coin', color: 'info', title: 'Funder filter: limited to configured funders.' },
        division: { icon: 'mdi-office-building', color: 'primary', title: 'Division-specific: actor resolved from division roles.' },
        division_field: { icon: 'mdi-badge-account', color: 'primary', title: 'Uses a division field (e.g. head, focal person) to pick the approver.' },
        category: { icon: 'mdi-tag-multiple', color: 'secondary', title: 'Document category / memo rules apply to this step.' },
        category_gate: { icon: 'mdi-source-branch', color: 'success', title: 'Conditional branch: may be skipped when category checks do not apply.' },
        division_scope: { icon: 'mdi-map-marker', color: 'error', title: 'Limited to selected division(s).' },
    };

    const SORT_KEY_TO_COLUMN = {
        avg_last_5_hours: 2,
        avg_approval_time_hours: 3,
        total_pending: 4,
        total_handled: 5,
        last_approval_date: 8,
    };

    const AVATAR_COLORS = ['#119a48', '#1bb85a', '#0d7a3a', '#9f2240', '#c44569', '#2c3e50'];

    const PENDING_CATEGORIES = [
        { key: 'matrix', label: 'Matrix', category: 'Matrix' },
        { key: 'non_travel', label: 'Non-Travel', category: 'Non-Travel Memo' },
        { key: 'single_memos', label: 'Single', category: 'Single Memo' },
        { key: 'special', label: 'Special', category: 'Special Memo' },
        { key: 'other_memo', label: 'Other memo', category: 'Other Memo' },
        { key: 'arf', label: 'ARF', category: 'ARF' },
        { key: 'requests_for_service', label: 'Requests', category: 'Service Request' },
        { key: 'change_requests', label: 'Change', category: 'Change Request' },
    ];

    let appInstance = null;

    function staffPortraitBasename(photo) {
        if (photo == null || typeof photo !== 'string') return '';
        const t = photo.trim().replace(/\\/g, '/');
        if (!t) return '';
        const i = t.lastIndexOf('/');
        return i >= 0 ? t.slice(i + 1) : t;
    }

    function buildTableCacheKey(params) {
        const key = {
            page: params.page,
            per_page: params.per_page,
            order: params.order,
            q: params.q,
            division_id: params.division_id,
            doc_type: params.doc_type,
            approval_level: params.approval_level,
            month: params.month,
            year: params.year,
        };
        return CACHE_KEY_PREFIX + JSON.stringify(key);
    }

    function getCachedTableResponse(cacheKey) {
        try {
            const raw = sessionStorage.getItem(cacheKey);
            if (!raw) return null;
            const parsed = JSON.parse(raw);
            if (!parsed || !parsed.json || typeof parsed.cachedAt !== 'number') return null;
            return parsed;
        } catch (e) {
            return null;
        }
    }

    function setCachedTableResponse(cacheKey, json) {
        try {
            sessionStorage.setItem(cacheKey, JSON.stringify({ json, cachedAt: Date.now() }));
        } catch (e) {}
    }

    function bootApproverDashboard(mountEl, cfg) {
        if (!mountEl || !cfg) {
            window.ApmVuetifyPage.destroy(MOUNT_ID);
            appInstance = null;
            window.approverDashboardRefresh = null;
            return;
        }

        window.ApmVuetifyPage.destroy(MOUNT_ID);
        appInstance = null;
        mountEl.innerHTML = '';

        const { createApp, ref, computed, watch, onMounted, nextTick } = Vue;
        const { createVuetify } = Vuetify;

        const vuetify = createVuetify({
            theme: {
                defaultTheme: 'apmLight',
                themes: {
                    apmLight: {
                        dark: false,
                        colors: {
                            primary: '#119a48',
                            secondary: '#64748b',
                            success: '#119a48',
                            error: '#dc3545',
                            info: '#0ea5e9',
                            warning: '#f59e0b',
                            surface: '#ffffff',
                            background: '#f8fafc',
                        },
                    },
                },
            },
            defaults: {
                VCard: { rounded: 'lg', elevation: 2 },
                VBtn: { rounded: 'lg' },
                VTextField: { variant: 'outlined', density: 'comfortable' },
                VSelect: { variant: 'outlined', density: 'comfortable' },
            },
        });

        appInstance = createApp({
            setup() {
                const search = ref('');
                const filters = ref({
                    division_id: '',
                    doc_type: '',
                    approval_level: '',
                    month: '',
                    year: String(new Date().getFullYear()),
                });
                const filterOptions = ref({
                    divisions: [],
                    document_types: [],
                    approval_levels: [],
                    years: [],
                });

                const page = ref(1);
                const itemsPerPage = ref(25);
                const sortBy = ref([{ key: 'avg_last_5_hours', order: 'desc' }]);
                const items = ref([]);
                const totalItems = ref(0);
                const tableLoading = ref(false);
                const skipCacheNext = ref(false);
                const lastUpdated = ref('—');

                const summary = ref({
                    overall_avg_display: '—',
                    total_submitted: 0,
                    total_approved: 0,
                    total_pending: 0,
                });

                const workflowStats = ref([]);
                const workflowLoading = ref(false);
                const chartUnit = ref('days');
                const chartRef = ref(null);
                let chartInstance = null;

                const trendGranularity = ref('monthly');
                const trendPoints = ref([]);
                const trendLoading = ref(false);
                const trendChartRef = ref(null);
                let trendChartInstance = null;

                const snackbar = ref({ show: false, text: '', color: 'success' });

                const divisionItems = computed(() => [
                    { title: 'All divisions', value: '' },
                    ...(filterOptions.value.divisions || []).map((d) => ({
                        title: d.division_name,
                        value: String(d.id),
                    })),
                ]);

                const docTypeItems = computed(() => [
                    { title: 'All types', value: '' },
                    ...(filterOptions.value.document_types || []).map((t) => ({
                        title: t.label,
                        value: t.value,
                    })),
                ]);

                const approvalLevelItems = computed(() => [
                    { title: 'All levels', value: '' },
                    ...(filterOptions.value.approval_levels || []).map((l) => ({
                        title: l.label,
                        value: String(l.value),
                    })),
                ]);

                const yearItems = computed(() => {
                    const years = filterOptions.value.years || [];
                    const list = [{ title: 'All years', value: '' }];
                    const source = years.length ? years : Array.from({ length: 8 }, (_, i) => new Date().getFullYear() - 5 + i);
                    source.forEach((y) => list.push({ title: String(y), value: String(y) }));
                    return list;
                });

                const tableHeaders = [
                    { title: '#', key: 'row_num', sortable: false, width: 48 },
                    { title: 'Approver', key: 'approver_name', sortable: false, minWidth: 200 },
                    { title: 'Avg. last 5 docs', key: 'avg_last_5_hours', sortable: true },
                    { title: 'Avg. all docs', key: 'avg_approval_time_hours', sortable: true },
                    { title: 'Total pending', key: 'total_pending', sortable: true, align: 'center' },
                    { title: 'Total handled', key: 'total_handled', sortable: true, align: 'center' },
                    { title: 'Pending items', key: 'pending_items', sortable: false, minWidth: 180 },
                    { title: 'Role', key: 'roles_display', sortable: false, minWidth: 200 },
                    { title: 'Last approval', key: 'last_approval_date', sortable: true },
                ];

                const summaryKpis = computed(() => [
                    {
                        key: 'avg',
                        icon: 'mdi-timer-sand',
                        iconColor: 'success',
                        value: summary.value.overall_avg_display || '—',
                        label: 'Average time',
                        subtext: 'Weighted across workflows',
                        valueClass: 'text-success',
                    },
                    {
                        key: 'submitted',
                        icon: 'mdi-send',
                        iconColor: 'info',
                        value: summary.value.total_submitted ?? 0,
                        label: 'Submitted',
                        subtext: 'Pending + approved in scope',
                    },
                    {
                        key: 'approved',
                        icon: 'mdi-check-circle',
                        iconColor: 'success',
                        value: summary.value.total_approved ?? 0,
                        label: 'Approved',
                        subtext: 'Completed in selected period',
                        valueClass: 'text-success',
                    },
                    {
                        key: 'pending',
                        icon: 'mdi-clock-outline',
                        iconColor: 'warning',
                        value: summary.value.total_pending ?? 0,
                        label: 'Pending',
                        subtext: 'Currently at approval level',
                        valueClass: (summary.value.total_pending ?? 0) > 0 ? 'text-warning' : '',
                    },
                ]);

                const workflowHeaders = [
                    { title: 'Workflow', key: 'workflow_name', sortable: false },
                    { title: 'Approver flow', key: 'pipeline', sortable: false, minWidth: 220 },
                    { title: 'Approved', key: 'memos', sortable: false, align: 'end' },
                    { title: 'Avg. time', key: 'avg_display', sortable: false, align: 'end' },
                ];

                const workflowOverall = computed(() => {
                    let totalApproved = 0;
                    let totalHours = 0;
                    (workflowStats.value || []).forEach((row) => {
                        const count = Number(row.memos || 0);
                        const avgHours = Number(row.avg_hours || 0);
                        if (count > 0 && avgHours > 0) {
                            totalApproved += count;
                            totalHours += count * avgHours;
                        }
                    });
                    let avgLabel = 'No data';
                    if (totalApproved > 0 && totalHours > 0) {
                        const avgHoursAll = totalHours / totalApproved;
                        if (chartUnit.value === 'hours') {
                            avgLabel = `${Math.round(avgHoursAll * 10) / 10} hrs`;
                        } else {
                            const avgDaysAll = avgHoursAll / 24;
                            const rounded = avgDaysAll >= 10 ? Math.round(avgDaysAll * 10) / 10 : Math.round(avgDaysAll * 100) / 100;
                            avgLabel = `${rounded} days`;
                        }
                    }
                    return { count: totalApproved, avg: avgLabel };
                });

                function notify(text, color = 'success') {
                    snackbar.value = { show: true, text, color };
                }

                function setLastUpdated(ts) {
                    const d = new Date(typeof ts === 'number' ? ts : Date.now());
                    lastUpdated.value = d.toLocaleString(undefined, {
                        month: 'short', day: 'numeric', year: 'numeric',
                        hour: '2-digit', minute: '2-digit', second: '2-digit',
                    });
                }

                function pendingFilterParams() {
                    const y = filters.value.year;
                    const m = filters.value.month;
                    return (y ? `&year=${encodeURIComponent(y)}` : '') + (m ? `&month=${encodeURIComponent(m)}` : '');
                }

                function buildApiParams() {
                    const params = new URLSearchParams();
                    if (search.value.trim()) params.set('q', search.value.trim());
                    if (filters.value.division_id) params.set('division_id', filters.value.division_id);
                    if (filters.value.doc_type) params.set('doc_type', filters.value.doc_type);
                    if (filters.value.approval_level) params.set('approval_level', filters.value.approval_level);
                    if (filters.value.month) params.set('month', String(filters.value.month));
                    if (filters.value.year) params.set('year', String(filters.value.year));
                    params.set('page', String(page.value));
                    params.set('per_page', String(itemsPerPage.value));
                    if (sortBy.value.length) {
                        const col = SORT_KEY_TO_COLUMN[sortBy.value[0].key];
                        if (col != null) {
                            params.set('order', JSON.stringify([{ column: col, dir: sortBy.value[0].order }]));
                        }
                    }
                    return params;
                }

                function applySummaryFromResponse(json) {
                    const sc = json?.summary_cards || {};
                    summary.value = {
                        overall_avg_display: sc.overall_avg_display ?? '—',
                        total_submitted: sc.total_submitted ?? sc.total_approval_requests ?? 0,
                        total_approved: sc.total_approved ?? 0,
                        total_pending: sc.total_pending ?? 0,
                    };
                }

                function mapTableRows(data) {
                    return (data || []).map((row) => {
                        const firstName = row.fname || (row.approver_name || '').split(' ')[0] || 'U';
                        const lastName = row.lname || (row.approver_name || '').split(' ')[1] || '';
                        const initials = (firstName[0] + (lastName ? lastName[0] : '')).toUpperCase();
                        const colorIndex = (firstName.charCodeAt(0) - 65) % AVATAR_COLORS.length;
                        const photoFile = staffPortraitBasename(row.photo);
                        const roles = row.roles && row.roles.length ? row.roles : (row.role ? [row.role] : []);
                        return {
                            ...row,
                            initials,
                            avatar_color: AVATAR_COLORS[colorIndex >= 0 ? colorIndex : 0],
                            photo_url: photoFile ? `${cfg.routes.staffPhoto}?f=${encodeURIComponent(photoFile)}` : null,
                            roles_list: roles,
                            roles_display: roles.join(', ') || row.role || 'N/A',
                        };
                    });
                }

                async function fetchTableFromApi(params) {
                    const res = await fetch(`${cfg.routes.api}?${params.toString()}`, {
                        headers: { Accept: 'application/json' },
                    });
                    return res.json();
                }

                async function loadTable() {
                    tableLoading.value = true;
                    try {
                        const params = buildApiParams();
                        const cacheKey = buildTableCacheKey(Object.fromEntries(params));
                        const now = Date.now();
                        let useSkip = skipCacheNext.value;
                        skipCacheNext.value = false;

                        if (!useSkip) {
                            const cached = getCachedTableResponse(cacheKey);
                            if (cached && (now - cached.cachedAt) < CACHE_TTL_MS) {
                                setLastUpdated(cached.cachedAt);
                                if (cached.json?.success) {
                                    applySummaryFromResponse(cached.json);
                                    items.value = mapTableRows(cached.json.data);
                                    totalItems.value = Number(cached.json.recordsTotal || 0);
                                }
                                fetchTableFromApi(params).then((json) => {
                                    if (json?.success) {
                                        setCachedTableResponse(cacheKey, json);
                                        setLastUpdated(Date.now());
                                        applySummaryFromResponse(json);
                                        items.value = mapTableRows(json.data);
                                        totalItems.value = Number(json.recordsTotal || 0);
                                    }
                                }).catch(() => {});
                                return;
                            }
                        }

                        const json = await fetchTableFromApi(params);
                        if (json?.success) {
                            setCachedTableResponse(cacheKey, json);
                            setLastUpdated(Date.now());
                            applySummaryFromResponse(json);
                            items.value = mapTableRows(json.data);
                            totalItems.value = Number(json.recordsTotal || 0);
                        } else {
                            items.value = [];
                            totalItems.value = 0;
                            notify(json?.message || 'Could not load approver data.', 'error');
                        }
                    } catch (e) {
                        items.value = [];
                        totalItems.value = 0;
                        notify('Could not load approver data.', 'error');
                    } finally {
                        tableLoading.value = false;
                    }
                }

                async function loadFilterOptions() {
                    try {
                        const res = await fetch(cfg.routes.filterOptions, { headers: { Accept: 'application/json' } });
                        const json = await res.json();
                        if (json?.success && json.data) {
                            filterOptions.value = json.data;
                            if (!cfg.hasPermission88 && cfg.userDivisionId) {
                                filters.value.division_id = String(cfg.userDivisionId);
                            }
                        }
                    } catch (e) {
                        notify('Could not load filter options.', 'error');
                    }
                }

                async function loadWorkflowStats() {
                    workflowLoading.value = true;
                    try {
                        const params = new URLSearchParams();
                        if (filters.value.division_id) params.set('division_id', filters.value.division_id);
                        if (filters.value.doc_type) params.set('doc_type', filters.value.doc_type);
                        if (filters.value.month) params.set('month', String(filters.value.month));
                        if (filters.value.year) params.set('year', String(filters.value.year));
                        const res = await fetch(`${cfg.routes.workflowStats}?${params.toString()}`, {
                            headers: { Accept: 'application/json' },
                        });
                        const json = await res.json();
                        workflowStats.value = json?.success && Array.isArray(json.data) ? json.data : [];
                    } catch (e) {
                        workflowStats.value = [];
                    } finally {
                        workflowLoading.value = false;
                        nextTick(() => drawWorkflowChart());
                    }
                }

                function buildTimingTrendParams() {
                    const params = new URLSearchParams();
                    if (filters.value.division_id) params.set('division_id', filters.value.division_id);
                    if (filters.value.doc_type) params.set('doc_type', filters.value.doc_type);
                    if (filters.value.month) params.set('month', String(filters.value.month));
                    if (filters.value.year) params.set('year', String(filters.value.year));
                    params.set('granularity', trendGranularity.value);
                    return params;
                }

                async function loadTimingTrend() {
                    if (!cfg.routes.timingTrend) return;
                    trendLoading.value = true;
                    try {
                        const res = await fetch(`${cfg.routes.timingTrend}?${buildTimingTrendParams().toString()}`, {
                            headers: { Accept: 'application/json' },
                        });
                        const json = await res.json();
                        trendPoints.value = json?.success && Array.isArray(json.data) ? json.data : [];
                    } catch (e) {
                        trendPoints.value = [];
                    } finally {
                        trendLoading.value = false;
                        nextTick(() => drawTimingTrendChart());
                    }
                }

                function destroyTrendChart() {
                    if (trendChartInstance) {
                        try { trendChartInstance.destroy(); } catch (e) {}
                        trendChartInstance = null;
                    }
                }

                function currentPeriodPlotBand(currentIdx, gran) {
                    if (currentIdx < 0) return [];
                    const label = gran === 'weekly' ? 'This week' : 'This month';
                    return [{
                        from: currentIdx - 0.5,
                        to: currentIdx + 0.5,
                        color: 'rgba(2, 132, 199, 0.1)',
                        label: {
                            text: label,
                            style: { color: '#0369a1', fontWeight: '600', fontSize: '10px' },
                            align: 'center',
                            verticalAlign: 'top',
                            y: -2,
                        },
                    }];
                }

                function drawTimingTrendChart(attempt = 0) {
                    const el = trendChartRef.value;
                    if (!el || typeof Highcharts === 'undefined') {
                        if (attempt < 25) setTimeout(() => drawTimingTrendChart(attempt + 1), 100);
                        return;
                    }
                    destroyTrendChart();
                    const points = trendPoints.value || [];
                    if (!points.length) {
                        el.innerHTML = '<p class="text-medium-emphasis text-center py-8 mb-0">No approval timing trend data for the current filters.</p>';
                        return;
                    }
                    el.innerHTML = '';
                    const gran = trendGranularity.value;
                    const currentIdx = points.findIndex((p) => p.is_current === true);
                    const categories = points.map((p) => {
                        if (p.is_current) {
                            return `${p.label} (now)`;
                        }
                        return p.label;
                    });
                    const seriesData = points.map((p, idx) => ({
                        y: p.avg_hours != null && p.avg_hours !== '' ? Number(p.avg_hours) : null,
                        count: Number(p.count) || 0,
                        isCurrent: p.is_current === true,
                        periodKey: p.period_key,
                        pointIndex: idx,
                        marker: p.is_current ? {
                            radius: 7,
                            fillColor: '#0284c7',
                            lineWidth: 2,
                            lineColor: '#ffffff',
                            symbol: 'circle',
                        } : undefined,
                    }));
                    const maxVal = seriesData.filter((d) => d.y != null).length
                        ? Math.max(...seriesData.filter((d) => d.y != null).map((d) => d.y))
                        : 0;
                    const yMax = maxVal > 0 ? Math.ceil(maxVal * 1.15 * 10) / 10 : 10;
                    const granLabel = gran === 'weekly' ? 'Weekly' : 'Monthly';
                    const periodHint = gran === 'weekly' ? 'week' : 'month';
                    try {
                        trendChartInstance = Highcharts.chart(el, {
                            chart: { type: 'line', height: 320 },
                            title: { text: `${granLabel} average hours trend (all approvers)` },
                            subtitle: {
                                text: `Average hours per approval action. Click a ${periodHint} to view approvals for that period. Blue highlight marks the current ${periodHint}.`,
                            },
                            xAxis: {
                                categories,
                                crosshair: true,
                                plotBands: currentPeriodPlotBand(currentIdx, gran),
                                labels: {
                                    rotation: -35,
                                    style: { fontSize: '11px' },
                                    formatter() {
                                        const isCurrent = this.pos === currentIdx;
                                        return isCurrent
                                            ? `<span style="font-weight:700;color:#0369a1">${this.value}</span>`
                                            : this.value;
                                    },
                                    useHTML: true,
                                },
                            },
                            yAxis: { min: 0, max: yMax, title: { text: 'Average hours' } },
                            tooltip: {
                                pointFormatter() {
                                    const currentNote = this.isCurrent ? '<br/><span style="color:#0369a1">Current ' + periodHint + '</span>' : '';
                                    const avg = this.y != null ? `${this.y} hrs` : 'No actions yet';
                                    return `Avg: <b>${avg}</b><br/>Actions: <b>${this.count}</b>${currentNote}<br/><span style="color:#64748b">Click to view approvals</span>`;
                                },
                            },
                            plotOptions: {
                                line: {
                                    color: '#119a48',
                                    lineWidth: 2,
                                    marker: { radius: 4, fillColor: '#119a48' },
                                    connectNulls: false,
                                    cursor: 'pointer',
                                    point: {
                                        events: {
                                            click() {
                                                if (typeof this.pointIndex === 'number') {
                                                    openApprovalsForTrendPeriod(this.pointIndex);
                                                }
                                            },
                                        },
                                    },
                                },
                            },
                            series: [{
                                name: 'Avg. hours',
                                data: seriesData,
                            }],
                            credits: { enabled: false },
                        });
                    } catch (err) {
                        console.warn('Timing trend chart render failed:', err);
                    }
                }

                function formatWorkflowAvg(row) {
                    if (chartUnit.value === 'hours') {
                        return row.avg_display || 'No data';
                    }
                    const d = row.avg_days != null ? Number(row.avg_days) : Number(row.avg_hours || 0) / 24;
                    if (!isFinite(d) || d <= 0) return 'No data';
                    const rounded = d >= 10 ? Math.round(d * 10) / 10 : Math.round(d * 100) / 100;
                    return `${rounded} days`;
                }

                function workflowChartRows() {
                    return (workflowStats.value || []).filter((row) => {
                        const count = Number(row.memos || 0);
                        const avgHours = Number(row.avg_hours || 0);
                        return row.has_timing_data === true || (count > 0 && avgHours > 0);
                    });
                }

                function destroyChart() {
                    if (chartInstance) {
                        try { chartInstance.destroy(); } catch (e) {}
                        chartInstance = null;
                    }
                }

                function drawWorkflowChart(attempt = 0) {
                    const el = chartRef.value;
                    if (!el || typeof Highcharts === 'undefined') {
                        if (attempt < 25) setTimeout(() => drawWorkflowChart(attempt + 1), 100);
                        return;
                    }
                    destroyChart();
                    const chartRows = workflowChartRows();
                    if (!chartRows.length) {
                        el.innerHTML = '<p class="text-medium-emphasis text-center py-8 mb-0">No approved workflow timing data for the current filters.</p>';
                        return;
                    }
                    el.innerHTML = '';
                    const unit = chartUnit.value;
                    const categories = chartRows.map((s) => s.workflow_name || 'Unknown');
                    const seriesData = chartRows.map((s) => {
                        if (unit === 'hours') return Math.round((Number(s.avg_hours) || 0) * 10) / 10;
                        const days = s.avg_days != null ? Number(s.avg_days) : (Number(s.avg_hours) || 0) / 24;
                        return Math.round(days * 1000) / 1000;
                    });
                    const maxVal = seriesData.length ? Math.max(...seriesData) : 0;
                    const yMax = maxVal > 0 ? Math.ceil(maxVal * 1.15 * 100) / 100 : (unit === 'hours' ? 10 : 5);
                    try {
                        chartInstance = Highcharts.chart(el, {
                            chart: { type: 'column', height: 350 },
                            title: { text: 'Average Time to Last Approver (approved documents only)' },
                            subtitle: {
                                text: unit === 'hours'
                                    ? 'Time from submission to final approval, in hours.'
                                    : 'Time from submission to final approval, in days.',
                            },
                            xAxis: { categories, crosshair: true, labels: { rotation: -35, style: { fontSize: '11px' } } },
                            yAxis: { min: 0, max: yMax, title: { text: unit === 'hours' ? 'Hours' : 'Days' } },
                            tooltip: {
                                pointFormat: unit === 'hours'
                                    ? 'Approved: {point.approved}<br/>Avg: {point.y} hrs'
                                    : 'Approved: {point.approved}<br/>Avg: {point.y} days',
                            },
                            plotOptions: {
                                column: {
                                    color: '#119a48',
                                    borderRadius: 4,
                                    dataLabels: { enabled: true, format: unit === 'hours' ? '{y} hrs' : '{y} d' },
                                },
                            },
                            series: [{
                                name: unit === 'hours' ? 'Avg. time (hours)' : 'Avg. time (days)',
                                data: chartRows.map((row, idx) => ({
                                    y: seriesData[idx],
                                    approved: Number(row.memos || 0),
                                })),
                            }],
                            credits: { enabled: false },
                        });
                    } catch (err) {
                        console.warn('Workflow chart render failed:', err);
                    }
                }

                function clearFilters() {
                    search.value = '';
                    filters.value = {
                        division_id: (!cfg.hasPermission88 && cfg.userDivisionId) ? String(cfg.userDivisionId) : '',
                        doc_type: '',
                        approval_level: '',
                        month: '',
                        year: String(new Date().getFullYear()),
                    };
                    page.value = 1;
                    loadTable();
                    loadWorkflowStats();
                    loadTimingTrend();
                }

                function refreshDashboard() {
                    skipCacheNext.value = true;
                    loadTable();
                    loadWorkflowStats();
                    loadTimingTrend();
                    notify('Dashboard refreshed.', 'success');
                }

                function exportData(format) {
                    const params = new URLSearchParams();
                    params.set('export', '1');
                    if (search.value.trim()) params.set('q', search.value.trim());
                    if (filters.value.division_id) params.set('division_id', filters.value.division_id);
                    if (filters.value.doc_type) params.set('doc_type', filters.value.doc_type);
                    if (filters.value.approval_level) params.set('approval_level', filters.value.approval_level);
                    if (filters.value.month) params.set('month', String(filters.value.month));
                    if (filters.value.year) params.set('year', String(filters.value.year));
                    if (format === 'csv') {
                        params.set('format', 'csv');
                        params.set('per_page', '10000');
                        params.set('page', '1');
                        if (sortBy.value.length) {
                            const col = SORT_KEY_TO_COLUMN[sortBy.value[0].key];
                            if (col != null) {
                                params.set('order', JSON.stringify([{ column: col, dir: sortBy.value[0].order }]));
                            }
                        }
                    }
                    window.open(`${cfg.routes.api}?${params.toString()}`, '_blank');
                }

                function timingReportUrl(staffId) {
                    const params = new URLSearchParams();
                    params.set('staff_id', String(staffId));
                    if (filters.value.year) params.set('year', filters.value.year);
                    if (filters.value.month) params.set('month', String(filters.value.month));
                    return `${cfg.routes.timingReport}?${params.toString()}`;
                }

                function buildTimingReportPeriodUrl(point, gran) {
                    const params = new URLSearchParams();
                    if (filters.value.division_id) params.set('division_id', filters.value.division_id);
                    if (filters.value.doc_type) params.set('document_type', filters.value.doc_type);
                    if (gran === 'monthly' && point?.period_key) {
                        const parts = String(point.period_key).split('-');
                        if (parts[0]) params.set('year', parts[0]);
                        if (parts[1]) params.set('month', String(parseInt(parts[1], 10)));
                    } else if (gran === 'weekly' && point?.period_key) {
                        params.set('year_week', String(point.period_key));
                    }
                    return `${cfg.routes.timingReport}?${params.toString()}`;
                }

                function openApprovalsForTrendPeriod(pointIndex) {
                    const point = trendPoints.value[pointIndex];
                    if (!point || !cfg.routes.timingReport) return;
                    const url = buildTimingReportPeriodUrl(point, trendGranularity.value);
                    if (typeof window.Livewire !== 'undefined' && typeof window.Livewire.navigate === 'function') {
                        window.Livewire.navigate(url);
                    } else {
                        window.location.href = url;
                    }
                }

                function canLinkTiming(staffId) {
                    return cfg.approverTimingReportWideAccess || Number(staffId) === Number(cfg.sessionStaffIdForTiming);
                }

                function pendingUrl(staffId, category) {
                    return `${cfg.routes.pendingApprovals}?category=${encodeURIComponent(category)}&staff_id=${staffId}${pendingFilterParams()}`;
                }

                function pipelineSteps(row) {
                    if (row.approver_roles_pipeline?.length) {
                        return row.approver_roles_pipeline;
                    }
                    if (row.approver_roles) {
                        return [{ role: String(row.approver_roles), hints: [] }];
                    }
                    return [];
                }

                let searchTimer = null;
                watch(search, () => {
                    clearTimeout(searchTimer);
                    searchTimer = setTimeout(() => {
                        page.value = 1;
                        loadTable();
                    }, 500);
                });

                watch(
                    () => ({ ...filters.value }),
                    () => {
                        page.value = 1;
                        loadTable();
                        loadWorkflowStats();
                        loadTimingTrend();
                    },
                    { deep: true }
                );

                watch(itemsPerPage, () => {
                    page.value = 1;
                    loadTable();
                });

                watch(page, () => loadTable());

                watch(sortBy, () => {
                    page.value = 1;
                    loadTable();
                }, { deep: true });

                watch(chartUnit, () => {
                    nextTick(() => drawWorkflowChart());
                });

                watch(trendGranularity, () => loadTimingTrend());

                window.approverDashboardRefresh = refreshDashboard;

                onMounted(async () => {
                    await loadFilterOptions();
                    await Promise.all([loadTable(), loadWorkflowStats(), loadTimingTrend()]);
                });

                return {
                    cfg,
                    search,
                    filters,
                    divisionItems,
                    docTypeItems,
                    approvalLevelItems,
                    yearItems,
                    months: cfg.months || [],
                    page,
                    itemsPerPage,
                    sortBy,
                    items,
                    totalItems,
                    tableLoading,
                    lastUpdated,
                    summary,
                    workflowStats,
                    workflowLoading,
                    chartUnit,
                    chartRef,
                    trendGranularity,
                    trendPoints,
                    trendLoading,
                    trendChartRef,
                    tableHeaders,
                    workflowHeaders,
                    workflowOverall,
                    summaryKpis,
                    snackbar,
                    clearFilters,
                    refreshDashboard,
                    exportData,
                    openApprovalsForTrendPeriod,
                    timingReportUrl,
                    canLinkTiming,
                    pendingUrl,
                    formatWorkflowAvg,
                    pipelineSteps,
                    WF_ROLE_HINTS,
                    PENDING_CATEGORIES,
                };
            },
            template: `
<v-app class="ad-vuetify-app">
  <v-container fluid class="pa-0">
    <v-card class="mb-4">
      <v-card-title class="d-flex flex-wrap align-center justify-space-between gap-2 py-4">
        <div>
          <div class="text-h6 font-weight-medium d-flex align-center">
            <v-icon icon="mdi-view-dashboard" color="primary" class="me-2"></v-icon>
            Approver dashboard overview
          </div>
          <div class="text-caption text-medium-emphasis mt-1">
            Last updated: <strong>{{ lastUpdated }}</strong>
          </div>
        </div>
        <v-btn color="primary" variant="tonal" prepend-icon="mdi-refresh" @click="refreshDashboard">Refresh</v-btn>
      </v-card-title>
      <v-card-text>
        <v-row dense class="mb-2">
          <v-col v-for="kpi in summaryKpis" :key="kpi.key" cols="6" lg="3">
            <v-sheet rounded="lg" class="pa-4 h-100 border" color="surface">
              <v-icon :icon="kpi.icon" :color="kpi.iconColor || 'primary'" class="mb-2"></v-icon>
              <div class="text-caption text-medium-emphasis">{{ kpi.label }}</div>
              <div class="font-weight-bold" :class="[kpi.valueClass || '', typeof kpi.value === 'number' ? 'text-h5' : 'text-h6']">{{ kpi.value }}</div>
              <div v-if="kpi.subtext" class="text-caption text-medium-emphasis">{{ kpi.subtext }}</div>
            </v-sheet>
          </v-col>
        </v-row>

        <v-expansion-panels class="mt-4" variant="accordion">
          <v-expansion-panel title="How these statistics are calculated" prepend-icon="mdi-information-outline">
            <v-expansion-panel-text>
              <ul class="text-body-2 text-medium-emphasis ps-4 mb-0">
                <li class="mb-2">Summary cards use the same division, document type, year, and month filters as the approver table.</li>
                <li class="mb-2">Average time is weighted across workflows (approved documents only).</li>
                <li>Per-approver columns use the same rules as the live approval queues.</li>
              </ul>
            </v-expansion-panel-text>
          </v-expansion-panel>
        </v-expansion-panels>
      </v-card-text>
    </v-card>

    <v-card class="mb-4">
      <v-card-title class="d-flex flex-wrap align-center justify-space-between gap-2">
        <div>
          <div class="text-subtitle-1 font-weight-medium">Average time to last approver by workflow</div>
          <div class="text-caption text-medium-emphasis">Approved documents only</div>
        </div>
        <v-btn-toggle v-model="chartUnit" mandatory density="compact" color="primary" rounded="lg">
          <v-btn value="days" size="small">Days</v-btn>
          <v-btn value="hours" size="small">Hours</v-btn>
        </v-btn-toggle>
      </v-card-title>
      <v-expansion-panels variant="accordion" class="px-4">
        <v-expansion-panel title="About skipped approval levels" density="compact">
          <v-expansion-panel-text class="text-body-2 text-medium-emphasis">
            Some approval levels may be skipped automatically depending on fund type, funder, division category, and related rules.
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>
      <v-card-text>
        <v-data-table
          :headers="workflowHeaders"
          :items="workflowStats"
          :loading="workflowLoading"
          density="comfortable"
          class="border rounded-lg mb-4"
          hide-default-footer
          :items-per-page="-1"
        >
          <template #item.workflow_name="{ item }">
            <div class="font-weight-medium">{{ item.workflow_name || '—' }}</div>
            <div v-if="item.doc_type_labels?.length" class="text-caption text-medium-emphasis">
              {{ item.doc_type_labels.join(', ') }}
            </div>
          </template>
          <template #item.pipeline="{ item }">
            <div class="d-flex flex-wrap align-center gap-1 py-1">
              <template v-for="(step, idx) in pipelineSteps(item)" :key="idx">
                <v-icon v-if="idx > 0" icon="mdi-arrow-right" size="small" class="workflow-pipeline-arrow mx-1"></v-icon>
                <v-chip size="small" variant="outlined" class="text-wrap" style="height:auto; min-height:28px;">
                  {{ step.role }}
                  <v-icon
                    v-for="(hint, hi) in (step.hints || [])"
                    :key="hi"
                    v-show="WF_ROLE_HINTS[hint]"
                    :icon="WF_ROLE_HINTS[hint].icon"
                    :color="WF_ROLE_HINTS[hint].color"
                    size="x-small"
                    class="ms-1"
                    :title="WF_ROLE_HINTS[hint].title"
                  ></v-icon>
                </v-chip>
              </template>
              <span v-if="!pipelineSteps(item).length" class="text-medium-emphasis">—</span>
            </div>
          </template>
          <template #item.memos="{ item }">{{ item.memos ?? 0 }}</template>
          <template #item.avg_display="{ item }">{{ formatWorkflowAvg(item) }}</template>
          <template #no-data>
            <v-alert type="info" variant="tonal" class="ma-2">No workflow data for current filters.</v-alert>
          </template>
          <template #bottom>
            <v-divider></v-divider>
            <div class="d-flex justify-space-between pa-3 text-body-2 font-weight-medium">
              <span>All workflows (weighted avg)</span>
              <span>{{ workflowOverall.count }} · {{ workflowOverall.avg }}</span>
            </div>
          </template>
        </v-data-table>
        <div ref="chartRef" style="min-height:350px;"></div>
      </v-card-text>
    </v-card>

    <v-card class="mb-4">
      <v-card-title class="d-flex flex-wrap align-center justify-space-between gap-2">
        <div>
          <div class="text-subtitle-1 font-weight-medium">Average hours trend</div>
          <div class="text-caption text-medium-emphasis">All approvers — per approval action at current filters</div>
          <div v-if="cfg.currentTrendPeriods" class="text-caption mt-1 d-flex flex-wrap align-center gap-2">
            <span class="text-medium-emphasis">Tracking now:</span>
            <v-chip size="x-small" variant="tonal" color="info" prepend-icon="mdi-calendar-week">
              {{ cfg.currentTrendPeriods.weekly }}
            </v-chip>
            <v-chip size="x-small" variant="tonal" color="primary" prepend-icon="mdi-calendar-month">
              {{ cfg.currentTrendPeriods.monthly }}
            </v-chip>
          </div>
        </div>
        <v-btn-toggle v-model="trendGranularity" mandatory density="compact" color="primary" rounded="lg" :disabled="trendLoading">
          <v-btn value="monthly" size="small">Monthly</v-btn>
          <v-btn value="weekly" size="small">Weekly</v-btn>
        </v-btn-toggle>
      </v-card-title>
      <v-card-text>
        <v-progress-linear v-if="trendLoading" indeterminate color="primary" class="mb-2"></v-progress-linear>
        <div ref="trendChartRef" style="min-height:320px;"></div>
      </v-card-text>
    </v-card>

    <v-card class="mb-4">
      <v-card-title class="text-subtitle-1 font-weight-medium">
        <v-icon icon="mdi-filter-outline" class="me-2"></v-icon>Filter approvers
      </v-card-title>
      <v-card-text>
        <v-row dense>
          <v-col cols="12" md="3">
            <v-text-field v-model="search" label="Search approver" prepend-inner-icon="mdi-magnify" clearable hide-details></v-text-field>
          </v-col>
          <v-col cols="6" md="2">
            <v-select v-model="filters.division_id" :items="divisionItems" label="Division" hide-details :disabled="!cfg.hasPermission88"></v-select>
          </v-col>
          <v-col cols="6" md="2">
            <v-select v-model="filters.doc_type" :items="docTypeItems" label="Document type" hide-details></v-select>
          </v-col>
          <v-col cols="6" md="2">
            <v-select v-model="filters.approval_level" :items="approvalLevelItems" label="Approval level" hide-details></v-select>
          </v-col>
          <v-col cols="6" md="1">
            <v-select v-model="filters.month" :items="months" item-title="title" item-value="value" label="Month" hide-details></v-select>
          </v-col>
          <v-col cols="6" md="1">
            <v-select v-model="filters.year" :items="yearItems" label="Year" hide-details></v-select>
          </v-col>
          <v-col cols="6" md="1" class="d-flex align-center">
            <v-btn variant="outlined" block @click="clearFilters">Clear</v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <v-card>
      <v-card-title class="d-flex flex-wrap align-center justify-space-between gap-2">
        <span class="text-subtitle-1 font-weight-medium">
          <v-icon icon="mdi-table" color="primary" class="me-2"></v-icon>Approver table
        </span>
        <div class="d-flex gap-2">
          <v-btn color="error" variant="tonal" size="small" prepend-icon="mdi-file-pdf-box" @click="exportData('pdf')">PDF</v-btn>
          <v-btn color="success" variant="tonal" size="small" prepend-icon="mdi-file-excel" @click="exportData('csv')">Excel</v-btn>
        </div>
      </v-card-title>
      <v-data-table
        v-model:sort-by="sortBy"
        :headers="tableHeaders"
        :items="items"
        :loading="tableLoading"
        :items-per-page="itemsPerPage"
        density="comfortable"
        class="ad-approver-table"
        hide-default-footer
        must-sort
      >
        <template #headers="{ columns, getSortIcon, toggleSort }">
          <tr class="ad-table-header-row">
            <th
              v-for="column in columns"
              :key="column.key"
              class="ad-table-th"
              :class="column.align === 'center' ? 'text-center' : (column.align === 'end' ? 'text-end' : 'text-start')"
              :style="column.width ? { width: column.width + 'px', minWidth: column.width + 'px' } : null"
            >
              <span
                v-if="column.sortable"
                class="ad-table-header-sortable d-inline-flex align-center gap-1"
                @click="toggleSort(column)"
              >
                {{ column.title }}
                <v-icon :icon="getSortIcon(column)" size="x-small"></v-icon>
              </span>
              <span v-else>{{ column.title }}</span>
            </th>
          </tr>
        </template>
        <template #item.row_num="{ index }">
          <v-chip size="x-small" variant="outlined" color="secondary">{{ (page - 1) * itemsPerPage + index + 1 }}</v-chip>
        </template>
        <template #item.approver_name="{ item }">
          <div class="d-flex align-start py-1 ad-approver-col">
            <v-avatar :color="item.avatar_color" size="40" class="me-3 flex-shrink-0">
              <v-img v-if="item.photo_url" :src="item.photo_url" cover @error="item.photo_url = null"></v-img>
              <span v-else class="text-caption font-weight-bold text-white">{{ item.initials }}</span>
            </v-avatar>
            <div class="min-w-0">
              <div class="ad-table-name text-wrap">{{ item.approver_name }}</div>
              <div class="ad-table-muted text-caption text-wrap">{{ item.approver_email }}</div>
              <div class="ad-table-muted text-caption text-wrap">{{ item.division_name || 'N/A' }}</div>
            </div>
          </div>
        </template>
        <template #item.avg_last_5_hours="{ item }">
          <v-chip
            v-if="canLinkTiming(item.staff_id)"
            :href="timingReportUrl(item.staff_id)"
            tag="a"
            variant="flat"
            size="small"
            class="text-decoration-none ad-chip-link ad-chip-last5"
          >{{ item.avg_last_5_display || 'No data' }}</v-chip>
          <v-chip v-else variant="flat" size="small" class="ad-chip-link ad-chip-last5">{{ item.avg_last_5_display || 'No data' }}</v-chip>
        </template>
        <template #item.avg_approval_time_hours="{ item }">
          <v-chip
            v-if="canLinkTiming(item.staff_id)"
            :href="timingReportUrl(item.staff_id)"
            tag="a"
            variant="flat"
            size="small"
            class="text-decoration-none ad-chip-link ad-chip-info"
          >{{ item.avg_approval_time_display || 'No data' }}</v-chip>
          <v-chip v-else variant="flat" size="small" class="ad-chip-link ad-chip-info">{{ item.avg_approval_time_display || 'No data' }}</v-chip>
        </template>
        <template #item.total_pending="{ item }">
          <v-chip
            :class="['ad-chip-link', item.total_pending > 0 ? 'ad-chip-error' : 'ad-chip-success']"
            variant="flat"
            size="small"
          >{{ item.total_pending }}</v-chip>
        </template>
        <template #item.total_handled="{ item }">
          <v-chip variant="flat" size="small" class="ad-chip-link ad-chip-primary">{{ item.total_handled || 0 }}</v-chip>
        </template>
        <template #item.pending_items="{ item }">
          <div class="d-flex flex-wrap gap-1 py-1">
            <template v-for="cat in PENDING_CATEGORIES" :key="cat.key">
              <v-chip
                v-if="(item.pending_counts?.[cat.key] || 0) > 0"
                :href="pendingUrl(item.staff_id, cat.category)"
                tag="a"
                size="x-small"
                variant="flat"
                class="text-decoration-none ad-chip-pending"
              >{{ cat.label }}: {{ item.pending_counts[cat.key] }}</v-chip>
            </template>
            <v-chip v-if="!item.total_pending" size="x-small" variant="outlined" color="secondary">No pending</v-chip>
          </div>
        </template>
        <template #item.roles_display="{ item }">
          <div class="d-flex flex-wrap gap-1 ad-role-col py-1">
            <v-chip
              v-for="(role, ri) in item.roles_list"
              :key="ri"
              size="x-small"
              variant="flat"
              class="ad-chip-role"
            >{{ role }}</v-chip>
            <span v-if="!item.roles_list.length" class="ad-table-muted text-body-2">{{ item.role || 'N/A' }}</span>
          </div>
        </template>
        <template #item.last_approval_date="{ item }">
          <span v-if="item.last_approval_date_display" class="ad-table-date text-nowrap">{{ item.last_approval_date_display }}</span>
          <span v-else class="ad-table-muted">—</span>
        </template>
        <template #no-data>
          <v-alert type="info" variant="tonal" class="ma-4">No approvers match your filters.</v-alert>
        </template>
        <template #bottom>
          <div class="d-flex flex-wrap align-center justify-space-between gap-3 px-4 py-3">
            <div class="d-flex align-center gap-2">
              <span class="text-body-2 text-medium-emphasis">
                {{ totalItems ? ((page - 1) * itemsPerPage + 1) : 0 }}–{{ Math.min(page * itemsPerPage, totalItems) }} of {{ totalItems }}
              </span>
              <v-select
                v-model="itemsPerPage"
                :items="[25, 50, 100]"
                density="compact"
                hide-details
                style="max-width:100px"
              ></v-select>
            </div>
            <v-pagination
              v-model="page"
              :length="Math.max(1, Math.ceil(totalItems / itemsPerPage))"
              :total-visible="7"
              density="comfortable"
              rounded="circle"
              active-color="primary"
            ></v-pagination>
          </div>
        </template>
      </v-data-table>
    </v-card>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="3500" location="top">
      {{ snackbar.text }}
    </v-snackbar>
  </v-container>
</v-app>
            `,
        }).use(vuetify);

        window.ApmVuetifyPage.register(MOUNT_ID, appInstance);
        appInstance.mount(`#${MOUNT_ID}`);
    }

    if (window.ApmVuetifyPage) {
        window.ApmVuetifyPage.bind(MOUNT_ID, bootApproverDashboard);
    }
})();
