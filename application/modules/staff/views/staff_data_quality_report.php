<?php $this->load->view('staff_tab_menu'); ?>

<div class="card">
	<div class="card-body">
		<form id="staff_data_quality_form" method="get">
			<div class="card shadow-sm p-3 mb-4 border rounded" style="background-color: #f9f9f9;">
				<div class="row g-3 align-items-end">
					<div class="col-md-4">
						<label for="staff_name" class="form-label fw-bold">Staff Name</label>
						<input type="text" id="staff_name" name="staff_name" class="form-control" placeholder="Enter staff name (min 3 chars)">
					</div>
					<div class="col-md-4">
						<label for="alert" class="form-label fw-bold">Alert Type</label>
						<select id="alert" name="alert" class="form-control">
							<option value="">All Alerts</option>
							<option value="supervisor_separated">Supervisor separated</option>
							<option value="invalid_age">DOB/Age out of range</option>
							<option value="no_gender">No gender</option>
							<option value="email_location">No Africa CDC email + no physical location</option>
						</select>
					</div>
					<div class="col-md-4">
						<div class="small text-muted">
							Shows current staff (latest contract status: Active or Due) with one or more data quality alerts.
						</div>
					</div>
				</div>
			</div>
		</form>

		<div class="row mb-3 align-items-center">
			<div class="col-md-4">
				<div id="paginationLinksTopDq" class="d-flex align-items-center flex-wrap"></div>
			</div>
			<div class="col-md-4 text-center">
				<div class="d-flex align-items-center justify-content-center gap-2">
					<label for="recordsPerPageDq" class="mb-0 fw-semibold">Records per page:</label>
					<select id="recordsPerPageDq" class="form-select form-select-sm" style="width: auto;">
						<option value="20" selected>20</option>
						<option value="50">50</option>
						<option value="75">75</option>
						<option value="100">100</option>
					</select>
				</div>
			</div>
			<div class="col-md-4 text-end">
				<div id="exportButtonsTopDq" class="d-flex gap-2 justify-content-end">
					<a id="dqExportExcel" href="<?= base_url('staff/staff_data_quality_report/1') ?>" class="btn btn-sm btn-outline-primary">
						<i class="fa fa-file-csv me-1"></i> Export Excel
					</a>
					<a id="dqExportPdf" href="<?= base_url('staff/staff_data_quality_report/0/1') ?>" class="btn btn-sm btn-outline-danger">
						<i class="fa fa-file-pdf me-1"></i> Export PDF
					</a>
				</div>
			</div>
		</div>

		<div class="table-responsive">
			<table class="table table-striped table-bordered">
				<thead>
					<tr>
						<th style="width: 70px;">#</th>
						<th style="width: 90px;">Profile</th>
						<th style="width: 320px;">Name of Staff</th>
						<th>Data Quality Alerts</th>
					</tr>
				</thead>
				<tbody id="staffDataQualityBody">
					<tr>
						<td colspan="4" class="text-center">
							<div class="spinner-border text-primary" role="status">
								<span class="visually-hidden">Loading...</span>
							</div>
							<p class="mt-2 mb-0">Loading report...</p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<div id="paginationInfoDq" class="mt-3 text-end">
			<div id="paginationLinksDq" class="mt-2"></div>
		</div>
	</div>
</div>

