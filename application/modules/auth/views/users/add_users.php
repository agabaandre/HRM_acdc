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

                <td><a data-toggle="modal" data-target="#user<?php echo $user->user_id; ?>" href="#"><i class="fa fa-edit"></i>Edit</button>

                  <?php if ($user->status == 1) { ?>

                    <span class="badge text-bg-warning"><a data-toggle="modal" data-target="#block<?php echo $user->user_id; ?>" href="#">Block</a></span>
                  <?php } else { ?>


                    <span class="badge text-bg-danger"><a data-toggle="modal" data-target="#unblock<?php echo $user->user_id; ?>" href="#">Activate</a></span>

                  <?php } ?>



                  <a data-toggle="modal" data-target="#reset<?php echo $user->user_id; ?>" href="#">Reset</a>

                </td>

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


      //Submit new user data

      $(".user_form").submit(function(e) {

        e.preventDefault();

        $('.status').html('<img style="max-height:50px" src="<?php echo base_url(); ?>assets/img/loading.gif">');
        var formData = $(this).serialize();
        // console.log(formData);
        var url = "<?php echo base_url(); ?>auth/addUser";
        $.ajax({
          url: url,
          method: 'post',
          data: formData,
          success: function(result) {
            console.log(result);
            setTimeout(function() {
              $('.status').html(result);
              $.notify(result, 'info');
              $('.status').html('');
              $('.clear').click();
            }, 1000);


          }
        }); //ajax

      }); //form submit


      //Submit user update
      $(".update_user").submit(function(e) {
        e.preventDefault();
        $('.status').html('<img style="max-height:50px" src="<?php echo base_url(); ?>assets/img/loading.gif">');
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

              $.notify(result, 'info');

              $('.status').html('');

              $('.clear').click();

            }, 3000);


          }
        }); //ajax


      }); //form submit



      $(".reset").submit(function(e) {
        e.preventDefault();
        $('.status').html('<img style="max-height:50px" src="<?php echo base_url(); ?>assets/img/loading.gif">');
        var formData = $(this).serialize();
        console.log(formData);
        var url = "<?php echo base_url(); ?>auth/resetPass";
        $.ajax({
          url: url,
          method: 'post',
          data: formData,
          success: function(result) {
            // console.log(result);
            setTimeout(function() {
              $('.status').html(result);
              $.notify(result, 'info');
              $('.status').html('');

              $('.clear').click();

            }, 3000);


          }
        }); //ajax


      }); //form submit


      //block user

      $(".block").submit(function(e) {

        e.preventDefault();


        $('.status').html('<img style="max-height:50px" src="<?php echo base_url(); ?>assets/img/loading.gif">');



        var formData = $(this).serialize();

        console.log(formData);

        var url = "<?php echo base_url(); ?>auth/blockUser";

        $.ajax({
          url: url,
          method: 'post',
          data: formData,
          success: function(result) {

            console.log(result);

            setTimeout(function() {

              $('.status').html(result);

              $.notify(result, 'info');

              $('.status').html('');

              $('.clear').click();

            }, 3000);


          }
        }); //ajax


      }); //form submit


      //block user

      $(".unblock").submit(function(e) {

        e.preventDefault();


        $('.status').html('<img style="max-height:50px" src="<?php echo base_url(); ?>assets/img/loading.gif">');



        var formData = $(this).serialize();

        console.log(formData);

        var url = "<?php echo base_url(); ?>auth/unblockUser";

        $.ajax({
          url: url,
          method: 'post',
          data: formData,
          success: function(result) {

            console.log(result);

            setTimeout(function() {

              $('.status').html(result);

              $.notify(result, 'info');

              $('.status').html('');

              $('.clear').click();

            }, 3000);


          }
        }); //ajax


      }); //form submit


    }); //doc ready


    function getCountries(val) {
      $.ajax({
        method: "GET",
        url: "<?php echo base_url(); ?>geoareas/getCountries",
        data: 'region_data=' + val,
        success: function(res) {
          console.log(res);
          $(".scountry").html(res);
        }
      });
    }
  </script>
