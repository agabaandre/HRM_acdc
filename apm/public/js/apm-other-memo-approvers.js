/**
 * Other memo create/edit — approver rows, Select2, role labels.
 * Loaded from layout (wire:navigate does not re-run inline scripts in morphed HTML).
 */
(function () {
    'use strict';

    function otherMemoFormPagePresent() {
        return !!document.querySelector(
            '[data-apm-livewire-page="other-memos-create"], [data-apm-livewire-page="other-memos-edit"]'
        );
    }

    function refreshStaffJobMap() {
        var container = document.getElementById('approver-rows-container');
        if (!container) {
            window.otherMemoStaffJobById = {};
            return;
        }
        var raw = container.getAttribute('data-staff-job-map') || '{}';
        try {
            window.otherMemoStaffJobById = JSON.parse(raw);
        } catch (e) {
            window.otherMemoStaffJobById = {};
        }
    }

    window.initOtherMemoStaffSelect2 = function ($el) {
        if (typeof jQuery === 'undefined' || !jQuery.fn.select2 || !$el || !$el.length) return;
        if ($el.hasClass('select2-hidden-accessible')) {
            try { $el.select2('destroy'); } catch (e) {}
        }
        $el.select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: '— Select staff —',
            allowClear: true
        });
    };

    function stripStaffSelectToNative(selectEl) {
        if (!selectEl) return;
        if (typeof jQuery !== 'undefined') {
            var $s = jQuery(selectEl);
            try {
                if ($s.hasClass('select2-hidden-accessible') || $s.data('select2')) {
                    $s.select2('destroy');
                }
            } catch (e) {}
        }
        selectEl.classList.remove('select2-hidden-accessible');
        selectEl.removeAttribute('data-select2-id');
        selectEl.removeAttribute('tabindex');
        selectEl.removeAttribute('aria-hidden');
        selectEl.style.cssText = 'width: 100%;';
        var n = selectEl.nextSibling;
        while (n) {
            var nx = n.nextSibling;
            if (n.nodeType === 1 && n.classList && n.classList.contains('select2-container')) {
                n.parentNode.removeChild(n);
            }
            n = nx;
        }
        Array.prototype.forEach.call(selectEl.querySelectorAll('option'), function (o) {
            o.removeAttribute('data-select2-id');
        });
    }

    function decodeJobDisplayString(s) {
        if (s == null || s === '') return '';
        var str = String(s);
        var prev;
        do {
            prev = str;
            str = str.replace(/&amp;/gi, '&');
        } while (str !== prev);
        return str
            .replace(/&lt;/gi, '<')
            .replace(/&gt;/gi, '>')
            .replace(/&quot;/gi, '"')
            .replace(/&#0*39;/g, "'")
            .replace(/&#0*34;/g, '"');
    }

    function findOptionByValue(selectEl, val) {
        if (val === undefined || val === null || val === '') return null;
        var s = String(val);
        for (var i = 0; i < selectEl.options.length; i++) {
            if (selectEl.options[i].value === s) return selectEl.options[i];
        }
        return null;
    }

    function jobOrLabelFromOption(opt) {
        if (!opt || !opt.value) return '';
        var j = (opt.getAttribute('data-job-name') || '').trim();
        if (j) return decodeJobDisplayString(j);
        var map = window.otherMemoStaffJobById;
        if (map && typeof map === 'object') {
            var fromMap = (map[opt.value] !== undefined && map[opt.value] !== null)
                ? String(map[opt.value]).trim()
                : '';
            if (fromMap) return decodeJobDisplayString(fromMap);
        }
        var t = (opt.textContent || '').trim();
        var m = t.match(/^(.+?)\s*\(#\d+\)\s*$/);
        return decodeJobDisplayString(m ? m[1].trim() : t);
    }

    function readJobForStaffId(selectEl, staffId) {
        if (!staffId) return '';
        var o = findOptionByValue(selectEl, staffId);
        return o ? jobOrLabelFromOption(o) : '';
    }

    function renumberApprovers() {
        var rows = document.querySelectorAll('#approver-rows-container .approver-row');
        rows.forEach(function (row, idx) {
            var sn = row.querySelector('.approver-step-num');
            if (sn) sn.textContent = String(idx + 1);
            row.querySelectorAll('input[name^="approvers["], select[name^="approvers["]').forEach(function (el) {
                if (typeof el.name === 'string' && el.name.indexOf('approvers[') === 0) {
                    el.name = el.name.replace(/^approvers\[\d+\]/, 'approvers[' + idx + ']');
                }
            });
        });
    }

    function applyJobToRole(row, prevJob, explicitOption) {
        var sel = row.querySelector('.approver-staff-id');
        var roleInp = row.querySelector('.approver-role-label');
        if (!sel || !roleInp) return;
        var opt = explicitOption || findOptionByValue(sel, sel.value);
        if (!opt || !opt.value) return;
        var job = jobOrLabelFromOption(opt);
        if (job === '') return;
        var cur = roleInp.value.trim();
        var prev = (prevJob || '').trim();
        if (cur === '' || cur === prev || cur === job) {
            roleInp.value = job;
            return;
        }
        if (cur.indexOf(job) !== -1) return;
        roleInp.value = cur + ' — ' + job;
    }

    function syncStaffRoleForRow(selectEl, explicitOption) {
        var row = selectEl.closest('.approver-row');
        if (!row) return;
        var lastSid = selectEl.dataset.prevStaffIdForRole || '';
        var prevJob = readJobForStaffId(selectEl, lastSid);
        applyJobToRole(row, prevJob, explicitOption);
        selectEl.dataset.prevStaffIdForRole = selectEl.value || '';
    }

    function optionFromSelect2Event(e) {
        var d = e.params && e.params.data;
        if (!d || !d.element) return null;
        var el = d.element;
        return el.jquery ? el[0] : el;
    }

    function ensureStaffRoleDelegation() {
        if (typeof jQuery === 'undefined') return;
        if (document.documentElement.dataset.otherMemoStaffRoleDelegated === '1') return;
        document.documentElement.dataset.otherMemoStaffRoleDelegated = '1';
        var sel = '#approver-rows-container select.approver-staff-id';
        jQuery(document).on('change.otherMemoStaffRole', sel, function () {
            var self = this;
            window.setTimeout(function () { syncStaffRoleForRow(self); }, 0);
        });
        jQuery(document).on('select2:select.otherMemoStaffRole', sel, function (e) {
            syncStaffRoleForRow(this, optionFromSelect2Event(e));
            updateOtherMemoApproverPreview();
        });
        jQuery(document).on('select2:clear.otherMemoStaffRole', sel, function () {
            var self = this;
            window.setTimeout(function () { syncStaffRoleForRow(self); }, 0);
        });
    }

    function bindRow(row) {
        if (row.dataset.approverBound === '1') return;
        row.dataset.approverBound = '1';
        var sel = row.querySelector('.approver-staff-id');
        if (sel) sel.dataset.prevStaffIdForRole = sel.value || '';
        var removeBtn = row.querySelector('.approver-remove');
        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                var c = document.querySelectorAll('#approver-rows-container .approver-row');
                if (c.length <= 1) return;
                if (typeof jQuery !== 'undefined' && sel && jQuery(sel).hasClass('select2-hidden-accessible')) {
                    try { jQuery(sel).select2('destroy'); } catch (e) {}
                }
                row.remove();
                renumberApprovers();
                updateOtherMemoApproverPreview();
            });
        }
    }

    function staffLabelFromSelect(selectEl) {
        if (!selectEl || !selectEl.value) return '';
        var opt = findOptionByValue(selectEl, selectEl.value);
        if (!opt) return 'Staff #' + selectEl.value;
        var t = (opt.textContent || '').trim();
        var m = t.match(/^(.+?)\s*\(#\d+\)\s*$/);
        return m ? m[1].trim() : t;
    }

    function collectApproverRowsForPreview() {
        var rows = [];
        document.querySelectorAll('#approver-rows-container .approver-row').forEach(function (row, idx) {
            var stepEl = row.querySelector('.approver-step-num');
            var step = stepEl ? parseInt(stepEl.textContent, 10) : idx + 1;
            if (isNaN(step)) step = idx + 1;
            var sectionEl = row.querySelector('.approver-memo-section');
            var section = sectionEl ? String(sectionEl.value || 'through').toLowerCase() : 'through';
            if (['to', 'through', 'from'].indexOf(section) === -1) section = 'through';
            var staffSel = row.querySelector('.approver-staff-id');
            var staffId = staffSel ? String(staffSel.value || '') : '';
            var roleInp = row.querySelector('.approver-role-label');
            var role = roleInp ? roleInp.value.trim() : '';
            if (!role && staffSel) {
                role = readJobForStaffId(staffSel, staffId) || 'Approver';
            }
            if (!role) role = 'Approver';
            rows.push({
                step: step,
                section: section,
                staffId: staffId,
                name: staffLabelFromSelect(staffSel),
                role: role
            });
        });
        return rows.sort(function (a, b) { return a.step - b.step; });
    }

    function applyDefaultSectionsForPreview(rows) {
        if (!rows.length) return [];
        var hasTo = rows.some(function (r) { return r.section === 'to'; });
        var hasFrom = rows.some(function (r) { return r.section === 'from'; });
        if (hasTo || hasFrom) {
            return rows.map(function (r) {
                return Object.assign({}, r, { effectiveSection: r.section });
            });
        }
        var n = rows.length;
        return rows.map(function (r, i) {
            var eff = 'through';
            if (n === 1) eff = 'to';
            else if (i === 0) eff = 'from';
            else if (i === n - 1) eff = 'to';
            return Object.assign({}, r, { effectiveSection: eff });
        });
    }

    function sectionDisplayLabel(section) {
        if (section === 'to') return 'To';
        if (section === 'from') return 'From';
        return 'Through';
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function updateOtherMemoApproverPreview() {
        var host = document.getElementById('other-memo-approver-preview');
        if (!host) return;
        var raw = collectApproverRowsForPreview();
        if (!raw.length) {
            host.innerHTML = '<p class="small text-muted mb-0">Add approver steps above to see how they will appear on the printed memo and in approval order.</p>';
            return;
        }
        var withSections = applyDefaultSectionsForPreview(raw);
        var printOrder = ['to', 'through', 'from'];
        var printHtml = '';
        printOrder.forEach(function (sec) {
            var inSec = withSections.filter(function (r) { return r.effectiveSection === sec; });
            inSec.sort(function (a, b) {
                return sec === 'to' ? b.step - a.step : a.step - b.step;
            });
            if (!inSec.length) return;
            printHtml += '<div class="mb-2"><span class="badge bg-success">' + sectionDisplayLabel(sec) + '</span>';
            printHtml += '<ul class="list-unstyled mb-0 ms-2 mt-1">';
            inSec.forEach(function (r) {
                var name = r.name || (r.staffId ? 'Staff #' + r.staffId : '— not selected —');
                printHtml += '<li class="small"><strong>' + escapeHtml(name) + '</strong>';
                printHtml += ' <span class="text-muted">(' + escapeHtml(r.role) + ')</span>';
                printHtml += ' <span class="badge bg-secondary">Step ' + r.step + '</span></li>';
            });
            printHtml += '</ul></div>';
        });
        var approvalHtml = '<ol class="mb-0 ps-3 small">';
        withSections.forEach(function (r) {
            var name = r.name || (r.staffId ? 'Staff #' + r.staffId : '— select staff —');
            approvalHtml += '<li class="mb-1"><span class="badge bg-warning text-dark">Pending</span> ';
            approvalHtml += '<strong>Step ' + r.step + '</strong> → ';
            approvalHtml += escapeHtml(name) + ' <span class="text-muted">(' + escapeHtml(r.role) + ')</span>';
            approvalHtml += ' · prints as <em>' + sectionDisplayLabel(r.effectiveSection) + '</em></li>';
        });
        approvalHtml += '</ol>';
        host.innerHTML =
            '<div class="row g-3">' +
            '<div class="col-md-6"><h6 class="small fw-bold text-success mb-2">Printed header (top → bottom)</h6>' +
            '<p class="small text-muted mb-2">Matches the PDF: <strong>To</strong> first (usually highest step / final approver), then <strong>Through</strong>, then <strong>From</strong> (usually step 1).</p>' +
            printHtml + '</div>' +
            '<div class="col-md-6"><h6 class="small fw-bold text-success mb-2">Approval order (who acts when)</h6>' +
            '<p class="small text-muted mb-2">Step 1 is notified first on submit; each approval advances to the next step. Do not skip steps.</p>' +
            approvalHtml + '</div></div>';
    }

    window.updateOtherMemoApproverPreview = updateOtherMemoApproverPreview;

    function bindApproverPreviewDelegatesOnce() {
        if (document.documentElement.dataset.otherMemoApproverPreviewDelegated === '1') return;
        document.documentElement.dataset.otherMemoApproverPreviewDelegated = '1';
        document.addEventListener('change', function (e) {
            if (!otherMemoFormPagePresent()) return;
            if (!e.target) return;
            if (e.target.closest('#approver-rows-container')) {
                updateOtherMemoApproverPreview();
            }
        });
        document.addEventListener('input', function (e) {
            if (!otherMemoFormPagePresent()) return;
            if (e.target && e.target.classList && e.target.classList.contains('approver-role-label')) {
                updateOtherMemoApproverPreview();
            }
        });
    }

    function createApproverRowFromTemplate() {
        var tpl = document.getElementById('approver-row-template');
        if (!tpl || !tpl.content || !tpl.content.firstElementChild) return null;
        return tpl.content.firstElementChild.cloneNode(true);
    }

    function onApproverAddClick() {
        if (!otherMemoFormPagePresent()) return;
        var container = document.getElementById('approver-rows-container');
        if (!container) return;
        var clone = createApproverRowFromTemplate();
        if (!clone) return;
        clone.removeAttribute('data-approver-bound');
        clone.querySelectorAll('input.approver-role-label').forEach(function (i) { i.value = ''; });
        var sel = clone.querySelector('select.approver-staff-id');
        if (sel) {
            sel.value = '';
            delete sel.dataset.prevStaffIdForRole;
            stripStaffSelectToNative(sel);
        }
        var sectionSel = clone.querySelector('.approver-memo-section');
        if (sectionSel) sectionSel.value = 'through';
        var emptyHint = document.getElementById('approver-empty-hint');
        if (emptyHint) emptyHint.remove();
        container.appendChild(clone);
        renumberApprovers();
        if (sel && typeof window.initOtherMemoStaffSelect2 === 'function') {
            runWhenSelect2Ready(function () {
                window.initOtherMemoStaffSelect2(jQuery(sel));
            });
        }
        if (sel) sel.dataset.prevStaffIdForRole = '';
        bindRow(clone);
        updateOtherMemoApproverPreview();
    }

    function approversSectionVisible() {
        var card = document.getElementById('memo-approvers-card');
        if (!card) return true;
        return !card.classList.contains('d-none');
    }

    function initOtherMemoApprovers() {
        if (!otherMemoFormPagePresent()) return;
        var container = document.getElementById('approver-rows-container');
        if (!container) return;

        refreshStaffJobMap();
        ensureStaffRoleDelegation();

        container.querySelectorAll('.approver-row').forEach(function (row) {
            if (row.dataset.approverBound !== '1') bindRow(row);
        });

        if (!document.documentElement.dataset.otherMemoApproverAddDelegated) {
            document.documentElement.dataset.otherMemoApproverAddDelegated = '1';
            document.addEventListener('click', function (e) {
                if (!e.target.closest('#approver-add-row')) return;
                if (!otherMemoFormPagePresent()) return;
                e.preventDefault();
                onApproverAddClick();
            });
        }

        if (!approversSectionVisible()) return;

        bindApproverPreviewDelegatesOnce();

        if (typeof jQuery !== 'undefined' && typeof window.initOtherMemoStaffSelect2 === 'function') {
            runWhenSelect2Ready(function () {
                jQuery(container).find('select.approver-staff-id').each(function () {
                    var $s = jQuery(this);
                    if ($s.hasClass('select2-hidden-accessible') && $s.next('.select2-container').length) {
                        return;
                    }
                    stripStaffSelectToNative(this);
                    window.initOtherMemoStaffSelect2($s);
                });
                updateOtherMemoApproverPreview();
            });
        } else {
            updateOtherMemoApproverPreview();
        }
    }

    window.initOtherMemoApprovers = initOtherMemoApprovers;

    function runWhenSelect2Ready(fn) {
        var tries = 0;
        (function poll() {
            if (typeof jQuery !== 'undefined' && jQuery.fn && jQuery.fn.select2) {
                fn();
            } else if (tries++ < 240) {
                setTimeout(poll, 25);
            }
        })();
    }

    window.runOtherMemoApproversWhenSelect2Ready = runWhenSelect2Ready;

    function bootOtherMemoApprovers() {
        if (!otherMemoFormPagePresent()) return;
        if (!document.getElementById('approver-rows-container')) return;
        runWhenSelect2Ready(initOtherMemoApprovers);
    }

    function scheduleBoot() {
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                setTimeout(bootOtherMemoApprovers, 0);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', scheduleBoot);
    document.addEventListener('livewire:navigated', scheduleBoot);
})();
