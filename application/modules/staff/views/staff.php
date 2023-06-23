<div class="card">
  <div class="col-md-12" style="float: right;">
    <a href="<?php echo base_url() ?>staff/new" class="btn   btn-dark btn-sm btn-bordered">+ Add New Staff</a>
  </div>
  <div class="card-body">
  <div class="justify-content-center">
    <?php //print_r($this->session->tempdata());?>
  </div>
    <div class="table-responsive">
      <table class="table mydata table-striped table-bordered">
        <thead>
          <tr>
            <th>#</th>
            <th>SAPNO</th>
            <th>Title</th>
            <th>Name</th>
            <th>Gender</th>
            <th>Nationality</th>
            <th>Job</th>
            <th>Division</th>
            <th>Acting Job</th>

            <th>Duty Station</th>
            <th>Email</th>
            <th>Telephone</th>
            <th>WhatsApp</th>
            <th>Funder</th>
            <th>Contracting Organisation</th>
            <th>Grade</th>
            <th>Contract Type</th>
            <th>Contract Status</th>
            <th>Contract Start Date</th>
            <th>Contract End Date</th>
            <th>Contract Comments</th>
          </tr>
        </thead>
        <tbody>
          <?php
          //dd($staff->toArray());
          $i = 1;
          foreach ($staff as $data) : ?>
            <tr>

              <td><?= $i++ ?></td>
              <td><?= $data->SAPNO ?></td>
              <td><?= $data->title ?></td>
              <td><a href="#" data-bs-toggle="modal" data-bs-target="#add_profile"><?= $data->lname . ' ' . $data->fname . ' ' . @$data->oname ?></td>
              <td><?= $data->gender ?></td>
              <td><?= $data->nationality->nationality ?></td>
              <td><?= @character_limiter($data->contracts[0]->job_name, 15) ?></td>
              <td><?= @$data->contracts[0]->division_name ?></td>
              <td><?= @character_limiter($data->contracts[0]->jobacting_name, 15) ?></td>

              <td><?= @$data->contracts[0]->station_name ?></td>
              <td><?= @$data->work_email ?></td>
              <td><?= @$data->tel_1 . ' / ' . $data->tel_2 ?></td>
              <td><?= @$data->whatsapp ?></td>
              <td><?= @$data->contracts[0]->funder_name ?></td>
              <td><?= @$data->contracts[0]->contractor_name ?></td>
              <td><?= @$data->contracts[0]->grade_name ?></td>
              <td><?= @$data->contracts[0]->contract_type_name ?></td>
              <td><?= @$data->contracts[0]->status ?></td>
              <td><?= @$data->contracts[0]->start_date ?></td>
              <td><?= @$data->contracts[0]->end_date ?></td>
              <td><?= @$data->contracts[0]->comments ?></td>

              <div class="modal fade" id="add_profile" tabindex="-1" aria-labelledby="add_item_label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title" id="add_item_label">Employee Profile: <?= $data->lname . ' ' . $data->fname . ' ' . @$data->oname ?></h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

                    </div>
                    <div class="toolbar hidden-print">
                      <div class="text-end" style="margin-right:10px;">
                        <a href="#" data-bs-toggle="modal" class="btn   btn-dark btn-sm btn-bordered btn-print" data-bs-target="#edit_profile">Edit</a>
                        <a href="#" class="btn   btn-dark btn-sm btn-bordered btn-print" onclick="printPage()">Print</a>
                      </div>
                      <hr>
                    </div>
                    <div class="modal-body" id="worker_profile">
                      <div class="col-md-12 d-flex justify-content-center p-5">
                        <div>
                          <img src="<?php echo base_url() ?>/assets/images/AU_CDC_Logo-800.png" width="300">
                        </div>
                      </div>
                      <div class="row">
                        <div class="col-md-4">
                          <img src="<?php echo base_url() ?>assets/images/pp.png" class="user-img" alt="user avatar" style="width:200px; height:200px;">
                        </div>

                        <div class="col-md-8">
                          <h2><?= $data->lname . ' ' . $data->fname . ' ' . @$data->oname ?></h2>
                          <h4>Personal Information</h4>
                          <ul>
                            <li><strong>SAPNO:</strong> <?= $data->SAPNO ?></li>
                            <li><strong>Title:</strong> <?= $data->title ?></li>
                            <li><strong>Gender:</strong> <?= $data->gender ?></li>
                            <li><strong>Nationality:</strong> <?= $data->nationality->nationality ?></li>
                          </ul>
                          <h4>Contact Information</h4>
                          <ul>
                            <li><strong>Email:</strong> <?= @$data->work_email ?></li>
                            <li><strong>Telephone:</strong> <?= @$data->tel_1 . ' / ' . $data->tel_2 ?></li>
                            <li><strong>WhatsApp:</strong> <?= @$data->whatsapp ?></li>
                          </ul>
                          <h4>Contract Information</h4>
                          <ul>
                            <li><strong>Job:</strong> <?= @character_limiter($data->contracts[0]->job_name, 15) ?></li>
                            <li><strong>Acting Job:</strong> <?= @character_limiter($data->contracts[0]->jobacting_name, 15) ?></li>
                            <li><strong>Division:</strong> <?= @$data->contracts[0]->division_name ?></li>
                            <li><strong>Duty Station:</strong> <?= @$data->contracts[0]->station_name ?></li>
                            <li><strong>Funder:</strong> <?= @$data->contracts[0]->funder_name ?></li>
                            <li><strong>Contracting Organisation:</strong> <?= @$data->contracts[0]->contractor_name ?></li>
                            <li><strong>Grade:</strong> <?= @$data->contracts[0]->grade_name ?></li>
                            <li><strong>Contract Type:</strong> <?= @$data->contracts[0]->contract_type_name ?></li>
                            <li><strong>Contract Status:</strong> <?= @$data->contracts[0]->status ?></li>
                            <li><strong>Contract Start Date:</strong> <?= @$data->contracts[0]->start_date ?></li>
                            <li><strong>Contract End Date:</strong> <?= @$data->contracts[0]->end_date ?></li>
                            <li><strong>Contract Comments:</strong> <?= @$data->contracts[0]->comments ?></li>
                          </ul>
                        </div>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                  </div>
                </div>
              </div>


              <!-- edit model -->
              <!-- edit employee data model -->
              <div class="modal fade" id="edit_profile" tabindex="-1" aria-labelledby="add_item_label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title" id="add_item_label">Edit Employee Profile: <?= $data->lname . ' ' . $data->fname . ' ' . @$data->oname ?></h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">

                      <?php echo validation_errors(); ?>
                      <?php echo form_open('staff/update_staff'); ?>
                      <div class="row">

                        <div class="col-md-6">


                          <h4>Personal Information</h4>

                          <div class="form-group">
                            <label for="SAPNO">SAP Number:</label>
                            <input type="text" class="form-control" value="<?= $data->SAPNO ?>" name="SAPNO" id="SAPNO" readonly>
                          </div>

                          <div class="form-group">
                            <label for="gender">Title:</label>
                            <select class="form-control" name="title" id="title" required>
                              <?php if (!empty($data->title)) { ?>
                                <option value="<?php echo $data->title ?>"><?php echo $data->title ?></option>
                              <?php } ?>
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
                            <input type="text" class="form-control" value="<?php echo $data->fname;   ?>" name="fname" id="fname" required>
                          </div>
                          <input type="hidden" name="staff_id" value="<?php echo $data->staff_id; ?>">

                          <div class="form-group">
                            <label for="lname">Last Name:</label>
                            <input type="text" class="form-control" name="lname" value="<?php echo $data->lname;   ?>" id="lname" required>
                          </div>

                          <div class="form-group">
                            <label for="oname">Other Name:</label>
                            <input type="text" class="form-control" value="<?php echo $data->oname;   ?>" name="oname" id="oname">
                          </div>

                          <div class="form-group">
                            <label for="date_of_birth">Date of Birth:</label>
                            <input type="text" class="form-control datepicker" value="<?php echo $data->date_of_birth; ?>" name="date_of_birth" id="date_of_birth" required>
                          </div>

                          <div class="form-group">
                            <label for="gender">Gender:</label>
                            <select class="form-control" name="gender" id="gender" required>
                              <?php if (!empty($data->gender)) {
                                echo $data->gender;
                              } ?>
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
                                <option value="<?php echo $list->nationality_id; ?>" <?php if ($list->nationality_id == $data->nationality_id) {
                                                                                        echo "selected";
                                                                                      } ?>><?php echo $list->status; ?><?php echo $list->nationality; ?></option>
                              <?php endforeach; ?>
                              <!-- Add more options as needed -->
                            </select>
                          </div>

                          <div class="form-group">
                            <label for="initiation_date">Initiation Date:</label>
                            <input type="text" class="form-control datepicker" value="<?php echo $data->initiation_date; ?>" name="initiation_date" id="initiation_date" required>
                          </div>
                        </div>

                        <div class="col-md-6">
                          <h4>Contact Information</h4>


                          <div class="form-group">
                            <label for="tel_1">Telephone 1:</label>
                            <input type="text" class="form-control" value="<?php echo $data->tel_1; ?>" name="tel_1" id="tel_1" required>
                          </div>

                          <div class="form-group">
                            <label for="tel_2">Telephone 2:</label>
                            <input type="text" class="form-control" value="<?php echo $data->tel_2; ?>" name="tel_2" id="tel_2">
                          </div>

                          <div class="form-group">
                            <label for="whatsapp">WhatsApp:</label>
                            <input type="text" class="form-control" name="whatsapp" value="<?php echo $data->whatsapp; ?>" id="whatsapp" required>
                          </div>

                          <div class="form-group">
                            <label for="work_email">Work Email:</label>
                            <input type="email" class="form-control" name="work_email" value="<?php echo $data->work_email; ?>" id="work_email" required>
                          </div>
                          <br>
                          <div class="form-group">
                            <label for="private_email">Private Email:</label>
                            <input type="email" class="form-control" name="private_email" value="<?php echo $data->private_email; ?>" id="private_email">
                          </div>

                          <div class="form-group">
                            <label for="physical_location">Physical Location:</label>
                            <textarea class="form-control" name="physical_location" id="physical_location" rows="2" required><?php echo $data->physical_location; ?></textarea>
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
    </div>




  </div>

  <!-- edit employee data model -->

  <!-- edit model -->


  <script>
    // Print button functionality
    function printPage() {
      // Hide the print button before printing
      document.querySelector(".btn-print").style.display = "none";

      // Print only the worker's profile
      var printContents = document.getElementById("worker_profile").innerHTML;
      var originalContents = document.body.innerHTML;

      document.body.innerHTML = printContents;
      window.print();

      // Restore the original contents after printing
      document.body.innerHTML = originalContents;

      // Dismiss the modal after printing
      document.getElementById("add_profile").addEventListener("afterprint", function() {
        var modal = new bootstrap.Modal(document.getElementById("add_profile"));
        modal.hide();
      });
    }
  </script>




  </tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>