<?php

?>

<div class="container-fluid py-4">
  <!-- Header Section -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h2 class="h3 mb-0 fw-bold text-dark">
            <i class="fa fa-user-shield me-2" style="color: #119A48;"></i>User Permissions Management
          </h2>
          <p class="text-muted mb-0">Manage individual user permissions and override group permissions</p>
        </div>
        <div class="d-flex gap-2">
          <a href="<?php echo base_url('permissions'); ?>" class="btn btn-outline-secondary">
            <i class="fa fa-arrow-left me-1"></i>Back to Groups
          </a>
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
    <div class="card-header text-white py-3" style="background: #1aad5a;">
      <h6 class="mb-0 fw-bold">
        <i class="fa fa-search me-2"></i>Search & Filters
      </h6>
    </div>
    <div class="card-body">
      <form id="searchForm" class="row g-3">
        <!-- CSRF Token -->
        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
        
                <div class="col-md-6">
          <label for="search" class="form-label fw-bold">Search Users</label>
          <input type="text" 
                 class="form-control" 
                 id="search" 
                 name="search" 
                 placeholder="Search by name, email, or staff ID">
        </div>
        <div class="col-md-4">
          <label for="group_id" class="form-label fw-bold">Filter by Group</label>
          <select class="form-select" id="group_id" name="group_id">
            <option value="">All Groups</option>
            <?php 
            $groups = Modules::run('permissions/getUserGroups');
            foreach ($groups as $group): 
            ?>
              <option value="<?php echo $group->id; ?>">
                <?php echo ucwords($group->group_name); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      
        <div class="col-md-3 d-flex align-items-end">
          <div class="d-grid gap-2 w-100">
            <button type="submit" class="btn text-white" style="background: #119A48; border-color: #119A48;">
              <i class="fa fa-search me-1"></i>Search
            </button>
            <button type="button" class="btn btn-outline-secondary clear-filters">
              <i class="fa fa-refresh me-1"></i>Clear
            </button>
          </div>
        </div>
        
        <div class="col-md-1 d-flex align-items-end">
          <div class="form-group mb-0">
            <label for="pageSize" class="form-label fw-bold small">Per Page</label>
            <select class="form-select form-select-sm" id="pageSize" name="pageSize">
              <option value="10">10</option>
              <option value="25" selected>25</option>
              <option value="50">50</option>
              <option value="100">100</option>
            </select>
          </div>
        </div>
      </form>
    </div>
  </div>

    <!-- Users Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-header text-white py-3" style="background: #119A48;">
      <div class="d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold">
          <i class="fa fa-users me-2"></i>Users and Their Permissions
        </h5>
        <div class="text-white">
          <span class="badge bg-light text-dark">
            <i class="fa fa-database me-1"></i><span id="totalUsers">0</span> Total Users
          </span>
        </div>
      </div>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0" id="usersTable">
          <thead class="bg-light">
            <tr>
              <th class="border-0 py-3 px-4">User</th>
              <th class="border-0 py-3">Group</th>
              <th class="border-0 py-3">Custom Permissions</th>
              <th class="border-0 py-3">Status</th>
              <th class="border-0 py-3 px-4">Actions</th>
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
        <div class="card-header text-white py-3" style="background: #1aad5a;">
          <h6 class="mb-0 fw-bold">
            <i class="fa fa-info-circle me-2"></i>How It Works
          </h6>
        </div>
        <div class="card-body">
          <ul class="list-unstyled mb-0">
            <li class="mb-2">
              <i class="fa fa-check-circle text-success me-2"></i>
              <strong>Group Permissions:</strong> Users inherit permissions from their assigned group
            </li>
            <li class="mb-2">
              <i class="fa fa-plus-circle text-primary me-2"></i>
              <strong>Custom Permissions:</strong> Add specific permissions to individual users
            </li>
            <li class="mb-2">
              <i class="fa fa-times-circle text-warning me-2"></i>
              <strong>Permission Override:</strong> Custom permissions take precedence over group permissions
            </li>
            <li class="mb-0">
              <i class="fa fa-sync text-info me-2"></i>
              <strong>Copy Group:</strong> Quickly copy all group permissions to a user
            </li>
          </ul>
        </div>
      </div>
    </div>
    
    <div class="col-md-6">
      <div class="card border-0 shadow-sm">
        <div class="card-header text-white py-3" style="background: #119A48;">
          <h6 class="mb-0 fw-bold">
            <i class="fa fa-lightbulb me-2"></i>Best Practices
          </h6>
        </div>
        <div class="card-body">
          <ul class="list-unstyled mb-0">
            <li class="mb-2">
              <i class="fa fa-star text-warning me-2"></i>
              <strong>Use Groups:</strong> Assign most permissions through groups for easier management
            </li>
            <li class="mb-2">
              <i class="fa fa-user-plus text-primary me-2"></i>
              <strong>Custom Overrides:</strong> Only add custom permissions when absolutely necessary
            </li>
            <li class="mb-2">
              <i class="fa fa-regular fa-calendar-check text-success me-2"></i>
              <strong>Regular Review:</strong> Periodically review custom permissions to ensure they're still needed
            </li>
            <li class="mb-0">
              <i class="fa fa-documentation text-info me-2"></i>
              <strong>Documentation:</strong> Keep track of why custom permissions were granted
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Custom CSS -->
<style>
.avatar-sm img {
  object-fit: cover;
  border: 2px solid #e9ecef;
}

