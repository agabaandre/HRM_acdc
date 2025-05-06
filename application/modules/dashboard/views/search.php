<!-- Staff Search Panel -->
<div class="mt-2">

  <!-- Search Header with Magnifying Glass Trigger -->
  <div class="d-flex justify-content-between align-items-center mb-2">
    <button class="btn btn-sm btn-outline-success" id="reveal-search-form">
      <i class="fas fa-search"></i> Search
    </button>
  </div>

  <!-- Hidden Collapsible Search Form -->
  <div class="collapse" id="searchPanel">
    <div class="card border-0 shadow-sm" style="border-left: 4px solid rgba(17, 154, 72, 0.3); background-color: #ffffff;">
      <div class="card-body">
        <div class="input-group input-group-lg au-search-wrapper">
          <input type="text" id="staff-search" class="form-control au-search-input" placeholder="Search by name, SAPNO, or email..." autocomplete="off">
          <span class="input-group-text au-search-icon" id="search-icon">
            <i class="fas fa-search"></i>
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- Search Results -->
  <div id="staff-results" class="mt-3" style="display: none;">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-light">
        <strong>Search Results</strong>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-striped mb-0">
            <thead class="table-light">
              <tr>
                <th>Name</th>
                <th>Gender</th>
                <th>SAPNO</th>
                <th>Email</th>
                <th>Telephone</th>
                <th>Contract</th>
              </tr>
            </thead>
            <tbody id="staff-results-body"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
