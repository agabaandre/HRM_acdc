<?php
$groups = Modules::run('permissions/getUserGroups');
$permissions = Modules::run('permissions/getPermissions');
$gpermissions = Modules::run('permissions/groupPermissions', $this->session->flashdata('group'));
?>
<div class="card">
  <div class="dashtwo-order-area" style="padding-top: 10px; min-height: 35em">
    <div class="container-fluid">
      <div class="row">
        <div class="col-lg-6">
          <div class="panel panel-default">
            <div class="panel-heading">
              <h5 class="panel-title">Manage User Groups and Permissions</h5>
            </div>
            <?php //print_r($gpermissions); 
            ?>

            <?php echo $this->session->flashdata("msg"); ?>
            <script>
              anim4_noti();
            </script>
            <div class="panel-body">
              <a class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#newgrp">Create a group</a>
              <a class="btn btn-danger btn-sm pull-right" data-bs-toggle="modal" data-bs-target="#permsModal">Add Permission</a>

              <hr>
              <?php echo form_open_multipart(base_url('permissions/assignPermissions'), array('id' => 'permissions', 'class' => 'permissions')); ?>
              <div class="form-group">
                <?php $selgroup = $this->session->flashdata('group'); ?>
                <label for="changeugroup">Select User Group to view or re-assign permissions</label>
                <div class="input-group">
                  <select id="changeugroup" class="form-control" name="group" style="min-width:300px; text-transform:capitalize;" onchange="this.form.submit()">
                    <?php foreach ($groups as $group) : ?>
                      <option value="<?php echo $group->id; ?>" <?php if ($group->id == $selgroup) echo "selected"; ?>><?php echo $group->group_name; ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <button type="submit" class="btn btn-danger">Save Group Permissions</button>
              <br>
              <table class="table">
                <tr style="background: #dee2e6; color:#17a2b8; border-radius:4px;">
                  <td>
                    <p style="color:#17a2b8;">Turn on/off Permission Assignment</p>
                  </td>
                  <td><input style="display: block; " name="assign" value="assign" type="checkbox" class="btn btn-primary"></td>
                </tr>
                <tr style="background: #dee2e6; color:#17a2b8; border-radius:4px;">
                  <td>
                    <p style="color:#17a2b8;">Grant All Permissions</p>
                  </td>
                  <td><input style="display: block;" id="checkAll" type="checkbox" class="btn btn-primary"></td>
                </tr>
                <hr>
                <?php foreach ($permissions as $perm) : ?>
                  <tr>
                    <td><?php echo $perm->definition; ?></td>
                    <td><input style="display: block; " name="permissions[]" value="<?php echo $perm->id; ?>" type="checkbox" <?php if (in_array($perm->id, $gpermissions)) echo "checked"; ?>></td>
                  </tr>
                <?php endforeach; ?>
              </table>
              <div class="form-group">
                <div class="input-group">
                  <input type="submit" class="btn btn-sm btn-primary" value="Save Group">
                </div>
              </div>
              </form>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="panel panel-default">
            <div class="panel-heading">
              <h5 class="panel-title">Groups Permissions</h5>
            </div>
            <div class="panel-body">
              <table class="table">
                <?php foreach ($groups as $group) : ?>
                  <tr>
                    <td><?php echo ucwords($group->group_name); ?></td>
                    <td>
                      <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#myModal<?php echo $group->id; ?>">Permissions</button>
                    </td>
                  </tr>
                  <div id="myModal<?php echo $group->id; ?>" class="modal fade" role="dialog">
                    <div class="modal-dialog modal-sm modal-dialog-centered">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h4 class="modal-title">Permissions for <?php echo ucwords($group->group_name); ?></h4>
                          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" style="padding-left:3em;">
                          <?php
                          $group_perms = Modules::run('permissions/getGroupPerms', $group->id);
                          foreach ($group_perms as $perm) {
                            echo "<li>" . ucwords($perm->name) . "</li>";
                          }
                          if (count($group_perms) < 1) {
                            echo "<h3 class='text-danger text-center'>No permissions assigned</h3>";
                          }
                          ?>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-default" data-bs-dismiss="modal">Close</button>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach;

                $this->load->view('add_perm_modal');
                ?>


              </table>


            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div id="newgrp" class="modal fade">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <?php echo form_open_multipart(base_url('permissions/addGroup'), array('id' => 'addGroup', 'class' => 'addGroup')); ?>
      <div class="modal-header">
        <h4 class="modal-title">Add group</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="padding-left: 3em;">
        <div class="mb-3">
          <input type="text" placeholder="Group Name" name="group_name" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Save</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
      </form>
    </div>
  </div>
</div>
<script>
  $("#checkAll").click(function() {
    $('input:checkbox').not(this).prop('checked', this.checked);
  });
</script>