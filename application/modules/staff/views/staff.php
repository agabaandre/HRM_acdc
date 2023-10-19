<div class="table-responsive" id="staff-container">
	<!-- Table content will be dynamically loaded here -->
</div>


<script>
	$(document).ready(function() {
		$.ajax({
			url: '<?php echo base_url() ?>staff/get_staff_data_ajax',
			type: 'GET',
			dataType: 'json', // Expecting JSON response
			success: function(response) {
				if (response.html) {
					// Use html() to replace the content
					$('#staff-container').html(response.html);
				} else {
					console.error('No HTML content found in the response.');
				}
			},
			error: function(xhr, status, error) {
				console.error('AJAX Error:', status, error);
			}
		});
	});
</script>
