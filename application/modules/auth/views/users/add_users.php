<?php

$usergroups = Modules::run("permissions/getUserGroups");


?>

<div class="row">


  <div class="col-md-12">
    <!-- general form elements disabled -->
    <div class="card card-default">
      <div class="card-header">
        <h4 class="card-title">User List</h4>
        <hr>
        <br>

        <?php echo form_open('auth/users', array('class' => 'form-horizontal', 'style' => 'margin-top: 4px !important;')); ?>

          <div class="form-group col-md-5">
            <label>Advanced User Search</label>
            <div class="input-group mb-3">
              <input type="text" name="search_key" class="form-control" placeholder="Username or Name">
              <div class="input-group-append">
                <button class="btn btn-default" type="submit">Search</button>
              </div>
            </div>



        </form>

      </div>
      <!-- /.card-header -->
      <div class="card-body">
        <a href="<?php echo base_url()?>auth/acdc_users" class="btn btn-success" target="_blanks"><i class="fa fa-users"></i>Render New Users</a>

        <?php echo $links; ?>

        <table id="mytab2" class="table table-striped ">
          <thead>

            <tr>
              <th style="width:2%;">#</th>
              <th>Name</th>
              <th>Email</th>
              <th>User Group</th>
              <th>Actions</th>


            </tr>
          </thead>
          <?php

          $no = 1;

          foreach ($users as $user) : 
          
          //dd($user);
          ?>
            <tbody>

              <tr>
                <td><?php echo $no; ?>. </td>
                <td><?php echo $user->name; ?></td>
                <td><?php echo $user->work_email; ?></td>

                <td><span class="badge text-bg-primary"><?php echo $user->group_name; ?></span></td>

                <td>
                <a href="<?php echo site_url('auth/impersonate/' . $user->user_id); ?>" class="btn btn-sm btn-warning">
                  <i class="fa fa-user-secret"></i> Impersonate
                </a>

                  <a data-bs-toggle="modal" data-bs-target="#user<?php echo $user->user_id; ?>" href="#">
                    <i class="fa fa-edit"></i> Edit
                  </a>
                </td>

                <div class="modal fade" id="block<?php echo $user->user_id; ?>">
<form class="block" action="<?php echo base_url(); ?>auth/blockUser" method="post">
	<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-body">

					<h4>Block user <b><?php echo $user->name; ?></b> ?</h4>

					<span class="status" style="margin:0 auto;"></span>

						<input type="hidden" name="id" value="<?php echo $user->id; ?>">
						

		
	</div>

	<div class="modal-footer">

<input type="submit" class="btn btn-danger" value="Yes, Block">

<a href="#" data-dismiss="modal" class="btn">Close</a>
		
	</div>



		
	</div>

	</div>

</form>

</div>



<div class="modal fade" id="reset<?php echo $user->user_id; ?>">
	<form class="reset" action="<?php echo base_url(); ?>auth/resetPass" method="post">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-body">

					<h4>Reset password for <b><?php echo $user->name; ?></b> ?</h4>

					<span class="status" style="margin:0 auto;"></span>


					<input type="hidden" name="id" value="<?php echo $user->id; ?>">
					<input type="hidden" name="password" value="<?php echo setting()->default_password; ?>">



				</div>

				<div class="modal-footer">

					<input type="submit" class="btn btn-danger" value="Yes, Reset">

					<a href="#" data-dismiss="modal" class="btn">Close</a>

				</div>


        <div class="modal fade" id="unblock<?php echo $user->id; ?>">
<form class="unblock" action="<?php echo base_url(); ?>auth/blockUser" method="post">
	<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-body">

					<h4>Activate user <b><?php echo $user->firstname." ".$user->lastname; ?></b> ?</h4>

					
					<span class="status" style="margin:0 auto;"></span>

						<input type="hidden" name="id" value="<?php echo $user->id; ?>">
						

		
	</div>

	<div class="modal-footer">

<input type="submit" class="btn btn-success" value="Yes, Activate">

<a href="#" data-dismiss="modal" class="btn">Close</a>
		
	</div>



		
	</div>

	</div>

</form>

</div>
                  


              </tr>


              <!--small modal to show Image-->
              <div class="modal" id="img<?php echo $user->user_id; ?>">
                <div class="modal-dialog">
                  <div class="modal-body">

                    <h1><a href="#" style="color: #FFF;" class="pull-right" data-dismiss="modal">&times;</a></h1>

                

                  </div>
                </div>
              </div>
              <!--/small modal to show Image-->

              <!---include supporting modal-->

            <?php

            include('user_details_modal.php');
            include('confirm_reset.php');
            include('confirm_block.php');

            if ($user->status == 0) {

              include('confirm_unblock.php');
            }

            $no++;
          endforeach ?>

            </tbody>

        </table>

        <?php echo $links; ?>

      </div>
      <!-- /.card-body -->
    </div>
  </div>



  <script>
    //get selected item
    function changeVal(selTag) {
      var x = selTag.options[selTag.selectedIndex].text;
      return x;
    }


    $(document).ready(function() {
      // Notification function using Lobibox
function show_notification(message, msgtype) {
    Lobibox.notify(msgtype, {
        pauseDelayOnHover: true,
        continueDelayOnInactiveTab: false,
        position: 'top right',
        icon: 'bx bx-check-circle',
        msg: message
    });
}

// Submit user update
$(".update_user").submit(function(e) {
    e.preventDefault();
    var formData = new FormData(this);
    console.log(formData);
    var url = "<?php echo base_url(); ?>auth/updateUser";
    $.ajax({
        url: url,
        method: 'post',
        contentType: false,
        processData: false,
        data: formData,
        success: function(result) {
            console.log(result);
            setTimeout(function() {
                $('.status').html(result);
                show_notification('Successfully Updated', 'success'); // Using Lobibox notification
                $('.status').html('');
                $('.clear').click();
            }, 3000);
        }
    });
});

// Submit password reset
$(".reset").submit(function(e) {
    e.preventDefault();
    var formData = $(this).serialize();
    console.log(formData);
    var url = "<?php echo base_url(); ?>auth/resetPass";
    $.ajax({
        url: url,
        method: 'post',
        data: formData,
        success: function(result) {
            setTimeout(function() {
                $('.status').html(result);
                show_notification('Successfully Updated', 'success'); // Using Lobibox notification
                $('.status').html('');
                $('.clear').click();
            }, 3000);
        }
    });
});
}); //doc ready



  </script>
