@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@include('pages.home-content', compact('user', 'permissions', 'base_url'))
@endsection
