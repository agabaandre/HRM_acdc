              <!-- edit model -->
              <!-- edit employee data model -->
              <div class="modal fade" id="update_divisions<?php echo $division->division_id; ?>" tabindex="-1" aria-labelledby="add_item_label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title" id="add_item_label">Edit Divisions: <?php echo $division->division_name; ?></h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">

                      <?php echo validation_errors(); ?>
                      <?php echo form_open('settings/update_content'); ?>
                      <input type="hidden" name="table" value="divisions">
                      <input type="hidden" name="redirect" value="div">
                      <input type="hidden" name="column_name" value="division_id">
                      <input type="hidden" name="caller_value" value="<?php echo $division->division_id; ?>">
                      <div class="row">
                        <div class="col-md-6">
                          <!-- <h4></h4> -->
                          <div class="form-group">
                            <label for="div">Disision Name:</label>
                            <input type="text" class="form-control" value="<?php echo $division->division_name; ?>" name="division_name" id="division_name" required>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <!-- <h4></h4> -->
                          <div class="form-group">
                            <label for="div">Disision head:</label>
                            <input type="text" class="form-control" value="<?php echo $division->division_head; ?>" name="division_head" id="division_head" required>
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