<style>

    @media print{
        .hidden{
          display: none;
        }
        @page{
            margin-top: 0;
            margin-bottom: 0;
            display: flex;
            justify-content: center;
            atdgn-items: center;
            height: 100%;
            /* html, body{
                height: 100%;
                  width: 100%;
            } */
        }
        /* body{
          padding-top: 72px;
          padding-bottom: 72px;
        } */
    }

</style>





<div class="container">
  <div class="row">
    <?php $this_staff=$contracts[0];
    //dd($contracts);
       

    ?>
        <div class="col-lg-10">
        <div class="col-md-2">
        <a href="<?php echo base_url() ?>staff/new_contract/<?php echo $this_staff->staff_id; ?>" class="btn btn-outline-dark btn-sm btn-bordered ">+ Add New Contract</a>
    </div>
        <div class="col-md-8">
            <h2><?= $this_staff->lname . ' ' . $this_staff->fname; ?></h2>
            <h4>Personal Information</h4>
            <td><strong>SAPNO:</strong> <?= $this_staff->SAPNO ?></td>
            <td><strong>Nationality:</strong> <?php echo $this_staff->nationality?></td>
        </div>
    </div>
 
    <hr>
  </div>
  <div class="row">
    <div class="col-lg-12">
      <table class="table mydata table-striped table-bordered hidden">
        <thead>
          <tr>
          <th>#</th>
          <th>Duty Station</th>
          <th>Division</th>
          <th>Job</th>
          <th>Acting Job</th>
          <th>First Supervisor</th>
          <th>Second Supervisor</th>
          <th>Funder</th>
          <th>Contracting Institution</th>
          <th>Grade</th>
          <th>Type</th>
          <th>Start Date</th>
          <th>End Date</th>
          <th>Comment</th>
          <th>Status</th>
          <th>Option</th>
          </tr>
        </thead>
        <tbody>
          <?php $i = 1; ?>
          <?php foreach($contracts as $contract ){ ?>
            <tr>
                  <td><?=$i++?></td>
                  <td><?= $contract->duty_station_name ?></td>
                  <td><?= $contract->division_name ?></td>
                  <td><?= @character_limiter($contract->job_name, 15) ?></td>
                  <td><?= @character_limiter($contract->job_acting, 15) ?></td>
                  <td><?= @staff_name($contract->first_supervisor); ?></td>
					      	<td><?= @staff_name($contract->second_supervisor); ?></td>
                
                  <td><?= $contract->funder ?></td>
                  <td><?= $contract->contracting_institution ?></td>
                  <td> <?= $contract->grade ?></td>
                  <td> <?= $contract->contract_type ?></td>
                  <td> <?= $contract->start_date ?></td>
                  <td><?= $contract->end_date ?></td>
                  <td><?= $contract->comments; ?></td>
                  
                  <td><?= $contract->status; ?></td>
              <td class="text text-center">
              
                <a class="" onclick="return confirm('You are about to Edit this contract, Continue??? ');" href="#" data-bs-toggle="modal" data-bs-target="#renew_contract<?=$contract->staff_contract_id?>">Edit</a>

            

              </td>
            </tr>


            
              <!-- edit employee contract -->
              <div class="modal fade" id="renew_contract<?=$contract->staff_contract_id?>" tabindex="-1" aria-labelledby="add_item_label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                  <div class="modal-content">
                    <div class="modal-header">

                      <h5 class="modal-title" id="add_item_label">Edit Contract: <?= $this_staff->lname . ' ' . $this_staff->fname . ' ' . @$this_staff->oname ?> </h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>


                    <div class="modal-body">

                      <?php echo validation_errors(); ?>
                      <?php echo form_open('staff/update_contract'); 
                      $readonly='';
                    
                      
                    
                      
                      ?>

                      <div class="row">
                        <div class="col-md-6">
                          <h4>Contract Information</h4>
                           <input type="hidden" name="staff_contract_id" value="<?php echo $contract->staff_contract_id; ?>">
                           <input type="hidden" name="staff_id" value="<?php echo $contract->staff_id; ?>">
                          <div class="form-group">
                            <label for="job_id">Job:</label>
                            <select class="form-control select2" name="job_id" id="job_id" required <?=$readonly?>>
                              <option value="">Select Job</option>
                              <?php

                              $jobs = Modules::run('lists/jobs');
                              foreach ($jobs as $job) :

                               
                              ?>

                                <option value="<?php echo $job->job_id; ?>" <?php if ($job->job_id == $contract->job_id) {
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

                                <option value="<?php echo $joba->job_acting_id; ?>" <?php if ($joba->job_acting_id == $contract->job_acting_id) {
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

                                <option value="<?php echo $list->grade_id; ?>" <?php if ($list->grade_id == $contract->grade_id) {
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
                                <option value="<?php echo $list->contracting_institution_id; ?>" <?php if ($list->contracting_institution_id == $contract->contracting_institution_id) {
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
                                <option value="<?php echo $list->funder_id; ?>" <?php if ($list->funder_id == $contract->funder_id) {
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
                                <option value="<?php echo $list->staff_id; ?>" <?php if ($list->staff_id == $contract->first_supervisor) {
                                                                                  echo "selected";
                                                                                } ?>><?php echo $list->lname . ' ' . $list->fname; ?></option>
                              <?php endforeach; ?>
                              <!-- Add more options as needed -->
                            </select>
                          </div>

                          <div class="form-group">
                            <label for="second_supervisor">Second Supervisor:</label>
                            <select class="form-control select2" name="second_supervisor" id="second_supervisor" <?=$readonly?>>
                              <option value="">Select Second Supervisor</option>
                              <?php $lists = Modules::run('lists/supervisor');
                              foreach ($lists as $list) :
                              ?>
                                <option value="<?php echo $list->staff_id; ?>" <?php if ($list->staff_id == $contract->second_supervisor) {
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
                                <option value="<?php echo $list->contract_type_id; ?>" <?php if ($list->contract_type_id == $contract->contract_type_id) {
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
                                <option value="<?php echo $list->duty_station_id; ?>" <?php if ($list->duty_station_id == $contract->duty_station_id) {
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
                                <option value="<?php echo $list->division_id; ?>" <?php if ($list->division_id == $contract->division_id) {
                                                                                    echo "selected";
                                                                                  } ?>><?php echo $list->division_name; ?></option>
                              <?php endforeach; ?>
                              <!-- Add more options as needed -->
                            </select>
                          </div>

                          <div class="form-group">
                            <label for="start_date">Start Date:</label>
                            <input type="text" class="form-control datepicker" value="<?php echo $contract->start_date; ?>" name="start_date" id="start_date" required <?=$readonly?>>
                          </div>

                          <div class="form-group">
                            <label for="end_date">End Date:</label>
                            <input type="text" class="form-control datepicker" value="<?php echo $contract->end_date; ?>" name="end_date" id="end_date" required <?=$readonly?>>
                          </div>

                          <div class="form-group">
                            <label for="status_id">Contract Status:</label>
                          <select class="form-control" name="status_id" id="status_id" required>
                              <?php 
                              $lists = Modules::run('lists/status');
                              foreach ($lists as $list) :
                           
                              ?>
                                      <option value="<?php echo $list->status_id; ?>" 
                                          <?php if ($list->status_id == $contract->status_id) {
                                              echo "selected";
                                          } ?>>
                                          <?php echo $list->status; ?>
                                      </option>
                            


                                <?php  
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
                            <textarea class="form-control" name="comments" id="comments" rows="3"><?php echo $contract->comments; ?></textarea>
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
            <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>


