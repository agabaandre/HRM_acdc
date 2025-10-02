@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<style>
:root {
  --primary-color: #119a48;
  --primary-dark: #0d7a3a;
  --primary-light: #1bb85a;
  --secondary-color: #9f2240;
  --secondary-light: #c44569;
  --accent-black: #2c3e50;
  --light-grey: #f8f9fa;
  --medium-grey: #e9ecef;
  --dark-grey: #6c757d;
  --text-dark: #1a1a1a;
  --text-muted: #4a4a4a;
  --border-color: #e9ecef;
  --shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  --shadow-lg: 0 4px 16px rgba(0, 0, 0, 0.12);
  --transition: all 0.2s ease;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  background-image: url('{{ asset("images/bg_login.jpg") }}');
  background-repeat: no-repeat;
  background-size: cover;
  background-position: center center;
  background-attachment: fixed;
  min-height: 100vh;
  padding: 20px;
}

.dashboard-title {
  font-size: 2rem;
  color: var(--primary-color);
  font-weight: 700;
  text-align: center;
  margin-bottom: 2rem;
  position: relative;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.dashboard-title::after {
  content: '';
  position: absolute;
  bottom: -10px;
  left: 50%;
  transform: translateX(-50%);
  width: 60px;
  height: 3px;
  background: var(--primary-color);
}

.dashboard-card {
  height: 280px;
  border-radius: 10px;
  padding: 1.2rem;
  transition: var(--transition);
  font-size: 0.9rem;
  display: flex;
  flex-direction: column;
  background: white;
  border: 1px solid var(--medium-grey);
  box-shadow: var(--shadow);
  position: relative;
  overflow: hidden;
  margin-bottom: 1rem;
}

.dashboard-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: var(--primary-color);
  transform: scaleX(0);
  transition: var(--transition);
}

.dashboard-card:hover {
  box-shadow: var(--shadow-lg);
  transform: translateY(-2px);
  border-color: var(--primary-color);
}

.dashboard-card:hover::before {
  transform: scaleX(1);
}

