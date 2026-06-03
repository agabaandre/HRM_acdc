@if(!empty($memo) && $memo->request_travel_with_cash)
    @php
        $cashCarrier = $memo->cashCarrier ?? null;
        if (! $cashCarrier && ! empty($memo->cash_carrier_staff_id)) {
            $cashCarrier = \App\Models\Staff::query()->find($memo->cash_carrier_staff_id);
        }
        $cashCarrierLabel = 'N/A';
        if ($cashCarrier) {
            $cashCarrierLabel = trim(($cashCarrier->title ? $cashCarrier->title.' ' : '').($cashCarrier->fname ?? '').' '.($cashCarrier->lname ?? ''));
        }
    @endphp
    <div class="section" style="margin-top: 14px;">
        <h3 class="mb-8" style="color: #b45309;">Approval to collect travel cash</h3>
        <div style="padding: 12px; background: #fff8e6; border: 2px solid #d97706;">
            <p style="margin: 0 0 8px 0;"><strong>Travel with cash:</strong> Requested on this memo.</p>
            <p style="margin: 0 0 8px 0;"><strong>Person carrying the cash:</strong> {{ $cashCarrierLabel }}</p>
            @if(!empty($memo->cash_bank_transfer_unavailable_reason))
                <p style="margin: 0;"><strong>Why bank transfer is not possible:</strong>
                    {{ strip_tags((string) $memo->cash_bank_transfer_unavailable_reason) }}
                </p>
            @endif
        </div>
    </div>
@endif
