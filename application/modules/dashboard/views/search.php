<!-- Staff Search -->
<div class=" mt-4">
  <div class="card shadow-sm border-0">
    <div class="card-body">
      <h5 class="mb-3">🔍 Search Staff</h5>
      <div class="input-group input-group-lg">
        <input type="text" id="staff-search" class="form-control rounded-start" placeholder="Search by name, SAPNO, or email..." autocomplete="off">
        <span class="input-group-text bg-white rounded-end" id="search-icon" style="cursor:pointer;">
          <i class="fas fa-search text-muted"></i>
        </span>
      </div>
    </div>
  </div>

  <!-- Search Results -->
  <div id="staff-results" class="mt-4" style="display: none;">
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
                <th>WhatsApp</th>
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
