@php
    $cr = $changeRequestForEdit ?? null;
    $requestCash = filter_var(old('request_travel_with_cash', $cr->request_travel_with_cash ?? false), FILTER_VALIDATE_BOOLEAN);
    $cashCarrierId = (int) old('cash_carrier_staff_id', $cr->cash_carrier_staff_id ?? 0);
    $cashReason = old('cash_bank_transfer_unavailable_reason', $cr->cash_bank_transfer_unavailable_reason ?? '');
    $crStaffList = $crStaffList ?? \App\Models\Staff::query()
        ->whereNotIn('status', ['Expired', 'Separated'])
        ->orderBy('lname')
        ->orderBy('fname')
        ->get(['staff_id', 'title', 'fname', 'lname', 'oname', 'job_name']);
@endphp

<div class="card border-0 shadow-sm mb-3 border-secondary">
    <div class="card-body py-3">
        <input type="hidden" name="request_travel_with_cash" value="0">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="request_travel_with_cash" value="1"
                id="request_travel_with_cash" @checked($requestCash)>
            <label class="form-check-label fw-semibold" for="request_travel_with_cash">
                Request to travel with cash
            </label>
        </div>
        <small class="text-muted d-block mt-1">
            Leave unchecked if travel funds will be paid by bank transfer only. When checked alone, approval follows the
            <strong>same-quarter date change</strong> workflow; combined with budget or participant changes, the
            <strong>different-quarter / participant replacement</strong> workflow applies.
        </small>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4" id="wb-change-request-details-card">
    <div class="card-header bg-warning text-dark">
        <h6 class="mb-0">
            <i class="fas fa-edit me-2"></i> Change Request Details
        </h6>
    </div>
    <div class="card-body">
        <div id="travel-cash-fields" class="mb-4 {{ $requestCash ? '' : 'd-none' }}">
            <div class="alert alert-info py-2 small mb-3">
                <strong>Cash collection:</strong> Identify who will carry the cash and explain why bank transfer is not possible.
            </div>
            <div class="mb-3">
                <label for="cash_carrier_staff_id" class="form-label fw-semibold">
                    Person carrying the cash <span class="text-danger wb-cash-required">*</span>
                </label>
                <select name="cash_carrier_staff_id" id="cash_carrier_staff_id"
                    class="form-select @error('cash_carrier_staff_id') is-invalid @enderror"
                    data-wb-cash-required="1">
                    <option value="">— Select staff —</option>
                    @foreach($crStaffList as $member)
                        <option value="{{ $member->staff_id }}" @selected($cashCarrierId === (int) $member->staff_id)>
                            {{ trim(($member->title ? $member->title.' ' : '').$member->fname.' '.$member->lname) }}
                            @if($member->job_name) ({{ $member->job_name }}) @endif
                        </option>
                    @endforeach
                </select>
                @error('cash_carrier_staff_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-0">
                <label for="cash_bank_transfer_unavailable_reason" class="form-label fw-semibold">
                    Why cash cannot be paid by bank transfer <span class="text-danger wb-cash-required">*</span>
                </label>
                <textarea name="cash_bank_transfer_unavailable_reason" id="cash_bank_transfer_unavailable_reason"
                    class="form-control @error('cash_bank_transfer_unavailable_reason') is-invalid @enderror"
                    rows="3" data-wb-cash-required="1"
                    placeholder="Explain clearly why the traveller cannot receive funds via bank transfer (e.g. destination banking limits, timing, etc.)">{{ $cashReason }}</textarea>
                @error('cash_bank_transfer_unavailable_reason')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="mb-0">
            <label for="supporting_reasons" class="form-label fw-semibold">
                <i class="fas fa-comment-alt me-1 text-warning"></i>
                {{ $supportingReasonsLabel ?? 'Supporting Reasons' }}
                <span class="text-danger">*</span>
            </label>
            @if(!empty($supportingReasonsHint))
                <small class="text-muted d-block mb-2">{{ $supportingReasonsHint }}</small>
            @else
                <small class="text-muted d-block mb-2">Provide detailed justification for the requested changes (shown to approvers and on the printed approval).</small>
            @endif
            <textarea name="supporting_reasons" id="supporting_reasons"
                class="form-control {{ !empty($useSummernoteForSupportingReasons) ? 'summernote' : '' }} @error('supporting_reasons') is-invalid @enderror"
                rows="{{ $supportingReasonsRows ?? 4 }}" required
                placeholder="{{ $supportingReasonsPlaceholder ?? 'Please explain why you are requesting these changes...' }}">{{ old('supporting_reasons', $cr->supporting_reasons ?? '') }}</textarea>
            @error('supporting_reasons')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

@once
    @push('scripts')
    <script>
    (function () {
        var cashCb = document.getElementById('request_travel_with_cash');
        var cashPanel = document.getElementById('travel-cash-fields');
        if (!cashCb || !cashPanel) return;

        function syncCashFields() {
            var on = cashCb.checked;
            cashPanel.classList.toggle('d-none', !on);
            cashPanel.querySelectorAll('[data-wb-cash-required]').forEach(function (el) {
                el.required = on;
            });
        }

        cashCb.addEventListener('change', syncCashFields);
        syncCashFields();
    })();
    </script>
    @endpush
@endonce
