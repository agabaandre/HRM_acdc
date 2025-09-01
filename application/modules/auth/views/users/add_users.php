<?php
$usergroups = Modules::run("permissions/getUserGroups");
?>

<div class="container-fluid py-4">
  <!-- Header Section -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h2 class="h3 mb-0 fw-bold text-dark">
            <i class="fa fa-users me-2" style="color:#15ca20;"></i>User Management
          </h2>
          <p class="text-muted mb-0">Manage system users, permissions, and access controls</p>
        </div>
        <div class="d-flex gap-2">
          <a href="<?php echo base_url()?>auth/acdc_users" class="btn btn-outline-success" target="_blank">
            <i class="fa fa-user-plus me-1"></i>Bulk Create Users
          </a>
          <button class="btn text-white btn-sm btn-success" id="exportExcel">
            <i class="fa fa-file-csv me-1"></i>Export to CSV
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Flash Messages -->
  <?php if($this->session->flashdata("msg")): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="fa fa-check-circle me-2"></i>
      <?php echo $this->session->flashdata("msg"); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div> 
  <?php endif; ?>

  <!-- Search and Filters -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <form id="searchForm" class="row g-3">
        <!-- CSRF Token -->
        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
        
        <!-- Global CSRF Token for AJAX calls -->
        <input type="hidden" id="globalCSRFToken" value="<?php echo $this->security->get_csrf_hash(); ?>">
        
        <div class="col-md-4">
          <label for="search" class="form-label fw-bold">Search Users</label>
          <input type="text" 
                 class="form-control" 
                 id="search" 
                 name="search" 
                 placeholder="Search by name, email, or staff ID">
            </div>
        <div class="col-md-3">
          <label for="group_id" class="form-label fw-bold">Filter by Group</label>
          <select class="form-select" id="group_id" name="group_id">
            <option value="">All Groups</option>
            <?php foreach ($usergroups as $group): ?>
              <option value="<?php echo $group->id; ?>">
                <?php echo ucwords($group->group_name); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label for="statuses" class="form-label fw-bold">Filter by Status</label>
          <select class="form-select" id="statuses" name="status">
            <option value="">All Statuses</option>
            <option value="1">Active</option>
            <option value="0">Disabled</option>
          </select>
        </div>
        <div class="col-md-2">
          <label for="pageSize" class="form-label fw-bold">Per Page</label>
          <select class="form-select" id="pageSize" name="pageSize">
            <option value="10">10</option>
            <option value="25" selected>25</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <div class="d-grid gap-2  d-flex flex-direction-row">
            <button type="submit" class="btn btn-sm btn-success">
              <i class="fa fa-search me-1"></i>Search
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary clear-filters">
              <i class="fa fa-refresh me-1"></i>Clear
            </button>
          
          </div>
        </div>
      </form>
          </div>
        </div>

      <!-- Users Table -->
  <div class="card border-0 shadow-lg">
   
    <div class="card-body p-0">
      <div class="table-responsive">
      <div class="d-flex gap-3">
          <!-- Records Count -->
          <div class="bg-white bg-opacity-20 rounded-3 px-3 py-2 d-flex align-items-center gap-2" style="font-size: 0.85rem;">
            <span class="fw-bold" id="totalUsers" style="color: #228B22; font-size: 1rem;">0</span>
            <span class="fw-medium" style="color: #228B22; font-size: 0.95rem;">Total Users</span>
          </div>
          <!-- Filtered Count -->
          <div class="bg-white bg-opacity-20 rounded-3 px-3 py-2 d-flex align-items-center gap-2" id="filteredCount" style="display: none; font-size: 0.85rem;">
            <span class="fw-bold" id="filteredUsers" style="color: #15ca20; font-size: 1rem;">0</span>
            <span class="fw-medium" style="color: #15ca20; font-size: 0.95rem;">Filtered</span>
          </div>
        </div>
        <table class="table table-hover mb-0" id="usersTable">
          <thead class="table-header">
            <tr>
              <th class="py-3 px-4" style="width: 25%;">User Information</th>
              <th class="py-3 px-4" style="width: 25%;">Contact Details</th>
              <th class="py-3 px-4" style="width: 25%;">Role & Status</th>
              <th class="py-3 px-4" style="width: 25%;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <!-- Data will be loaded via AJAX -->
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Information Section -->
  <div class="row mt-4">
    <div class="col-md-6">
      <div class="card border-0 shadow-sm">
      <div class="card-header text-muted py-3" style="background:#FFFFFF;">
          <h6 class="mb-0 fw-bold">
            <i class="fa fa-info-circle me-2"></i>Quick Actions
          </h6>
        </div>
        <div class="card-body">
          <ul class="list-unstyled mb-0">
            <li class="mb-2">
              <i class="fa fa-user-secret text-warning me-2"></i>
              <strong>Impersonate:</strong> Test user access and permissions
            </li>
            <li class="mb-2">
              <i class="fa fa-edit text-primary me-2"></i>
              <strong>Edit User:</strong> Modify user details and permissions
            </li>
            <li class="mb-2">
              <i class="fa fa-toggle-on text-success me-2"></i>
              <strong>Status Toggle:</strong> Activate/deactivate user accounts
            </li>
            <li class="mb-0">
              <i class="fa fa-key text-warning me-2"></i>
              <strong>Reset Password:</strong> Reset user passwords to default
            </li>
          </ul>
        </div>
      </div>
	</div>

    <div class="col-md-6">
      <div class="card border-0 shadow-sm">
        <div class="card-header text-muted py-3" style="background:#FFFFFF;">
          <h6 class="mb-0 fw-bold">
            <i class="fa fa-lightbulb me-2"></i>Export Features
          </h6>
      </div>
      <div class="card-body">
          <ul class="list-unstyled mb-0">
            <li class="mb-2">
              <i class="fa fa-file-csv text-success me-2"></i>
              <strong>CSV Export:</strong> Download all users with complete details
            </li>
            <li class="mb-2">
              <i class="fa fa-filter text-info me-2"></i>
              <strong>Filtered Export:</strong> Export only filtered results
            </li>
            <li class="mb-2">
              <i class="fa fa-search text-primary me-2"></i>
              <strong>Advanced Search:</strong> Find users by name, email, or group
            </li>
            <li class="mb-0">
              <i class="fa fa-download text-success me-2"></i>
              <strong>Real-time Data:</strong> Always export current information
            </li>
          </ul>
        </div>
      </div>
	</div>
	</div>
