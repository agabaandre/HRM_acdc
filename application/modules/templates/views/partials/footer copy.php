<footer class="page-footer">
	<p class="mb-0">Copyright &copy; <a href="https://africacdc.org/" target="_blank">Africa CDC</a>
		<?php echo date("Y"); ?> All right reserved.
	</p>
</footer>

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
<!--plugins-->
<script src="<?php echo base_url() ?>assets/js/jquery.min.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/metismenu/js/metisMenu.min.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>

<!-- DataTables  & Plugins -->

<script src="<?php echo base_url(); ?>assets/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="<?php echo base_url(); ?>assets/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
<script src="<?php echo base_url(); ?>assets/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="<?php echo base_url(); ?>assets/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="<?php echo base_url(); ?>assets/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="<?php echo base_url(); ?>assets/plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="<?php echo base_url(); ?>assets/plugins/datatables-buttons/js/buttons.colVis.min.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/fullcalendar/js/main.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>

<script>
	$(document).ready(function() {
		$('#example').DataTable();
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
<script>
	$(document).ready(function() {
		var table = $('#example3').DataTable({
			lengthChange: true,
			buttons: ['copy', 'excel', 'csv', 'pdf', 'print']
		});

		table.buttons().container()
			.appendTo('#example3_wrapper .col-md-6:eq(0)');
	});

	$(document).ready(function() {
		var table = $('#example4').DataTable({
			lengthChange: true,
			buttons: ['copy', 'excel', 'csv', 'pdf', 'print']
		});

		table.buttons().container()
			.appendTo('#example4_wrapper .col-md-6:eq(0)');
	});

	$(document).ready(function() {
		var table = $('#example5').DataTable({
			lengthChange: true,
			buttons: ['copy', 'excel', 'csv', 'pdf', 'print']
		});

		table.buttons().container()
			.appendTo('#example5_wrapper .col-md-6:eq(0)');
	});
</script>
<!--app JS-->
<script src="<?php echo base_url() ?>assets/js/app.js"></script>

</body>

</html>