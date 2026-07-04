@if(user_session('division_id'))
@php
    $pgListUrl = parse_url(route('participant-groups.list'), PHP_URL_PATH) ?: route('participant-groups.list');
    $pgMembersUrl = parse_url(route('participant-groups.members', ['group' => '__GROUP__']), PHP_URL_PATH)
        ?: route('participant-groups.members', ['group' => '__GROUP__']);
@endphp
<script src="{{ asset('js/apm-participant-groups.js') }}?v=2"></script>
<script>
$(function () {
    if (typeof window.ApmParticipantGroups === 'undefined') {
        return;
    }
    window.ApmParticipantGroups.init({
        listUrl: @json($pgListUrl),
        membersUrlTemplate: @json($pgMembersUrl),
        requireDates: function () {
            return !!($('#date_from').val() && $('#date_to').val());
        }
    });
});
</script>
@endif
