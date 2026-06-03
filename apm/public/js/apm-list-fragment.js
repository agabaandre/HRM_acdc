/**
 * Headers for memo index tab partial fetches. Prevents back / Livewire navigate
 * from loading JSON as a full page when the URL contains ?tab=.
 */
(function () {
    'use strict';

    window.APMListFragment = {
        headerName: 'X-APM-List-Fragment',
        headers: function () {
            return {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-APM-List-Fragment': '1'
            };
        }
    };

    function looksLikeListFragmentJsonPage() {
        var text = document.body && document.body.innerText ? document.body.innerText.trim() : '';
        return text.indexOf('{"html":') === 0 || text.indexOf('{\"html\":') === 0;
    }

    function recoverFromJsonBackNavigation() {
        if (!looksLikeListFragmentJsonPage()) {
            return;
        }
        var url = new URL(window.location.href);
        url.searchParams.delete('tab');
        window.location.replace(url.pathname + url.search + url.hash);
    }

    document.addEventListener('DOMContentLoaded', recoverFromJsonBackNavigation);
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            recoverFromJsonBackNavigation();
        }
    });
})();
