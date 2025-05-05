@extends('layouts.app')

@section('title', 'Approval History')

@section('content')
  <div class="container">
    <div class="row">
    <div class="col-lg-12">
      <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="card-title">Approval History for Memo #{{ $memo->id }}</h4>
        <a href="{{ route('approvals.index') }}" class="btn btn-secondary">Back to Approvals</a>
      </div>
      <div class="card-body">
        <div class="mb-4">
        <h5 class="border-bottom pb-2">Memo Information</h5>
        <div class="row">
          <div class="col-md-3 fw-bold">Title:</div>
          <div class="col-md-9">{{ $memo->title }}</div>
        </div>
        <div class="row">
          <div class="col-md-3 fw-bold">Country:</div>
          <div class="col-md-9">{{ $memo->country }}</div>
        </div>
        <div class="row">
          <div class="col-md-3 fw-bold">Created:</div>
          <div class="col-md-9">{{ date('M d, Y H:i', strtotime($memo->created_at)) }}</div>
        </div>
        </div>

        <div class="mb-4">
        <h5 class="border-bottom pb-2">Approval Timeline</h5>

        <!-- In a real implementation, you would fetch approval history from a database table -->
        <!-- This is a placeholder that would show the approval history -->
        <div class="timeline">
          <div class="timeline-item border-start border-2 border-primary ps-3 pb-3 position-relative">
          <div class="position-absolute"
            style="left: -8px; top: 0; width: 16px; height: 16px; border-radius: 50%; background-color: #0d6efd;">
          </div>
          <div class="mb-1 text-secondary">May 5, 2025 10:30 AM</div>
          <div class="fw-bold">Created by User ID: {{ $memo->user_id }}</div>
          <div>Memo created and submitted for approval</div>
          </div>

          <!-- Example of what an approval might look like -->
          <div class="timeline-item border-start border-2 border-success ps-3 pb-3 position-relative">
          <div class="position-absolute"
            style="left: -8px; top: 0; width: 16px; height: 16px; border-radius: 50%; background-color: #198754;">
          </div>
          <div class="mb-1 text-secondary">Sample: May 6, 2025 9:15 AM</div>
          <div class="fw-bold">Division Head (User ID: 1)</div>
          <div>
            <span class="badge bg-success">Approved</span>
            <p class="mt-2">Sample comment: This memo is approved for the next step.</p>
          </div>
          </div>

          <!-- Example of what a rejection might look like -->
          <div class="timeline-item border-start border-2 border-danger ps-3 pb-3 position-relative">
          <div class="position-absolute"
            style="left: -8px; top: 0; width: 16px; height: 16px; border-radius: 50%; background-color: #dc3545;">
          </div>
          <div class="mb-1 text-secondary">Sample: May 7, 2025 2:45 PM</div>
          <div class="fw-bold">Finance Officer (User ID: 4)</div>
          <div>
            <span class="badge bg-danger">Rejected</span>
            <p class="mt-2">Sample comment: Budget allocation is not sufficient for this request.</p>
          </div>
          </div>

          <!-- Example of what a return for correction might look like -->
          <div class="timeline-item border-start border-2 border-warning ps-3 pb-3 position-relative">
          <div class="position-absolute"
            style="left: -8px; top: 0; width: 16px; height: 16px; border-radius: 50%; background-color: #ffc107;">
          </div>
          <div class="mb-1 text-secondary">Sample: May 8, 2025 11:20 AM</div>
          <div class="fw-bold">Procurement Officer (User ID: 20)</div>
          <div>
            <span class="badge bg-warning text-dark">Returned for Correction</span>
            <p class="mt-2">Sample comment: Please provide more details about the specifications.</p>
          </div>
          </div>
        </div>
        </div>
      </div>
      </div>
    </div>
    </div>
  </div>

  <style>
    .timeline {
    position: relative;
    padding: 20px 0;
    }

    .timeline-item {
    margin-bottom: 20px;
    }
  </style>
@endsection