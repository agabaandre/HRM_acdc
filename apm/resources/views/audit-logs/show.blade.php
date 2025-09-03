@extends('layouts.app')

@section('title', 'Audit Log Details')

@section('header', 'Audit Log Details')

@section('header-actions')
<div class="d-flex gap-2">
    <a href="{{ route('audit-logs.index') }}" class="btn btn-secondary">
        <i class="bx bx-arrow-back"></i> Back to Logs
    </a>
</div>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bx bx-info-circle me-2 text-primary"></i>
                    Audit Log #{{ $auditLog->id }}
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <!-- Basic Information -->
                    <div class="col-md-6">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="card-title text-primary mb-3">
                                    <i class="bx bx-user me-2"></i>User Information
                                </h6>
                                <div class="row g-2">
                                    <div class="col-4"><strong>User ID:</strong></div>
                                    <div class="col-8">{{ $auditLog->user_id ?: 'N/A' }}</div>
                                    
                                    <div class="col-4"><strong>Name:</strong></div>
                                    <div class="col-8">{{ $auditLog->user_name ?: 'System' }}</div>
                                    
                                    <div class="col-4"><strong>Email:</strong></div>
                                    <div class="col-8">{{ $auditLog->user_email ?: 'N/A' }}</div>
                                    
                                    <div class="col-4"><strong>IP Address:</strong></div>
                                    <div class="col-8"><code>{{ $auditLog->ip_address ?: 'N/A' }}</code></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Information -->
                    <div class="col-md-6">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="card-title text-primary mb-3">
                                    <i class="bx bx-activity me-2"></i>Action Information
                                </h6>
                                <div class="row g-2">
                                    <div class="col-4"><strong>Action:</strong></div>
                                    <div class="col-8">
                                        <span class="badge {{ $auditLog->action_badge_class }}">
                                            <i class="bx {{ $auditLog->action_icon }} me-1"></i>
                                            {{ $auditLog->action }}
                                        </span>
                                    </div>
                                    
                                    <div class="col-4"><strong>Resource:</strong></div>
                                    <div class="col-8">{{ $auditLog->resource_type }}</div>
                                    
                                    <div class="col-4"><strong>Resource ID:</strong></div>
                                    <div class="col-8">{{ $auditLog->resource_id ?: 'N/A' }}</div>
                                    
                                    <div class="col-4"><strong>Method:</strong></div>
                                    <div class="col-8">
                                        <span class="badge bg-info">{{ $auditLog->method }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Request Information -->
                    <div class="col-12">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="card-title text-primary mb-3">
                                    <i class="bx bx-link me-2"></i>Request Information
                                </h6>
                                <div class="row g-2">
                                    <div class="col-2"><strong>Route:</strong></div>
                                    <div class="col-10">
                                        <code>{{ $auditLog->route_name ?: 'N/A' }}</code>
                                    </div>
                                    
                                    <div class="col-2"><strong>URL:</strong></div>
                                    <div class="col-10">
                                        <code class="text-break">{{ $auditLog->url }}</code>
                                    </div>
                                    
                                    <div class="col-2"><strong>User Agent:</strong></div>
                                    <div class="col-10">
                                        <small class="text-muted">{{ $auditLog->user_agent ?: 'N/A' }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="col-12">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="card-title text-primary mb-3">
                                    <i class="bx bx-message-square-detail me-2"></i>Description
                                </h6>
                                <p class="mb-0">{{ $auditLog->description ?: 'No description available' }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Data Changes -->
                    @if($auditLog->old_values || $auditLog->new_values)
                        <div class="col-12">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="card-title text-primary mb-3">
                                        <i class="bx bx-data me-2"></i>Data Changes
                                    </h6>
                                    
                                    <div class="row">
                                        @if($auditLog->old_values)
                                            <div class="col-md-6">
                                                <h6 class="text-danger">Old Values</h6>
                                                <pre class="bg-white p-3 rounded border"><code>{{ json_encode($auditLog->old_values, JSON_PRETTY_PRINT) }}</code></pre>
                                            </div>
                                        @endif
                                        
                                        @if($auditLog->new_values)
                                            <div class="col-md-6">
                                                <h6 class="text-success">New Values</h6>
                                                <pre class="bg-white p-3 rounded border"><code>{{ json_encode($auditLog->new_values, JSON_PRETTY_PRINT) }}</code></pre>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Metadata -->
                    @if($auditLog->metadata)
                        <div class="col-12">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="card-title text-primary mb-3">
                                        <i class="bx bx-cog me-2"></i>Metadata
                                    </h6>
                                    <pre class="bg-white p-3 rounded border"><code>{{ json_encode($auditLog->metadata, JSON_PRETTY_PRINT) }}</code></pre>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Timestamps -->
                    <div class="col-12">
                        <div class="card border-0 bg-light">
                            <div class="card-body">
                                <h6 class="card-title text-primary mb-3">
                                    <i class="bx bx-time me-2"></i>Timestamps
                                </h6>
                                <div class="row g-2">
                                    <div class="col-3"><strong>Created:</strong></div>
                                    <div class="col-9">
                                        {{ $auditLog->created_at->format('F j, Y g:i A') }}
                                        <small class="text-muted">({{ $auditLog->created_at->diffForHumans() }})</small>
                                    </div>
                                    
                                    <div class="col-3"><strong>Updated:</strong></div>
                                    <div class="col-9">
                                        {{ $auditLog->updated_at->format('F j, Y g:i A') }}
                                        <small class="text-muted">({{ $auditLog->updated_at->diffForHumans() }})</small>
                                    </div>
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
