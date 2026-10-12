@extends('layouts.app')

@section('title', 'Reporting')
@section('header', 'Reporting')

@section('content')
@livewire('app-page', ['view' => 'pages.reports-index-content', 'data' => []])
@endsection
