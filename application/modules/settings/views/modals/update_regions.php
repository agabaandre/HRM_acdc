              <!-- edit model -->
              <!-- edit employee data model -->
              <div class="modal fade" id="update_regions<?php echo $region->id; ?>" tabindex="-1" aria-labelledby="add_item_label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title" id="add_item_label">Edit Region: <?php echo $region->region_name; ?></h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">

                      <?php echo validation_errors(); ?>
                      <?php echo form_open('settings/update_content'); ?>
                      <input type="hidden" name="table" value="regions">
                      <input type="hidden" name="redirect" value="region">
                      <input type="hidden" name="column_name" value="id">
                      <input type="hidden" name="caller_value" value="<?php echo $region->id; ?>">
                      <div class="row">
                        <div class="col-md-12">
                          <!-- <h4>Update Duty Station</h4> -->
                          <div class="form-group">
                            <label for="fname">Region Name:</label>
                            <input type="text" class="form-control" value="<?php echo $region->region_name; ?>" name="region_name" id="region_name" required>
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