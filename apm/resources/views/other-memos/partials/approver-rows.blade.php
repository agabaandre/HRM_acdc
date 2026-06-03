@php
    $rows = $approvers ?? [];
    if (! is_array($rows)) {
        $rows = [];
    }
    $staffOptions = $staffOptions ?? collect();
    $roleExamples = $roleExamples ?? [];
    $sectionOptions = [
        'to' => 'To',
        'through' => 'Through',
        'from' => 'From',
    ];
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
<div id="approver-rows-container" class="mb-2" data-staff-job-map='@json($otherMemoStaffJobMap)'>
    @forelse ($rows as $idx => $row)
        @php
            $section = strtolower((string) old('approvers.' . $idx . '.memo_section', $row['memo_section'] ?? 'through'));
            if (! array_key_exists($section, $sectionOptions)) {
                $section = 'through';
            }
        @endphp
        <div class="row g-2 mb-2 approver-row align-items-end">
            <div class="col-md-1">
                <label class="form-label small text-muted mb-0">Step</label>
                <div class="form-control-plaintext fw-bold approver-step-num">{{ $row['sequence'] ?? $idx + 1 }}</div>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Section <span class="text-danger">*</span></label>
                <select name="approvers[{{ $idx }}][memo_section]" class="form-select form-select-sm approver-memo-section">
                    @foreach ($sectionOptions as $value => $label)
                        <option value="{{ $value }}" @selected($section === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
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
<p class="small text-muted mt-2 mb-0">Approvals run top-to-bottom in the order listed. Optionally assign each person to <strong>To</strong>, <strong>Through</strong>, or <strong>From</strong> for the printed header. If you leave everyone on Through, the system uses the first person as <strong>From</strong>, the last as <strong>To</strong>, and anyone in between as <strong>Through</strong>.</p>

<template id="approver-row-template">
    <div class="row g-2 mb-2 approver-row align-items-end">
        <div class="col-md-1">
            <label class="form-label small text-muted mb-0">Step</label>
            <div class="form-control-plaintext fw-bold approver-step-num">1</div>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Section <span class="text-danger">*</span></label>
            <select name="approvers[0][memo_section]" class="form-select form-select-sm approver-memo-section">
                @foreach ($sectionOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
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
{{-- Approver Select2 + add row: public/js/apm-other-memo-approvers.js (layout footer; wire:navigate safe). --}}
