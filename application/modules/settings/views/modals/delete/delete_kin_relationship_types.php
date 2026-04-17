<div class="modal fade" id="delete_kin_relationship_types<?= (int) $row->kin_relationship_id ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Delete relationship</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php echo form_open('settings/delete_content'); ?>
        <input type="hidden" name="table" value="kin_relationship_types">
        <input type="hidden" name="column_name" value="kin_relationship_id">
        <input type="hidden" name="caller_value" value="<?= (int) $row->kin_relationship_id ?>">
        <p class="text-center"><?= htmlspecialchars($row->relationship_name) ?></p>
        <div class="text-center">
          <button type="submit" class="btn btn-danger btn-sm">Delete</button>
          <button type="button" class="btn btn-dark btn-sm" data-bs-dismiss="modal">Cancel</button>
        </div>
        <?php echo form_close(); ?>
      </div>
    </div>
  </div>
</div>
