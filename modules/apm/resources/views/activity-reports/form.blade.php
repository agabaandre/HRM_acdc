@extends('layouts.app')

@section('title', 'Activity Report')
@section('header', 'Activity Report')

@section('content')
<style>
    #activity-report-form-app .arf-vuetify-app { background: transparent !important; }
    #activity-report-form-app .v-application__wrap { min-height: 0 !important; }
</style>
<div id="activity-report-form-app" data-apm-vuetify-page="activity-report-form">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
</div>
@endsection
