@extends('layouts.app')

@section('title', 'Non-Travel Memo Status')

@section('header', 'Non-Travel Memo Approval Status')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bx bx-info-circle me-2 text-primary"></i>Status & Approval Trail</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Status:</strong> {{ ucfirst($nonTravel->overall_status ?? 'draft') }}
                    </div>
                    <div class="mb-3">
                        <strong>Current Workflow:</strong> {{ $nonTravel->forwardWorkflow->workflow_name ?? 'N/A' }}
                    </div>
                    <div class="mb-3">
                        <strong>Approval Level:</strong> {{ $nonTravel->approval_level ?? '-' }}
                    </div>
                    <hr>
                    <h6 class="fw-semibold mb-3">Approval Trail</h6>
                    @php $trails = $nonTravel->serviceRequestApprovalTrails ?? []; @endphp
                    @if(count($trails) > 0)
                        <ul class="list-group">
                            @foreach($trails as $trail)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>{{ ucfirst($trail->action) }}</strong>
                                        <span class="text-muted small ms-2">{{ $trail->created_at->format('Y-m-d H:i') }}</span>
                                        <div class="small text-muted">{{ $trail->remarks }}</div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="alert alert-secondary">No approval actions taken yet.</div>
                    @endif
                </div>
            </div>
            <a href="{{ route('non-travel.show', $nonTravel) }}" class="btn btn-outline-primary">
                <i class="bx bx-arrow-back me-1"></i> Back to Memo
            </a>
        </div>
    </div>
</div>
@endsection 