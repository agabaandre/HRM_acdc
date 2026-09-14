@once
@push('head-meta')
<link rel="stylesheet" href="{{ asset('css/apm-vuetify-like-forms.css') }}?v=5">
@endpush
@push('scripts')
<script>
window.renumberParticipantsTable = function () {
    var n = 0;
    jQuery('#participantsTableBody tr[data-participant-id]').not('.participant-days-warning-row').each(function () {
        n += 1;
        jQuery(this).find('td.participants-col-index').text(n);
    });
};
</script>
@endpush
@endonce
