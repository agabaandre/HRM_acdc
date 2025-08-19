<div id="permsModal" class="modal fade" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header text-white" style="background: #119A48;">
        <h5 class="modal-title">
          <i class="fa fa-key me-2"></i>Add New Permission
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <?php echo form_open_multipart(base_url('permissions/savePermissions'), array('id' => 'savepermissions', 'class' => 'savepermissions')); ?>
       
          <div class="mb-3">
            <label for="definition" class="form-label fw-bold">Permission Description</label>
            <input type="text" 
                   name="definition" 
                   class="form-control form-control-lg" 
                   id="definition"
                   placeholder="Enter a clear description of what this permission allows"
                   required>
            <div class="form-text">This should clearly describe what the permission enables users to do.</div>
          </div>
          
          <div class="mb-3">
            <label for="name" class="form-label fw-bold">Permission Name</label>
            <input type="text" 
                   name="name" 
                   class="form-control form-control-lg" 
                   id="name"
                   placeholder="e.g., user_create, report_view, admin_delete"
                   pattern="[a-zA-Z_]+"
                   title="Only letters and underscores allowed, no spaces"
                   required>
            <div class="form-text">
              <i class="fa fa-info-circle me-1"></i>
              Use lowercase letters and underscores only. No spaces or special characters allowed.
              <br><strong>Examples:</strong> user_create, report_view, admin_delete
            </div>
          </div>
          
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn text-white" style="background: #119A48; border-color: #119A48;">
              <i class="fa fa-save me-2"></i>Create Permission
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
  // Enhanced permission form validation
  $('#savepermissions').submit(function(e) {
    const definition = $('#definition').val().trim();
    const name = $('#name').val().trim();
    
    if (definition === '') {
      e.preventDefault();
      alert('Please enter a permission description.');
      $('#definition').focus();
      return false;
    }
    
    if (name === '') {
      e.preventDefault();
      alert('Please enter a permission name.');
      $('#name').focus();
      return false;
    }
    
    // Validate permission name format
    const namePattern = /^[a-zA-Z_]+$/;
    if (!namePattern.test(name)) {
      e.preventDefault();
      alert('Permission name can only contain letters and underscores. No spaces or special characters allowed.');
      $('#name').focus();
      return false;
    }
    
    if (name.length < 3) {
      e.preventDefault();
      alert('Permission name must be at least 3 characters long.');
      $('#name').focus();
      return false;
    }
    
    return true;
  });
  
  // Auto-format permission name
  $('#name').on('input', function() {
    let value = $(this).val();
    // Remove spaces and special characters, convert to lowercase
    value = value.replace(/[^a-zA-Z_]/g, '').toLowerCase();
    $(this).val(value);
  });
});
</script>