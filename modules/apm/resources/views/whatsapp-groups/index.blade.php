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
    #whatsapp-groups-app .wg-chat-card {
        background: #f7f8fa !important;
        overflow: hidden;
    }
    #whatsapp-groups-app .wg-chat-header {
        background: #fff;
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
    }
    #whatsapp-groups-app .wg-chat-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #25D366, #128C7E);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        font-weight: 700;
        flex-shrink: 0;
    }
    #whatsapp-groups-app .wg-chat-avatar-sm {
        width: 32px;
        height: 32px;
        font-size: 0.7rem;
    }
    #whatsapp-groups-app .wg-chat-scroll {
        overflow: auto;
        background:
            radial-gradient(circle at 20% 20%, rgba(37, 211, 102, 0.05), transparent 40%),
            radial-gradient(circle at 80% 0%, rgba(18, 140, 126, 0.06), transparent 35%),
            #efeae2;
    }
    #whatsapp-groups-app .wg-bubble {
        max-width: min(78%, 520px);
        border-radius: 14px;
        padding: 10px 12px;
        box-shadow: 0 1px 1px rgba(15, 23, 42, 0.06);
    }
    #whatsapp-groups-app .wg-bubble-in {
        background: #fff;
        border-top-left-radius: 4px;
    }
    #whatsapp-groups-app .wg-bubble-out {
        background: #d9fdd3;
        border-top-right-radius: 4px;
    }
    #whatsapp-groups-app .wg-chat-time {
        opacity: 0.7;
        white-space: nowrap;
    }
    #whatsapp-groups-app .wg-chat-image {
        display: block;
        max-width: 100%;
        max-height: 280px;
        border-radius: 10px;
        object-fit: cover;
    }
    #whatsapp-groups-app .wg-chat-composer {
        background: #fff;
        border-top: 1px solid rgba(15, 23, 42, 0.08);
    }
    #whatsapp-groups-app .wg-chat-attach-thumb {
        width: 48px;
        height: 48px;
        object-fit: cover;
        border-radius: 8px;
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
