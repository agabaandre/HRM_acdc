@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<style>
  body {
    font-family: "Segoe UI", sans-serif;
  }

  .dashboard-title {
    font-size: 1.8rem;
    color: #119A48;
    font-weight: bold;
    text-align: center;
    margin: 2rem 0 1.5rem;
  }

  .dashboard-card {
    height: 300px;
    padding: 1.5rem;
    transition: all 0.3s ease-in-out;
    font-size: 0.9rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    border-radius: 1rem;
    background: white;
    border: 1px solid #ddd;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
    position: relative;
    overflow: hidden;
    margin-bottom: 1.5rem;
  }

  .dashboard-card:hover {
    box-shadow: 0 0 12px #911c3966;
    transform: translateY(-4px);
  }

  .dashboard-card h6 {
    font-weight: 700;
    font-size: 1.1rem;
    color: #911C39;
    margin-bottom: 0.5rem;
  }

  .dashboard-card p {
    font-size: 0.85rem;
    color: #5F5F5F;
    margin: 0;
  }

  .dashboard-container .col-lg-3 {
    margin-bottom: 1.5rem;
  }

  .dashboard-icon {
    width: 60px;
    height: 60px;
    background-color: #f4f4f4;
    color: #C3A366;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 1rem auto 0;
    font-size: 1.5rem;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
  }

  .pending-badge {
    font-size: 0.8rem;
    padding: 0.4rem 0.6rem;
  }

  .btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
  }
</style>

