<div class="card">
  <div class="col-md-12" style="float: right;">
    <a href="<?php echo base_url() ?>staff/new" class="btn   btn-dark btn-sm btn-bordered">+ Add New Staff</a>
  </div>
  <div class="card-body">

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
            <th>Acting Job</th>
            <th>Division</th>
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
            <th>Actions</th>
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
              <td><?= @character_limiter($data->contracts[0]->jobacting_name, 15) ?></td>
              <td><?= @$data->contracts[0]->division_name ?></td>
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

              <td><a href="#" data-bs-toggle="modal" data-bs-target="#edit_profile">Edit</a> | <a href="#" data-bs-toggle="modal" data-bs-target="#add_profile">Profie</a></td>

              <div class="modal fade" id="add_profile" tabindex="-1" aria-labelledby="add_item_label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title" id="add_item_label">Employee Profile</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

                    </div>
                    <div class="toolbar hidden-print">
                      <div class="text-end">
                        <a href="#" data-bs-toggle="modal" class="btn   btn-dark btn-sm btn-bordered btn-print" data-bs-target="#edit_profile">Edit</a>
                        <a href="#" class="btn   btn-dark btn-sm btn-bordered btn-print" onclick="printPage()">Print</a>
                        <a href="#" class="btn   btn-dark btn-sm btn-bordered btn-print">Export as PDF</a>
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
                      <h5 class="modal-title" id="add_item_label">Employee Profile</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <form action="update_employee.php" method="POST">
                      <div class="modal-body" id="worker_profile">
                        <div class="row">
                          <div class="col-md-6">
                            <h4>Personal Information</h4>
                            <div class="mb-3">
                              <label for="lname" class="form-label">Last Name</label>
                              <input type="text" id="lname" name="lname" class="form-control" value="<?= $data->lname ?>" required>
                            </div>
                            <div class="mb-3">
                              <label for="fname" class="form-label">First Name</label>
                              <input type="text" id="fname" name="fname" class="form-control" value="<?= $data->fname ?>" required>
                            </div>
                            <div class="mb-3">
                              <label for="oname" class="form-label">Other Name</label>
                              <input type="text" id="oname" name="oname" class="form-control" value="<?= @$data->oname ?>">
                            </div>
                            <div class="mb-3">
                              <label for="sapno" class="form-label">SAPNO</label>
                              <input type="text" id="sapno" name="sapno" class="form-control" value="<?= $data->SAPNO ?>" required>
                            </div>
                            <div class="mb-3">
                              <label for="title" class="form-label">Title</label>
                              <input type="text" id="title" name="title" class="form-control" value="<?= $data->title ?>" required>
                            </div>
                            <div class="mb-3">
                              <label for="gender" class="form-label">Gender</label>
                              <input type="text" id="gender" name="gender" class="form-control" value="<?= $data->gender ?>" required>
                            </div>
                            <div class="mb-3">
                              <label for="nationality" class="form-label">Nationality</label>
                              <input type="text" id="nationality" name="nationality" class="form-control" value="<?= $data->nationality->nationality ?>" required>
                            </div>
                          </div>
                          <div class="col-md-6">
                            <h4>Contact Information</h4>
                            <div class="mb-3">
                              <label for="work_email" class="form-label">Email</label>
                              <input type="email" id="work_email" name="work_email" class="form-control" value="<?= @$data->work_email ?>">
                            </div>
                            <div class="mb-3">
                              <label for="tel_1" class="form-label">Telephone</label>
                              <input type="tel" id="tel_1" name="tel_1" class="form-control" value="<?= @$data->tel_1 ?>">
                            </div>
                            <div class="mb-3">
                              <label for="whatsapp" class="form-label">WhatsApp</label>
                              <input type="tel" id="whatsapp" name="whatsapp" class="form-control" value="<?= @$data->whatsapp ?>">
                            </div>
                          </div>
                          <div class="col-md-6">
                            <h4>Contract Information</h4>
                            <div class="mb-3">
                              <label for="job_name" class="form-label">Job</label>
                              <input type="text" id="job_name" name="job_name" class="form-control" value="<?= @character_limiter($data->contracts[0]->job_name, 15) ?>">
                            </div>
                            <div class="mb-3">
                              <label for="jobacting_name" class="form-label">Acting Job</label>
                              <input type="text" id="jobacting_name" name="jobacting_name" class="form-control" value="<?= @character_limiter($data->contracts[0]->jobacting_name, 15) ?>">
                            </div>
                            <div class="mb-3">
                              <label for="division_name" class="form-label">Division</label>
                              <input type="text" id="division_name" name="division_name" class="form-control" value="<?= @$data->contracts[0]->division_name ?>">
                            </div>
                            <div class="mb-3">
                              <label for="station_name" class="form-label">Duty Station</label>
                              <input type="text" id="station_name" name="station_name" class="form-control" value="<?= @$data->contracts[0]->station_name ?>">
                            </div>
                            <div class="mb-3">
                              <label for="funder_name" class="form-label">Funder</label>
                              <input type="text" id="funder_name" name="funder_name" class="form-control" value="<?= @$data->contracts[0]->funder_name ?>">
                            </div>
                            <div class="mb-3">
                              <label for="contractor_name" class="form-label">Contracting Organisation</label>
                              <input type="text" id="contractor_name" name="contractor_name" class="form-control" value="<?= @$data->contracts[0]->contractor_name ?>">
                            </div>
                            <div class="mb-3">
                              <label for="grade_name" class="form-label">Grade</label>
                              <input type="text" id="grade_name" name="grade_name" class="form-control" value="<?= @$data->contracts[0]->grade_name ?>">
                            </div>
                            <div class="mb-3">
                              <label for="contract_type_name" class="form-label">Contract Type</label>
                              <input type="text" id="contract_type_name" name="contract_type_name" class="form-control" value="<?= @$data->contracts[0]->contract_type_name ?>">
                            </div>
                            <div class="mb-3">
                              <label for="status" class="form-label">Contract Status</label>
                              <input type="text" id="status" name="status" class="form-control" value="<?= @$data->contracts[0]->status ?>">
                            </div>
                            <div class="mb-3">
                              <label for="start_date" class="form-label">Contract Start Date</label>
                              <input type="date" id="start_date" name="start_date" class="form-control" value="<?= @$data->contracts[0]->start_date ?>">
                            </div>
                            <div class="mb-3">
                              <label for="end_date" class="form-label">Contract End Date</label>
                              <input type="date" id="end_date" name="end_date" class="form-control" value="<?= @$data->contracts[0]->end_date ?>">
                            </div>
                            <div class="mb-3">
                              <label for="comments" class="form-label">Contract Comments</label>
                              <textarea id="comments" name="comments" class="form-control"><?= @$data->contracts[0]->comments ?></textarea>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <input type="submit" value="Update Employee Data" class="btn btn-primary">
                      </div>
                    </form>
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