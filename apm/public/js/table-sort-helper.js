/**
 * Reusable table sort helper for report tables and any table with .sortable-th headers.
 * Usage:
 *   1. Include this script on the page.
 *   2. Call TableSort.init({ defaultColumn: 'col', defaultDir: 'asc', readFromQuery: true }) on load.
 *   3. In your getQuery() use TableSort.addToParams(params) so sort params are sent to the server.
 *   4. After rendering the table, call TableSort.attach(containerElement, reloadCallback).
 *   5. For client-built tables, use TableSort.getSortIcon(column) to render the sort indicator in <th>.
 */
(function (global) {
    'use strict';

    var state = { column: null, dir: 'asc' };

    function init(options) {
        options = options || {};
        var q = typeof window !== 'undefined' && window.location && window.location.search
            ? new URLSearchParams(window.location.search)
            : null;
        if (options.readFromQuery && q && q.has('sort_column')) {
            state.column = q.get('sort_column');
            state.dir = (q.get('sort_dir') || 'asc').toLowerCase() === 'desc' ? 'desc' : 'asc';
        } else if (options.defaultColumn) {
            state.column = options.defaultColumn;
            state.dir = (options.defaultDir || 'asc').toLowerCase() === 'desc' ? 'desc' : 'asc';
        }
    }

    function getState() {
        return { column: state.column, dir: state.dir };
    }

    function setState(column, dir) {
        state.column = column;
        state.dir = (dir || 'asc').toLowerCase() === 'desc' ? 'desc' : 'asc';
    }

    /**
     * Add sort_column and sort_dir to a URLSearchParams object. Returns the same object.
     */
    function addToParams(params) {
        if (params && state.column) {
            params.set('sort_column', state.column);
            params.set('sort_dir', state.dir);
        }
        return params;
    }

    /**
     * Return HTML for the sort indicator (for client-rendered tables).
     * column: the data-sort-column value for this th.
     */
    function getSortIcon(column) {
        if (state.column !== column) {
            return ' <span class="text-muted opacity-50 small">&#8645;</span>';
        }
        return state.dir === 'asc'
            ? ' <span class="small">&#9650;</span>'
            : ' <span class="small">&#9660;</span>';
    }

    /**
     * Bind click handlers to all .sortable-th inside container. On click updates state and calls reloadCallback.
     * @param {HTMLElement} container - Element containing the table (e.g. table_container).
     * @param {function} reloadCallback - Function to call after sort change (e.g. loadData or loadList(1)).
     */
    function attach(container, reloadCallback) {
        if (!container || typeof container.querySelectorAll !== 'function') return;
        container.querySelectorAll('.sortable-th').forEach(function (th) {
            th.addEventListener('click', function () {
                var col = th.getAttribute('data-sort-column');
                if (!col) return;
                if (state.column === col) {
                    state.dir = state.dir === 'asc' ? 'desc' : 'asc';
                } else {
                    state.column = col;
                    state.dir = 'asc';
                }
                if (typeof reloadCallback === 'function') reloadCallback();
            });
        });
    }

    global.TableSort = {
        init: init,
        getState: getState,
        setState: setState,
        addToParams: addToParams,
        getSortIcon: getSortIcon,
        attach: attach
    };
})(typeof window !== 'undefined' ? window : this);
