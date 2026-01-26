<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Staff extends MX_Controller
{


	public  function __construct()
	{
		parent::__construct();

		$this->module = "staff";
		$this->load->model("staff_mdl",'staff_mdl');
	

	}

	public function index($csv=FALSE,$pdf=FALSE)
	{
		$data['module'] = $this->module;
		$data['title'] = "Current Staff";
		$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
		$filters = $this->input->get();
		$filters['csv'] =$csv;
		$filters['pdf'] =$pdf;
		$data['divisions'] = $this->db->get('divisions')->result(); 
        $data['duty_stations'] = $this->db->get('duty_stations')->result();
		
		// Handle CSV and PDF exports
		if($csv==1){
			$staffs = $this->staff_mdl->get_active_staff_data($filters);
            $staff = $this->remove_ids($staffs);
			
			// Add age as a separate column for each staff member
			foreach ($staff as &$staff_member) {
				if (!empty($staff_member['date_of_birth'])) {
					$staff_member['age'] = calculate_age($staff_member['date_of_birth']);
				} else {
					$staff_member['age'] = 'N/A';
				}
			}
			unset($staff_member); // Break reference
			
			$file_name = 'Africa-CDC-Staff_'.date('d-m-Y-H-i').'.csv';
			render_csv_data($staff, $file_name,true);
			return;
		}
		elseif($pdf==1){
			$data['staffs'] = $this->staff_mdl->get_active_staff_data($filters);
			$pdf_name = 'Africa-CDC-Staff_'.date('d-m-Y-H-i').'.pdf';
			pdf_print_data($data, $pdf_name,'L','pdfs/staff');
			return;
		}
		
		// For normal page load, don't load staff data (will be loaded via AJAX)
		$data['staffs'] = [];
		$data['records'] = 0;
		$data['links'] = ''; // Empty links for AJAX pagination
		
		render('staff_table', $data);
	}

	// AJAX endpoint for staff index data
	public function get_staff_index_data_ajax()
	{
		// Clear any existing output
		if (ob_get_level()) {
			ob_end_clean();
		}
		ob_start();
		
		try {
			$page = (int)($this->input->post('page') ?: 0);
			$per_page = (int)($this->input->post('per_page') ?: 20);
			// Validate per_page is between 20 and 50
			if ($per_page < 20) $per_page = 20;
			if ($per_page > 50) $per_page = 50;
			$start = $page * $per_page;
			
			// Get filters from POST
			$filters = $this->input->post();
			
			// Remove non-filter fields
			unset($filters['page']);
			unset($filters['per_page']);
			$csrf_token_name = $this->security->get_csrf_token_name();
			if (isset($filters[$csrf_token_name])) {
				unset($filters[$csrf_token_name]);
			}
			
			// Get staff data
			$staffs = $this->staff_mdl->get_active_staff_data($filters, $per_page, $start);
			$count = count($this->staff_mdl->get_active_staff_data($filters));
			
			// Prepare data for view
			$data['staffs'] = $staffs;
			$data['records'] = $count;
			$data['page'] = $page;
			$data['per_page'] = $per_page;
			
			// Load table view - capture any output
			ob_start();
			$html_content = $this->load->view('staff_table_table', $data, true);
			$view_output = ob_get_clean();
			
			// If view outputted something, prepend it to html_content
			if ($view_output) {
				$html_content = $view_output . $html_content;
			}
			
			// Get new CSRF hash
			$csrf_hash = $this->security->get_csrf_hash();
			
			// Clean output buffer and send JSON
			if (ob_get_level()) {
				ob_end_clean();
			}
			
			// Helper function to sanitize UTF-8 strings
			$sanitize_utf8 = function($value) use (&$sanitize_utf8) {
				if (is_array($value)) {
					return array_map($sanitize_utf8, $value);
				} elseif (is_object($value)) {
					$result = new stdClass();
					foreach ($value as $key => $val) {
						$result->$key = $sanitize_utf8($val);
					}
					return $result;
				} elseif (is_string($value)) {
					// Use iconv to remove invalid UTF-8 sequences (most reliable method)
					if (function_exists('iconv')) {
						$value = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
					}
					// Fallback: use mb_convert_encoding if iconv failed or is not available
					if (function_exists('mb_convert_encoding')) {
						// Check if string is valid UTF-8
						if (!mb_check_encoding($value, 'UTF-8')) {
							// Try to detect encoding and convert
							$detected = @mb_detect_encoding($value, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
							if ($detected && $detected !== 'UTF-8') {
								$value = @mb_convert_encoding($value, 'UTF-8', $detected);
							} else {
								// Force conversion and remove invalid sequences
								$value = @mb_convert_encoding($value, 'UTF-8', 'UTF-8');
							}
						}
					}
					// Final validation - if still invalid, remove problematic characters
					if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
						// Remove control characters and invalid bytes
						$value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
						// Try one more time with iconv
						if (function_exists('iconv')) {
							$value = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
						}
					}
					return $value;
				}
				return $value;
			};
			
			$response = [
				'html' => $html_content,
				'total' => $count,
				'page' => $page,
				'per_page' => $per_page,
				'records' => $count,
				'csrf_hash' => $csrf_hash
			];
			
			// Sanitize all string values to valid UTF-8
			$response = $sanitize_utf8($response);
			
			$json_output = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			
			if ($json_output === false) {
				throw new Exception('JSON encoding failed: ' . json_last_error_msg());
			}
			
			$this->output
				->set_content_type('application/json; charset=utf-8')
				->set_output($json_output);
				
		} catch (Exception $e) {
			// Clean any remaining output buffers
			while (ob_get_level()) {
				ob_end_clean();
			}
			
			$this->output
				->set_content_type('application/json; charset=utf-8')
				->set_output(json_encode([
					'error' => true,
					'message' => 'Error loading data: ' . $e->getMessage(),
					'html' => '<tr><td colspan="18" class="text-center text-danger">Error loading data. Please try again.</td></tr>',
					'total' => 0,
					'page' => 0,
					'per_page' => 20,
					'records' => 0,
					'csrf_hash' => $this->security->get_csrf_hash()
				], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		}
	}

	public function search()
	{

		$data['module'] = $this->module;
		$data['title'] = "Quick Search";
	
		render('search', $data);
	}

	public function find_staff_by_email($email){
		
		$this->db->where('work_email', $email);
		$data = $this->db->get('staff')->result();
		json_encode($data);
	}

	public function profile($profile){
        $filters['staff_id']=$profile;
	
		$data['staffs'] = $this->staff_mdl->get_all_staff_data($filters,$per_page = 20, $page=0);
		
	
		$pdf_name = $data['staffs'][0]->lname.'_'.$data['staffs'][0]->fname.'_'.date('d-m-Y-H-i').'.pdf';
		
		pdf_print_data($data, $pdf_name,'P','pdfs/staff_profile');
		
		

	}

	public function profile_view($profile){
        $filters['staff_id']=$profile;
		$data['module']= $this->module;
	
		$data['staffs'] = $this->staff_mdl->get_all_staff_data($filters,$per_page = 20, $page=0);
		
	
		$pdf_name = $data['staffs'][0]->lname.'_'.$data['staffs'][0]->fname.'_'.date('d-m-Y-H-i').'.pdf';
		
	render('pdfs/staff_profile',$data);
		
		

	}

	function print_data($staffs, $file_name,$orient,$view)  
	{
	   if($orient =='L'){
	   $this->load->library('ML_pdf');
	   }
	   else{
	    $this->load->library('M_pdf');
	   }
	   // Define PDF File Name
	   $watermark = FCPATH . "assets/images/AU_CDC_Logo-800.png";
	   $filename = $file_name; 
	   // Set Execution Time to Unlimited
	   ini_set('max_execution_time', 0);
	   // Load the Specified View Dynamically and Convert to HTML

	   
	  // dd($data);
	   $html =  $this->load->view($view, $staffs,true);
	   //exit;
	   $PDFContent = mb_convert_encoding($html, 'UTF-8', 'UTF-8');
	   // Set Watermark Image (if applicable)
		

	   if($orient =='L'){
		if (!empty($watermark)) {
		$this->load->ml_pdf->pdf->SetWatermarkImage($watermark);
		$this->load->ml_pdf->pdf->showWatermarkImage = true;
		}
		// Set Footer with Timestamp and Source
		date_default_timezone_set("Africa/Addis Ababa");
		$this->load->ml_pdf->pdf->SetHTMLFooter(
			"Printed/Accessed on: <b>" . date('d F,Y h:i A') . "</b><br>" .
			"Source: Africa CDC - Staff Tracker " . base_url()
		);
	
		// Generate the PDF with the Staff Profile Data
		$this->load->ml_pdf->pdf->WriteHTML($PDFContent);
	
		// Output the PDF (Display in Browser)
		$this->load->ml_pdf->pdf->Output($filename, 'I');
		}
		else{
		if (!empty($watermark)) {
		$this->load->m_pdf->pdf->SetWatermarkImage($watermark);
		$this->load->m_pdf->pdf->showWatermarkImage = true;
		}
		
		// Set Footer with Timestamp and Source
		date_default_timezone_set("Africa/Addis Ababa");
		$this->load->m_pdf->pdf->SetHTMLFooter(
			"Printed/Accessed on: <b>" . date('d F,Y h:i A') . "</b><br>" .
			"Source: Africa CDC - Staff Tracker " . base_url()
		);
	
		// Generate the PDF with the Staff Profile Data
		$this->load->m_pdf->pdf->WriteHTML($PDFContent);
	
		// Output the PDF (Display in Browser)
		$this->load->m_pdf->pdf->Output($filename, 'I');
		}
	   
   }
   
	function remove_ids($staffs = []) {
		$keysToRemove = [
			'staff_contract_id',
			'email_disabled_at',
			'email_disabled_by',
			'job_id',
			'job_acting_id',
			'job_acting',
			'grade_id',
			'contracting_institution_id',
			'funder_id',
			'nationality_id',
			'staff_id',
			'first_supervisor',
			'second_supervisor',
			'contract_type_id',
			'duty_station_id',
			'division_id',
			'unit_id',
			'photo',
			'flag',
			'created_at',
			'updated_at',
			'status_id',
			'division_head',
			'focal_person',
			'admin_assistant',
			'finance_officer',
			'region_id',
			'email_status'

		];
		
		// If it's an array of arrays:
		foreach ($staffs as $index => $staff) {
			foreach ($keysToRemove as $key) {
				unset($staffs[$index][$key]);
			}
		}
		
		return $staffs;
	}
	
	

	public function all_staff($csv=FALSE,$pdf=FALSE)
	{
		$data['module'] = $this->module;
		$data['title'] = "All Staff (Active, Due, Expired, Under Renewal)";
		$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
		$filters = $this->input->get();
		$filters['csv'] =$csv;
		$filters['pdf'] =$pdf;
		$data['divisions'] = $this->db->get('divisions')->result(); 
        $data['duty_stations'] = $this->db->get('duty_stations')->result();
		$data['jobs'] = Modules::run('lists/jobs');
		$data['grades'] = Modules::run('lists/grades');
		
		// Handle CSV and PDF exports
		if($csv==1){
			$staffs = $this->staff_mdl->get_all_staff_data($filters);
            $staff = $this->remove_ids($staffs);
			
			// Add age as a separate column for each staff member
			foreach ($staff as &$staff_member) {
				if (!empty($staff_member['date_of_birth'])) {
					$staff_member['age'] = calculate_age($staff_member['date_of_birth']);
				} else {
					$staff_member['age'] = 'N/A';
				}
			}
			unset($staff_member); // Break reference
			
			$file_name = 'All-Africa-CDC-Staff_'.date('d-m-Y-H-i').'.csv';
			render_csv_data($staff, $file_name,true);
			return;
		}
		elseif($pdf==1){
			$data['staffs'] = $this->staff_mdl->get_all_staff_data($filters);
			$pdf_name = 'Africa-CDC-All_Staff_'.date('d-m-Y-H-i').'.pdf';
			pdf_print_data($data, $pdf_name,'L','pdfs/staff');
			return;
		}
		
		// For normal page load, don't load staff data (will be loaded via AJAX)
		$data['staffs'] = [];
		$data['records'] = 0;
		$data['links'] = ''; // Empty links for AJAX pagination
		
		render('all_staff', $data);
	}

	// AJAX endpoint for all_staff data
	public function get_all_staff_data_ajax()
	{
		// Clear any existing output
		if (ob_get_level()) {
			ob_end_clean();
		}
		ob_start();
		
		try {
			$page = (int)($this->input->post('page') ?: 0);
			$per_page = (int)($this->input->post('per_page') ?: 20);
			// Validate per_page is between 20 and 50
			if ($per_page < 20) $per_page = 20;
			if ($per_page > 50) $per_page = 50;
			$start = $page * $per_page;
			
			// Get filters from POST
			$filters = $this->input->post();
			
			// Remove non-filter fields
			unset($filters['page']);
			unset($filters['per_page']);
			$csrf_token_name = $this->security->get_csrf_token_name();
			if (isset($filters[$csrf_token_name])) {
				unset($filters[$csrf_token_name]);
			}
			
			// Get staff data
			$staffs = $this->staff_mdl->get_all_staff_data($filters, $per_page, $start);
			$count = count($this->staff_mdl->get_all_staff_data($filters));
			
			// Prepare data for view
			$data['staffs'] = $staffs;
			$data['records'] = $count;
			$data['page'] = $page;
			$data['per_page'] = $per_page;
			
			// Load table view - capture any output
			ob_start();
			$html_content = $this->load->view('all_staff_table', $data, true);
			$view_output = ob_get_clean();
			
			// If view outputted something, prepend it to html_content
			if ($view_output) {
				$html_content = $view_output . $html_content;
			}
			
			// Get new CSRF hash
			$csrf_hash = $this->security->get_csrf_hash();
			
			// Clean output buffer and send JSON
			if (ob_get_level()) {
				ob_end_clean();
			}
			
			// Helper function to sanitize UTF-8 strings
			$sanitize_utf8 = function($value) use (&$sanitize_utf8) {
				if (is_array($value)) {
					return array_map($sanitize_utf8, $value);
				} elseif (is_object($value)) {
					$result = new stdClass();
					foreach ($value as $key => $val) {
						$result->$key = $sanitize_utf8($val);
					}
					return $result;
				} elseif (is_string($value)) {
					// Use iconv to remove invalid UTF-8 sequences (most reliable method)
					if (function_exists('iconv')) {
						$value = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
					}
					// Fallback: use mb_convert_encoding if iconv failed or is not available
					if (function_exists('mb_convert_encoding')) {
						// Check if string is valid UTF-8
						if (!mb_check_encoding($value, 'UTF-8')) {
							// Try to detect encoding and convert
							$detected = @mb_detect_encoding($value, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
							if ($detected && $detected !== 'UTF-8') {
								$value = @mb_convert_encoding($value, 'UTF-8', $detected);
							} else {
								// Force conversion and remove invalid sequences
								$value = @mb_convert_encoding($value, 'UTF-8', 'UTF-8');
							}
						}
					}
					// Final validation - if still invalid, remove problematic characters
					if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
						// Remove control characters and invalid bytes
						$value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
						// Try one more time with iconv
						if (function_exists('iconv')) {
							$value = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
						}
					}
					return $value;
				}
				return $value;
			};
			
			$response = [
				'html' => $html_content,
				'total' => $count,
				'page' => $page,
				'per_page' => $per_page,
				'records' => $count,
				'csrf_hash' => $csrf_hash
			];
			
			// Sanitize all string values to valid UTF-8
			$response = $sanitize_utf8($response);
			
			$json_output = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			
			if ($json_output === false) {
				throw new Exception('JSON encoding failed: ' . json_last_error_msg());
			}
			
			$this->output
				->set_content_type('application/json; charset=utf-8')
				->set_output($json_output);
				
		} catch (Exception $e) {
			// Clean any remaining output buffers
			while (ob_get_level()) {
				ob_end_clean();
			}
			
			$this->output
				->set_content_type('application/json; charset=utf-8')
				->set_output(json_encode([
					'error' => true,
					'message' => 'Error loading data: ' . $e->getMessage(),
					'html' => '<tr><td colspan="18" class="text-center text-danger">Error loading data. Please try again.</td></tr>',
					'total' => 0,
					'page' => 0,
					'per_page' => 20,
					'records' => 0,
					'csrf_hash' => $this->security->get_csrf_hash()
				], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		}
	}

	// AJAX endpoint to get staff profile data
	public function get_staff_profile_ajax($staff_id)
	{
		// Clear any existing output (BOM, whitespace, etc.)
		if (ob_get_level()) {
			ob_end_clean();
		}
		
		// Start fresh output buffering to catch any accidental output
		ob_start();
		
		try {
			// Get staff info with more details
			$this->db->select('s.*, n.nationality');
			$this->db->from('staff s');
			$this->db->join('nationalities n', 'n.nationality_id = s.nationality_id', 'left');
			$this->db->where('s.staff_id', $staff_id);
			$staff_info = $this->db->get()->row();

			if (!$staff_info) {
				ob_end_clean();
				$this->output
					->set_content_type('application/json; charset=utf-8')
					->set_output(json_encode(['success' => false, 'message' => 'Staff not found'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
				return;
			}

			// Get latest contract - capture any output
			ob_start();
			$contract = Modules::run('staff/latest_staff_contract', $staff_id);
			$contract_output = ob_get_clean();

			// Generate staff photo - capture output
			ob_start();
			$surname = $staff_info->lname;
			$other_name = $staff_info->fname;
			$image_path = base_url() . 'uploads/staff/' . @$staff_info->photo;
			$staff_photo = generate_user_avatar($surname, $other_name, $image_path, $staff_info->photo);
			$photo_output = ob_get_clean();

			// Helper function to sanitize UTF-8 strings
			$sanitize_utf8 = function($value) use (&$sanitize_utf8) {
				if (is_array($value)) {
					return array_map($sanitize_utf8, $value);
				} elseif (is_object($value)) {
					$result = new stdClass();
					foreach ($value as $key => $val) {
						$result->$key = $sanitize_utf8($val);
					}
					return $result;
				} elseif (is_string($value)) {
					// Use iconv to remove invalid UTF-8 sequences (most reliable method)
					if (function_exists('iconv')) {
						$value = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
					}
					// Fallback: use mb_convert_encoding if iconv failed or is not available
					if (function_exists('mb_convert_encoding')) {
						// Check if string is valid UTF-8
						if (!mb_check_encoding($value, 'UTF-8')) {
							// Try to detect encoding and convert
							$detected = @mb_detect_encoding($value, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
							if ($detected && $detected !== 'UTF-8') {
								$value = @mb_convert_encoding($value, 'UTF-8', $detected);
							} else {
								// Force conversion and remove invalid sequences
								$value = @mb_convert_encoding($value, 'UTF-8', 'UTF-8');
							}
						}
					}
					// Final validation - if still invalid, remove problematic characters
					if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
						// Remove control characters and invalid bytes
						$value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
						// Try one more time with iconv
						if (function_exists('iconv')) {
							$value = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
						}
					}
					return $value;
				}
				return $value;
			};

			// Prepare response
			$response = [
				'success' => true,
				'staff' => [
					'staff_id' => $staff_info->staff_id,
					'title' => $staff_info->title ?? '',
					'fname' => $staff_info->fname ?? '',
					'lname' => $staff_info->lname ?? '',
					'oname' => $staff_info->oname ?? '',
					'SAPNO' => $staff_info->SAPNO ?? '',
					'gender' => $staff_info->gender ?? '',
					'date_of_birth' => $staff_info->date_of_birth ?? '',
					'nationality' => $staff_info->nationality ?? '',
					'nationality_id' => $staff_info->nationality_id ?? '',
					'initiation_date' => $staff_info->initiation_date ?? '',
					'work_email' => $staff_info->work_email ?? '',
					'private_email' => $staff_info->private_email ?? '',
					'tel_1' => $staff_info->tel_1 ?? '',
					'tel_2' => $staff_info->tel_2 ?? '',
					'whatsapp' => $staff_info->whatsapp ?? '',
					'physical_location' => $staff_info->physical_location ?? '',
					'photo_html' => $staff_photo
				],
				'contract' => $contract ? [
					'duty_station_name' => $contract->duty_station_name ?? '',
					'division_name' => $contract->division_name ?? '',
					'job_name' => $contract->job_name ?? '',
					'job_acting' => $contract->job_acting ?? '',
					'first_supervisor' => $contract->first_supervisor ? @staff_name($contract->first_supervisor) : '',
					'second_supervisor' => $contract->second_supervisor ? @staff_name($contract->second_supervisor) : '',
					'funder' => $contract->funder ?? '',
					'contracting_institution' => $contract->contracting_institution ?? '',
					'grade' => $contract->grade ?? '',
					'contract_type' => $contract->contract_type ?? '',
					'status' => $contract->status ?? '',
					'start_date' => $contract->start_date ?? '',
					'end_date' => $contract->end_date ?? '',
					'comments' => $contract->comments ?? ''
				] : null
			];

			// Sanitize all string values to valid UTF-8
			$response = $sanitize_utf8($response);

			// Clean any output and send JSON
			ob_end_clean();
			
			// Encode JSON and handle errors
			$json_output = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			
			if ($json_output === false) {
				$error_msg = json_last_error_msg();
				$this->output
					->set_content_type('application/json; charset=utf-8')
					->set_output(json_encode([
						'success' => false, 
						'message' => 'JSON encoding error: ' . $error_msg
					], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
				return;
			}
			
			$this->output
				->set_content_type('application/json; charset=utf-8')
				->set_output($json_output);
				
		} catch (Exception $e) {
			ob_end_clean();
			$this->output
				->set_content_type('application/json; charset=utf-8')
				->set_output(json_encode([
					'success' => false, 
					'message' => 'Error loading staff profile: ' . $e->getMessage()
				], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		}
	}

	// }
	public function get_staff_data_ajax()
	{
		$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
		$filters = $this->input->post();
		$data['staffs'] = $this->staff_mdl->get_active_staff_data($per_page = 20, $page, $filters);
		$data['links'] = pagination('staff/index', count($data['staffs']), 3);
		$html_content = $this->load->view('staff_ajax', $data, true);
		$this->output
			->set_content_type('application/json')
			->set_output(json_encode(['html' => $html_content]));

		
	}
	// Getting All Contracts
	public function staff_contracts($staff_id, $excel = FALSE)
	{
		$data['module'] = $this->module;
		$filters = array('staff_id' => $staff_id);
		
		// Get staff info with more details
		$this->db->select('s.lname, s.fname, s.oname, s.title, s.SAPNO, s.work_email, s.tel_1, s.tel_2, s.whatsapp, s.physical_location, s.gender, s.date_of_birth, s.initiation_date, s.nationality_id, s.private_email, n.nationality');
		$this->db->from('staff s');
		$this->db->join('nationalities n', 'n.nationality_id = s.nationality_id', 'left');
		$this->db->where('s.staff_id', $staff_id);
		$staff_info = $this->db->get()->row();
		
		if ($excel == 1) {
			// Export to Excel
			$contracts = $this->staff_mdl->get_staff_contracts($staff_id, FALSE, FALSE, TRUE); // Get all for export
			$file_name = 'Staff_Contracts_' . ($staff_info->lname ?? '') . '_' . date('d-m-Y-H-i') . '.csv';
			render_csv_data($contracts, $file_name, true);
			return;
		}
		
		$page = ($this->uri->segment(4)) ? $this->uri->segment(4) : 0;
		$per_page = 10;
		
		$count = $this->staff_mdl->get_staff_contracts_count($staff_id);
		$data['contracts'] = $this->staff_mdl->get_staff_contracts($staff_id, $per_page, $page);
		$data['records'] = $count;
		$data['staff_id'] = $staff_id;
		$data['this_staff'] = $staff_info;
		$data['title'] = ($staff_info->lname ?? '') . " " . ($staff_info->fname ?? '');
		$data['links'] = pagination("staff/staff_contracts/{$staff_id}", $count, $per_page);
		
		render('staff_contracts', $data);
	}
	
	// AJAX endpoint for contracts data
	public function get_contracts_data_ajax($staff_id)
	{
		$page = (int)($this->input->post('page') ?: 0);
		$per_page = 10;
		$start = $page * $per_page;
		
		$contracts = $this->staff_mdl->get_staff_contracts($staff_id, $per_page, $start);
		$count = $this->staff_mdl->get_staff_contracts_count($staff_id);
		
		// Get staff info with more details
		$this->db->select('s.lname, s.fname, s.oname, s.title, s.SAPNO, s.work_email, s.tel_1, s.tel_2, s.whatsapp, s.physical_location, s.gender, s.date_of_birth, s.initiation_date, s.nationality_id, s.private_email, n.nationality');
		$this->db->from('staff s');
		$this->db->join('nationalities n', 'n.nationality_id = s.nationality_id', 'left');
		$this->db->where('s.staff_id', $staff_id);
		$staff_info = $this->db->get()->row();
		
		$data['contracts'] = $contracts;
		$data['this_staff'] = $staff_info;
		$data['staff_id'] = $staff_id;
		$data['page'] = $page;
		
		$html_content = $this->load->view('staff_contracts_table', $data, true);
		
		// Get new CSRF hash in case it was regenerated
		$csrf_hash = $this->security->get_csrf_hash();
		
		$this->output
			->set_content_type('application/json')
			->set_output(json_encode([
				'html' => $html_content,
				'total' => $count,
				'page' => $page,
				'per_page' => $per_page,
				'csrf_hash' => $csrf_hash
			]));
	}
	// Getting latest Contract
	public function latest_staff_contract($staff_id)
	{
		$data = $this->staff_mdl->get_latest_contracts($staff_id);
		//dd($data);
		return $data;
	}

	// New Contract
	public function new_contract($staff_id){
		$data['module'] = $this->module;
		
		$data['staff_id'] = $staff_id;
		
		// Get staff info directly from staff table (doesn't depend on contracts)
		$this->db->select('s.*, n.nationality');
		$this->db->from('staff s');
		$this->db->join('nationalities n', 'n.nationality_id = s.nationality_id', 'left');
		$this->db->where('s.staff_id', $staff_id);
		$staff_info = $this->db->get()->row();
		
		if (!$staff_info) {
			// Staff not found
			Modules::run('utility/setFlash', [
				'msg' => 'Staff member not found.',
				'type' => 'error'
			]);
			redirect('staff/index');
		}
		
		// Get latest contract for default values (if exists)
		$latest_contract = $this->staff_mdl->get_latest_contracts($staff_id);
		
		// Get the most recent contract's status (this will be the "previous" contract when we create a new one)
		$previous_contract_status = null;
		$this->db->select('status_id');
		$this->db->from('staff_contracts');
		$this->db->where('staff_id', $staff_id);
		$this->db->order_by('staff_contract_id', 'DESC');
		$this->db->limit(1);
		$most_recent_contract = $this->db->get()->row();
		if ($most_recent_contract) {
			$previous_contract_status = (int)$most_recent_contract->status_id;
		}
		
		// Create a staff object with default values from latest contract or staff info
		$staff_obj = new stdClass();
		$staff_obj->staff_id = $staff_id;
		$staff_obj->lname = $staff_info->lname;
		$staff_obj->fname = $staff_info->fname;
		$staff_obj->oname = $staff_info->oname ?? '';
		$staff_obj->title = $staff_info->title ?? '';
		$staff_obj->job_id = $latest_contract->job_id ?? null;
		$staff_obj->job_acting_id = $latest_contract->job_acting_id ?? null;
		$staff_obj->grade_id = $latest_contract->grade_id ?? null;
		$staff_obj->contracting_institution_id = $latest_contract->contracting_institution_id ?? null;
		$staff_obj->funder_id = $latest_contract->funder_id ?? null;
		$staff_obj->first_supervisor = $latest_contract->first_supervisor ?? null;
		$staff_obj->second_supervisor = $latest_contract->second_supervisor ?? null;
		$staff_obj->contract_type_id = $latest_contract->contract_type_id ?? null;
		$staff_obj->duty_station_id = $latest_contract->duty_station_id ?? null;
		$staff_obj->division_id = $latest_contract->division_id ?? null;
		$staff_obj->status_id = $latest_contract->status_id ?? null;
		
		$data['staffs'] = [$staff_obj]; // Wrap in array for view compatibility
		$data['title'] = ($staff_info->lname ?? '') . " " . ($staff_info->fname ?? '');
		$data['contracts'] = $this->staff_mdl->get_staff_contracts($staff_id);
		$data['previous_contract_status'] = $previous_contract_status; // Pass previous contract status to view
		
		render('new_contract', $data);
	}

	

	function timer_after($time,$function)
	{
		// Access the event loop
		$loop = $this->reactphp_lib->getLoop();
		$loop->addTimer($time, function () {
			
		});
		$this->reactphp_lib->run();
	}


	// Add New Contract
	public function add_new_contract(){
		$data['module'] = $this->module;
		$data = $this->input->post();
		//dd($data);
		$new_contract = $this->staff_mdl->add_new_contract($data);
		//dd($new_contract);
		$staffid = $this->input->post('staff_id');
		$this->notify_contract_status_change($data);
		
		// Get the previous contract ID
		$previous_contract_id = $this->staff_mdl->previous_contract($staffid, $new_contract);
		
		// Only update previous contract if it exists and is not separated
		if ($previous_contract_id) {
			// Get the current status of the previous contract
			$this->db->select('status_id');
			$this->db->from('staff_contracts');
			$this->db->where('staff_contract_id', $previous_contract_id);
			$previous_contract = $this->db->get()->row();
			
			// If previous contract is separated (status 4), don't change it
			// Otherwise, update it with the form value
			if ($previous_contract && (int)$previous_contract->status_id !== 4) {
		$update['staff_id'] = $data['staff_id'];
				$update['staff_contract_id'] = $previous_contract_id;
				$update['status_id'] = $data['previous_contract_status_id'];
		$this->staff_mdl->update_contract($update);
			}
			// If status is 4 (separated), skip the update - it stays as separated
		}
		
		// Set flash message for notification
		if ($new_contract) {
			$msg = array(
				'msg' => 'New contract created successfully.',
				'type' => 'success'
			);
		} else {
			$msg = array(
				'msg' => 'Failed to create new contract. Please try again.',
				'type' => 'error'
			);
		}
		Modules::run('utility/setFlash', $msg);
		
		redirect("staff/staff_contracts/".$staffid);
	}




	public function contract_status($status, $csv=FALSE,$pdf=FALSE)
	{

		$data['module'] = $this->module;
		if ($status == 2) {
			$data['title'] = "Due Contracts";
		}
		else if ($status == 3) {
			$data['title'] = "Expired Contracts";
		} 
		else if ($status == 4) {
			$data['title'] = "Former Staff";
		}
		else if ($status == 7) {
			$data['title'] = "Under Renewal";
		}
		else if ($status == 6) {
			$data['title'] = "Renewed Contracts";
		}
		else if ($status == 5) {
			$data['title'] = "Re Assigned Staff";
		}
		$page = ($this->uri->segment(4)) ? $this->uri->segment(4) : 0;
		$filters = $this->input->get();
		$filters['csv'] =$csv;
		$filters['pdf'] =$pdf;
		$filters['status_id'] =$status;	
		$per_page=20;
		
		// Handle CSV and PDF exports
		if($csv==1){
			$staffs = $this->staff_mdl->get_status($filters);
            $staff = $this->remove_ids($staffs);
			
			// Add age as a separate column for each staff member
			foreach ($staff as &$staff_member) {
				if (!empty($staff_member['date_of_birth'])) {
					$staff_member['age'] = calculate_age($staff_member['date_of_birth']);
				} else {
					$staff_member['age'] = 'N/A';
				}
			}
			unset($staff_member); // Break reference
			
			$file_name = $data['title'].'_Africa CDC Staff_'.date('d-m-Y-H-i').'.csv';
			render_csv_data($staff, $file_name,true);
			return;
		}
		elseif($pdf==1){
			$data['staffs'] = $this->staff_mdl->get_status($filters);
			$pdf_name = str_replace(' ','',$data['title']) .'_Africa-CDC-Staff_'.date('d-m-Y-H-i').'.pdf';
			pdf_print_data($data, $pdf_name,'L','pdfs/staff');
			return;
		}
		
		// For normal page load, don't load staff data (will be loaded via AJAX)
		$data['staffs'] = [];
        $data['divisions'] = $this->db->get('divisions')->result(); 
        $data['duty_stations'] = $this->db->get('duty_stations')->result();
		$data['jobs'] = Modules::run('lists/jobs');
		$data['grades'] = Modules::run('lists/grades');
		$data['records'] = 0;
		$data['status'] = $status;
		$data['links'] = ''; // Empty links for AJAX pagination
		
		render('contract_status', $data);
	}

	// AJAX endpoint for contract status data
	public function get_contract_status_data_ajax($status)
	{
		// Clear any existing output
		if (ob_get_level()) {
			ob_end_clean();
		}
		ob_start();
		
		try {
			$page = (int)($this->input->post('page') ?: 0);
			$per_page = (int)($this->input->post('per_page') ?: 20);
			// Validate per_page is between 20 and 50
			if ($per_page < 20) $per_page = 20;
			if ($per_page > 50) $per_page = 50;
			$start = $page * $per_page;
			
			// Get filters from POST
			$filters = $this->input->post();
			$filters['status_id'] = $status;
			
			// Remove non-filter fields
			unset($filters['page']);
			unset($filters['per_page']);
			$csrf_token_name = $this->security->get_csrf_token_name();
			if (isset($filters[$csrf_token_name])) {
				unset($filters[$csrf_token_name]);
			}
			
			// Get staff data
			$staffs = $this->staff_mdl->get_status($filters, $per_page, $start);
			$count = count($this->staff_mdl->get_status($filters));
			
			// Prepare data for view
			$data['staffs'] = $staffs;
			$data['records'] = $count;
			$data['page'] = $page;
			$data['per_page'] = $per_page;
			$data['status'] = $status;
			
			// Load table view - capture any output
			ob_start();
			$html_content = $this->load->view('contract_status_table', $data, true);
			$view_output = ob_get_clean();
			
			// If view outputted something, prepend it to html_content
			if ($view_output) {
				$html_content = $view_output . $html_content;
			}
			
			// Get new CSRF hash
			$csrf_hash = $this->security->get_csrf_hash();
			
			// Clean output buffer and send JSON
			if (ob_get_level()) {
				ob_end_clean();
			}
			
			// Helper function to sanitize UTF-8 strings
			$sanitize_utf8 = function($value) use (&$sanitize_utf8) {
				if (is_array($value)) {
					return array_map($sanitize_utf8, $value);
				} elseif (is_object($value)) {
					$result = new stdClass();
					foreach ($value as $key => $val) {
						$result->$key = $sanitize_utf8($val);
					}
					return $result;
				} elseif (is_string($value)) {
					// Use iconv to remove invalid UTF-8 sequences (most reliable method)
					if (function_exists('iconv')) {
						$value = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
					}
					// Fallback: use mb_convert_encoding if iconv failed or is not available
					if (function_exists('mb_convert_encoding')) {
						// Check if string is valid UTF-8
						if (!mb_check_encoding($value, 'UTF-8')) {
							// Try to detect encoding and convert
							$detected = @mb_detect_encoding($value, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
							if ($detected && $detected !== 'UTF-8') {
								$value = @mb_convert_encoding($value, 'UTF-8', $detected);
							} else {
								// Force conversion and remove invalid sequences
								$value = @mb_convert_encoding($value, 'UTF-8', 'UTF-8');
							}
						}
					}
					// Final validation - if still invalid, remove problematic characters
					if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
						// Remove control characters and invalid bytes
						$value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
						// Try one more time with iconv
						if (function_exists('iconv')) {
							$value = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
						}
					}
					return $value;
				}
				return $value;
			};
			
			$response = [
				'html' => $html_content,
				'total' => $count,
				'page' => $page,
				'per_page' => $per_page,
				'records' => $count,
				'csrf_hash' => $csrf_hash
			];
			
			// Sanitize all string values to valid UTF-8
			$response = $sanitize_utf8($response);
			
			$json_output = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			
			if ($json_output === false) {
				throw new Exception('JSON encoding failed: ' . json_last_error_msg());
			}
			
			$this->output
				->set_content_type('application/json; charset=utf-8')
				->set_output($json_output);
				
		} catch (Exception $e) {
			// Clean any remaining output buffers
			while (ob_get_level()) {
				ob_end_clean();
			}
			
			$this->output
				->set_content_type('application/json; charset=utf-8')
				->set_output(json_encode([
					'error' => true,
					'message' => 'Error loading data: ' . $e->getMessage(),
					'html' => '<tr><td colspan="18" class="text-center text-danger">Error loading data. Please try again.</td></tr>',
					'total' => 0,
					'page' => 0,
					'per_page' => 20,
					'records' => 0,
					'csrf_hash' => $this->security->get_csrf_hash()
				], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		}
	}

	public function contract_statuses($status) {
		$data['module'] = $this->module;
	
		// Set Titles Based on Status
		$titles = [
			2 => "Due Contracts",
			3 => "Expired Contracts",
			4 => "Former Staff",
			5 => "Re Assigned Staff",
			6 => "Renewed Contracts",
			7 => "Under Renewal"
		];
	
		$data['title'] = $titles[$status] ?? "Contracts";
	
		// Fetch Staff Data
		$staffData = $this->staff_mdl->get_status($status);
	
		// Ensure CSRF Token is Updated
		$csrf = [
			"csrf_token_name" => $this->security->get_csrf_token_name(),
			"csrf_token_hash" => $this->security->get_csrf_hash()
		];
	
		// Return Data with Updated CSRF Token
		echo json_encode([
			"draw" => $_POST['draw'] ?? null,
			"recordsTotal" => count($staffData),
			"recordsFiltered" => count($staffData),
			"data" => $staffData,
			"csrf" => $csrf // Include new CSRF token
		]);
	}
	
		
	public function staff_birthday()
	{
		$data['module'] = $this->module;
		$data['title'] = "Staff Birthday";
		$data['today'] = $this->staff_mdl->getBirthdays(0);
		$data['tomorrow'] = $this->staff_mdl->getBirthdays(1);
		$data['week'] = $this->staff_mdl->getBirthdays(7);
		$data['month'] = $this->staff_mdl->getBirthdays(30);
		//dd($data['month']);
		render('staff_birthday', $data);
	}

	public function update_contract()
	{
		$data = $this->input->post();
		
		// Handle other_associated_divisions - convert array to JSON
		$other_divisions = $this->input->post('other_associated_divisions');
		$other_divisions_json = null;
		if (!empty($other_divisions) && is_array($other_divisions)) {
			// Filter out empty values
			$other_divisions = array_filter($other_divisions);
			if (!empty($other_divisions)) {
				$other_divisions_json = json_encode(array_values($other_divisions));
			}
		}
		$data['other_associated_divisions'] = $other_divisions_json;
		
		$staffid = $data['staff_id'];
		$q= $this->staff_mdl->update_contract($data);

		 
		//dd($data);
		//$this->notify_contract_status_change($data);
		if ($q) {
			$msg = array(
				'msg' => 'Staff Updated successfully.',
				'type' => 'success'
			);
			
			
		}
		else{
			$msg = array(
				'msg' => 'Updated Failed!.',
				'type' => 'error'
			);

		}
		Modules::run('utility/setFlash', $msg);
		redirect("staff/staff_contracts/".$staffid);
	}

	public function notify_contract_status_change($data) {
		     $staff_id = $data['staff_id'];
		        if($data['status_id']==7){
				
				$data['subject'] = "Staff Contract Under Renewal Notice";
				$data['date_2'] = date('Y-m-d');
				$supervisor_id = $this->staff_mdl->get_latest_contracts($staff_id)->first_supervisor;
				$first_supervisor_mail =staff_details($supervisor_id)->work_email;
				$copied_mails = settings()->contracts_status_copied_emails;
				$data['email_to'] = staff_details($staff_id)->work_email.';'.$copied_mails.';'.	$first_supervisor_mail;
				$data['name'] = staff_name($staff_id);
				
					// Load the view and return its output as a string.
				$data['body'] = $this->load->view('emails/under_review', $data, true);
				//dd($data);
				$id = $this->session->userdata('user')->staff_id;
				$trigger=staff_name($id);
				$dispatch = date('Y-m-d H:i:s');
				$entry_id = $staff_id.'UR'.date('Y-m-d');
				return golobal_log_email($trigger,$data['email_to'], $data['body'], $data['subject'], $staff_id, $data['date_2'],$dispatch,md5($entry_id));
				}
				else  if($data['status_id']==4){
		
				$data['subject'] = "Staff Contract Separation Notice";
				$data['date_2'] = date('Y-m-d');
				$supervisor_id = $this->staff_mdl->get_latest_contracts($staff_id)->first_supervisor;
				$first_supervisor_mail =staff_details($supervisor_id)->work_email;
				$copied_mails = settings()->contracts_status_copied_emails;
				$data['email_to'] = staff_details($staff_id)->work_email.';'.$copied_mails.';'.	$first_supervisor_mail;
				$data['name'] = staff_name($staff_id);
						// Load the view and return its output as a string.
				$data['body'] = $this->load->view('emails/separated', $data, true);
				$id = $this->session->userdata('user')->staff_id;
				$trigger=staff_name($id);
				$dispatch = date('Y-m-d H:i:s');
				$entry_id = $staff_id.'-SP-'.date('Y-m-d');
				return golobal_log_email($trigger,$data['email_to'], $data['body'], $data['subject'], $staff_id, $data['date_2'],$dispatch,md5($entry_id));
			
				}
				else  if($data['status_id']==1){
		
					$data['subject'] = "Contract Notice";
					$supervisor_id = $this->staff_mdl->get_latest_contracts($staff_id)->first_supervisor;
					$first_supervisor_mail =staff_details($supervisor_id)->work_email;
					$copied_mails = settings()->contracts_status_copied_emails;
					$data['email_to'] = staff_details($staff_id)->work_email.';'.$copied_mails.';'.	$first_supervisor_mail;
					$data['name'] = staff_name($staff_id);
							// Load the view and return its output as a string.
					$data['body'] = $this->load->view('emails/new_contract', $data, true);
					$id = $this->session->userdata('user')->staff_id;
					$trigger=staff_name($id);
					$dispatch = date('Y-m-d H:i:s');
					$data['date_2'] = date('Y-m-d');
					$entry_id = $staff_id.'-NC-'.date('Y-m-d');
					return golobal_log_email($trigger,$data['email_to'], $data['body'], $data['subject'], $staff_id, $data['date_2'],$dispatch,md5($entry_id));
				
					}	
				
	}
	public function update_staff()
	{
		// Check if this is an AJAX request
		$is_ajax = $this->input->is_ajax_request() || $this->input->post('ajax');
		
		$data = $this->input->post();
		
		// Remove non-database fields
		unset($data['ajax']);
		$csrf_token_name = $this->security->get_csrf_token_name();
		if (isset($data[$csrf_token_name])) {
			unset($data[$csrf_token_name]);
		}
		
		$q = $this->staff_mdl->update_staff($data);
		
		if ($is_ajax) {
			// Return JSON response for AJAX requests
			if ($q) {
				$this->output
					->set_content_type('application/json; charset=utf-8')
					->set_output(json_encode([
						'success' => true,
						'q' => true,
						'msg' => 'Staff Updated successfully.'
					], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
			} else {
				$this->output
					->set_content_type('application/json; charset=utf-8')
					->set_output(json_encode([
						'success' => false,
						'q' => false,
						'msg' => 'Staff update Failed.'
					], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
			}
			return;
		}
		
		// Regular form submission
		if ($q) {
			$msg = array(
				'msg' => 'Staff Updated successfully.',
				'type' => 'success'
			);
			Modules::run('utility/setFlash', $msg);
		} else {
			$msg = array(
				'msg' => 'Staff update Failed .',
				'type' => 'error'
			);
			Modules::run('utility/setFlash', $msg);

		}
		redirect('staff');
	}

	// Controller: Staff.php

// Method to load the form only
public function new()
{
    $data['module'] = $this->module;
    $data['title']  = "New Staff";
	$data['divisions'] = $this->db->get('divisions')->result(); 
    $data['duty_stations'] = $this->db->get('duty_stations')->result(); // 
    // Render the view with your form (e.g., new_staff.php)
    render('new_staff', $data);
}

// Method to process the form submission via AJAX
public function new_submit()
{
    // Check if it's a POST request
    if ($this->input->post()) {

	

        // Personal Information
        $sapno          = $this->input->post('SAPNO');
        $title          = $this->input->post('title');
        $fname          = $this->input->post('fname');
        $lname          = $this->input->post('lname');
        $oname          = $this->input->post('oname');
        $dob            = date('Y-m-d', strtotime($this->input->post('date_of_birth')));
        $gender         = $this->input->post('gender');
        $nationality_id = $this->input->post('nationality_id');
        $initiation_date= date('Y-m-d', strtotime($this->input->post('initiation_date')));

        // Contact Information
        $tel_1            = $this->input->post('tel_1');
        $tel_2            = $this->input->post('tel_2');
        $whatsapp         = $this->input->post('whatsapp');
        $work_email       = $this->input->post('work_email');
        $private_email    = $this->input->post('private_email');
        $physical_location= $this->input->post('physical_location');

        // Contract Information
        $job_id                    = $this->input->post('job_id');
        $job_acting_id             = $this->input->post('job_acting_id');
        $grade_id                  = $this->input->post('grade_id');
        $contracting_institution_id= $this->input->post('contracting_institution_id');
        $funder_id                 = $this->input->post('funder_id');
        $first_supervisor          = $this->input->post('first_supervisor');
        $second_supervisor         = $this->input->post('second_supervisor');
        $contract_type_id          = $this->input->post('contract_type_id');
        $duty_station_id           = $this->input->post('duty_station_id');
        $division_id               = $this->input->post('division_id');
        $unit_id                   = $this->input->post('unit_id');
        $start_date                = date('Y-m-d', strtotime($this->input->post('start_date')));
        $end_date                  = date('Y-m-d', strtotime($this->input->post('end_date')));
        $status_id                 = $this->input->post('status_id');
        $file_name                 = $this->input->post('file_name');
        $comments                  = $this->input->post('comments');
        
        // Handle other_associated_divisions - convert array to JSON
        $other_divisions = $this->input->post('other_associated_divisions');
        $other_divisions_json = null;
        if (!empty($other_divisions) && is_array($other_divisions)) {
            // Filter out empty values
            $other_divisions = array_filter($other_divisions);
            if (!empty($other_divisions)) {
                $other_divisions_json = json_encode(array_values($other_divisions));
            }
        }

		//dd($end_date);

        // Save to database (first save staff, then contract information)
        $staff_id = $this->staff_mdl->add_staff(
            $sapno, $title, $fname, $lname, $oname, $dob, $gender, 
            $nationality_id, $initiation_date, $tel_1, $tel_2, $whatsapp, 
            $work_email, $private_email, $physical_location
        );

        if ($staff_id) {
            $contract_id = $this->staff_mdl->add_contract_information($staff_id, $job_id, $job_acting_id, $grade_id, $contracting_institution_id, $funder_id, $first_supervisor, $second_supervisor, $contract_type_id, $duty_station_id, $division_id, $unit_id, $other_divisions_json, $start_date, $end_date, $status_id, $file_name, $comments);
            if ($contract_id) {
				$data['status_id']=1;
				$data['staff_id']=$staff_id;
				$this->notify_contract_status_change($data);
                $response = array(
					'staff_id'=>$staff_id,
                    'msg'  => 'Staff information saved successfully.',
                    'type' => 'success'
                );
            } else {
                $response = array(
					'staff_id'=>$staff_id,
                    'msg'  => 'Failed, please Retry',
                    'type' => 'error'
                );
            }
        } else {
            $response = array(
                'msg'  => 'Failed, please Retry',
                'type' => 'error'
            );
        }
        // Return JSON response
        echo json_encode($response);
    } else {
        // If not POST, return an error message
        echo json_encode(array('msg' => 'Invalid request', 'type' => 'error'));
    }
}
public function check_work_email()
{
    $email = $this->input->post('work_email');

    $staff = $this->db->select('fname, lname')
                      ->where('work_email', $email)
                      ->get('staff')
                      ->row();

    if ($staff) {
        echo json_encode([
            'exists' => true,
            'name' => $staff->fname . ' ' . $staff->lname
        ]);
    } else {
        echo json_encode(['exists' => false]);
    }
}


	
}