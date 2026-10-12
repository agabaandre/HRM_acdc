@extends('layouts.app')

@section('title', 'Activity Reports')
@section('header', 'Activity Reports')

@section('content')
<style>
    #reporting-registry-app .rr-vuetify-app { background: transparent !important; }
    #reporting-registry-app .v-application__wrap { min-height: 0 !important; }
</style>
<div id="reporting-registry-app" data-apm-vuetify-page="reporting-registry">
    <script type="application/json" class="apm-page-config">@json($pageConfig)</script>
</div>
@endsection
