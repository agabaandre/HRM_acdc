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
	<p class="mb-0">Copyright © Africa CDC<?php echo date('Y') ?>. All right reserved.</p>
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

<script src="<?php echo base_url() ?>assets/js/bootstrap.bundle.min.js"></script>
  <!-- jQuery UI Library -->
<!-- Bootstrap 5 with Popper bundled -->
<!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-FHgNHNh4rHcmJ8s9jP3J7iYmMOTtMnJ0A2gU2wZSwRPmpZuUMHefPlU+GfNwH3zU" crossorigin="anonymous"></script> -->



<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.0/main.min.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/notifications/js/lobibox.min.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/notifications/js/notifications.min.js"></script>
<script src="<?php echo base_url() ?>assets/js/pace.min.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/notifications/js/notification-custom-script.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/simplebar/js/simplebar.min.js"></script>
<script src="<?php echo base_url() ?>assets/js/app.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/datatable/js/jquery.dataTables.min.js"></script>
<script src="<?php echo base_url() ?>assets/plugins/datatable/js/dataTables.bootstrap5.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>


<script src="<?php echo base_url() ?>assets/plugins/smart-wizard/js/jquery.smartWizard.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

  <!-- FullCalendar & Bootstrap JS Bundle -->
  <script>
  $(document).ready(function () {
    $('.datepicker').flatpickr({
      theme: "confetti",
      altInput: true,
      altFormat: "F j, Y",
      dateFormat: "Y-m-d",
      allowInput: true
    });
  });
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const currentYear = new Date().getFullYear();
  const minDate = `${currentYear}-01-01`;
  const maxDate = `${currentYear}-12-31`;

  flatpickr('.current_datepicker', {
    dateFormat: "Y-m-d",
    minDate: minDate,
    maxDate: maxDate,
    disableMobile: true
  });
});
</script>
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

$(document).ready(function() {
    var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
    var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
    var flag = '<?php echo $this->uri->segment(3); ?>';

    function initializeDataTable() {
        // Destroy DataTable if already initialized
        if ($.fn.DataTable.isDataTable('#staffTable')) {
            $('#staffTable').DataTable().clear().destroy();
        }

        var table = $('#staffTable').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": "<?php echo base_url('staff/contract_statuses/'); ?>" + flag,
                "type": "POST",
                "headers": {
                    "X-CSRF-TOKEN": csrfHash  // Securely pass CSRF token
                },
                "data": function(d) {
                    d[csrfName] = csrfHash; // Include CSRF token in every request
                },
                "dataSrc": function(json) {
                    console.log("DataTables Response:", json); // Debugging

                    if (!json || typeof json !== "object" || !json.data || !Array.isArray(json.data)) {
                        console.error("Invalid DataTables JSON response:", json);
                        return [];
                    }

                    return json.data;
                },
                "error": function(xhr, textStatus, errorThrown) {
                    console.error("DataTables AJAX error:", textStatus, errorThrown);
                    console.error("Server response:", xhr.responseText);
                }
            },
            "dom": 'Bfrtip', // Add buttons to the DOM
            "paging": true, // Enable pagination
            "lengthChange": true, // Enable length change dropdown
            "searching": true, // Enable search functionality
            "ordering": true, // Enable column ordering
            "info": true, // Show table information
            "autoWidth": true, // Enable auto-width for columns
            "lengthMenu": [
                [25, 50, 100, 150, -1],
                ['25', '50', '100', '150', '200', 'Show all']
            ], // Custom length menu options
            "buttons": [
            
                'csvHtml5', // Export to CSV
                'pdfHtml5', // Export to PDF
                'pageLength' // Show page length dropdown
            ],
            "columns": [
                { "data": null, "render": function (data, type, row, meta) { return meta.row + 1; } },
                { "data": "staff_id", "render": function (data, type, row) {
                    return `<a href="<?php echo base_url()?>staff/staff_contracts/${row.staff_id}">${row.fname} ${row.lname}</a>`;
                }},
                { "data": "gender" },
                { "data": "job_name" },
                { "data": "contract_type" },
                { "data": "start_date" },
                { "data": "end_date" },
                { "data": "status" },
                { "data": "comments", "render": function (data) { return data ? data.substr(0, 100) + '...' : ''; } },
                { "data": "contracting_institution" },
                { "data": "nationality" },
                { "data": "grade" },
                { "data": "division_name" },
                { "data": "job_acting" },
                { "data": "duty_station_name" },
                { "data": "work_email" },
                { "data": null, "render": function (data, type, row) {
                    let tel1 = row.tel_1 ? `<a href="tel:${row.tel_1}">${row.tel_1}</a>` : '';
                    let tel2 = row.tel_2 ? `<a href="tel:${row.tel_2}">${row.tel_2}</a>` : '';
                    return tel1 + (tel1 && tel2 ? ' | ' : '') + tel2;
                }},
                { "data": "whatsapp" },
                { "data": "funder" },
                // { "data": "status_id", "render": function (data, type, row) {
                //     return data == 3 ? `<a href="#" class="edit-contract" data-id="${row.staff_contract_id}" data-toggle="modal" data-target="#editContractModal">Edit</a>` : '';
                // }}
            ]
        });

        return table;
    }

    var table = initializeDataTable();

    // Handle edit button click
    $(document).on("click", ".edit-contract", function() {
        var contractId = $(this).data("id");

        $.ajax({
            url: "<?php echo base_url('staff/edit_contract/'); ?>" + contractId,
            type: "POST",
            data: { [csrfName]: csrfHash },
            success: function(response) {
                $("#editContractModalContainer").html(response);
                $("#editContractModal").modal("show");
            },
            error: function(xhr, textStatus, errorThrown) {
                console.error("Error loading edit modal:", textStatus, errorThrown);
            }
        });
    });

    // Ensure DataTable is not reinitialized on subsequent AJAX calls or page updates
    $(document).ajaxComplete(function() {
        if (!$.fn.DataTable.isDataTable('#staffTable')) {
            table = initializeDataTable();
        }
    });
});


