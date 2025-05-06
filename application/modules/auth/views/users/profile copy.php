<!-- Col -->
<div class="row">
    <div class="col-md-5 col-lg-4 col-xl-3 col-xs-12 col-md-pull-2">
        <div class="card box-widget widget-user">
            <div class="widget-user-header testbgpattern1"></div>
            <div class="widget-user-image">


                <img class="img-fluid img-thumbnail" src="<?php if (isset($this->session->userdata('user')->photo)) {
                                                                echo base_url() ?>uploads/staff/<?php echo $this->session->userdata('user')->photo;
                                                            } else {
                                                                echo base_url() ?>uploads/staff/author.png
					<?php } ?>" style="width:200px; border-radius:7px;">
            </div>
            <div class="card-body text-center">
                <div class="item-user pro-user">
                    <h4 class="pro-user-username tx-15 pt-2 mt-4 mb-1"><?php echo $this->session->userdata('user')->name; ?></h4>
                    <h6 class="pro-user-desc tx-13 mb-3 font-weight-normal text-muted"><?php echo $this->session->userdata('user')->group_name; ?></h6>
                </div>
            </div>

        </div>
        <div class="card">
            <div class="card-header pb-0 border-bottom">
                <div class="card-title pb-1">Edit Password</div>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Old Password</label>
                    <input type="password" class="form-control" value="password">
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="new_password" class="form-control" value="password">
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <input type="new_password" class="form-control" value="password">
                </div>
            </div>
            <div class="card-footer text-right"><a href="#" class="btn btn-success">Update</a> <a href="#" class="btn btn-danger">Cancel</a></div>
        </div>
    </div>

    <!-- Col -->

    <div class="col-md-7 col-lg-8 col-xl-9">
        <div class="card">
            <div class="card-body">
                <div class="mb-4 main-content-label">Personal Information</div>
                <?php echo form_open_multipart(base_url('auth/update_profile'), array('id' => 'profile', 'class' => 'profile')); ?>
                <div class="form-group ">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Language</label>
                        </div>
                        <input type="hidden" name="staff_id" value="<?php echo $this->session->userdata('user')->staff_id ?>">
                        <input type="hidden" name="user_id" value="<?php echo $this->session->userdata('user')->user_id ?>">
                        <div class="col-md-9">
                            <?php $langs = array('aa' => 'Afar', 'am' => 'Amharic', 'ar' => 'Arabic', 'en' => 'English', 'fr' => 'French', 'ha' => 'Hausa', 'rw' => 'Kinyarwanda', 'ln' => 'Lingala', 'pr' => 'Portuguese', 'sw' => 'Swahili'); ?>
                            <select class="form-control" name="langauge">
                                <?php foreach ($langs as $key => $value) : ?>

                                    <option value='<?php echo $key ?>' <?php if ($key == $this->session->userdata('user')->langauge) {
                                                                            echo 'selected';
                                                                        } ?>><?php echo $value ?></option>

                                <?php endforeach; ?>


                            </select>


                        </div>
                    </div>
                </div>
                <div class="mb-4 main-content-label"></div>

                <div class="form-group ">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">User Name</label>
                        </div>
                        <div class="col-md-9">
                            <input type="text" class="form-control" name="username" placeholder="" value="<?php echo $this->session->userdata('user')->username; ?>" readonly>
                        </div>
                    </div>
                </div>
                <div class="form-group ">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label"> Name</label>
                        </div>
                        <div class="col-md-9">
                            <input type="text" name="name" class="form-control" placeholder="Name" value="<?php echo $this->session->userdata('user')->name; ?>" readonly>
                        </div>
                    </div>
                </div>
                <div class="form-group ">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Email</label>
                        </div>
                        <div class="col-md-9">
                            <input type="text" name="email" class="form-control" placeholder="Email" value="<?php echo $this->session->userdata('user')->email; ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group ">
                    <div class="row">
                        <div class="col-md-3">
                            <i class="bx bx-info-square"></i>
                            <label class="form-label">Role</label>
                        </div>
                        <div class="col-md-9">

                            <span class="badge text-bg-info">
                                <?php echo $this->session->userdata('user')->group_name; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="form-group ">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Profile Image (Image should be less than 1MB)</label>
                        </div>
                        <div class="col-md-9">
                            <input type="file" class="form-control" name="photo" value="<?php echo $this->session->userdata('user')->photo; ?>">

                        </div>
                        <?php if (isset($this->session->userdata('user')->photo)) { ?>
                            <img src="<?php echo base_url() ?>uploads/staff/<?php echo $this->session->userdata('user')->photo; ?>" style="width:180px; height: 150px;">
                        <?php } ?>
                    </div>
                </div>

            </div>
            <div class=" card-footer">
                <button type="submit" class="btn btn-success waves-effect waves-light">Update Profile</button>
            </div>

            </form>
        </div>
    </div>
    <!-- /Col -->
</div>
<!-- /row -->