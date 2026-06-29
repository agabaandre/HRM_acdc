@php
    $apmBalanceConfig = array_merge([
        'pollUrl' => route('fund-codes.working-balances'),
        'pollMs' => 15000,
        'selectSelector' => '#budget_codes',
        'fundTypeSelector' => '#fund_type',
        'exclude' => [],
        'changeRequestMode' => false,
        'currentActivityBudgets' => new \stdClass(),
        'originalSubtotals' => new \stdClass(),
    ], $apmBalanceConfig ?? []);
@endphp
<script src="{{ asset('js/apm-working-balance.js') }}"></script>
<script>
(function () {
    window.ApmWorkingBalanceConfig = @json($apmBalanceConfig);
    function bootWorkingBalance() {
        if (!window.ApmWorkingBalance) {
            return;
        }
        window.ApmWorkingBalance.startPolling(window.ApmWorkingBalanceConfig);
        $(document).on('apm:working-balances-updated', function () {
            if (typeof window.updateAllTotals === 'function') {
                window.updateAllTotals();
            } else if (typeof window.updateGrandTotal === 'function') {
                window.updateGrandTotal();
            }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootWorkingBalance);
    } else {
        bootWorkingBalance();
    }
})();
</script>
