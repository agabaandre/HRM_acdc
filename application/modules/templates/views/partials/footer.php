</div>
</div>
</div>
</div>
<!--end page wrapper -->
<!--start overlay-->
<div class="overlay toggle-icon"></div>
<!--end overlay-->
<!--Start Back To Top Button--> <a href="javaScript:;" class="back-to-top"><i class='bx bxs-up-arrow-alt'></i></a>
<!--End Back To Top Button-->
<footer class="page-footer">
	<p class="mb-0">Copyright © 2021. All right reserved.</p>
</footer>
</div>
<!--end wrapper-->
<!--start switcher-->
<div class="switcher-wrapper">
	<div class="switcher-btn"> <i class='bx bx-cog bx-spin'></i>
	</div>
	<div class="switcher-body">
		<div class="d-flex align-items-center">
			<h5 class="mb-0 text-uppercase">Theme Customizer</h5>
			<button type="button" class="btn-close ms-auto close-switcher" aria-label="Close"></button>
		</div>
		<hr />
		<h6 class="mb-0">Theme Styles</h6>
		<hr />
		<div class="d-flex align-items-center justify-content-between">
			<div class="form-check">
				<input class="form-check-input" type="radio" name="flexRadioDefault" id="lightmode" checked>
				<label class="form-check-label" for="lightmode">Light</label>
			</div>
			<div class="form-check">
				<input class="form-check-input" type="radio" name="flexRadioDefault" id="darkmode">
				<label class="form-check-label" for="darkmode">Dark</label>
			</div>
			<div class="form-check">
				<input class="form-check-input" type="radio" name="flexRadioDefault" id="semidark">
				<label class="form-check-label" for="semidark">Semi Dark</label>
			</div>
		</div>
		<hr />
		<div class="form-check">
			<input class="form-check-input" type="radio" id="minimaltheme" name="flexRadioDefault">
			<label class="form-check-label" for="minimaltheme">Minimal Theme</label>
		</div>
		<hr />
		<h6 class="mb-0">Header Colors</h6>
		<hr />
		<div class="header-colors-indigators">
			<div class="row row-cols-auto g-3">
				<div class="col">
					<div class="indigator headercolor1" id="headercolor1"></div>
				</div>
				<div class="col">
					<div class="indigator headercolor2" id="headercolor2"></div>
				</div>
				<div class="col">
					<div class="indigator headercolor3" id="headercolor3"></div>
				</div>
				<div class="col">
					<div class="indigator headercolor4" id="headercolor4"></div>
				</div>
				<div class="col">
					<div class="indigator headercolor5" id="headercolor5"></div>
				</div>
				<div class="col">
					<div class="indigator headercolor6" id="headercolor6"></div>
				</div>
				<div class="col">
					<div class="indigator headercolor7" id="headercolor7"></div>
				</div>
				<div class="col">
					<div class="indigator headercolor8" id="headercolor8"></div>
				</div>
			</div>
		</div>
	</div>
</div>
<!--end switcher-->
<!-- Bootstrap JS -->

<script src="<?php echo base_url() ?>assets/js/bootstrap.bundle.min.js"></script>
  <!-- jQuery UI Library -->
  <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/notifications/js/lobibox.min.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/notifications/js/notifications.min.js"></script>
<script src="<?php echo base_url() ?>assets/js/pace.min.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/notifications/js/notification-custom-script.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/datetimepicker/js/legacy.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/datetimepicker/js/picker.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/datetimepicker/js/picker.time.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/datetimepicker/js/picker.date.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/bootstrap-material-datetimepicker/js/moment.min.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/bootstrap-material-datetimepicker/js/bootstrap-material-datetimepicker.min.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/select2/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
<script src="<?php echo base_url() ?>assets/js/app.js">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
</script>
<script src="<?php echo base_url() ?>assets/plugins/smart-wizard/js/jquery.smartWizard.min.js"></script>

  <!-- FullCalendar & Bootstrap JS Bundle -->

<script>
	$(document).ready(function() {

		var message = "<?php echo $this->session->tempdata('msg'); ?>";
		var msgtype = "<?php echo $this->session->tempdata('type'); ?>";
		if (msgtype !== '') {
			show_notification(message, msgtype);
		}
		<?php
		$_SESSION['type'] = '';
		$_SESSION['msg'] = '';
		?>

	});
</script>
<script>
	function show_notification(message, msgtype) {
		Lobibox.notify(msgtype, {
			pauseDelayOnHover: true,
			continueDelayOnInactiveTab: false,
			position: 'top right',
			icon: 'bx bx-check-circle',
			msg: message
		});
	}
