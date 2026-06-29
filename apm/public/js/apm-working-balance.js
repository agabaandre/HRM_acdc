/**
 * APM fund-code working balance — poll server cache and update budget UIs.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    const timers = {};
    const DEFAULT_POLL_MS = 15000;

    function formatMoney(amount) {
        const n = parseFloat(amount) || 0;
        return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function collectFundCodeIds(selectSelector) {
        const ids = new Set();
        $(selectSelector || '#budget_codes').find('option').each(function () {
            const v = parseInt($(this).val(), 10);
            if (v > 0) {
                ids.add(v);
            }
        });
        return Array.from(ids);
    }

    function applyBalancesToOptions(balances, selectSelector) {
        const $select = $(selectSelector || '#budget_codes');
        $select.find('option').each(function () {
            const id = parseInt($(this).val(), 10);
            if (!id || !balances[id]) {
                return;
            }
            const snap = balances[id];
            const working = snap.working_balance ?? snap.workingBalance ?? 0;
            $(this).attr('data-balance', working);
            $(this).data('balance', working);
            $(this).attr('data-approved-budget', snap.approved_budget ?? 0);
            $(this).attr('data-committed-total', snap.committed_total ?? 0);

            const parts = ($(this).text() || '').split('|');
            if (parts.length >= 3) {
                const code = parts[0].trim();
                const funder = parts[1].trim();
                $(this).text(`${code} | ${funder} | $${formatMoney(working)}`);
            }
        });

        if ($select.hasClass('select2-hidden-accessible')) {
            $select.trigger('change.select2');
        }
    }

    function buildQuery(ids, config) {
        const params = new URLSearchParams();
        ids.forEach((id) => params.append('ids[]', String(id)));
        const exclude = config.exclude || {};
        Object.keys(exclude).forEach((key) => {
            if (exclude[key]) {
                params.append(key, String(exclude[key]));
            }
        });
        return params.toString();
    }

    function refreshBalances(config) {
        const cfg = config || window.ApmWorkingBalanceConfig || {};
        const url = cfg.pollUrl;
        if (!url) {
            return $.Deferred().reject().promise();
        }

        const ids = collectFundCodeIds(cfg.selectSelector);
        if (!ids.length) {
            return $.Deferred().resolve().promise();
        }

        return $.getJSON(`${url}?${buildQuery(ids, cfg)}`)
            .done(function (resp) {
                const balances = resp.balances || resp;
                applyBalancesToOptions(balances, cfg.selectSelector);
                if (typeof cfg.onBalancesUpdated === 'function') {
                    cfg.onBalancesUpdated(balances);
                }
                $(document).trigger('apm:working-balances-updated', [balances]);
            });
    }

    function checkExceededForCard(codeId, subtotal, config) {
        const cfg = config || window.ApmWorkingBalanceConfig || {};
        const fundTypeId = parseInt($(cfg.fundTypeSelector || '#fund_type').val(), 10) || 0;
        if (fundTypeId === 3) {
            return false;
        }

        const $option = $(`${cfg.selectSelector || '#budget_codes'} option[value="${codeId}"]`);
        let available = parseFloat($option.data('balance')) || 0;

        if (cfg.currentActivityBudgets && cfg.currentActivityBudgets[codeId]) {
            available += parseFloat(cfg.currentActivityBudgets[codeId]) || 0;
        }

        if (cfg.changeRequestMode) {
            const original = (cfg.originalSubtotals && cfg.originalSubtotals[codeId]) || 0;
            const delta = subtotal - original;
            return delta > 0 && delta > available + 0.009;
        }

        return subtotal > available + 0.009;
    }

    function showBudgetWarning($card, available, isChangeRequest) {
        let $warning = $card.find('.budget-warning');
        if ($warning.length) {
            return;
        }
        const msg = isChangeRequest
            ? `New items exceed available budget! Available: $${formatMoney(available)}`
            : `Budget exceeded! Available: $${formatMoney(available)}`;
        $warning = $(`<div class="alert alert-danger mt-2 budget-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>${msg}
        </div>`);
        ($card.find('.card-body').length ? $card.find('.card-body') : $card).append($warning);
    }

    function startPolling(config) {
        const cfg = $.extend({
            pollMs: DEFAULT_POLL_MS,
            selectSelector: '#budget_codes',
            fundTypeSelector: '#fund_type',
        }, config || window.ApmWorkingBalanceConfig || {});

        window.ApmWorkingBalanceConfig = cfg;
        const key = cfg.pollUrl || 'default';

        if (timers[key]) {
            clearInterval(timers[key]);
        }

        refreshBalances(cfg);
        timers[key] = setInterval(function () {
            refreshBalances(cfg);
        }, cfg.pollMs || DEFAULT_POLL_MS);
    }

    function stopPolling(config) {
        const cfg = config || window.ApmWorkingBalanceConfig || {};
        const key = cfg.pollUrl || 'default';
        if (timers[key]) {
            clearInterval(timers[key]);
            delete timers[key];
        }
    }

    window.ApmWorkingBalance = {
        startPolling,
        stopPolling,
        refreshBalances,
        applyBalancesToOptions,
        checkExceededForCard,
        showBudgetWarning,
        formatMoney,
    };
})(window, window.jQuery);
