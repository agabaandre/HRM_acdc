              <!-- edit model -->
              <!-- edit employee data model -->
              <div class="modal fade" id="delete_regions<?php echo $region->id; ?>" tabindex="-1" aria-labelledby="add_item_label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-sm">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title" id="add_item_label">Delete Region</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">

                      <?php echo validation_errors(); ?>
                      <?php echo form_open('settings/delete_content'); ?>
                      <input type="hidden" name="table" value="regions">
                      <input type="hidden" name="redirect" value="region">
                      <input type="hidden" name="column_name" value="id">
                      <input type="hidden" name="caller_value" value="<?php echo $region->id; ?>">
                      <div class="row">

                        <div class="col-md-12">
                          <!-- <h4>Update Duty Station</h4> -->
                          <center>
                            <p><?php echo $region->region_name; ?></p>
                          </center>
                        </div>

                      </div>

                      <center>
                        <div class="form-group" style="float:center;">
                            <br>
                            <label for="submit"></label>
                            <button type="submit" class="btn btn-danger btn-sm" value="Submit">Delete</button>
                            <button type="reset" class="btn btn-dark btn-sm" data-bs-dismiss="modal">Close</button>
                        </div>
                      </center>


                      <?php echo form_close(); ?>
                    </div>
                  </div>
                </div>


              </div>