</script>
<script>
	$('.select2').select2({
		theme: 'bootstrap4',
		width: $(this).data('width') ? $(this).data('width') : $(this).hasClass('w-100') ? '100%' : 'style',
		placeholder: $(this).data('placeholder'),
		allowClear: Boolean($(this).data('allow-clear')),
	});

	// $('.select2').select2({
	// 	dropdownParent: $('#renew_contract')
	// });


	$('.multiple-select').select2({
		theme: 'bootstrap4',
		multiple: true,
		width: $(this).data('width') ? $(this).data('width') : $(this).hasClass('w-100') ? '100%' : 'style',
		placeholder: $(this).data('placeholder'),
		allowClear: Boolean($(this).data('allow-clear')),

	});
</script>
<script>
	$(document).ready(function() {
		$(function() {
			var priorities = ["Low", "Medium", "High"];
			$("#edit_priority").autocomplete({
			source: priorities
			});
		});

		// $(".datepicker").datepicker({
		// 	dateFormat: 'yy-mm-dd',
		// 	changeMonth: true,
		// 	changeYear: true
		// });

		$('.mydata').DataTable({
			dom: 'Bfrtip',
			"paging": true,
			"lengthChange": true,
			"searching": true,
			"ordering": true,
			"info": true,
			"autoWidth": true,
			// "responsive": true,
			lengthMenu: [
				[25, 50, 100, 150, -1],
				['25', '50', '100', '150', '200', 'Show all']
			],
			buttons: [
				'copyHtml5',
				'excelHtml5',
				'csvHtml5',
				'pdfHtml5',
				'pageLength',
			]
		});
	});
</script>

<script type="text/javascript">
	//pos1_success_noti();
</script>
<script>
	//change Password
	function checker() {
		$first = $('#new').val();
		$confirm = $('#confirm').val();
		if (($first !== $confirm) && $first !== "") {
			$('.error').html('<font color="red">Passwords Do not Match</font>');
		} else {
			$('.error').html('<font color="green">Passwords Match</font>');
		}
	} //checker
	$('#change_pass').submit(function(e) {
		e.preventDefault();
		var data = $(this).serialize();
		var url = '<?php echo base_url() ?>/auth/changePass'
		console.log(data);
		$.ajax({
			url: url,
			method: "post",
			data: data,
			success: function(res) {
				if (res == "OK") {
					$('.changed').html("<center><font color='green'>Password change effective</font></center>");
				} else {
					$('.changed').html("<center>" + res + "</center>");
				}
				console.log(res);
			} //success
		}); // ajax
	}); //form submit
</script>
<!-- date picker on employee form -->
<script>
	$('.datepicker').pickadate({
			selectMonths: true,
			selectYears: true
		}),
		$('.timepicker').pickatime()
</script>
<script>
	$('.staffdatepicker').pickadate({
			selectMonths: true,
			selectYears: true
		}),
		$('.stafftimepicker').pickatime()
</script>
<script>
	$(function() {
		$('.date-time').bootstrapMaterialDatePicker({
			format: 'YYYY-MM-DD HH:mm'
		});
		$('.date').bootstrapMaterialDatePicker({
			time: false
		});
		$('.time').bootstrapMaterialDatePicker({
			date: false,
			format: 'HH:mm'
		});
	});
