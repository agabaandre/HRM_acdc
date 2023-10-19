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

    ?>
        <div class="col-lg-10">
              <img src="<?php if (get_photo($this_staff->staff_id)) {
                          echo base_url() ?>uploads/staff/<?php echo @get_photo($this_staff->staff_id);
                                                        } else {
                                                          echo base_url() ?>uploads/staff/author.png
          <?php } ?>" class="img-fluid img-thumbnail" alt="user avatar" style="width:180px;">
        </div>
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
    <!-- <div class="col-md-2">
      <a href="<?php echo base_url() ?>staff/new_contract" class="btn btn-outline-dark btn-sm btn-bordered ">+ Add New Contract</a>
    </div> -->
    <hr>
  </div>
  <div class="row">
    <div class="col-lg-12">
      <table class="table mydata table-striped table-bordered hidden">
        <thead>
          <tr>
          <th>#</th>
          <th>Job</th>
          <th>Acting Job</th>
          <th>Division</th>
          <th>Duty Station</th>
          <th>Funder</th>
          <th>Contractor</th>
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
                  <td><?= @character_limiter($contract->job_name, 15) ?></td>
                  <td><?= @character_limiter($contract->job_acting, 15) ?></td>
                  <td><?= $contract->division_name ?></td>
                  <td><?= $contract->duty_station_name ?></td>
                  <td><?= $contract->funder ?></td>
                  <td><?= $contract->contracting_institution ?></td>
                  <td> <?= $contract->grade ?></td>
                  <td> <?= $contract->contract_type ?></td>
                  <td> <?= $contract->start_date ?></td>
                  <td><?= $contract->end_date ?></td>
                  <td><?= $contract->comments; ?></td>
                  
                  <td><?= $contract->status; ?></td>
              <td class="text text-center">
                <?php if($contract->status_id == 2){ ?>
                  <a class="" onctdck="return confirm('You are about to Renew this contract, Continue??? ');" href="<?php echo base_url();?>staff/renew_contract/<?php echo $contract->staff_contract_id."/".$contract->staff_id; ?>">Renew</a>
                <?php }elseif($contract->status_id == 1){ ?>
                  <a class="text text-danger" onctdck="return confirm('You are about to End this contract, Continue??? ');" href="<?php echo base_url();?>staff/end_contract/<?php echo $contract->staff_contract_id."/".$contract->staff_id; ?>">End Contract</a>
                <?php }else{ ?>
                  <a class="text text-muted" href="#!">-</a>
                <?php } ?>
              </td>
            </tr>
            <?php } ?>
        </tbody>
      </table>
    </div>
  </div>
</div>


