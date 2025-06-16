@extends('layouts.app')

@section('title', 'Settings')

@section('content')
<style>
  body {
    font-family: "Segoe UI", sans-serif;
  }

  .settings-title {
    font-size: 1.4rem;
    color: #119A48;
    font-weight: bold;
    text-align: center;
    margin: 2rem 0 1rem;
  }

  .setting-card {
    height: 200px;
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
  }

  .setting-card:hover {
    box-shadow: 0 0 12px #911c3966;
    transform: translateY(-4px);
  }

  .setting-card h6 {
    font-weight: 700;
    font-size: 1.1rem;
    color: #911C39;
    margin-bottom: 0.5rem;
  }

  .setting-card p {
    font-size: 0.85rem;
    color: #5F5F5F;
    margin: 0;
  }

  .settings-container .col-md-4 {
    margin-bottom: 1.5rem;
  }

  .settings-icon {
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

</style>

<div class="row justify-content-center settings-container">

  {{-- Quarterly Travel Matrix --}}
  <div class="col-md-4">
    <div class="setting-card text-center">
      <div>
        <h6>Quarterly Travel Matrix<sup><span class="alert-count" title="{{ get_staff_unread_notifications_count()}} Item(s)  require your attention">{{ get_staff_unread_notifications_count()}}</span><sup></h6>
        <p>Plan and track quarterly travel for all staff.</p>
      </div>
      <div>
        <a href="{{ route('matrices.index') }}" class="btn btn-success btn-sm mt-2">Open </a>
        <div class="settings-icon mt-3"><i class="fas fa-calendar-alt"></i></div>
      </div>
    </div>
  </div>

  {{-- Non-Travel --}}
  <div class="col-md-4">
    <div class="setting-card text-center">
      <div>
        <h6>Non-Travel</h6>
        <p>Manage activities that are not related to travel logistics.</p>
      </div>
      <div>
        <a href="{{ url('non-travel') }}" class="btn btn-success btn-sm mt-2">Open</a>
        <div class="settings-icon mt-3"><i class="fas fa-walking"></i></div>
      </div>
    </div>
  </div>
  {{-- Special Memo --}}
  <div class="col-md-4">
    <div class="setting-card text-center">
      <div>
        <h6>Special Memo</h6>
        <p>Create and send special memos for specific activities.</p>
      </div>
      <div>
        <a href="{{ url('special-memo') }}" class="btn btn-success btn-sm mt-2">Open</a>
        <div class="settings-icon mt-3"><i class="fas fa-envelope-open-text"></i></div>
      </div>
    </div>
  </div>

  {{-- Request for Services --}}
  <div class="col-md-4">
    <div class="setting-card text-center">
      <div>
        <h6>Request for Services</h6>
        <p>Submit requests for tickets, DSA, procurement, or imprest.</p>
      </div>
      <div>
        <a href="{{ url('service-requests') }}" class="btn btn-success btn-sm mt-2">Open</a>
        <div class="settings-icon mt-3"><i class="fas fa-box"></i></div>
      </div>
    </div>
  </div>



  {{-- Request for ARF --}}
  <div class="col-md-4">
    <div class="setting-card text-center">
      <div>
        <h6>Request for ARF</h6>
        <p>Submit your Activity Request Form for approvals.</p>
      </div>
      <div>
        <a href="{{ url('request-arf') }}" class="btn btn-success btn-sm mt-2">Open</a>
        <div class="settings-icon mt-3"><i class="fas fa-file-signature"></i></div>
      </div>
    </div>
  </div>

</div>
@endsection
