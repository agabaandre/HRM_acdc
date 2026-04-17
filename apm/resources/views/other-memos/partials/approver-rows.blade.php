@php
    $rows = $approvers ?? [];
    if (! is_array($rows)) {
        $rows = [];
    }
    $staffOptions = $staffOptions ?? collect();
    $roleExamples = $roleExamples ?? [];
    $otherMemoStaffJobMap = $staffOptions->mapWithKeys(function ($s) {
        $raw = trim((string) ($s->job_name ?? ''));
        $decoded = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return [(string) $s->staff_id => $decoded];
    })->all();
@endphp
@if (count($roleExamples))
    <p class="small text-muted mb-2" id="approver-role-examples">
        Example approver role labels (from workflow #1 definitions):
        @foreach ($roleExamples as $i => $r)
            @if ($i > 0)<span>, </span>@endif<span>{{ $r }}</span>
        @endforeach
    </p>
@endif
<div id="approver-rows-container" class="mb-2">
    @forelse ($rows as $idx => $row)
        <div class="row g-2 mb-2 approver-row align-items-end">
            <div class="col-md-2">
                <label class="form-label small text-muted mb-0">Step</label>
                <div class="form-control-plaintext fw-bold approver-step-num">{{ $row['sequence'] ?? $idx + 1 }}</div>
            </div>
            <div class="col-md-5">
                <label class="form-label small">Staff <span class="text-danger">*</span></label>
                <select name="approvers[{{ $idx }}][staff_id]" class="form-select approver-staff-id select2 w-100 border-success" style="width: 100%;">
                    <option value="">— Select staff —</option>
                    @foreach ($staffOptions as $st)
                        @php
                            $sid = (int) $st->staff_id;
                            $optLabel = trim(($st->title ? $st->title . ' ' : '') . $st->fname . ' ' . $st->lname);
                            $jobName = html_entity_decode(trim((string) ($st->job_name ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        @endphp
                        <option value="{{ $sid }}" data-job-name="{{ e($jobName) }}" @selected((int) old('approvers.' . $idx . '.staff_id', $row['staff_id'] ?? 0) === $sid)>{{ $optLabel }} (#{{ $sid }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small">Approver role label</label>
                <input type="text" name="approvers[{{ $idx }}][role_label]" class="form-control approver-role-label" value="{{ old('approvers.' . $idx . '.role_label', $row['role_label'] ?? '') }}" placeholder="Filled from job title when you pick staff" autocomplete="off">
            </div>
            <div class="col-md-1">
                <label class="form-label small d-block mb-0 opacity-0">.</label>
                <button type="button" class="btn btn-outline-danger btn-sm w-100 approver-remove" title="Remove">&times;</button>
            </div>
        </div>
    @empty
        <div class="text-muted small py-1" id="approver-empty-hint">
            No approver added yet. Click <strong>Add approver step</strong> to start from Step 1.
        </div>
    @endforelse
</div>
<button type="button" class="btn btn-sm btn-outline-success" id="approver-add-row"><i class="bx bx-plus"></i> Add approver step</button>
<p class="small text-muted mt-2 mb-0">Approvals run top-to-bottom in the order listed. Picking a staff member fills the role label with their job title; you can edit it afterward (for example to match one of the workflow role examples above).</p>

<template id="approver-row-template">
    <div class="row g-2 mb-2 approver-row align-items-end">
        <div class="col-md-2">
            <label class="form-label small text-muted mb-0">Step</label>
            <div class="form-control-plaintext fw-bold approver-step-num">1</div>
        </div>
        <div class="col-md-5">
            <label class="form-label small">Staff <span class="text-danger">*</span></label>
            <select name="approvers[0][staff_id]" class="form-select approver-staff-id select2 w-100 border-success" style="width: 100%;">
                <option value="">— Select staff —</option>
                @foreach ($staffOptions as $st)
                    @php
                        $sid = (int) $st->staff_id;
                        $optLabel = trim(($st->title ? $st->title . ' ' : '') . $st->fname . ' ' . $st->lname);
                        $jobName = html_entity_decode(trim((string) ($st->job_name ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    @endphp
                    <option value="{{ $sid }}" data-job-name="{{ e($jobName) }}">{{ $optLabel }} (#{{ $sid }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small">Approver role label</label>
            <input type="text" name="approvers[0][role_label]" class="form-control approver-role-label" value="" placeholder="Filled from job title when you pick staff" autocomplete="off">
        </div>
        <div class="col-md-1">
            <label class="form-label small d-block mb-0 opacity-0">.</label>
            <button type="button" class="btn btn-outline-danger btn-sm w-100 approver-remove" title="Remove">&times;</button>
        </div>
    </div>
</template>

{{-- Inline script: keep in swapped DOM; Select2 loads from layout footer — runApproversWhenSelect2Ready() waits for jQuery.fn.select2. --}}
<script>
window.otherMemoStaffJobById = @json($otherMemoStaffJobMap);
</script>
<script>
(function() {
    window.initOtherMemoStaffSelect2 = function($el) {
        if (typeof jQuery === 'undefined' || !jQuery.fn.select2 || !$el || !$el.length) return;
        if ($el.hasClass('select2-hidden-accessible')) {
            try {
                $el.select2('destroy');
            } catch (e) {}
        }
        $el.select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: '— Select staff —',
            allowClear: true
        });
    };

    /**
     * After cloning a row, Select2 leaves a hidden <select> and a duplicate container with no instance — restore a visible native select.
     */
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
        Array.prototype.forEach.call(selectEl.querySelectorAll('option'), function(o) {
            o.removeAttribute('data-select2-id');
        });
    }

    /** Undo HTML entity encoding (e.g. &amp; → &) for plain-text role field */
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
            if (selectEl.options[i].value === s) {
                return selectEl.options[i];
            }
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
        rows.forEach(function(row, idx) {
            var sn = row.querySelector('.approver-step-num');
            if (sn) sn.textContent = String(idx + 1);
            row.querySelectorAll('input[name^="approvers["], select[name^="approvers["]').forEach(function(el) {
                var m = el.name.match(/^approvers\[\d+\]\[(\w+)\]$/);
                if (m) el.name = 'approvers[' + idx + '][' + m[1] + ']';
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
        jQuery(document).on('change.otherMemoStaffRole', sel, function() {
            var self = this;
            window.setTimeout(function() {
                syncStaffRoleForRow(self);
            }, 0);
        });
        jQuery(document).on('select2:select.otherMemoStaffRole', sel, function(e) {
            syncStaffRoleForRow(this, optionFromSelect2Event(e));
        });
        jQuery(document).on('select2:clear.otherMemoStaffRole', sel, function() {
            var self = this;
            window.setTimeout(function() {
                syncStaffRoleForRow(self);
            }, 0);
        });
    }

    function bindRow(row) {
        if (row.dataset.approverBound === '1') return;
        row.dataset.approverBound = '1';
        var sel = row.querySelector('.approver-staff-id');
        if (sel) {
            sel.dataset.prevStaffIdForRole = sel.value || '';
        }
        row.querySelector('.approver-remove')?.addEventListener('click', function() {
            var c = document.querySelectorAll('#approver-rows-container .approver-row');
            if (c.length <= 1) return;
            if (typeof jQuery !== 'undefined' && sel && jQuery(sel).hasClass('select2-hidden-accessible')) {
                try {
                    jQuery(sel).select2('destroy');
                } catch (e) {}
            }
            row.remove();
            renumberApprovers();
        });
    }

    function onApproverAddClick() {
        var container = document.getElementById('approver-rows-container');
        if (!container) return;
        var first = container.querySelector('.approver-row');
        var clone;
        if (first) {
            clone = first.cloneNode(true);
        } else {
            var tpl = document.getElementById('approver-row-template');
            if (!tpl || !tpl.content || !tpl.content.firstElementChild) return;
            clone = tpl.content.firstElementChild.cloneNode(true);
        }
        clone.removeAttribute('data-approver-bound');
        clone.querySelectorAll('input.approver-role-label').forEach(function(i) { i.value = ''; });
        var sel = clone.querySelector('select.approver-staff-id');
        if (sel) {
            sel.value = '';
            delete sel.dataset.prevStaffIdForRole;
            stripStaffSelectToNative(sel);
        }
        var emptyHint = document.getElementById('approver-empty-hint');
        if (emptyHint) emptyHint.remove();
        container.appendChild(clone);
        renumberApprovers();
        if (sel && typeof window.initOtherMemoStaffSelect2 === 'function') {
            window.initOtherMemoStaffSelect2(jQuery(sel));
        }
        if (sel) {
            sel.dataset.prevStaffIdForRole = '';
        }
        bindRow(clone);
    }

    function initOtherMemoApprovers() {
        var container = document.getElementById('approver-rows-container');
        if (!container) return;

        ensureStaffRoleDelegation();

        container.querySelectorAll('.approver-row').forEach(function(row) {
            if (row.dataset.approverBound !== '1') {
                bindRow(row);
            }
        });

        if (!document.documentElement.dataset.otherMemoApproverAddDelegated) {
            document.documentElement.dataset.otherMemoApproverAddDelegated = '1';
            document.addEventListener('click', function(e) {
                if (!e.target.closest('#approver-add-row')) return;
                e.preventDefault();
                onApproverAddClick();
            });
        }

        if (typeof jQuery !== 'undefined' && typeof window.initOtherMemoStaffSelect2 === 'function') {
            jQuery(container).find('select.approver-staff-id').each(function() {
                // Normalize first-render selects too (same cleanup used for cloned rows),
                // otherwise Select2 can keep stale hidden state and show no options.
                stripStaffSelectToNative(this);
                window.initOtherMemoStaffSelect2(jQuery(this));
            });
        }
    }

    function runApproversWhenSelect2Ready() {
        if (typeof jQuery !== 'undefined' && jQuery.fn && jQuery.fn.select2) {
            initOtherMemoApprovers();
            return;
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function waitSelect2() {
                var tries = 0;
                (function poll() {
                    if (typeof jQuery !== 'undefined' && jQuery.fn && jQuery.fn.select2) {
                        initOtherMemoApprovers();
                    } else if (tries++ < 240) {
                        setTimeout(poll, 25);
                    }
                })();
            });
        } else {
            var tries = 0;
            (function poll() {
                if (typeof jQuery !== 'undefined' && jQuery.fn && jQuery.fn.select2) {
                    initOtherMemoApprovers();
                } else if (tries++ < 240) {
                    setTimeout(poll, 25);
                }
            })();
        }
    }
    window.initOtherMemoApprovers = initOtherMemoApprovers;
    runApproversWhenSelect2Ready();
})();
</script>
