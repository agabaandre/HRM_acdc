<?php
$userPermissionIds = array();
foreach ($userPermissions as $up) {
    $userPermissionIds[] = $up->permission_id;
}
?>

<div class="container-fluid py-4">
  <!-- Header Section -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h2 class="h3 mb-0 fw-bold text-dark">
            <i class="fa fa-user-cog me-2" style="color: #119A48;"></i>User Permissions: <?php echo htmlspecialchars($user->name ?? 'Unknown User'); ?>
          </h2>
          <p class="text-muted mb-0">Manage individual permissions for this user</p>
        </div>
        <div class="d-flex gap-2">
          <a href="<?php echo base_url('permissions/userpermissions'); ?>" class="btn btn-outline-secondary">
            <i class="fa fa-arrow-left me-1"></i>Back to Users
          </a>
          <button class="btn text-white" data-bs-toggle="modal" data-bs-target="#copyGroupModal" style="background: #1aad5a; border-color: #1aad5a;">
            <i class="fa fa-copy me-1"></i>Copy Group Permissions
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- User Summary Cards -->
  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fa fa-user fa-3x" style="color: #119A48;"></i>
          </div>
          <h5 class="fw-bold text-dark mb-2"><?php echo htmlspecialchars($user->name ?? 'Unknown User'); ?></h5>
          <p class="text-muted mb-0">User</p>
        </div>
      </div>
    </div>
    
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fa fa-users fa-3x" style="color: #1aad5a;"></i>
          </div>
          <h5 class="fw-bold text-dark mb-2"><?php echo ucwords($user->group_name ?? 'No Group'); ?></h5>
          <p class="text-muted mb-0">Group</p>
        </div>
      </div>
    </div>
    
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fa fa-shield-alt fa-3x" style="color: #119A48;"></i>
          </div>
          <h5 class="fw-bold text-dark mb-2"><?php echo count($userPermissions); ?></h5>
          <p class="text-muted mb-0">Custom Permissions</p>
        </div>
      </div>
    </div>
    
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center p-4">
          <div class="mb-3">
            <i class="fa fa-check-circle fa-3x" style="color: #1aad5a;"></i>
          </div>
          <h5 class="fw-bold text-dark mb-2"><?php echo count($groupPermissions); ?></h5>
          <p class="text-muted mb-0">Group Permissions</p>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <!-- Custom Permissions Section -->
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm">
        <div class="card-header text-white py-3" style="background: #119A48;">
          <h5 class="mb-0 fw-bold">
            <i class="fa fa-user-shield me-2"></i>Custom User Permissions
          </h5>
        </div>
        <div class="card-body p-4">
          <?php echo form_open('permissions/userpermissions/assignPermissions', array('id' => 'permissionsForm')); ?>
            <input type="hidden" name="user_id" value="<?php echo $user->user_id; ?>">
            
            <!-- Permission Assignment Controls -->
            <div class="row mb-4">
              <div class="col-md-6">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="enableAssignment" name="assign" value="assign">
                  <label class="form-check-label fw-bold" for="enableAssignment">
                    <i class="fa fa-toggle-on me-2 text-success"></i>Enable Permission Assignment
                  </label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="checkAll">
                  <label class="form-check-label fw-bold" for="checkAll">
                    <i class="fa fa-check-double me-2 text-primary"></i>Select All Permissions
                  </label>
                </div>
              </div>
            </div>

            <!-- Permissions Grid -->
            <div class="permissions-grid">
              <div class="row">
                <?php 
                $permissionCategories = [];
                foreach ($allPermissions as $perm) {
                  $category = ucfirst($perm->module ?? 'General');
                  $permissionCategories[$category][] = $perm;
                }
                ?>
                
                <?php foreach ($permissionCategories as $category => $perms): ?>
                  <div class="col-md-6 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                      <div class="card-header py-2" style="background: #119A48; color: white;">
                        <h6 class="mb-0 fw-bold text-white">
                          <i class="fa fa-folder me-2"></i><?php echo $category; ?>
                        </h6>
                      </div>
                      <div class="card-body p-3">
                        <?php foreach ($perms as $perm): ?>
                          <div class="form-check mb-2">
                            <input class="form-check-input permission-checkbox" 
                                   type="checkbox" 
                                   name="permissions[]" 
                                   value="<?php echo $perm->id; ?>" 
                                   id="perm_<?php echo $perm->id; ?>"
                                   <?php if (in_array($perm->id, $userPermissionIds)) echo "checked"; ?>
                                   disabled>
                            <label class="form-check-label" for="perm_<?php echo $perm->id; ?>">
                              <span class="fw-medium"><?php echo ucwords(str_replace('_', ' ', $perm->name)); ?></span>
                              <br><small class="text-muted"><?php echo $perm->definition; ?></small>
                              <?php if (in_array($perm->id, $userPermissionIds)): ?>
                                <br><small class="text-success">
                                  <i class="fa fa-check-circle me-1"></i>Currently assigned
                                </small>
                              <?php endif; ?>
                            </label>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Submit Button -->
            <div class="row mt-4">
              <div class="col-12">
                <button type="submit" class="btn btn-lg w-100 text-white" id="savePermissionsBtn" disabled style="background: #119A48; border-color: #119A48;">
                  <i class="fa fa-save me-2"></i>Save User Permissions
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Group Permissions Reference Section -->
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm">
        <div class="card-header text-white py-3" style="background: #1aad5a;">
          <h5 class="mb-0 fw-bold">
            <i class="fa fa-users me-2"></i>Group Permissions (Reference)
          </h5>
        </div>
        <div class="card-body p-0">
          <?php if (count($groupPermissions) > 0): ?>
            <div class="list-group list-group-flush">
              <?php 
              $groupPermissionCategories = [];
              foreach ($groupPermissions as $perm) {
                $category = ucfirst($perm->module ?? 'General');
                $groupPermissionCategories[$category][] = $perm;
              }
              ?>
              
              <?php foreach ($groupPermissionCategories as $category => $perms): ?>
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
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="text-center py-4">
              <i class="fa fa-exclamation-triangle fa-2x text-warning mb-3"></i>
              <h6 class="text-warning">No Group Permissions</h6>
              <p class="text-muted small">This group has no permissions assigned.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
   
    </div>
  </div>
