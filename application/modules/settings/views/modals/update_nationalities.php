<div class="modal fade" id="update_nationality<?= $row->nationality_id; ?>" tabindex="-1" aria-labelledby="updateNationalityLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="updateNationalityLabel">Edit Nationality: <?= $row->nationality; ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <?= validation_errors(); ?>
        <?= form_open('settings/update_content'); ?>
        <input type="hidden" name="table" value="nationalities">
        <input type="hidden" name="redirect" value="nationalities">
        <input type="hidden" name="column_name" value="nationality_id">
        <input type="hidden" name="caller_value" value="<?= $row->nationality_id; ?>">

        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label>Country Name</label>
              <input type="text" class="form-control" name="nationality" value="<?= $row->nationality ?>" required>
            </div>
          </div>

          <div class="col-md-4">
            <div class="form-group">
              <label>Nationality Name</label>
              <input type="text" class="form-control" name="nationality_name" value="<?= $row->nationality_name ?>" required>
            </div>
          </div>

          <div class="col-md-4">
            <div class="form-group">
              <label>Continent</label>
              <input type="text" class="form-control" name="continent" value="<?= $row->continent ?>" required>
            </div>
          </div>

          <div class="col-md-3">
            <div class="form-group">
              <label>ISO2 Code</label>
              <input type="text" class="form-control" name="iso2" maxlength="2" value="<?= $row->iso2 ?>" required>
            </div>
          </div>

          <div class="col-md-3">
            <div class="form-group">
              <label>ISO3 Code</label>
              <input type="text" class="form-control" name="iso3" maxlength="3" value="<?= $row->iso3 ?>" required>
            </div>
          </div>

          <div class="col-md-6">
            <div class="form-group">
              <label>AU Region</label>
              <select name="region_id" class="form-control select2" required>
                <option value="">Select Region</option>
                <option value="1" <?= $row->region_id == 1 ? 'selected' : '' ?>>AU_Central</option>
                <option value="2" <?= $row->region_id == 2 ? 'selected' : '' ?>>AU_Eastern</option>
                <option value="3" <?= $row->region_id == 3 ? 'selected' : '' ?>>AU_Northern</option>
                <option value="4" <?= $row->region_id == 4 ? 'selected' : '' ?>>AU_Southern</option>
                <option value="5" <?= $row->region_id == 5 ? 'selected' : '' ?>>AU_Western</option>
                <option value="0" <?= $row->region_id == 0 ? 'selected' : '' ?>>Rest of </option>
              </select>
            </div>
          </div>
        </div>

        <div class="form-group text-end mt-4">
          <input type="submit" class="btn btn-dark" value="Update">
          <input type="reset" class="btn btn-danger" value="Reset">
        </div>

        <?= form_close(); ?>
      </div>
    </div>
  </div>
</div>
