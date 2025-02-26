<!-- Main content -->
<section class="content">
     <div class="container-fluid">
       <!-- Main row -->
       <div class="row">
         <section class="col-lg-12 ">
         
        
                <table id="mytab2" class="table mydata table-bordered table-striped mytable">
                  <thead>
                  <tr>
                      <th>#</th>
                      <th>Name</th>
                      <th>Division Name</th>
                      <th>Duty Station</th>
                      <th>Job</th>
                   
                      <th>Attendance Report</th>
                  </tr>
                  </thead>
                  <tbody>
                  <?php 
                  //dd($staffs);
                  $i=1; foreach ($staffs as $staff) { 
                                                     ?>
                                              <tr>
                                              <td data-label="Staff ID"><?php echo $i++; ?></td>
                                              <td data-label="NAME"><?php echo $staff->lname. " ". $staff->fname; ?> 
                                              </td>
                                              <td data-label="Duty Station"><?php echo $staff->division_name; ?></td>
                                              <td data-label="Duty Station"><?php echo $staff->duty_station_name; ?></td>
                                              <td data-label="JOB"><?php echo $staff->job_name; ?></td>
                                              <td data-label="ATTENDANCE">
                                              <?php if (in_array('83', $permissions)) : ?>
                                              <a class="btn btn-sm btn-default btn-outline"
                                                   href="<?php echo base_url(); ?>attendance/identifier/<?php echo urlencode($staff->staff_id); ?>">
                                                   <i class="fa fa-card" aria-hidden="true"></i>Add Identifier</a> 
                                              <?php endif; ?> 
                                              
                                              <a class="btn btn-sm btn-default btn-outline"
                                                   href="<?php echo base_url(); ?>attendance/timelogs/<?php echo urlencode($staff->staff_id); ?>">
                                                   <i class="fa fa-eye" aria-hidden="true"></i> Report</a>
                                                  
                                                  </td>
                                              </tr>
                                              <?php   } ?>
                  </tbody>
                  <tfoot>
                  </tfoot>
                </table>
         </section>
       </div>
       <!-- /.row (main row) -->
     </div><!-- /.container-fluid -->
   </section>
   <!-- /.content -->
   