<div class="container-fluid">
  <h2 class="dashboard-title">Approvals Management</h2>
  
  <div class="row justify-content-center dashboard-container">
    
    {{-- Quarterly Travel Matrix --}}
    <div class="col-lg-3 col-md-6">
      <div class="dashboard-card text-center">
        <div>
          <h6>
            Quarterly Travel Matrix
          
          </h6>
          <p>Plan and track quarterly travel for all staff.</p>
        </div>
        <div>
          <a href="{{ route('matrices.index') }}" class="btn btn-success btn-sm mt-2">Open</a>
          <div class="dashboard-icon mt-3"><i class="fas fa-calendar-alt"></i></div>
        </div>
        <div class="mt-2">
          <strong>Pending Action:</strong>
          <span class="badge bg-warning text-dark pending-badge">
            {{ get_staff_unread_notifications_count() }}
          </span>
        </div>
        <div class="mt-2">
          <a href="{{ route('matrices.index') }}" class="btn btn-success btn-sm">
            <i class="fas fa-list"></i> All QMs
          </a>
        </div>
      </div>
    </div>

    {{-- Non-Travel --}}
    <div class="col-lg-3 col-md-6">
      <div class="dashboard-card text-center">
        <div>
          <h6>Non-Travel</h6>
          <p>Manage activities that are not related to travel logistics.</p>
        </div>
        <div>
          <a href="{{ url('non-travel') }}" class="btn btn-success btn-sm mt-2">Open</a>
          <div class="dashboard-icon mt-3"><i class="fas fa-walking"></i></div>
        </div>
        <div class="mt-2">
          <strong>Pending Action:</strong>
          <span class="badge bg-warning text-dark pending-badge">
            {{ get_staff_pending_action_count('non-travel') }}
          </span>
        </div>
        <div class="mt-2">
          <a href="{{ url('non-travel') }}" class="btn btn-success btn-sm">
            <i class="fas fa-list"></i> All Non-Travel
          </a>
        </div>
      </div>
    </div>

    {{-- Special Memo --}}
    <div class="col-lg-3 col-md-6">
      <div class="dashboard-card text-center">
        <div>
          <h6>Special Memo</h6>
          <p>Create and send special memos for specific activities.</p>
        </div>
        <div>
          <a href="{{ url('special-memo') }}" class="btn btn-success btn-sm mt-2">Open</a>
          <div class="dashboard-icon mt-3"><i class="fas fa-envelope-open-text"></i></div>
        </div>
        <div class="mt-2">
          <strong>Pending Action:</strong>
          <span class="badge bg-warning text-dark pending-badge">
            {{ get_staff_pending_action_count('special-memo') }}
          </span>
        </div>
        <div class="mt-2">
          <a href="{{ url('special-memo') }}" class="btn btn-success btn-sm">
            <i class="fas fa-list-alt"></i> All Memos
          </a>
        </div>
      </div>
    </div>

    {{-- Request for Services --}}
    <div class="col-lg-3 col-md-6">
      <div class="dashboard-card text-center">
        <div>
          <h6>Request for Services</h6>
          <p>Submit requests for tickets, DSA, procurement, or imprest.</p>
        </div>
        <div>
          <a href="{{ url('service-requests') }}" class="btn btn-success btn-sm mt-2">Open</a>
          <div class="dashboard-icon mt-3"><i class="fas fa-tools"></i></div>
        </div>
        <div class="mt-2">
          <strong>Pending Action:</strong>
          <span class="badge bg-warning text-dark pending-badge">
            {{ get_staff_pending_action_count('service-requests') }}
          </span>
        </div>
        <div class="mt-2">
          <a href="{{ url('service-requests') }}" class="btn btn-success btn-sm">
            <i class="fas fa-tasks"></i> All Requests
          </a>
        </div>
      </div>
    </div>

    {{-- Request for ARF --}}
    <div class="col-lg-3 col-md-6">
      <div class="dashboard-card text-center">
        <div>
          <h6>Request for ARF</h6>
          <p>Submit your Activity Request Form for approvals.</p>
        </div>
        <div>
          <a href="{{ url('request-arf') }}" class="btn btn-success btn-sm mt-2">Open</a>
          <div class="dashboard-icon mt-3"><i class="fas fa-file-signature"></i></div>
        </div>
        <div class="mt-2">
          <strong>Pending Action:</strong>
          <span class="badge bg-warning text-dark pending-badge">
            {{ get_staff_pending_action_count('request-arf') }}
          </span>
        </div>
        <div class="mt-2">
          <a href="{{ url('request-arf') }}" class="btn btn-success btn-sm">
            <i class="fas fa-file-signature"></i> All ARFs
          </a>
        </div>
      </div>
    </div>

    {{-- Single Memo --}}
    <div class="col-lg-3 col-md-6">
      <div class="dashboard-card text-center">
        <div>
          <h6>Single Memo</h6>
          <p>View Submitted Single Memos.</p>
        </div>
        <div>
          <a href="#" class="btn btn-success btn-sm mt-2">My Single Memos</a>
          <div class="dashboard-icon mt-3"><i class="fas fa-file-alt"></i></div>
        </div>
        <div class="mt-2">
          <strong>Pending Action:</strong>
          <span class="badge bg-warning text-dark pending-badge">
            {{ get_staff_pending_action_count('single-memo') }}
          </span>
        </div>
        <div class="mt-2">
          <a href="#" class="btn btn-success btn-sm">
            <i class="fas fa-file-alt"></i> All Single Memos
          </a>
        </div>
      </div>
    </div>

    {{-- Change Request --}}
    <div class="col-lg-3 col-md-6">
      <div class="dashboard-card text-center">
        <div>
          <h6>Change Request</h6>
          <p>View Submitted Change Requests.</p>
        </div>
        <div>
          <a href="#" class="btn btn-success btn-sm mt-2">My Change Requests</a>
          <div class="dashboard-icon mt-3"><i class="fas fa-edit"></i></div>
        </div>
        <div class="mt-2">
          <strong>Pending Action:</strong>
          <span class="badge bg-warning text-dark pending-badge">
            {{ get_staff_pending_action_count('change-request') }}
          </span>
        </div>
        <div class="mt-2">
          <a href="#" class="btn btn-success btn-sm">
            <i class="fas fa-edit"></i> All Change Requests
          </a>
        </div>
      </div>
    </div>

    {{-- Reports --}}
    <div class="col-lg-3 col-md-6">
      <div class="dashboard-card text-center">
        <div>
          <h6>Reports</h6>
          <p>View and download performance reports.</p>
        </div>
        <div>
          <a href="{{ url('reports') }}" class="btn btn-success btn-sm mt-2">Open</a>
          <div class="dashboard-icon mt-3"><i class="fas fa-chart-bar"></i></div>
        </div>
        <div class="mt-2">
          <a href="{{ url('reports') }}" class="btn btn-success btn-sm">
            <i class="fas fa-chart-bar"></i> All Reports
          </a>
        </div>
      </div>
    </div>

  </div>
</div>
@endsection
