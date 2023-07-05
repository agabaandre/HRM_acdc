<div id="permsModal" class="modal fade">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title text-center">Add permission</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php echo form_open_multipart(base_url('permissions/savePermissions'), array('id' => 'savepermissions', 'class' => 'savepermissions')); ?>
       
          <div class="mb-3">
            <label for="definition" class="form-label">Definition</label>
            <input type="text" name="definition" class="form-control" title="Permission Description">
          </div>
          <div class="mb-3">
            <label for="name" class="form-label">Permission</label>
            <input type="text" name="name" class="form-control" title="Note: No Spaces Allowed!">
            <small class="form-text text-muted">No spaces allowed</small>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-info">Save</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>