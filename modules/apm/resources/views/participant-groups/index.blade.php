@extends('layouts.app')

@section('title', 'Participant Groups')

@section('header', 'Participant Groups')

@section('header-actions')
<a href="{{ route('staff.index') }}" class="btn btn-outline-secondary btn-sm">
    <i class="bx bx-arrow-back me-1"></i> Staff Directory
</a>
@endsection

@push('head-meta')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/vuetify@3.7.5/dist/vuetify.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
<style>
    #participant-groups-app .pg-vuetify-app {
        background: transparent !important;
    }
    #participant-groups-app .v-application__wrap {
        min-height: 0 !important;
    }
    #participant-groups-app .pg-groups-table .v-data-table-footer {
        border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
    }
    #participant-groups-app .pg-memo-list {
        border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
        border-radius: 12px;
        overflow-y: auto;
    }
    /* Keep Vuetify overlays above Bootstrap chrome */
    .v-overlay-container {
        z-index: 20000 !important;
    }
</style>
@endpush

@section('content')
<div id="participant-groups-app">
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading participant groups…</p>
    </div>
</div>

<script>
    window.ParticipantGroupsPageConfig = @json($pageConfig);
</script>
@endsection

@push('scripts')
<script src="https://unpkg.com/vue@3.5.13/dist/vue.global.prod.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vuetify@3.7.5/dist/vuetify.min.js"></script>
<script src="{{ asset('js/participant-groups-app.js') }}?v=1"></script>
@endpush
