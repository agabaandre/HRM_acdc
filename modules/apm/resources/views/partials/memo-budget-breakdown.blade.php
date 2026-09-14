@php
    $budgetByFundCode = $budgetByFundCode ?? [];
    $fundCodes = $fundCodes ?? collect();
    $budget = $budget ?? [];
    $availableBudget = $availableBudget ?? null;
    $titleMode = $titleMode ?? 'code_only';
    $variant = $variant ?? 'units_days';
    $panelTitle = $panelTitle ?? 'Budget Information';

    $resolveFundCode = static function ($fundCodeId) use ($fundCodes) {
        $id = (int) $fundCodeId;
        if ($fundCodes instanceof \Illuminate\Support\Collection) {
            return $fundCodes->get($id);
        }
        if (is_array($fundCodes)) {
            return $fundCodes[$fundCodeId] ?? $fundCodes[$id] ?? null;
        }

        return null;
    };

    $lineTotal = static function (array $item) use ($variant): float {
        if ($variant === 'quantity') {
            $qty = floatval($item['quantity'] ?? 1);
            $unit = floatval($item['unit_cost'] ?? 0);

            return $qty * $unit;
        }

        $unitCost = floatval($item['unit_cost'] ?? 0);
        $units = floatval($item['units'] ?? $item['quantity'] ?? 1);
        $days = floatval($item['days'] ?? 1);

        return $unitCost * $units * $days;
    };
@endphp

@once
    @push('head-meta')
        <link rel="stylesheet" href="{{ asset('css/apm-memo-budget-show.css') }}?v=3">
    @endpush
@endonce

<div class="apm-budget-panel">
    <div class="apm-budget-panel__header">
        <i class="bx bx-money apm-budget-panel__icon"></i>
        {{ $panelTitle }}
    </div>
    <div class="apm-budget-panel__body">
        @if (! empty($budgetByFundCode))
            @php $grandTotal = 0; @endphp

            @foreach ($budgetByFundCode as $fundCodeId => $items)
                @if (! is_array($items) || $items === [])
                    @continue
                @endif

                @php
                    $fundCode = $resolveFundCode($fundCodeId);
                    $groupTotal = 0;
                    $itemCount = 1;
                @endphp

                <div class="apm-budget-fund-block">
                    <div class="apm-budget-fund-title">
                        @if ($titleMode === 'full' && $fundCode)
                            {{ $fundCode->activity ?? '—' }} — {{ $fundCode->code ?? '—' }} — {{ $fundCode->funder->name ?? 'N/A' }}
                            @if ($fundCode->fundType)
                                <span class="text-muted">({{ $fundCode->fundType->name }})</span>
                            @endif
                        @elseif ($fundCode)
                            <span class="text-muted">Budget code:</span> {{ $fundCode->code ?: '—' }}
                            @if ($fundCode->fundType)
                                <span class="text-muted">({{ $fundCode->fundType->name }})</span>
                            @endif
                        @else
                            <span class="text-muted">Budget code reference:</span> {{ $fundCodeId }}
                        @endif
                    </div>

                    <div class="apm-budget-table-wrap table-responsive">
                        <table class="table apm-budget-table mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 3rem;">#</th>
                                    @if ($variant === 'quantity')
                                        <th>Description</th>
                                        <th class="text-center">Quantity</th>
                                        <th class="text-end">Unit price</th>
                                        <th class="text-end">Total</th>
                                    @else
                                        <th>Cost item</th>
                                        <th class="text-end">Unit cost</th>
                                        <th class="text-end">Units</th>
                                        <th class="text-end">Days</th>
                                        <th class="text-end">Total</th>
                                        <th>Description</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $item)
                                    @if (! is_array($item))
                                        @continue
                                    @endif
                                    @php
                                        $total = $lineTotal($item);
                                        $groupTotal += $total;
                                        $grandTotal += $total;
                                    @endphp
                                    <tr>
                                        <td>{{ $itemCount++ }}</td>
                                        @if ($variant === 'quantity')
                                            <td>
                                                <div class="fw-medium">{{ $item['description'] ?? 'N/A' }}</div>
                                                @if (! empty($item['notes']))
                                                    <small class="text-muted">{{ $item['notes'] }}</small>
                                                @endif
                                            </td>
                                            <td class="text-center">{{ $item['quantity'] ?? 1 }}</td>
                                            <td class="text-end">{{ number_format((float) ($item['unit_cost'] ?? 0), 2) }}</td>
                                            <td class="text-end">{{ number_format($total, 2) }}</td>
                                        @else
                                            <td>{{ $item['cost'] ?? 'N/A' }}</td>
                                            <td class="text-end">{{ number_format((float) ($item['unit_cost'] ?? 0), 2) }}</td>
                                            <td class="text-end">{{ $item['units'] ?? $item['quantity'] ?? 1 }}</td>
                                            <td class="text-end">{{ $item['days'] ?? 1 }}</td>
                                            <td class="text-end">{{ number_format($total, 2) }}</td>
                                            <td>{{ $item['description'] ?? '' }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    @if ($variant === 'quantity')
                                        <th colspan="4" class="text-end">Sub total</th>
                                        <th class="text-end">{{ number_format($groupTotal, 2) }}</th>
                                    @else
                                        <th colspan="5" class="text-end">Sub total</th>
                                        <th class="text-end">{{ number_format($groupTotal, 2) }}</th>
                                        <th></th>
                                    @endif
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endforeach

            <div class="apm-budget-grand-total">
                <span>Grand total</span>
                <strong>{{ number_format($grandTotal, 2) }} USD</strong>
            </div>

            @if ($availableBudget)
                <div class="apm-budget-available">
                    <strong>Available budget: {{ number_format((float) $availableBudget, 2) }} USD</strong>
                    <small>Allocated by Finance Officer</small>
                </div>
            @endif
        @elseif (! empty($budget))
            <div class="apm-budget-table-wrap table-responsive">
                <table class="table apm-budget-table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Budget item</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($budget as $key => $value)
                            @if ($key !== 'grand_total')
                                <tr>
                                    <td>{{ $key }}</td>
                                    <td>
                                        @if (is_array($value))
                                            <pre class="mb-0 small">{{ json_encode($value, JSON_PRETTY_PRINT) }}</pre>
                                        @else
                                            {{ $value }}
                                        @endif
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="apm-budget-empty">
                <i class="bx bx-money"></i>
                <p class="mb-0">No budget details</p>
            </div>
        @endif
    </div>
</div>
