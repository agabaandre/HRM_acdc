<?php
$divisions = isset($divisions) ? $divisions : [];
$duty_stations = isset($duty_stations) ? $duty_stations : [];
$jobs = isset($jobs) ? $jobs : [];
$grades = isset($grades) ? $grades : [];
?>
<style>
	@media print {
		.hidden { display: none; }
	}
	.export-buttons#originalExportButtons {
		display: none !important;
	}
</style>

<?php $this->load->view('staff_tab_menu'); ?>

<div class="card">
	<div class="card-body">
		<?= form_open_multipart(base_url('staff/staff_next_of_kin'), ['id' => 'staff_form', 'class' => 'staff', 'method' => 'get']) ?>
		<?php $this->load->view('staff_filters', [
			'divisions' => $divisions,
			'duty_stations' => $duty_stations,
			'jobs' => $jobs,
			'grades' => $grades,
			'staff_filters_hide_apply' => !empty($staff_filters_hide_apply),
		]); ?>
		<?= form_close() ?>

		<div class="row mb-3 align-items-center mt-2">
			<div class="col-md-4">
				<div id="paginationLinksTop" class="d-flex align-items-center flex-wrap"></div>
			</div>
			<div class="col-md-4 text-center">
				<div class="d-flex align-items-center justify-content-center gap-2">
					<label for="recordsPerPageNok" class="mb-0 fw-semibold">Records per page:</label>
					<select id="recordsPerPageNok" class="form-select form-select-sm" style="width: auto;">
						<option value="20" selected>20</option>
						<option value="50">50</option>
						<option value="75">75</option>
						<option value="100">100</option>
					</select>
				</div>
			</div>
			<div class="col-md-4 text-end">
				<div id="exportButtonsTopNok" class="d-flex gap-2 justify-content-end"></div>
			</div>
		</div>

		<div class="container pb-4 px-0">
			<h4 class="mb-1"><?= htmlspecialchars($title) ?></h4>
			<p class="text-muted small mb-3">
				Staff with a latest contract in <strong>Active</strong>, <strong>Due</strong>, or <strong>Under renewal</strong>
				(same filter fields as All Staff). Residential address and next-of-kin come from each staff member&rsquo;s portal profile where captured.
				Filters run automatically when you change a dropdown, or when <strong>Name</strong> / <strong>SAP NO</strong> is empty or has at least <strong>3</strong> characters.
				Use <strong>Export CSV / PDF</strong> above to download the <strong>full filtered</strong> list (all pages).
			</p>

			<div id="staffNextOfKinBody">
				<div class="text-center py-5 text-muted">
					<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
					<p class="mt-2 mb-0">Loading report…</p>
				</div>
			</div>
			<div id="paginationInfoNok" class="mt-3 text-end">
				<div id="paginationLinksNok" class="mt-2"></div>
			</div>
		</div>
	</div>
</div>

