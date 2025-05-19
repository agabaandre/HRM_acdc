@extends('layouts.app')

@section('title', 'Settings')

@section('content')
<style>
  body {
    font-family: "Segoe UI", sans-serif;
  }

  .settings-title {
    font-size: 1.4rem;
    color: #119A48; /* AU Green */
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
    color: #911C39; /* CDC Red */
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
    font-size: 1.6rem;
    color: #C3A366;
    background: #f4f4f4;
    padding: 0.6rem;
    border-radius: 50%;
    display: inline-block;
    margin-top: 1rem;
  }
</style>

<h5 class="settings-title">Menu</h5>

<div class="row justify-content-center settings-container">

  @if(session('role') == 10)
  <div class="col-md-4">
    <div class="setting-card text-center">
      <div>
        <h6>Workflows</h6>
        <p>Manage workflow definitions and templates across departments.</p>
      </div>
      <div>
        <a href="{{ route('workflows.index') }}" class="btn btn-outline-success btn-sm mt-2">Manage Workflows</a>
        <div class="settings-icon mt-3"><i class="bx bx-git-branch"></i></div>
      </div>
    </div>
  </div>
  @endif

  <div class="col-md-4">
    <div class="setting-card text-center">
      <div>
        <h6>Divisions</h6>
        <p>Set up and manage organizational divisions and hierarchies.</p>
      </div>
      <div>
        <a href="{{ route('divisions.index') }}" class="btn btn-outline-success btn-sm mt-2">Manage Divisions</a>
        <div class="settings-icon mt-3"><i class="bx bx-sitemap"></i></div>
      </div>
    </div>
  </div>

  {{-- Additional Menu Items --}}
  <div class="col-md-4">
    <div class="setting-card text-center">
      <div>
        <h6>Quarterly Travel Matrix</h6>
        <p>Plan and track quarterly travel for all staff.</p>
      </div>
      <div>
        <a href="{{ route('matrices.index') }}" class="btn btn-outline-success btn-sm mt-2">Open Matrix</a>
        <div class="settings-icon mt-3"><i class="bx bx-calendar"></i></div>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="setting-card text-center">
      <div>
        <h6>Request for Services</h6>
        <p>Submit requests for tickets, DSA, procurement, or imprest.</p>
      </div>
      <div>
        <a href="{{ url('service-requests') }}" class="btn btn-outline-success btn-sm mt-2">Request Services</a>
        <div class="settings-icon mt-3"><i class="bx bx-package"></i></div>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="setting-card text-center">
      <div>
        <h6>Non-Travel</h6>
        <p>Manage activities that are not related to travel logistics.</p>
      </div>
      <div>
        <a href="{{ url('non-travel') }}" class="btn btn-outline-success btn-sm mt-2">Open Module</a>
        <div class="settings-icon mt-3"><i class="bx bx-walk"></i></div>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="setting-card text-center">
      <div>
        <h6>Request for ARF</h6>
        <p>Submit your Activity Request Form for approvals.</p>
      </div>
      <div>
        <a href="{{ url('arf-request') }}" class="btn btn-outline-success btn-sm mt-2">Submit ARF</a>
        <div class="settings-icon mt-3"><i class="bx bx-edit"></i></div>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="setting-card text-center">
      <div>
        <h6>Special Memo</h6>
        <p>Create and send special memos for specific activities.</p>
      </div>
      <div>
        <a href="{{ url('special-memo') }}" class="btn btn-outline-success btn-sm mt-2">Create Memo</a>
        <div class="settings-icon mt-3"><i class="bx bx-envelope"></i></div>
      </div>
    </div>
  </div>

</div>
@endsection
