<style>
	@media print {
		.hidden {
			display: none;
		}

		@page {
			margin-top: 0;
			margin-bottom: 0;
			display: flex;
			justify-content: center;
			align-items: center;
			height: 100%;
			/* html, body{
                height: 100%;
                width: 100%;
            } */
		}

		/* body{
            padding-top: 72px;
            padding-bottom: 72px;
        } */
	}
	
	/* Hide original export buttons in filters for all_staff page after they're moved */
	.export-buttons#originalExportButtons {
		display: none !important;
	}
</style>

<?php $this->load->view('staff_tab_menu'); ?>
<div class="card">
	<div class="card-body">
		
<?= form_open_multipart(base_url('staff/all_staff'), ['id' => 'staff_form', 'class' => 'staff', 'method' => 'get']) ?>

<?php $this->load->view('staff_filters'); ?>

<?= form_close() ?>

<div class="row mb-3 align-items-center">
	<div class="col-md-4">
		<div id="paginationLinksTop" class="d-flex align-items-center"></div>
	</div>
	<div class="col-md-4 text-center">
			<div class="d-flex align-items-center justify-content-center gap-2">
				<label for="recordsPerPage" class="mb-0 fw-semibold">Records per page:</label>
				<select id="recordsPerPage" class="form-select form-select-sm" style="width: auto;">
					<option value="20" selected>20</option>
					<option value="50">50</option>
					<option value="75">75</option>
					<option value="100">100</option>
				</select>
			</div>
	</div>
	<div class="col-md-4 text-end">
		<div id="exportButtonsTop" class="d-flex gap-2 justify-content-end">
			<!-- Export buttons will be moved here from staff_filters -->
		</div>
	</div>
</div>

<div class="table-responsive" style="margin-left: 4px; margin-right: 4px;">
		<table class="table table-striped table-bordered">
			<thead>
				<tr>
					<th>#</th>
					<th>SAPNO</th>
					<th>Title</th>
					<th>Passport Photo</th>
					<th>Name</th>
					<th>Gender</th>
					<th>Date of Birth</th>
					<th>Age</th>
					<th>Nationality</th>
					<th>Duty Station</th>
					<th>Division</th>
					<th>Grade</th>
					<th>Job</th>
					<th>Current Contract End Date</th>
					<th>Years of Tenure</th>
					<th>Acting Job</th>
					<th>First Supervisor</th>
					<th>Second Supervisor</th>
					<th>Funder</th>
					<th>Email</th>
					<th>Telephone</th>
					<th>WhatsApp</th>
				</tr>
			</thead>
		<tbody id="staffTableBody">
				  <tr>
				<td colspan="22" class="text-center">
					<div class="spinner-border text-primary" role="status">
						<span class="visually-hidden">Loading...</span>
					</div>
					<p class="mt-2">Loading staff data...</p>
					</td>
</tr>
</tbody>
</table>
	<div id="paginationInfo" class="mt-3 text-end">
		<div id="paginationLinks" class="mt-2"></div>
	</div>
</div>
</div>
</div>

<!-- Bootstrap Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel">Employee Passport Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" style="width:150px; height:auto; border-radius:10px;">
            </div>
        </div>
    </div>
</div>

<!-- Single Employee Profile Modal -->
<div class="modal fade" id="employeeProfileModal" tabindex="-1" aria-labelledby="employeeProfileModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header text-white" style="background-color: #119a48;">
				<div class="d-flex align-items-center w-100">
					<div class="flex-grow-1">
						<h5 class="modal-title mb-0" id="employeeProfileModalLabel">
							<i class="fa fa-user me-2"></i>Employee Profile
						</h5>
					</div>
					<div class="d-flex gap-2">
						<a href="#" id="editProfileBtn" class="btn btn-light btn-sm">
							<i class="fa fa-edit me-1"></i>Edit
						</a>
						<a href="#" id="printProfileBtn" class="btn btn-light btn-sm" target="_blank">
							<i class="fa fa-print me-1"></i>Print
						</a>
						<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
					</div>
				</div>
			</div>
			<div class="modal-body p-4" id="employeeProfileContent">
				<div class="text-center py-5">
					<div class="spinner-border" role="status" style="color: #119a48;">
						<span class="visually-hidden">Loading...</span>
					</div>
					<p class="mt-3 text-muted">Loading employee profile...</p>
				</div>
			</div>
			<div class="modal-footer bg-light">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
					<i class="fa fa-times me-1"></i>Close
				</button>
            </div>
        </div>
	</div>
