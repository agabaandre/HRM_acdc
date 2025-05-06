<!-- Staff Search Panel -->
<?php $this->load->view('staff_tab_menu'); ?>
<div class="mt-3">

  <!-- Stylish Search Box -->
  <div id="searchPanel">
    <div class="card border-0 shadow-sm rounded-3" style="border-left: 4px solid #119A48; background-color: #fff;">
      <div class="card-body">
        <label for="staff-search" class="form-label fw-semibold text-muted mb-2">
          <i class="fas fa-search me-1 text-primary"></i> Search Staff
        </label>
        <div class="input-group input-group-lg">
          <input 
            type="text" 
            id="staff-search" 
            class="form-control border-end-0 shadow-none" 
            placeholder="Search by name, SAPNO, or email..." 
            autocomplete="off"
          >
          <span class="input-group-text bg-white border-start-0">
            <i class="fas fa-search text-secondary"></i>
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- Search Results Display -->
  <div id="staff-results" class="mt-3" style="display: none;">
    <div class="card border-0 shadow-sm rounded-3">
      <div class="card-header bg-white border-bottom fw-semibold">
        <i class="fas fa-users text-success me-2"></i>Search Results
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th><i class="fas fa-user me-1 text-muted"></i> Name</th>
                <th><i class="fas fa-venus-mars me-1 text-muted"></i> Gender</th>
                <th><i class="fas fa-id-card me-1 text-muted"></i> SAPNO</th>
                <th><i class="fas fa-envelope me-1 text-muted"></i> Email</th>
                <th><i class="fas fa-phone me-1 text-muted"></i> Telephone</th>
                <th><i class="fas fa-file-contract me-1 text-muted"></i> Contract</th>
              </tr>
            </thead>
            <tbody id="staff-results-body">
              <!-- Dynamic search results go here -->
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>