</script>
<script>
	$(document).ready(function() {
		// Toolbar extra buttons
		var btnFinish = $('<button></button>').text('Finish').addClass('btn btn-info').on('click', function() {
			alert('Finish Clicked');
		});
		var btnCancel = $('<button></button>').text('Cancel').addClass('btn btn-danger').on('click', function() {
			$('#smartwizard').smartWizard("reset");
		});


		// Smart Wizard
		$('#smartwizard').smartWizard({
			autoAdjustHeight: false,
			selected: 0,
			theme: 'arrows',
			toolbarSettings: {
				toolbarPosition: 'both', // both bottom
			},

		});

		// Step show event
		$("#smartwizard").on("showStep", function(e, anchorObject, stepNumber, stepDirection, stepPosition) {
			$("#prev-btn").removeClass('disabled');
			$("#next-btn").removeClass('disabled');
			if (stepPosition === 'first') {
				$("#prev-btn").addClass('disabled');
			} else if (stepPosition === 'last') {
				$("#next-btn").addClass('disabled');
			} else {
				$("#prev-btn").removeClass('disabled');
				$("#next-btn").removeClass('disabled');
			}
		});
		// External Button Events
		$("#reset-btn").on("click", function() {
			// Reset wizard
			$('#smartwizard').smartWizard("reset");
			return true;
		});
		$("#prev-btn").on("click", function() {
			// Navigate previous
			$('#smartwizard').smartWizard("prev");
			return true;
		});
		$("#next-btn").on("click", function() {
			// Navigate next
			$('#smartwizard').smartWizard("next");
			return true;
		});
		// Demo Button Events
		$("#got_to_step").on("change", function() {
			// Go to step
			var step_index = $(this).val() - 1;
			$('#smartwizard').smartWizard("goToStep", step_index);
			return true;
		});
		$("#is_justified").on("click", function() {
			// Change Justify
			var options = {
				justified: $(this).prop("checked")
			};
			$('#smartwizard').smartWizard("setOptions", options);
			return true;
		});
		$("#animation").on("change", function() {
			// Change theme
			var options = {
				transition: {
					animation: $(this).val()
				},
			};
			$('#smartwizard').smartWizard("setOptions", options);
			return true;
		});
		$("#theme_selector").on("change", function() {
			// Change theme
			var options = {
				theme: $(this).val()
			};
			$('#smartwizard').smartWizard("setOptions", options);
			return true;
		});
	});

	var objectiveCounter = 0;

	function addObjective() {
		if (objectiveCounter < 5) {
			var objectiveSection = document.getElementById('step-2');
			var objectiveDiv = document.createElement('div');
			objectiveDiv.innerHTML = `<div class="obj${objectiveCounter}">
      <div class="mb-3">
         <label for="objective${objectiveCounter}" class="form-label"><h4>Objective ${objectiveCounter+1}</h4></label>
         <input type="text" id="objective${objectiveCounter}" name="objective[${objectiveCounter}][]" class="form-control" required>
      </div>
	 <div class="mb-3">
              <table class="table table-striped mt-4" id="activityTable${objectiveCounter}">
                <thead>
                  <tr>
                    <th scope="col">
                      <h6>Activities</h6>
                    </th>
                  </tr>
                </thead>
                <tbody id='activities${objectiveCounter}'>
				<td><input type="text" class="form-control" id="activityName${objectiveCounter}" name="activityName[${objectiveCounter}][]" autocomplete="off" required objective="${objectiveCounter}"></td></tr><tr>
				<td><input type="text" class="form-control" id="activityName${objectiveCounter}" name="activityName[${objectiveCounter}][]" autocomplete="off"  objective="${objectiveCounter}"></td></tr><tr>
				<td><input type="text" class="form-control" id="activityName${objectiveCounter}" name="activityName[${objectiveCounter}][]" autocomplete="off"  objective="${objectiveCounter}"></td></tr><tr>
				<td><input type="text" class="form-control" id="activityName${objectiveCounter}" name="activityName[${objectiveCounter}][]" autocomplete="off"  objective="${objectiveCounter}"></td></tr><tr>
				<td><input type="text" class="form-control" id="activityName${objectiveCounter}" name="activityName[${objectiveCounter}][]" autocomplete="off"  objective="${objectiveCounter}"></td></tr><tr>
            
                </tbody>
              </table>
      </div>
      <div class="col-md-12 mb-4 row">
	  <label for="timeline_start${objectiveCounter}" class="form-label">Time Line</label>
	    <div class="col-md-4">
		         
         <label for="timeline_start${objectiveCounter}" class="form-label">Start Date</label>
        <input type="date" id="timeline_start${objectiveCounter}" name="timeline_start[${objectiveCounter}][]" class="form-control" max="<?= date('Y') . '-12-31'; ?>" min="<?= date('Y') . '-01-01'; ?>" style="width:200px !important;" required>
		</div>
		<div class="col-md-4">
         <label for="timeline_end${objectiveCounter}" class="form-label">End Date</label>
        <input type="date" id="timeline_end${objectiveCounter}" name="timeline_end[${objectiveCounter}][]" class="form-control" max="<?= date('Y') . '-12-31'; ?>" min="<?= date('Y') . '-01-01'; ?>" style="width:200px !important;"  required>
        </div>
		<div class="col-md-4">
		</div>
	 </div>
      <div class="mb-3">
              <table class="table table-striped mt-4" id="kpiTable">
                <thead>
                  <tr>
                    <th scope="col">
                      <h6>Deliverables and KPIs</h6>
                    </th>
                  </tr>
                </thead>
                <tbody id='kpitable${objectiveCounter}'>
				    <tr><td><input type="text" class="form-control" id="kpiName${objectiveCounter}" name="kpiName[${objectiveCounter}][]" autocomplete="off" required objective="${objectiveCounter}"></td></tr><tr>
					<td><input type="text" class="form-control" id="kpiName${objectiveCounter}" autocomplete="off" name="kpiName[${objectiveCounter}][]"  objective="${objectiveCounter}"></td></tr><tr>
					<td><input type="text" class="form-control" id="kpiName${objectiveCounter}" autocomplete="off" name="kpiName[${objectiveCounter}][]"  objective="${objectiveCounter}"></td></tr><tr>
					<td><input type="text" class="form-control" id="kpiName${objectiveCounter}" autocomplete="off" name="kpiName[${objectiveCounter}][]"  objective="${objectiveCounter}"></td></tr><tr>
					<td><input type="text" class="form-control" id="kpiName${objectiveCounter}" autocomplete="off" name="kpiName[${objectiveCounter}][]"  objective="${objectiveCounter}"></td></tr><tr>
                </tbody>
              </table>
		</div>
      <div class="mb-3">
         <label for="weight${objectiveCounter}" class="form-label">Weight</label>
         <input type="number" maxlength="2" id="weight${objectiveCounter}" name="weight[${objectiveCounter}][]" class="form-control" max="100" min="0">
      </div>
	      <div class="mt-4">
            <button class="btn btn-primary" title ="Add Objective" onclick="addObjective()">+</button>
            <button class="btn btn-danger" title ="Delete Objective" onclick="removeObjective(${objectiveCounter})">-</button>
     </div>
<hr style="border:4px dotted #f62718;"></div>`;
			$('.new-objectives').append(objectiveDiv);
			objectiveCounter++;
		} else {
			show_notification('Maximum Number of Allowed objecives is 5', 'error');
		}
	}


	$(document).ready(function() {
		addObjective(); // Add first objective
	});