</div>

<!-- Copy Group Permissions Modal -->
<div id="copyGroupModal" class="modal fade" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header text-white" style="background: #1aad5a;">
        <h5 class="modal-title">
          <i class="fa fa-copy me-2"></i>Copy Group Permissions
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form action="<?php echo base_url('permissions/userpermissions/copyGroupPermissions'); ?>" method="POST">
        <div class="modal-body p-4">
          <input type="hidden" name="user_id" value="<?php echo $user->user_id; ?>">
          <div class="alert alert-info">
            <i class="fa fa-info-circle me-2"></i>
            <strong>This will:</strong>
            <ul class="mb-0 mt-2">
              <li>Remove all current custom permissions for this user</li>
              <li>Copy all permissions from the group: <strong><?php echo ucwords($user->group_name ?? 'No Group'); ?></strong></li>
              <li>Create individual permission records for this user</li>
            </ul>
          </div>
          <p class="text-muted">
            <strong>Note:</strong> After copying, you can still modify individual permissions as needed.
          </p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn text-white" style="background: #1aad5a; border-color: #1aad5a;">
            <i class="fa fa-copy me-2"></i>Copy Group Permissions
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Custom CSS -->
<style>
.permissions-grid .card {
  transition: all 0.3s ease;
}

.permissions-grid .card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(17, 154, 72, 0.15);
}

.form-check-input:disabled + .form-check-label {
  opacity: 0.6;
  cursor: not-allowed;
}

.form-check-input:checked {
  background-color: #119A48;
  border-color: #119A48;
}

.form-check-input:indeterminate {
  background-color: #0d7a3a;
  border-color: #0d7a3a;
}

.form-check-input:focus {
  border-color: #119A48;
  box-shadow: 0 0 0 0.2rem rgba(17, 154, 72, 0.25);
}

.list-group-item {
  border-left: none;
  border-right: none;
}

.list-group-item:first-child {
  border-top: none;
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
  // Enable/disable permission assignment
  $('#enableAssignment').change(function() {
    const isEnabled = $(this).is(':checked');
    $('.permission-checkbox').prop('disabled', !isEnabled);
    $('#savePermissionsBtn').prop('disabled', !isEnabled);
    
    if (isEnabled) {
      $('.permission-checkbox').prop('disabled', false);
      $('#savePermissionsBtn').prop('disabled', false);
    } else {
      $('.permission-checkbox').prop('disabled', true);
      $('#savePermissionsBtn').prop('disabled', true);
    }
  });

  // Select all permissions
  $('#checkAll').change(function() {
    const isChecked = $(this).is(':checked');
    $('.permission-checkbox:not(:disabled)').prop('checked', isChecked);
  });

  // Update select all checkbox state
  $('.permission-checkbox').change(function() {
    const totalCheckboxes = $('.permission-checkbox:not(:disabled)').length;
    const checkedCheckboxes = $('.permission-checkbox:not(:disabled):checked').length;
    
    if (checkedCheckboxes === 0) {
      $('#checkAll').prop('indeterminate', false).prop('checked', false);
    } else if (checkedCheckboxes === totalCheckboxes) {
      $('#checkAll').prop('indeterminate', false).prop('checked', true);
    } else {
      $('#checkAll').prop('indeterminate', true);
    }
  });

  // Form validation
  $('#permissionsForm').submit(function(e) {
    if (!$('#enableAssignment').is(':checked')) {
      e.preventDefault();
      alert('Please enable permission assignment before saving.');
      return false;
    }
    
    const selectedPermissions = $('.permission-checkbox:checked').length;
    if (selectedPermissions === 0) {
      if (!confirm('No permissions selected. This will remove all custom permissions for this user. Continue?')) {
        e.preventDefault();
        return false;
      }
    }
    
    return true;
  });
});

function clearAllPermissions() {
  if (confirm('Are you sure you want to clear all custom permissions for this user?')) {
    $('.permission-checkbox').prop('checked', false);
    $('#checkAll').prop('indeterminate', false).prop('checked', false);
  }
}

function showPermissionComparison() {
  // This function can be implemented to show a detailed comparison
  // between user permissions and group permissions
  alert('Permission comparison feature coming soon!');
}
</script>
