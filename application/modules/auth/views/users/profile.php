<!-- Col -->
<?php
$staff_id = $this->session->userdata('user')->staff_id;
$contract = Modules::run('auth/contract_info', $staff_id);

?>
<div class="row">

    <div class="col-md-5 col-lg-4 col-xl-3 col-xs-12 col-md-pull-2">

        <div class="email-header d-xl-flex align-items-center">
            <div class="d-flex align-items-center text-muted">
                
                    <!-- <div class="">
                    
                        <a href="<?php echo base_url() ?>leave/request" type="button" class="btn btn-grey ms-2">Leave Request<i class=""></i>
                        </a>
                    </div>
                    <div class="">

                        <a href="<?php echo base_url() ?>leave/approve_leave" type="button" class="btn btn-grey ms-2">Approve Leave<i class="fa fa-ok"></i>
                        </a>
                    </div>
                    <div class="">
                        <a href="<?php echo base_url() ?>leave/status" type="button" class="btn btn-outline accordionbtn-grey ms-2">Leave Status<i class=""></i>
                        </a>
                    </div>
                    <div class="">
                        <a href="<?php echo base_url() ?>performance" type="button" class="btn btn-outline btn-grey ms-2">Submit Plan<i class=""></i>
                        </a>
                    </div>
                    <div class="">
                        <a href="<?php echo base_url() ?>performance" type="button" class="btn btn-outline btn-grey ms-2">My Plans<i class=""></i>
                        </a>
                    </div>
                    <div class="">
                        <a href="<?php echo base_url() ?>performance/approve" type="button" class="btn btn-outline btn-grey ms-2">Approvals<i class="fa fa-ok"></i>
                        </a>
                    </div> -->
                </div>
           
            <!-- <div class="flex-grow-1 mx-xl-2 my-2 my-xl-0">

            </div>
            <div class="ms-auto d-flex align-items-center">

            </div> -->
        </div>
        <div class="card box-widget widget-user">
            <div class="widget-user-header testbgpattern1"></div>
    
            <div class="card-body text-center">
                <br>
                <div class="item-user pro-user">
                    <h4 class="pro-user-username tx-15 pt-2 mt-4 mb-1" style="color:black;"><?php echo $this->session->userdata('user')->name; ?></h4>
                    <h6><?php

                       
                        echo @$contract->job_name;
                        ?>
                    </h6>
                    <p class="pro-user-desc tx-13 mb-3 font-weight-normal badge text-bg-success" style="color:white !important;"><?php echo $this->session->userdata('user')->group_name; ?></p>
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
                    <input type="password" class="form-control" value="">
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="new_password" class="form-control" value="">
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <input type="new_password" class="form-control" value="">
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
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Language</label>
                        </div>
                        <input type="hidden" name="staff_id" value="<?php echo $staff_id = $this->session->userdata('user')->staff_id ?>">
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

                <div class="form-group">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Contract Start Date</label>
                        </div>
                        <div class="col-md-9">
                            <input type="text" class="form-control" name="contract_start_date" value="<?php echo @$contract->start_date; ?>" readonly>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Contract End Date</label>
                        </div>
                        <div class="col-md-9">
                            <input type="text" class="form-control" name="contract_end_date" value="<?php echo @$contract->end_date; ?>" readonly>
                        </div>
                    </div>
                </div>
                <!-- Add more contact fields as needed -->

             

                <div class="form-group">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Name</label>
                        </div>
                        <div class="col-md-9">
                            <input type="text" name="name" class="form-control" placeholder="Name" value="<?php echo @$name = $this->session->userdata('user')->name; ?>" disabled>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Email</label>
                        </div>
                        <div class="col-md-9">
                            <input type="text" name="work_email" class="form-control" placeholder="Email" value="<?php echo @$this->session->userdata('user')->email; ?>">
                        </div>
                    </div>
                </div>
                <?php //echo dd($contract); 
                ?>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Primary Number</label>
                        </div>
                        <div class="col-md-9">
                            <input type="text" class="form-control" name="tel_1" value="<?php echo @$contract->tel_1; ?>">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Alternative Number</label>
                        </div>
                        <div class="col-md-9">
                            <input type="text" class="form-control" name="tel_2" value="<?php echo @$contract->tel_2; ?>">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">WhatsApp Number</label>
                        </div>
                        <div class="col-md-9">
                            <input type="text" class="form-control" name="whatsapp" value="<?php echo @$contract->whatsapp; ?>">
                        </div>
                    </div>
                </div>


                <div class="form-group">
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
                <div class="form-group">
    <div class="row">
        <div class="col-md-3">
            <label class="form-label">Profile Image (Image should be less than 1MB)</label>
        </div>
        <div class="col-md-9">
            <input type="file" class="form-control" name="photo" value="<?php echo @$this->session->userdata('user')->photo; ?>">
        </div>
        <?php
        $image_path = base_url() . 'uploads/staff/' . @$this->session->userdata('user')->photo;
        $photo = $this->session->userdata('user')->photo;
        echo  $staff_photo = generate_user_avatar( $name,$name, $image_path,$photo);
       
        ?>
    </div>
</div>
<div class="form-group">
    <div class="row">
        <div class="col-md-3">
            <label class="form-label">Employee Signature (Image should be less than 1MB)</label>
        </div>
        <div class="col-md-9">
            <input type="file" class="form-control" name="signature" value="<?php echo @$this->session->userdata('user')->signature; ?>">
        </div>
        <?php
        $signatureImagePath = base_url() . 'uploads/staff/signature/' . @$this->session->userdata('user')->signature;
        $placeholderSignatureImage = base_url() . 'uploads/staff/signature.png';
        if (!empty($this->session->userdata('user')->signature) && file_exists(FCPATH . 'uploads/staff/signature/' . $this->session->userdata('user')->signature)) {
            echo '<img src="' . $signatureImagePath . '" style="width:100px; height: 80px;">';
        } else {
            echo '<img src="' . $placeholderSignatureImage . '" style="width:100px; height: 80px;">';
        }
        ?>
    </div>
</div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-success waves-effect waves-light">Update Profile</button>
            </div>
            </form>
        </div>
    </div>
    <!-- /Col -->
</div>
<!-- /row -->