<div class="card">
    <div class="row">
        <div class="col col-md-4"></div>

        <div class="col col-md-4">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changepasswordModalLabel">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="changed" style="color:#005662;"></p>
                    <?php echo form_open_multipart(base_url('auth/change_pass'), array('id' => 'changpass', 'class' => 'user_form')); ?>

                    <div class="form-group">
                        <label for="old">Old Password</label>
                        <input type="password" class="form-control" name="oldpass" id="old">
                    </div>
                    <div class="form-group">
                        <label for="new">New Password</label>
                        <input type="password" class="form-control" name="newpass" id="new" onkeyup="checker();" required>
                        <p class="help-block error"></p>
                    </div>
                    <div class="form-group">
                        <label for="confirm">Confirm New Password</label>
                        <input type="hidden" value='1' name="changed">
                        <input type="hidden" value='<?php echo $this->session->userdata('user_id'); ?>' name="user_id">
                        <input type="password" class="form-control" name="confirm" id="confirm" onkeyup="checker();" required>
                        <p class="help-block error"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
                </form>
            </div>
        </div>
        <div class="col col-md-4"></div>

    </div>
</div>