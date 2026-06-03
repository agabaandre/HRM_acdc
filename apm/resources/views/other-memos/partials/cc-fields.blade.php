@php
    $staffOptions = $staffOptions ?? collect();
    $ccConfig = is_array($ccConfig ?? null) ? $ccConfig : [];
    $ccAllStaff = (bool) old('cc_all_staff', ($ccConfig['mode'] ?? '') === 'all');
    $ccStaffIds = old('cc_staff_ids', []);
    if (! is_array($ccStaffIds)) {
        $ccStaffIds = [];
    }
    if ($ccStaffIds === [] && ($ccConfig['mode'] ?? '') === 'specific' && is_array($ccConfig['staff'] ?? null)) {
        $ccStaffIds = collect($ccConfig['staff'])->pluck('staff_id')->map(fn ($v) => (int) $v)->all();
    }
    $ccStaffIdSet = array_flip(array_map('intval', $ccStaffIds));
@endphp
<div class="card border mb-4 d-none" id="memo-cc-card">
    <div class="card-header bg-light border-bottom py-2">
        <span class="fw-semibold text-success"><i class="bx bx-copy-alt me-1"></i> CC on approval</span>
    </div>
    <div class="card-body">
        <p class="small text-muted mb-3">Shown on the printed memo after the body. Choose copy to all staff or pick specific recipients.</p>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="cc_all_staff" id="cc_all_staff" value="1" @checked($ccAllStaff)>
            <label class="form-check-label" for="cc_all_staff">Copy to all staff</label>
        </div>
        <div id="memo-cc-specific-wrap" @class(['d-none' => $ccAllStaff])>
            <label class="form-label small fw-semibold" for="cc_staff_ids">CC specific staff</label>
            <select name="cc_staff_ids[]" id="cc_staff_ids" class="form-select other-memo-cc-staff multiple-select w-100" multiple
                data-placeholder="Select staff to CC" style="width: 100%;">
                @foreach ($staffOptions as $st)
                    @php
                        $sid = (int) $st->staff_id;
                        $optLabel = trim(($st->title ? $st->title . ' ' : '') . $st->fname . ' ' . $st->lname);
                        $jobName = html_entity_decode(trim((string) ($st->job_name ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    @endphp
                    <option value="{{ $sid }}" @selected(isset($ccStaffIdSet[$sid]))>{{ $optLabel }}@if ($jobName !== '') — {{ $jobName }}@endif</option>
                @endforeach
            </select>
            <p class="small text-muted mt-2 mb-0">Each person appears on the PDF as <em>Name (job title)</em>, one per line under Cc:.</p>
        </div>
        <div id="memo-cc-all-staff-preview" class="small text-muted mt-2 @if (! $ccAllStaff) d-none @endif">
            <span id="memo-cc-all-staff-preview-text"></span>
        </div>
    </div>
</div>
