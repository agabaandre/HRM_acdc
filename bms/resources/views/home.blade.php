@extends('layouts.app')

@section('title', 'Home')

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Workflow Management System</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Memos</h5>
                                        <p class="card-text">Create and manage memos</p>
                                        <a href="{{ route('memos.index') }}" class="btn btn-primary">View Memos</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Workflows</h5>
                                        <p class="card-text">Manage workflow definitions</p>
                                        <a href="{{ route('workflows.index') }}" class="btn btn-primary">View Workflows</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Approvals</h5>
                                        <p class="card-text">Handle pending approvals</p>
                                        <a href="{{ route('approvals.index') }}" class="btn btn-primary">View Approvals</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-4">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Divisions</h5>
                                        <p class="card-text">Manage organizational divisions</p>
                                        <a href="{{ route('divisions.index') }}" class="btn btn-primary">View Divisions</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection