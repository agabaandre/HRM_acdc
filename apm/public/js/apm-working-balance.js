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
    const FINANCE_EXCEED_WARNING =
        'Budget exceeds the available fund code balance. You may still submit — Finance will validate your request, and if the overage is confirmed the memo will be returned.';

    function formatMoney(amount) {
        const n = parseFloat(amount) || 0;
        return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function config(config) {
        return config || window.ApmWorkingBalanceConfig || {};
    }

    function fundCodeOption(codeId, cfg) {
        const selectSelector = (cfg || config()).selectSelector || '#budget_codes';
        return $(`${selectSelector} option[value="${codeId}"]`);
    }

    function fundCodeBaseBalance(codeId, cfg) {
        const $option = fundCodeOption(codeId, cfg);
        if (!$option.length) {
            return 0;
        }
        let initial = parseFloat($option.data('initial-balance'));
        if (Number.isNaN(initial)) {
            initial = parseFloat($option.data('balance')) || 0;
            $option.data('initial-balance', initial);
        }
        return initial;
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

    function resolveDisplayBalance(snap) {
        if (!snap) {
            return 0;
        }
        if (snap.budget_balance !== undefined && snap.budget_balance !== null) {
            return parseFloat(snap.budget_balance) || 0;
        }
        return parseFloat(snap.working_balance ?? snap.workingBalance ?? 0) || 0;
    }

    function applyBalancesToOptions(balances, selectSelector) {
        const $select = $(selectSelector || '#budget_codes');
        $select.find('option').each(function () {
            const id = parseInt($(this).val(), 10);
            if (!id || !balances[id]) {
                return;
            }
            const snap = balances[id];
            const displayBalance = resolveDisplayBalance(snap);
            $(this).attr('data-balance', displayBalance);
            $(this).data('balance', displayBalance);
            $(this).data('initial-balance', displayBalance);
            $(this).attr('data-approved-budget', snap.approved_budget ?? 0);
            $(this).attr('data-committed-total', snap.committed_total ?? 0);

            const parts = ($(this).text() || '').split('|');
            if (parts.length >= 3) {
                const code = parts[0].trim();
                const funder = parts[1].trim();
                $(this).text(`${code} | ${funder} | $${formatMoney(displayBalance)}`);
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

    function updateCardRemainingBalance(codeId, subtotal, cfg) {
        const configObj = cfg || config();
        let initial = fundCodeBaseBalance(codeId, configObj);
        const $option = fundCodeOption(codeId, configObj);
        let currentActivityBudget = parseFloat($option.data('current-activity-budget')) || 0;
        if (!currentActivityBudget && configObj.currentActivityBudgets && configObj.currentActivityBudgets[codeId]) {
            currentActivityBudget = parseFloat(configObj.currentActivityBudgets[codeId]) || 0;
        }
        initial += currentActivityBudget;
        const remaining = initial - (parseFloat(subtotal) || 0);
        const $card = $(`.budget-body[data-code="${codeId}"]`).closest('.card');
        const $budgetCard = $(`.budget-card[data-code="${codeId}"]`);
        const $targetCard = $card.length ? $card : $budgetCard;
        const $balanceEl = $targetCard.find('.fund-code-remaining-balance');
        if ($balanceEl.length) {
            $balanceEl.text(formatMoney(remaining));
            $balanceEl.toggleClass('text-danger fw-bold', remaining < -0.009);
            $balanceEl.toggleClass('text-success', remaining >= -0.009);
        }
        return remaining;
    }

    function checkExceededForCard(codeId, subtotal, config) {
        const cfg = config || window.ApmWorkingBalanceConfig || {};
        const fundTypeId = parseInt($(cfg.fundTypeSelector || '#fund_type').val(), 10) || 0;
        if (fundTypeId === 3) {
            return false;
        }

        let available = fundCodeBaseBalance(codeId, cfg);
        const $option = fundCodeOption(codeId, cfg);
        const currentActivityBudget = parseFloat($option.data('current-activity-budget')) || 0;
        available += currentActivityBudget;
        if (!currentActivityBudget && cfg.currentActivityBudgets && cfg.currentActivityBudgets[codeId]) {
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
        const msg = isChangeRequest
            ? `New items exceed available budget! Available: $${formatMoney(available)}. ${FINANCE_EXCEED_WARNING}`
            : FINANCE_EXCEED_WARNING;
        let $warning = $card.find('.budget-warning');
        if ($warning.length) {
            $warning.removeClass('alert-danger').addClass('alert-warning');
            $warning.html(`<i class="fas fa-exclamation-triangle me-2"></i>${msg}`);
            return;
        }
        $warning = $(`<div class="alert alert-warning mt-2 budget-warning">
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
        updateCardRemainingBalance,
        fundCodeBaseBalance,
        formatMoney,
        FINANCE_EXCEED_WARNING,
    };
})(window, window.jQuery);
