@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<style>
  body {
    font-family: "Segoe UI", sans-serif;
  }

  .dashboard-title {
    font-size: 1.5rem;
    color: #119A48;
    font-weight: bold;
    text-align: center;
    margin: 1rem 0 0.5rem;
  }

  .dashboard-card {
    height: 310px;
    padding: 1.2rem;
    transition: all 0.3s ease-in-out;
    font-size: 0.85rem;
    display: flex;
    flex-direction: column;
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
    font-size: 1rem;
    color: #911C39;
    margin-bottom: 0.3rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .dashboard-card p {
    font-size: 0.8rem;
    color: #5F5F5F;
    margin: 0 0 0.6rem 0;
    line-height: 1.3;
  }

  .dashboard-container .col-lg-3 {
    margin-bottom: 1.5rem;
  }

  .dashboard-icon {
    width: 35px;
    height: 35px;
    background-color: #f4f4f4;
    color: #C3A366;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    flex-shrink: 0;
  }

  .pending-badge {
    font-size: 0.75rem;
    padding: 0.3rem 0.5rem;
  }

  .btn-sm {
    padding: 0.3rem 0.6rem;
    font-size: 0.8rem;
    margin-bottom: 0.4rem;
    width: 100%;
    text-align: left;
  }

  .menu-section {
    margin-top: 0.8rem;
  }

  .menu-section h6 {
    font-size: 0.8rem;
    color: #666;
    margin-bottom: 0.4rem;
    border-bottom: 1px solid #eee;
    padding-bottom: 0.2rem;
  }

  .menu-links {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
  }

  .pending-action-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.2rem;
  }

  .pending-action-badge .bell-icon {
    width: 40px;
    height: 40px;
    background-color: #ffc107;
    color: #212529;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
    position: relative;
  }

  .pending-action-badge .notification-count {
    position: absolute;
    top: -8px;
    right: -8px;
    background-color: #dc3545;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: bold;
    border: 2px solid white;
  }
</style>

