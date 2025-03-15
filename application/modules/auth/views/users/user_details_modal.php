<!-- Default modal Size -->
<div class="modal fade" id="user<?php echo $user->user_id; ?>" tabindex="-1" aria-labelledby="defaultModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="defaultModalLabel">Update <?php echo $user->name; ?></h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <span class="status d-block text-center mb-3"></span>
        <?php echo form_open_multipart(base_url('auth/updateUser'), array('id' => 'update_user', 'class' => 'update_user')); ?>
          <div class="mb-3">
            <label class="form-label"><strong>Name</strong></label>
            <input type="text" name="name" value="<?php echo $user->name; ?>" class="form-control" required>
          </div>
      
          <div class="mb-3">
            <label class="form-label"><strong>User Group</strong></label>
            <select name="role" class="form-control role select2" required>
              <?php foreach ($usergroups as $usergroup) : ?>
                <option value="<?php echo $usergroup->id; ?>" <?php echo ($user->role == $usergroup->id) ? 'selected' : ''; ?>>
                  <?php echo $usergroup->group_name; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <input type="hidden" name="user_id" value="<?php echo $user->user_id; ?>">
          <div class="text-end">
            <button type="submit" class="btn btn-info">Save Changes</button>
          </div>
        <?php echo form_close(); ?>
      </div>
      <div class="modal-footer">
        <!-- Optionally add footer content -->
      </div>
    </div>
  </div>
</div>
