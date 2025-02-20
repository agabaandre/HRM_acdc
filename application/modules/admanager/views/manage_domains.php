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
            <th>Contract End Date</th>
            <th>Contract Status</th>
            <th>Division</th>
            <th>Email</th>
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
              <td><?= @character_limiter($data->contracts[0]->job_name, 30) ?></td>
         
              <td><?= @$data->contracts[0]->end_date ?></td>
              <td><?= @$data->contracts[0]->status ?></td>
              <td><?= @$data->contracts[0]->division_name ?></td>
              <td><?= @$data->work_email ?></td>
          

              <td class="text text-center">
              <?php $status=$data->contracts[0]->status_id;?>
              <?php if($status==3){?>
                <a class="" onclick="return confirm('You are about to Edit this contract, Continue??? ');" href="#" data-bs-toggle="modal" data-bs-target="#renew_contract<?=$data->contracts[0]->staff_contract_id?>">Disable Email from AD</a>

              <?php }?>
               
              </td>


          

            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>