<h6 class="mb-0 text-uppercase"></h6>
<hr />
<div class="card">
    <div class="card-body">
        <br />

        <!-- SmartWizard html -->
        <div id="smartwizard">
            <ul class="nav">
                <li>
                    <a class="nav-link" href="#step-1"> <strong>Step 1</strong>
                        <br>Personal Information</a>
                </li>
                <li>
                    <a class="nav-link" href="#step-2"> <strong>Step 2</strong>
                        <br>Contact Information</a>
                </li>

            </ul>
            <div class="tab-content">
                <div id="step-1" class="tab-pane" role="tabpanel" aria-labelledby="step-1">
                    <h3>Step 1: </h3>
                    <div class="row">

                        <div class="col-md-6">
                            <?php echo validation_errors(); ?>
                            <?php echo form_open('staff/new'); ?>

                            <h4>Personal Information</h4>

                            <div class="form-group">
                                <label for="SAPNO">SAP Number:</label>
                                <input type="text"  class="form-control" name="SAPNO" id="SAPNO" required>
                            </div>

                            <div class="form-group">
                                <label for="gender">Title:</label>
                                <select class="form-control" name="title" id="title" required>
                                    <option value="">Select Title</option>
                                    <option value="Dr">Dr</option>
                                    <option value="Prof">Prof</option>
                                    <option value="Rev">Rev</option>
                                    <option value="Mr">Mr</option>
                                    <option value="Mrs">Mrs</option>

                                </select>
                            </div>

                            <div class="form-group">
                                <label for="fname">First Name:</label>
                                <input type="text" class="form-control" name="fname" id="fname" required>
                            </div>

                            <div class="form-group">
                                <label for="lname">Last Name:</label>
                                <input type="text" class="form-control" name="lname" id="lname" required>
                            </div>

                            <div class="form-group">
                                <label for="oname">Other Name:</label>
                                <input type="text" class="form-control" name="oname" id="oname">
                            </div>

                            <div class="form-group">
                                <label for="date_of_birth">Date of Birth:</label>
                                <input type="text" class="form-control datepicker" name="date_of_birth" id="date_of_birth" required>
                            </div>

                            <div class="form-group">
                                <label for="gender">Gender:</label>
                                <select class="form-control" name="gender" id="gender" required>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="nationality_id">Nationality:</label>
                                <select class="form-control select2" name="nationality_id" id="nationality_id" required>
                                    <?php $lists = Modules::run('lists/nationality');
                                    foreach ($lists as $list) :
                                    ?>
                                        <option value="<?php echo $list->nationality_id; ?>"><?php echo $list->nationality; ?></option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="initiation_date">Initiation Date:</label>
                                <input type="text" class="form-control datepicker" name="initiation_date" id="initiation_date" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h4>Contact Information</h4>


                            <div class="form-group">
                                <label for="tel_1">Telephone 1:</label>
                                <input type="text" class="form-control" name="tel_1" id="tel_1" required>
                            </div>

                            <div class="form-group">
                                <label for="tel_2">Telephone 2:</label>
                                <input type="text" class="form-control" name="tel_2" id="tel_2">
                            </div>

                            <div class="form-group">
                                <label for="whatsapp">WhatsApp:</label>
                                <input type="text" class="form-control" name="whatsapp" id="whatsapp" required>
                            </div>

                            <div class="form-group">
                                <label for="work_email">Work Email:</label>
                                <input type="email" class="form-control" name="work_email" id="work_email" required>
                            </div>
                            <br>
                            <div class="form-group">
                                <label for="private_email">Private Email:</label>
                                <input type="email" class="form-control" name="private_email" id="private_email">
                            </div>

                            <div class="form-group">
                                <label for="physical_location">Physical Location:</label>
                                <textarea class="form-control" name="physical_location" id="physical_location" rows="2" required></textarea>
                            </div>
                        </div>
                    </div>


                </div>

                <div id="step-2" class="tab-pane" role="tabpanel" aria-labelledby="step-2">
                    <h3>Step 2: </h3>

                    <div class="row">
                        <div class="col-md-6">
                            <h4>Contract Information</h4>

                            <div class="form-group">
                                <label for="job_id">Job:</label>
                                <select class="form-control select2" name="job_id" id="job_id" required>
                                    <option value="">Select Job</option>
                                    <?php $jobs = Modules::run('lists/jobs');
                                    foreach ($jobs as $job) :
                                    ?>

                                        <option value="<?php echo $job->job_id; ?>"><?php echo $job->job_name; ?></option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="job_acting_id">Job Acting:</label>
                                <select class="form-control select2" name="job_acting_id" id="job_acting_id" required>
                                    <option value="">Select Job Acting</option>
                                    <?php $jobsacting = Modules::run('lists/jobsacting');
                                    foreach ($jobsacting as $joba) :
                                    ?>

                                        <option value="<?php echo $joba->job_acting_id; ?>"><?php echo $joba->job_acting; ?></option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="grade_id">Grade:</label>
                                <select class="form-control select2" name="grade_id" id="grade_id" required>
                                    <option value="">Select Grade</option>
                                    <?php $lists = Modules::run('lists/grades');
                                    foreach ($lists as $list) :
                                    ?>

                                        <option value="<?php echo $list->grade_id; ?>"><?php echo $list->grade; ?></option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="contracting_institution_id">Contracting Institution:</label>
                                <select class="form-control select2" name="contracting_institution_id" id="contracting_institution_id" required>
                                    <option value="">Select Contracting Institution</option>
                                    <?php $lists = Modules::run('lists/contractors');
                                    foreach ($lists as $list) :
                                    ?>
                                        <option value="<?php echo $list->contracting_institution_id; ?>"><?php echo $list->contracting_institution; ?></option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="funder_id">Funder:</label>
                                <select class="form-control select2" name="funder_id" id="funder_id" required>
                                    <option value="">Select Funder</option>
                                    <?php $lists = Modules::run('lists/funder');
                                    foreach ($lists as $list) :
                                    ?>
                                        <option value="<?php echo $list->funder_id; ?>"><?php echo $list->funder; ?></option>
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
                                        <option value="<?php echo $list->staff_id; ?>"><?php echo $list->lname . ' ' . $list->fname; ?></option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="second_supervisor">Second Supervisor:</label>
                                <select class="form-control select2" name="second_supervisor" id="second_supervisor" required>
                                    <option value="">Select Second Supervisor</option>
                                    <?php $lists = Modules::run('lists/supervisor');
                                    foreach ($lists as $list) :
                                    ?>
                                        <option value="<?php echo $list->staff_id; ?>"><?php echo $list->lname . ' ' . $list->fname; ?></option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="contract_type_id">Contract Type:</label>
                                <select class="form-control select2" name="contract_type_id" id="contract_type_id" required>
                                    <?php $lists = Modules::run('lists/contracttype');
                                    foreach ($lists as $list) :
                                    ?>
                                        <option value="<?php echo $list->contract_type_id; ?>"><?php echo $list->contract_type; ?></option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6" style="margin-top:35px;">
                            <div class="form-group">
                                <label for="duty_station_id">Duty Station:</label>
                                <select class="form-control select2" name="duty_station_id" id="duty_station_id" required>
                                    <?php $lists = Modules::run('lists/stations');
                                    foreach ($lists as $list) :
                                    ?>
                                        <option value="<?php echo $list->duty_station_id; ?>"><?php echo $list->duty_station_name; ?></option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="division_id">Division:</label>
                                <select class="form-control select2" name="division_id" id="division_id" required>
                                    <?php $lists = Modules::run('lists/divisions');
                                    foreach ($lists as $list) :
                                    ?>
                                        <option value="<?php echo $list->division_id; ?>"><?php echo $list->division_name; ?></option>
                                    <?php endforeach; ?>
                                    <!-- Add more options as needed -->
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="start_date">Start Date:</label>
                                <input type="text" class="form-control datepicker" name="start_date" id="start_date" required>
                            </div>

                            <div class="form-group">
                                <label for="end_date">End Date:</label>
                                <input type="text" class="form-control datepicker" name="end_date" id="end_date" required>
                            </div>

                            <div class="form-group">
                                <label for="status_id">Contract Status:</label>
                                <select class="form-control" name="status_id" id="status_id" required>
                                    <option value="1">Active</option>

                                </select>
                            </div>

                            <div class="form-group">
                                <label for="file_name">File Name:</label>
                                <input type="text" class="form-control" name="file_name" id="file_name" required>
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
        </div>
    </div>