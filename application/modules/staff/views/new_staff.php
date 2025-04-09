<style>
    .dt-buttons .btn-group .dataTables_paginate .paging_simple_numbers .dataTables_info{
        display: none !important;
   }
</style>
<?php $this->load->view('staff_tab_menu'); ?>
<div class="card">
<div class="row container-fluid mb-0">
  <?php $this->load->view('search','');?>
</div>
      <div class="card-body">
        <br />

                    <div class="row">

                        <div class="col-md-6">
                            <?php echo validation_errors(); ?>
                            <?php echo form_open('staff/new_submit'); ?>

                            <h4>Personal Information</h4>

                            <div class="form-group">
                                <label for="SAPNO">SAP Number:</label>
                                <input type="text" class="form-control" name="SAPNO" id="SAPNO">
                            </div>

                            <div class="form-group">
                                <label for="gender">Title:</label>
                                <select class="form-control validate-required" name="title" id="title">
                                    <option value="">Select Title</option>
                                    <option value="Dr">Dr</option>
                                    <option value="Prof">Prof</option>
                                    <option value="Rev">Rev</option>
                                    <option value="Mr">Mr</option>
                                    <option value="Mrs">Mrs</option>
                                    <option value="Ms">Ms</option>

                                </select>
                            </div>

                            <div class="form-group">
                                <label for="fname">First Name: <?php echo asterik();?></label>
                                <input type="text" class="form-control validate-required" name="fname" id="fname">
                                <div class="invalid-feedback">First Name is Required</div>
                            </div>

                            <div class="form-group">
                                <label for="lname">Last Name / Surname: <?php echo asterik();?></label>
                                <input type="text" class="form-control validate-required" name="lname" id="lname">
                                <div class="invalid-feedback">Surname is Required</div>
                            </div>

                            <div class="form-group">
                                <label for="oname">Other Name:</label>
                                <input type="text" class="form-control" name="oname" id="oname">
                            </div>

                            <div class="form-group">
                                <label for="date_of_birth">Date of Birth: <?php echo asterik();?></label>
                                <input type="text" class="form-control datepicker validate-required" name="date_of_birth"
                                    id="date_of_birth" autocomplete="off">
                                    <div class="invalid-feedback">Must be above 18 years of Age.</div>
                                
                            </div>

                            <div class="form-group">
                                <label for="gender">Gender:<?php echo asterik();?></label>
                                <select class="form-control validate-required" name="gender" id="gender">
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="nationality_id">Nationality: <?php echo asterik();?></label>
                                <select class="form-control select2 validate-required" name="nationality_id" id="nationality_id">
                                    <?php $lists = Modules::run('lists/nationality');
                                    foreach ($lists as $list) :
                                    ?>
                                    <option value="<?php echo $list->nationality_id; ?>">
                                        <?php echo $list->nationality; ?></option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="initiation_date">Initiation Date: <?php echo asterik();?></label>
                                <input type="text" class="form-control datepicker validate-required" name="initiation_date"
                                    id="initiation_date"  autocomplete="off">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h4>Contact Information </h4>


                            <div class="form-group">
                                <label for="tel_1">Telephone 1: <?php echo asterik();?></label>
                                <input type="text" class="form-control validate-required" name="tel_1" id="tel_1">
                                
                            </div>

                            <div class="form-group">
                                <label for="tel_2">Telephone 2:</label>
                                <input type="text" class="form-control" name="tel_2" id="tel_2">
                            </div>

                            <div class="form-group">
                                <label for="whatsapp">WhatsApp:</label>
                                <input type="text" class="form-control" name="whatsapp" id="whatsapp">
                            </div>

                            <div class="form-group">
                                <label for="work_email">Work Email: <?php echo asterik();?></label>
                                <input type="email" class="form-control validate-required" name="work_email" id="work_email">
                                <div class="invalid-feedback">Work Email  is Required</div>
                            </div>
                            <br>
                            <div class="form-group">
                                <label for="private_email">Personal/Private Email:</label>
                                <input type="email" class="form-control" name="private_email" id="private_email">
                            </div>

                            <div class="form-group">
                                <label for="physical_location">Physical Location:</label>
                                <textarea class="form-control" name="physical_location" id="physical_location" rows="2"
                                    ></textarea>
                            </div>
                        </div>
                    </div>



        
             

                    <div class="row">
                        <div class="col-md-6">
                            <h4>Contract Information</h4>

                            <div class="form-group">
                                <label for="job_id">Job: <?php echo asterik();?></label>
                                <select class="form-control select2 validate-required" name="job_id" id="job_id">
                                    <option value="">Select Job</option>
                                    <?php $jobs = Modules::run('lists/jobs');
                                    foreach ($jobs as $job) :
                                    ?>

                                    <option value="<?php echo $job->job_id; ?>"><?php echo $job->job_name; ?></option>
                                    <?php endforeach; ?>
                                    <div class="invalid-feedback">Job Institution is Required</div>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="job_acting_id">Job Acting:</label>
                                <select class="form-control select2" name="job_acting_id" id="job_acting_id">
                                    <option value="">Select Job Acting</option>
                                    <?php $jobsacting = Modules::run('lists/jobsacting');
                                    foreach ($jobsacting as $joba) :
                                    ?>

                                    <option value="<?php echo $joba->job_acting_id; ?>"><?php echo $joba->job_acting; ?>
                                    </option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="grade_id">Grade: <?php echo asterik();?></label>
                                <select class="form-control select2 validate-required" name="grade_id" id="grade_id">
                                    <option value="">Select Grade</option>
                                    <?php $lists = Modules::run('lists/grades');
                                    foreach ($lists as $list) :
                                    ?>

                                    <option value="<?php echo $list->grade_id; ?>"><?php echo $list->grade; ?></option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                    <div class="invalid-feedback">Salary Grade is Required</div>
                                     
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="contracting_institution_id">Contracting Institution: <?php echo asterik();?></label>
                                <select class="form-control select2 validate-required" name="contracting_institution_id"
                                    id="contracting_institution_id">
                                    <option value="">Select Contracting Institution</option>
                                    <?php $lists = Modules::run('lists/contractors');
                                    foreach ($lists as $list) :
                                    ?>
                                    <option value="<?php echo $list->contracting_institution_id; ?>">
                                        <?php echo $list->contracting_institution; ?></option>
                                    <?php endforeach; ?>
                                    <div class="invalid-feedback">Contracting Institution is Required</div>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="funder_id">Funder: <?php echo asterik();?></label>
                                <select class="form-control select2 validate-required" name="funder_id" id="funder_id">
                                    <option value="">Select Funder</option>
                                    <?php $lists = Modules::run('lists/funder');
                                    foreach ($lists as $list) :
                                    ?>
                                    <option value="<?php echo $list->funder_id; ?>"><?php echo $list->funder; ?>
                                    </option>
                                    <?php endforeach; ?>
                                    <div class="invalid-feedback">Funder is Required</div>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="first_supervisor">First Supervisor: <?php echo asterik();?></label>
                                <select class="form-control select2 validate-required" name="first_supervisor" id="first_supervisor">
                                    <option value="">Select First Supervisor</option>
                                    <?php $lists = Modules::run('lists/supervisor');
                                    foreach ($lists as $list) :
                                    ?>
                                    <option value="<?php echo $list->staff_id; ?>">
                                        <?php echo $list->lname . ' ' . $list->fname; ?></option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                                <div class="invalid-feedback">First Supervisor is Required</div>
                            </div>

                            <div class="form-group">
                                <label for="second_supervisor">Second Supervisor:</label>
                                <select class="form-control select2" name="second_supervisor" id="second_supervisor"
                                    >
                                    <option value="">Select Second Supervisor</option>
                                    <?php $lists = Modules::run('lists/supervisor');
                                    foreach ($lists as $list) :
                                    ?>
                                    <option value="<?php echo $list->staff_id; ?>">
                                        <?php echo $list->lname . ' ' . $list->fname; ?></option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="contract_type_id">Contract Type: <?php echo asterik();?></label>
                                <select class="form-control select2 validate-required" name="contract_type_id" id="contract_type_id"
                                    >
                                    <?php $lists = Modules::run('lists/contracttype');
                                    foreach ($lists as $list) :
                                    ?>
                                    <option value="<?php echo $list->contract_type_id; ?>">
                                        <?php echo $list->contract_type; ?></option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                                <div class="invalid-feedback">Contract Type is Required</div>
                            </div>
                        </div>
                        <div class="col-md-6" style="margin-top:35px;">
                            <div class="form-group">
                                <label for="duty_station_id">Duty Station: <?php echo asterik();?></label>
                                <select class="form-control select2 validate-required" name="duty_station_id" id="duty_station_id"
                                    >
                                    <?php $lists = Modules::run('lists/stations');
                                    foreach ($lists as $list) :
                                    ?>
                                    <option value="<?php echo $list->duty_station_id; ?>">
                                        <?php echo $list->duty_station_name; ?></option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                                <div class="invalid-feedback">Duty Station is Required</div>
                            </div>
                            <div class="form-group">
                                <label for="division_id">Division: <?php echo asterik();?></label>
                                <select class="form-control select2 validate-required" name="division_id" id="division_id">
                                    <?php 
                                        $divisions = Modules::run('lists/divisions');
                                        foreach ($divisions as $division): 
                                    ?>
                                    <option value="<?php echo $division->division_id; ?>">
                                        <?php echo $division->division_name; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Division is Required</div>
                            </div>

                            <div class="form-group">
                                <label for="unit_id">Unit:</label>
                                <select class="form-control select2" name="unit_id" id="unit_id">
                                    <option value="">Select a Unit</option>
                            
                                </select>
                            </div>


                            <div class="form-group">
                                <label for="start_date">Start Date:<?php echo asterik();?></label>
                                <input type="text" class="form-control datepicker validate-required" name="start_date" id="start_date"
                                     autocomplete="off">
                                     <div class="invalid-feedback">Start Date is Required</div>
                            </div>
                            

                            <div class="form-group">
                                <label for="end_date">End Date: <?php echo asterik();?></label>
                                <input type="text" class="form-control datepicker validate-required" name="end_date" id="end_date"
                                     autocomplete="off">
                                     <div class="invalid-feedback">Must be greater than start date</div>
                            </div>
                            

                            <div class="form-group">
                                <label for="status_id">Contract Status:</label>
                                <select class="form-control validate-required" name="status_id" id="status_id">
                                    <option value="1" selected>Active</option>

                                </select>
                            </div>

                            <div class="form-group">
                                <label for="file_name">File Name/Number:</label>
                                <input type="hidden" class="form-control" name="file_name" id="file_name">
                                <div class="invalid-feedback">File Number/Name is Required</div>
                            </div>

                            <div class="form-group">
                                <label for="comments">Comments:</label>
                                <textarea class="form-control" name="comments" id="comments" rows="3"></textarea>
                            </div>



                            <div class="form-group" style="float:right;">
                                <br>
                                <label for="submit"></label>
                                <input type="submit" class="btn btn-dark" name="submit" value="Submit">
                                <input type="reset" class="btn btn-danger" name="submit" value="Reset">
                            </div>
                            <?php echo form_close(); ?>
                        </div>
                    </div>


                </div>
    </div>
      
     

    <script>
  $(document).ready(function () {
    $('#division_id, #unit_id').select2();

    $('#division_id').on('change', function () {
      var divisionId = $(this).val();
      $.ajax({
        url: '<?= base_url("lists/get_units_by_division"); ?>/' + divisionId,
        type: 'GET',
        dataType: 'json',
        success: function (units) {
          var $unitSelect = $('#unit_id').empty();
          if (units.length > 0) {
            $.each(units, function (index, unit) {
              $unitSelect.append(new Option(unit.unit_name, unit.unit_id));
            });
          } else {
            $unitSelect.append(new Option('No units available', ''));
          }
          $unitSelect.trigger('change');
        }
      });
    });

    $('#division_id').trigger('change');

    // Blur validation
    $(".form-control").on("blur", function () {
      if ($(this).hasClass("validate-required") && $(this).val().trim() === "") {
        $(this).addClass("is-invalid");
      } else {
        $(this).removeClass("is-invalid");
      }
    });

    // Form Submit
    $("form").on("submit", function (e) {
      e.preventDefault();

      let form = $(this);
      let emailField = $("#work_email");
      let work_email = emailField.val().trim();

      if (!validateForm()) {
        show_notification("Please fix the errors in the form.", "error");
        return false;
      }

      // Check if email already exists
      $.ajax({
        url: '<?= base_url("staff/check_work_email"); ?>',
        method: 'POST',
        data: { work_email: work_email },
        dataType: 'json',
        success: function (response) {
          if (response.exists) {
            const nameInfo = response.name ? ` to ${response.name}` : '';
            show_notification(`The email address is already assigned${nameInfo}.`, "error");
            emailField.addClass("is-invalid");
          } else {
            emailField.removeClass("is-invalid");

            // Submit if email is unique
            $.ajax({
              url: '<?= base_url("staff/new_submit"); ?>',
              type: "POST",
              data: form.serialize(),
              dataType: "json",
              success: function (response) {
                show_notification("Form submitted successfully!", "success");
                setTimeout(() => {
                  window.location.href = '<?= base_url("staff/staff_contracts/"); ?>' + response.staff_id;
                }, 3000);
              },
              error: function () {
                show_notification("There was an error submitting the form.", "error");
                setTimeout(() => {
                  window.location.href = '<?= base_url("staff/index/"); ?>';
                }, 3000);
              }
            });
          }
        },
        error: function () {
          show_notification("Unable to validate work email. Try again.", "error");
        }
      });
    });

    function validateForm() {
      let isValid = true;

      $('.validate-required').each(function () {
        if ($(this).val().trim() === "") {
          $(this).addClass("is-invalid");
          isValid = false;
        } else {
          $(this).removeClass("is-invalid");
        }
      });

      const dob = $("#date_of_birth").val();
      if (dob) {
        const birthDate = new Date(dob);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) age--;
        if (age < 18) {
          $("#date_of_birth").addClass("is-invalid");
          isValid = false;
        } else {
          $("#date_of_birth").removeClass("is-invalid");
        }
      }

      const start = $("#start_date").val();
      const end = $("#end_date").val();
      if (start && end) {
        if (new Date(end) <= new Date(start)) {
          $("#end_date").addClass("is-invalid");
          isValid = false;
        } else {
          $("#end_date").removeClass("is-invalid");
        }
      }

      return isValid;
    }

    function show_notification(message, msgtype) {
      Lobibox.notify(msgtype, {
        pauseDelayOnHover: true,
        continueDelayOnInactiveTab: false,
        position: 'top right',
        icon: 'bx bx-check-circle',
        msg: message
      });
    }
  });
</script>
