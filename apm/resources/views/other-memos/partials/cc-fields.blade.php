@php
    use App\Support\OtherMemoCc;
    $staffOptions = $staffOptions ?? collect();
    $ccConfig = is_array($ccConfig ?? null) ? $ccConfig : [];
    $ccInclude = (bool) old('cc_include', OtherMemoCc::hasCcForPdf($ccConfig));
    $ccMode = old('cc_mode', ($ccConfig['mode'] ?? '') === 'specific' ? 'specific' : 'all');
    if (! in_array($ccMode, ['all', 'specific'], true)) {
        $ccMode = 'all';
    }
    $ccStaffIds = old('cc_staff_ids', []);
    if (! is_array($ccStaffIds)) {
        $ccStaffIds = [];
    }
    if ($ccStaffIds === [] && ($ccConfig['mode'] ?? '') === 'specific' && is_array($ccConfig['staff'] ?? null)) {
        $ccStaffIds = collect($ccConfig['staff'])->pluck('staff_id')->map(fn ($v) => (int) $v)->all();
    }
    $ccStaffIdSet = array_flip(array_map('intval', $ccStaffIds));
    $ccHeading = old('cc_all_staff_heading', $ccConfig['all_staff_heading'] ?? '');
    $ccLabel = old('cc_all_staff_label', $ccConfig['all_staff_label'] ?? 'All Africa CDC Staff');
@endphp
<div class="card border mb-4 d-none" id="memo-cc-card">
    <div class="card-header bg-light border-bottom py-2">
        <span class="fw-semibold text-success"><i class="bx bx-copy-alt me-1"></i> CC (carbon copy)</span>
    </div>
    <div class="card-body">
        <p class="small text-muted mb-3">Optional. Shown on the printed memo after the body. You choose who receives a copy for this memo.</p>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="cc_include" id="cc_include" value="1" @checked($ccInclude)>
            <label class="form-check-label fw-semibold" for="cc_include">Include CC on this memo</label>
        </div>
        <div id="memo-cc-options-wrap" @class(['d-none' => ! $ccInclude])>
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="cc_mode" id="cc_mode_all" value="all" @checked($ccMode === 'all')>
                    <label class="form-check-label" for="cc_mode_all">All staff</label>
                </div>
                <div id="memo-cc-all-fields" class="ms-4 mt-2 @if ($ccMode !== 'all') d-none @endif">
                    <label class="form-label small" for="cc_all_staff_heading">Optional line (e.g. role or office)</label>
                    <input type="text" class="form-control form-control-sm mb-2" name="cc_all_staff_heading" id="cc_all_staff_heading"
                        maxlength="500" value="{{ $ccHeading }}" placeholder="e.g. Principal Advisor to the DG on Management and Operations">
                    <label class="form-label small" for="cc_all_staff_label">Audience label</label>
                    <input type="text" class="form-control form-control-sm" name="cc_all_staff_label" id="cc_all_staff_label"
                        maxlength="255" value="{{ $ccLabel }}" placeholder="All Africa CDC Staff">
                </div>
            </div>
            <div class="mb-0">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="cc_mode" id="cc_mode_specific" value="specific" @checked($ccMode === 'specific')>
                    <label class="form-check-label" for="cc_mode_specific">Specific staff</label>
                </div>
                <div id="memo-cc-specific-wrap" class="ms-4 mt-2 @if ($ccMode !== 'specific') d-none @endif">
                    <label class="form-label small fw-semibold" for="cc_staff_ids">Select staff to CC</label>
                    <select name="cc_staff_ids[]" id="cc_staff_ids" class="form-select other-memo-cc-staff w-100" multiple
                        data-placeholder="Search and select staff…" style="width: 100%;">
                        @foreach ($staffOptions as $st)
                            @php
                                $sid = (int) $st->staff_id;
                                $optLabel = trim(($st->title ? $st->title . ' ' : '') . $st->fname . ' ' . $st->lname);
                                $jobName = html_entity_decode(trim((string) ($st->job_name ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            @endphp
                            <option value="{{ $sid }}" @selected(isset($ccStaffIdSet[$sid]))>{{ $optLabel }}@if ($jobName !== '') — {{ $jobName }}@endif</option>
                        @endforeach
                    </select>
                    <p class="small text-muted mt-2 mb-0">Each person is listed on the PDF as <em>Name (job title)</em>, one per line under Cc:.</p>
                </div>
            </div>
        </div>
    </div>
</div>