</div>

<!-- Custom CSS -->
<style>
/* Professional Table Styling */
/* Table Header */
.table-header th {
  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
  font-weight: 700;
  text-transform: uppercase;
  font-size: 0.875rem;
  letter-spacing: 0.5px;
  color: #2c3e50;
  border: none;
  border-bottom: 3px solid rgba(52, 143, 65, 0.2);
  position: relative;
}

.table-header th::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 0;
  height: 3px;
  background: linear-gradient(90deg, rgba(52, 143, 65, 1), rgba(255, 193, 7, 0.8));
  transition: width 0.3s ease;
}

.table-header th:hover::after {
  width: 80%;
}

/* Table Rows */
.user-row {
  transition: all 0.3s ease;
  border-bottom: 1px solid #f1f3f4;
}

.user-row:hover {
  background: linear-gradient(135deg, rgba(52, 143, 65, 0.05) 0%, rgba(255, 193, 7, 0.03) 100%);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.user-row td {
  vertical-align: top;
  border: none;
}

/* User Avatar */
.user-avatar img {
  width: 40px;
  height: 40px;
  object-fit: cover;
  border: 2px solid #e9ecef;
  border-radius: 50%;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}

.user-row:hover .user-avatar img {
  border-color: rgba(52, 143, 65, 0.6);
  box-shadow: 0 4px 16px rgba(52, 143, 65, 0.2);
}

/* User Name */
.user-name {
  font-size: 1rem;
  font-weight: 700;
  color: #2c3e50;
  margin-bottom: 0.75rem;
  line-height: 1.2;
}


/* Ensure consistent badge sizing for role and status */
.role-status-content .badge {
  text-align: center;
  font-size: 0.8rem !important;
  padding: 0.5rem 0.75rem !important;
  border-radius: 0.375rem;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Contact Items */
.contact-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.contact-text {
  color: #495057;
  font-size: 0.875rem;
  font-weight: 500;
  word-break: break-word;
}



/* Action Button Container */
.actions-container {
  min-width: 0;
}

.actions-container .btn {
  min-width: 60px;
  white-space: nowrap;
}


/* Pagination styling */
.pagination .page-link {
  color: #15ca20;
  border-color: #dee2e6;
}

.pagination .page-item.active .page-link {
  background-color: #15ca20;
  border-color: #15ca20;
  color: white;
}

.pagination .page-item.disabled .page-link {
  color: #6c757d;
  background-color: transparent;
  border-color: #dee2e6;
}

.pagination .page-link:hover {
  color: rgba(45, 120, 55, 1);
  background-color: #e9ecef;
  border-color: #dee2e6;
}

/* User Details Modal Styling */
.user-avatar-section img {
  object-fit: cover;
  border: 3px solid #e9ecef;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.form-label {
  font-size: 0.9rem;
  margin-bottom: 0.5rem;
}

.form-text {
  font-size: 0.8rem;
}

.form-check-input:checked {
  background-color: #198754;
  border-color: #198754;
}

.form-check-input:focus {
  border-color: #86b7fe;
  box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.modal-header {
  border-bottom: none;
}

.modal-footer {
  border-top: none;
  background-color: #f8f9fa;
}

.alert {
  border-radius: 0.5rem;
  border: none;
}

/* Animation for status switch */
.form-check-input {
  transition: all 0.3s ease;
}

.form-check-input:checked {
  transform: scale(1.1);
}

/* Table Container Enhancements */
.table-responsive {
  border-radius: 0.5rem;
  overflow: hidden;
}

/* Ensure table columns are properly balanced */
#usersTable {
  table-layout: fixed;
  width: 100%;
}

#usersTable th,
#usersTable td {
  word-wrap: break-word;
  overflow-wrap: break-word;
}

/* Optimize user information column */
.user-details {
  min-width: 0;
  max-width: 100%;
}

.user-name {
  font-size: 0.875rem !important;
  line-height: 1.2;
  margin-bottom: 0.5rem;
}

#usersTable tbody tr:last-child {
  border-bottom: none;
}

/* Loading State */
.loading-row {
  background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: loading 1.5s infinite;
}

@keyframes loading {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 3rem 1rem;
  color: #6c757d;
}

.empty-state i {
  font-size: 3rem;
  margin-bottom: 1rem;
  color: #dee2e6;
}

