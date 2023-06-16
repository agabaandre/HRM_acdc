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
<script>
	$(document).ready(function() {
		$('#example').DataTable();
	});
</script>
<script>
	$(document).ready(function() {
		$('#example2').DataTable({
			dom: 'Bfrtip',
			"paging": true,
			"lengthChange": true,
			"searching": true,
			"ordering": true,
			"info": true,
			"autoWidth": false,
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
	// 	var table = $('#example2').DataTable({
	// 		lengthChange: true,
	// 		buttons: ['copy', 'excel', 'csv', 'pdf', 'print']
	// 	});

	// 	table.buttons().container()
	// 		.appendTo('#example2_wrapper .col-md-6:eq(0)');
	// });


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

<script>
	Highcharts.setOptions({
		colors: ['#b4a269', '#28a745', '#6905AD', '#0913AC', '#b4a269', '#a3a3a3']
	});
	// Radialize the colors
	var pieColors = (function() {
		var colors = [],
			base = Highcharts.getOptions().colors[0],
			i;

		for (i = 0; i < 10; i += 1) {
			// Start out with a darkened base color (negative brighten), and end
			// up with a much brighter color
			colors.push(Highcharts.color(base).brighten((i - 3) / 7).get());
		}
		return colors;
	}());

	// Build the chart
	Highcharts.chart('container', {
		chart: {
			plotBackgroundColor: null,
			plotBorderWidth: null,
			plotShadow: false,
			type: 'pie'
		},
		title: {
			text: ''
		},
		tooltip: {
			pointFormat: '{series.name}: <b>{point.percentage:.1f}%</b>'
		},
		accessibility: {
			point: {
				valueSuffix: '%'
			}
		},
		plotOptions: {
			pie: {
				allowPointSelect: true,
				size: '70%',
				cursor: 'pointer',
				dataLabels: {
					enabled: true,
					format: '{point.y:1f}<br><b>{point.name}</b><br>{point.percentage:.1f} %',
					distance: -60,
					filter: {
						property: 'percentage',
						operator: '>',
						value: 4
					},
					style: {
						fontSize: '15px'
					}
				}
			}
		},
		series: [{
			name: 'Percentage',
			data: <?php echo json_encode($data_points, JSON_NUMERIC_CHECK); ?>
		}],

		credits: {
			enabled: false
		}
	});
</script>

<script>
	Highcharts.setOptions({
		colors: ['#b4a269', '#a3a3a3']
	});
	// Radialize the colors
	var pieColors = (function() {
		var colors = [],
			base = Highcharts.getOptions().colors[0],
			i;

		for (i = 0; i < 10; i += 1) {
			// Start out with a darkened base color (negative brighten), and end
			// up with a much brighter color
			colors.push(Highcharts.color(base).brighten((i - 3) / 7).get());
		}
		return colors;
	}());

	// Build the chart
	// Data retrieved from https://netmarketshare.com
</script>
<script>
	Highcharts.setOptions({
		colors: ['#28a745', '#ED561B', '#DDDF00', '#24CBE5', '#64E572', '#FF9655', '#FFF263', '#6AF9C4']
	});

	Highcharts.chart('container3', {
		chart: {
			type: 'column'
		},
		title: {
			text: ''
		},
		subtitle: {
			text: ''
		},
		xAxis: {
			categories: <?php echo json_encode($staff_by_contract['contract_type'], JSON_NUMERIC_CHECK); ?>

				,
			crosshair: true
		},
		yAxis: {
			min: 0,
			title: {
				text: 'Total Staff'
			}
		},

		plotOptions: {
			column: {
				dataLabels: {
					enabled: true
				},
				pointPadding: 0.2,
				borderWidth: 0
			},

		},
		series: [{
			name: 'Contract Types',
			data: <?php echo json_encode($staff_by_contract['value'], JSON_NUMERIC_CHECK); ?>


		}],
		credits: {
			enabled: false
		}
	});
</script>


<script>
	Highcharts.setOptions({
		colors: ['#28a745', '#ED561B', '#DDDF00', '#24CBE5', '#64E572', '#FF9655', '#FFF263', '#6AF9C4']
	});

	Highcharts.chart('container4', {
		chart: {
			type: 'column'
		},
		title: {
			text: ''
		},
		subtitle: {
			text: ''
		},
		xAxis: {
			categories: <?php echo json_encode($staff_by_division['division'], JSON_NUMERIC_CHECK); ?>

				,
			crosshair: true
		},
		yAxis: {
			min: 0,
			title: {
				text: 'Total Staff'
			}
		},

		plotOptions: {
			column: {
				dataLabels: {
					enabled: true
				},
				pointPadding: 0,
				borderWidth: 0
			},

		},
		series: [{
			name: 'Divisions',
			data: <?php echo json_encode($staff_by_division['value'], JSON_NUMERIC_CHECK); ?>


		}],
		credits: {
			enabled: false
		}
	});
</script>

<script>
	Highcharts.setOptions({
		colors: ['#28a745', '#ED561B', '#DDDF00', '#24CBE5', '#64E572', '#FF9655', '#FFF263', '#6AF9C4']
	});

	Highcharts.chart('container5', {
		chart: {
			type: 'column'
		},
		title: {
			text: ''
		},
		subtitle: {
			text: ''
		},
		xAxis: {
			categories: <?php echo json_encode($staff_by_member_state['member_states'], JSON_NUMERIC_CHECK); ?>

				,
			crosshair: true
		},
		yAxis: {
			min: 0,
			title: {
				text: 'Total Staff'
			}
		},

		plotOptions: {
			column: {
				dataLabels: {
					enabled: true
				},
				pointPadding: 0.2,
				borderWidth: 0
			},

		},
		series: [{
			name: 'Member States',
			data: <?php echo json_encode($staff_by_member_state['value'], JSON_NUMERIC_CHECK); ?>


		}],
		credits: {
			enabled: false
		}
	});
</script>
</body>

</html>