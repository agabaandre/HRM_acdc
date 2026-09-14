/**
 * Apply saved / virtual participant groups on activity & special memo forms.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    const NONE_OPTION_HTML = '<option value="">None</option>';

    function config() {
        return window.ApmParticipantGroupsConfig || {};
    }

    function requireDates() {
        const cfg = config();
        if (typeof cfg.requireDates === 'function') {
            return cfg.requireDates();
        }
        const start = $('#date_from').val();
        const end = $('#date_to').val();
        return !!(start && end);
    }

    function notify(msg, type) {
        if (typeof window.show_notification === 'function') {
            window.show_notification(msg, type || 'warning');
            return;
        }
        alert(msg);
    }

    function groupSelect() {
        const cfg = config();
        return $(cfg.selectSelector || '#participant_group_select');
    }

    function ensureParticipantSelectOptions(members) {
        const $select = $('#internal_participants');
        if (!$select.length) {
            return;
        }

        members.forEach(function (m) {
            const id = String(m.id);
            if (!$select.find('option[value="' + id + '"]').length) {
                $select.append(
                    $('<option></option>').attr('value', id).text(m.name)
                );
            }
        });
    }

    function mergeInternalParticipantsSelect(staffIds, triggerChange) {
        const $select = $('#internal_participants');
        if (!$select.length) {
            return;
        }

        const current = ($select.val() || []).map(String);
        const merged = Array.from(new Set(current.concat(staffIds.map(String))));
        $select.val(merged);

        if (!triggerChange) {
            return;
        }

        if ($select.hasClass('select2-hidden-accessible')) {
            $select.trigger('change.select2');
        } else {
            $select.trigger('change');
        }
    }

    function resetGroupSelect() {
        const $select = groupSelect();
        if (!$select.length) {
            return;
        }
        $select.val('');
        if ($select.hasClass('select2-hidden-accessible')) {
            $select.trigger('change.select2');
        }
    }

    function applyMembers(members) {
        if (!members || !members.length) {
            notify('This group has no active members.', 'warning');
            return;
        }

        const staffList = members.map(function (m) {
            return { id: String(m.id), name: m.name };
        });
        const staffIds = staffList.map(function (s) {
            return s.id;
        });

        ensureParticipantSelectOptions(members);
        mergeInternalParticipantsSelect(staffIds, false);

        if (typeof window.rebuildParticipantsTableFromSelect === 'function') {
            window.rebuildParticipantsTableFromSelect();
        } else if (typeof window.appendToInternalParticipantsTable === 'function') {
            window.appendToInternalParticipantsTable(staffList);
            mergeInternalParticipantsSelect(staffIds, true);
        } else {
            notify('Participant table is not ready.', 'error');
            return;
        }

        if (typeof window.updateTotalParticipants === 'function') {
            window.updateTotalParticipants();
        }
        if (typeof window.checkParticipantDaysWarnings === 'function') {
            setTimeout(window.checkParticipantDaysWarnings, 50);
        }

        notify('Added ' + staffList.length + ' participant(s) from the group.', 'success');
        resetGroupSelect();
    }

    function loadGroups() {
        const cfg = config();
        const $select = groupSelect();
        if (!$select.length || !cfg.listUrl) {
            return;
        }

        $.get(cfg.listUrl, function (groups) {
            $select.empty().append(NONE_OPTION_HTML);
            (groups || []).forEach(function (g) {
                const suffix = g.is_virtual ? ' (all division staff)' : (' (' + g.member_count + ')');
                $select.append(
                    $('<option></option>').val(g.id).text(g.name + suffix)
                );
            });
            $select.val('');
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.trigger('change.select2');
            }
        }).fail(function () {
            $select.empty().append(NONE_OPTION_HTML);
            notify('Could not load participant groups.', 'error');
        });
    }

    function bindApply() {
        const cfg = config();
        const buttonSelector = cfg.applyButtonSelector || '#applyParticipantGroup';

        $(document).off('click.apmParticipantGroups', buttonSelector);
        $(document).on('click.apmParticipantGroups', buttonSelector, function () {
            const groupId = groupSelect().val();
            if (!groupId) {
                notify('Select a participant group first.', 'warning');
                return;
            }

            if (!requireDates()) {
                notify(cfg.datesRequiredMessage || 'Please set activity start and end dates first.', 'warning');
                return;
            }

            const url = (cfg.membersUrlTemplate || '').replace('__GROUP__', encodeURIComponent(groupId));
            if (!url) {
                notify('Participant group configuration is missing.', 'error');
                return;
            }

            const $btn = $(this).prop('disabled', true);
            $.get(url, function (resp) {
                applyMembers(resp.members || []);
            }).fail(function () {
                notify('Could not load group members.', 'error');
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });
    }

    function initSelect2() {
        const $select = groupSelect();
        if (!$select.length) {
            return;
        }

        if ($select.hasClass('select2-hidden-accessible')) {
            try {
                $select.select2('destroy');
            } catch (e) { /* ignore */ }
        }

        $select.select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'None',
            allowClear: false,
        });
    }

    window.ApmParticipantGroups = {
        init: function (options) {
            window.ApmParticipantGroupsConfig = $.extend({
                listUrl: '',
                membersUrlTemplate: '',
                selectSelector: '#participant_group_select',
                applyButtonSelector: '#applyParticipantGroup',
                datesRequiredMessage: 'Please select both Start Date and End Date before adding participants from a group.',
            }, options || {});

            if (!groupSelect().length) {
                return;
            }

            initSelect2();
            loadGroups();
            bindApply();
        },
        reload: loadGroups,
    };

    document.addEventListener('livewire:navigated', function () {
        if (groupSelect().length && window.ApmParticipantGroupsConfig) {
            window.ApmParticipantGroups.init(window.ApmParticipantGroupsConfig);
        }
    });
})(window, window.jQuery);