/* Count Boxes */
.bg-white.bg-opacity-20 {
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.bg-white.bg-opacity-20 .text-white {
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

/* Filtered Count Animation */
#filteredCount {
  animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Responsive Design */
@media (max-width: 1200px) {
  .user-col { width: 25%; }
  .contact-col { width: 25%; }
  .role-col { width: 25%; }
  .actions-col { width: 25%; }
}

@media (max-width: 992px) {
  .professional-table {
    font-size: 0.875rem;
  }
  
  .user-name {
    font-size: 0.9rem;
  }
  
  .action-btn {
    font-size: 0.75rem;
    padding: 0.4rem 0.6rem;
  }
}

@media (max-width: 768px) {
  .user-col { width: 25%; }
  .contact-col { width: 25%; }
  .role-col { width: 25%; }
  .actions-col { width: 25%; }
  
  .user-avatar img {
    width: 40px;
    height: 40px;
  }
  
  .action-row {
    flex-direction: column;
    gap: 0.25rem;
  }
}
</style>

<!-- Consolidated JavaScript -->
<script>
$(document).ready(function() {
  // Initialize tooltips
  $('[data-bs-toggle="tooltip"]').tooltip();
  
  // Simple pagination system
  let currentPage = 1;
  let pageSize = 25;
  let totalUsers = 0;
  
  // Load users function
  function loadUsers(page = 1) {
    currentPage = page;
    
    console.log('loadUsers called with page:', page);
    console.log('Current page set to:', currentPage);
    console.log('Table element check:', $('#usersTable').length);
    console.log('Table tbody check:', $('#usersTable tbody').length);
    
    // Show loading
    $('#usersTable tbody').html('<tr class="loading-row"><td colspan="4" class="text-center py-4"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Loading users...</td></tr>');
    
    // Prepare data
    const data = {
      search: $('#search').val(),
      group_id: $('#group_id').val(),
      status: $('#statuses').val(),
      page: page,
      pageSize: pageSize
    };
    
    console.log('Sending AJAX request with data:', data);
    console.log('Status filter value:', $('#statuses').val());
    console.log('Status filter type:', typeof $('#statuses').val());
    
    $.ajax({
      url: '<?php echo base_url("auth/fetch_users_ajax"); ?>',
      type: 'GET',
      data: data,
      dataType: 'json',
      success: function(response) {
        console.log('AJAX Response:', response);
        console.log('Response type:', typeof response);
        
        // Parse response if it's a string
        let parsedResponse = response;
        if (typeof response === 'string') {
          try {
            parsedResponse = JSON.parse(response);
            console.log('Parsed response:', parsedResponse);
          } catch (parseError) {
            console.error('Error parsing JSON response:', parseError);
            $('#usersTable tbody').html('<tr><td colspan="4" class="empty-state text-danger"><i class="fa fa-exclamation-triangle"></i><br>Error parsing server response. Check console for details.</td></tr>');
            return;
          }
        }
        
        console.log('Response.users:', parsedResponse.users);
        console.log('Response.users length:', parsedResponse.users ? parsedResponse.users.length : 'undefined');
        
        try {
          if (parsedResponse.users && parsedResponse.users.length > 0) {
            console.log('Users found:', parsedResponse.users.length);
            console.log('First user sample:', parsedResponse.users[0]);
            console.log('About to call renderUsers...');
            // Add a small delay to ensure DOM is ready
            setTimeout(function() {
              renderUsers(parsedResponse.users);
              console.log('renderUsers completed');
            }, 100);
            // Update total users count from response
            totalUsers = parsedResponse.totalUsers || parsedResponse.users.length;
            $('#totalUsers').text(totalUsers);
            
            // Render pagination with proper data
            renderPagination(totalUsers, pageSize, page);
            console.log('Updated total users count to:', parsedResponse.users.length);
            
            // Show filtered count if search/filters are applied
            const hasFilters = $('#search').val() || $('#group_id').val() || $('#statuses').val();
            if (hasFilters) {
              $('#filteredCount').show();
              $('#filteredUsers').text(parsedResponse.users.length);
              console.log('Showing filtered count:', parsedResponse.users.length);
            } else {
              $('#filteredCount').hide();
              console.log('Hiding filtered count');
            }
          } else {
            console.log('No users found in response');
            $('#usersTable tbody').html('<tr><td colspan="4" class="empty-state"><i class="fa fa-users"></i><br>No users found</td></tr>');
            totalUsers = parsedResponse.totalUsers || 0;
            renderPagination(totalUsers, pageSize, 1);
            $('#totalUsers').text(totalUsers);
            $('#filteredCount').hide();
          }
        } catch (error) {
          console.error('Error processing response:', error);
          console.error('Error stack:', error.stack);
          $('#usersTable tbody').html('<tr><td colspan="4" class="empty-state text-danger"><i class="fa fa-exclamation-triangle"></i><br>Error processing response. Check console for details.</td></tr>');
        }
      },
      error: function(xhr, status, error) {
        console.error('AJAX Error:', status, error);
        console.error('Response:', xhr.responseText);
        console.error('Status Code:', xhr.status);
        console.error('Response Headers:', xhr.getAllResponseHeaders());
        
        let errorMessage = 'Error loading users. Please try again.';
        if (xhr.status === 500) {
          errorMessage = 'Server error occurred. Please check the console for details.';
        } else if (xhr.status === 403) {
          errorMessage = 'Access denied. Please check your permissions.';
        }
        
        $('#usersTable tbody').html('<tr><td colspan="4" class="empty-state text-danger"><i class="fa fa-exclamation-triangle"></i><br>' + errorMessage + '</td></tr>');
      }
    });
  }
  
  // Render users in table
  function renderUsers(users) {
    console.log('Rendering users:', users);
    
    // Check for duplicate user IDs
    const userIds = users.map(u => u.user_id);
    const uniqueIds = [...new Set(userIds)];
    if (userIds.length !== uniqueIds.length) {
      console.warn('Duplicate user IDs detected!', {
        total: userIds.length,
        unique: uniqueIds.length,
        duplicates: userIds.filter((id, index) => userIds.indexOf(id) !== index)
      });
    }
    
    let html = '';
    
    try {
      users.forEach(function(user) {
        console.log('Processing user:', user);
        
        // Construct full name from title, fname, lname, oname
        let fullName = '';
        if (user.title) fullName += user.title + ' ';
        if (user.fname) fullName += user.fname + ' ';
        if (user.lname) fullName += user.lname;
        if (user.oname) fullName += ' ' + user.oname;
        fullName = fullName.trim() || 'Unknown User';
      
      let photoHtml = '';
      if (user.photo) {
        photoHtml = `<img src="<?php echo base_url('uploads/staff/'); ?>${user.photo}" class="rounded-circle" width="40" height="40" alt="${fullName}">`;
      } else {
        photoHtml = `<img src="<?php echo base_url('assets/images/pp.png'); ?>" class="rounded-circle" width="40" height="40" alt="${fullName}">`;
      }
      
      let statusBadge = '';
      if (user.status == 1) {
        statusBadge = `<span class="badge bg-success badge-sm"><i class="fa fa-check-circle me-1"></i>Active</span>`;
      } else {
        statusBadge = `<span class="badge bg-danger badge-sm"><i class="fa fa-ban me-1"></i>Inactive</span>`;
      }
      
              html += `
        <tr class="user-row">
          <td class="px-3 py-2">
            <div class="d-flex align-items-start">
              <div class="user-avatar me-2">${photoHtml}</div>
              <div class="user-details flex-grow-1">
                <h6 class="user-name mb-1 fw-bold text-dark fs-6">${fullName}</h6>
                <div class="user-ids d-flex flex-column gap-1">
                  <span class="badge bg-light text-dark badge-sm">
                    <i class="fa fa-user-circle me-1"></i>ID: ${user.user_id || 'N/A'}
                  </span>
                  <div class="d-flex gap-1">
                    ${user.staff_id ? `<span class="badge bg-secondary text-white badge-sm"><i class="fa fa-id-card me-1"></i>Staff: ${user.staff_id}</span>` : ''}
                    ${user.SAPNO ? `<span class="badge bg-dark text-white badge-sm"><i class="fa fa-barcode me-1"></i>SAP: ${user.SAPNO}</span>` : ''}
      </div>
    </div>
  </div>
            </div>
          </td>
          <td class="px-3 py-2">
            <div class="contact-details">
              <div class="contact-item mb-2">
                <i class="fa fa-envelope text-primary me-2"></i>
                <span class="contact-text">${user.work_email || 'No email'}</span>
              </div>
              ${user.tel_1 ? `<div class="contact-item mb-2"><i class="fa fa-phone text-success me-2"></i><span class="contact-text">${user.tel_1}</span></div>` : ''}
              ${user.tel_2 ? `<div class="contact-item mb-2"><i class="fa fa-phone-alt text-info me-2"></i><span class="contact-text">${user.tel_2}</span></div>` : ''}
            </div>
          </td>
          <td class="px-3 py-2">
            <div class="role-status-content">
              <div class="role-badge mb-2">
                <span class="badge bg-primary fs-6 px-3 py-2 w-100">
                  <i class="fa fa-user-tag me-1"></i>${user.group_name ? user.group_name.charAt(0).toUpperCase() + user.group_name.slice(1) : 'No Group'}
                </span>
              </div>
              <div class="status-container">
                <span class="badge fs-6 px-3 py-2 w-100 ${user.status == 1 ? 'bg-success' : 'bg-danger'}">
                  <i class="fa ${user.status == 1 ? 'fa-check-circle' : 'fa-ban'} me-1"></i>${user.status == 1 ? 'Active' : 'Inactive'}
                </span>
              </div>
            </div>
          </td>
          <td class="px-3 py-2">
            <div class="actions-container">
              <!-- Action Buttons -->
              <div class="d-flex flex-column gap-2">
                <!-- Top Row: Impersonate & Edit -->
                <div class="d-flex gap-2">
                  <a href="<?php echo site_url('auth/impersonate/'); ?>${user.user_id}" 
                     class="btn btn-outline-warning btn-sm flex-fill shadow-sm px-3 py-1 d-flex align-items-center justify-content-center cute-btn"
                     data-bs-toggle="tooltip" 
                     title="Impersonate this user"
                     style="font-weight:600; font-size:0.95rem; color: #222; border-radius: 0.375rem;">
                    <i class="fa fa-user-secret me-1 text-warning"></i>
                    <span style="color:#222;">Impersonate</span>
                  </a>
                  
                  <button type="button" 
                          class="btn btn-outline-primary btn-sm flex-fill shadow-sm px-3 py-1 d-flex align-items-center justify-content-center cute-btn edit-user"
                          data-user-id="${user.user_id}"
                          data-user-data="${encodeURIComponent(JSON.stringify(user))}"
                          data-csrf="<?php echo $this->security->get_csrf_hash(); ?>"
                          title="Edit user details"
                          style="font-weight:600; font-size:0.95rem; color: #222; border-radius: 0.375rem;">
                    <i class="fa fa-edit me-1 text-primary"></i>
                    <span style="color:#222;">Edit</span>
                  </button>
                </div>
                
                <!-- Bottom Row: Status & Reset -->
                <div class="d-flex gap-2">
                  ${
                    user.status == 1 ? 
                    `<button type="button" class="btn btn-outline-danger btn-sm flex-fill shadow-sm px-3 py-1 d-flex align-items-center justify-content-center cute-btn block-user" data-user-id="${user.user_id}" data-user-name="${fullName}" style="font-weight:600; font-size:0.95rem; color: #222; border-radius: 0.375rem;"><i class='fa fa-ban me-1 text-danger'></i><span style='color:#222;'>Block</span></button>` :
                    `<button type="button" class="btn btn-outline-success btn-sm flex-fill shadow-sm px-3 py-1 d-flex align-items-center justify-content-center cute-btn unblock-user" data-user-id="${user.user_id}" data-user-name="${fullName}" style="font-weight:600; font-size:0.95rem; color: #222; border-radius: 0.375rem;"><i class='fa fa-check me-1 text-success'></i><span style='color:#222;'>Activate</span></button>`
                  }
                  
                  <button type="button" 
                          class="btn btn-outline-secondary btn-sm flex-fill shadow-sm px-3 py-1 d-flex align-items-center justify-content-center cute-btn reset-password" 
                          data-user-id="${user.user_id}" 
                          data-user-name="${fullName}"
                          style="font-weight:600; font-size:0.95rem; color: #222; border-radius: 0.375rem;">
                    <i class="fa fa-key me-1 text-warning"></i>
                    <span style="color:#222;">Reset</span>
                  </button>
                </div>
              </div>
            </div>
          </td>
        </tr>
      `;
      });
    } catch (error) {
      console.error('Error generating HTML:', error);
      html = '<tr><td colspan="4" class="text-center py-4 text-danger">Error generating user data. Check console for details.</td></tr>';
    }
    
    console.log('Generated HTML length:', html.length);
    console.log('Table element exists:', $('#usersTable tbody').length > 0);
    console.log('Table tbody element:', $('#usersTable tbody')[0]);
    
    try {
      console.log('Attempting to update table...');
      console.log('HTML content length:', html.length);
      console.log('HTML preview:', html.substring(0, 200) + '...');
      
      // Test if we can update the table at all
      $('#usersTable tbody').html('<tr><td colspan="4" class="empty-state text-success"><i class="fa fa-check-circle"></i><br>Test update successful!</td></tr>');
      console.log('Test update completed');
      
      // Now update with actual content
      $('#usersTable tbody').html(html);
      console.log('Table updated successfully with user data');
    } catch (error) {
      console.error('Error updating table:', error);
      console.error('Error details:', error.message);
    }
  }
  
  // Render pagination with smart page display (max 5 pages + ellipsis)
  function renderPagination(total, pageSize, currentPage) {
    const totalPages = Math.ceil(total / pageSize);
    let html = '';
    
    if (totalPages > 1) {
      html = '<div class="card-footer bg-light"><div class="row align-items-center"><div class="col-md-6"><span class="text-muted small">Showing ' + ((currentPage - 1) * pageSize + 1) + ' to ' + Math.min(currentPage * pageSize, total) + ' of ' + total + ' users</span></div><div class="col-md-6"><nav><ul class="pagination justify-content-end mb-0">';
      
      // Previous button
      if (currentPage > 1) {
        html += '<li class="page-item"><a class="page-link" href="#" data-page="' + (currentPage - 1) + '">&laquo; Previous</a></li>';
      }
      
      // Smart page number display (max 5 pages + ellipsis)
      let startPage = Math.max(1, currentPage - 2);
      let endPage = Math.min(totalPages, startPage + 4);
      
      // Adjust start page if we're near the end
      if (endPage - startPage < 4) {
        startPage = Math.max(1, endPage - 4);
      }
      
      // First page (if not in range)
      if (startPage > 1) {
        html += '<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>';
        if (startPage > 2) {
          html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
      }
      
      // Page numbers in range
      for (let i = startPage; i <= endPage; i++) {
        if (i === currentPage) {
          html += '<li class="page-item active"><span class="page-link">' + i + '</span></li>';
        } else {
          html += '<li class="page-item"><a class="page-link" href="#" data-page="' + i + '">' + i + '</a></li>';
        }
      }
      
      // Last page (if not in range)
      if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
          html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        html += '<li class="page-item"><a class="page-link" href="#" data-page="' + totalPages + '">' + totalPages + '</a></li>';
      }
      
      // Next button
      if (currentPage < totalPages) {
        html += '<li class="page-item"><a class="page-link" href="#" data-page="' + (currentPage + 1) + '">Next &raquo;</a></li>';
      }
      
      html += '</ul></nav></div></div></div>';
    }
    
    // Remove existing pagination and add new one
    $('#usersTable').closest('.card').find('.card-footer').remove();
    if (html) {
      $('#usersTable').closest('.card').append(html);
    }
  }
  
  // Handle filter changes
  $('#group_id, #statuses').change(function() {
    console.log('Filter changed:', $(this).attr('id'), 'Value:', $(this).val());
    loadUsers(1);
  });
  
  // Handle page size changes
  $('#pageSize').change(function() {
    pageSize = parseInt($(this).val());
    loadUsers(1);
  });
  
  // Handle pagination clicks using event delegation
  $(document).on('click', '.pagination .page-link[data-page]', function(e) {
    e.preventDefault();
    const page = parseInt($(this).data('page'));
    console.log('Pagination clicked:', page);
    if (page && page > 0) {
      console.log('Loading page:', page);
      loadUsers(page);
    }
  });
  
  // Handle search input with debouncing
  let searchTimeout;
  $('#search').on('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(function() {
      loadUsers(1);
    }, 500); // Wait 500ms after user stops typing
  });
  
  // Handle search form submission
  $('#searchForm').submit(function(e) {
    e.preventDefault();
    loadUsers(1);
  });
  
  // Handle clear filters
  $('.clear-filters').click(function() {
    $('#group_id, #statuses').val('');
    $('#search').val('');
    $('#pageSize').val('25');
    pageSize = 25;
    $('#filteredCount').hide();
    loadUsers(1);
  });
  
  // Test status filter button
  $('.test-status-filter').click(function() {
    console.log('Testing status filter...');
    $('#statuses').val('0'); // Set to Disabled
    console.log('Status set to:', $('#statuses').val());
    loadUsers(1);
  });
  
  // CSV Export functionality
  $('#exportExcel').click(function() {
    exportToCSV();
  });
  
  function exportToCSV() {
    // Get current filters
    const search = $('#search').val();
    const groupId = $('#group_id').val();
    const status = $('#statuses').val();
    
    // Create export URL with filters
    let exportUrl = '<?php echo base_url("auth/export_users_excel"); ?>?';
    if (search) exportUrl += 'search=' + encodeURIComponent(search) + '&';
    if (groupId) exportUrl += 'group_id=' + groupId + '&';
    if (status !== '') exportUrl += 'status=' + status;
    
    // Trigger download
    window.location.href = exportUrl;
  }
  
  // Refresh CSRF token periodically to prevent expiration
  function refreshCSRFToken() {
    $.ajax({
      url: '<?php echo base_url("auth/refreshCSRF"); ?>',
      type: 'GET',
      success: function(response) {
        if (response.csrf_token) {
          // Update all CSRF token fields
          $('input[name="<?php echo $this->security->get_csrf_token_name(); ?>"]').val(response.csrf_token);
          
          // Update global CSRF token field
          $('#globalCSRFToken').val(response.csrf_token);
          
          // Also update CSRF tokens on action buttons (for backward compatibility)
          updateButtonCSRFTokens(response.csrf_token);
          
          console.log('CSRF token refreshed:', response.csrf_token);
        }
      }
    });
  }
  
  // Get fresh CSRF token synchronously
  function getFreshCSRFToken() {
    return new Promise((resolve, reject) => {
      $.ajax({
        url: '<?php echo base_url("auth/refreshCSRF"); ?>',
        type: 'GET',
        success: function(response) {
          if (response.csrf_token) {
            // Update all CSRF token fields
            $('input[name="<?php echo $this->security->get_csrf_token_name(); ?>"]').val(response.csrf_token);
            $('#globalCSRFToken').val(response.csrf_token);
            updateButtonCSRFTokens(response.csrf_token);
            console.log('CSRF token refreshed before action:', response.csrf_token);
            resolve(response.csrf_token);
          } else {
            reject('No CSRF token received');
          }
        },
        error: function(xhr, status, error) {
          reject('Failed to refresh CSRF token: ' + error);
        }
      });
    });
  }
  
  // Update CSRF tokens on all action buttons
  function updateButtonCSRFTokens(newToken) {
    $('.block-user, .unblock-user, .reset-password').each(function() {
      $(this).data('csrf', newToken);
    });
    console.log('Updated CSRF tokens on action buttons');
  }
  
  // Refresh CSRF token every 30 minutes
  setInterval(refreshCSRFToken, 30 * 60 * 1000);
  
  // Block User
  $(document).on('click', '.block-user', function() {
    var userId = $(this).data('user-id');
    var userName = $(this).data('user-name');
    
    $('#blockUserName').text(userName);
    $('#confirmBlockUser').data('user-id', userId);
    var blockModal = new bootstrap.Modal(document.getElementById('blockUserModal'));
    blockModal.show();
  });

  $('#confirmBlockUser').click(function() {
    var userId = $(this).data('user-id');
    blockUser(userId);
  });

  // Unblock User
  $(document).on('click', '.unblock-user', function() {
    var userId = $(this).data('user-id');
    var userName = $(this).data('user-name');
    
    $('#unblockUserName').text(userName);
    $('#confirmUnblockUser').data('user-id', userId);
    var unblockModal = new bootstrap.Modal(document.getElementById('unblockUserModal'));
    unblockModal.show();
  });

  $('#confirmUnblockUser').click(function() {
    var userId = $(this).data('user-id');
    unblockUser(userId);
  });

  // Reset Password
  $(document).on('click', '.reset-password', function() {
    var userId = $(this).data('user-id');
    var userName = $(this).data('user-name');
    
    $('#resetPasswordUserName').text(userName);
    $('#confirmResetPassword').data('user-id', userId);
    var resetModal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
    resetModal.show();
  });

  $('#confirmResetPassword').click(function() {
    var userId = $(this).data('user-id');
    resetPassword(userId);
  });

  // AJAX Functions
  function blockUser(userId) {
    // Get fresh CSRF token before making the request
    getFreshCSRFToken().then(function(csrfToken) {
      console.log('Blocking user:', userId, 'CSRF token:', csrfToken);
      
      $.ajax({
        url: '<?= base_url("auth/blockUser") ?>',
      method: 'POST',
        data: { 
          user_id: userId,
          '<?php echo $this->security->get_csrf_token_name(); ?>': csrfToken
        },
      dataType: 'json',
        success: function(response) {
          showNotification(response.message || 'User blocked successfully', 'success');
          // Close the modal
          closeModal('blockUserModal');
        setTimeout(function() {
            loadUsers(currentPage);
          }, 1500);
        },
        error: function(xhr, status, error) {
          console.error('Block user error:', xhr.status, error);
          console.error('Response:', xhr.responseText);
          showNotification('Error blocking user: ' + (xhr.responseText || error), 'error');
          // Keep modal open on error so user can try again
        }
      });
    }).catch(function(error) {
      console.error('Failed to get CSRF token:', error);
      showNotification('Error: Failed to get security token. Please refresh the page.', 'error');
    });
  }

  function unblockUser(userId) {
    // Get fresh CSRF token before making the request
    getFreshCSRFToken().then(function(csrfToken) {
      console.log('Unblocking user:', userId, 'CSRF token:', csrfToken);
      
      $.ajax({
        url: '<?= base_url("auth/unblockUser") ?>',
        method: 'POST',
        data: { 
          user_id: userId,
          '<?php echo $this->security->get_csrf_token_name(); ?>': csrfToken
        },
        dataType: 'json',
        success: function(response) {
          showNotification(response.message || 'User activated successfully', 'success');
          // Close the modal
          closeModal('unblockUserModal');
          setTimeout(function() {
            loadUsers(currentPage);
          }, 1500);
        },
        error: function(xhr, status, error) {
          console.error('Unblock user error:', xhr.status, error);
          console.error('Response:', xhr.responseText);
          showNotification('Error activating user: ' + (xhr.responseText || error), 'error');
          // Keep modal open on error so user can try again
        }
      });
    }).catch(function(error) {
      console.error('Failed to get CSRF token:', error);
      showNotification('Error: Failed to get security token. Please refresh the page.', 'error');
    });
  }

  function resetPassword(userId) {
    // Get fresh CSRF token before making the request
    getFreshCSRFToken().then(function(csrfToken) {
      console.log('Resetting password for user:', userId, 'CSRF token:', csrfToken);
      
      $.ajax({
        url: '<?= base_url("auth/resetPass") ?>',
        method: 'POST',
        data: { 
          user_id: userId,
          password: '<?= setting()->default_password ?? "password123" ?>',
          '<?php echo $this->security->get_csrf_token_name(); ?>': csrfToken
        },
        dataType: 'json',
        success: function(response) {
          showNotification(response.message || 'Password reset successfully', 'success');
          // Close the modal
          closeModal('resetPasswordModal');
          setTimeout(function() {
            loadUsers(currentPage);
          }, 1500);
        },
        error: function(xhr, status, error) {
          console.error('Reset password error:', xhr.status, error);
          console.error('Response:', xhr.responseText);
          showNotification('Error resetting password: ' + (xhr.responseText || error), 'error');
          // Keep modal open on error so user can try again
        }
      });
    }).catch(function(error) {
      console.error('Failed to get CSRF token:', error);
      showNotification('Error: Failed to get security token. Please refresh the page.', 'error');
    });
  }

  // Helper function to close modals
  function closeModal(modalId) {
    var modal = bootstrap.Modal.getInstance(document.getElementById(modalId));
    if (modal) {
      modal.hide();
    }
  }
  
  // Notification function
  function showNotification(message, type) {
    if (typeof Lobibox !== 'undefined') {
      Lobibox.notify(type, {
        pauseDelayOnHover: true,
        continueDelayOnInactiveTab: false,
        position: 'top right',
        icon: 'bx bx-check-circle',
        msg: message
    });
    } else {
      alert(message);
    }
  }

  // Close modals after actions
  $('.modal').on('hidden.bs.modal', function() {
    $(this).find('.btn').removeData('user-id');
  });
  
  // Handle modal close buttons
  $('.btn-close, .btn[data-bs-dismiss="modal"]').click(function() {
    var modalId = $(this).closest('.modal').attr('id');
    closeModal(modalId);
  });
  
  // Initialize all modals
  var modals = document.querySelectorAll('.modal');
  modals.forEach(function(modalElement) {
    new bootstrap.Modal(modalElement);
  });
  
  // Handle edit user button clicks
  $(document).on('click', '.edit-user', function() {
    try {
      var encodedData = $(this).data('user-data');
      console.log('Encoded user data:', encodedData);
      var userData = JSON.parse(decodeURIComponent(encodedData));
      console.log('Decoded user data:', userData);
      populateUserModal(userData);
      var userModal = new bootstrap.Modal(document.getElementById('userDetailsModal'));
      userModal.show();
    } catch (error) {
      console.error('Error parsing user data:', error);
      alert('Error loading user data. Please try again.');
    }
  });
  
  // Populate user modal with data
  function populateUserModal(user) {
    console.log('Populating modal with user:', user);
    
    // Construct full name from title, fname, lname, oname
    let fullName = '';
    if (user.title) fullName += user.title + ' ';
    if (user.fname) fullName += user.fname + ' ';
    if (user.lname) fullName += user.lname;
    if (user.oname) fullName += ' ' + user.oname;
    fullName = fullName.trim() || 'Unknown User';
    
    // Set modal title and basic info
    $('#modalUserName').text(fullName);
    $('#modalUserDisplayName').text(fullName);
    $('#modalUserId').text(user.user_id || 'N/A');
    $('#modalStaffId').text(user.staff_id || 'N/A');
    $('#modalDivision').text(user.division_name || 'N/A');
    
    // Set form values
    $('#modalUserNameInput').val(fullName);
    $('#modalUserRole').val(user.role || '');
    $('#modalUserEmail').val(user.work_email || '');
    
    // Set status
    var isActive = user.status == 1;
    $('#modalStatusSwitch').prop('checked', isActive);
    $('#modalStatusLabel').text(isActive ? 'Active' : 'Inactive');
    $('#modalStatusField').val(isActive ? 1 : 0);
    
    // Update status badge
    var statusBadge = $('#modalStatusBadge');
    if (isActive) {
      statusBadge.removeClass('bg-danger').addClass('bg-success').text('Enabled');
    } else {
      statusBadge.removeClass('bg-success').addClass('bg-danger').text('Disabled');
    }
    
    // Set hidden fields
    $('#modalUserIdHidden').val(user.user_id || '');
    
    // Update avatar if available
    if (user.photo) {
      $('#modalUserAvatar').attr('src', '<?php echo base_url("uploads/staff/"); ?>' + user.photo);
    } else {
      $('#modalUserAvatar').attr('src', '<?php echo base_url("assets/images/pp.png"); ?>');
    }
  }
  
  // Handle status switch change in modal
  $('#modalStatusSwitch').change(function() {
    var isChecked = $(this).is(':checked');
    var statusValue = isChecked ? 1 : 0;
    var statusText = isChecked ? 'Active' : 'Inactive';
    var statusClass = isChecked ? 'success' : 'danger';
    var statusLabel = isChecked ? 'Enabled' : 'Disabled';
    
    // Update hidden field
    $('#modalStatusField').val(statusValue);
    
    // Update label
    $('#modalStatusLabel').text(statusText);
    
    // Update badge
    var badge = $('#modalStatusBadge');
    badge.removeClass('bg-success bg-danger').addClass('bg-' + statusClass).text(statusLabel);
    
    // Show status message
    var messageBox = $('#modalStatusMessage');
    var messageText = $('#modalMessageText');
    
    messageText.text('Status changed to: ' + statusText);
    messageBox.removeClass('d-none').addClass('show');
    
    // Hide message after 3 seconds
    setTimeout(function() {
      messageBox.removeClass('show').addClass('d-none');
    }, 3000);
  });
  
  // Handle user update form submission
  $('#updateUserForm').submit(function(e) {
    e.preventDefault();

    var formData = new FormData(this);
    var submitBtn = $(this).find('button[type="submit"]');
    var originalText = submitBtn.html();

    // Disable submit button and show loading
    submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i>Saving...');

    $.ajax({
      url: '<?= base_url("auth/updateUser") ?>',
      method: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      dataType: 'json',
      success: function(response) {
        // Show success message
        var messageBox = $('#modalStatusMessage');
        var messageText = $('#modalMessageText');
        
        messageText.html('<i class="fa fa-check-circle me-2"></i>' + (response.message || 'User updated successfully'));
        messageBox.removeClass('d-none alert-info').addClass('show alert-success');
        
        // Close modal and reload users after 2 seconds
        setTimeout(function() {
          closeModal('userDetailsModal');
          loadUsers(currentPage);
        }, 2000);
      },
      error: function(xhr, status, error) {
        // Show error message
        var messageBox = $('#modalStatusMessage');
        var messageText = $('#modalMessageText');
        
        messageText.html('<i class="fa fa-exclamation-triangle me-2"></i>Error updating user. Please try again.');
        messageBox.removeClass('d-none alert-info').addClass('show alert-danger');
        
        // Re-enable submit button
        submitBtn.prop('disabled', false).html(originalText);
      }
    });
  });

  // Load initial data
  loadUsers(1);
  
  // Refresh CSRF token immediately on page load
  refreshCSRFToken();
  
  // Make loadUsers function globally accessible for pagination
  window.loadUsers = loadUsers;
});
</script>

