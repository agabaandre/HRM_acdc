<?php
$groups = Modules::run('permissions/getUserGroups');
$permissions = Modules::run('permissions/getPermissions');
$gpermissions = Modules::run('permissions/groupPermissions', $this->session->flashdata('group'));
$selectedGroup = $this->session->flashdata('group');
?>
<div class="container-fluid py-4">
  <!-- Header Section -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h2 class="h3 mb-0 fw-bold text-dark">
            <i class="fa fa-shield-alt me-2" style="color: #119A48;"></i>Permissions Management
          </h2>
          <p class="text-muted mb-0">Manage user groups and their permissions</p>
        </div>
        <div class="d-flex gap-2">
          <a href="<?php echo base_url('permissions/userpermissions'); ?>" class="btn text-white" style="background: #1aad5a; border-color: #1aad5a;">
            <i class="fa fa-user-shield me-1"></i>User Permissions
          </a>
          <button class="btn text-white" data-bs-toggle="modal" data-bs-target="#newgrp" style="background: #119A48; border-color: #119A48;">
            <i class="fa fa-plus me-1"></i>Create Group
          </button>
          <button class="btn text-white" data-bs-toggle="modal" data-bs-target="#permsModal" style="background: #119A48; border-color: #119A48;">
            <i class="fa fa-key me-1"></i>Add Permission
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Flash Messages -->
  <?php if($this->session->flashdata("msg")): ?>
    <div class="alert alert-dismissible fade show" role="alert" style="background-color: rgba(17, 154, 72, 0.1); border-color: #119A48; color: #0d7a3a;">
      <i class="fa fa-check-circle me-2" style="color: #119A48;"></i>
      <?php echo $this->session->flashdata("msg"); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="row">
    <!-- Permission Assignment Section -->
    <div class="col-lg-8">
      <div class="card shadow-sm border-0">
        <div class="card-header text-white py-3" style="background: #1aad5a;">
          <h6 class="mb-0 fw-bold text-white">
            <i class="fa fa-cogs me-2" ></i>Permission Assignment
          </h6>
        </div>
        <div class="card-body p-4">
          <?php echo form_open_multipart(base_url('permissions/assignPermissions'), array('id' => 'permissions', 'class' => 'permissions')); ?>
          
          <!-- Group Selection -->
          <div class="row mb-4">
            <div class="col-md-8">
              <label for="changeugroup" class="form-label fw-bold">Select User Group</label>
              <select id="changeugroup" class="form-select form-select-lg" name="group" onchange="this.form.submit()">
                <option value="">-- Select a group to manage permissions --</option>
                <?php foreach ($groups as $group) : ?>
                  <option value="<?php echo $group->id; ?>" 
                          <?php if ($group->id == $selectedGroup) echo "selected"; ?>>
                    <?php echo ucwords($group->group_name); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <button type="submit" class="btn btn-lg w-100 text-white" id="savePermissionsBtn" disabled style="background: #119A48; border-color: #119A48;">
                <i class="fa fa-save me-2"></i>Save Permissions
              </button>
            </div>
          </div>

          <?php if($selectedGroup): ?>
            <!-- Assignment Controls -->
            <div class="row mb-4">
              <div class="col-md-6">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="enableAssignment" name="assign" value="assign">
                  <label class="form-check-label fw-bold" for="enableAssignment">
                    <i class="fa fa-toggle-on me-2" style="color: #119A48;"></i>Enable Permission Assignment
                  </label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="checkAll">
                  <label class="form-check-label fw-bold" for="checkAll">
                    <i class="fa fa-check-double me-2" style="color: #119A48;"></i>Select All Permissions
                    </label>
                </div>
              </div>
            </div>

            <!-- Permissions Grid -->
            <div class="permissions-grid">
              <div class="row">
                <?php 
                $permissionCategories = [];
                foreach ($permissions as $perm) {
                  $category = ucfirst(explode('_', $perm->name)[0] ?? 'General');
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
                                   <?php if (in_array($perm->id, $gpermissions)) echo "checked"; ?>
                                   disabled>
                            <label class="form-check-label" for="perm_<?php echo $perm->id; ?>">
                              <span class="fw-medium"><?php echo ucwords(str_replace('_', ' ', $perm->name)); ?></span>
                              <br><small class="text-muted"><?php echo $perm->definition; ?></small>
                            </label>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php else: ?>
            <!-- No Group Selected Message -->
            <div class="text-center py-5">
              <i class="fa fa-users fa-3x text-muted mb-3"></i>
              <h5 class="text-muted">No Group Selected</h5>
              <p class="text-muted">Please select a user group from the dropdown above to manage their permissions.</p>
            </div>
          <?php endif; ?>
          
          </form>
        </div>
      </div>
    </div>

    <!-- Groups Overview Section -->
    <div class="col-lg-4">
      <div class="card shadow-sm border-0">
        <div class="card-header text-white py-3" style="background: #1aad5a;">
          <h6 class="mb-0 fw-bold text-white ">
            <i class="fa fa-users me-2"></i>User Groups
          </h6>
        </div>
        <div class="card-body p-0">
          <div class="list-group list-group-flush">
            <?php foreach ($groups as $group) : ?>
              <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                                  <div>
                    <h6 class="mb-1 fw-bold"><?php echo ucwords($group->group_name); ?></h6>
                    <small class="text-muted">
                      <?php 
                      $group_perms = Modules::run('permissions/getGroupPerms', $group->id);
                      $user_count = Modules::run('permissions/getGroupUserCount', $group->id);
                      echo count($group_perms) . ' permission' . (count($group_perms) != 1 ? 's' : '') . ' • ' . $user_count . ' user' . ($user_count != 1 ? 's' : '');
                      ?>
                    </small>
                  </div>
                  <div class="d-flex gap-1">
                    <button type="button" class="btn btn-sm text-white" 
                            data-bs-toggle="modal" 
                            data-bs-target="#myModal<?php echo $group->id; ?>"
                            style="background: #119A48; border-color: #119A48;">
                      <i class="fa fa-eye me-1"></i>View
                    </button>
                    <a href="<?php echo base_url('permissions/groupDetails/' . $group->id); ?>" 
                       class="btn btn-sm text-white" 
                       style="background: #1aad5a; border-color: #1aad5a;">
                      <i class="fa fa-users me-1"></i>Details
                    </a>
                  </div>
              </div>
              
              <!-- Group Permissions Modal -->
              <div id="myModal<?php echo $group->id; ?>" class="modal fade" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                  <div class="modal-content">
                    <div class="modal-header text-white" style="background: #119A48;">
                      <h5 class="modal-title">
                        <i class="fa fa-shield-alt me-2"></i>Permissions for <?php echo ucwords($group->group_name); ?>
                      </h5>
                      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                      <?php if (count($group_perms) > 0): ?>
                        <div class="row">
                          <?php 
                          $categories = [];
                          foreach ($group_perms as $perm) {
                            $cat = ucfirst($perm->module ?? 'General');
                            $categories[$cat][] = $perm;
                          }
                          ?>
                          <?php foreach ($categories as $category => $perms): ?>
                            <div class="col-md-6 mb-3">
                              <div class="card border-0 bg-light">
                                <div class="card-header py-2" style="background: #119A48; color: white;">
                                  <h6 class="mb-0 fw-bold text-white">
                                    <i class="fa fa-folder me-2"></i><?php echo $category; ?>
                                  </h6>
                                </div>
                                <div class="card-body p-2">
                                  <ul class="list-unstyled mb-0">
                                    <?php foreach ($perms as $perm): ?>
                                      <li class="mb-1">
                                        <i class="fa fa-check-circle text-success me-2"></i>
                                        <span class="fw-medium"><?php echo ucwords(str_replace('_', ' ', $perm->name)); ?></span>
                                        <br><small class="text-muted ms-4"><?php echo $perm->definition; ?></small>
                                        <br><small class="text-muted text-info ms-4">
                                          <i class="fa fa-clock me-1"></i>Updated: <?php echo date('M d, Y', strtotime($perm->last_updated)); ?>
                                        </small>
                                      </li>
                                    <?php endforeach; ?>
                                  </ul>
                                </div>
                              </div>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      <?php else: ?>
                        <div class="text-center py-4">
                          <i class="fa fa-exclamation-triangle fa-2x text-warning mb-3"></i>
                          <h5 class="text-warning">No Permissions Assigned</h5>
                          <p class="text-muted">This group currently has no permissions assigned.</p>
                        </div>
                      <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Permission Modal -->
<?php $this->load->view('add_perm_modal'); ?>

<!-- Create Group Modal -->
<div id="newgrp" class="modal fade" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header text-white" style="background: #119A48;">
        <h5 class="modal-title">
          <i class="fa fa-plus me-2"></i>Create New User Group
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <?php echo form_open_multipart(base_url('permissions/addGroup'), array('id' => 'addGroup', 'class' => 'addGroup')); ?>
      <div class="modal-body p-4">
        <div class="mb-3">
          <label for="group_name" class="form-label fw-bold">Group Name</label>
          <input type="text" 
                 class="form-control form-control-lg" 
                 name="group_name" 
                 id="group_name"
                 placeholder="Enter group name (e.g., Administrators, Users, Managers)"
                 required>
          <div class="form-text">This will be used to identify the user group in the system.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn text-white" style="background: #119A48; border-color: #119A48;">
          <i class="fa fa-save me-2"></i>Create Group
        </button>
      </div>
      </form>
    </div>
  </div>
</div>

<!-- Enhanced JavaScript -->
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
  $('#permissions').submit(function(e) {
    if (!$('#enableAssignment').is(':checked')) {
      e.preventDefault();
      alert('Please enable permission assignment before saving.');
      return false;
    }
    
    const selectedPermissions = $('.permission-checkbox:checked').length;
    if (selectedPermissions === 0) {
      e.preventDefault();
      alert('Please select at least one permission before saving.');
      return false;
    }
    
    return true;
  });

  // Enhanced group creation form
  $('#addGroup').submit(function(e) {
    const groupName = $('#group_name').val().trim();
    if (groupName === '') {
      e.preventDefault();
      alert('Please enter a group name.');
      $('#group_name').focus();
      return false;
    }
    
    if (groupName.length < 3) {
      e.preventDefault();
      alert('Group name must be at least 3 characters long.');
      $('#group_name').focus();
      return false;
    }
    
    return true;
  });

  // Auto-submit when group is selected
  $('#changeugroup').change(function() {
    if ($(this).val()) {
      $(this).closest('form').submit();
    }
  });

  // Initialize tooltips
  $('[data-bs-toggle="tooltip"]').tooltip();
});
</script>

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

