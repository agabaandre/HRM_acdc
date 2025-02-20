<div class="card">
  <div class="card-body">
    <div class="table-responsive">
    
      <table class="table mydata table-striped table-bordered">
        <thead>
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Gender</th>
            <th>Job</th>
            <th>Contract Type</th>
            <th>Contract Start Date</th>
            <th>Contract End Date</th>
            <th>Contract Status</th>
            <th>Contract Comments</th>
            <th>Contracting Organisation</th>
            <th>Nationality</th>
            <th>Grade</th>
            <th>Division</th>
            <th>Acting Job</th>

            <th>Duty Station</th>
            <th>Email</th>
            <th>Telephone</th>
            <th>WhatsApp</th>
            <th>Funder</th>
            <th>Option</th>
          </tr>
        </thead>
        <tbody>
          <?php
          //dd($staff->toArray());
          $i = 1;
          foreach ($staff as $data) :
          
          ?>
            <tr>

              <td><?= $i++ ?></td>
              <td><a href="<?php echo base_url()?>staff/staff_contracts/<?=$data->staff_id;?>"><?= $data->lname . ' ' . $data->fname . ' ' . @$data->oname ?></td>
              <td><?= $data->gender ?></td>
              <td><?= @character_limiter($data->contracts[0]->job_name, 15) ?></td>
              <td><?= @$data->contracts[0]->contract_type_name ?></td>

              <td><?= @$data->contracts[0]->start_date ?></td>
              <td><?= @$data->contracts[0]->end_date ?></td>
              <td><?= @$data->contracts[0]->status ?></td>

              <td><?= @character_limiter($data->contracts[0]->comments, 100) ?></td>
              <td><?= @$data->contracts[0]->contractor_name ?></td>
              <td><?= $data->nationality->nationality ?></td>
              <td><?= @$data->contracts[0]->grade_name ?></td>
              <td><?= @$data->contracts[0]->division_name ?></td>
              <td><?= @$data->contracts[0]->jobacting_name ?></td>

              <td><?= @$data->contracts[0]->station_name ?></td>
              <td><?= @$data->work_email ?></td>
              <td>
                  <?php 
                  $tel1 = !empty($data->tel_1) ? '<a href="tel:' . $data->tel_1 . '">' . $data->tel_1 . '</a>' : '';
                  $tel2 = !empty($data->tel_2) ? '<a href="tel:' . $data->tel_2 . '">' . $data->tel_2 . '</a>' : '';

                  echo $tel1 . (!empty($tel1) && !empty($tel2) ? ' | ' : '') . $tel2;
                  ?>
              </td>

              <td><?= @$data->whatsapp ?></td>
              <td><?= @$data->contracts[0]->funder_name ?></td>
              <td class="text text-center">
              <?php $status=$data->contracts[0]->status_id;?>
              <?php if($status==3){?>
                <a class="" onclick="return confirm('You are about to Edit this contract, Continue??? ');" href="#" data-bs-toggle="modal" data-bs-target="#renew_contract<?=$data->contracts[0]->staff_contract_id?>">Edit</a>

              <?php }?>
               
              </td>

             
              <!-- edit employee contract -->
              <div class="modal fade" id="renew_contract<?=$data->contracts[0]->staff_contract_id?>" tabindex="-1" aria-labelledby="add_item_label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                  <div class="modal-content">
                    <div class="modal-header">

                      <h5 class="modal-title" id="add_item_label">Edit Contract: <?= $data->lname . ' ' . $data->fname . ' ' . @$data->oname ?> </h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>


                    <div class="modal-body">

                      <?php echo validation_errors(); ?>
                      <?php echo form_open('staff/update_contract'); 
                      $readonly='';
                      if($status==3){
                      $readonly = "disabled";
                      }
                      
                    
                      
                      ?>

                      <div class="row">
                        <div class="col-md-6">
                          <h4>Contract Information</h4>
                           <input type="hidden" name="staff_contract_id" value="<?php echo $data->contracts[0]->staff_contract_id; ?>">
                          <div class="form-group">
                            <label for="job_id">Job:</label>
                            <select class="form-control select2" name="job_id" id="job_id" required <?=$readonly?>>
                              <option value="">Select Job</option>
                              <?php

                              $jobs = Modules::run('lists/jobs');
                              foreach ($jobs as $job) :

                               
                              ?>

                                <option value="<?php echo $job->job_id; ?>" <?php if ($job->job_id == $data->contracts[0]->job_id) {
                                                                              echo "selected";
                                                                            } ?>><?php echo $job->job_name; ?></option>
                              <?php endforeach; ?>
                              <!-- Add more options as needed -->
                            </select>
                          </div>

                          <div class="form-group">
                            <label for="job_acting_id">Job Acting:</label>
                            <select class="form-control select2" name="job_acting_id" id="job_acting_id" required <?=$readonly?>>
                              <option value="">Select Job Acting</option>
                              <?php $jobsacting = Modules::run('lists/jobsacting');
                              foreach ($jobsacting as $joba) :
                              ?>

                                <option value="<?php echo $joba->job_acting_id; ?>" <?php if ($joba->job_acting_id == $data->contracts[0]->job_acting_id) {
                                                                                      echo "selected";
                                                                                    } ?>><?php echo $joba->job_acting; ?></option>
                              <?php endforeach; ?>
                              <!-- Add more options as needed -->
                            </select>
                          </div>

                          <div class="form-group">
                            <label for="grade_id">Grade:</label>
                            <select class="form-control select2" name="grade_id" id="grade_id" required <?=$readonly?>>
                              <option value="">Select Grade</option>
                              <?php $lists = Modules::run('lists/grades');
                              foreach ($lists as $list) :
                              ?>

                                <option value="<?php echo $list->grade_id; ?>" <?php if ($list->grade_id == $data->contracts[0]->grade_id) {
                                                                                  echo "selected";
                                                                                } ?>><?php echo $list->grade; ?></option>
                              <?php endforeach; ?>
                              <!-- Add more options as needed -->
                            </select>
                          </div>

                          <div class="form-group">
                            <label for="contracting_institution_id">Contracting Institution:</label>
                            <select class="form-control select2" name="contracting_institution_id" id="contracting_institution_id" required <?=$readonly?>>
                              <option value="">Select Contracting Institution</option>
                              <?php $lists = Modules::run('lists/contractors');
                              foreach ($lists as $list) :
                              ?>
                                <option value="<?php echo $list->contracting_institution_id; ?>" <?php if ($list->contracting_institution_id == $data->contracts[0]->contracting_institution_id) {
                                                                                                    echo "selected";
                                                                                                  } ?>><?php echo $list->contracting_institution; ?></option>
                              <?php endforeach; ?>
                              <!-- Add more options as needed -->
                            </select>
                          </div>

                          <div class="form-group">
                            <label for="funder_id">Funder:</label>
                            <select class="form-control select2" name="funder_id" id="funder_id" required <?=$readonly?>>
                              <option value="">Select Funder</option>
                              <?php $lists = Modules::run('lists/funder');
                              foreach ($lists as $list) :
                              ?>
                                <option value="<?php echo $list->funder_id; ?>" <?php if ($list->funder_id == $data->contracts[0]->funder_id) {
                                                                                  echo "selected";
                                                                                } ?>><?php echo $list->funder; ?></option>
                              <?php endforeach; ?>
                            </select>
                          </div>

                          <div class="form-group">
                            <label for="first_supervisor">First Supervisor:</label>
                            <select class="form-control select2" name="first_supervisor" id="first_supervisor" required <?=$readonly?>>
                              <option value="">Select First Supervisor</option>
                              <?php $lists = Modules::run('lists/supervisor');
                              foreach ($lists as $list) :
                              ?>
                                <option value="<?php echo $list->staff_id; ?>" <?php if ($list->staff_id == $data->staff_id) {
                                                                                  echo "selected";
                                                                                } ?>><?php echo $list->lname . ' ' . $list->fname; ?></option>
                              <?php endforeach; ?>
                              <!-- Add more options as needed -->
                            </select>
                          </div>

                          <div class="form-group">
                            <label for="second_supervisor">Second Supervisor:</label>
                            <select class="form-control select2" name="second_supervisor" id="second_supervisor" required <?=$readonly?>>
                              <option value="">Select Second Supervisor</option>
                              <?php $lists = Modules::run('lists/supervisor');
                              foreach ($lists as $list) :
                              ?>
                                <option value="<?php echo $list->staff_id; ?>" <?php if ($list->staff_id == $data->staff_id) {
                                                                                  echo "selected";
                                                                                } ?>><?php echo $list->lname . ' ' . $list->fname; ?></option>
                              <?php endforeach; ?>
                              <!-- Add more options as needed -->
                            </select>
                          </div>

                          <div class="form-group">
                            <label for="contract_type_id">Contract Type:</label>
                            <select class="form-control select2" name="contract_type_id" id="contract_type_id" required <?=$readonly?>>
                              <?php $lists = Modules::run('lists/contracttype');
                              foreach ($lists as $list) :
                              ?>
                                <option value="<?php echo $list->contract_type_id; ?>" <?php if ($list->contract_type_id == $data->contracts[0]->contract_type_id) {
                                                                                          echo "selected";
                                                                                        } ?>><?php echo $list->contract_type; ?></option>
                              <?php endforeach; ?>
                              <!-- Add more options as needed -->
                            </select>
                          </div>
                        </div>
                        <div class="col-md-6" style="margin-top:35px;">
                          <div class="form-group">
                            <label for="duty_station_id">Duty Station:</label>
                            <select class="form-control select2" name="duty_station_id" id="duty_station_id" required <?=$readonly?>>
                              <?php $lists = Modules::run('lists/stations');
                              foreach ($lists as $list) :
                              ?>
                                <option value="<?php echo $list->duty_station_id; ?>" <?php if ($list->duty_station_id == $data->contracts[0]->duty_station_id) {
                                                                                        echo "selected";
                                                                                      } ?>><?php echo $list->duty_station_name; ?></option>
                              <?php endforeach; ?>
                              <!-- Add more options as needed -->
                            </select>
                          </div>

                          <div class="form-group">
                            <label for="division_id">Division:</label>
                            <select class="form-control select2" name="division_id" id="division_id" required <?=$readonly?>>
                              <?php $lists = Modules::run('lists/divisions');
                              foreach ($lists as $list) :
                              ?>
                                <option value="<?php echo $list->division_id; ?>" <?php if ($list->division_id == $data->contracts[0]->division_id) {
                                                                                    echo "selected";
                                                                                  } ?>><?php echo $list->division_name; ?></option>
                              <?php endforeach; ?>
                              <!-- Add more options as needed -->
                            </select>
                          </div>

                          <div class="form-group">
                            <label for="start_date">Start Date:</label>
                            <input type="text" class="form-control datepicker" value="<?php echo $data->contracts[0]->start_date; ?>" name="start_date" id="start_date" required <?=$readonly?>>
                          </div>

                          <div class="form-group">
                            <label for="end_date">End Date:</label>
                            <input type="text" class="form-control datepicker" value="<?php echo $data->contracts[0]->end_date; ?>" name="end_date" id="end_date" required <?=$readonly?>>
                          </div>

                          <div class="form-group">
                            <label for="status_id">Contract Status:</label>
                          <select class="form-control" name="status_id" id="status_id" required>
                              <?php 
                              $lists = Modules::run('lists/status');
                              foreach ($lists as $list) :
                                  if (in_array($list->status_id, [4, 7])&&($status==3)) { // Only allow status_id 6 and 7
                              ?>
                                      <option value="<?php echo $list->status_id; ?>" 
                                          <?php if ($list->status_id == $data->contracts[0]->status_id) {
                                              echo "selected";
                                          } ?>>
                                          <?php echo $list->status; ?>
                                      </option>
                              <?php 
                                  }
                                  else{ ?>
                                    <option value="<?php echo $list->status_id; ?>" 
                                    <?php if ($list->status_id == $data->contracts[0]->status_id) {
                                        echo "selected";
                                    } ?>>
                                    <?php echo $list->status; ?>
                                </option>


                                <?php  }
                              endforeach; 
                              ?>
                          </select>

                          </div>

                          <!-- <div class="form-group">
                            <label for="file_name">File Name:</label>
                            <input type="text" class="form-control" name="file_name" id="file_name" required>
                          </div> -->

                          <div class="form-group">
                            <label for="comments">Comments:</label>
                            <textarea class="form-control" name="comments" id="comments" rows="3"><?php echo $data->contracts[0]->comments; ?></textarea>
                          </div>




                          <div class="form-group" style="float:right;">
                            <br>
                            <label for="submit"></label>
                            <input type="submit" class="btn btn-dark"  value="Submit">
                            <input type="reset" class="btn btn-danger" value="Reset">
                          </div>

                          <?php echo form_close(); ?>
                        </div>
                      </div>
                    </div>


                  </div>
                </div>




              </div>

              <!-- Edit contract -->

            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>