</div>

<!-- Edit Biodata Modal -->
<div class="modal fade" id="editBiodataModal" tabindex="-1" aria-labelledby="editBiodataModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="editBiodataModalLabel">Edit Employee Biodata</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body" id="editBiodataContent">
				<div class="text-center py-5">
					<div class="spinner-border" role="status" style="color: #119a48;">
						<span class="visually-hidden">Loading...</span>
					</div>
					<p class="mt-3 text-muted">Loading form...</p>
				</div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
function openImageModal(imageSrc) {
    document.getElementById("modalImage").src = imageSrc;
    var myModal = new bootstrap.Modal(document.getElementById("imageModal"), {});
    myModal.show();
}

// Employee Profile Modal Handler
$(document).ready(function() {
	var currentStaffId = null;

	// Handle profile link clicks
	$(document).on('click', '.view-staff-profile', function(e) {
		e.preventDefault();
		var staffId = $(this).data('staff-id');
		currentStaffId = staffId;
		
		// Get modal element
		var modalElement = document.getElementById('employeeProfileModal');
		
		// Get or create modal instance
		var modal = bootstrap.Modal.getInstance(modalElement);
		if (!modal) {
			modal = new bootstrap.Modal(modalElement);
		}
		
		// Show modal
		modal.show();
		
		// Load staff data
		loadStaffProfile(staffId);
	});
	
	// Handle Edit button click
	$(document).on('click', '#editProfileBtn', function(e) {
		e.preventDefault();
		var profileModal = bootstrap.Modal.getInstance(document.getElementById('employeeProfileModal'));
		if (profileModal) {
			profileModal.hide();
		}
		if (currentStaffId) {
			loadEditBiodataModal(currentStaffId);
		}
	});
	
	// Handle modal close events to reset content (only bind once)
	$('#employeeProfileModal').on('hidden.bs.modal', function() {
		$('#employeeProfileContent').html(`
			<div class="text-center py-5">
				<div class="spinner-border" role="status" style="color: #119a48;">
					<span class="visually-hidden">Loading...</span>
				</div>
				<p class="mt-3 text-muted">Loading employee profile...</p>
			</div>
		`);
	});

	function loadStaffProfile(staffId) {
		// Show loading state
		$('#employeeProfileContent').html(`
			<div class="text-center py-5">
				<div class="spinner-border" role="status" style="color: #119a48;">
					<span class="visually-hidden">Loading...</span>
				</div>
				<p class="mt-3 text-muted">Loading employee profile...</p>
			</div>
		`);

		// Fetch staff data
		$.ajax({
			url: '<?php echo base_url(); ?>staff/get_staff_profile_ajax/' + encodeURIComponent(staffId),
			method: 'GET',
			dataType: 'json',
			contentType: 'application/json; charset=utf-8',
			success: function(response) {
				if (response.success) {
					populateModal(response);
				} else {
					$('#employeeProfileContent').html(`
						<div class="alert alert-danger">
							<i class="fa fa-exclamation-triangle me-2"></i>${response.message || 'Failed to load staff profile'}
						</div>
					`);
				}
			},
			error: function(xhr, status, error) {
				console.error('AJAX Error:', status, error);
				console.error('Response:', xhr.responseText);
				$('#employeeProfileContent').html(`
					<div class="alert alert-danger">
						<i class="fa fa-exclamation-triangle me-2"></i>Error loading staff profile. Please try again.
						<br><small>Error: ${error}</small>
					</div>
				`);
			}
		});
	}

	function populateModal(data) {
		var staff = data.staff;
		var contract = data.contract;
		
		// Update edit and print buttons
		$('#editProfileBtn').attr('data-bs-target', '#edit_profile' + staff.staff_id);
		$('#printProfileBtn').attr('href', '<?php echo base_url(); ?>staff/profile/' + staff.staff_id);

		var html = `
			<!-- Header Section with Photo and Name -->
			<div class="row mb-4 pb-3 border-bottom">
				<div class="col-md-3 text-center">
					<div class="mb-3">
						${staff.photo_html || '<div class="bg-secondary rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px;"><i class="fa fa-user fa-3x text-white"></i></div>'}
					</div>
				</div>
				<div class="col-md-9">
					<h3 class="mb-2" style="color: #119a48;">
						${staff.title ? staff.title + ' ' : ''}${staff.lname} ${staff.fname} ${staff.oname || ''}
					</h3>
					${staff.SAPNO ? `<p class="text-muted mb-1"><i class="fa fa-id-card me-2"></i><strong>SAP Number:</strong> ${staff.SAPNO}</p>` : ''}
					${staff.work_email ? `<p class="text-muted mb-1"><i class="fa fa-envelope me-2"></i><a href="mailto:${staff.work_email}" class="text-decoration-none">${staff.work_email}</a></p>` : ''}
				</div>
			</div>

			<!-- Information Sections -->
			<div class="row g-4">
				<!-- Personal Information -->
				<div class="col-md-6">
					<div class="card h-100 border-0 shadow-sm">
						<div class="card-header bg-light">
							<h5 class="mb-0"><i class="fa fa-user me-2" style="color: #119a48;"></i>Personal Information</h5>
						</div>
						<div class="card-body">
							<div class="row g-3">
								${staff.gender ? `<div class="col-12"><strong class="text-muted d-block mb-1">Gender</strong><span>${staff.gender}</span></div>` : ''}
								${staff.date_of_birth ? `<div class="col-12"><strong class="text-muted d-block mb-1">Date of Birth</strong><span>${staff.date_of_birth}</span></div>` : ''}
								${staff.nationality ? `<div class="col-12"><strong class="text-muted d-block mb-1">Nationality</strong><span><i class="fa fa-globe me-1"></i>${staff.nationality}</span></div>` : ''}
								${staff.initiation_date ? `<div class="col-12"><strong class="text-muted d-block mb-1">Initiation Date</strong><span>${staff.initiation_date}</span></div>` : ''}
							</div>
						</div>
					</div>
				</div>

				<!-- Contact Information -->
				<div class="col-md-6">
					<div class="card h-100 border-0 shadow-sm">
						<div class="card-header bg-light">
							<h5 class="mb-0"><i class="fa fa-address-book me-2" style="color: #119a48;"></i>Contact Information</h5>
						</div>
						<div class="card-body">
							<div class="row g-3">
								${staff.work_email ? `<div class="col-12"><strong class="text-muted d-block mb-1">Work Email</strong><span><a href="mailto:${staff.work_email}" class="text-decoration-none">${staff.work_email}</a></span></div>` : ''}
								${staff.tel_1 || staff.tel_2 ? `<div class="col-12"><strong class="text-muted d-block mb-1">Telephone</strong><span><i class="fa fa-phone me-1"></i>${staff.tel_1 || ''}${staff.tel_1 && staff.tel_2 ? ' <span class="text-muted">/</span> ' : ''}${staff.tel_2 || ''}</span></div>` : ''}
								${staff.whatsapp ? `<div class="col-12"><strong class="text-muted d-block mb-1">WhatsApp</strong><span><i class="fab fa-whatsapp me-1 text-success"></i>${staff.whatsapp}</span></div>` : ''}
								${staff.physical_location ? `<div class="col-12"><strong class="text-muted d-block mb-1">Physical Location</strong><span><i class="fa fa-map-marker-alt me-1"></i>${staff.physical_location}</span></div>` : ''}
							</div>
						</div>
					</div>
				</div>

				<!-- Contract Information -->
				<div class="col-12">
					<div class="card border-0 shadow-sm">
						<div class="card-header bg-light d-flex justify-content-between align-items-center">
							<h5 class="mb-0"><i class="fa fa-file-contract me-2" style="color: #119a48;"></i>Current Contract Information</h5>
							<a href="<?php echo base_url(); ?>staff/staff_contracts/${staff.staff_id}" target="_blank" class="btn btn-sm" style="background-color: #119a48; color: white; border-color: #119a48;">
								<i class="fa fa-external-link-alt me-1"></i>Manage Contracts
							</a>
						</div>
						<div class="card-body">
							${contract ? `
								<div class="row g-3">
									${contract.duty_station_name ? `<div class="col-md-4"><strong class="text-muted d-block mb-1">Duty Station</strong><span>${contract.duty_station_name}</span></div>` : ''}
									${contract.division_name ? `<div class="col-md-4"><strong class="text-muted d-block mb-1">Division</strong><span>${contract.division_name}</span></div>` : ''}
									${contract.job_name ? `<div class="col-md-4"><strong class="text-muted d-block mb-1">Job Title</strong><span>${contract.job_name}</span></div>` : ''}
									${contract.job_acting && contract.job_acting != 'N/A' ? `<div class="col-md-4"><strong class="text-muted d-block mb-1">Acting Job</strong><span>${contract.job_acting}</span></div>` : ''}
									${contract.first_supervisor ? `<div class="col-md-4"><strong class="text-muted d-block mb-1">First Supervisor</strong><span>${contract.first_supervisor}</span></div>` : ''}
									${contract.second_supervisor ? `<div class="col-md-4"><strong class="text-muted d-block mb-1">Second Supervisor</strong><span>${contract.second_supervisor}</span></div>` : ''}
									${contract.funder ? `<div class="col-md-4"><strong class="text-muted d-block mb-1">Funder</strong><span>${contract.funder}</span></div>` : ''}
									${contract.contracting_institution ? `<div class="col-md-4"><strong class="text-muted d-block mb-1">Contracting Organisation</strong><span>${contract.contracting_institution}</span></div>` : ''}
									${contract.grade ? `<div class="col-md-4"><strong class="text-muted d-block mb-1">Grade</strong><span>${contract.grade}</span></div>` : ''}
									${contract.contract_type ? `<div class="col-md-4"><strong class="text-muted d-block mb-1">Contract Type</strong><span>${contract.contract_type}</span></div>` : ''}
									${contract.status ? `<div class="col-md-4"><strong class="text-muted d-block mb-1">Contract Status</strong><span class="badge bg-${contract.status == 'Active' ? 'success' : (contract.status == 'Expired' ? 'danger' : 'warning')}">${contract.status}</span></div>` : ''}
									${contract.start_date ? `<div class="col-md-4"><strong class="text-muted d-block mb-1">Current Contract Start Date</strong><span>${contract.start_date}</span></div>` : ''}
									${contract.end_date ? `<div class="col-md-4"><strong class="text-muted d-block mb-1">Current Contract End Date</strong><span>${contract.end_date}</span></div>` : ''}
									${contract.comments ? `<div class="col-12"><strong class="text-muted d-block mb-1">Comments</strong><span>${contract.comments}</span></div>` : ''}
								</div>
							` : '<p class="text-muted">No contract information available.</p>'}
						</div>
					</div>
				</div>
			</div>
		`;

		$('#employeeProfileContent').html(html);
	}

	// Load Edit Biodata Modal
	function loadEditBiodataModal(staffId) {
		if (!staffId) {
			console.error('No staff ID provided');
			return;
		}

		// Show loading state
		$('#editBiodataContent').html(`
			<div class="text-center py-5">
				<div class="spinner-border" role="status" style="color: #119a48;">
					<span class="visually-hidden">Loading...</span>
				</div>
				<p class="mt-3 text-muted">Loading form...</p>
			</div>
		`);

		// Fetch staff data for edit form
		$.ajax({
			url: '<?php echo base_url(); ?>staff/get_staff_profile_ajax/' + encodeURIComponent(staffId),
			method: 'GET',
			dataType: 'json',
			contentType: 'application/json; charset=utf-8',
			success: function(response) {
				if (response.success && response.staff) {
					populateEditBiodataModal(response.staff);
					// Show the modal
					var editModal = new bootstrap.Modal(document.getElementById('editBiodataModal'));
					editModal.show();
				} else {
					$('#editBiodataContent').html(`
						<div class="alert alert-danger">
							<i class="fa fa-exclamation-triangle me-2"></i>${response.message || 'Failed to load staff data'}
						</div>
					`);
				}
			},
			error: function(xhr, status, error) {
				console.error('AJAX Error:', status, error);
				$('#editBiodataContent').html(`
					<div class="alert alert-danger">
						<i class="fa fa-exclamation-triangle me-2"></i>Error loading form. Please try again.
					</div>
				`);
			}
		});
	}

	// Populate Edit Biodata Modal
	function populateEditBiodataModal(staff) {
		// Fetch nationality options via AJAX
		var nationalityOptions = '<option value="">Select Nationality</option>';
		
		// Use a synchronous AJAX call to get nationalities
		$.ajax({
			url: '<?php echo base_url(); ?>lists/nationality?format=json',
			method: 'GET',
			dataType: 'json',
			async: false,
			success: function(nationalities) {
				if (nationalities && nationalities.length) {
					nationalities.forEach(function(nat) {
						// Convert both to strings for comparison to avoid type mismatch
						var staffNatId = String(staff.nationality_id || '');
						var natId = String(nat.nationality_id || '');
						var selected = (staffNatId && natId && staffNatId === natId) ? 'selected' : '';
						var displayText = nat.nationality || nat.nationality_name || nat.status || '';
						nationalityOptions += `<option value="${nat.nationality_id}" ${selected}>${displayText}</option>`;
					});
				}
			},
			error: function(xhr, status, error) {
				console.error('Error loading nationalities:', error);
			}
		});

		var html = `
			<?php echo form_open('staff/update_staff', array('id' => 'editBiodataForm', 'class' => 'edit-biodata-form')); ?>
			<div class="row">
				<div class="col-md-6">
					<h4>Personal Information</h4>

					<div class="form-group mb-3">
						<label for="edit_SAPNO">SAP Number:<?=asterik()?></label>
						<input type="text" class="form-control" value="${staff.SAPNO || ''}" name="SAPNO" id="edit_SAPNO">
					</div>

					<div class="form-group mb-3">
						<label for="edit_title">Title:<?=asterik()?></label>
						<select class="form-control select2" name="title" id="edit_title" required>
							${staff.title ? `<option value="${staff.title}">${staff.title}</option>` : ''}
							<option value="">Select Title</option>
							<option value="Dr">Dr</option>
							<option value="Prof">Prof</option>
							<option value="Rev">Rev</option>
							<option value="Mr">Mr</option>
							<option value="Mrs">Mrs</option>
							<option value="Ms">Ms</option>
						</select>
					</div>

					<div class="form-group mb-3">
						<label for="edit_fname">First Name:<?=asterik()?></label>
						<input type="text" class="form-control" value="${staff.fname || ''}" name="fname" id="edit_fname" required>
					</div>
					<input type="hidden" name="staff_id" value="${staff.staff_id}">

					<div class="form-group mb-3">
						<label for="edit_lname">Last Name:<?=asterik()?></label>
						<input type="text" class="form-control" name="lname" value="${staff.lname || ''}" id="edit_lname" required>
					</div>

					<div class="form-group mb-3">
						<label for="edit_oname">Other Name:</label>
						<input type="text" class="form-control" value="${staff.oname || ''}" name="oname" id="edit_oname">
					</div>

					<div class="form-group mb-3">
						<label for="edit_date_of_birth">Date of Birth:<?=asterik()?></label>
						<input type="text" class="form-control datepicker" value="${staff.date_of_birth || ''}" name="date_of_birth" id="edit_date_of_birth" required>
					</div>

					<div class="form-group mb-3">
						<label for="edit_gender">Gender:<?=asterik()?></label>
						<select class="form-control select2" name="gender" id="edit_gender" required>
							<option value="">Select Gender</option>
							<option value="Male" ${(staff.gender == 'Male') ? 'selected' : ''}>Male</option>
							<option value="Female" ${(staff.gender == 'Female') ? 'selected' : ''}>Female</option>
							<option value="Other" ${(staff.gender == 'Other') ? 'selected' : ''}>Other</option>
						</select>
					</div>

					<div class="form-group mb-3">
						<label for="edit_nationality_id">Nationality:<?=asterik()?></label>
						<select class="form-control select2" name="nationality_id" id="edit_nationality_id" required>
							${nationalityOptions}
						</select>
					</div>

					<div class="form-group mb-3">
						<label for="edit_initiation_date">Initiation Date: <?=asterik()?></label>
						<input type="text" class="form-control datepicker" value="${staff.initiation_date || ''}" name="initiation_date" id="edit_initiation_date" required>
					</div>
				</div>

				<div class="col-md-6">
					<h4>Contact Information</h4>

					<div class="form-group mb-3">
						<label for="edit_tel_1">Telephone 1: <?=asterik()?></label>
						<input type="text" class="form-control" value="${staff.tel_1 || ''}" name="tel_1" id="edit_tel_1" required>
					</div>

					<div class="form-group mb-3">
						<label for="edit_tel_2">Telephone 2:</label>
						<input type="text" class="form-control" value="${staff.tel_2 || ''}" name="tel_2" id="edit_tel_2">
					</div>

					<div class="form-group mb-3">
						<label for="edit_whatsapp">WhatsApp:</label>
						<input type="text" class="form-control" name="whatsapp" value="${staff.whatsapp || ''}" id="edit_whatsapp">
					</div>

					<div class="form-group mb-3">
						<label for="edit_work_email">Work Email:<?=asterik()?></label>
						<input type="email" class="form-control" name="work_email" value="${staff.work_email || ''}" id="edit_work_email" required>
					</div>
					<br>
					<div class="form-group mb-3">
						<label for="edit_private_email">Private Email:</label>
						<input type="email" class="form-control" name="private_email" value="${staff.private_email || ''}" id="edit_private_email">
					</div>

					<div class="form-group mb-3">
						<label for="edit_physical_location">Physical Location:</label>
						<textarea class="form-control" name="physical_location" id="edit_physical_location" rows="2">${staff.physical_location || ''}</textarea>
					</div>
				</div>
			</div>

			<div class="form-group text-end mt-3">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="submit" class="btn btn-dark">Save Changes</button>
			</div>
			<?php echo form_close(); ?>
		`;

		$('#editBiodataContent').html(html);
		$('#editBiodataModalLabel').text('Edit Employee Biodata: ' + (staff.title || '') + ' ' + (staff.fname || '') + ' ' + (staff.lname || '') + ' ' + (staff.oname || ''));

		// Initialize Select2 and datepicker after modal is shown
		$('#editBiodataModal').off('shown.bs.modal').on('shown.bs.modal', function() {
			var $modal = $(this);
			
			// Initialize Select2
			$modal.find('.select2').each(function() {
				var $select = $(this);
				if (!$select.hasClass('select2-hidden-accessible')) {
					$select.select2({
						theme: 'bootstrap4',
						width: '100%',
						dropdownParent: $modal
					});
				}
			});
			
			// Initialize datepicker
			if (typeof $().datepicker === 'function') {
				$modal.find('.datepicker').each(function() {
					var $datepicker = $(this);
					if (!$datepicker.data('datepicker')) {
						$datepicker.datepicker({
							format: 'yyyy-mm-dd',
							autoclose: true,
							todayHighlight: true
						});
					}
				});
			}
		});
	}

	// Handle edit biodata form submission
	$(document).on('submit', '#editBiodataForm', function(e) {
		e.preventDefault();
		var form = $(this);
		var formData = form.serialize();

		$.ajax({
			url: form.attr('action'),
			method: 'POST',
			data: formData + '&ajax=1',
			dataType: 'json',
			success: function(response) {
				if (response.success || response.q) {
					// Show success message
					if (typeof Lobibox !== 'undefined') {
						Lobibox.notify('success', {
							msg: response.msg || 'Staff updated successfully.',
							position: 'top right'
						});
					} else {
						alert(response.msg || 'Staff updated successfully.');
					}
					
					// Close modal
					var editModal = bootstrap.Modal.getInstance(document.getElementById('editBiodataModal'));
					if (editModal) {
						editModal.hide();
					}
					
					// Reload staff profile if it's open
					if (currentStaffId) {
						loadStaffProfile(currentStaffId);
					}
					
					// Reload table data
					loadAllStaffData();
				} else {
					if (typeof Lobibox !== 'undefined') {
						Lobibox.notify('error', {
							msg: response.msg || 'Failed to update staff.',
							position: 'top right'
						});
					} else {
						alert(response.msg || 'Failed to update staff.');
					}
				}
			},
			error: function(xhr, status, error) {
				console.error('Update error:', error);
				if (typeof Lobibox !== 'undefined') {
					Lobibox.notify('error', {
						msg: 'Error updating staff. Please try again.',
						position: 'top right'
					});
				} else {
					alert('Error updating staff. Please try again.');
				}
			}
		});
	});

	// All Staff AJAX Data Loading
	var currentPage = 0;
	var currentFilters = {};
	var currentPerPage = 20;

	// Move export buttons to top right on page load and hide originals
	$(document).ready(function() {
		var exportButtons = $('#originalExportButtons').clone();
		if (exportButtons.length && exportButtons.html().trim() !== '') {
			$('#exportButtonsTop').html(exportButtons.html());
			// Hide the original export buttons in staff_filters after cloning
			$('#originalExportButtons').hide();
		} else {
			// If no export buttons found, create them directly
			var queryString = window.location.search;
			var csvUrl = '<?php echo base_url(); ?>staff/all_staff/1' + queryString;
			var pdfUrl = '<?php echo base_url(); ?>staff/all_staff/0/1' + queryString;
			$('#exportButtonsTop').html(`
				<a href="${csvUrl}" class="btn btn-sm btn-outline-primary">
					<i class="fa fa-file-csv me-1"></i> Export CSV
				</a>
				<a href="${pdfUrl}" class="btn btn-sm btn-outline-danger">
					<i class="fa fa-file-pdf me-1"></i> Export PDF
				</a>
			`);
		}
	});

	// Records per page change handler
	$('#recordsPerPage').on('change', function() {
		currentPerPage = parseInt($(this).val());
		currentPage = 0; // Reset to first page when changing per page
		loadAllStaffData();
	});

	// Load data on page load
	loadAllStaffData();
	updateExportLinks(); // Initialize export links

	// Handle filter form submission
	$('#staff_form').on('submit', function(e) {
		e.preventDefault();
		currentPage = 0;
		// Get all form data
		currentFilters = $(this).serializeArray().reduce(function(obj, item) {
			if (item.value) {
				if (obj[item.name]) {
					// If key already exists, convert to array
					if (!Array.isArray(obj[item.name])) {
						obj[item.name] = [obj[item.name]];
					}
					obj[item.name].push(item.value);
				} else {
					obj[item.name] = item.value;
				}
			}
			return obj;
		}, {});
		updateExportLinks(); // Update export links with new filters
		loadAllStaffData();
	});

	// Update export links in staff_filters when filters change
	function updateExportLinks() {
		var filters = Object.assign({}, currentFilters);
		var queryString = $.param(filters);
		var baseUrl = '<?php echo base_url(); ?>staff/all_staff';
		
		// Update export links in top right (visible buttons)
		$('#exportButtonsTop a').each(function() {
			var href = $(this).attr('href');
			if (href && href.includes('all_staff')) {
				// Check for PDF first (before CSV, since /0/1 contains /1)
				if (href.includes('/0/1') || href.includes('/0/1?')) {
					// PDF export
					$(this).attr('href', baseUrl + '/0/1' + (queryString ? '?' + queryString : ''));
				} else if (href.includes('/1') && !href.includes('/0/1')) {
					// CSV export (but not PDF)
					$(this).attr('href', baseUrl + '/1' + (queryString ? '?' + queryString : ''));
				}
			}
		});
		
		// Also update original export buttons if they exist (for backup)
		$('.export-buttons a[href*="all_staff"]').each(function() {
			var href = $(this).attr('href');
			// Check for PDF first (before CSV, since /0/1 contains /1)
			if (href.includes('/0/1')) {
				// PDF export
				$(this).attr('href', baseUrl + '/0/1' + (queryString ? '?' + queryString : ''));
			} else if (href.includes('/1') && !href.includes('/0/1')) {
				// CSV export (but not PDF)
				$(this).attr('href', baseUrl + '/1' + (queryString ? '?' + queryString : ''));
			}
		});
	}

	// Pagination handler
	$(document).on('click', '.pagination a', function(e) {
		e.preventDefault();
		var page = $(this).data('page');
		if (page !== undefined) {
			currentPage = parseInt(page);
			loadAllStaffData();
		}
	});

	function loadAllStaffData() {
		$('#staffTableBody').html(`
			<tr>
				<td colspan="22" class="text-center">
					<div class="spinner-border text-primary" role="status">
						<span class="visually-hidden">Loading...</span>
					</div>
					<p class="mt-2">Loading staff data...</p>
				</td>
			</tr>
		`);

		var postData = Object.assign({}, currentFilters);
		postData.page = currentPage;
		postData.per_page = currentPerPage;
		postData['<?php echo $this->security->get_csrf_token_name(); ?>'] = '<?php echo $this->security->get_csrf_hash(); ?>';

		$.ajax({
			url: '<?php echo base_url(); ?>staff/get_all_staff_data_ajax',
			method: 'POST',
			data: postData,
			dataType: 'json',
			success: function(response) {
				if (response.html) {
					$('#staffTableBody').html(response.html);
					
					// Update CSRF token
					if (response.csrf_hash) {
						$('input[name="<?php echo $this->security->get_csrf_token_name(); ?>"]').val(response.csrf_hash);
					}

					// Generate pagination (both top and bottom) with total staff count
					generatePagination(response.total, response.page, response.per_page, response.records);
				} else {
					$('#staffTableBody').html('<tr><td colspan="22" class="text-center">No data available</td></tr>');
				}
			},
			error: function(xhr, status, error) {
				console.error('AJAX Error:', status, error);
				console.error('Response Text:', xhr.responseText);
				console.error('Status Code:', xhr.status);
				
				var errorMessage = 'Error loading data. Please try again.';
				if (xhr.responseText) {
					// Try to extract error message from HTML response
					var match = xhr.responseText.match(/<title>(.*?)<\/title>/i);
					if (match) {
						errorMessage = match[1];
					}
				}
				
				$('#staffTableBody').html(`
					<tr>
						<td colspan="22" class="text-center text-danger">
							${errorMessage}<br>
							<small>Status: ${xhr.status}</small>
						</td>
					</tr>
				`);
			}
		});
	}

	function generatePagination(total, page, perPage, records) {
		var totalPages = Math.ceil(total / perPage);
		var paginationHtml = '<nav><ul class="pagination pagination-sm mb-0">';
		
		// Previous button
		if (page > 0) {
			paginationHtml += `<li class="page-item"><a class="page-link" href="#" data-page="${page - 1}">Previous</a></li>`;
		} else {
			paginationHtml += '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
		}

		// Page numbers
		var startPage = Math.max(0, page - 2);
		var endPage = Math.min(totalPages - 1, page + 2);

		if (startPage > 0) {
			paginationHtml += `<li class="page-item"><a class="page-link" href="#" data-page="0">1</a></li>`;
			if (startPage > 1) {
				paginationHtml += '<li class="page-item disabled"><span class="page-link">...</span></li>';
			}
		}

		for (var i = startPage; i <= endPage; i++) {
			if (i == page) {
				paginationHtml += `<li class="page-item active"><span class="page-link">${i + 1}</span></li>`;
			} else {
				paginationHtml += `<li class="page-item"><a class="page-link" href="#" data-page="${i}">${i + 1}</a></li>`;
			}
		}

		if (endPage < totalPages - 1) {
			if (endPage < totalPages - 2) {
				paginationHtml += '<li class="page-item disabled"><span class="page-link">...</span></li>';
			}
			paginationHtml += `<li class="page-item"><a class="page-link" href="#" data-page="${totalPages - 1}">${totalPages}</a></li>`;
		}

		// Next button
		if (page < totalPages - 1) {
			paginationHtml += `<li class="page-item"><a class="page-link" href="#" data-page="${page + 1}">Next</a></li>`;
		} else {
			paginationHtml += '<li class="page-item disabled"><span class="page-link">Next</span></li>';
		}

		paginationHtml += '</ul></nav>';
		
		// Add total staff count after pagination (only for top)
		var recordsText = '<span class="text-muted ms-3"><strong>' + (records || 0) + ' Total Staff</strong></span>';
		var topHtml = paginationHtml + recordsText;
		
		// Update top pagination with total staff count, bottom without
		$('#paginationLinksTop').html(topHtml);
		$('#paginationLinks').html(paginationHtml);
	}
});
</script>