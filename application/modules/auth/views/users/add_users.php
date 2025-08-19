<?php
$usergroups = Modules::run("permissions/getUserGroups");
?>


<div class="row">
  <div class="col-md-12">
    <div class="card card-default">
      <div class="card-header text-white" style="background: rgba(52, 143, 65, 1);">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h4 class="card-title mb-0">
              <i class="fa fa-users me-2"></i>User Management
            </h4>
            <small class="text-white-50">Manage system users and their permissions</small>
          </div>
          <div>
            <a href="<?php echo base_url()?>auth/acdc_users" class="btn btn-light btn-sm" target="_blank">
              <i class="fa fa-user-plus me-1"></i>Bulk Create Users
            </a>
          </div>
        </div>
      </div>

      <div class="card-body">
        <!-- Search Form -->
        <div class="row mb-4">
          <div class="col-md-8">
            <?php 
              echo form_open('auth/users', array(
                'method' => 'get',
                'class' => 'form-inline',
                'style' => 'margin-top: 4px !important;'
              )); 
            ?>
            <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" 
                  value="<?= $this->security->get_csrf_hash(); ?>">

            <div class="input-group">
              <input type="text" name="search_key" class="form-control" 
                     placeholder="Search by name, email, or username..." 
                     value="<?= isset($_GET['search_key']) ? htmlspecialchars($_GET['search_key']) : '' ?>">
              <button class="btn btn-outline-primary" type="submit">
                <i class="fa fa-search me-1"></i>Search
              </button>
              <?php if(isset($_GET['search_key']) && !empty($_GET['search_key'])): ?>
                <a href="<?= base_url('auth/users') ?>" class="btn btn-outline-secondary">
                  <i class="fa fa-times me-1"></i>Clear
                </a>
              <?php endif; ?>
            </div>
            <?= form_close() ?>
          </div>
          <div class="col-md-4 text-end">
            <span class="badge bg-info fs-6">
              <i class="fa fa-users me-1"></i><?= count($users) ?> Users Found
            </span>
          </div>
        </div>

        <!-- Pagination Links -->
        <?php if(isset($links) && !empty($links)): ?>
          <div class="d-flex justify-content-center mb-3">
            <?php echo $links; ?>
          </div>
        <?php endif; ?>

        <!-- Users Table -->
        <div class="table-responsive">
          <table class="table table-hover table-striped" id="usersTable">
            <thead class="table-dark">
              <tr>
                <th style="width: 3%;">#</th>
                <th style="width: 22%;">User Information</th>
                <th style="width: 18%;">Contact Details</th>
                <th style="width: 13%;">Role & Status</th>
                <th style="width: 24%;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php $no = 1; foreach ($users as $user): ?>
                <tr class="user-row" data-user-id="<?= $user->user_id ?>">
                  <td class="text-center">
                    <span class="badge bg-secondary"><?= $no ?></span>
                  </td>
                  
                  <td>
                    <div class="d-flex align-items-center">
                      <div class="avatar-sm me-3">
                        <img src="<?= base_url('assets/images/pp.png') ?>" 
                             class="rounded-circle" width="40" height="40" 
                             alt="<?= htmlspecialchars($user->name) ?>">
                      </div>
                      <div>
                        <h6 class="mb-1 fw-bold"><?= htmlspecialchars($user->name) ?></h6>
                        <small class="text-muted">
                          <i class="fa fa-id-badge me-1"></i>User ID: <?= $user->user_id ?>
                        </small>
                        <?php if(isset($user->staff_id) && !empty($user->staff_id)): ?>
                          <br><small class="text-muted">
                            <i class="fa fa-user me-1"></i>Staff ID: <?= $user->staff_id ?>
                          </small>
                        <?php endif; ?>
                      </div>
                    </div>
                  </td>
                  
                  <td>
                    <div class="user-contact">
                      <div class="mb-1">
                        <i class="fa fa-envelope text-primary me-2"></i>
                        <span class="text-break"><?= htmlspecialchars($user->work_email) ?></span>
                      </div>
                      <?php if(isset($user->tel_1) && !empty($user->tel_1)): ?>
                        <div class="mb-1">
                          <i class="fa fa-phone text-success me-2"></i>
                          <span><?= htmlspecialchars($user->tel_1) ?></span>
                        </div>
                      <?php endif; ?>
                    </div>
                  </td>
                  
                  <td>
                    <div class="user-role-status">
                      <div class="mb-2">
                        <span class="badge bg-primary fs-6">
                          <i class="fa fa-user-tag me-1"></i><?= htmlspecialchars($user->group_name) ?>
                        </span>
                      </div>
                      <div>
                        <?php if ($user->status == 1): ?>
                          <span class="badge bg-success">
                            <i class="fa fa-check-circle me-1"></i>Active
                          </span>
                        <?php else: ?>
                          <span class="badge bg-danger">
                            <i class="fa fa-ban me-1"></i>Inactive
                          </span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </td>
                  
                  <td>
                    <div class="d-flex flex-column gap-1">
                      <!-- Row 1: Primary Actions -->
                      <div class="d-flex gap-1 mb-1">
                        <!-- Impersonate Button -->
                        <a href="<?php echo site_url('auth/impersonate/' . $user->user_id); ?>" 
                           class="btn btn-warning btn-sm flex-fill" 
                           data-bs-toggle="tooltip" 
                           title="Impersonate this user">
                          <i class="fa fa-user-secret me-1"></i>Impersonate
                        </a>
                        
                        <!-- Edit Button -->
                        <button type="button" 
                                class="btn btn-info btn-sm flex-fill" 
                                data-bs-toggle="modal" 
                                data-bs-target="#user<?php echo $user->user_id; ?>"
                                data-bs-tooltip="tooltip"
                                title="Edit user details">
                          <i class="fa fa-edit me-1"></i>Edit
                        </button>
                      </div>
                      
                      <!-- Row 2: Secondary Actions -->
                      <div class="d-flex gap-1">
                        <!-- Status Toggle -->
                        <?php if ($user->status == 1): ?>
                          <button type="button" 
                                  class="btn btn-outline-danger btn-sm flex-fill" 
                                  data-user-id="<?= $user->user_id ?>"
                                  data-user-name="<?= htmlspecialchars($user->name) ?>"
                                  data-bs-tooltip="tooltip"
                                  title="Block this user">
                            <i class="fa fa-ban me-1"></i>Block
                          </button>
                        <?php else: ?>
                          <button type="button" 
                                  class="btn btn-outline-success btn-sm flex-fill" 
                                  data-user-id="<?= $user->user_id ?>"
                                  data-user-name="<?= htmlspecialchars($user->name) ?>"
                                  data-bs-tooltip="tooltip"
                                  title="Activate this user">
                            <i class="fa fa-check me-1"></i>Activate
                          </button>
                        <?php endif; ?>
                        
                        <!-- Reset Password -->
                        <button type="button" 
                                class="btn btn-outline-warning btn-sm flex-fill" 
                                data-user-id="<?= $user->user_id ?>"
                                data-user-name="<?= htmlspecialchars($user->name) ?>"
                                data-bs-tooltip="tooltip"
                                title="Reset user password">
                          <i class="fa fa-key me-1"></i>Reset Password
                        </button>
                      </div>
                    </div>
                  </td>
                </tr>

                <!-- User Details Modal -->
                <?php include('user_details_modal.php'); ?>
                
              <?php $no++; endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Bottom Pagination -->
        <?php if(isset($links) && !empty($links)): ?>
          <div class="d-flex justify-content-center mt-4">
            <?php echo $links; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Block User Confirmation Modal -->
