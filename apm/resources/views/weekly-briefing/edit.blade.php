@extends('layouts.app')

@section('title', 'Edit Weekly brief')
@section('header', 'Edit Weekly brief')

@push('head-meta')
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet" />
<style>
    #weekly-briefing-edit-app .wb-edit-vuetify-app {
        background: transparent !important;
    }
    #weekly-briefing-edit-app .v-application__wrap {
        min-height: 0 !important;
    }
    #weekly-briefing-edit-app .wb-edit-table-wrap {
        overflow-x: auto;
        border: 1px solid rgba(0, 0, 0, 0.08);
        border-radius: 8px;
    }
    #weekly-briefing-edit-app .wb-edit-table {
        width: 100%;
        margin-bottom: 0;
        border-collapse: collapse;
        background: #fff;
    }
    #weekly-briefing-edit-app .wb-edit-table thead th {
        background: #f8fafc;
        color: rgba(0, 0, 0, 0.65);
        font-size: 0.6875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        vertical-align: middle;
    }
    #weekly-briefing-edit-app .wb-edit-table tbody td {
        padding: 0.75rem;
        vertical-align: top;
        border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    }
    #weekly-briefing-edit-app .wb-edit-table tbody tr:last-child td {
        border-bottom: none;
    }
    #weekly-briefing-edit-app .wb-quill-editor {
        border: 1px solid rgba(0, 0, 0, 0.12);
        border-radius: 8px;
        background: #fff;
    }
    #weekly-briefing-edit-app .wb-quill-editor .ql-editor {
        min-height: 120px;
    }
    #weekly-briefing-edit-app .wb-quill-editor .ql-toolbar {
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
        flex-wrap: wrap;
    }
    #weekly-briefing-edit-app .wb-edit-fieldset {
        border: 0;
        margin: 0;
        padding: 0;
        min-width: 0;
    }
    #weekly-briefing-edit-app .wb-edit-fieldset:disabled {
        opacity: 1;
    }
    #weekly-briefing-edit-app .wb-edit-fieldset:disabled .ql-toolbar {
        display: none;
    }
    #weekly-briefing-edit-app .wb-edit-fieldset:disabled .wb-quill-editor {
        border-color: rgba(0, 0, 0, 0.08) !important;
        background: #f8fafc !important;
    }
    #weekly-briefing-edit-app .wb-edit-fieldset:disabled .ql-editor {
        cursor: default;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
@endpush

@section('content')
<div id="weekly-briefing-edit-app" data-apm-vuetify-page="weekly-briefing-edit">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-success" role="status"></div>
        <p class="mt-2 mb-0">Loading weekly brief…</p>
    </div>
</div>
@endsection