<script>
(function () {
	var currentPage = 0;
	var currentPerPage = 20;
	var debounceTimer = null;

	function collectFilters() {
		var staffName = ($('#staff_name').val() || '').trim();
		var alertType = $('#alert').val() || '';
		var filters = {};

		if (staffName.length >= 3 || staffName.length === 0) {
			if (staffName.length >= 3) {
				filters.staff_name = staffName;
			}
		}
		if (alertType) {
			filters.alert = alertType;
		}
		return filters;
	}

	function updateExportLinks() {
		var filters = collectFilters();
		var qs = $.param(filters);
		var excelHref = '<?= base_url('staff/staff_data_quality_report/1') ?>' + (qs ? ('?' + qs) : '');
		var pdfHref = '<?= base_url('staff/staff_data_quality_report/0/1') ?>' + (qs ? ('?' + qs) : '');
		$('#dqExportExcel').attr('href', excelHref);
		$('#dqExportPdf').attr('href', pdfHref);
	}

	function generatePagination(total, page, perPage, records) {
		var totalPages = Math.ceil(total / perPage) || 1;
		var html = '<nav><ul class="pagination pagination-sm mb-0">';

		if (page > 0) {
			html += '<li class="page-item"><a class="page-link" href="#" data-page-dq="' + (page - 1) + '">Previous</a></li>';
		} else {
			html += '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
		}

		var startPage = Math.max(0, page - 2);
		var endPage = Math.min(totalPages - 1, page + 2);
		if (startPage > 0) {
			html += '<li class="page-item"><a class="page-link" href="#" data-page-dq="0">1</a></li>';
			if (startPage > 1) {
				html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
			}
		}
		for (var i = startPage; i <= endPage; i++) {
			if (i === page) {
				html += '<li class="page-item active"><span class="page-link">' + (i + 1) + '</span></li>';
			} else {
				html += '<li class="page-item"><a class="page-link" href="#" data-page-dq="' + i + '">' + (i + 1) + '</a></li>';
			}
		}
		if (endPage < totalPages - 1) {
			if (endPage < totalPages - 2) {
				html += '<li class="page-item disabled"><span class="page-link">…</span></li>';
			}
			html += '<li class="page-item"><a class="page-link" href="#" data-page-dq="' + (totalPages - 1) + '">' + totalPages + '</a></li>';
		}

		if (page < totalPages - 1) {
			html += '<li class="page-item"><a class="page-link" href="#" data-page-dq="' + (page + 1) + '">Next</a></li>';
		} else {
			html += '<li class="page-item disabled"><span class="page-link">Next</span></li>';
		}
		html += '</ul></nav>';

		var recordsText = '<span class="text-muted ms-3"><strong>' + (records || 0) + '</strong> matching staff</span>';
		$('#paginationLinksTopDq').html(html + recordsText);
		$('#paginationLinksDq').html(html);
	}

	function loadData() {
		$('#staffDataQualityBody').html(
			'<tr><td colspan="4" class="text-center">' +
			'<div class="spinner-border text-primary" role="status"></div>' +
			'<p class="mt-2 mb-0">Loading report...</p></td></tr>'
		);

		var postData = collectFilters();
		postData.page = currentPage;
		postData.per_page = currentPerPage;
		postData['<?= $this->security->get_csrf_token_name() ?>'] = '<?= $this->security->get_csrf_hash() ?>';

		$.ajax({
			url: '<?= base_url('staff/get_staff_data_quality_report_ajax') ?>',
			method: 'POST',
			data: postData,
			dataType: 'json',
			success: function (response) {
				if (response.html !== undefined) {
					$('#staffDataQualityBody').html(response.html);
				}
				if (response.csrf_hash) {
					$('input[name="<?= $this->security->get_csrf_token_name() ?>"]').val(response.csrf_hash);
				}
				generatePagination(response.total || 0, response.page || 0, response.per_page || currentPerPage, response.records || 0);
			},
			error: function () {
				$('#staffDataQualityBody').html('<tr><td colspan="4" class="text-center text-danger">Error loading report. Please try again.</td></tr>');
			}
		});
	}

	function triggerDebouncedApply() {
		clearTimeout(debounceTimer);
		debounceTimer = setTimeout(function () {
			currentPage = 0;
			loadData();
		}, 350);
	}

	$(document).ready(function () {
		updateExportLinks();
		loadData();

		$('#recordsPerPageDq').on('change', function () {
			currentPerPage = parseInt($(this).val(), 10) || 20;
			currentPage = 0;
			loadData();
		});

		$('#alert').on('change', function () {
			currentPage = 0;
			updateExportLinks();
			loadData();
		});

		$('#staff_name').on('input', function () {
			updateExportLinks();
			triggerDebouncedApply();
		});

		$(document).on('click', '[data-page-dq]', function (e) {
			e.preventDefault();
			var p = $(this).data('page-dq');
			if (p !== undefined) {
				currentPage = parseInt(p, 10);
				loadData();
			}
		});
	});
})();
</script>
