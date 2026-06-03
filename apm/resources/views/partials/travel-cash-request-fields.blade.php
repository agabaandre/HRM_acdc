@php
    $memo = $memo ?? null;
    $requestCash = filter_var(old('request_travel_with_cash', $memo->request_travel_with_cash ?? false), FILTER_VALIDATE_BOOLEAN);
    $cashCarrierId = (int) old('cash_carrier_staff_id', $memo->cash_carrier_staff_id ?? 0);
    $cashReason = old('cash_bank_transfer_unavailable_reason', $memo->cash_bank_transfer_unavailable_reason ?? '');
    $cashStaffList = $cashStaffList ?? $staff ?? \App\Models\Staff::query()
        ->whereNotIn('status', ['Expired', 'Separated'])
        ->orderBy('lname')
        ->orderBy('fname')
        ->get(['staff_id', 'title', 'fname', 'lname', 'oname', 'job_name']);
    $cashCheckboxId = $cashCheckboxId ?? 'request_travel_with_cash';
    $cashPanelId = $cashPanelId ?? 'travel-cash-fields';
    $cashSelectId = $cashSelectId ?? 'cash_carrier_staff_id';
@endphp

<div class="card border-0 shadow-sm mb-3 border-secondary">
    <div class="card-body py-3">
        <input type="hidden" name="request_travel_with_cash" value="0">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="request_travel_with_cash" value="1"
                id="{{ $cashCheckboxId }}" @checked($requestCash)>
            <label class="form-check-label fw-semibold" for="{{ $cashCheckboxId }}">
                Request to travel with cash
            </label>
        </div>
        @if(!empty($showChangeRequestWorkflowHint))
            <small class="text-muted d-block mt-1">
                Leave unchecked if travel funds will be paid by bank transfer only. When checked alone on a change request, approval follows the
                <strong>same-quarter date change</strong> workflow; combined with budget or participant changes, the
                <strong>different-quarter / participant replacement</strong> workflow applies.
            </small>
        @else
            <small class="text-muted d-block mt-1">
                Check only if the traveller needs to carry cash. This will appear on the printed memo as approval to collect cash.
            </small>
        @endif
    </div>
</div>

<div id="{{ $cashPanelId }}" class="mb-4 {{ $requestCash ? '' : 'd-none' }}">
    <div class="card border-0 shadow-sm border-warning">
        <div class="card-header bg-warning text-dark py-2">
            <h6 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i> Travel with cash</h6>
        </div>
        <div class="card-body">
            <div class="alert alert-info py-2 small mb-3">
                <strong>Cash collection:</strong> Identify who will carry the cash and explain why bank transfer is not possible.
            </div>
            <div class="mb-3">
                <label for="{{ $cashSelectId }}" class="form-label fw-semibold">
                    Person carrying the cash <span class="text-danger sm-cash-required">*</span>
                </label>
                <select name="cash_carrier_staff_id" id="{{ $cashSelectId }}"
                    class="form-select w-100 sm-cash-carrier-select @error('cash_carrier_staff_id') is-invalid @enderror"
                    data-sm-cash-required="1"
                    data-placeholder="— Select staff —">
                    <option value=""></option>
                    @foreach($cashStaffList as $member)
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
                    Why cash cannot be paid by bank transfer <span class="text-danger sm-cash-required">*</span>
                </label>
                <textarea name="cash_bank_transfer_unavailable_reason" id="cash_bank_transfer_unavailable_reason"
                    class="form-control @error('cash_bank_transfer_unavailable_reason') is-invalid @enderror"
                    rows="3" data-sm-cash-required="1"
                    placeholder="Explain clearly why the traveller cannot receive funds via bank transfer (e.g. destination banking limits, timing, etc.)">{{ $cashReason }}</textarea>
                @error('cash_bank_transfer_unavailable_reason')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
    <script>
    (function () {
        function bindTravelCashUi(checkboxId, panelId, selectId) {
            var cashCb = document.getElementById(checkboxId);
            var cashPanel = document.getElementById(panelId);
            if (!cashCb || !cashPanel) return;

            function initCashCarrierSelect2() {
                if (typeof jQuery === 'undefined' || !jQuery.fn.select2) return;
                var $sel = jQuery('#' + selectId);
                if (!$sel.length) return;
                if ($sel.hasClass('select2-hidden-accessible')) {
                    try { $sel.select2('destroy'); } catch (e) {}
                }
                $sel.select2({
                    theme: 'bootstrap4',
                    width: '100%',
                    placeholder: $sel.data('placeholder') || '— Select staff —',
                    allowClear: true
                });
            }

            function syncCashFields() {
                var on = cashCb.checked;
                cashPanel.classList.toggle('d-none', !on);
                cashPanel.querySelectorAll('[data-sm-cash-required]').forEach(function (el) {
                    el.required = on;
                });
                if (on) {
                    window.requestAnimationFrame(initCashCarrierSelect2);
                }
            }

            cashCb.addEventListener('change', syncCashFields);
            syncCashFields();

            if (typeof jQuery !== 'undefined') {
                jQuery(document).ready(function () {
                    if (cashCb.checked) {
                        initCashCarrierSelect2();
                    }
                });
            }
        }

        bindTravelCashUi('request_travel_with_cash', 'travel-cash-fields', 'cash_carrier_staff_id');
    })();
    </script>
    @endpush
@endonce
