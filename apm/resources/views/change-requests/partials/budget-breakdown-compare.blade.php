{{--
  Side-by-side original vs changed budget for change requests.
  Non-travel: description / unit / quantity / unit_cost
  Travel/special: cost / units / days / unit_cost
--}}
@php
    $isNonTravelBudget = (bool) ($isNonTravelBudget ?? false);

    $decodeBreakdown = static function ($raw): array {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($raw) ? $raw : [];
    };

    $parentBudgetBreakdown = $decodeBreakdown($parentBudgetBreakdown ?? []);
    $currentBudgetBreakdown = $decodeBreakdown($currentBudgetBreakdown ?? []);

    $parentTotal = (float) ($parentBudgetBreakdown['grand_total'] ?? 0);
    $currentTotal = (float) ($currentBudgetBreakdown['grand_total'] ?? 0);
    unset($parentBudgetBreakdown['grand_total'], $currentBudgetBreakdown['grand_total']);

    if ($parentTotal <= 0) {
        foreach ($parentBudgetBreakdown as $items) {
            if (! is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if (is_array($item)) {
                    $parentTotal += change_request_budget_line_display($item, $isNonTravelBudget)['total'];
                }
            }
        }
    }
    if ($currentTotal <= 0) {
        foreach ($currentBudgetBreakdown as $items) {
            if (! is_array($items)) {
                continue;
            }
            foreach ($items as $item) {
                if (is_array($item)) {
                    $currentTotal += change_request_budget_line_display($item, $isNonTravelBudget)['total'];
                }
            }
        }
    }

    $originalBudgetMap = [];
    foreach ($parentBudgetBreakdown as $fundCodeId => $items) {
        if (! is_array($items)) {
            continue;
        }
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $line = change_request_budget_line_display($item, $isNonTravelBudget);
            $key = $fundCodeId.'_'.$line['signature'];
            $originalBudgetMap[$key] = ($originalBudgetMap[$key] ?? 0) + 1;
        }
    }
    $matchedItems = [];

    $qtyHeader = $isNonTravelBudget ? 'Qty' : 'Units';
    $extraHeader = $isNonTravelBudget ? 'Unit' : 'Days';
@endphp

<style>
    .cr-budget-compare-table {
        table-layout: fixed;
        width: 100%;
    }
    .cr-budget-compare-table th:first-child,
    .cr-budget-compare-table td.cr-budget-item-cell {
        width: 42%;
        white-space: normal !important;
        word-break: break-word;
        overflow-wrap: anywhere;
        hyphens: auto;
        vertical-align: top;
    }
    .cr-budget-compare-table th:not(:first-child),
    .cr-budget-compare-table td:not(.cr-budget-item-cell) {
        white-space: nowrap;
        vertical-align: top;
    }
</style>

<div class="row">
    <div class="col-md-6">
        <h6 class="text-muted mb-2"><strong>Original Budget</strong></h6>
        @if(count($parentBudgetBreakdown) > 0)
            <div>
                @foreach($parentBudgetBreakdown as $fundCodeId => $items)
                    @if(is_array($items) && count($items) > 0)
                        @php $fundCode = \App\Models\FundCode::find($fundCodeId); @endphp
                        <div class="mb-3">
                            <div class="bg-light p-2 rounded-top border border-bottom-0">
                                <strong style="color: #911C39;">{{ $fundCode->code ?? 'N/A' }}</strong>
                            </div>
                            <table class="table table-sm table-bordered mb-0 cr-budget-compare-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-end">Unit Cost</th>
                                        <th class="text-end">{{ $qtyHeader }}</th>
                                        <th class="text-end">{{ $extraHeader }}</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($items as $item)
                                        @continue(! is_array($item))
                                        @php $line = change_request_budget_line_display($item, $isNonTravelBudget); @endphp
                                        <tr>
                                            <td class="cr-budget-item-cell">{{ $line['name'] }}</td>
                                            <td class="text-end">${{ number_format($line['unit_cost'], 2) }}</td>
                                            <td class="text-end">{{ number_format($line['qty'], $isNonTravelBudget ? 2 : 0) }}</td>
                                            <td class="text-end">
                                                @if($isNonTravelBudget)
                                                    {{ $line['extra_text'] ?: '—' }}
                                                @else
                                                    {{ number_format((float) ($line['extra'] ?? 1), 0) }}
                                                @endif
                                            </td>
                                            <td class="text-end">${{ number_format($line['total'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endforeach
                <div class="table-warning p-2 rounded-bottom border">
                    <div class="d-flex justify-content-end">
                        <strong>Total: ${{ number_format($parentTotal, 2) }}</strong>
                    </div>
                </div>
            </div>
        @else
            <div class="text-muted">No budget breakdown available</div>
        @endif
    </div>

    <div class="col-md-6">
        <h6 class="text-muted mb-2"><strong>Changed Budget</strong></h6>
        @if(count($currentBudgetBreakdown) > 0)
            <div>
                @foreach($currentBudgetBreakdown as $fundCodeId => $items)
                    @if(is_array($items) && count($items) > 0)
                        @php $fundCode = \App\Models\FundCode::find($fundCodeId); @endphp
                        <div class="mb-3">
                            <div class="bg-light p-2 rounded-top border border-bottom-0">
                                <strong style="color: #911C39;">{{ $fundCode->code ?? 'N/A' }}</strong>
                            </div>
                            <table class="table table-sm table-bordered mb-0 cr-budget-compare-table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-end">Unit Cost</th>
                                        <th class="text-end">{{ $qtyHeader }}</th>
                                        <th class="text-end">{{ $extraHeader }}</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($items as $item)
                                        @continue(! is_array($item))
                                        @php
                                            $line = change_request_budget_line_display($item, $isNonTravelBudget);
                                            $key = $fundCodeId.'_'.$line['signature'];
                                            if (! isset($matchedItems[$fundCodeId])) {
                                                $matchedItems[$fundCodeId] = [];
                                            }
                                            if (! isset($matchedItems[$fundCodeId][$key])) {
                                                $matchedItems[$fundCodeId][$key] = 0;
                                            }
                                            $matchedItems[$fundCodeId][$key]++;
                                            $originalCount = $originalBudgetMap[$key] ?? 0;
                                            $shouldHighlight = $originalCount === 0 || $matchedItems[$fundCodeId][$key] > $originalCount;
                                        @endphp
                                        <tr @if($shouldHighlight) style="background-color: #ffe6e6;" @endif>
                                            <td class="cr-budget-item-cell">{{ $line['name'] }}</td>
                                            <td class="text-end">${{ number_format($line['unit_cost'], 2) }}</td>
                                            <td class="text-end">{{ number_format($line['qty'], $isNonTravelBudget ? 2 : 0) }}</td>
                                            <td class="text-end">
                                                @if($isNonTravelBudget)
                                                    {{ $line['extra_text'] ?: '—' }}
                                                @else
                                                    {{ number_format((float) ($line['extra'] ?? 1), 0) }}
                                                @endif
                                            </td>
                                            <td class="text-end">${{ number_format($line['total'], 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endforeach
                <div class="p-2 rounded-bottom border" style="background-color: #d4edda;">
                    <div class="d-flex justify-content-end">
                        <strong>Total: ${{ number_format($currentTotal, 2) }}</strong>
                    </div>
                </div>
            </div>
        @else
            <div class="text-muted">No budget breakdown available</div>
        @endif
    </div>
</div>
