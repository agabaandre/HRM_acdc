              <!-- edit model -->
              <!-- edit employee data model -->
              <div class="modal fade" id="update_leave_types<?php echo $leave->leave_id; ?>" tabindex="-1" aria-labelledby="add_item_label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title" id="add_item_label">Edit Leave Type: <?php echo $leave->leave_name; ?></h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">

                      <?php echo validation_errors(); ?>
                      <?php echo form_open('settings/update_content'); ?>
                      <input type="hidden" name="table" value="leave_types">
                      <input type="hidden" name="redirect" value="leave">
                      <input type="hidden" name="column_name" value="leave_id">
                      <input type="hidden" name="caller_value" value="<?php echo $leave->leave_id; ?>">
                      <div class="row">
                        <div class="col-md-6">
                          <!-- <h4>Update Duty Station</h4> -->
                          <div class="form-group">
                            <label for="fname">Leave Name:</label>
                            <input type="text" class="form-control" value="<?php echo $leave->leave_name; ?>" name="leave_name" id="leave_name" required>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <!-- <h4>Update Duty Station</h4> -->
                          <div class="form-group">
                            <label for="fname">Leave Days:</label>
                            <input type="number" class="form-control" value="<?php echo $leave->leave_days;   ?>" name="leave_days" id="leave_days" required>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <!-- <h4>Update Duty Station</h4> -->
                          <div class="form-group">
                            <label for="fname">Is Accrued:</label>
                            <input type="number" class="form-control" value="<?php echo $leave->is_accrued;   ?>" name="is_accrued" id="is_accrued" required>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <!-- <h4>Update Duty Station</h4> -->
                          <div class="form-group">
                            <label for="fname">Accrual Rate:</label>
                            <input type="number" class="form-control" value="<?php echo $leave->accrual_rate;   ?>" name="accrual_rate" id="accrual_rate" required>
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