.dashboard-card h6 {
  font-weight: 700;
  font-size: 1.1rem;
  color: var(--text-dark);
  margin-bottom: 0.8rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  line-height: 1.3;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

.dashboard-card p {
  font-size: 0.9rem;
  color: var(--text-muted);
  margin: 0 0 1rem 0;
  line-height: 1.5;
  flex-grow: 1;
  font-weight: 500;
}

.dashboard-container .col-lg-3 {
  margin-bottom: 1.5rem;
}

.dashboard-icon {
  width: 40px;
  height: 40px;
  background: linear-gradient(135deg, rgba(17, 154, 72, 0.05) 0%, rgba(17, 154, 72, 0.02) 100%);
  color: rgba(17, 154, 72, 0.4);
  border: 1px solid rgba(17, 154, 72, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  box-shadow: var(--shadow);
  flex-shrink: 0;
  transition: var(--transition);
}

.dashboard-card:hover .dashboard-icon {
  background: rgba(17, 154, 72, 0.1);
  color: rgba(17, 154, 72, 0.7);
  transform: scale(1.05);
  box-shadow: var(--shadow-lg);
}

/* Colored icons for different modules */
.dashboard-icon.quarterly-matrix {
  background: linear-gradient(135deg, rgba(52, 152, 219, 0.1) 0%, rgba(52, 152, 219, 0.05) 100%);
  color: #3498db;
  border-color: rgba(52, 152, 219, 0.2);
}

.dashboard-card:hover .dashboard-icon.quarterly-matrix {
  background: rgba(52, 152, 219, 0.15);
  color: #2980b9;
}

.dashboard-icon.non-travel {
  background: linear-gradient(135deg, rgba(155, 89, 182, 0.1) 0%, rgba(155, 89, 182, 0.05) 100%);
  color: #9b59b6;
  border-color: rgba(155, 89, 182, 0.2);
}

.dashboard-card:hover .dashboard-icon.non-travel {
  background: rgba(155, 89, 182, 0.15);
  color: #8e44ad;
}

.dashboard-icon.special-memo {
  background: linear-gradient(135deg, rgba(230, 126, 34, 0.1) 0%, rgba(230, 126, 34, 0.05) 100%);
  color: #e67e22;
  border-color: rgba(230, 126, 34, 0.2);
}

.dashboard-card:hover .dashboard-icon.special-memo {
  background: rgba(230, 126, 34, 0.15);
  color: #d35400;
}

.dashboard-icon.request-services {
  background: linear-gradient(135deg, rgba(46, 204, 113, 0.1) 0%, rgba(46, 204, 113, 0.05) 100%);
  color: #2ecc71;
  border-color: rgba(46, 204, 113, 0.2);
}

.dashboard-card:hover .dashboard-icon.request-services {
  background: rgba(46, 204, 113, 0.15);
  color: #27ae60;
}

.dashboard-icon.request-arf {
  background: linear-gradient(135deg, rgba(231, 76, 60, 0.1) 0%, rgba(231, 76, 60, 0.05) 100%);
  color: #e74c3c;
  border-color: rgba(231, 76, 60, 0.2);
}

.dashboard-card:hover .dashboard-icon.request-arf {
  background: rgba(231, 76, 60, 0.15);
  color: #c0392b;
}

.dashboard-icon.single-memo {
  background: linear-gradient(135deg, rgba(241, 196, 15, 0.1) 0%, rgba(241, 196, 15, 0.05) 100%);
  color: #f1c40f;
  border-color: rgba(241, 196, 15, 0.2);
}

.dashboard-card:hover .dashboard-icon.single-memo {
  background: rgba(241, 196, 15, 0.15);
  color: #f39c12;
}

.dashboard-icon.change-request {
  background: linear-gradient(135deg, rgba(142, 68, 173, 0.1) 0%, rgba(142, 68, 173, 0.05) 100%);
  color: #8e44ad;
  border-color: rgba(142, 68, 173, 0.2);
}

.dashboard-card:hover .dashboard-icon.change-request {
  background: rgba(142, 68, 173, 0.15);
  color: #7d3c98;
}

.dashboard-icon.reports {
  background: linear-gradient(135deg, rgba(52, 73, 94, 0.1) 0%, rgba(52, 73, 94, 0.05) 100%);
  color: #34495e;
  border-color: rgba(52, 73, 94, 0.2);
}

.dashboard-card:hover .dashboard-icon.reports {
  background: rgba(52, 73, 94, 0.15);
  color: #2c3e50;
}

.pending-badge {
  font-size: 0.75rem;
  padding: 0.3rem 0.5rem;
  background: rgba(17, 154, 72, 0.1);
  color: rgba(17, 154, 72, 0.9);
  border: 1px solid rgba(17, 154, 72, 0.2);
  font-weight: 600;
}

.btn-sm {
  padding: 0.1rem 1rem;
  font-size: 0.85rem;
  margin-bottom: 0.5rem;
  width: 100%;
  text-align: left;
  background: rgba(17, 154, 72, 0.1);
  color: rgba(17, 154, 72, 0.9);
  border: 1px solid rgba(17, 154, 72, 0.2);
  transition: var(--transition);
  font-weight: 600;
}

.btn-sm:hover {
  background: rgba(17, 154, 72, 0.15);
  color: rgba(17, 154, 72, 1);
  transform: translateX(2px);
}

.btn-outline-primary {
  background: transparent;
  color: rgba(17, 154, 72, 0.8);
  border: 1px solid rgba(17, 154, 72, 0.4);
  transition: var(--transition);
  font-weight: 600;
}

.btn-outline-primary:hover {
  background: rgba(17, 154, 72, 0.1);
  color: rgba(17, 154, 72, 1);
  border-color: rgba(17, 154, 72, 0.6);
}

.menu-section {
  margin-top: 0.1rem;
}

.dashboard-card p.text-muted {
  margin-bottom: 0.5rem;
}

.menu-section h6 {
  font-size: 0.9rem;
  color: var(--text-dark);
  margin-bottom: 0.6rem;
  border-bottom: 2px solid var(--border-color);
  padding-bottom: 0.4rem;
  font-weight: 700;
}

.menu-links {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
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
    background-color: rgba(17, 154, 72, 0.1);
    color: rgba(17, 154, 72, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    box-shadow: var(--shadow);
    position: relative;
    border: 1px solid rgba(17, 154, 72, 0.2);
  }

  .pending-action-badge .notification-count {
    position: absolute;
    top: -8px;
    right: -8px;
    background-color: rgba(17, 154, 72, 0.8);
    color: white;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: bold;
    border: 2px solid white;
    box-shadow: var(--shadow);
  }

/* Loading animation for cards */
.dashboard-card {
  animation: fadeInUp 0.6s ease forwards;
}

.col-lg-3:nth-child(1) .dashboard-card { animation-delay: 0.1s; }
.col-lg-3:nth-child(2) .dashboard-card { animation-delay: 0.2s; }
.col-lg-3:nth-child(3) .dashboard-card { animation-delay: 0.3s; }
.col-lg-3:nth-child(4) .dashboard-card { animation-delay: 0.4s; }

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Responsive Design */
@media (max-width: 767px) {
  .dashboard-card {
    height: auto;
    min-height: 280px;
  }
  
  .dashboard-title {
    font-size: 1.5rem;
  }
}

/* Accessibility improvements */
.dashboard-card:focus-within {
  outline: 2px solid var(--primary-color);
  outline-offset: 2px;
}

/* High contrast mode support */
@media (prefers-contrast: high) {
  .dashboard-card {
    border-width: 3px;
  }
  
  .dashboard-icon {
    border-width: 3px;
  }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
  * {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}
</style>

<div class="container-fluid">
  <h2 class="dashboard-title text-muted">Approvals Management</h2>
  
  <div class="row justify-content-center dashboard-container">
    
    {{-- Quarterly Travel Matrix --}}
    @php
      $pendingMatrixCount = get_staff_pending_action_count('matrices');
      $pendingNonTravelCount = get_staff_pending_action_count('non-travel');
      $pendingSpecialMemoCount = get_staff_pending_action_count('special-memo');
    @endphp

    <div class="col-lg-3 col-md-6">
      <div class="dashboard-card position-relative">
        <div class="d-flex align-items-center mb-2">
          <div class="dashboard-icon quarterly-matrix me-2"><i class="fas fa-calendar-alt"></i></div>
          <h6>Quarterly Travel Matrix (QM)</h6>
        </div>
        <p class="text-muted" style="font-size: 0.9rem;">Plan and track quarterly travel for all staff.</p>
        
        <div class="menu-section">
          <h6>Quick Actions</h6>
          <div class="menu-links">
            <a href="{{ route('matrices.index') }}" class="btn btn-success btn-sm">
              <i class="fas fa-plus"></i> Open
            </a>
            @if(get_staff_pending_action_count('matrices') >= 0)
            <a href="{{ route('matrices.pending-approvals') }}" class="btn btn-outline-primary btn-sm position-relative">
              <i class="fas fa-tasks"></i> Pending Approval
              <span class="alert-count" id="matrix-pending-count">{{ get_staff_pending_action_count('matrices') }}</span>
            </a>
            @endif
          </div>
        </div>

      </div>
    </div>

    {{-- Non-Travel Memo --}}
    <div class="col-lg-3 col-md-6">
      <div class="dashboard-card position-relative">
        <div class="d-flex align-items-center mb-2">
          <div class="dashboard-icon non-travel me-2"><i class="fas fa-file-alt"></i></div>
          <h6>Non-Travel Memo (NT)</h6>
        </div>
        <p class="text-muted" style="font-size: 0.9rem;">Manage activities that are not related to travel logistics.</p>
        
        <div class="menu-section">
          <h6>Quick Actions</h6>
          <div class="menu-links">
            <a href="{{ route('non-travel.index') }}" class="btn btn-success btn-sm">
              <i class="fas fa-plus"></i> Open
            </a>
            
            @if(get_staff_pending_action_count('non-travel') >= 0)
            <a href="{{ route('non-travel.pending-approvals') }}" class="btn btn-outline-primary btn-sm position-relative">
              <i class="fas fa-tasks"></i> Pending Approval
              <span class="alert-count" id="non-travel-pending-count">{{ get_staff_pending_action_count('non-travel') }}</span>
            </a>
            @endif
          </div>
        </div>

      </div>
    </div>

    {{-- Special Memo --}}
    <div class="col-lg-3 col-md-6">
      <div class="dashboard-card position-relative">
        <div class="d-flex align-items-center mb-2">
          <div class="dashboard-icon special-memo me-2"><i class="fas fa-envelope-open-text"></i></div>
          <h6>Special Memo (SPM)</h6>
        </div>
        <p class="text-muted" style="font-size: 0.9rem;">Create and send special memos for specific activities.</p>
        
        <div class="menu-section">
          <h6>Quick Actions</h6>
          <div class="menu-links">
            <a href="{{ url('special-memo') }}" class="btn btn-success btn-sm">
              <i class="fas fa-plus"></i> Open
            </a>
            @if(get_staff_pending_action_count('special-memo') >=0)
            <a href="{{ route('special-memo.pending-approvals') }}" class="btn btn-outline-primary btn-sm position-relative">
              <i class="fas fa-tasks"></i> Pending Approval
              <span class="alert-count" id="special-memo-pending-count">{{ get_staff_pending_action_count('special-memo') }}</span>
            </a>
            @endif
           
          </div>
        </div>

      </div>
    </div>

    {{-- Request for Services --}}
    <div class="col-lg-3 col-md-6">
      <div class="dashboard-card position-relative">
        <div class="d-flex align-items-center mb-2">
          <div class="dashboard-icon request-services me-2"><i class="fas fa-tools"></i></div>
          <h6>Request for Services <br>(RQS)</h6>
        </div>
        <p class="text-muted" style="font-size: 0.9rem;">Submit requests for tickets, DSA, procurement, or imprest.</p>
        
        <div class="menu-section">
          <h6>Quick Actions</h6>
          <div class="menu-links">
            <a href="{{ url('service-requests') }}" class="btn btn-success btn-sm">
              <i class="fas fa-plus"></i> Open
            </a>
            @if(get_staff_pending_action_count('service-requests') >= 0)
            <a href="{{ url('service-requests/pending-approvals') }}" class="btn btn-outline-primary btn-sm position-relative">
              <i class="fas fa-tasks"></i> Pending Approval
              <span class="alert-count" id="service-requests-pending-count">{{ get_staff_pending_action_count('service-requests') }}</span>
            </a>
            @endif
          </div>
        </div>

      </div>
    </div>

    {{-- Request for ARF --}}
    <div class="col-lg-3 col-md-6">
      <div class="dashboard-card position-relative">
        <div class="d-flex align-items-center mb-2">
          <div class="dashboard-icon request-arf me-2"><i class="fas fa-file-signature"></i></div>
          <h6>Request for ARF</h6>
        </div>
        <p class="text-muted" style="font-size: 0.9rem;">Submit your Activity Request Form for approvals.</p>
        
        <div class="menu-section">
          <h6>Quick Actions</h6>
          <div class="menu-links">
            <a href="{{ url('request-arf') }}" class="btn btn-success btn-sm">
              <i class="fas fa-plus"></i> Open
            </a>
            @if(get_staff_pending_action_count('request-arf') >= 0)
            <a href="{{ url('request-arf') }}" class="btn btn-outline-primary btn-sm position-relative">
              <i class="fas fa-tasks"></i> Pending Approval
              <span class="alert-count" id="arf-pending-count">{{ get_staff_pending_action_count('request-arf') }}</span>
            </a>
            @endif
           
          </div>
        </div>

      </div>
    </div>


    {{-- Single Memo --}}
    <div class="col-lg-3 col-md-6">
      <div class="dashboard-card position-relative">
        <div class="d-flex align-items-center mb-2">
          <div class="dashboard-icon single-memo me-2"><i class="fas fa-file-alt"></i></div>
          <h6>Single Memo (SM)</h6>
        </div>
        <p class="text-muted" style="font-size: 0.9rem;">View Submitted Single Memos.</p>
        
        <div class="menu-section">
          <h6>Quick Actions</h6>
          <div class="menu-links">
            <a href="{{ route('activities.single-memos.index') }}" class="btn btn-success btn-sm">
              <i class="fas fa-plus"></i> Open
            </a>
            @if(get_staff_pending_action_count('single-memo') >= 0)
            <a href="{{ route('activities.single-memos.pending-approvals') }}" class="btn btn-outline-primary btn-sm position-relative">
              <i class="fas fa-tasks"></i> Pending Approval
              <span class="alert-count" id="single-memo-pending-count">{{ get_staff_pending_action_count('single-memo') }}</span>
            </a>
            @endif
        
          </div>
        </div>

      </div>
    </div>

    {{-- Change Request --}}
    <div class="col-lg-3 col-md-6">
      <div class="dashboard-card position-relative">
        <div class="d-flex align-items-center mb-2">
          <div class="dashboard-icon change-request me-2"><i class="fas fa-edit"></i></div>
          <h6>Change Request (CR)</h6>
        </div>
        <p class="text-muted" style="font-size: 0.9rem;">Request changes to existing memos and activities.</p>
        
        <div class="menu-section">
          <h6>Quick Actions</h6>
          <div class="menu-links">
            <a href="{{ route('change-requests.index') }}" class="btn btn-success btn-sm">
              <i class="fas fa-list"></i> View All
            </a>
            @if(get_staff_pending_action_count('change-request') >= 0)
            <a href="{{ route('change-requests.index') }}?status=submitted" class="btn btn-outline-primary btn-sm position-relative">
              <i class="fas fa-tasks"></i> Pending Approval
              <span class="alert-count" id="change-request-pending-count">{{ get_staff_pending_action_count('change-request') }}</span>
            </a>
            @endif
          </div>
        </div>

      </div>
    </div>

    {{-- Reports --}}
    <div class="col-lg-3 col-md-6">
      <div class="dashboard-card position-relative">
        <div class="d-flex align-items-center mb-2">
          <div class="dashboard-icon reports me-2"><i class="fas fa-chart-bar"></i></div>
          <h6>Reports</h6>
        </div>
        <p class="text-muted" style="font-size: 0.9rem;">View and download performance reports.</p>
        
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