$(document).ready(function() {
    $('.modal').on('shown.bs.modal', function () {
        $(this).find('.select2').select2({
            dropdownParent: $(this)
        });
    });
});

</script>
<script>
$(document).ready(function () {
    // Apply filters when the "Enter" key is pressed in text inputs
    $("input").keypress(function (event) {
        if (event.which === 13) { // 13 = Enter key
            event.preventDefault(); // Prevent default form submission
            $("#staff_form").submit(); // Submit the form
        }
    });

    // Apply filters when select fields change
    $("select").change(function () {
        $("#filters_form").submit(); // Submit the form when a select field is changed
    });
});
</script>

<script>
  function toggleTrainingSection(show) {
    const section = document.getElementById('training-section');
    section.style.display = show ? 'block' : 'none';
  }

  // Trigger visibility on page load
  $(document).ready(function () {
    if ($('#training_yes').is(':checked')) {
      toggleTrainingSection(true);
    } else if ($('#training_no').is(':checked')) {
      toggleTrainingSection(false);
    }
  });
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('staff_ppa');
  if (!form) return;

  // Create and insert global error box if not present
  let globalErrorBox = document.getElementById('form-errors');
  if (!globalErrorBox) {
    globalErrorBox = document.createElement('div');
    globalErrorBox.id = 'form-errors';
    globalErrorBox.className = 'alert alert-danger';
    globalErrorBox.style.display = 'none';
    form.prepend(globalErrorBox);
  }

  form.addEventListener('submit', function (e) {
    let validObjectives = 0;
    let totalWeight = 0;
    let isValid = true;
    const currentYear = new Date().getFullYear();
    let errorMessages = [];

    // Clear previous validation states
    document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    document.querySelectorAll('.error-message').forEach(el => el.remove());
    globalErrorBox.innerHTML = '';
    globalErrorBox.style.display = 'none';

    const rows = document.querySelectorAll('#objectives-table-body tr');
    rows.forEach((row, idx) => {
      const objective = row.querySelector('textarea[name*="[objective]"]');
      const timeline = row.querySelector('input[name*="[timeline]"]');
      const indicator = row.querySelector('textarea[name*="[indicator]"]');
      const weightInput = row.querySelector('input[name*="[weight]"]');

      let filled = 0;
      if (objective?.value.trim()) filled++;
      if (timeline?.value.trim()) filled++;
      if (indicator?.value.trim()) filled++;
      if (weightInput?.value.trim()) filled++;

      if (filled > 0) {
        let rowValid = true;

        // if (!objective?.value.trim()) {
        //   objective.classList.add('is-invalid');
        //   addError(objective, 'Objective is required');
        //   rowValid = false;
        // }

        const year = new Date(timeline?.value).getFullYear();
        if (!timeline?.value.trim() || year !== currentYear) {
          timeline.classList.add('is-invalid');
          addError(timeline, 'Timeline must be a valid date within this year');
          rowValid = false;
        }

        // if (!indicator?.value.trim()) {
        //   indicator.classList.add('is-invalid');
        //   addError(indicator, 'KPI/Deliverable is required');
        //   rowValid = false;
        // }

        const weight = parseFloat(weightInput?.value);
        if (isNaN(weight) || weight >= 100) {
          weightInput.classList.add('is-invalid');
          addError(weightInput, 'Weight must be a number less than 100');
          rowValid = false;
        }

        if (rowValid) {
          totalWeight += weight;
          validObjectives++;
        } else {
          isValid = false;
        }
      }
    });

    if (validObjectives < 3) {
      errorMessages.push('At least 3 objectives must be completed.');
      isValid = false;
    }

    if (totalWeight > 100) {
      errorMessages.push('Total weight must not exceed 100%.');
      isValid = false;
    }
	if (totalWeight < 100) {
      errorMessages.push('Total weight must not be less than 100%.');
      isValid = false;
    }

   

    if (!isValid) {
      e.preventDefault();
      if (errorMessages.length) {
        globalErrorBox.innerHTML = `<ul>${errorMessages.map(m => `<li>${m}</li>`).join('')}</ul>`;
        globalErrorBox.style.display = 'block';
        window.scrollTo({ top: form.offsetTop - 50, behavior: 'smooth' });
      }
    }
  });

  function addError(element, message) {
    const error = document.createElement('div');
    error.className = 'text-danger error-message';
    error.innerText = message;
    element.parentNode.appendChild(error);
  }
});
</script>

