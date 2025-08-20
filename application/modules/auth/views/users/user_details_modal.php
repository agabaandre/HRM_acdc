<!-- Enhanced User Details Modal -->
<div class="modal fade" id="user<?php echo $user->user_id; ?>" tabindex="-1" aria-labelledby="userModalLabel<?php echo $user->user_id; ?>" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="userModalLabel<?php echo $user->user_id; ?>">
          <i class="fa fa-user-edit me-2"></i>Edit User: <?php echo htmlspecialchars($user->name); ?>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body">
        <div class="row">
          <!-- User Avatar Section -->
          <div class="col-md-3 text-center mb-3">
            <div class="user-avatar-section">
              <img src="<?= base_url('assets/images/pp.png') ?>" 
                   class="rounded-circle mb-3" width="80" height="80" 
                   alt="<?= htmlspecialchars($user->name) ?>">
              <h6 class="text-muted"><?= htmlspecialchars($user->name) ?></h6>
              <small class="text-muted">ID: <?= $user->user_id ?></small>
            </div>
          </div>
          
          <!-- User Details Form -->
          <div class="col-md-9">
            <form id="updateUserForm<?php echo $user->user_id; ?>" class="update_user" method="POST">
              <div class="row">
                <!-- Name Field -->
                <div class="col-md-6 mb-3">
                  <label class="form-label fw-bold">
                    <i class="fa fa-user me-1 text-primary"></i>Full Name
                  </label>
                  <input type="text" 
                         name="name" 
                         value="<?php echo htmlspecialchars($user->name); ?>" 
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
                  <select name="role" class="form-select role" required>
                    <option value="">Select User Group</option>
                    <?php foreach ($usergroups as $usergroup) : ?>
                      <option value="<?php echo $usergroup->id; ?>" 
                              <?php echo ($user->role == $usergroup->id) ? 'selected' : ''; ?>>
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
                         value="<?php echo htmlspecialchars($user->work_email); ?>" 
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
                             id="statusSwitch<?php echo $user->user_id; ?>"
                             <?php echo ($user->status == 1) ? 'checked' : ''; ?>>
                      <label class="form-check-label" for="statusSwitch<?php echo $user->user_id; ?>">
                        <?php echo ($user->status == 1) ? 'Active' : 'Inactive'; ?>
                      </label>
                    </div>
                    <span class="badge bg-<?php echo ($user->status == 1) ? 'success' : 'danger'; ?>">
                      <?php echo ($user->status == 1) ? 'Enabled' : 'Disabled'; ?>
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
                        <i class="fa fa-calendar me-1"></i>Created: <?= date('M d, Y', strtotime($user->created_at ?? 'now')) ?>
                      </small>
                    </div>
                    <div class="col-md-6">
                      <small class="text-muted">
                        <i class="fa fa-clock me-1"></i>Last Updated: <?= date('M d, Y', strtotime($user->updated_at ?? 'now')) ?>
                      </small>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Hidden Fields -->
              <input type="hidden" name="user_id" value="<?php echo $user->user_id; ?>">
              <input type="hidden" name="status" id="statusField<?php echo $user->user_id; ?>" value="<?php echo $user->status; ?>">
              <!-- CSRF Token -->
              <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
              
              <!-- Status Messages -->
              <div class="alert alert-info d-none" id="statusMessage<?php echo $user->user_id; ?>">
                <i class="fa fa-info-circle me-2"></i>
                <span id="messageText<?php echo $user->user_id; ?>"></span>
              </div>
            </form>
          </div>
        </div>
      </div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          <i class="fa fa-times me-1"></i>Cancel
        </button>
        <button type="submit" form="updateUserForm<?php echo $user->user_id; ?>" class="btn btn-primary">
          <i class="fa fa-save me-1"></i>Save Changes
        </button>
      </div>
    </div>
  </div>
</div>

<style>
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
</style>

<script>
$(document).ready(function() {
  // Status switch functionality
  $('#statusSwitch<?php echo $user->user_id; ?>').change(function() {
    var isChecked = $(this).is(':checked');
    var statusValue = isChecked ? 1 : 0;
    var statusText = isChecked ? 'Active' : 'Inactive';
    var statusClass = isChecked ? 'success' : 'danger';
    var statusLabel = isChecked ? 'Enabled' : 'Disabled';
    
    // Update hidden field
    $('#statusField<?php echo $user->user_id; ?>').val(statusValue);
    
    // Update label
    $(this).next('label').text(statusText);
    
    // Update badge
    var badge = $(this).closest('.d-flex').find('.badge');
    badge.removeClass('bg-success bg-danger').addClass('bg-' + statusClass).text(statusLabel);
    
    // Show status message
    var messageBox = $('#statusMessage<?php echo $user->user_id; ?>');
    var messageText = $('#messageText<?php echo $user->user_id; ?>');
    
    messageText.text('Status changed to: ' + statusText);
    messageBox.removeClass('d-none').addClass('show');
    
    // Hide message after 3 seconds
    setTimeout(function() {
      messageBox.removeClass('show').addClass('d-none');
    }, 3000);
  });
  
  // Form submission
  $('#updateUserForm<?php echo $user->user_id; ?>').submit(function(e) {
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
        var messageBox = $('#statusMessage<?php echo $user->user_id; ?>');
        var messageText = $('#messageText<?php echo $user->user_id; ?>');
        
        messageText.html('<i class="fa fa-check-circle me-2"></i>' + (response.message || 'User updated successfully'));
        messageBox.removeClass('d-none alert-info').addClass('show alert-success');
        
        // Reload page after 2 seconds
        setTimeout(function() {
          location.reload();
        }, 2000);
      },
      error: function(xhr, status, error) {
        // Show error message
        var messageBox = $('#statusMessage<?php echo $user->user_id; ?>');
        var messageText = $('#messageText<?php echo $user->user_id; ?>');
        
        messageText.html('<i class="fa fa-exclamation-triangle me-2"></i>Error updating user. Please try again.');
        messageBox.removeClass('d-none alert-info').addClass('show alert-danger');
        
        // Re-enable submit button
        submitBtn.prop('disabled', false).html(originalText);
      }
    });
  });
  
  // Form validation
  $('#updateUserForm<?php echo $user->user_id; ?> input[required], #updateUserForm<?php echo $user->user_id; ?> select[required]').on('blur', function() {
    var field = $(this);
    var value = field.val().trim();
    
    if (value === '') {
      field.addClass('is-invalid');
      if (!field.next('.invalid-feedback').length) {
        field.after('<div class="invalid-feedback">This field is required.</div>');
      }
    } else {
      field.removeClass('is-invalid');
      field.next('.invalid-feedback').remove();
    }
  });
});
</script>
