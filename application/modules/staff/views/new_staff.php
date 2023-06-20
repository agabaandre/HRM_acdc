 <div class="container">
     <div class="row">
         <div class="col-md-6">
             <?php echo validation_errors(); ?>
             <?php echo form_open('staff/new'); ?>

             <h4>Staff Information</h4>

             <div class="form-group">
                 <label for="SAPNO">SAP Number:</label>
                 <input type="text" class="form-control" name="SAPNO" id="SAPNO" required>
             </div>

             <div class="form-group">
                 <label for="title">Title:</label>
                 <input type="text" class="form-control" name="title" id="title" required>
             </div>

             <div class="form-group">
                 <label for="fname">First Name:</label>
                 <input type="text" class="form-control" name="fname" id="fname" required>
             </div>

             <div class="form-group">
                 <label for="lname">Last Name:</label>
                 <input type="text" class="form-control" name="lname" id="lname" required>
             </div>

             <div class="form-group">
                 <label for="oname">Other Name:</label>
                 <input type="text" class="form-control" name="oname" id="oname" required>
             </div>

             <div class="form-group">
                 <label for="date_of_birth">Date of Birth:</label>
                 <input type="text" class="form-control datepicker" name="date_of_birth" id="date_of_birth" required>
             </div>

             <div class="form-group">
                 <label for="gender">Gender:</label>
                 <select class="form-control" name="gender" id="gender" required>
                     <option value="Male">Male</option>
                     <option value="Female">Female</option>
                     <option value="Other">Other</option>
                 </select>
             </div>
         </div>

         <div class="col-md-6">
             <br>
             <div class="form-group" style="margin-top:15px;">
                 <label for="nationality_id">Nationality:</label>
                 <select class="form-control" name="nationality_id" id="nationality_id" required>
                     <option value="">Select Nationality</option>
                     <option value="1">Nationality 1</option>
                     <option value="2">Nationality 2</option>
                     <option value="3">Nationality 3</option>
                     <!-- Add more options as needed -->
                 </select>
             </div>

             <div class="form-group">
                 <label for="initiation_date">Initiation Date:</label>
                 <input type="text" class="form-control datepicker" name="initiation_date" id="initiation_date" required>
             </div>

             <div class="form-group">
                 <label for="tel_1">Telephone 1:</label>
                 <input type="text" class="form-control" name="tel_1" id="tel_1" required>
             </div>

             <div class="form-group">
                 <label for="tel_2">Telephone 2:</label>
                 <input type="text" class="form-control" name="tel_2" id="tel_2" required>
             </div>

             <div class="form-group">
                 <label for="whatsapp">WhatsApp:</label>
                 <input type="text" class="form-control" name="whatsapp" id="whatsapp" required>
             </div>

             <div class="form-group">
                 <label for="work_email">Work Email:</label>
                 <input type="email" class="form-control" name="work_email" id="work_email" required>
             </div>

             <div class="form-group">
                 <label for="private_email">Private Email:</label>
                 <input type="email" class="form-control" name="private_email" id="private_email" required>
             </div>

             <div class="form-group">
                 <label for="physical_location">Physical Location:</label>
                 <textarea class="form-control" name="physical_location" id="physical_location" rows="2" required></textarea>
             </div>

             <div class="form-group">
                 <input type="submit" class="btn btn-primary" name="submit" value="Submit">
             </div>
             <?php echo form_close(); ?>
         </div>
     </div>
 </div>