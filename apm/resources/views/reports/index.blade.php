@extends('layouts.app')

@section('title', 'Reports')
@section('header', 'Reports')

@section('content')
@livewire('app-page', ['view' => 'pages.reports-index-content', 'data' => []])
@endsection