<div class="modal fade" id="blockUserModal" tabindex="-1" aria-labelledby="blockUserModalLabel" aria-hidden="true">
  <div class="modal-dialog">
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
  <div class="modal-dialog">
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
  <div class="modal-dialog">
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

<style>
.avatar-sm img {
  object-fit: cover;
  border: 2px solid #e9ecef;
}

.user-row:hover {
  background-color: #f8f9fa;
  transform: translateY(-1px);
  transition: all 0.2s ease;
}

.btn-group-vertical .btn {
  text-align: left;
  border-radius: 0.375rem !important;
}

.btn-group-vertical .btn:first-child {
  border-top-left-radius: 0.375rem !important;
  border-top-right-radius: 0.375rem !important;
}

.btn-group-vertical .btn:last-child {
  border-bottom-left-radius: 0.375rem !important;
  border-bottom-right-radius: 0.375rem !important;
}

/* Action buttons styling */
.btn-sm {
  font-size: 0.75rem;
  padding: 0.375rem 0.5rem;
  white-space: nowrap;
}

.flex-fill {
  flex: 1 1 auto;
}

/* Ensure buttons don't wrap text */
.btn i {
  margin-right: 0.25rem;
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

.form-inline .input-group {
  width: 100%;
}

@media (max-width: 768px) {
  .btn-group-vertical {
    flex-direction: row;
    flex-wrap: wrap;
  }
  
  .btn-group-vertical .btn {
    margin-right: 0.25rem;
    margin-bottom: 0.25rem;
  }
}
</style>

<script>
$(document).ready(function() {
  // Initialize tooltips
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-tooltip="tooltip"]'));
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });

  // Block User
  $('.block-user').click(function() {
    var userId = $(this).data('user-id');
    var userName = $(this).data('user-name');
    
    $('#blockUserName').text(userName);
    $('#confirmBlockUser').data('user-id', userId);
    $('#blockUserModal').modal('show');
  });

  $('#confirmBlockUser').click(function() {
    var userId = $(this).data('user-id');
    blockUser(userId);
  });

  // Unblock User
  $('.unblock-user').click(function() {
    var userId = $(this).data('user-id');
    var userName = $(this).data('user-name');
    
    $('#unblockUserName').text(userName);
    $('#confirmUnblockUser').data('user-id', userId);
    $('#unblockUserModal').modal('show');
  });

  $('#confirmUnblockUser').click(function() {
    var userId = $(this).data('user-id');
    unblockUser(userId);
  });

  // Reset Password
  $('.reset-password').click(function() {
    var userId = $(this).data('user-id');
    var userName = $(this).data('user-name');
    
    $('#resetPasswordUserName').text(userName);
    $('#confirmResetPassword').data('user-id', userId);
    $('#resetPasswordModal').modal('show');
  });

  $('#confirmResetPassword').click(function() {
    var userId = $(this).data('user-id');
    resetPassword(userId);
  });

  // AJAX Functions
  function blockUser(userId) {
    $.ajax({
      url: '<?= base_url("auth/blockUser") ?>',
      method: 'POST',
      data: { user_id: userId },
      dataType: 'json',
      success: function(response) {
        showNotification(response.message || 'User blocked successfully', 'success');
        setTimeout(function() {
          location.reload();
        }, 1500);
      },
      error: function() {
        showNotification('Error blocking user', 'error');
      }
    });
  }

  function unblockUser(userId) {
    $.ajax({
      url: '<?= base_url("auth/unblockUser") ?>',
      method: 'POST',
      data: { user_id: userId },
      dataType: 'json',
      success: function(response) {
        showNotification(response.message || 'User activated successfully', 'success');
        setTimeout(function() {
          location.reload();
        }, 1500);
      },
      error: function() {
        showNotification('Error activating user', 'error');
      }
    });
  }

  function resetPassword(userId) {
    $.ajax({
      url: '<?= base_url("auth/resetPass") ?>',
      method: 'POST',
      data: { 
        user_id: userId,
        password: '<?= setting()->default_password ?? "password123" ?>'
      },
      dataType: 'json',
      success: function(response) {
        showNotification(response.message || 'Password reset successfully', 'success');
        setTimeout(function() {
          location.reload();
        }, 1500);
      },
      error: function() {
        showNotification('Error resetting password', 'error');
      }
    });
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
});
</script>