.list-group-item:hover {
  background-color: rgba(17, 154, 72, 0.05);
}

.modal-header {
  border-bottom: none;
}

.card-header {
  border-bottom: none;
}

.btn-close-white {
  filter: invert(1) grayscale(100%) brightness(200%);
}

/* Responsive adjustments */
@media (max-width: 768px) {
  .permissions-grid .col-md-6 {
    margin-bottom: 1rem;
  }
  
  .modal-dialog {
    margin: 1rem;
  }
}

/* AU Green Button Styles */
.btn[style*="background: #119A48"]:hover {
  background-color: #0f8a42 !important;
  border-color: #0f8a42 !important;
}

.btn[style*="background: #119A48"]:active {
  background-color: #0d7a3a !important;
  border-color: #0d7a3a !important;
}

/* Permission Category Headers */
.permissions-grid .card-header {
  background: linear-gradient(135deg, #119A48 0%, #1aad5a 100%) !important;
  color: white !important;
}

.permissions-grid .card-header h6 {
  color: white !important;
}

/* Enhanced Form Controls */
.form-select:focus {
  border-color: #119A48;
  box-shadow: 0 0 0 0.2rem rgba(17, 154, 72, 0.25);
}

.form-control:focus {
  border-color: #119A48;
  box-shadow: 0 0 0 0.2rem rgba(17, 154, 72, 0.25);
}

/* Switch Styling */
.form-check-input:focus {
  border-color: #119A48;
  box-shadow: 0 0 0 0.2rem rgba(17, 154, 72, 0.25);
}

/* Alert Styling */
.alert-success {
  background-color: rgba(17, 154, 72, 0.1);
  border-color: #119A48;
  color: #0d7a3a;
}

/* Icon Colors */
.text-primary {
  color: #119A48 !important;
}

/* Enhanced Card Shadows */
.card {
  border: 1px solid rgba(17, 154, 72, 0.1);
}

.card:hover {
  border-color: rgba(17, 154, 72, 0.3);
}

/* Permission Grid Enhancements */
.permissions-grid .card-header i {
  color: rgba(255, 255, 255, 0.9);
}

/* Button Focus States */
.btn:focus {
  box-shadow: 0 0 0 0.2rem rgba(17, 154, 72, 0.25);
}
</style>
<div id="newgrp" class="modal fade">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <?php echo form_open_multipart(base_url('permissions/addGroup'), array('id' => 'addGroup', 'class' => 'addGroup')); ?>
      <div class="modal-header">
        <h4 class="modal-title">Add group</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="padding-left: 3em;">
        <div class="mb-3">
          <input type="text" placeholder="Group Name" name="group_name" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Save</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
      </form>
    </div>
  </div>
</div>
<script>
  $("#checkAll").click(function() {
    $('input:checkbox').not(this).prop('checked', this.checked);
  });
</script>