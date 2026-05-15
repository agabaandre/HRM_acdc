<div class="modal fade" id="divisionDeleteModal" tabindex="-1" aria-labelledby="divisionDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="divisionDeleteModalLabel">
          <i class="fas fa-exclamation-triangle me-2"></i>Delete Division
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <div class="text-center mb-4 division-delete-loading">
          <div class="spinner-border text-danger" role="status"><span class="visually-hidden">Loading...</span></div>
        </div>

        <div class="division-delete-content d-none">
          <div class="text-center mb-4">
            <i class="fas fa-trash-alt text-danger" style="font-size: 3rem;"></i>
          </div>

          <div class="alert alert-warning" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Warning!</strong> This action cannot be undone.
          </div>

          <div class="text-center">
            <h6 class="mb-3">Are you sure you want to delete this division?</h6>
            <div class="card bg-light">
              <div class="card-body">
                <h5 class="card-title text-primary" id="divisionDeleteName"></h5>
                <p class="card-text d-none" id="divisionDeleteShortWrap">
                  <span class="badge bg-primary" id="divisionDeleteShort"></span>
                </p>
                <p class="card-text d-none" id="divisionDeleteCategoryWrap">
                  <small class="text-muted">Category: <span class="badge bg-info" id="divisionDeleteCategory"></span></small>
                </p>
              </div>
            </div>
          </div>

          <?= form_open('settings/delete_content', ['id' => 'divisionDeleteForm']); ?>
          <input type="hidden" name="table" value="divisions">
          <input type="hidden" name="redirect" value="divisions">
          <input type="hidden" name="column_name" value="division_id">
          <input type="hidden" name="caller_value" id="divisionDeleteCallerValue" value="">

          <div class="row mt-4">
            <div class="col-md-12">
              <div class="d-flex gap-2 justify-content-center">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                  <i class="fas fa-times me-1"></i> Cancel
                </button>
                <button type="submit" class="btn btn-danger">
                  <i class="fas fa-trash me-1"></i> Delete Division
                </button>
              </div>
            </div>
          </div>
          <?= form_close(); ?>
        </div>
      </div>
    </div>
  </div>
</div>