<div class="container-fluid">
  <h2 class="dashboard-title">Approvals Management</h2>
  
  <div class="row justify-content-center dashboard-container">
    
    {{-- Quarterly Travel Matrix --}}
    @php
      $pendingMatrixCount = get_staff_unread_notifications_count();
      $pendingNonTravelCount = get_staff_pending_action_count('non-travel');
      $pendingSpecialMemoCount = get_staff_pending_action_count('special-memo');
    @endphp

    <div class="col-lg-3 col-md-6">
      <div class="dashboard-card position-relative">
        <div class="d-flex align-items-center mb-2">
          <div class="dashboard-icon me-2"><i class="fas fa-calendar-alt"></i></div>
          <h6>Quarterly Travel Matrix (QM)</h6>
        </div>
        <p>Plan and track quarterly travel for all staff.</p>
        
        <div class="menu-section">
          <h6>Quick Actions</h6>
          <div class="menu-links">
            <a href="{{ route('matrices.index') }}" class="btn btn-success btn-sm">
              <i class="fas fa-plus"></i> Open
            </a>
            <a href="{{ route('activities.user-schedule') }}" class="btn btn-outline-primary btn-sm position-relative">
              <i class="fas fa-tasks"></i> Pending Approval
              @if($pendingMatrixCount > 0)
                <span class="notification-count" style="position:absolute; top:-8px; right:-8px;">
                  {{ $pendingMatrixCount }}
                </span>
              @endif
              <span class="ms-2 bell-icon" style="position:static;">
                <i class="fas fa-bell"></i>
              </span>
            </a>
            <a href="{{ route('activities.user-schedule') }}" class="btn btn-outline-primary btn-sm">
              <i class="fas fa-tasks"></i> Activities
            </a>
            <a href="{{ route('matrices.index') }}" class="btn btn-outline-info btn-sm">
              <i class="fas fa-list"></i> All QMs
            </a>
          </div>
        </div>
        {{-- Pending action badge in card corner --}}
        @if($pendingMatrixCount > 0)
        <div class="pending-action-badge">
          <div class="bell-icon"><i class="fas fa-bell"></i></div>
          <span class="notification-count">
            {{ $pendingMatrixCount }}
          </span>
        </div>
        @endif
      </div>
    </div>

        </div>
        <p>Manage activities that are not related to travel logistics.</p>
        
        <div class="menu-section">
          <h6>Quick Actions</h6>
          <div class="menu-links">
            <a href="{{ url('non-travel') }}" class="btn btn-success btn-sm">
              <i class="fas fa-plus"></i> Open
            </a>
            <a href="{{ url('non-travel') }}" class="btn btn-outline-primary btn-sm position-relative">
              <i class="fas fa-tasks"></i> Pending Approval
              @if(get_staff_pending_action_count('non-travel') > 0)
                <span class="notification-count" style="position:absolute; top:-8px; right:-8px;">
                  {{ get_staff_pending_action_count('non-travel') }}
                </span>
              @endif
              <span class="ms-2 bell-icon" style="position:static;">
                <i class="fas fa-bell"></i>
              </span>
            </a>
            <a href="{{ url('non-travel') }}" class="btn btn-outline-primary btn-sm">
              <i class="fas fa-file-alt"></i> My Submitted (NT)
            </a>
            <a href="{{ url('non-travel') }}" class="btn btn-outline-info btn-sm">
              <i class="fas fa-list"></i> All Non-Travel
            </a>
          </div>
        </div>
        {{-- Pending action badge in card corner --}}
        @if(get_staff_pending_action_count('non-travel') > 0)
        <div class="pending-action-badge">
          <div class="bell-icon"><i class="fas fa-bell"></i></div>
          <span class="notification-count">
            {{ get_staff_pending_action_count('non-travel') }}
          </span>
        </div>
        @endif
      </div>
    </div>

    {{-- Special Memo --}}
    <div class="col-lg-3 col-md-6">
      <div class="dashboard-card position-relative">
        <div class="d-flex align-items-center mb-2">
          <div class="dashboard-icon me-2"><i class="fas fa-envelope-open-text"></i></div>
          <h6>Special Memo (SPM)</h6>
        </div>
        <p>Create and send special memos for specific activities.</p>
        
        <div class="menu-section">
          <h6>Quick Actions</h6>
          <div class="menu-links">
            <a href="{{ url('special-memo') }}" class="btn btn-success btn-sm">
              <i class="fas fa-plus"></i> Open
            </a>
            <a href="{{ url('special-memo') }}" class="btn btn-outline-primary btn-sm position-relative">
              <i class="fas fa-tasks"></i> Pending Approval
              @if(get_staff_pending_action_count('special-memo') > 0)
                <span class="notification-count" style="position:absolute; top:-8px; right:-8px;">
                  {{ get_staff_pending_action_count('special-memo') }}
                </span>
              @endif
              <span class="ms-2 bell-icon" style="position:static;">
                <i class="fas fa-bell"></i>
              </span>
            </a>
            <a href="{{ url('special-memo') }}" class="btn btn-outline-primary btn-sm">
              <i class="fas fa-file-alt"></i> My Submitted
            </a>
            <a href="{{ url('special-memo') }}" class="btn btn-outline-info btn-sm">
              <i class="fas fa-list"></i> All SPMs
            </a>
            <a href="{{ url('special-memo') }}" class="btn btn-outline-secondary btn-sm">
              <i class="fas fa-handshake"></i> Shared SPMs
            </a>
          </div>
        </div>
        {{-- Pending action badge in card corner --}}
        @if(get_staff_pending_action_count('special-memo') > 0)
        <div class="pending-action-badge">
          <div class="bell-icon"><i class="fas fa-bell"></i></div>
          <span class="notification-count">
            {{ get_staff_pending_action_count('special-memo') }}
          </span>
        </div>
        @endif
      </div>
    </div>

    {{-- Request for Services --}}
    <div class="col-lg-3 col-md-6">
      <div class="dashboard-card position-relative">
        <div class="d-flex align-items-center mb-2">
          <div class="dashboard-icon me-2"><i class="fas fa-tools"></i></div>
          <h6>Request for Services <br>(RQS)</h6>
        </div>
        <p>Submit requests for tickets, DSA, procurement, or imprest.</p>
        
        <div class="menu-section">
          <h6>Quick Actions</h6>
          <div class="menu-links">
            <a href="{{ url('service-requests') }}" class="btn btn-success btn-sm">
              <i class="fas fa-plus"></i> Open
            </a>
            <a href="{{ url('service-requests') }}" class="btn btn-outline-primary btn-sm position-relative">
              <i class="fas fa-tasks"></i> Pending Approval
              @if(get_staff_pending_action_count('service-requests') > 0)
                <span class="notification-count" style="position:absolute; top:-8px; right:-8px;">
                  {{ get_staff_pending_action_count('service-requests') }}
                </span>
              @endif
              <span class="ms-2 bell-icon" style="position:static;">
                <i class="fas fa-bell"></i>
              </span>
            </a>
            <a href="{{ url('service-requests') }}" class="btn btn-outline-primary btn-sm">
              <i class="fas fa-file-alt"></i> My Submitted RQS
            </a>
          </div>
        </div>
        {{-- Pending action badge in card corner --}}
        @if(get_staff_pending_action_count('service-requests') > 0)
        <div class="pending-action-badge">
          <div class="bell-icon"><i class="fas fa-bell"></i></div>
          <span class="notification-count">
            {{ get_staff_pending_action_count('service-requests') }}
          </span>
        </div>
        @endif
      </div>
    </div>

    {{-- Request for ARF --}}
    <div class="col-lg-3 col-md-6">
      <div class="dashboard-card position-relative">
        <div class="d-flex align-items-center mb-2">
          <div class="dashboard-icon me-2"><i class="fas fa-file-signature"></i></div>
          <h6>Request for ARF</h6>
        </div>
        <p>Submit your Activity Request Form for approvals.</p>
        
        <div class="menu-section">
          <h6>Quick Actions</h6>
          <div class="menu-links">
            <a href="{{ url('request-arf') }}" class="btn btn-success btn-sm">
              <i class="fas fa-plus"></i> Open
            </a>
            <a href="{{ url('request-arf') }}" class="btn btn-outline-primary btn-sm position-relative">
              <i class="fas fa-tasks"></i> Pending Approval
              @if(get_staff_pending_action_count('request-arf') > 0)
                <span class="notification-count" style="position:absolute; top:-8px; right:-8px;">
                  {{ get_staff_pending_action_count('request-arf') }}
                </span>
              @endif
              <span class="ms-2 bell-icon" style="position:static;">
                <i class="fas fa-bell"></i>
              </span>
            </a>
            <a href="{{ url('request-arf') }}" class="btn btn-outline-primary btn-sm">
              <i class="fas fa-file-alt"></i> My Submitted
            </a>
            <a href="{{ url('request-arf') }}" class="btn btn-outline-info btn-sm">
              <i class="fas fa-list"></i> All ARFs
            </a>
          </div>
        </div>
        {{-- Pending action badge in card corner --}}
        @if(get_staff_pending_action_count('request-arf') > 0)
        <div class="pending-action-badge">
          <div class="bell-icon"><i class="fas fa-bell"></i></div>
          <span class="notification-count">
            {{ get_staff_pending_action_count('request-arf') }}
          </span>
        </div>
        @endif
      </div>
    </div>

    {{-- Single Memo --}}
    <div class="col-lg-3 col-md-6">
      <div class="dashboard-card position-relative">
        <div class="d-flex align-items-center mb-2">
          <div class="dashboard-icon me-2"><i class="fas fa-file-alt"></i></div>
          <h6>Single Memo (SM)</h6>
        </div>
        <p>View Submitted Single Memos.</p>
        
        <div class="menu-section">
          <h6>Quick Actions</h6>
          <div class="menu-links">
            <a href="{{ route('activities.single-memos.index') }}" class="btn btn-success btn-sm">
              <i class="fas fa-plus"></i> Open
            </a>
            <a href="{{ route('activities.single-memos.index') }}" class="btn btn-outline-primary btn-sm position-relative">
              <i class="fas fa-tasks"></i> Pending Approval
              @if(get_staff_pending_action_count('single-memo') > 0)
                <span class="notification-count" style="position:absolute; top:-8px; right:-8px;">
                  {{ get_staff_pending_action_count('single-memo') }}
                </span>
              @endif
              <span class="ms-2 bell-icon" style="position:static;">
                <i class="fas fa-bell"></i>
              </span>
            </a>
            <a href="{{ route('activities.single-memos.index') }}" class="btn btn-outline-primary btn-sm">
              <i class="fas fa-file-alt"></i> My Submitted
            </a>
            <a href="{{ route('activities.single-memos.index') }}" class="btn btn-outline-info btn-sm">
              <i class="fas fa-handshake"></i> Shared SMs
            </a>
            <a href="{{ route('activities.single-memos.index') }}" class="btn btn-outline-secondary btn-sm">
              <i class="fas fa-list"></i> All Single Memos
            </a>
          </div>
        </div>
        {{-- Pending action badge in card corner --}}
        @if(get_staff_pending_action_count('single-memo') > 0)
        <div class="pending-action-badge">
          <div class="bell-icon"><i class="fas fa-bell"></i></div>
          <span class="notification-count">
            {{ get_staff_pending_action_count('single-memo') }}
          </span>
        </div>
        @endif
      </div>
    </div>

    {{-- Change Request --}}
    <div class="col-lg-3 col-md-6">
      <div class="dashboard-card position-relative">
        <div class="d-flex align-items-center mb-2">
          <div class="dashboard-icon me-2"><i class="fas fa-edit"></i></div>
          <h6>Change Request (CR)</h6>
        </div>
        <p>View Submitted Change Requests.</p>
        
        <div class="menu-section">
          <h6>Quick Actions</h6>
          <div class="menu-links">
            <a href="#" class="btn btn-success btn-sm">
              <i class="fas fa-plus"></i> Open
            </a>
            <a href="#" class="btn btn-outline-primary btn-sm position-relative">
              <i class="fas fa-tasks"></i> Pending Approval
              @if(get_staff_pending_action_count('change-request') > 0)
                <span class="notification-count" style="position:absolute; top:-8px; right:-8px;">
                  {{ get_staff_pending_action_count('change-request') }}
                </span>
              @endif
              <span class="ms-2 bell-icon" style="position:static;">
                <i class="fas fa-bell"></i>
              </span>
            </a>
            <a href="#" class="btn btn-outline-primary btn-sm">
              <i class="fas fa-file-alt"></i> My Change Requests (CR)
            </a>
            <a href="#" class="btn btn-outline-info btn-sm">
              <i class="fas fa-handshake"></i> Shared CR
            </a>
            <a href="#" class="btn btn-outline-secondary btn-sm">
              <i class="fas fa-list"></i> All CRs
            </a>
          </div>
        </div>
        {{-- Pending action badge in card corner --}}
        @if(get_staff_pending_action_count('change-request') > 0)
        <div class="pending-action-badge">
          <div class="bell-icon"><i class="fas fa-bell"></i></div>
          <span class="notification-count">
            {{ get_staff_pending_action_count('change-request') }}
          </span>
        </div>
        @endif
      </div>
    </div>

    {{-- Reports --}}
    <div class="col-lg-3 col-md-6">
      <div class="dashboard-card position-relative">
        <div class="d-flex align-items-center mb-2">
          <div class="dashboard-icon me-2"><i class="fas fa-chart-bar"></i></div>
          <h6>Reports</h6>
        </div>
        <p>View and download performance reports.</p>
        
        <div class="menu-section">
          <h6>Quick Actions</h6>
          <div class="menu-links">
            <a href="{{ url('reports') }}" class="btn btn-outline-info btn-sm">
              <i class="fas fa-list"></i> All Reports
            </a>
          </div>
        </div>
        
        {{-- Reports don't have pending approvals, so no badge needed --}}
      </div>
    </div>

  </div>
</div>
@endsection
