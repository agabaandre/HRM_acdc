@extends('layouts.app')

@section('title', 'RBM Activity Reporting')
@section('header', 'RBM Activity Reporting')

@section('content')
<style>
    #activity-report-form-app .arf-vuetify-app { background: transparent !important; }
    #activity-report-form-app .v-application__wrap { min-height: 0 !important; }
    #activity-report-form-app .arf-form-body .v-field__input,
    #activity-report-form-app .arf-form-body .v-label,
    #activity-report-form-app .arf-form-body .v-select__selection-text {
        color: #0f172a !important;
        opacity: 1 !important;
    }
    #activity-report-form-app .arf-form-body .v-field--disabled .v-field__input,
    #activity-report-form-app .arf-form-body .v-field--disabled .v-label {
        color: #334155 !important;
        opacity: 0.9 !important;
    }
    #activity-report-form-app .arf-section {
        margin-bottom: 1.25rem;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        background: #fff;
    }
    #activity-report-form-app .arf-section-head {
        padding: 0.65rem 1rem;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #334155;
        background: #f1f5f9;
        border-bottom: 1px solid #e2e8f0;
    }
    #activity-report-form-app .arf-section-head.arf-purple {
        background: #5b4b8a;
        color: #fff;
        border-bottom-color: #5b4b8a;
    }
    #activity-report-form-app .arf-section-body { padding: 1rem; }
    #activity-report-form-app .arf-bg-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.85rem;
    }
    @media (max-width: 900px) {
        #activity-report-form-app .arf-bg-grid { grid-template-columns: 1fr; }
    }
    #activity-report-form-app .arf-bg-card {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.85rem 1rem;
        background: #fafafa;
        min-height: 5.5rem;
    }
    #activity-report-form-app .arf-bg-card .arf-k {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 0.35rem;
    }
    #activity-report-form-app .arf-bg-card .arf-v { font-size: 0.95rem; color: #0f172a; line-height: 1.35; }
    #activity-report-form-app .arf-bg-card .arf-v.accent-purple { color: #6d28d9; font-weight: 600; }
    #activity-report-form-app .arf-bg-card .arf-v.accent-orange { color: #c2410c; font-weight: 600; }
    #activity-report-form-app .arf-hint {
        font-size: 0.8rem;
        color: #64748b;
        margin: 0.15rem 0 0.75rem;
    }
    #activity-report-form-app .arf-field-label {
        display: block;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #78716c;
        margin-bottom: 0.35rem;
    }
    #activity-report-form-app .arf-rich-block { margin-bottom: 1rem; }
    #activity-report-form-app .apm-quill-wrap .ql-editor {
        color: #0f172a;
        min-height: 120px;
        font-size: 0.95rem;
    }
    #activity-report-form-app .arf-upload-note {
        background: #fef9c3;
        border: 1px solid #fde047;
        border-radius: 8px;
        padding: 0.65rem 0.85rem;
        font-size: 0.85rem;
        color: #713f12;
        margin-bottom: 0.75rem;
    }
    #activity-report-form-app .arf-meta-row {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 1rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #e2e8f0;
    }
    @media (max-width: 700px) {
        #activity-report-form-app .arf-meta-row { grid-template-columns: 1fr; }
    }
    #activity-report-form-app .arf-meta-row .arf-k {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #64748b;
    }
</style>
<div id="activity-report-form-app" data-apm-vuetify-page="activity-report-form">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
</div>
@endsection
