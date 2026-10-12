(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    ready(function () {
        var cfg = window.ApmRbmMapConfig;
        if (!cfg || !cfg.linkUrl) return;

        var tbody = document.getElementById('rbm-map-tbody');
        var metaEl = document.getElementById('rbm-map-meta');
        var progress = document.getElementById('rbm-map-progress');
        var alertEl = document.getElementById('rbm-map-alert');
        var allowWrap = document.getElementById('rbm-map-allow-wrap');
        var allowExisting = document.getElementById('rbm-map-allow-existing');
        var saveBtn = document.getElementById('rbm-map-save-btn');
        var yearInput = document.getElementById('rbm-map-year');
        var quarterFilter = document.getElementById('rbm-map-quarter-filter');
        var searchInput = document.getElementById('rbm-map-search');
        var modalEl = document.getElementById('rbmMapModal');
        var statusEl = document.getElementById('rbm-map-status');
        var openBtn = document.getElementById('rbm-map-open-btn');
        var clearBtn = document.getElementById('rbm-map-clear-btn');

        var rows = [];
        var selectedId = cfg.praActivityId ? Number(cfg.praActivityId) : null;
        var loaded = false;

        function showAlert(msg, isError) {
            if (!alertEl) return;
            if (!msg) {
                alertEl.classList.add('d-none');
                alertEl.textContent = '';
                return;
            }
            alertEl.textContent = msg;
            alertEl.classList.remove('d-none', 'alert-warning', 'alert-danger', 'alert-success');
            alertEl.classList.add(isError ? 'alert-danger' : 'alert-warning');
        }

        function setLoading(on) {
            if (progress) progress.classList.toggle('d-none', !on);
        }

        function matchesQuarter(row, year, quarter) {
            if (!quarter) return true;
            var q = String(quarter).toUpperCase();
            var start = row.start_date || row.start || '';
            var end = row.end_date || row.end || '';
            var hay = (String(row.quarter || '') + ' ' + String(row.title || '') + ' ' + String(row.code || '')).toUpperCase();
            if (hay.indexOf(q) !== -1) return true;
            function inQ(d) {
                if (!d) return false;
                var m = Number(String(d).slice(5, 7));
                if (!m) return false;
                var qq = 'Q' + Math.ceil(m / 3);
                return qq === q && (!year || String(d).slice(0, 4) === String(year));
            }
            return inQ(start) || inQ(end);
        }

        function filteredRows() {
            var q = String((searchInput && searchInput.value) || '').trim().toLowerCase();
            var qf = String((quarterFilter && quarterFilter.value) || '').toUpperCase();
            var year = Number((yearInput && yearInput.value) || cfg.year || 0);
            return rows.filter(function (r) {
                if (qf && !matchesQuarter(r, year, qf)) return false;
                if (!q) return true;
                var blob = [
                    r.title, r.code, r.outcome_area, r.indicator_title, r.pra_activity_id
                ].join(' ').toLowerCase();
                return blob.indexOf(q) !== -1;
            });
        }

        function updateAllowVisibility() {
            var row = rows.find(function (r) { return Number(r.pra_activity_id) === Number(selectedId); });
            var needs = !!(row && row.already_exists);
            if (allowWrap) allowWrap.classList.toggle('d-none', !needs);
            if (!needs && allowExisting) allowExisting.checked = false;
            if (saveBtn) saveBtn.disabled = !selectedId;
        }

        function render() {
            if (!tbody) return;
            var list = filteredRows();
            if (!list.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-muted text-center py-4">No RBM activities match this filter.</td></tr>';
                return;
            }
            tbody.innerHTML = list.map(function (r) {
                var id = Number(r.pra_activity_id);
                var checked = selectedId && Number(selectedId) === id ? ' checked' : '';
                var existingHtml = '—';
                if (r.already_exists) {
                    var links = (r.existing || []).slice(0, 3).map(function (ex) {
                        return '<a href="' + (ex.url || '#') + '" target="_blank" class="d-block small">' +
                            (ex.label || ('#' + ex.id)) + ' (' + (ex.status || '') + ')</a>';
                    }).join('');
                    existingHtml = '<span class="badge text-bg-warning">Already exists</span>' + links;
                }
                return '<tr data-pra-id="' + id + '" style="cursor:pointer">' +
                    '<td><input type="radio" name="rbm_map_pick" value="' + id + '"' + checked + '></td>' +
                    '<td><span class="badge text-bg-primary-subtle text-primary">' +
                    (r.code || id) + '</span></td>' +
                    '<td><div>' + escapeHtml(r.title || '') + '</div></td>' +
                    '<td class="small text-muted">' + escapeHtml(r.outcome_area || '—') + '</td>' +
                    '<td>' + existingHtml + '</td>' +
                    '</tr>';
            }).join('');
        }

        function escapeHtml(s) {
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function loadActivities(refresh) {
            setLoading(true);
            showAlert('');
            var year = Number((yearInput && yearInput.value) || cfg.year || new Date().getFullYear());
            var params = new URLSearchParams({
                year: String(year),
                quarter: String((quarterFilter && quarterFilter.value) || cfg.quarter || ''),
            });
            if (cfg.divisionId) params.set('division_id', String(cfg.divisionId));
            if (refresh) params.set('refresh', '1');

            return fetch(cfg.activitiesUrl + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then(function (res) { return res.json().then(function (json) { return { res: res, json: json }; }); })
                .then(function (_ref) {
                    var json = _ref.json;
                    if (!_ref.res.ok || !json.success) {
                        throw new Error(json.message || 'Could not load RBM activities.');
                    }
                    rows = json.data && json.data.activities ? json.data.activities : [];
                    loaded = true;
                    var meta = json.data && json.data.meta ? json.data.meta : {};
                    if (metaEl) {
                        metaEl.textContent =
                            rows.length + ' loaded' +
                            (meta.existing_count ? (' · ' + meta.existing_count + ' already in APM') : '') +
                            (meta.pra_codes && meta.pra_codes.length ? (' · RBM codes: ' + meta.pra_codes.join(', ')) : '');
                    }
                    if (!rows.length) {
                        showAlert('No RBM activities found for this division/year. Check RBM division mapping or refresh.', false);
                    }
                    render();
                    updateAllowVisibility();
                })
                .catch(function (e) {
                    rows = [];
                    render();
                    showAlert(e.message || 'Could not load RBM activities.', true);
                })
                .finally(function () { setLoading(false); });
        }

        function saveMapping(opts) {
            opts = opts || {};
            var clear = !!opts.clear;
            var praId = clear ? null : selectedId;
            if (!clear && !praId) {
                showAlert('Select one RBM activity.', true);
                return;
            }
            var row = rows.find(function (r) { return Number(r.pra_activity_id) === Number(praId); });
            if (!clear && row && row.already_exists && !(allowExisting && allowExisting.checked)) {
                showAlert('This RBM activity is already linked in APM. Confirm the checkbox to continue.', true);
                if (allowWrap) allowWrap.classList.remove('d-none');
                return;
            }

            if (saveBtn) saveBtn.disabled = true;
            setLoading(true);
            showAlert('');

            fetch(cfg.linkUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': cfg.csrf || '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    document_type: cfg.documentType,
                    document_id: cfg.documentId,
                    pra_activity_id: praId,
                    clear: clear,
                    allow_existing: !!(allowExisting && allowExisting.checked),
                }),
            })
                .then(function (res) { return res.json().then(function (json) { return { res: res, json: json }; }); })
                .then(function (_ref) {
                    var res = _ref.res;
                    var json = _ref.json;
                    if (res.status === 409 && json.data && json.data.requires_confirmation) {
                        showAlert(json.message || 'Confirm to continue with an already-linked RBM activity.', false);
                        if (allowWrap) allowWrap.classList.remove('d-none');
                        return;
                    }
                    if (!res.ok || !json.success) {
                        throw new Error(json.message || 'Could not save mapping.');
                    }
                    cfg.praActivityId = json.data ? json.data.pra_activity_id : null;
                    selectedId = cfg.praActivityId;
                    if (statusEl) {
                        statusEl.innerHTML = cfg.praActivityId
                            ? ('Linked to RBM activity <strong id="rbm-map-current-id">' + cfg.praActivityId + '</strong>')
                            : 'Not linked to an RBM activity';
                    }
                    if (openBtn) {
                        openBtn.innerHTML = '<i class="bx bx-link-alt me-1"></i>' +
                            (cfg.praActivityId ? 'Change RBM mapping' : 'Map to RBM');
                    }
                    if (clearBtn) {
                        clearBtn.classList.toggle('d-none', !cfg.praActivityId);
                    } else if (cfg.praActivityId && !document.getElementById('rbm-map-clear-btn')) {
                        // banner may have been rendered without clear; ignore
                    }
                    var modal = modalEl && window.bootstrap ? window.bootstrap.Modal.getInstance(modalEl) : null;
                    if (modal) modal.hide();
                    window.location.reload();
                })
                .catch(function (e) {
                    showAlert(e.message || 'Could not save mapping.', true);
                })
                .finally(function () {
                    setLoading(false);
                    if (saveBtn) saveBtn.disabled = !selectedId;
                });
        }

        if (tbody) {
            tbody.addEventListener('click', function (e) {
                var tr = e.target.closest('tr[data-pra-id]');
                if (!tr) return;
                selectedId = Number(tr.getAttribute('data-pra-id'));
                var radio = tr.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
                updateAllowVisibility();
            });
        }

        if (searchInput) searchInput.addEventListener('input', render);
        if (quarterFilter) quarterFilter.addEventListener('change', render);
        document.getElementById('rbm-map-refresh-btn')?.addEventListener('click', function () {
            loadActivities(true);
        });
        saveBtn?.addEventListener('click', function () { saveMapping({ clear: false }); });
        clearBtn?.addEventListener('click', function () {
            if (!window.confirm('Clear the RBM mapping on this document?')) return;
            saveMapping({ clear: true });
        });

        if (modalEl) {
            modalEl.addEventListener('shown.bs.modal', function () {
                if (!loaded) loadActivities(false);
                else render();
            });
        }
    });
})();
