@extends('layouts.app')

@section('title', 'WhatsApp groups')

@section('header', 'WhatsApp group management')

@section('header-actions')
<a href="{{ route('staff.index') }}" class="btn btn-outline-secondary btn-sm me-2" wire:navigate>
    <i class="fas fa-users me-1"></i> Staff list
</a>
<a href="{{ route('system-configs.index', ['tab' => 'whatsapp']) }}" class="btn btn-outline-success btn-sm" wire:navigate>
    <i class="bx bxl-whatsapp me-1"></i> WhatsApp settings
</a>
@endsection

@push('head-meta')
<style>
    #whatsapp-groups-app .wg-vuetify-app { background: transparent !important; }
    #whatsapp-groups-app .v-application__wrap { min-height: 0 !important; }
    #whatsapp-groups-app .wg-stat-card {
        border-left: 3px solid var(--wg-accent, #25D366) !important;
        height: 100%;
    }
</style>
@endpush

@section('content')
<div id="whatsapp-groups-app" data-apm-vuetify-page="whatsapp-groups">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading WhatsApp groups…</p>
    </div>
</div>
@endsection
