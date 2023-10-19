              <!-- edit model -->
              <!-- edit employee data model -->
              <div class="modal fade" id="update_contruct_types<?php echo $contract->contract_type_id; ?>" tabindex="-1" aria-labelledby="add_item_label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title" id="add_item_label">Edit Contract Types: <?php echo $contract->contract_type; ?></h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">

                      <?php echo validation_errors(); ?>
                      <?php echo form_open('settings/update_content'); ?>
                      <input type="hidden" name="table" value="contract_types">
                      <input type="hidden" name="redirect" value="c_type">
                      <input type="hidden" name="column_name" value="contract_type_id">
                      <input type="hidden" name="caller_value" value="<?php echo $contract->contract_type_id; ?>">
                      <div class="row">
                        <div class="col-md-12">
                          <!-- <h4></h4> -->
                          <div class="form-group">
                            <label for="fname">Contact Type:</label>
                            <input type="text" class="form-control" value="<?php echo $contract->contract_type; ?>" name="contract_type" id="contract_type" required>
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