.table th {
  font-weight: 600;
  text-transform: uppercase;
  font-size: 0.875rem;
  letter-spacing: 0.5px;
}

.badge {
  font-size: 0.75rem;
}

.btn-group .btn {
  border-radius: 0.375rem !important;
}

.btn-group .btn:not(:last-child) {
  margin-right: 0.25rem;
}

/* AU Green Button Hover States */
.btn[style*="background: #119A48"]:hover {
  background-color: #0f8a42 !important;
  border-color: #0f8a42 !important;
}

.btn[style*="background: #1aad5a"]:hover {
  background-color: #0f8a42 !important;
  border-color: #0f8a42 !important;
}

/* Enhanced Card Shadows */
.card {
  border: 1px solid rgba(17, 154, 72, 0.1);
}

.card:hover {
  border-color: rgba(17, 154, 72, 0.3);
}

/* Pagination styling */
.pagination .page-link {
  color: #119A48;
  border-color: #dee2e6;
}

.pagination .page-item.active .page-link {
  background-color: #119A48;
  border-color: #119A48;
  color: white;
}

.pagination .page-item.disabled .page-link {
  color: #6c757d;
  background-color: transparent;
  border-color: #dee2e6;
}

.pagination .page-link:hover {
  color: #0d7a3a;
  background-color: #e9ecef;
  border-color: #dee2e6;
}
</style>

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
    
    // Show loading
    $('#usersTable tbody').html('<tr><td colspan="5" class="text-center py-4"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Loading users...</td></tr>');
    
    // Prepare data
    const data = {
      '<?php echo $this->security->get_csrf_token_name(); ?>': '<?php echo $this->security->get_csrf_hash(); ?>',
      search: $('#search').val(),
      group_id: $('#group_id').val(),
      page: page,
      pageSize: pageSize
    };
    
    $.ajax({
      url: '<?php echo base_url("permissions/userpermissions/getUsersAjax"); ?>',
      type: 'POST',
      data: data,
      success: function(response) {
        if (response.data && response.data.length > 0) {
          renderUsers(response.data);
          totalUsers = response.recordsTotal;
          renderPagination(response.recordsTotal, pageSize, page);
          $('#totalUsers').text(response.recordsTotal);
        } else {
          $('#usersTable tbody').html('<tr><td colspan="5" class="text-center py-4 text-muted">No users found</td></tr>');
          renderPagination(0, pageSize, 1);
          $('#totalUsers').text('0');
        }
      },
      error: function() {
        $('#usersTable tbody').html('<tr><td colspan="5" class="text-center py-4 text-danger">Error loading users. Please try again.</td></tr>');
      }
    });
  }
  
  // Render users in table
  function renderUsers(users) {
    let html = '';
    users.forEach(function(user) {
      let photoHtml = '';
      if (user.photo) {
        photoHtml = `<img src="<?php echo base_url('uploads/photos/'); ?>${user.photo}" class="rounded-circle" width="40" height="40" alt="${user.name || ''}">`;
      } else {
        photoHtml = `<img src="<?php echo base_url('assets/images/pp.png'); ?>" class="rounded-circle" width="40" height="40" alt="${user.name || ''}">`;
      }
      
      let statusHtml = '';
      if ((user.permission_count || 0) > 0) {
        statusHtml = `<small class="text-success"><i class="fa fa-check-circle me-1"></i>Has overrides</small>`;
      } else {
        statusHtml = `<small class="text-muted"><i class="fa fa-info-circle me-1"></i>Uses group permissions</small>`;
      }
      
      let statusBadge = '';
      if (user.status == 1) {
        statusBadge = `<span class="badge bg-success"><i class="fa fa-check-circle me-1"></i>Active</span>`;
      } else {
        statusBadge = `<span class="badge bg-danger"><i class="fa fa-ban me-1"></i>Inactive</span>`;
      }
      
      html += `
        <tr>
          <td class="px-4">
            <div class="d-flex align-items-center">
              <div class="avatar-sm me-3">${photoHtml}</div>
              <div>
                <h6 class="mb-1 fw-bold">${user.name || 'Unknown User'}</h6>
                <small class="text-muted">User ID: ${user.user_id || 'N/A'}</small>
                ${user.auth_staff_id ? `<br><small class="text-muted">Staff ID: ${user.auth_staff_id}</small>` : ''}
              </div>
            </div>
          </td>
          <td>
            <span class="badge bg-info">
              ${user.group_name ? user.group_name.charAt(0).toUpperCase() + user.group_name.slice(1) : 'No Group'}
            </span>
          </td>
          <td>
            <div class="d-flex align-items-center">
              <span class="badge bg-primary me-2">${user.permission_count || 0} custom</span>
              ${statusHtml}
            </div>
          </td>
          <td>${statusBadge}</td>
          <td class="px-4">
            <div class="btn-group btn-group-sm">
              <a href="<?php echo base_url('permissions/userpermissions/userDetails/'); ?>${user.user_id}" class="btn btn-outline-primary btn-sm">
                <i class="fa fa-cog me-1"></i>Manage
              </a>
            </div>
          </td>
        </tr>
      `;
    });
    
    $('#usersTable tbody').html(html);
  }
  
  // Render pagination with smart page display (max 5 pages + ellipsis)
  function renderPagination(total, pageSize, currentPage) {
    const totalPages = Math.ceil(total / pageSize);
    let html = '';
    
    if (totalPages > 1) {
      html = '<div class="card-footer bg-light"><div class="row align-items-center"><div class="col-md-6"><span class="text-muted small">Showing ' + ((currentPage - 1) * pageSize + 1) + ' to ' + Math.min(currentPage * pageSize, total) + ' of ' + total + ' users</span></div><div class="col-md-6"><nav><ul class="pagination justify-content-end mb-0">';
      
      // Previous button
      if (currentPage > 1) {
        html += '<li class="page-item"><a class="page-link" href="#" onclick="loadUsers(' + (currentPage - 1) + ')">&laquo; Previous</a></li>';
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
        html += '<li class="page-item"><a class="page-link" href="#" onclick="loadUsers(1)">1</a></li>';
        if (startPage > 2) {
          html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
      }
      
      // Page numbers in range
      for (let i = startPage; i <= endPage; i++) {
        if (i === currentPage) {
          html += '<li class="page-item active"><span class="page-link">' + i + '</span></li>';
        } else {
          html += '<li class="page-item"><a class="page-link" href="#" onclick="loadUsers(' + i + ')">' + i + '</a></li>';
        }
      }
      
      // Last page (if not in range)
      if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
          html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        html += '<li class="page-item"><a class="page-link" href="#" onclick="loadUsers(' + totalPages + ')">' + totalPages + '</a></li>';
      }
      
      // Next button
      if (currentPage < totalPages) {
        html += '<li class="page-item"><a class="page-link" href="#" onclick="loadUsers(' + (currentPage + 1) + ')">Next &raquo;</a></li>';
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
  $('#group_id').change(function() {
    loadUsers(1);
  });
  
  // Handle page size changes
  $('#pageSize').change(function() {
    pageSize = parseInt($(this).val());
    loadUsers(1);
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
    $('#group_id').val('');
    $('#search').val('');
    $('#pageSize').val('25');
    pageSize = 25;
    loadUsers(1);
  });
  
  // Refresh CSRF token periodically to prevent expiration
  function refreshCSRFToken() {
    $.ajax({
      url: '<?php echo base_url("permissions/userpermissions/refreshCSRF"); ?>',
      type: 'GET',
      success: function(response) {
        if (response.csrf_token) {
          // Update all CSRF token fields
          $('input[name="<?php echo $this->security->get_csrf_token_name(); ?>"]').val(response.csrf_token);
        }
      }
    });
  }
  
  // Refresh CSRF token every 30 minutes
  setInterval(refreshCSRFToken, 30 * 60 * 1000);
  
  // Load initial data
  loadUsers(1);
});
</script>
