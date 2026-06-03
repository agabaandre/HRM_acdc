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
<div id="approver-rows-container" class="mb-2" data-staff-job-map='@json($otherMemoStaffJobMap)'>
    @forelse ($rows as $idx => $row)
        <div class="row g-2 mb-2 approver-row align-items-end">
            <div class="col-md-2">
                <label class="form-label small text-muted mb-0">Step</label>
                <div class="form-control-plaintext fw-bold approver-step-num">{{ $row['sequence'] ?? $idx + 1 }}</div>
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
            <div class="col-md-3">
                <label class="form-label small">Approver role label</label>
                <input type="text" name="approvers[{{ $idx }}][role_label]" class="form-control approver-role-label" value="{{ old('approvers.' . $idx . '.role_label', $row['role_label'] ?? '') }}" placeholder="Filled from job title when you pick staff" autocomplete="off">
            </div>
            <div class="col-md-4">
                <label class="form-label small">Signature area (page/x/y/w/h)</label>
                <div class="row g-1">
                    <div class="col-2">
                        <input type="number" min="1" class="form-control form-control-sm" name="approvers[{{ $idx }}][signature_box][page]" value="{{ old('approvers.' . $idx . '.signature_box.page', $row['signature_box']['page'] ?? 1) }}" placeholder="P">
                    </div>
                    <div class="col-2">
                        <input type="number" min="0" class="form-control form-control-sm" name="approvers[{{ $idx }}][signature_box][x]" value="{{ old('approvers.' . $idx . '.signature_box.x', $row['signature_box']['x'] ?? '') }}" placeholder="X">
                    </div>
                    <div class="col-2">
                        <input type="number" min="0" class="form-control form-control-sm" name="approvers[{{ $idx }}][signature_box][y]" value="{{ old('approvers.' . $idx . '.signature_box.y', $row['signature_box']['y'] ?? '') }}" placeholder="Y">
                    </div>
                    <div class="col-3">
                        <input type="number" min="1" class="form-control form-control-sm" name="approvers[{{ $idx }}][signature_box][width]" value="{{ old('approvers.' . $idx . '.signature_box.width', $row['signature_box']['width'] ?? 180) }}" placeholder="W">
                    </div>
                    <div class="col-3">
                        <input type="number" min="1" class="form-control form-control-sm" name="approvers[{{ $idx }}][signature_box][height]" value="{{ old('approvers.' . $idx . '.signature_box.height', $row['signature_box']['height'] ?? 70) }}" placeholder="H">
                    </div>
                </div>
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
        <div class="col-md-3">
            <label class="form-label small">Approver role label</label>
            <input type="text" name="approvers[0][role_label]" class="form-control approver-role-label" value="" placeholder="Filled from job title when you pick staff" autocomplete="off">
        </div>
        <div class="col-md-4">
            <label class="form-label small">Signature area (page/x/y/w/h)</label>
            <div class="row g-1">
                <div class="col-2">
                    <input type="number" min="1" class="form-control form-control-sm" name="approvers[0][signature_box][page]" value="1" placeholder="P">
                </div>
                <div class="col-2">
                    <input type="number" min="0" class="form-control form-control-sm" name="approvers[0][signature_box][x]" placeholder="X">
                </div>
                <div class="col-2">
                    <input type="number" min="0" class="form-control form-control-sm" name="approvers[0][signature_box][y]" placeholder="Y">
                </div>
                <div class="col-3">
                    <input type="number" min="1" class="form-control form-control-sm" name="approvers[0][signature_box][width]" value="180" placeholder="W">
                </div>
                <div class="col-3">
                    <input type="number" min="1" class="form-control form-control-sm" name="approvers[0][signature_box][height]" value="70" placeholder="H">
                </div>
            </div>
        </div>
        <div class="col-md-1">
            <label class="form-label small d-block mb-0 opacity-0">.</label>
            <button type="button" class="btn btn-outline-danger btn-sm w-100 approver-remove" title="Remove">&times;</button>
        </div>
    </div>
</template>
{{-- Approver Select2 + add row: public/js/apm-other-memo-approvers.js (layout footer; wire:navigate safe). --}}
