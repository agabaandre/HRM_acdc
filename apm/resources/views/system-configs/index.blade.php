@extends('layouts.app')

@section('title', 'System configs')

@push('head-meta')
<link rel="stylesheet" href="{{ asset('assets/css/system-configs.css') }}?v={{ time() }}">
@endpush

@section('content')
@php
    $activeMeta = $tabs[$tab] ?? $tabs['jobs'];
@endphp

<div class="sys-config-page">
    <header class="sys-config-hero">
        <h1><i class="bx bx-cog me-2 text-success"></i>System configs</h1>
        <p>Operations, monitoring, backups, application settings and audit history — one place for platform administration.</p>
    </header>

    <div class="sys-config-toolbar">
        <nav class="sys-config-tabs" aria-label="System configuration sections">
            @foreach ($tabs as $key => $meta)
                <a
                    href="{{ route('system-configs.index', array_merge(['tab' => $key], $key === 'audit-logs' ? request()->except('tab', 'export') : [])) }}"
                    class="sys-config-tab {{ $tab === $key ? 'is-active' : '' }}"
                    wire:navigate
                    @if ($tab === $key) aria-current="page" @endif
                >
                    <i class="bx {{ $meta['icon'] }}"></i>
                    <span>{{ $meta['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="sys-config-tab-actions">
            @if ($tab === 'jobs')
                <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#envEditorModal">
                    <i class="bx bx-edit"></i> Edit environment
                </button>
            @elseif ($tab === 'backups')
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="typeof refreshStats === 'function' && refreshStats()">
                    <i class="bx bx-revision"></i> Refresh
                </button>
                <button type="button" class="btn btn-sm btn-success" onclick="typeof showDatabaseModal === 'function' && showDatabaseModal()">
                    <i class="bx bx-data"></i> Manage databases
                </button>
            @endif
        </div>
    </div>

    <p class="text-muted small mb-3">
        <i class="bx {{ $activeMeta['icon'] }} me-1"></i>{{ $activeMeta['description'] }}
    </p>

    <div class="sys-config-panel">
        @switch($tab)
            @case('jobs')
                @include('jobs.index', $panelData)
                @break
            @case('monitor')
                @include('systemd-monitor.index', $panelData)
                @break
            @case('app-settings')
                @include('system-settings.index', $panelData)
                @break
            @case('audit-logs')
                @include('audit-logs.index', $panelData)
                @break
            @case('backups')
                @include('backups.index', $panelData)
                @break
        @endswitch
    </div>
</div>
@endsection
