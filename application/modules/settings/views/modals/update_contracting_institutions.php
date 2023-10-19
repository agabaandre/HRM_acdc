              <!-- edit model -->
              <!-- edit employee data model -->
              <div class="modal fade" id="update_institution<?php echo $institute->contracting_institution_id; ?>" tabindex="-1" aria-labelledby="add_item_label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title" id="add_item_label">Edit Contracting Institution: <?php echo $institute->contracting_institution; ?></h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">

                      <?php echo validation_errors(); ?>
                      <?php echo form_open('settings/update_content'); ?>
                      <input type="hidden" name="table" value="contracting_institutions">
                      <input type="hidden" name="redirect" value="institution">
                      <input type="hidden" name="column_name" value="contracting_institution_id">
                      <input type="hidden" name="caller_value" value="<?php echo $institute->contracting_institution_id; ?>">
                      <div class="row">
                        <div class="col-md-12">
                          <!-- <h4>Update Duty Station</h4> -->
                          <div class="form-group">
                            <label for="fname">Contacting Institutions:</label>
                            <input type="text" class="form-control" value="<?php echo $institute->contracting_institution;   ?>" name="contracting_institution" id="contracting_institution" required>
                          </div>
                        </div>

                      </div>

                      <div class="form-group" style="float:right;">
                        <br>
                        <label for="submit"></label>
                        <input type="submit" class="btn btn-dark" value="Submit">
                        <input type="reset" class="btn btn-danger" value="Reset">
                      </div>

                      <?php echo form_close(); ?>
                    </div>
                  </div>
                </div>


              </div>