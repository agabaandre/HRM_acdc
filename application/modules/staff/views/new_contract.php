<?php $this->load->view('staff_tab_menu'); ?>

<style>
    .contract-form-section {
        background: #fff;
        border-radius: 8px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        border: 1px solid #e9ecef;
    }
    .contract-form-section h5 {
        color: #495057;
        font-weight: 600;
        margin-bottom: 1.25rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #e9ecef;
    }
    .contract-form-section h5 i {
        color: #6c757d;
        margin-right: 0.5rem;
    }
    .form-group {
        margin-bottom: 1.25rem;
    }
    .form-group label {
        font-weight: 500;
        color: #495057;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }
    .required-field::after {
        content: " *";
        color: #dc3545;
    }
</style>

<div class="container-fluid mt-3">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom">
            <h4 class="mb-0"><i class="fa fa-file-contract me-2"></i>Assign New Contract</h4>
        </div>
    <div class="card-body">
                <?php echo validation_errors(); ?>
                        <?php echo form_open('staff/add_new_contract');
                         $staffs = $staffs[0];
            ?>
            
            <input type="hidden" name="staff_id" value="<?php echo $staff_id; ?>">
            
            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-6">
                    <!-- Contract Information Section -->
                    <div class="contract-form-section">
                        <h5><i class="fa fa-info-circle"></i>Contract Information</h5>

                            <div class="form-group">
                            <label for="job_id" class="required-field">Job</label>
                                <select class="form-control select2" name="job_id" id="job_id" required>
                                    <option value="">Select Job</option>
                                    <?php $jobs = Modules::run('lists/jobs');
                                foreach ($jobs as $job) : ?>
                                    <option value="<?php echo $job->job_id; ?>" <?php if ($job->job_id == $staffs->job_id) { echo "selected"; } ?>><?php echo $job->job_name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                             
                            <div class="form-group">
                            <label for="job_acting_id">Job Acting</label>
                                <select class="form-control select2" name="job_acting_id" id="job_acting_id">
                                <option value="">Select Job Acting</option>
                                    <?php $jobsacting = Modules::run('lists/jobsacting');
                                foreach ($jobsacting as $joba) : ?>
                                    <option value="<?php echo $joba->job_acting_id; ?>" <?php if ($joba->job_acting_id == $staffs->job_acting_id) { echo "selected"; } ?>><?php echo $joba->job_acting; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                            <label for="grade_id" class="required-field">Grade</label>
                                <select class="form-control select2" name="grade_id" id="grade_id" required>
                                    <option value="">Select Grade</option>
                                    <?php $lists = Modules::run('lists/grades');
                                foreach ($lists as $list) : ?>
                                    <option value="<?php echo $list->grade_id; ?>" <?php if ($list->grade_id == $staffs->grade_id) { echo "selected"; } ?>><?php echo $list->grade; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="contract_type_id" class="required-field">Contract Type</label>
                            <select class="form-control select2" name="contract_type_id" id="contract_type_id" required>
                                <option value="">Select Contract Type</option>
                                <?php $lists = Modules::run('lists/contracttype');
                                foreach ($lists as $list) : ?>
                                    <option value="<?php echo $list->contract_type_id; ?>" <?php if ($list->contract_type_id == $staffs->contract_type_id) { echo "selected"; } ?>><?php echo $list->contract_type; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                            <label for="contracting_institution_id" class="required-field">Contracting Institution</label>
                                <select class="form-control select2" name="contracting_institution_id" id="contracting_institution_id" required>
                                    <option value="">Select Contracting Institution</option>
                                    <?php $lists = Modules::run('lists/contractors');
                                foreach ($lists as $list) : ?>
                                    <option value="<?php echo $list->contracting_institution_id; ?>" <?php if ($list->contracting_institution_id == $staffs->contracting_institution_id) { echo "selected"; } ?>><?php echo $list->contracting_institution; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                            <label for="funder_id" class="required-field">Funder</label>
                                <select class="form-control select2" name="funder_id" id="funder_id" required>
                                    <option value="">Select Funder</option>
                                    <?php $lists = Modules::run('lists/funder');
                                foreach ($lists as $list) : ?>
                                    <option value="<?php echo $list->funder_id; ?>" <?php if ($list->funder_id == $staffs->funder_id) { echo "selected"; } ?>><?php echo $list->funder; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Location & Assignment Section -->
                    <div class="contract-form-section">
                        <h5><i class="fa fa-map-marker-alt"></i>Location & Assignment</h5>

                        <div class="form-group">
                            <label for="duty_station_id" class="required-field">Duty Station</label>
                            <select class="form-control select2" name="duty_station_id" id="duty_station_id" required>
                                <option value="">Select Duty Station</option>
                                <?php $lists = Modules::run('lists/stations');
                                foreach ($lists as $list) : ?>
                                    <option value="<?php echo $list->duty_station_id; ?>" <?php if ($list->duty_station_id == $staffs->duty_station_id) { echo "selected"; } ?>><?php echo $list->duty_station_name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                            <label for="division_id" class="required-field">Division</label>
                            <select class="form-control select2" name="division_id" id="division_id" required>
                                <option value="">Select Division</option>
                                <?php $lists = Modules::run('lists/divisions');
                                foreach ($lists as $list) : ?>
                                    <option value="<?php echo $list->division_id; ?>" <?php if ($list->division_id == $staffs->division_id) { echo "selected"; } ?>><?php echo $list->division_name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                            <label for="unit_id">Unit</label>
                            <select class="form-control select2" name="unit_id" id="unit_id">
                                <option value="">Select a Unit</option>
                                </select>
                            </div>

                            <div class="form-group">
                            <label for="other_associated_divisions">Other Associated Divisions</label>
                            <select class="form-control select2" name="other_associated_divisions[]" id="other_associated_divisions" multiple>
                                <option value="">Select Associated Divisions</option>
                                <?php $lists = Modules::run('lists/divisions');
                                foreach ($lists as $list) : ?>
                                    <option value="<?php echo $list->division_id; ?>"><?php echo $list->division_name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <small class="text-muted d-block mt-1"><i class="fa fa-info-circle"></i> You can select multiple divisions. Leave empty if none.</small>
                            </div>
                        </div>
                </div>

                <!-- Right Column -->
                <div class="col-lg-6">
                    <!-- Supervisors Section -->
                    <div class="contract-form-section">
                        <h5><i class="fa fa-users"></i>Supervisors</h5>

                            <div class="form-group">
                            <label for="first_supervisor" class="required-field">First Supervisor</label>
                            <select class="form-control select2" name="first_supervisor" id="first_supervisor" required>
                                <option value="">Select First Supervisor</option>
                                <?php $filters = array();
                                $lists = $this->staff_mdl->get_all_staff_data($filters);
                                foreach ($lists as $list) : ?>
                                    <?php if($list->staff_id != $staff_id): ?>
                                        <option value="<?php echo $list->staff_id; ?>" <?php if ($list->staff_id == $staffs->first_supervisor) { echo "selected"; } ?>><?php echo $list->lname . ' ' . $list->fname; ?></option>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                            <label for="second_supervisor">Second Supervisor</label>
                            <select class="form-control select2" name="second_supervisor" id="second_supervisor">
                                <option value="">Select Second Supervisor</option>
                                <?php 
                                $filters = array();
                                $lists = $this->staff_mdl->get_all_staff_data($filters);
                                foreach ($lists as $list) : ?>
                                    <?php if($list->staff_id != $staff_id): ?>
                                        <option value="<?php echo $list->staff_id; ?>" <?php if ($list->staff_id == $staffs->second_supervisor) { echo "selected"; } ?>><?php echo $list->lname . ' ' . $list->fname; ?></option>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            </div>

                    <!-- Contract Dates & Status Section -->
                    <div class="contract-form-section">
                        <h5><i class="fa fa-calendar-alt"></i>Contract Dates & Status</h5>

                            <div class="form-group">
                            <label for="start_date" class="required-field">Start Date</label>
                                <input type="text" class="form-control datepicker" name="start_date" id="start_date" required>
                            </div>

                            <div class="form-group">
                            <label for="end_date" class="required-field">End Date</label>
                                <input type="text" class="form-control datepicker" name="end_date" id="end_date" required>
                            </div>

                            <div class="form-group">
                            <label for="status_id" class="required-field">Contract Status</label>
                            <select class="form-control select2" name="status_id" id="status_id" required>
                                <option value="1" selected>Active</option>
                                <?php 
                                $lists = Modules::run('lists/status');
                                foreach ($lists as $list) :
                                    if (in_array($list->status_id, [1, 4, 7])): ?>
                                        <option value="<?php echo $list->status_id; ?>"><?php echo $list->status; ?></option>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                                </select>
                            </div>

                            <div class="form-group">
                            <label for="previous_contract_status_id" class="required-field">Previous Contract Status</label>
                            <select class="form-control select2" name="previous_contract_status_id" id="previous_contract_status_id" required <?= (isset($previous_contract_status) && $previous_contract_status === 4) ? 'readonly' : '' ?>>
                                <?php 
                                $previous_contract_status = isset($previous_contract_status) ? (int)$previous_contract_status : null;
                                
                                // If previous contract is separated (status 4), only show "Separated" option
                                if ($previous_contract_status === 4) {
                                    $lists = Modules::run('lists/status');
                                    foreach ($lists as $list) :
                                        if ($list->status_id == 4): ?>
                                            <option value="<?php echo $list->status_id; ?>" selected>
                                              <?php echo $list->status; ?>
                                          </option>
                                    <?php 
                                        endif;
                                    endforeach;
                                } else {
                                    // Otherwise, show normal options (status 5 and 6)
                                    ?>
                                    <option value="">Select Previous Contract Status</option>
                              <?php 
                              $lists = Modules::run('lists/status');
                              foreach ($lists as $list) :
                                        if (in_array($list->status_id, [5, 6])): ?>
                                            <option value="<?php echo $list->status_id; ?>" <?php if ($list->status_id == $staffs->status_id) { echo "selected"; } ?>>
                                          <?php echo $list->status; ?>
                                      </option>
                              <?php 
                                        endif;
                                    endforeach;
                                  }
                              ?>
                          </select>
                          <?php if (isset($previous_contract_status) && $previous_contract_status === 4): ?>
                            <small class="text-muted">The previous contract is separated and cannot be changed.</small>
                          <?php endif; ?>
                        </div>
                            </div>

                    <!-- Additional Information Section -->
                    <div class="contract-form-section">
                        <h5><i class="fa fa-comment-alt"></i>Additional Information</h5>

                        <div class="form-group">
                            <label for="comments">Comments</label>
                            <textarea class="form-control" name="comments" id="comments" rows="4" placeholder="Enter any additional comments or notes..."></textarea>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="<?php echo base_url('staff/staff_contracts/' . $staff_id); ?>" class="btn btn-outline-secondary">
                            <i class="fa fa-times me-1"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-dark">
                            <i class="fa fa-save me-1"></i> Save Contract
                        </button>
                    </div>
                </div>
            </div>
   
            <?php echo form_close(); ?>
        </div>
    </div>
</div>
    
    <script>
    $(document).ready(function() {
        // Initialize Select2 on ALL select fields
        $('.select2').select2({
            theme: 'bootstrap4',
            width: '100%'
        });

        // Initialize Select2 for multiple select (other_associated_divisions)
        $('#other_associated_divisions').select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'Select Associated Divisions',
            allowClear: true
        });

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