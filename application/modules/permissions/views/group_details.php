<?php
//dd($data);
$group = $data['group'];
$users = $data['users'];
$userCount = $data['userCount'];
$permissions = $data['permissions'];
?>

<div class="container-fluid py-4">
  <!-- Header Section -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h2 class="h3 mb-0 fw-bold text-dark">
            <i class="fa fa-users me-2" style="color: #119A48;"></i>Group Details: <?php echo ucwords($group->group_name); ?>
          </h2>
          <p class="text-muted mb-0">View group information, permissions, and members</p>
        </div>
        <div class="d-flex gap-2">
          <a href="<?php echo base_url('permissions'); ?>" class="btn btn-outline-secondary">
            <i class="fa fa-arrow-left me-1"></i>Back to Groups
          </a>
          <button class="btn text-white" data-bs-toggle="modal" data-bs-target="#editGroupModal" style="background: #119A48; border-color: #119A48;">
            <i class="fa fa-edit me-1"></i>Edit Group
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Group Summary Cards -->
  <div class="row mb-4">
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fa fa-users fa-3x" style="color: #119A48;"></i>
          </div>
          <h3 class="fw-bold text-dark mb-2"><?php echo $userCount; ?></h3>
          <p class="text-muted mb-0">Total Members</p>
        </div>
      </div>
    </div>
    
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fa fa-shield-alt fa-3x" style="color: #1aad5a;"></i>
          </div>
          <h3 class="fw-bold text-dark mb-2"><?php echo count($permissions); ?></h3>
          <p class="text-muted mb-0">Total Permissions</p>
        </div>
      </div>
    </div>
    
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fa fa-calendar fa-3x" style="color: #119A48;"></i>
          </div>
          <h3 class="fw-bold text-dark mb-2"><?php echo date('M Y'); ?></h3>
          <p class="text-muted mb-0">Last Updated</p>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <!-- Group Members Section -->
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm">
        <div class="card-header text-white py-3" style="background: #119A48;">
          <h5 class="mb-0 fw-bold">
            <i class="fa fa-users me-2"></i>Group Members (<?php echo $userCount; ?>)
          </h5>
        </div>
        <div class="card-body p-0">
          <?php if ($userCount > 0): ?>
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="bg-light">
                  <tr>
                    <th class="border-0 py-3 px-4">User</th>
                    <th class="border-0 py-3">Role & Info</th>
                    <th class="border-0 py-3">Status</th>
                    <th class="border-0 py-3">Created</th>
                    <th class="border-0 py-3 px-4">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($users as $user): ?>
                    <tr>
                      <td class="px-4">
                        <div class="d-flex align-items-center">
                          <div class="avatar-sm me-3">
                            <?php if (!empty($user->photo)): ?>
                              <img src="<?php echo base_url('uploads/photos/' . $user->photo); ?>" 
                                   class="rounded-circle" width="40" height="40" 
                                   alt="<?php echo htmlspecialchars($user->name); ?>">
                            <?php else: ?>
                              <img src="<?php echo base_url('assets/images/pp.png'); ?>" 
                                   class="rounded-circle" width="40" height="40" 
                                   alt="<?php echo htmlspecialchars($user->name); ?>">
                            <?php endif; ?>
                          </div>
                          <div>
                            <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($user->name); ?></h6>
                            <small class="text-muted">User ID: <?php echo $user->user_id; ?></small>
                            <?php if (!empty($user->auth_staff_id)): ?>
                              <br><small class="text-muted">Staff ID: <?php echo $user->auth_staff_id; ?></small>
                            <?php endif; ?>
                          </div>
                        </div>
                      </td>
                      <td>
                        <span class="text-break">
                          <strong>Role:</strong> <?php echo htmlspecialchars($user->group_name); ?>
                          <br><small class="text-muted">Created: <?php echo date('M d, Y', strtotime($user->created_at)); ?></small>
                        </span>
                      </td>
                      <td>
                        <?php if ($user->status == 1): ?>
                          <span class="badge bg-success">
                            <i class="fa fa-check-circle me-1"></i>Active
                          </span>
                        <?php else: ?>
                          <span class="badge bg-danger">
                            <i class="fa fa-ban me-1"></i>Inactive
                          </span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <small class="text-muted">
                          <?php echo date('M d, Y', strtotime($user->created_at)); ?>
                        </small>
                      </td>
                      <td class="px-4">
                        <div class="btn-group btn-group-sm">
                          <a href="<?php echo base_url('auth/users'); ?>" 
                             class="btn btn-outline-primary btn-sm">
                            <i class="fa fa-eye me-1"></i>View
                          </a>
                          <button type="button" 
                                  class="btn btn-outline-info btn-sm"
                                  data-bs-toggle="tooltip"
                                  title="View user details">
                            <i class="fa fa-user me-1"></i>Profile
                          </button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="text-center py-5">
              <i class="fa fa-users fa-3x text-muted mb-3"></i>
              <h5 class="text-muted">No Members</h5>
              <p class="text-muted">This group currently has no members assigned.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Group Permissions Section -->
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm">
        <div class="card-header text-white py-3" style="background: #1aad5a;">
          <h5 class="mb-0 fw-bold">
            <i class="fa fa-shield-alt me-2"></i>Group Permissions (<?php echo count($permissions); ?>)
          </h5>
        </div>
        <div class="card-body p-0">
          <?php if (count($permissions) > 0): ?>
            <div class="list-group list-group-flush">
                             <?php 
               $permissionCategories = [];
               foreach ($permissions as $perm) {
                 $category = ucfirst($perm->module ?? 'General');
                 $permissionCategories[$category][] = $perm;
               }
               ?>
               
               <?php foreach ($permissionCategories as $category => $perms): ?>
                 <div class="list-group-item border-0">
                   <h6 class="fw-bold text-primary mb-2">
                     <i class="fa fa-folder me-2"></i><?php echo $category; ?>
                   </h6>
                   <?php foreach ($perms as $perm): ?>
                     <div class="d-flex align-items-center mb-2">
                       <i class="fa fa-check-circle text-success me-2"></i>
                       <div>
                         <span class="fw-medium"><?php echo ucwords(str_replace('_', ' ', $perm->name)); ?></span>
                         <br><small class="text-muted"><?php echo $perm->definition; ?></small>
                         <br><small class="text-muted text-info">
                           <i class="fa fa-clock me-1"></i>Updated: <?php echo date('M d, Y', strtotime($perm->last_updated)); ?>
                         </small>
                       </div>
                     </div>
                   <?php endforeach; ?>
                 </div>
               <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="text-center py-4">
              <i class="fa fa-exclamation-triangle fa-2x text-warning mb-3"></i>
              <h6 class="text-warning">No Permissions</h6>
              <p class="text-muted small">This group has no permissions assigned.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
      
      <!-- Quick Actions -->
      <div class="card border-0 shadow-sm mt-4">
        <div class="card-header text-white py-3" style="background: #119A48;">
          <h6 class="mb-0 fw-bold">
            <i class="fa fa-cogs me-2"></i>Quick Actions
          </h6>
        </div>
        <div class="card-body">
          <div class="d-grid gap-2">
            <a href="<?php echo base_url('permissions'); ?>" class="btn btn-outline-primary">
              <i class="fa fa-edit me-2"></i>Edit Permissions
            </a>
            <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
              <i class="fa fa-user-plus me-2"></i>Add Member
            </button>
            <button class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#groupInfoModal">
              <i class="fa fa-info-circle me-2"></i>Group Info
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Edit Group Modal -->
<div id="editGroupModal" class="modal fade" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header text-white" style="background: #119A48;">
        <h5 class="modal-title">
          <i class="fa fa-edit me-2"></i>Edit Group: <?php echo ucwords($group->group_name); ?>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form action="<?php echo base_url('permissions/updateGroup'); ?>" method="POST">
        <div class="modal-body p-4">
          <input type="hidden" name="group_id" value="<?php echo $group->id; ?>">
          <div class="mb-3">
            <label for="edit_group_name" class="form-label fw-bold">Group Name</label>
            <input type="text" 
                   class="form-control form-control-lg" 
                   name="group_name" 
                   id="edit_group_name"
                   value="<?php echo htmlspecialchars($group->group_name); ?>"
                   required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn text-white" style="background: #119A48; border-color: #119A48;">
            <i class="fa fa-save me-2"></i>Update Group
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal fade" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header text-white" style="background: #1aad5a;">
        <h5 class="modal-title">
          <i class="fa fa-user-plus me-2"></i>Add Member to Group
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form action="<?php echo base_url('permissions/addUserToGroup'); ?>" method="POST">
        <div class="modal-body p-4">
          <input type="hidden" name="group_id" value="<?php echo $group->id; ?>">
          <div class="mb-3">
            <label for="user_id" class="form-label fw-bold">Select User</label>
            <select class="form-select form-select-lg" name="user_id" id="user_id" required>
              <option value="">-- Select a user to add --</option>
            </select>
            <div class="form-text">Choose a user to add to this permission group.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn text-white" style="background: #1aad5a; border-color: #1aad5a;">
            <i class="fa fa-plus me-2"></i>Add User
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Group Info Modal -->
<div id="groupInfoModal" class="modal fade" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header text-white" style="background: #119A48;">
        <h5 class="modal-title">
          <i class="fa fa-info-circle me-2"></i>Group Information
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <div class="row">
          <div class="col-md-6">
            <h6 class="fw-bold text-primary">Group Details</h6>
            <p><strong>Name:</strong> <?php echo ucwords($group->group_name); ?></p>
            <p><strong>ID:</strong> <?php echo $group->id; ?></p>
            <p><strong>Members:</strong> <?php echo $userCount; ?> users</p>
            <p><strong>Permissions:</strong> <?php echo count($permissions); ?> total</p>
          </div>
          <div class="col-md-6">
            <h6 class="fw-bold text-primary">Created</h6>
            <p><strong>Date:</strong> <?php echo isset($group->created_at) ? date('M d, Y', strtotime($group->created_at)) : 'Unknown'; ?></p>
            <p><strong>Status:</strong> <span class="badge bg-success">Active</span></p>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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

