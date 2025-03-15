<h6 class="mb-0 text-uppercase"></h6>
<hr />
<div class="card">
    <div class="card-body">
        <br />


                <?php echo validation_errors(); ?>
                        <?php echo form_open('staff/add_new_contract');
                       // dd($staffs);
                         $staffs = $staffs[0];
                        ?>
                    <div class="row">

                        <div class="col-md-6">
                            <h4>Assign New Contract</h4>

                            <div class="form-group">
                                <label for="job_id">Job:<?php echo asterik()?></label>
                                <select class="form-control select2" name="job_id" id="job_id" required>
                                    <option value="">Select Job</option>
                                    <?php $jobs = Modules::run('lists/jobs');
                                    foreach ($jobs as $job) :
                                    ?>

                                        <option value="<?php echo $job->job_id; ?>" <?php if ($job->job_id == $staffs->job_id) {
                                                                              echo "selected";} ?>><?php echo $job->job_name; ?></option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                            </div>
                            <input type="hidden" name="staff_id" value="<?php echo $staff_id; ?>">
                                  <!-- Old staff contract-->
                            <input type="hidden" name="staff_contract_id" value="<?php echo $staffs->staff_contract_id; ?>">

                            <div class="form-group">
                                <label for="job_acting_id">Job Acting:</label>
                                <select class="form-control select2" name="job_acting_id" id="job_acting_id">
                                    <option value="">Select Job Acting</option>
                                    <?php $jobsacting = Modules::run('lists/jobsacting');
                                    foreach ($jobsacting as $joba) :
                                    ?>

                                        <option value="<?php echo $joba->job_acting_id; ?>" <?php if ($joba->job_acting_id == $staffs->job_acting_id) {
                                                                              echo "selected";
                                                                            } ?>><?php echo $joba->job_acting; ?></option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="grade_id">Grade:<?php echo asterik()?></label>
                                <select class="form-control select2" name="grade_id" id="grade_id" required>
                                    <option value="">Select Grade</option>
                                    <?php $lists = Modules::run('lists/grades');
                                    foreach ($lists as $list) :
                                    ?>

                                        <option value="<?php echo $list->grade_id; ?>" <?php if ($list->grade_id == $staffs->grade_id) {
                                                                              echo "selected";
                                                                            } ?>><?php echo $list->grade; ?></option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="contracting_institution_id">Contracting Institution:<?php echo asterik()?></label>
                                <select class="form-control select2" name="contracting_institution_id" id="contracting_institution_id" required>
                                    <option value="">Select Contracting Institution</option>
                                    <?php $lists = Modules::run('lists/contractors');
                                    foreach ($lists as $list) :
                                    ?>
                                        <option value="<?php echo $list->contracting_institution_id; ?>" <?php if ($list->contracting_institution_id == $staffs->contracting_institution_id) {
                                                                              echo "selected";
                                                                            } ?>><?php echo $list->contracting_institution; ?></option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="funder_id">Funder:<?php echo asterik()?></label>
                                <select class="form-control select2" name="funder_id" id="funder_id" required>
                                    <option value="">Select Funder</option>
                                    <?php $lists = Modules::run('lists/funder');
                                    foreach ($lists as $list) :
                                    ?>
                                        <option value="<?php echo $list->funder_id; ?>" <?php if ($list->funder_id == $staffs->funder_id) {
                                                                              echo "selected";
                                                                            } ?>><?php echo $list->funder; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="first_supervisor">First Supervisor:</label>
                                <select class="form-control select2" name="first_supervisor" id="first_supervisor" required>
                                    <option value="">Select First Supervisor</option>
                                    <?php $lists = Modules::run('lists/supervisor');
                                    foreach ($lists as $list) :
                                    ?>
                                        <?php if($list->staff_id != $staff_id){ ?>
                                        <option value="<?php echo $list->staff_id; ?>" <?php if ($list->staff_id == $staffs->first_supervisor) {
                                                                              echo "selected";
                                                                            } ?>><?php echo $list->lname . ' ' . $list->fname; ?></option>
                                        <?php } ?>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="second_supervisor">Second Supervisor:<?php echo asterik()?></label>
                                <select class="form-control select2" name="second_supervisor" id="second_supervisor" required>
                                    <option value="">Select Second Supervisor</option>
                                    <?php $lists = Modules::run('lists/supervisor');
                                    foreach ($lists as $list) :
                                    ?>
                                        <?php if($list->staff_id != $staff_id){ ?>
                                        <option value="<?php echo $list->staff_id; ?>"  <?php if ($list->staff_id == $staffs->second_supervisor) {
                                                                              echo "selected";
                                                                            } ?>><?php echo $list->lname . ' ' . $list->fname; ?></option>
                                        <?php } ?>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="contract_type_id">Contract Type:<?php echo asterik()?></label>
                                <select class="form-control select2" name="contract_type_id" id="contract_type_id" required>
                                    <?php $lists = Modules::run('lists/contracttype');
                                    foreach ($lists as $list) :
                                    ?>
                                        <option value="<?php echo $list->contract_type_id; ?>" <?php if ($list->contract_type_id == $staffs->contract_type_id) {
                                                                              echo "selected";
                                                                            } ?>><?php echo $list->contract_type; ?></option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6" style="margin-top:35px;">
                            <div class="form-group">
                                <label for="duty_station_id">Duty Station:<?php echo asterik()?></label>
                                <select class="form-control select2" name="duty_station_id" id="duty_station_id" required>
                                    <?php $lists = Modules::run('lists/stations');
                                    foreach ($lists as $list) :
                                    ?>
                                        <option value="<?php echo $list->duty_station_id; ?>" <?php if ($list->duty_station_id == $staffs->duty_station_id) {
                                                                              echo "selected";
                                                                            } ?>><?php echo $list->duty_station_name; ?></option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="division_id">Division:<?php echo asterik()?></label>
                                <select class="form-control select2" name="division_id" id="division_id" required>
                                    <?php $lists = Modules::run('lists/divisions');
                                    foreach ($lists as $list) :
                                    ?>
                                        <option value="<?php echo $list->division_id; ?>" <?php if ($list->division_id == $staffs->division_id) {
                                                                              echo "selected";
                                                                            } ?>><?php echo $list->division_name; ?></option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                            </div>


                            <div class="form-group">
                                <label for="unit_id">Unit:<?php echo asterik()?></label>
                                <select class="form-control select2" name="unit_id" id="unit_id">
                                    <option value="">Select a Unit</option>
                            
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="start_date">Start Date:<?php echo asterik()?></label>
                                <input type="text" class="form-control datepicker" name="start_date" id="start_date" required>
                            </div>

                            <div class="form-group">
                                <label for="end_date">End Date:<?php echo asterik()?></label>
                                <input type="text" class="form-control datepicker" name="end_date" id="end_date" required>
                            </div>

                            <div class="form-group">
                                <label for="status_id">Contract Status:<?php echo asterik()?></label>
                                <select class="form-control" name="status_id" id="status_id" required>
                                    <option value="1">Active</option>

                                </select>
                            </div>

                            <!-- <div class="form-group">
                                <label for="file_name">File Name:<?php echo asterik()?></label>
                                <input type="text" class="form-control" name="file_name" id="file_name" required>
                            </div> -->

                            <div class="form-group">
                            <label for="file_name">Previous Contract Status:<?php echo asterik()?></label>
                            <select class="form-control" name="previous_contract_status_id" id="status_id" required>
                              <?php 
                              $lists = Modules::run('lists/status');
                              foreach ($lists as $list) :
                                  if (in_array($list->status_id, [5, 6])) { // Only allow status_id 5 and 6
                              ?>
                                      <option value="<?php echo $list->status_id; ?>" 
                                          <?php if ($list->status_id == $staffs->status_id) {
                                              echo "selected";
                                          } ?>>
                                          <?php echo $list->status; ?>
                                      </option>
                              <?php 
                                  }
                              endforeach; 
                              ?>
                          </select>

                            <div class="form-group">
                                <label for="comments">Comments:</label>
                                <textarea class="form-control" name="comments" id="comments" rows="3"></textarea>
                            </div>



                            <div class="form-group" style="float:right;">
                                <br>
                                <label for="submit"></label>
                                <input type="submit" class="btn btn-dark" name="submit" value="Save">
                            </div>
                            <?php echo form_close(); ?>
                        </div>
                    </div>


                <!-- </div> -->
            </div>
   
    
    <script>
    $(document).ready(function() {
        // Initialize Select2 on both select fields
        $('#division_id, #unit_id').select2();

        // When a Division is selected, fetch the corresponding Units
        $('#division_id').on('change', function() {
            var divisionId = $(this).val();

            $.ajax({
                url: '<?php echo base_url("lists/get_units_by_division"); ?>/' + divisionId,
                type: 'GET',
                dataType: 'json',
                success: function(units) {
                    var $unitSelect = $('#unit_id');
                    $unitSelect.empty(); // Clear existing options

                    if (units && units.length > 0) {
                        $.each(units, function(index, unit) {
                            $unitSelect.append(
                                $('<option>', {
                                    value: unit.unit_id,
                                    text: unit.unit_name
                                })
                            );
                        });
                    } else {
                        $unitSelect.append(
                            $('<option>', {
                                value: '',
                                text: 'No units available'
                            })
                        );
                    }
                    // Refresh Select2
                    $unitSelect.trigger('change');
                },
                error: function(xhr, status, error) {
                    console.error("Error fetching units: " + error);
                }
            });
        });

        // Optionally, trigger change to load units for the initially selected division
        $('#division_id').trigger('change');
    });
    </script>