</script>

<script>
	function removeObjective(objId) {
		$('.obj' + objId).remove();
		objectiveCounter--;
	}
</script>

<script>
	// Function to calculate requested_days
	function calculateRequestedDays() {
		var startDate = new Date($("#start_date").val());
		var endDate = new Date($("#end_date").val());
		var timeDiff = endDate.getTime() - startDate.getTime();
		var daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24));

		// Update the requested_days field
		$("#requested_days").val(daysDiff);
	}

	// Event listener for start_date and end_date change
	$("#start_date, #end_date").on("change", calculateRequestedDays);
</script>

<script>
	$(document).ready(function() {
		const trainingRecommendedCheckbox = $('#training_recommended');
		const requiredTrainingsSection = $('.required_trainings');

		// Initially hide the required_trainings section
		requiredTrainingsSection.hide();

		// Add an event listener to the checkbox to show/hide the section
		trainingRecommendedCheckbox.on('change', function() {
			if (trainingRecommendedCheckbox.is(':checked')) {
				requiredTrainingsSection.show();
			} else {
				requiredTrainingsSection.hide();
			}
		});
	});


</script>

<script>
    // Initialize Summernote on the textarea
    $(document).ready(function() {
      $('.summernote').summernote({
        placeholder: 'Type here.................',
        tabsize: 2,
        height: 250,
        toolbar: [
          // customize the toolbar as needed
          ['style', ['style']],
          ['font', ['bold', 'italic', 'underline', 'clear']],
          ['fontname', ['fontname']],
          ['color', ['color']],
          ['para', ['ul', 'ol', 'paragraph']],
          ['insert', ['link', 'picture', 'video']],
          ['view', ['fullscreen', 'codeview', 'help']]
        ],
		// Remove default image upload (base64) behavior
        callbacks: {
          onImageUpload: function(files) {
            for (var i = 0; i < files.length; i++) {
              uploadImage(files[i]);
            }
          }
        }
      });
    });



function uploadImage(file) {
  var data = new FormData();
  data.append("file", file);
  
  // Append CSRF token
  var csrfName = "<?= $this->security->get_csrf_token_name(); ?>";
  var csrfHash = "<?= $this->security->get_csrf_hash(); ?>";
  data.append(csrfName, csrfHash);

  $.ajax({
    url: '<?php echo base_url()?>upload/image_upload', // Replace with your server-side upload script
    type: 'POST',
    data: data,
    cache: false,
    contentType: false,
    processData: false,
    success: function(response) {
      // Assuming your server returns a JSON object with the image URL
      var imageUrl = response.url || response;
      $('#summernote').summernote('insertImage', imageUrl);
    },
    error: function(jqXHR, textStatus, errorThrown) {
      console.error("Image upload failed: " + textStatus + " " + errorThrown);
    }
  });
}

  </script>
	

  






</body>

</html>
