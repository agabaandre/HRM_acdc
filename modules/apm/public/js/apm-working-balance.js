/**
 * APM fund-code Budget Balance (Finance/SAP synced) — poll and update budget UIs.
 * Commitments are handled in SAP; we use fund_codes.budget_balance only.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    const timers = {};
    const DEFAULT_POLL_MS = 15000;
    const FINANCE_EXCEED_WARNING =
        'This line exceeds the Budget Balance synced from Finance (SAP). You may still submit — Finance will validate, and if the overage is confirmed the memo will be returned.';

    function formatMoney(amount) {
        const n = parseFloat(amount) || 0;
        return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatBudgetCodeLabel(code, funder, financeBalance, sapSyncedAt) {
        const money = formatMoney(financeBalance);
        const sync = sapSyncedAt ? ` | last SAP sync: ${sapSyncedAt}` : '';
        return `${code} | ${funder || 'No Funder'} | $${money}${sync}`;
    }

    window.ApmBudgetCodeLabel = { format: formatBudgetCodeLabel, formatMoney: formatMoney };

    function config(config) {
        return config || window.ApmWorkingBalanceConfig || {};
    }

    function hasExcludeContext(cfg) {
        const exclude = (cfg || config()).exclude || {};
        return Object.keys(exclude).some(function (key) {
            const value = parseInt(exclude[key], 10);
            return !Number.isNaN(value) && value > 0;
        });
    }

    function fundCodeOption(codeId, cfg) {
        const selectSelector = (cfg || config()).selectSelector || '#budget_codes';
        return $(`${selectSelector} option[value="${codeId}"]`);
    }

    /** SAP/Finance Budget Balance from fund_codes.budget_balance. */
    function fundCodeFinanceBalance(codeId, cfg) {
        const $option = fundCodeOption(codeId, cfg);
        if (!$option.length) {
            return 0;
        }
        let finance = parseFloat($option.data('finance-balance'));
        if (Number.isNaN(finance)) {
            finance = parseFloat($option.attr('data-finance-balance'));
        }
        if (Number.isNaN(finance)) {
            finance = parseFloat($option.data('balance'));
        }
        if (Number.isNaN(finance)) {
            finance = parseFloat($option.data('initial-balance'));
        }
        return Number.isNaN(finance) ? 0 : finance;
    }

    function fundCodeBaseBalance(codeId, cfg) {
        return fundCodeFinanceBalance(codeId, cfg);
    }

    function fundCodeAvailableBalance(codeId, cfg) {
        return fundCodeFinanceBalance(codeId, cfg);
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
        if (snap.finance_budget_balance !== undefined && snap.finance_budget_balance !== null) {
            return parseFloat(snap.finance_budget_balance) || 0;
        }
        if (snap.budget_balance !== undefined && snap.budget_balance !== null) {
            return parseFloat(snap.budget_balance) || 0;
        }
        if (snap.working_balance !== undefined && snap.working_balance !== null) {
            return parseFloat(snap.working_balance) || 0;
        }
        return 0;
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
            $(this).attr('data-finance-balance', displayBalance);
            $(this).data('finance-balance', displayBalance);
            $(this).attr('data-approved-budget', snap.approved_budget ?? 0);
            $(this).attr('data-committed-total', 0);

            if (snap.sap_synced_at) {
                $(this).attr('data-sap-synced-at', snap.sap_synced_at);
                $(this).data('sap-synced-at', snap.sap_synced_at);
            }

            const code = ($(this).data('code') || ($(this).text() || '').split('|')[0] || '').toString().trim();
            const funder = ($(this).data('funder-name') || ($(this).text() || '').split('|')[1] || 'No Funder').toString().trim();
            const sapSyncedAt = $(this).data('sap-synced-at') || '';
            if (code) {
                $(this).text(formatBudgetCodeLabel(code, funder, displayBalance, sapSyncedAt));
            }

            $(`.budget-body[data-code="${id}"]`).closest('.card').find('.fund-code-header-balance').each(function () {
                $(this).text(formatMoney(displayBalance));
                $(this).toggleClass('text-danger', displayBalance < 0.009);
                $(this).toggleClass('text-success', displayBalance >= 0.009);
            });
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
        const initial = fundCodeFinanceBalance(codeId, configObj);
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

        const $headerBalanceEl = $targetCard.find('.fund-code-header-balance');
        if ($headerBalanceEl.length) {
            $headerBalanceEl.text(formatMoney(initial));
            $headerBalanceEl.toggleClass('text-danger', initial < 0.009);
            $headerBalanceEl.toggleClass('text-success', initial >= 0.009);
        }

        return remaining;
    }

    function checkExceededForCard(codeId, subtotal, config) {
        const cfg = config || window.ApmWorkingBalanceConfig || {};
        const fundTypeId = parseInt($(cfg.fundTypeSelector || '#fund_type').val(), 10) || 0;
        if (fundTypeId === 3) {
            return false;
        }

        const budgetBalance = fundCodeFinanceBalance(codeId, cfg);

        if (cfg.changeRequestMode) {
            const original = (cfg.originalSubtotals && cfg.originalSubtotals[codeId]) || 0;
            const delta = subtotal - original;
            return delta > 0 && subtotal > budgetBalance + 0.009;
        }

        return subtotal > budgetBalance + 0.009;
    }

    function showBudgetWarning($card, available, isChangeRequest) {
        const msg = isChangeRequest
            ? `New items exceed Budget Balance! Budget Balance: $${formatMoney(available)}. ${FINANCE_EXCEED_WARNING}`
            : `Budget exceeds Budget Balance ($${formatMoney(available)}). ${FINANCE_EXCEED_WARNING}`;
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
        fundCodeAvailableBalance,
        fundCodeFinanceBalance,
        hasExcludeContext,
        formatMoney,
        FINANCE_EXCEED_WARNING,
    };
})(window, window.jQuery);
