<div class="card">
  <div class="col-md-12" style="float: right;">
    <button type="button" class="btn   btn-dark btn-sm btn-bordered" data-bs-toggle="modal" data-bs-target="#add_item">+ Add New Staff</button>
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

              <td><a href="<?= base_url() ?>staff/update">Edit</a> | <a href="<?= base_url() ?>staff/profile">Profie</a></td>
              <div class="modal fade" id="add_profile" tabindex="-1" aria-labelledby="add_item_label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title" id="add_item_label">Employee Profile</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>

                    </div>
                    <div class="col-md-12 d-flex justify-content-center">
                      <button type="button" class="btn btn-primary" onclick="printPage()" data-bs-dismiss="modal">Print</button>
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

              <script>
                // Print button functionality
                function printPage() {
                  // Hide the print button before printing
                  document.querySelector(".btn-primary").style.display = "none";

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