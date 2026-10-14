@extends('layouts.app')

@section('title', 'Budget Execution')
@section('header', 'Budget Execution')

@push('head-meta')
<style>
    #budget-execution-app .be-vuetify-app { background: transparent !important; }
    #budget-execution-app .v-application__wrap { min-height: 0 !important; }
    #budget-execution-app .be-hero {
        background: #fff;
        border: 1px solid rgba(15, 23, 42, 0.08);
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 6px 16px rgba(15, 23, 42, 0.04);
        color: #0f172a;
    }
    #budget-execution-app .be-hero h2 {
        color: #0f172a !important;
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }
    #budget-execution-app .be-hero .text-body-2 { color: #64748b !important; }
    #budget-execution-app .be-asat {
        margin: 0.45rem 0 0; font-size: 0.82rem; color: #64748b;
    }
    #budget-execution-app .be-asat strong { color: #119a48; font-weight: 700; }
    #budget-execution-app .be-kpi {
        display: flex; justify-content: space-between; align-items: center; gap: 0.85rem;
        min-height: 108px; background: #fff;
        border: 1px solid rgba(15, 23, 42, 0.08);
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 6px 16px rgba(15, 23, 42, 0.04);
    }
    #budget-execution-app .be-kpi-body > span {
        display: block; font-size: 0.78rem; color: #64748b; font-weight: 500;
    }
    #budget-execution-app .be-kpi-body strong {
        display: block; margin-top: 0.4rem; font-size: 1.7rem; font-weight: 800;
        letter-spacing: -0.02em; line-height: 1.1; color: #1e293b;
    }
    #budget-execution-app .be-kpi-body small {
        display: block; margin-top: 0.35rem; font-size: 0.75rem; color: #94a3b8;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    #budget-execution-app .be-kpi-icon {
        flex: 0 0 auto; width: 2.75rem; height: 2.75rem;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 2px; background: #eef2f7; color: #475569;
    }
    #budget-execution-app .be-kpi-icon i { font-size: 1.15rem; }
    #budget-execution-app .be-kpi--green .be-kpi-icon { background: #e3f6eb; color: #119a48; }
    #budget-execution-app .be-kpi--gold .be-kpi-icon { background: #f7efd9; color: #b8924a; }
    #budget-execution-app .be-kpi--maroon .be-kpi-icon { background: #f8e4ea; color: #911c39; }
    #budget-execution-app .be-fund {
        display: inline-block; padding: 0.1rem 0.45rem; border-radius: 999px;
        background: #e8f6ee; color: #0f5132; font-size: 0.78rem; font-weight: 700;
    }
    #budget-execution-app .be-row-link { cursor: pointer; }
    #budget-execution-app .be-div-card {
        border: 1px solid rgba(15, 23, 42, 0.08);
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03), 0 8px 18px rgba(15, 23, 42, 0.04);
    }
    #budget-execution-app .be-metrics {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 0.65rem;
    }
    #budget-execution-app .be-metric {
        display: flex; align-items: center; gap: 0.65rem;
        min-height: 74px; padding: 0.7rem 0.8rem;
        border-radius: 2px; border: 1px solid transparent;
        background: #f8fafc;
    }
    #budget-execution-app .be-metric > div { min-width: 0; }
    #budget-execution-app .be-metric span:not(.be-metric-icon) {
        display: block; font-size: 0.72rem; font-weight: 600;
        letter-spacing: 0.02em; text-transform: uppercase; color: #64748b;
    }
    #budget-execution-app .be-metric strong {
        display: block; margin-top: 0.2rem; font-size: 0.98rem; font-weight: 800;
        color: #0f172a; line-height: 1.15; white-space: nowrap;
        overflow: hidden; text-overflow: ellipsis;
    }
    #budget-execution-app .be-metric-icon {
        flex: 0 0 auto; width: 2.15rem; height: 2.15rem;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 2px; background: #e2e8f0; color: #475569;
    }
    #budget-execution-app .be-metric-icon i { font-size: 0.95rem; }
    #budget-execution-app .be-metric--green { background: #edf9f1; border-color: #c9ebd5; }
    #budget-execution-app .be-metric--green .be-metric-icon { background: #d8f3e3; color: #119a48; }
    #budget-execution-app .be-metric--gold { background: #fbf6ea; border-color: #edd9a8; }
    #budget-execution-app .be-metric--gold .be-metric-icon { background: #f4e6c2; color: #b8924a; }
    #budget-execution-app .be-metric--blue { background: #eef5fb; border-color: #c7dbf0; }
    #budget-execution-app .be-metric--blue .be-metric-icon { background: #d9e9f8; color: #385cad; }
    #budget-execution-app .be-metric--slate { background: #f3f5f8; border-color: #d5dde8; }
    #budget-execution-app .be-metric--slate .be-metric-icon { background: #e4eaf1; color: #475569; }
    #budget-execution-app .be-metric--exec-high { background: #edf9f1; border-color: #c9ebd5; }
    #budget-execution-app .be-metric--exec-high .be-metric-icon { background: #d8f3e3; color: #119a48; }
    #budget-execution-app .be-metric--exec-high strong { color: #0f7a38; }
    #budget-execution-app .be-metric--exec-mid { background: #fff8e8; border-color: #f0d48a; }
    #budget-execution-app .be-metric--exec-mid .be-metric-icon { background: #fceec4; color: #b8860b; }
    #budget-execution-app .be-metric--exec-mid strong { color: #9a7209; }
    #budget-execution-app .be-metric--exec-low { background: #fdf0f3; border-color: #efc2cd; }
    #budget-execution-app .be-metric--exec-low .be-metric-icon { background: #f8dce4; color: #911c39; }
    #budget-execution-app .be-metric--exec-low strong { color: #911c39; }
    #budget-execution-app .be-metric--exec-muted { background: #f8fafc; border-color: #e2e8f0; }
    #budget-execution-app .be-exec-pill {
        display: inline-block; padding: 0.12rem 0.45rem; border-radius: 999px;
        font-size: 0.78rem; font-weight: 700;
    }
    #budget-execution-app .be-exec-pill--high { background: #e3f6eb; color: #0f7a38; }
    #budget-execution-app .be-exec-pill--mid { background: #fceec4; color: #9a7209; }
    #budget-execution-app .be-exec-pill--low { background: #f8dce4; color: #911c39; }
    #budget-execution-app .be-exec-pill--muted { background: #e2e8f0; color: #475569; }
    #budget-execution-app .be-chart { min-height: 320px; }
    @media (max-width: 1100px) {
        #budget-execution-app .be-metrics { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media (max-width: 700px) {
        #budget-execution-app .be-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
</style>
@endpush

@section('content')
<div id="budget-execution-app" data-apm-vuetify-page="budget-execution">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
    <div class="text-center py-5 text-muted">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-2 mb-0">Loading budget execution from Finance…</p>
    </div>
</div>
@endsection