.card-header {
  border-bottom: none;
}

.list-group-item {
  border-left: none;
  border-right: none;
}

.list-group-item:first-child {
  border-top: none;
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
</style>

<script>
$(document).ready(function() {
  // Initialize tooltips
  $('[data-bs-toggle="tooltip"]').tooltip();
  
  // Form validation
  $('#editGroupModal form').submit(function(e) {
    const groupName = $('#edit_group_name').val().trim();
    if (groupName === '') {
      e.preventDefault();
      alert('Please enter a group name.');
      $('#edit_group_name').focus();
      return false;
    }
    
    if (groupName.length < 3) {
      e.preventDefault();
      alert('Group name must be at least 3 characters long.');
      $('#edit_group_name').focus();
      return false;
    }
    
    return true;
  });
  
  // Populate available users when add user modal opens
  $('#addUserModal').on('show.bs.modal', function() {
    const groupId = <?php echo $group->id; ?>;
    const selectElement = $('#user_id');
    
    // Clear existing options
    selectElement.find('option:not(:first)').remove();
    
    // Fetch available users
    $.ajax({
      url: '<?php echo base_url("permissions/getAvailableUsersAjax"); ?>',
      type: 'GET',
      data: { group_id: groupId },
      dataType: 'json',
      success: function(users) {
        if (users.length > 0) {
          users.forEach(function(user) {
            selectElement.append(
              $('<option></option>')
                .val(user.id)
                .text(user.name + ' (' + user.email + ')')
            );
          });
        } else {
          selectElement.append(
            $('<option></option>')
              .val('')
              .text('No available users to add')
              .prop('disabled', true)
          );
        }
      },
      error: function() {
        selectElement.append(
          $('<option></option>')
            .val('')
            .text('Error loading users')
            .prop('disabled', true)
        );
      }
    });
  });
  
  // Form validation for add user
  $('#addUserModal form').submit(function(e) {
    const userId = $('#user_id').val();
    if (!userId) {
      e.preventDefault();
      alert('Please select a user to add.');
      $('#user_id').focus();
      return false;
    }
    
    return true;
  });
});
</script>