<script>
(function () {
	var currentPage = 0;
	var currentPerPage = 20;
	var currentFilters = {};

	function filtersFromForm() {
		var obj = {};
		$('#staff_form').serializeArray().forEach(function (item) {
			if (!item.value) {
				return;
			}
			if (item.name === 'lname' || item.name === 'SAPNO') {
				var t = String(item.value).trim();
				if (t.length > 0 && t.length < 3) {
					return;
				}
			}
			if (obj[item.name]) {
				if (!Array.isArray(obj[item.name])) {
					obj[item.name] = [obj[item.name]];
				}
				obj[item.name].push(item.value);
			} else {
				obj[item.name] = item.value;
			}
		});
		return obj;
	}

	function applyFiltersNow() {
		currentPage = 0;
		currentFilters = filtersFromForm();
		updateExportLinksNok();
		loadStaffNextOfKinData();
	}

	var textFilterDebounceTimer = null;

	function updateExportLinksNok() {
		var filters = Object.assign({}, currentFilters);
		var queryString = $.param(filters);
		var baseUrl = '<?= base_url('staff/staff_next_of_kin') ?>';
		$('#exportButtonsTopNok a').each(function () {
			var href = $(this).attr('href');
			if (!href || href.indexOf('staff_next_of_kin') === -1) {
				return;
			}
			if (href.indexOf('/0/1') !== -1) {
				$(this).attr('href', baseUrl + '/0/1' + (queryString ? '?' + queryString : ''));
			} else if (href.indexOf('/1') !== -1) {
				$(this).attr('href', baseUrl + '/1' + (queryString ? '?' + queryString : ''));
			}
		});
		$('.export-buttons a[href*="staff_next_of_kin"]').each(function () {
			var href = $(this).attr('href');
			if (!href) {
				return;
			}
			if (href.indexOf('/0/1') !== -1) {
				$(this).attr('href', baseUrl + '/0/1' + (queryString ? '?' + queryString : ''));
			} else if (href.indexOf('/1') !== -1 && href.indexOf('/0/1') === -1) {
				$(this).attr('href', baseUrl + '/1' + (queryString ? '?' + queryString : ''));
			}
		});
	}

	function generatePaginationNok(total, page, perPage, records) {
		var totalPages = Math.ceil(total / perPage) || 1;
		var paginationHtml = '<nav><ul class="pagination pagination-sm mb-0">';
		if (page > 0) {
			paginationHtml += '<li class="page-item"><a class="page-link" href="#" data-page-nok="' + (page - 1) + '">Previous</a></li>';
		} else {
			paginationHtml += '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
		}
		var startPage = Math.max(0, page - 2);
		var endPage = Math.min(totalPages - 1, page + 2);
		if (startPage > 0) {
			paginationHtml += '<li class="page-item"><a class="page-link" href="#" data-page-nok="0">1</a></li>';
			if (startPage > 1) {
				paginationHtml += '<li class="page-item disabled"><span class="page-link">…</span></li>';
			}
		}
		for (var i = startPage; i <= endPage; i++) {
			if (i === page) {
				paginationHtml += '<li class="page-item active"><span class="page-link">' + (i + 1) + '</span></li>';
			} else {
				paginationHtml += '<li class="page-item"><a class="page-link" href="#" data-page-nok="' + i + '">' + (i + 1) + '</a></li>';
			}
		}
		if (endPage < totalPages - 1) {
			if (endPage < totalPages - 2) {
				paginationHtml += '<li class="page-item disabled"><span class="page-link">…</span></li>';
			}
			paginationHtml += '<li class="page-item"><a class="page-link" href="#" data-page-nok="' + (totalPages - 1) + '">' + totalPages + '</a></li>';
		}
		if (page < totalPages - 1) {
			paginationHtml += '<li class="page-item"><a class="page-link" href="#" data-page-nok="' + (page + 1) + '">Next</a></li>';
		} else {
			paginationHtml += '<li class="page-item disabled"><span class="page-link">Next</span></li>';
		}
		paginationHtml += '</ul></nav>';
		var recordsText = '<span class="text-muted ms-3"><strong>' + (records || 0) + '</strong> matching staff</span>';
		$('#paginationLinksTop').html(paginationHtml + recordsText);
		$('#paginationLinksNok').html(paginationHtml);
	}

	function loadStaffNextOfKinData() {
		$('#staffNextOfKinBody').html(
			'<div class="text-center py-5 text-muted">' +
			'<div class="spinner-border text-primary" role="status"></div>' +
			'<p class="mt-2 mb-0">Loading report…</p></div>'
		);

		var postData = Object.assign({}, currentFilters);
		postData.page = currentPage;
		postData.per_page = currentPerPage;
		postData['<?= $this->security->get_csrf_token_name() ?>'] = '<?= $this->security->get_csrf_hash() ?>';

		$.ajax({
			url: '<?= base_url('staff/get_staff_next_of_kin_ajax') ?>',
			method: 'POST',
			data: postData,
			dataType: 'json',
			success: function (response) {
				if (response.html !== undefined) {
					$('#staffNextOfKinBody').html(response.html);
				}
				if (response.csrf_hash) {
					$('input[name="<?= $this->security->get_csrf_token_name() ?>"]').val(response.csrf_hash);
				}
				generatePaginationNok(response.total || 0, response.page || 0, response.per_page || currentPerPage, response.records || 0);
			},
			error: function (xhr) {
				$('#staffNextOfKinBody').html('<p class="text-danger">Error loading report. Please try again.</p>');
			}
		});
	}

	$(document).ready(function () {
		var exportClone = $('#originalExportButtons').clone();
		if (exportClone.length && exportClone.html().trim() !== '') {
			$('#exportButtonsTopNok').html(exportClone.html());
			$('#originalExportButtons').hide();
		} else {
			var qs = window.location.search || '';
			$('#exportButtonsTopNok').html(
				'<a href="<?= base_url('staff/staff_next_of_kin/1') ?>' + qs + '" class="btn btn-sm btn-outline-primary"><i class="fa fa-file-csv me-1"></i> Export CSV</a>' +
				'<a href="<?= base_url('staff/staff_next_of_kin/0/1') ?>' + qs + '" class="btn btn-sm btn-outline-danger"><i class="fa fa-file-pdf me-1"></i> Export PDF</a>'
			);
		}

		currentFilters = filtersFromForm();
		updateExportLinksNok();
		loadStaffNextOfKinData();

		$('#recordsPerPageNok').on('change', function () {
			currentPerPage = parseInt($(this).val(), 10) || 20;
			currentPage = 0;
			loadStaffNextOfKinData();
		});

		$('#staff_form').on('submit', function (e) {
			e.preventDefault();
			applyFiltersNow();
		});

		$('#staff_form').on('input', 'input[name="lname"], input[name="SAPNO"]', function () {
			clearTimeout(textFilterDebounceTimer);
			textFilterDebounceTimer = setTimeout(function () {
				applyFiltersNow();
			}, 400);
		});

		$('#staff_form').on('change', 'select', function () {
			applyFiltersNow();
		});

		$(document).on('click', '[data-page-nok]', function (e) {
			e.preventDefault();
			var p = $(this).data('page-nok');
			if (p !== undefined) {
				currentPage = parseInt(p, 10);
				loadStaffNextOfKinData();
			}
		});
	});
})();
</script>
