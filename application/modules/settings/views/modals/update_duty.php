              <!-- edit model -->
              <!-- edit employee data model -->
              <div class="modal fade" id="update_duty<?php echo $duty->duty_station_id; ?>" tabindex="-1" aria-labelledby="add_item_label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title" id="add_item_label">Edit Duty Station: <?php echo $duty->duty_station_name; ?></h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">

                      <?php echo validation_errors(); ?>
                      <?php echo form_open('settings/update_content'); ?>
                      <input type="hidden" name="table" value="duty_stations">
                      <input type="hidden" name="redirect" value="duty">
                      <input type="hidden" name="column_name" value="duty_station_id">
                      <input type="hidden" name="caller_value" value="<?php echo $duty->duty_station_id; ?>">
                      <div class="row">
                        <div class="col-md-6">
                          <!-- <h4>Update Duty Station</h4> -->
                          <div class="form-group">
                            <label for="fname">Duty Station Name:</label>
                            <input type="text" class="form-control" value="<?php echo $duty->duty_station_name;   ?>" name="duty_station_name" id="duty_station_name" required>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <!-- <h4>Update Duty Station</h4> -->
                          <div class="form-group">
                            <label for="fname">Type:</label>
                            <select type="text" name="country" autocomplete="off" placeholder="Country" class="form-control">
                                <!-- <option selected disabled value="<?php //echo $coutry->name?>"><?php //echo $coutry->name?> <span style="color: red;">| Selected Country</span></option> -->
                                <?php foreach($countries->result() as $coutry): ?>
                                    <option value="<?php echo $coutry->name?>"><?php echo $coutry->name?></option>
                                <?php endforeach; ?>
                            </select>
                          </div>
                        </div>
                        <div class="col-md-6">
                          <!-- <h4>Update Duty Station</h4> -->
                          <div class="form-group">
                            <label for="fname">Type:</label>
                            <select type="text" name="type" autocomplete="off" placeholder="Type" class="form-control" required>
                                <option disabled selected >Type</option>
                                <option value="MS">Member State</option>
                                <option value="Head Office">Head Office</option>
                                <option value="RCC">RCC</option>
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