<script>
  $(document).ready(function () {
    const baseUrl = "<?= base_url() ?>";

    $('#staff-search').on('keyup', function () {
      let query = $(this).val().trim();

      if (query.length >= 2) {
        console.log("Searching for:", query);

        $.ajax({
          url: baseUrl + 'dashboard/search_staff',
          type: 'POST',
          data: { query: query },
          dataType: 'json',
          beforeSend: function () {
            $('#staff-results-body').html('<tr><td colspan="6" class="text-center text-muted">Searching...</td></tr>');
            $('#staff-results').show();
          },
          success: function (res) {
            console.log("Response:", res);

            if (res.length > 0) {
              let rows = '';
              res.forEach(staff => {
                rows += `
                  <tr>
                    <td>${staff.title ?? ''} ${staff.fname ?? ''} ${staff.lname ?? ''} ${staff.oname ?? ''}</td>
                    <td>${staff.gender ?? ''}</td>
                    <td>${staff.SAPNO ?? ''}</td>
                    <td>${staff.work_email ?? ''}</td>
                    <td>${staff.tel_1 ?? ''}</td>
                    <td>
                      <a href="${baseUrl}staff/staff_contracts/${staff.staff_id}" 
                         class="btn btn-sm btn-success" target="_blank">View Contract</a>
                    </td>
                  </tr>
                `;
              });
              $('#staff-results-body').html(rows);
            } else {
              $('#staff-results-body').html('<tr><td colspan="6" class="text-center text-warning">No results found.</td></tr>');
            }
          },
          error: function (xhr, status, error) {
            console.error("AJAX Error:", status, error);
            $('#staff-results-body').html(`<tr><td colspan="6" class="text-danger">Error: ${xhr.responseText}</td></tr>`);
          }
        });
      } else {
        $('#staff-results').hide();
      }
    });
  });
</script>