<!-- Dynamic User Details Modal (will be populated by JavaScript) -->
<div class="modal fade" id="userDetailsModal" tabindex="-1" aria-labelledby="userDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="userDetailsModalLabel">
          <i class="fa fa-user-edit me-2"></i>Edit User: <span id="modalUserName"></span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body">
        <div class="row">
          <!-- User Avatar Section -->
          <div class="col-md-3 text-center mb-3">
            <div class="user-avatar-section">
              <img src="<?php echo base_url('assets/images/pp.png'); ?>" 
                   class="rounded-circle mb-3" width="80" height="80" 
                   id="modalUserAvatar" alt="User Avatar">
              <h6 class="text-muted" id="modalUserDisplayName"></h6>
              <small class="text-muted">ID: <span id="modalUserId"></span></small>
            </div>
          </div>
          
          <!-- User Details Form -->
          <div class="col-md-9">
            <form id="updateUserForm" class="update_user" method="POST">
              <div class="row">
                <!-- Name Field -->
                <div class="col-md-6 mb-3">
                  <label class="form-label fw-bold">
                    <i class="fa fa-user me-1 text-primary"></i>Full Name
                  </label>
                  <input type="text" 
                         name="name" 
                         id="modalUserNameInput"
                         class="form-control" 
                         required 
                         maxlength="100">
                  <div class="form-text">Enter the user's full name</div>
                </div>
                
                <!-- User Group Field -->
                <div class="col-md-6 mb-3">
                  <label class="form-label fw-bold">
                    <i class="fa fa-users me-1 text-success"></i>User Group
                  </label>
                  <select name="role" id="modalUserRole" class="form-select role" required>
                    <option value="">Select User Group</option>
                    <?php foreach ($usergroups as $usergroup): ?>
                      <option value="<?php echo $usergroup->id; ?>">
                        <?php echo htmlspecialchars($usergroup->group_name); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="form-text">Choose the appropriate user group</div>
                </div>
              </div>
              
              <div class="row">
                <!-- Email Field -->
                <div class="col-md-6 mb-3">
                  <label class="form-label fw-bold">
                    <i class="fa fa-envelope me-1 text-info"></i>Work Email
                  </label>
                  <input type="email" 
                         id="modalUserEmail"
                         class="form-control" 
                         readonly>
                  <div class="form-text text-muted">Email cannot be changed</div>
                </div>
                
                <!-- Status Field -->
                <div class="col-md-6 mb-3">
                  <label class="form-label fw-bold">
                    <i class="fa fa-toggle-on me-1 text-warning"></i>Account Status
                  </label>
                  <div class="d-flex align-items-center">
                    <div class="form-check form-switch me-3">
                      <input class="form-check-input" type="checkbox" 
                             id="modalStatusSwitch">
                      <label class="form-check-label" for="modalStatusSwitch" id="modalStatusLabel">
                        Active
                      </label>
                    </div>
                    <span class="badge bg-success" id="modalStatusBadge">
                      Enabled
                    </span>
                  </div>
                  <div class="form-text">Toggle user account status</div>
                </div>
              </div>
              
              <!-- Additional Information -->
              <div class="row">
                <div class="col-12 mb-3">
                  <label class="form-label fw-bold">
                    <i class="fa fa-info-circle me-1 text-secondary"></i>Additional Information
                  </label>
                  <div class="row">
                    <div class="col-md-6">
                      <small class="text-muted">
                        <i class="fa fa-calendar me-1"></i>Staff ID: <span id="modalStaffId">N/A</span>
                      </small>
                    </div>
                    <div class="col-md-6">
                      <small class="text-muted">
                        <i class="fa fa-building me-1"></i>Division: <span id="modalDivision">N/A</span>
                      </small>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Hidden Fields -->
              <input type="hidden" name="user_id" id="modalUserIdHidden">
              <input type="hidden" name="status" id="modalStatusField">
              <!-- CSRF Token -->
              <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
              
              <!-- Status Messages -->
              <div class="alert alert-info d-none" id="modalStatusMessage">
                <i class="fa fa-info-circle me-2"></i>
                <span id="modalMessageText"></span>
              </div>
            </form>
          </div>
        </div>
      </div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="fa fa-times me-1"></i>Cancel
        </button>
        <button type="submit" form="updateUserForm" class="btn btn-primary">
          <i class="fa fa-save me-1"></i>Save Changes
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Block User Confirmation Modal -->
<div class="modal fade" id="blockUserModal" tabindex="-1" aria-labelledby="blockUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="blockUserModalLabel">
          <i class="fa fa-ban me-2"></i>Block User
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to block <strong id="blockUserName"></strong>?</p>
        <p class="text-muted small">This will prevent the user from accessing the system.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmBlockUser">
          <i class="fa fa-ban me-1"></i>Yes, Block User
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Unblock User Confirmation Modal -->
<div class="modal fade" id="unblockUserModal" tabindex="-1" aria-labelledby="unblockUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="unblockUserModalLabel">
          <i class="fa fa-check me-2"></i>Activate User
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to activate <strong id="unblockUserName"></strong>?</p>
        <p class="text-muted small">This will restore the user's access to the system.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="confirmUnblockUser">
          <i class="fa fa-check me-1"></i>Yes, Activate User
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Reset Password Confirmation Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title" id="resetPasswordModalLabel">
          <i class="fa fa-key me-2"></i>Reset Password
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to reset the password for <strong id="resetPasswordUserName"></strong>?</p>
        <p class="text-muted small">The password will be reset to the default password.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning" id="confirmResetPassword">
          <i class="fa fa-key me-1"></i>Yes, Reset Password
        </button>
      </div>
    </div>
  </div>
</div>
