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
<script src="<?php echo base_url() ?>assets/js/jquery.min.js"></script>
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
<script src="<?php echo base_url() ?>assets/js/app.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/smart-wizard/js/jquery.smartWizard.min.js"></script>
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

	$('.select2').select2({
		dropdownParent: $('#renew_contract')
	});


	$('.multiple-select').select2({
		theme: 'bootstrap4',
		width: $(this).data('width') ? $(this).data('width') : $(this).hasClass('w-100') ? '100%' : 'style',
		placeholder: $(this).data('placeholder'),
		allowClear: Boolean($(this).data('allow-clear')),

	});
</script>
<script>
	$(document).ready(function() {
		$('.mydata').DataTable({
			dom: 'Bfrtip',
			"paging": true,
			"lengthChange": true,
			"searching": true,
			"ordering": true,
			"info": true,
			"autoWidth": true,
			"responsive": true,
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
		// Smart Wizard
		$('#smartwizard').smartWizard({
			selected: 0,
			theme: 'arrows',
			transition: {
				animation: 'slide-horizontal', // Effect on navigation, none/fade/slide-horizontal/slide-vertical/slide-swing
			},
			toolbarSettings: {
				toolbarPosition: 'both', // both bottom

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
</script>




</body>

</html>