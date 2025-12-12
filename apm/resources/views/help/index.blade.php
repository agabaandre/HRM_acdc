@extends('layouts.app')

@section('title', 'Help & Documentation')
@section('header', 'Help & Documentation')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h4 class="mb-0">
                        <i class="fas fa-book me-2 text-primary"></i>APM System Documentation
                    </h4>
                </div>
                <div class="card-body">
                    <p class="lead">Welcome to the APM Help Center. Select a guide to get started.</p>
                    
                    <div class="row g-4 mt-3">
                        <!-- User Guide Card -->
                        <div class="col-md-6">
                            <div class="card h-100 border-primary">
                                <div class="card-body">
                                    <div class="d-flex align-items-start mb-3">
                                        <div class="flex-shrink-0">
                                            <div class="bg-primary bg-opacity-10 rounded-circle p-3">
                                                <i class="fas fa-user fa-2x text-primary"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h5 class="card-title mb-1">User Guide</h5>
                                            <p class="text-muted small mb-0">For document creators</p>
                                        </div>
                                    </div>
                                    <p class="card-text">
                                        Learn how to create and manage:
                                    </p>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-check text-success me-2"></i>Matrices</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Special Memos</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Single Memos</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Non-Travel Memos</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Change Requests</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Service Requests</li>
                                        <li><i class="fas fa-check text-success me-2"></i>ARF Requests</li>
                                    </ul>
                                    <a href="{{ route('help.user-guide') }}" class="btn btn-primary w-100 mt-3">
                                        <i class="fas fa-book-open me-2"></i>Open User Guide
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Approvers Guide Card -->
                        <div class="col-md-6">
                            <div class="card h-100 border-info">
                                <div class="card-body">
                                    <div class="d-flex align-items-start mb-3">
                                        <div class="flex-shrink-0">
                                            <div class="bg-info bg-opacity-10 rounded-circle p-3">
                                                <i class="fas fa-user-check fa-2x text-info"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h5 class="card-title mb-1">Approvers Guide</h5>
                                            <p class="text-muted small mb-0">For approvers</p>
                                        </div>
                                    </div>
                                    <p class="card-text">
                                        Learn how to approve documents:
                                    </p>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-check text-info me-2"></i>Understanding approval workflow</li>
                                        <li><i class="fas fa-check text-info me-2"></i>Approving documents</li>
                                        <li><i class="fas fa-check text-info me-2"></i>Returning documents</li>
                                        <li><i class="fas fa-check text-info me-2"></i>Rejecting documents</li>
                                        <li><i class="fas fa-check text-info me-2"></i>Best practices</li>
                                        <li><i class="fas fa-check text-info me-2"></i>Approval trail tracking</li>
                                    </ul>
                                    <a href="{{ route('help.approvers-guide') }}" class="btn btn-info w-100 mt-3">
                                        <i class="fas fa-clipboard-check me-2"></i>Open Approvers Guide
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <!-- Additional Resources -->
                    <div class="row">
                        <div class="col-12">
                            <h5 class="mb-3">
                                <i class="fas fa-link me-2 text-secondary"></i>Additional Resources
                            </h5>
                            <div class="list-group">
                                <a href="{{ url('../apm/documentation/README.md') }}" target="_blank" class="list-group-item list-group-item-action">
                                    <i class="fas fa-book me-2"></i>Complete APM Documentation
                                </a>
                                <a href="{{ url('../apm/documentation/DEPLOYMENT.md') }}" target="_blank" class="list-group-item list-group-item-action">
                                    <i class="fas fa-server me-2"></i>Deployment Guide
                                </a>
                                <a href="{{ url('../apm/documentation/QUEUE_SETUP_GUIDE.md') }}" target="_blank" class="list-group-item list-group-item-action">
                                    <i class="fas fa-tasks me-2"></i>Queue Setup Guide
                                </a>
                                <a href="{{ url('../apm/README_BACKUP.md') }}" target="_blank" class="list-group-item list-group-item-action">
                                    <i class="fas fa-database me-2"></i>Database Backup System
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .screenshot-placeholder {
        background: #f8f9fa;
        border: 2px dashed #dee2e6;
        border-radius: 8px;
        padding: 40px;
        margin: 20px 0;
        text-align: center;
    }
    
    .screenshot-placeholder .placeholder-content {
        color: #6c757d;
    }
    
    .screenshot-placeholder i {
        font-size: 3rem;
        margin-bottom: 10px;
        color: #adb5bd;
    }
    
    .screenshot-placeholder p {
        font-weight: 600;
        margin: 10px 0 5px 0;
        color: #495057;
    }
    
    .screenshot-placeholder small {
        color: #868e96;
        font-style: italic;
    }
</style>
@endpush

