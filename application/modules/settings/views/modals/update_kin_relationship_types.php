<div class="modal fade" id="update_kin_relationship_types<?= (int) $row->kin_relationship_id ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit: <?= htmlspecialchars($row->relationship_name) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php echo form_open('settings/update_content'); ?>
        <input type="hidden" name="table" value="kin_relationship_types">
        <input type="hidden" name="redirect" value="kin_relationship_types">
        <input type="hidden" name="column_name" value="kin_relationship_id">
        <input type="hidden" name="caller_value" value="<?= (int) $row->kin_relationship_id ?>">
        <div class="mb-3">
          <label class="form-label">Relationship name</label>
          <input type="text" class="form-control" name="relationship_name" value="<?= htmlspecialchars($row->relationship_name) ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Sort order</label>
          <input type="number" class="form-control" name="sort_order" value="<?= (int) $row->sort_order ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Active</label>
          <select name="is_active" class="form-select">
            <option value="1" <?= !empty($row->is_active) ? 'selected' : '' ?>>Yes</option>
            <option value="0" <?= empty($row->is_active) ? 'selected' : '' ?>>No</option>
          </select>
        </div>
        <div class="text-end">
          <button type="submit" class="btn btn-dark">Save</button>
        </div>
        <?php echo form_close(); ?>
      </div>
    </div>
  </div>
</div>