<script>
  document.getElementById('reveal-search-form').addEventListener('click', function () {
    // const panel = document.getElementById('searchPanel');
    // const collapseInstance = bootstrap.Collapse.getInstance(panel);

    // if (collapseInstance) {
    //   collapseInstance.toggle(); // toggles show/hide
    // } else {
    //   new bootstrap.Collapse(panel, { toggle: true });
    // }
  });
</script>

<script>
  $(document).ready(function () {
    // Get today's date at midnight (to prevent timezone issues)
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // Calculate the next Monday
    const dayOfWeek = today.getDay(); // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
    const daysUntilNextMonday = (dayOfWeek === 1) ? 7 : ((8 - dayOfWeek) % 7 || 7);
    const nextMonday = new Date(today);
    nextMonday.setDate(today.getDate() + daysUntilNextMonday);

    // Calculate the corresponding Friday
    const nextFriday = new Date(nextMonday);
    nextFriday.setDate(nextMonday.getDate() + 4); // Monday + 4 = Friday

    // Init flatpickr
    $('.activity-dates').flatpickr({
      altInput: true,
      altFormat: "F j, Y",
      dateFormat: "Y-m-d",
      allowInput: true,
      minDate: nextMonday,
      maxDate: nextFriday,
      disable: [
        function(date) {
          return date < nextMonday || date > nextFriday;
        }
      ]
    });
  });
</script>


<script>
$(document).ready(function () {
  function loadMessages() {
    $.ajax({
      url: '<?= base_url("dashboard/fetch_messages_ajax"); ?>',
      method: 'GET',
      dataType: 'json',
      success: function (messages) {
        $('#ajax-messages').empty();

        if (messages.length === 0) {
          $('#ajax-messages').html('<div class="text-center text-muted py-3">No new messages</div>');
        }

        $('#message-count').text(messages.length);

        messages.forEach(function (message) {
          let html = `
            <a class="dropdown-item" href="javascript:;">
              <div class="d-flex align-items-center mb-2">
                <div class="user-online me-2">
                  <img src="${message.avatar || 'assets/images/avatar.svg'}" class="msg-avatar rounded-circle" alt="avatar" style="width: 35px; height: 35px;">
                </div>
                <div class="flex-grow-1">
                  <h6 class="msg-name mb-1">${message.trigger}
                    <span class="msg-time float-end small">${message.time_ago}</span>
                  </h6>
                  <p class="msg-info small mb-0">${message.subject}</p>
                </div>
              </div>
            </a>`;
          $('#ajax-messages').append(html);
        });
      },
      error: function () {
        $('#ajax-messages').html('<div class="text-center text-danger py-3">Failed to load messages</div>');
      }
    });
  }

  loadMessages(); // Load on page ready
  setInterval(loadMessages, 30000); // Auto refresh every 30 sec
});
</script>

<script type="text/javascript">
  function googleTranslateElementInit() {
    new google.translate.TranslateElement({
      pageLanguage: 'en',
      autoDisplay: false,
      disableAutoHover: true,
      showBanner: false
    }, 'google_translate_element');
  }

  function GTranslateFireEvent(element, event) {
    try {
      if (document.createEventObject) {
        var evt = document.createEventObject();
        element.fireEvent('on' + event, evt);
      } else {
        var evt = document.createEvent('HTMLEvents');
        evt.initEvent(event, true, true);
        element.dispatchEvent(evt);
      }
    } catch (e) {}
  }

  function doGTranslate(lang_code) {
    var lang = lang_code || 'en';
    var interval = setInterval(function () {
      var teCombo = document.querySelector('select.goog-te-combo');
      if (teCombo && teCombo.options.length > 0) {
        var langIndex = Array.from(teCombo.options).findIndex(option => option.value === lang);
        if (langIndex !== -1) {
          teCombo.selectedIndex = langIndex;
          GTranslateFireEvent(teCombo, 'change');
          GTranslateFireEvent(teCombo, 'change');
          clearInterval(interval); // stop once applied
        }
      }
    }, 500); // retry every 500ms until successful
  }

  document.addEventListener("DOMContentLoaded", function () {
    const preferredLang = "<?= $defaultLangCode ?>";
    setTimeout(() => {
      doGTranslate(preferredLang);
    }, 1500); // delay to let Google Translate load
  });
</script>


</body>

</html>
