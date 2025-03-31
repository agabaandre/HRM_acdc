<!-- Staff Search -->
<div class=" mt-2">
  <!-- Search Card -->
<!-- AU Green Themed Search Card -->
<div class="card border-0 shadow-sm" style="border-left: 4px solid rgba(17, 154, 72, 0.3); background-color: #ffffff;">
  <div class="card-body">
    <h5 class="mb-3" style="font-weight: 600; color: #119A48;">🔍 Search Staff</h5>
    <div class="input-group input-group-lg au-search-wrapper">
      <input type="text" id="staff-search" class="form-control au-search-input" placeholder="Search by name, SAPNO, or email..." autocomplete="off">
      <span class="input-group-text au-search-icon" id="search-icon">
        <i class="fas fa-search"></i>
      </span>
    </div>
  </div>
</div>



  <!-- Search Results -->
  <div id="staff-results" class="mt-2" style="display: none;">
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
