<?php $this->load->view('staff_tab_menu'); ?>

<style>
	.sig-manager-stats .card {
		border: 1px solid #e9ecef;
	}
	.sig-manager-preview-box {
		display: inline-block;
		padding: 0.35rem 0.5rem;
		background:
			linear-gradient(45deg, #f1f3f5 25%, transparent 25%),
			linear-gradient(-45deg, #f1f3f5 25%, transparent 25%),
			linear-gradient(45deg, transparent 75%, #f1f3f5 75%),
			linear-gradient(-45deg, transparent 75%, #f1f3f5 75%);
		background-size: 8px 8px;
		background-position: 0 0, 0 4px, 4px -4px, -4px 0;
		background-color: #fff;
		border: 1px solid #dee2e6;
		border-radius: 0.375rem;
		max-width: 220px;
	}
	.sig-manager-preview-box img {
		max-height: 52px;
		max-width: 200px;
		display: block;
	}
	.sig-manager-toolbar {
		background: #f8f9fa;
		border: 1px solid #e9ecef;
		border-radius: 0.5rem;
	}
</style>

<div class="card">
	<div class="card-body">
		<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
			<div>
				<h5 class="mb-1">Signature Manager</h5>
				<p class="text-muted small mb-0">
					Current staff with valid signatures are protected. Generate typed signatures from staff names (blue script font, same as profile) and upload in bulk for missing signatures only.
				</p>
			</div>
		</div>

		<div class="row g-3 mb-4 sig-manager-stats">
			<div class="col-md-3 col-6">
				<div class="card shadow-sm h-100">
					<div class="card-body py-3">
						<div class="text-muted small">Total staff</div>
						<div class="fs-4 fw-bold" id="sigStatTotal">—</div>
					</div>
				</div>
			</div>
			<div class="col-md-3 col-6">
				<div class="card shadow-sm h-100 border-success">
					<div class="card-body py-3">
						<div class="text-muted small">Valid signatures</div>
						<div class="fs-4 fw-bold text-success" id="sigStatValid">—</div>
					</div>
				</div>
			</div>
			<div class="col-md-3 col-6">
				<div class="card shadow-sm h-100 border-danger">
					<div class="card-body py-3">
						<div class="text-muted small">Missing</div>
						<div class="fs-4 fw-bold text-danger" id="sigStatMissing">—</div>
					</div>
				</div>
			</div>
			<div class="col-md-3 col-6">
				<div class="card shadow-sm h-100 border-warning">
					<div class="card-body py-3">
						<div class="text-muted small">Broken file</div>
						<div class="fs-4 fw-bold text-warning" id="sigStatBroken">—</div>
					</div>
				</div>
			</div>
		</div>

		<form id="signature_manager_form" method="get">
			<div class="card shadow-sm p-3 mb-4 border rounded" style="background-color: #f9f9f9;">
				<div class="row g-3 align-items-end">
					<div class="col-md-3">
						<label for="staff_name" class="form-label fw-bold">Staff Name</label>
						<input type="text" id="staff_name" name="staff_name" class="form-control" placeholder="Enter staff name (min 3 chars)">
					</div>
					<div class="col-md-3">
						<label for="signature_scope" class="form-label fw-bold">Staff scope</label>
						<select id="signature_scope" name="scope" class="form-control">
							<option value="approvers" selected>APM approvers only</option>
							<option value="current">All active staff</option>
						</select>
					</div>
					<div class="col-md-3">
						<label for="signature_status" class="form-label fw-bold">Signature Status</label>
						<select id="signature_status" name="signature_status" class="form-control">
							<option value="all" selected>All</option>
							<option value="valid">Valid only</option>
							<option value="missing">Missing only</option>
							<option value="broken">Broken file only</option>
						</select>
					</div>
					<div class="col-md-3">
						<div class="small text-muted">
							<strong>All active staff</strong> includes everyone on active, due, or renewal contracts (with or without signatures).
							<strong>APM approvers</strong> matches the <a href="<?= htmlspecialchars(rtrim($this->config->item('apm_base_url') ?: base_url('apm'), '/') . '/approver-dashboard') ?>" target="_blank" rel="noopener">Approver Dashboard</a>. Valid signatures are never overwritten.
						</div>
					</div>
				</div>
			</div>
		</form>

		<div class="sig-manager-toolbar p-3 mb-3 d-flex flex-wrap align-items-center gap-2">
			<div class="form-check mb-0">
				<input class="form-check-input" type="checkbox" id="sigManagerSelectAll">
				<label class="form-check-label" for="sigManagerSelectAll">Select all on page</label>
			</div>
			<button type="button" class="btn btn-sm btn-outline-primary" id="sigManagerGenerateSelected">
				<i class="fa fa-pen-nib me-1"></i> Generate previews
			</button>
			<button type="button" class="btn btn-sm btn-success" id="sigManagerUploadSelected" disabled>
				<i class="fa fa-cloud-upload-alt me-1"></i> Upload generated to server
			</button>
			<span class="text-muted small ms-auto" id="sigManagerBulkStatus"></span>
		</div>

		<div class="row mb-3 align-items-center">
			<div class="col-md-4">
				<div id="paginationLinksTopSig" class="d-flex align-items-center flex-wrap"></div>
			</div>
			<div class="col-md-4 text-center">
				<div class="d-flex align-items-center justify-content-center gap-2">
					<label for="recordsPerPageSig" class="mb-0 fw-semibold">Records per page:</label>
					<select id="recordsPerPageSig" class="form-select form-select-sm" style="width: auto;">
						<option value="20" selected>20</option>
						<option value="50">50</option>
						<option value="75">75</option>
						<option value="100">100</option>
					</select>
				</div>
			</div>
			<div class="col-md-4 text-end">
				<div class="d-flex gap-2 justify-content-end">
					<a id="sigExportExcel" href="<?= base_url('staff/signature_manager/1') ?>" class="btn btn-sm btn-outline-primary">
						<i class="fa fa-file-csv me-1"></i> Export Excel
					</a>
					<a id="sigExportPdf" href="<?= base_url('staff/signature_manager/0/1') ?>" class="btn btn-sm btn-outline-danger">
						<i class="fa fa-file-pdf me-1"></i> Export PDF
					</a>
				</div>
			</div>
		</div>

		<div class="table-responsive">
			<table class="table table-striped table-bordered align-middle">
				<thead>
					<tr>
						<th style="width: 42px;">Sel</th>
						<th style="width: 50px;">#</th>
						<th style="width: 90px;">Profile</th>
						<th>Name of Staff</th>
						<th style="width: 110px;">Status</th>
						<th style="width: 240px;">Signature preview</th>
						<th style="width: 120px;">Action</th>
					</tr>
				</thead>
				<tbody id="signatureManagerBody">
					<tr>
						<td colspan="7" class="text-center">
							<div class="spinner-border text-primary" role="status">
								<span class="visually-hidden">Loading...</span>
							</div>
							<p class="mt-2 mb-0">Loading staff signatures...</p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<div id="paginationInfoSig" class="mt-3 text-end">
			<div id="paginationLinksSig" class="mt-2"></div>
		</div>
	</div>
</div>

<style>
  @font-face {
    font-family: 'SignatureFont';
    font-style: italic;
    font-weight: 400;
    font-display: swap;
    src: url('https://cdn.jsdelivr.net/npm/@fontsource/dancing-script/files/dancing-script-latin-400-normal.woff') format('woff');
  }
</style>
<link rel="preload" href="https://cdn.jsdelivr.net/npm/@fontsource/dancing-script/files/dancing-script-latin-400-normal.woff" as="font" type="font/woff" crossorigin>
<script>
	window.STAFF_SIGNATURE_MANAGER = {
		csrfTokenName: '<?= $this->security->get_csrf_token_name() ?>',
		csrfHash: '<?= $this->security->get_csrf_hash() ?>',
		ajaxUrl: '<?= base_url('staff/get_signature_manager_ajax') ?>',
		uploadUrl: '<?= base_url('staff/bulk_save_signatures') ?>',
		manualUploadUrl: '<?= base_url('staff/upload_signature_manual') ?>',
		exportExcelUrl: '<?= base_url('staff/signature_manager/1') ?>',
		exportPdfUrl: '<?= base_url('staff/signature_manager/0/1') ?>',
		signatureColor: '#3B82F6'
	};
</script>
<script src="<?= base_url('assets/js/staff-signature-manager.js') ?>?v=5"></script>
