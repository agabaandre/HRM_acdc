              <!-- edit model -->
              <!-- edit employee data model -->
              <div class="modal fade" id="update_institution<?php echo $unit->unit_id; ?>" tabindex="-1" aria-labelledby="add_item_label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title" id="add_item_label">Edit Unit: <?php echo $unit->unit_name; ?></h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">

                      <?php echo validation_errors(); ?>
                      <?php echo form_open('settings/update_content'); ?>
                      <input type="hidden" name="table" value="units">
                      <input type="hidden" name="redirect" value="unit">
                      <input type="hidden" name="column_name" value="unit_id">
                      <input type="hidden" name="caller_value" value="<?php echo $unit->unit_id; ?>">
                      <div class="row">
                        <div class="col-md-12">
                          <!-- <h4>Update Duty Station</h4> -->
                          <div class="form-group">
                            <label for="fname">Unit Name:</label>
                            <input type="text" class="form-control" value="<?php echo $unit->unit_name;   ?>" name="unit_name" id="unit" required>
                          </div>
                          <div class="form-group">
                                <label for="division_id">Division:</label>
                                <select class="form-control select2" name="division_id" id="division_id" required>
                                    <?php $lists = Modules::run('lists/divisions');
                                    foreach ($lists as $list) :
                                    ?>
                                        <option value="<?php echo $list->division_id; ?>"<?php if ($list->division_id == $unit->division_id) {
                                                                              echo "selected";
                                                                            } ?>><?php echo $list->division_name; ?></option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                            </div>

                          <div class="form-group">
                                <label for="first_supervisor">Unit Head:</label>
                                <select class="form-control select2" name="staff_id" id="staff_id" required>
                                    <option value="">Select First Supervisor</option>
                                    <?php $lists = Modules::run('lists/supervisor');
                                    foreach ($lists as $list) :
                                    ?>
                                    
                                        <option value="<?php echo $list->staff_id; ?>" <?php if ($list->staff_id == $unit->staff_id) {
                                                                              echo "selected";
                                                                            } ?>><?php echo $list->lname . ' ' . $list->fname; ?></option>
                                    
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
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