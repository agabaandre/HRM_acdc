<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Share extends MX_Controller
{


	public  function __construct()
	{
		parent::__construct();

		$this->module = "share";
		$this->load->model("apm_mdl");
	}

	public function index()
	{

	echo "Welcome to the staff Tracker API";
	}

	/**
	 * Validate session endpoint for Laravel app integration
	 */
	public function validate_session()
	{
		try {
			// Get the authorization header
			$headers = $this->input->request_headers();
			$authHeader = $headers['Authorization'] ?? '';
			
			if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
				http_response_code(401);
				echo json_encode([
					'success' => false,
					'message' => 'No valid authorization token provided',
					'session_expired' => true
				]);
				return;
			}
			
			$token = substr($authHeader, 7); // Remove 'Bearer ' prefix
			
			// Decode and validate the token
			$decodedToken = base64_decode($token);
			$userData = json_decode($decodedToken, true);
			
			if (!$userData || !isset($userData['staff_id'])) {
				http_response_code(401);
				echo json_encode([
					'success' => false,
					'message' => 'Invalid token format',
					'session_expired' => true
				]);
				return;
			}
			
			// Check if user exists and is active
			$staffId = $userData['staff_id'];
			$query = $this->db->get_where('staff', ['staff_id' => $staffId]);
			
			if ($query->num_rows() === 0) {
				http_response_code(401);
				echo json_encode([
					'success' => false,
					'message' => 'User not found',
					'session_expired' => true
				]);
				return;
			}
			
			$staff = $query->row_array();
			
			// Check if staff is active
			if ($staff['status'] !== 'active') {
				http_response_code(401);
				echo json_encode([
					'success' => false,
					'message' => 'User account is not active',
					'session_expired' => true
				]);
				return;
			}
			
			// Session is valid
			http_response_code(200);
			echo json_encode([
				'success' => true,
				'message' => 'Session is valid',
				'session_expired' => false,
				'user' => [
					'staff_id' => $staff['staff_id'],
					'name' => $staff['fname'] . ' ' . $staff['lname'],
					'email' => $staff['work_email']
				]
			]);
			
		} catch (Exception $e) {
			log_message('error', 'Session validation failed: ' . $e->getMessage());
			http_response_code(500);
			echo json_encode([
				'success' => false,
				'message' => 'Session validation failed',
				'session_expired' => true
			]);
		}
	}

	/**
	 * Refresh token endpoint for Laravel app integration
	 */
	public function refresh_token()
	{
		try {
			// Get the authorization header
			$headers = $this->input->request_headers();
			$authHeader = $headers['Authorization'] ?? '';
			
			if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
				http_response_code(401);
				echo json_encode([
					'success' => false,
					'message' => 'No valid authorization token provided'
				]);
				return;
			}
			
			$token = substr($authHeader, 7); // Remove 'Bearer ' prefix
			
			// Decode and validate the token
			$decodedToken = base64_decode($token);
			$userData = json_decode($decodedToken, true);
			
			if (!$userData || !isset($userData['staff_id'])) {
				http_response_code(401);
				echo json_encode([
					'success' => false,
					'message' => 'Invalid token format'
				]);
				return;
			}
			
			// Generate new token with extended expiry
			$newUserData = $userData;
			$newUserData['token_issued_at'] = time();
			$newUserData['token_expires_at'] = time() + (2 * 60 * 60); // 2 hours from now
			
			$newToken = base64_encode(json_encode($newUserData));
			
			http_response_code(200);
			echo json_encode([
				'success' => true,
				'message' => 'Token refreshed successfully',
				'token' => $newToken,
				'expires_at' => date('c', $newUserData['token_expires_at'])
			]);
			
		} catch (Exception $e) {
			log_message('error', 'Token refresh failed: ' . $e->getMessage());
			http_response_code(500);
			echo json_encode([
				'success' => false,
				'message' => 'Token refresh failed'
			]);
		}
	}
	


	public function staff()
{
    if ($this->api_login())  {
        try {
            $sql11 = "SELECT sc.start_date, sc.end_date, f.funder, s.SAPNO, st.status, ds.duty_station_name, s.title, s.fname, s.lname, s.oname, g.grade, s.date_of_birth, s.gender, j.job_name, ja.job_acting, ci.contracting_institution, ct.contract_type, n.nationality, d.division_name, sc.first_supervisor, sc.second_supervisor, ds.duty_station_name, s.initiation_date, s.tel_1, s.tel_2, s.whatsapp, s.work_email, s.private_email, s.physical_location 
                FROM staff s, staff_contracts sc, grades g, nationalities n, divisions d, duty_stations ds, contracting_institutions ci, contract_types ct, jobs_acting ja, status st, jobs j, funders f 
                WHERE n.nationality_id = s.nationality_id 
                  AND d.division_id = sc.division_id 
                  AND ds.duty_station_id = sc.duty_station_id 
                  AND ci.contracting_institution_id = sc.contracting_institution_id 
                  AND ct.contract_type_id = sc.contract_type_id 
                  AND s.staff_id = sc.staff_id 
                  AND sc.grade_id = g.grade_id 
                  AND ja.job_acting_id = sc.job_acting_id 
                  AND st.status_id = sc.status_id 
                  AND sc.status_id IN (1,2,7) 
                  AND j.job_id = sc.job_id 
                  AND f.funder_id = sc.funder_id 
                  AND s.work_email != '' 
                  AND sc.division_id != '' 
                  AND sc.division_id != '27' 
                  AND s.work_email NOT LIKE 'xx%'";

            $result = $this->db->query($sql11)->result_array();

            $data = array(); // Initialize $data array

            foreach ($result as $row):
                $row['start_date']       = date('Y-m-d', strtotime($row['start_date']));
                $row['end_date']         = date('Y-m-d', strtotime($row['end_date']));
                $row['date_of_birth']    = date('Y-m-d', strtotime($row['date_of_birth']));
                $row['initiation_date']  = date('Y-m-d', strtotime($row['initiation_date']));
                $f = $row['first_supervisor'];
                $s = $row['second_supervisor'];

                @$row['first_supervisor_email']  = $this->get_supervisor_mail($f);
                @$row['second_supervisor_email'] = $this->get_supervisor_mail($s);

                // Concatenate first name, other name (if available) and last name into full name
                if (!empty($row['oname'])) {
                    $row['name'] = trim($row['fname']) . ' ' . trim($row['oname']) . ' ' . trim($row['lname']);
                } else {
                    $row['name'] = trim($row['fname']) . ' ' . trim($row['lname']);
                }

                // Remove unnecessary keys
                unset($row['fname'], $row['lname'], $row['oname'], $row['first_supervisor'], $row['second_supervisor']);
                $data[] = $row;
            endforeach;

            // Return JSON response
            header('Content-Type: application/json');
            http_response_code(200);
            echo json_encode($data);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(array('success' => false, 'error' => 'Database error: ' . $e->getMessage()));
        }
    } else {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(array('success' => false, 'error' => 'Authentication Failed! Invalid Request'));
    }
}

public function visualise()
{
    if ($this->api_login()) {
        try {
            $sql11 = "SELECT sc.start_date, sc.end_date, f.funder, s.SAPNO, st.status, ds.duty_station_name, s.title, s.fname, s.lname, s.oname, g.grade, s.date_of_birth, s.gender, j.job_name, ja.job_acting, ci.contracting_institution, ct.contract_type, n.nationality, d.division_name, sc.first_supervisor, sc.second_supervisor, ds.duty_station_name, s.initiation_date, s.tel_1, s.tel_2, s.whatsapp, s.work_email, s.private_email, s.physical_location 
                FROM staff s, staff_contracts sc, grades g, nationalities n, divisions d, duty_stations ds, contracting_institutions ci, contract_types ct, jobs_acting ja, status st, jobs j, funders f 
                WHERE n.nationality_id = s.nationality_id 
                  AND d.division_id = sc.division_id 
                  AND ds.duty_station_id = sc.duty_station_id 
                  AND ci.contracting_institution_id = sc.contracting_institution_id 
                  AND ct.contract_type_id = sc.contract_type_id 
                  AND s.staff_id = sc.staff_id 
                  AND sc.grade_id = g.grade_id 
                  AND ja.job_acting_id = sc.job_acting_id 
                  AND st.status_id = sc.status_id 
                  AND sc.status_id IN (1,2,3,7) 
                  AND j.job_id = sc.job_id 
                  AND f.funder_id = sc.funder_id 
                  AND sc.division_id != '' 
                  AND sc.division_id != '27'";

            $result = $this->db->query($sql11)->result_array();

            $data = array(); // Initialize $data array

            foreach ($result as $row):
                $row['start_date']       = date('Y-m-d', strtotime($row['start_date']));
                $row['end_date']         = date('Y-m-d', strtotime($row['end_date']));
                $row['date_of_birth']    = date('Y-m-d', strtotime($row['date_of_birth']));
                $row['initiation_date']  = date('Y-m-d', strtotime($row['initiation_date']));
                $f = $row['first_supervisor'];
                $s = $row['second_supervisor'];

                @$row['first_supervisor_email']  = $this->get_supervisor_mail($f);
                @$row['second_supervisor_email'] = $this->get_supervisor_mail($s);

                // Concatenate first name, other name (if available) and last name into full name
                if (!empty($row['oname'])) {
                    $row['name'] = trim($row['fname']) . ' ' . trim($row['oname']) . ' ' . trim($row['lname']);
                } else {
                    $row['name'] = trim($row['fname']) . ' ' . trim($row['lname']);
                }

                // Remove unnecessary keys
                unset($row['fname'], $row['lname'], $row['oname'], $row['first_supervisor'], $row['second_supervisor']);
                $data[] = $row;
            endforeach;

            // Return JSON response
            header('Content-Type: application/json');
            http_response_code(200);
            echo json_encode($data);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(array('success' => false, 'error' => 'Database error: ' . $e->getMessage()));
        }
    } else {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(array('success' => false, 'error' => 'Authentication Failed! Invalid Request'));
    }
}

	function get_supervisor_mail($h){
   
    //Collect first and second supervisor email
    if (empty($h) || !is_numeric($h)) {
        return null;
    }
    
    $result12 = $this->db->query("SELECT work_email FROM staff WHERE staff_id = ?", array($h));
    $row12 = $result12->row();
        
    return $row12 ? $row12->work_email : null;
        
    }

	

  public function divisions(){
	// Debug: Log the request
	log_message('debug', 'Divisions API called');
	
	if($this->api_login()){
		try {
			log_message('debug', 'Authentication successful, querying divisions');
			$result = $this->db->get("divisions")->result_array();
			log_message('debug', 'Divisions query result count: ' . count($result));

			header('Content-Type: application/json');
			http_response_code(200);
			echo json_encode($result);
		} catch (Exception $e) {
			log_message('error', 'Divisions API error: ' . $e->getMessage());
			header('Content-Type: application/json');
			http_response_code(500);
			echo json_encode(array('success' => false, 'error' => 'Database error: ' . $e->getMessage()));
		}
	}
	else{
		log_message('error', 'Divisions API authentication failed');
		header('Content-Type: application/json');
		http_response_code(401);
		echo json_encode(array('success'=> false,'error'=> 'Authentication Failed! Invalid Request'));
	}
}


public function directorates(){
	if($this->api_login()){
		try {
			$result = $this->db->get("directorates")->result_array();
			
			header('Content-Type: application/json');
			http_response_code(200);
			echo json_encode($result);
		} catch (Exception $e) {
			header('Content-Type: application/json');
			http_response_code(500);
			echo json_encode(array('success' => false, 'error' => 'Database error: ' . $e->getMessage()));
		}
	}
	else{
		header('Content-Type: application/json');
		http_response_code(401);
		echo json_encode(array('success'=> false,'error'=> 'Authentication Failed! Invalid Request'));
	}
}

public function get_current_staff(){

    if($this->api_login()){
		try {
			$filters = $this->input->get();
			$limit = isset($filters['limit']) ? $filters['limit'] : FALSE;
			$start = isset($filters['start']) ? $filters['start'] : FALSE;

			// Get staff data
			$data = $this->staff_mdl->get_all_staff_data($filters, $limit, $start);

			// Add associated_divisions (array) from staff_contracts.other_associated_divisions for API/sync consumers
			foreach ($data as $row) {
				$raw = isset($row->other_associated_divisions) ? $row->other_associated_divisions : '';
				$decoded = is_string($raw) ? json_decode($raw, true) : $raw;
				$row->associated_divisions = is_array($decoded) ? $decoded : [];
			}

			// Set headers before any output
			if (!headers_sent()) {
				header('Content-Type: application/json');
				http_response_code(200);
			}
			
			// Use JSON encoding with options to handle large data
			$json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			
			if ($json === false) {
				// If JSON encoding fails, return error
				if (!headers_sent()) {
					header('Content-Type: application/json');
					http_response_code(500);
				}
				echo json_encode(array('success' => false, 'error' => 'Failed to encode response data. Data may be too large.'));
			} else {
				echo $json;
			}
		} catch (Exception $e) {
			// Set headers before any output
			if (!headers_sent()) {
				header('Content-Type: application/json');
				http_response_code(500);
			}
			echo json_encode(array('success' => false, 'error' => 'Database error: ' . $e->getMessage()));
		}
	}
	else{
		header('Content-Type: application/json');
		http_response_code(401);
		echo json_encode(array('success'=> false,'error'=> 'Authentication Failed! Invalid Request'));
	}
}

/**
 * Get users (for API consumers e.g. APM sync).
 * Returns user records with email from staff.work_email. Optional limit/start for pagination.
 * GET /share/users?limit=100&start=0
 */
public function users()
{
	if ($this->api_login()) {
		try {
			$limit = (int) $this->input->get('limit');
			$start = (int) $this->input->get('start');
			$this->db->select('user.user_id, user.password, user.name, user.role, user.auth_staff_id, user.status, user.created_at, user.changed, user.isChanged, user.photo, user.signature, user.is_approved, user.is_verfied, user.langauge, staff.work_email AS email');
			$this->db->from('user');
			$this->db->join('staff', 'staff.staff_id = user.auth_staff_id', 'left');
			$this->db->order_by('user.user_id', 'ASC');
			if ($limit > 0) {
				$this->db->limit($limit, $start);
			}
			$result = $this->db->get()->result_array();
			if (!headers_sent()) {
				header('Content-Type: application/json');
				http_response_code(200);
			}
			echo json_encode($result);
		} catch (Exception $e) {
			if (!headers_sent()) {
				header('Content-Type: application/json');
				http_response_code(500);
			}
			echo json_encode(array('success' => false, 'error' => 'Database error: ' . $e->getMessage()));
		}
	} else {
		header('Content-Type: application/json');
		http_response_code(401);
		echo json_encode(array('success' => false, 'error' => 'Authentication Failed! Invalid Request'));
	}
}

/**
 * Get signature for a specific staff member
 * Accepts staff_id as GET parameter
 */
public function get_signature()
{
	if($this->api_login()){
		try {
			$staffId = $this->input->get('staff_id');
			
			if (empty($staffId)) {
				header('Content-Type: application/json');
				http_response_code(400);
				echo json_encode(array('success' => false, 'error' => 'staff_id parameter is required'));
				return;
			}
			
			// Get staff signature filename from database using filters
			$filters = array('staff_id' => $staffId);
			$staffData = $this->staff_mdl->get_all_staff_data($filters, 1, 0);
			
			if (empty($staffData) || !is_array($staffData) || count($staffData) === 0) {
				header('Content-Type: application/json');
				http_response_code(404);
				echo json_encode(array('success' => false, 'error' => 'Staff member not found'));
				return;
			}
			
			$staff = $staffData[0];
			$signature = is_object($staff) ? ($staff->signature ?? null) : ($staff['signature'] ?? null);
			
			if (empty($signature)) {
				header('Content-Type: application/json');
				http_response_code(404);
				echo json_encode(array('success' => false, 'error' => 'Signature not found for this staff member'));
				return;
			}
			
			$signaturePath = "uploads/staff/signature/" . $signature;
			
			if (!file_exists($signaturePath)) {
				header('Content-Type: application/json');
				http_response_code(404);
				echo json_encode(array('success' => false, 'error' => 'Signature file not found'));
				return;
			}
			
			// Check file size (limit to 2MB to prevent memory issues)
			$fileSize = @filesize($signaturePath);
			if ($fileSize === false || $fileSize <= 0 || $fileSize > 2097152) { // 2MB limit
				header('Content-Type: application/json');
				http_response_code(400);
				echo json_encode(array('success' => false, 'error' => 'Signature file is too large or invalid'));
				return;
			}
			
			// Set memory limit for processing large files
			$originalMemoryLimit = ini_get('memory_limit');
			ini_set('memory_limit', '1024M');
			
			try {
				$fileContent = @file_get_contents($signaturePath);
				if ($fileContent === false) {
					header('Content-Type: application/json');
					http_response_code(500);
					echo json_encode(array('success' => false, 'error' => 'Failed to read signature file'));
					ini_set('memory_limit', $originalMemoryLimit);
					return;
				}
				
				$signatureData = base64_encode($fileContent);
				unset($fileContent); // Free memory immediately
				
				// Restore original memory limit
				ini_set('memory_limit', $originalMemoryLimit);
				
				// Set headers before any output
				if (!headers_sent()) {
					header('Content-Type: application/json');
					http_response_code(200);
				}
				
				echo json_encode(array(
					'success' => true,
					'staff_id' => $staffId,
					'signature_data' => $signatureData
				));
			} catch (Exception $e) {
				ini_set('memory_limit', $originalMemoryLimit);
				header('Content-Type: application/json');
				http_response_code(500);
				echo json_encode(array('success' => false, 'error' => 'Error processing signature: ' . $e->getMessage()));
			}
		} catch (Exception $e) {
			header('Content-Type: application/json');
			http_response_code(500);
			echo json_encode(array('success' => false, 'error' => 'Database error: ' . $e->getMessage()));
		}
	}
	else{
		header('Content-Type: application/json');
		http_response_code(401);
		echo json_encode(array('success'=> false,'error'=> 'Authentication Failed! Invalid Request'));
	}
}

/**
 * Get photo for a specific staff member
 * Accepts staff_id as GET parameter
 */
public function get_photo()
{
	if($this->api_login()){
		try {
			$staffId = $this->input->get('staff_id');
			
			if (empty($staffId)) {
				header('Content-Type: application/json');
				http_response_code(400);
				echo json_encode(array('success' => false, 'error' => 'staff_id parameter is required'));
				return;
			}
			
			// Get staff photo filename from database using filters
			$filters = array('staff_id' => $staffId);
			$staffData = $this->staff_mdl->get_all_staff_data($filters, 1, 0);
			
			if (empty($staffData) || !is_array($staffData) || count($staffData) === 0) {
				header('Content-Type: application/json');
				http_response_code(404);
				echo json_encode(array('success' => false, 'error' => 'Staff member not found'));
				return;
			}
			
			$staff = $staffData[0];
			$photo = is_object($staff) ? ($staff->photo ?? null) : ($staff['photo'] ?? null);
			
			if (empty($photo)) {
				header('Content-Type: application/json');
				http_response_code(404);
				echo json_encode(array('success' => false, 'error' => 'Photo not found for this staff member'));
				return;
			}
			
			$photoPath = "uploads/staff/" . $photo;
			
			if (!file_exists($photoPath)) {
				header('Content-Type: application/json');
				http_response_code(404);
				echo json_encode(array('success' => false, 'error' => 'Photo file not found'));
				return;
			}
			
			// Check file size (limit to 2MB to prevent memory issues)
			$fileSize = @filesize($photoPath);
			if ($fileSize === false || $fileSize <= 0 || $fileSize > 2097152) { // 2MB limit
				header('Content-Type: application/json');
				http_response_code(400);
				echo json_encode(array('success' => false, 'error' => 'Photo file is too large or invalid'));
				return;
			}
			
			// Set memory limit for processing large files
			$originalMemoryLimit = ini_get('memory_limit');
			ini_set('memory_limit', '1024M');
			
			try {
				$fileContent = @file_get_contents($photoPath);
				if ($fileContent === false) {
					header('Content-Type: application/json');
					http_response_code(500);
					echo json_encode(array('success' => false, 'error' => 'Failed to read photo file'));
					ini_set('memory_limit', $originalMemoryLimit);
					return;
				}
				
				$photoData = base64_encode($fileContent);
				unset($fileContent); // Free memory immediately
				
				// Restore original memory limit
				ini_set('memory_limit', $originalMemoryLimit);
				
				// Set headers before any output
				if (!headers_sent()) {
			header('Content-Type: application/json');
			http_response_code(200);
				}
				
				echo json_encode(array(
					'success' => true,
					'staff_id' => $staffId,
					'photo_data' => $photoData
				));
			} catch (Exception $e) {
				ini_set('memory_limit', $originalMemoryLimit);
				header('Content-Type: application/json');
				http_response_code(500);
				echo json_encode(array('success' => false, 'error' => 'Error processing photo: ' . $e->getMessage()));
			}
		} catch (Exception $e) {
			header('Content-Type: application/json');
			http_response_code(500);
			echo json_encode(array('success' => false, 'error' => 'Database error: ' . $e->getMessage()));
		}
	}
	else{
		header('Content-Type: application/json');
		http_response_code(401);
		echo json_encode(array('success'=> false,'error'=> 'Authentication Failed! Invalid Request'));
	}
}


public function api_login()
{
    // Check if HTTP Basic Auth headers are present
    $username = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : null;
    $password = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : null;

    // If not provided, prompt for credentials
    if (!$username || !$password) {
        header('WWW-Authenticate: Basic realm="API Access Required"');
        header('HTTP/1.0 401 Unauthorized');
        echo json_encode(['status' => false, 'message' => 'Authentication required']);
        exit;
    }

    // Validate user
    $user = $this->auth_mdl->login(['email' => $username]);
    if (empty($user) || !$this->validate_password($password, $user->password)) {
        header('HTTP/1.0 401 Unauthorized');
        echo json_encode(['status' => false, 'message' => 'Invalid credentials']);
        exit;
    }
    
    return $user; // Authenticated user
}


public function validate_password($post_password,$dbpassword){
    $auth = ($this->argonhash->check($post_password, $dbpassword));
     if ($auth) {
       return TRUE;
     }
     else{
       return FALSE;
     }
     
   }

	/**
	 * Get Service Requests
	 * GET /share/service_requests?overall_status=approved&division_id=1&limit=10&offset=0
	 */
	public function service_requests()
	{
		if ($this->api_login()) {
			try {
				$filters = [
					'overall_status' => $this->input->get('overall_status'),
					'division_id' => $this->input->get('division_id'),
					'staff_id' => $this->input->get('staff_id'),
					'date_from' => $this->input->get('date_from'),
					'date_to' => $this->input->get('date_to'),
					'limit' => $this->input->get('limit') ? (int)$this->input->get('limit') : null,
					'offset' => $this->input->get('offset') ? (int)$this->input->get('offset') : 0,
					'order_by' => $this->input->get('order_by') ?: 'id',
					'order_dir' => $this->input->get('order_dir') ?: 'DESC'
				];
				
				// Remove empty filters
				$filters = array_filter($filters, function($value) {
					return $value !== null && $value !== '';
				});
				
				$data = $this->apm_mdl->get_service_requests($filters);
				$total = $this->apm_mdl->count_service_requests($filters);
				
				header('Content-Type: application/json');
				http_response_code(200);
				echo json_encode([
					'success' => true,
					'data' => $data,
					'total' => $total,
					'count' => count($data)
				]);
			} catch (Exception $e) {
				header('Content-Type: application/json');
				http_response_code(500);
				echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
			}
		} else {
			header('Content-Type: application/json');
			http_response_code(401);
			echo json_encode(['success' => false, 'error' => 'Authentication Failed! Invalid Request']);
		}
	}

	/**
	 * Get Activities
	 * GET /share/activities?overall_status=approved&division_id=1&limit=10&offset=0
	 */
	public function activities()
	{
		if ($this->api_login()) {
			try {
				$filters = [
					'overall_status' => $this->input->get('overall_status'),
					'division_id' => $this->input->get('division_id'),
					'staff_id' => $this->input->get('staff_id'),
					'is_single_memo' => $this->input->get('is_single_memo'),
					'date_from' => $this->input->get('date_from'),
					'date_to' => $this->input->get('date_to'),
					'limit' => $this->input->get('limit') ? (int)$this->input->get('limit') : null,
					'offset' => $this->input->get('offset') ? (int)$this->input->get('offset') : 0,
					'order_by' => $this->input->get('order_by') ?: 'id',
					'order_dir' => $this->input->get('order_dir') ?: 'DESC'
				];
				
				// Remove empty filters
				$filters = array_filter($filters, function($value) {
					return $value !== null && $value !== '';
				});
				
				$data = $this->apm_mdl->get_activities($filters);
				$total = $this->apm_mdl->count_activities($filters);
				
				header('Content-Type: application/json');
				http_response_code(200);
				echo json_encode([
					'success' => true,
					'data' => $data,
					'total' => $total,
					'count' => count($data)
				]);
			} catch (Exception $e) {
				header('Content-Type: application/json');
				http_response_code(500);
				echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
			}
		} else {
			header('Content-Type: application/json');
			http_response_code(401);
			echo json_encode(['success' => false, 'error' => 'Authentication Failed! Invalid Request']);
		}
	}

	/**
	 * Get Non-Travel Memos
	 * GET /share/non_travel_memos?overall_status=approved&division_id=1&limit=10&offset=0
	 */
	public function non_travel_memos()
	{
		if ($this->api_login()) {
			try {
				$filters = [
					'overall_status' => $this->input->get('overall_status'),
					'division_id' => $this->input->get('division_id'),
					'staff_id' => $this->input->get('staff_id'),
					'date_from' => $this->input->get('date_from'),
					'date_to' => $this->input->get('date_to'),
					'limit' => $this->input->get('limit') ? (int)$this->input->get('limit') : null,
					'offset' => $this->input->get('offset') ? (int)$this->input->get('offset') : 0,
					'order_by' => $this->input->get('order_by') ?: 'id',
					'order_dir' => $this->input->get('order_dir') ?: 'DESC'
				];
				
				// Remove empty filters
				$filters = array_filter($filters, function($value) {
					return $value !== null && $value !== '';
				});
				
				$data = $this->apm_mdl->get_non_travel_memos($filters);
				$total = $this->apm_mdl->count_non_travel_memos($filters);
				
				header('Content-Type: application/json');
				http_response_code(200);
				echo json_encode([
					'success' => true,
					'data' => $data,
					'total' => $total,
					'count' => count($data)
				]);
			} catch (Exception $e) {
				header('Content-Type: application/json');
				http_response_code(500);
				echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
			}
		} else {
			header('Content-Type: application/json');
			http_response_code(401);
			echo json_encode(['success' => false, 'error' => 'Authentication Failed! Invalid Request']);
		}
	}

	/**
	 * Get Special Memos
	 * GET /share/special_memos?overall_status=approved&division_id=1&limit=10&offset=0
	 */
	public function special_memos()
	{
		if ($this->api_login()) {
			try {
				$filters = [
					'overall_status' => $this->input->get('overall_status'),
					'division_id' => $this->input->get('division_id'),
					'staff_id' => $this->input->get('staff_id'),
					'date_from' => $this->input->get('date_from'),
					'date_to' => $this->input->get('date_to'),
					'limit' => $this->input->get('limit') ? (int)$this->input->get('limit') : null,
					'offset' => $this->input->get('offset') ? (int)$this->input->get('offset') : 0,
					'order_by' => $this->input->get('order_by') ?: 'id',
					'order_dir' => $this->input->get('order_dir') ?: 'DESC'
				];
				
				// Remove empty filters
				$filters = array_filter($filters, function($value) {
					return $value !== null && $value !== '';
				});
				
				$data = $this->apm_mdl->get_special_memos($filters);
				$total = $this->apm_mdl->count_special_memos($filters);
				
				header('Content-Type: application/json');
				http_response_code(200);
				echo json_encode([
					'success' => true,
					'data' => $data,
					'total' => $total,
					'count' => count($data)
				]);
			} catch (Exception $e) {
				header('Content-Type: application/json');
				http_response_code(500);
				echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
			}
		} else {
			header('Content-Type: application/json');
			http_response_code(401);
			echo json_encode(['success' => false, 'error' => 'Authentication Failed! Invalid Request']);
		}
	}

	/**
	 * Get Request ARFs
	 * GET /share/request_arfs?overall_status=approved&division_id=1&limit=10&offset=0
	 */
	public function request_arfs()
	{
		if ($this->api_login()) {
			try {
				$filters = [
					'overall_status' => $this->input->get('overall_status'),
					'division_id' => $this->input->get('division_id'),
					'staff_id' => $this->input->get('staff_id'),
					'date_from' => $this->input->get('date_from'),
					'date_to' => $this->input->get('date_to'),
					'limit' => $this->input->get('limit') ? (int)$this->input->get('limit') : null,
					'offset' => $this->input->get('offset') ? (int)$this->input->get('offset') : 0,
					'order_by' => $this->input->get('order_by') ?: 'id',
					'order_dir' => $this->input->get('order_dir') ?: 'DESC'
				];
				
				// Remove empty filters
				$filters = array_filter($filters, function($value) {
					return $value !== null && $value !== '';
				});
				
				$data = $this->apm_mdl->get_request_arfs($filters);
				$total = $this->apm_mdl->count_request_arfs($filters);
				
				header('Content-Type: application/json');
				http_response_code(200);
				echo json_encode([
					'success' => true,
					'data' => $data,
					'total' => $total,
					'count' => count($data)
				]);
			} catch (Exception $e) {
				header('Content-Type: application/json');
				http_response_code(500);
				echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
			}
		} else {
			header('Content-Type: application/json');
			http_response_code(401);
			echo json_encode(['success' => false, 'error' => 'Authentication Failed! Invalid Request']);
		}
	}

	/**
	 * Get Change Requests
	 * GET /share/change_requests?overall_status=approved&parent_memo_model=App\Models\SpecialMemo&limit=10&offset=0
	 */
	public function change_requests()
	{
		if ($this->api_login()) {
			try {
				$filters = [
					'overall_status' => $this->input->get('overall_status'),
					'parent_memo_model' => $this->input->get('parent_memo_model'),
					'parent_memo_id' => $this->input->get('parent_memo_id'),
					'staff_id' => $this->input->get('staff_id'),
					'date_from' => $this->input->get('date_from'),
					'date_to' => $this->input->get('date_to'),
					'limit' => $this->input->get('limit') ? (int)$this->input->get('limit') : null,
					'offset' => $this->input->get('offset') ? (int)$this->input->get('offset') : 0,
					'order_by' => $this->input->get('order_by') ?: 'id',
					'order_dir' => $this->input->get('order_dir') ?: 'DESC'
				];
				
				// Remove empty filters
				$filters = array_filter($filters, function($value) {
					return $value !== null && $value !== '';
				});
				
				$data = $this->apm_mdl->get_change_requests($filters);
				$total = $this->apm_mdl->count_change_requests($filters);
				
				header('Content-Type: application/json');
				http_response_code(200);
				echo json_encode([
					'success' => true,
					'data' => $data,
					'total' => $total,
					'count' => count($data)
				]);
			} catch (Exception $e) {
				header('Content-Type: application/json');
				http_response_code(500);
				echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
			}
		} else {
			header('Content-Type: application/json');
			http_response_code(401);
			echo json_encode(['success' => false, 'error' => 'Authentication Failed! Invalid Request']);
		}
	}

	/**
	 * Get Fund Codes
	 * GET /share/fund_codes?fund_type_id=1&division_id=1&funder_id=1&year=2025&is_active=1&limit=10&offset=0
	 */
	public function fund_codes()
	{
		if ($this->api_login()) {
			try {
				$filters = [
					'fund_type_id' => $this->input->get('fund_type_id'),
					'division_id' => $this->input->get('division_id'),
					'funder_id' => $this->input->get('funder_id'),
					'year' => $this->input->get('year'),
					'is_active' => $this->input->get('is_active'),
					'code' => $this->input->get('code'),
					'limit' => $this->input->get('limit') ? (int)$this->input->get('limit') : null,
					'offset' => $this->input->get('offset') ? (int)$this->input->get('offset') : 0,
					'order_by' => $this->input->get('order_by') ?: 'fc.code',
					'order_dir' => $this->input->get('order_dir') ?: 'ASC'
				];
				
				// Remove empty filters
				$filters = array_filter($filters, function($value) {
					return $value !== null && $value !== '';
				});
				
				$data = $this->apm_mdl->get_fund_codes($filters);
				$total = $this->apm_mdl->count_fund_codes($filters);
				
				header('Content-Type: application/json');
				http_response_code(200);
				echo json_encode([
					'success' => true,
					'data' => $data,
					'total' => $total,
					'count' => count($data)
				]);
			} catch (Exception $e) {
				header('Content-Type: application/json');
				http_response_code(500);
				echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
			}
		} else {
			header('Content-Type: application/json');
			http_response_code(401);
			echo json_encode(['success' => false, 'error' => 'Authentication Failed! Invalid Request']);
		}
	}

	/**
	 * Get Fund Types
	 * GET /share/fund_types?id=1&name=Donor&limit=10&offset=0
	 */
	public function fund_types()
	{
		if ($this->api_login()) {
			try {
				$filters = [
					'id' => $this->input->get('id'),
					'name' => $this->input->get('name'),
					'limit' => $this->input->get('limit') ? (int)$this->input->get('limit') : null,
					'offset' => $this->input->get('offset') ? (int)$this->input->get('offset') : 0,
					'order_by' => $this->input->get('order_by') ?: 'ft.name',
					'order_dir' => $this->input->get('order_dir') ?: 'ASC'
				];
				
				// Remove empty filters
				$filters = array_filter($filters, function($value) {
					return $value !== null && $value !== '';
				});
				
				$data = $this->apm_mdl->get_fund_types($filters);
				$total = $this->apm_mdl->count_fund_types($filters);
				
				header('Content-Type: application/json');
				http_response_code(200);
				echo json_encode([
					'success' => true,
					'data' => $data,
					'total' => $total,
					'count' => count($data)
				]);
			} catch (Exception $e) {
				header('Content-Type: application/json');
				http_response_code(500);
				echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
			}
		} else {
			header('Content-Type: application/json');
			http_response_code(401);
			echo json_encode(['success' => false, 'error' => 'Authentication Failed! Invalid Request']);
		}
	}

	/**
	 * API Documentation Web View
	 * GET /share/documentation?file=README.md
	 */
	public function documentation()
	{
		// Check if user is logged in
		//dd($this->session->userdata('user'));
		if (!isset($this->session->userdata('user')->name)) {
			redirect('auth/login');
		}

		// Get file parameter or default to API_DOCUMENTATION.md
		$file_param = $this->input->get('file');
		
		if ($file_param) {
			// Get project root (one level up from application)
			$project_root = realpath(APPPATH . '../');
			
			// Handle different documentation files
			$file_map = [
				'README.md' => $project_root . '/README.md',
				'ENVIRONMENT_VARIABLES.md' => $project_root . '/assets/ENVIRONMENT_VARIABLES.md',
				'APM_README.md' => $project_root . '/apm/README.md',
				'APM_DOCS.md' => $project_root . '/apm/documentation/README.md',
				'FINANCE_README.md' => $project_root . '/finance/README.md',
				'FINANCE_DOCS.md' => $project_root . '/finance/documentation/README.md',
				'DOCS_README.md' => $project_root . '/documentation/README.md',
			];
			
			if (isset($file_map[$file_param])) {
				$markdown_file = $file_map[$file_param];
			} else {
				// Try to resolve relative paths from API_DOCUMENTATION.md location
				$base_path = APPPATH . 'modules/share/';
				$markdown_file = realpath($base_path . $file_param);
				
				// Security: ensure file is within allowed directories
				if (!$markdown_file || strpos($markdown_file, realpath($base_path)) !== 0) {
					show_error('Documentation file not found or access denied.', 404);
				}
			}
			
			$title = ucfirst(str_replace(['.md', '_'], ['', ' '], $file_param));
		} else {
			// Default to API documentation
			$markdown_file = APPPATH . 'modules/share/API_DOCUMENTATION.md';
			$title = 'API Documentation';
		}

		//dd($markdown_file);
		
		if (!file_exists($markdown_file)) {
			show_error('Documentation file not found.', 404);
		}

		$markdown_content = file_get_contents($markdown_file);
		
		// Convert relative markdown links to documentation routes
		$markdown_content = $this->convert_markdown_links($markdown_content, $markdown_file);
		
		$html_content = $this->markdown_to_html($markdown_content);

		$data = [
			'module' => 'share',
			'view' => 'documentation',
			'documentation_html' => $html_content,
			'title' => $title
		];

			//dd($data['staff']);
		render('documentation', $data);
		
	}

	/**
	 * Convert relative markdown file links to documentation routes
	 */
	private function convert_markdown_links($content, $current_file)
	{
		// Get the directory of the current file
		$current_dir = dirname($current_file);
		// Get project root (one level up from application)
		$base_path = realpath(APPPATH . '../');
		
		// Map of common documentation files to their route names
		$file_map = [
			'README.md' => 'README.md',
			'ENVIRONMENT_VARIABLES.md' => 'ENVIRONMENT_VARIABLES.md',
			'apm/README.md' => 'APM_README.md',
			'apm/documentation/README.md' => 'APM_DOCS.md',
			'finance/README.md' => 'FINANCE_README.md',
			'finance/documentation/README.md' => 'FINANCE_DOCS.md',
			'documentation/README.md' => 'DOCS_README.md',
		];
		
		// Convert markdown links [text](relative/path/to/file.md)
		$content = preg_replace_callback('/\[([^\]]+)\]\(([^\)]+\.md)\)/', function($matches) use ($current_dir, $base_path, $file_map) {
			$link_text = $matches[1];
			$link_path = $matches[2];
			
			// Skip absolute URLs and anchors
			if (preg_match('/^(https?:\/\/|#)/', $link_path)) {
				return $matches[0]; // Return original
			}
			
			// Resolve relative path
			$resolved_path = realpath($current_dir . '/' . $link_path);
			
			if ($resolved_path && strpos($resolved_path, $base_path) === 0) {
				// File exists and is within project directory
				$relative_from_base = str_replace($base_path . '/', '', $resolved_path);
				
				// Check if it's in our file map
				foreach ($file_map as $file_path => $route_name) {
					if (strpos($relative_from_base, $file_path) !== false || 
					    strpos($resolved_path, $file_path) !== false) {
						$doc_url = base_url('share/documentation?file=' . urlencode($route_name));
						return '[' . $link_text . '](' . $doc_url . ')';
					}
				}
				
				// For other files, try to create a route
				// Extract filename
				$filename = basename($resolved_path);
				if (strpos($filename, 'README.md') !== false) {
					// Try to infer the route name
					$dir_name = basename(dirname($resolved_path));
					$route_name = strtoupper($dir_name) . '_README.md';
					$doc_url = base_url('share/documentation?file=' . urlencode($route_name));
					return '[' . $link_text . '](' . $doc_url . ')';
				}
				
				// Default: use filename
				$doc_url = base_url('share/documentation?file=' . urlencode($filename));
				return '[' . $link_text . '](' . $doc_url . ')';
			}
			
			// If file doesn't exist or can't be resolved, return original
			return $matches[0];
		}, $content);
		
		return $content;
	}

	/**
	 * Simple Markdown to HTML converter
	 * Converts basic markdown syntax to HTML
	 */
	private function markdown_to_html($markdown)
	{
		$html = $markdown;
		$lines = explode("\n", $html);
		$output = [];
		$in_code_block = false;
		$in_list = false;
		$list_type = '';
		$in_table = false;
		$table_rows = [];

		foreach ($lines as $line) {
			// Handle code blocks
			if (preg_match('/^```/', $line)) {
				if ($in_code_block) {
					$output[] = '</code></pre>';
					$in_code_block = false;
				} else {
					$lang = preg_match('/^```(\w+)/', $line, $lang_match) ? $lang_match[1] : '';
					$output[] = '<pre><code' . ($lang ? ' class="language-' . htmlspecialchars($lang) . '"' : '') . '>';
					$in_code_block = true;
				}
				continue;
			}

			if ($in_code_block) {
				$output[] = htmlspecialchars($line) . "\n";
				continue;
			}

			// Headers
			if (preg_match('/^#### (.*)$/', $line, $matches)) {
				$output[] = '<h4>' . $this->process_inline_markdown($matches[1]) . '</h4>';
				continue;
			}
			if (preg_match('/^### (.*)$/', $line, $matches)) {
				$output[] = '<h3>' . $this->process_inline_markdown($matches[1]) . '</h3>';
				continue;
			}
			if (preg_match('/^## (.*)$/', $line, $matches)) {
				$output[] = '<h2>' . $this->process_inline_markdown($matches[1]) . '</h2>';
				continue;
			}
			if (preg_match('/^# (.*)$/', $line, $matches)) {
				$output[] = '<h1>' . $this->process_inline_markdown($matches[1]) . '</h1>';
				continue;
			}

			// Horizontal rules
			if (preg_match('/^---$/', $line)) {
				$output[] = '<hr>';
				continue;
			}

			// Tables
			if (preg_match('/^\|/', $line)) {
				if (!$in_table) {
					$in_table = true;
					$table_rows = [];
				}
				$cells = array_map('trim', explode('|', $line));
				array_shift($cells); // Remove first empty element
				array_pop($cells); // Remove last empty element
				$table_rows[] = $cells;
				continue;
			} else {
				if ($in_table) {
					// Process table
					if (count($table_rows) > 1) {
						$output[] = '<table>';
						// First row is header
						$output[] = '<thead><tr>';
						foreach ($table_rows[0] as $cell) {
							$output[] = '<th>' . $this->process_inline_markdown($cell) . '</th>';
						}
						$output[] = '</tr></thead>';
						// Skip separator row (index 1)
						$output[] = '<tbody>';
						for ($i = 2; $i < count($table_rows); $i++) {
							$output[] = '<tr>';
							foreach ($table_rows[$i] as $cell) {
								$output[] = '<td>' . $this->process_inline_markdown($cell) . '</td>';
							}
							$output[] = '</tr>';
						}
						$output[] = '</tbody></table>';
					}
					$in_table = false;
					$table_rows = [];
				}
			}

			// Lists
			if (preg_match('/^(\*|\-|\+)\s+(.*)$/', $line, $matches)) {
				if (!$in_list || $list_type != 'ul') {
					if ($in_list) $output[] = '</' . $list_type . '>';
					$output[] = '<ul>';
					$in_list = true;
					$list_type = 'ul';
				}
				$output[] = '<li>' . $this->process_inline_markdown($matches[2]) . '</li>';
				continue;
			}
			if (preg_match('/^\d+\.\s+(.*)$/', $line, $matches)) {
				if (!$in_list || $list_type != 'ol') {
					if ($in_list) $output[] = '</' . $list_type . '>';
					$output[] = '<ol>';
					$in_list = true;
					$list_type = 'ol';
				}
				$output[] = '<li>' . $this->process_inline_markdown($matches[1]) . '</li>';
				continue;
			}

			// Close list if needed
			if ($in_list && trim($line) == '') {
				$output[] = '</' . $list_type . '>';
				$in_list = false;
				$list_type = '';
			}

			// Blockquotes
			if (preg_match('/^>\s+(.*)$/', $line, $matches)) {
				$output[] = '<blockquote>' . $this->process_inline_markdown($matches[1]) . '</blockquote>';
				continue;
			}

			// Regular paragraphs
			if (trim($line) != '') {
				$output[] = '<p>' . $this->process_inline_markdown($line) . '</p>';
			} else {
				$output[] = '';
			}
		}

		// Close any open tags
		if ($in_code_block) {
			$output[] = '</code></pre>';
		}
		if ($in_list) {
			$output[] = '</' . $list_type . '>';
		}
		if ($in_table && count($table_rows) > 1) {
			$output[] = '<table>';
			$output[] = '<thead><tr>';
			foreach ($table_rows[0] as $cell) {
				$output[] = '<th>' . $this->process_inline_markdown($cell) . '</th>';
			}
			$output[] = '</tr></thead>';
			$output[] = '<tbody>';
			for ($i = 2; $i < count($table_rows); $i++) {
				$output[] = '<tr>';
				foreach ($table_rows[$i] as $cell) {
					$output[] = '<td>' . $this->process_inline_markdown($cell) . '</td>';
				}
				$output[] = '</tr>';
			}
			$output[] = '</tbody></table>';
		}

		return implode("\n", $output);
	}

	/**
	 * Process inline markdown (bold, italic, code, links)
	 */
	private function process_inline_markdown($text)
	{
		// First, escape HTML to prevent XSS
		$text = htmlspecialchars($text);
		
		// Process links [text](url) - URLs are already escaped by htmlspecialchars
		$text = preg_replace_callback('/\[([^\]]+)\]\(([^\)]+)\)/', function($matches) {
			$link_text = $matches[1]; // Already escaped
			$link_url = $matches[2]; // Already escaped, but we need to decode it for href
			// Decode URL for href attribute (but keep text escaped)
			$link_url_decoded = html_entity_decode($link_url, ENT_QUOTES);
			return '<a href="' . htmlspecialchars($link_url_decoded, ENT_QUOTES) . '">' . $link_text . '</a>';
		}, $text);

		// Bold **text**
		$text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text);

		// Italic *text* (but not if it's part of **text**)
		$text = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $text);

		// Inline code `code`
		$text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);

		return $text;
	}

	/**
	 * API Documentation endpoint
	 * GET /share/api_docs
	 */
	public function api_docs()
	{
		header('Content-Type: application/json');
		http_response_code(200);
		echo json_encode([
			'api_name' => 'Africa CDC Staff Tracker API',
			'version' => '1.0.0',
			'base_url' => base_url('share'),
			'authentication' => [
				'type' => 'HTTP Basic Authentication',
				'description' => 'All endpoints require HTTP Basic Authentication using your CodeIgniter app credentials',
				'example' => 'Authorization: Basic base64(username:password)'
			],
			'endpoints' => [
				'service_requests' => [
					'url' => base_url('share/service_requests'),
					'method' => 'GET',
					'description' => 'Get service requests with optional filtering',
					'parameters' => [
						'overall_status' => ['type' => 'string', 'options' => ['approved', 'pending', 'returned', 'draft', 'cancelled'], 'required' => false],
						'division_id' => ['type' => 'integer', 'required' => false],
						'staff_id' => ['type' => 'integer', 'required' => false],
						'date_from' => ['type' => 'date (Y-m-d)', 'required' => false],
						'date_to' => ['type' => 'date (Y-m-d)', 'required' => false],
						'limit' => ['type' => 'integer', 'required' => false],
						'offset' => ['type' => 'integer', 'required' => false],
						'order_by' => ['type' => 'string', 'default' => 'id', 'required' => false],
						'order_dir' => ['type' => 'string', 'options' => ['ASC', 'DESC'], 'default' => 'DESC', 'required' => false]
					],
					'example' => base_url('share/service_requests?overall_status=approved&division_id=1&limit=10')
				],
				'activities' => [
					'url' => base_url('share/activities'),
					'method' => 'GET',
					'description' => 'Get activities with optional filtering',
					'parameters' => [
						'overall_status' => ['type' => 'string', 'options' => ['approved', 'pending', 'returned', 'draft', 'cancelled'], 'required' => false],
						'division_id' => ['type' => 'integer', 'required' => false],
						'staff_id' => ['type' => 'integer', 'required' => false],
						'is_single_memo' => ['type' => 'boolean', 'required' => false],
						'date_from' => ['type' => 'date (Y-m-d)', 'required' => false],
						'date_to' => ['type' => 'date (Y-m-d)', 'required' => false],
						'limit' => ['type' => 'integer', 'required' => false],
						'offset' => ['type' => 'integer', 'required' => false],
						'order_by' => ['type' => 'string', 'default' => 'id', 'required' => false],
						'order_dir' => ['type' => 'string', 'options' => ['ASC', 'DESC'], 'default' => 'DESC', 'required' => false]
					],
					'example' => base_url('share/activities?overall_status=approved&limit=10')
				],
				'non_travel_memos' => [
					'url' => base_url('share/non_travel_memos'),
					'method' => 'GET',
					'description' => 'Get non-travel memos (single memos) with optional filtering',
					'parameters' => [
						'overall_status' => ['type' => 'string', 'options' => ['approved', 'pending', 'returned', 'draft', 'cancelled'], 'required' => false],
						'division_id' => ['type' => 'integer', 'required' => false],
						'staff_id' => ['type' => 'integer', 'required' => false],
						'date_from' => ['type' => 'date (Y-m-d)', 'required' => false],
						'date_to' => ['type' => 'date (Y-m-d)', 'required' => false],
						'limit' => ['type' => 'integer', 'required' => false],
						'offset' => ['type' => 'integer', 'required' => false],
						'order_by' => ['type' => 'string', 'default' => 'id', 'required' => false],
						'order_dir' => ['type' => 'string', 'options' => ['ASC', 'DESC'], 'default' => 'DESC', 'required' => false]
					],
					'example' => base_url('share/non_travel_memos?overall_status=approved&limit=10')
				],
				'special_memos' => [
					'url' => base_url('share/special_memos'),
					'method' => 'GET',
					'description' => 'Get special memos with optional filtering',
					'parameters' => [
						'overall_status' => ['type' => 'string', 'options' => ['approved', 'pending', 'returned', 'draft', 'cancelled'], 'required' => false],
						'division_id' => ['type' => 'integer', 'required' => false],
						'staff_id' => ['type' => 'integer', 'required' => false],
						'date_from' => ['type' => 'date (Y-m-d)', 'required' => false],
						'date_to' => ['type' => 'date (Y-m-d)', 'required' => false],
						'limit' => ['type' => 'integer', 'required' => false],
						'offset' => ['type' => 'integer', 'required' => false],
						'order_by' => ['type' => 'string', 'default' => 'id', 'required' => false],
						'order_dir' => ['type' => 'string', 'options' => ['ASC', 'DESC'], 'default' => 'DESC', 'required' => false]
					],
					'example' => base_url('share/special_memos?overall_status=approved&limit=10')
				],
				'request_arfs' => [
					'url' => base_url('share/request_arfs'),
					'method' => 'GET',
					'description' => 'Get request ARFs with optional filtering',
					'parameters' => [
						'overall_status' => ['type' => 'string', 'options' => ['approved', 'pending', 'returned', 'draft', 'cancelled'], 'required' => false],
						'division_id' => ['type' => 'integer', 'required' => false],
						'staff_id' => ['type' => 'integer', 'required' => false],
						'date_from' => ['type' => 'date (Y-m-d)', 'required' => false],
						'date_to' => ['type' => 'date (Y-m-d)', 'required' => false],
						'limit' => ['type' => 'integer', 'required' => false],
						'offset' => ['type' => 'integer', 'required' => false],
						'order_by' => ['type' => 'string', 'default' => 'id', 'required' => false],
						'order_dir' => ['type' => 'string', 'options' => ['ASC', 'DESC'], 'default' => 'DESC', 'required' => false]
					],
					'example' => base_url('share/request_arfs?overall_status=approved&limit=10')
				],
				'change_requests' => [
					'url' => base_url('share/change_requests'),
					'method' => 'GET',
					'description' => 'Get change requests with optional filtering',
					'parameters' => [
						'overall_status' => ['type' => 'string', 'options' => ['approved', 'pending', 'returned', 'draft', 'cancelled'], 'required' => false],
						'parent_memo_model' => ['type' => 'string', 'example' => 'App\\Models\\SpecialMemo', 'required' => false],
						'parent_memo_id' => ['type' => 'integer', 'required' => false],
						'staff_id' => ['type' => 'integer', 'required' => false],
						'date_from' => ['type' => 'date (Y-m-d)', 'required' => false],
						'date_to' => ['type' => 'date (Y-m-d)', 'required' => false],
						'limit' => ['type' => 'integer', 'required' => false],
						'offset' => ['type' => 'integer', 'required' => false],
						'order_by' => ['type' => 'string', 'default' => 'id', 'required' => false],
						'order_dir' => ['type' => 'string', 'options' => ['ASC', 'DESC'], 'default' => 'DESC', 'required' => false]
					],
					'example' => base_url('share/change_requests?overall_status=approved&limit=10')
				],
				'divisions' => [
					'url' => base_url('share/divisions'),
					'method' => 'GET',
					'description' => 'Get all divisions',
					'parameters' => [],
					'example' => base_url('share/divisions')
				],
				'directorates' => [
					'url' => base_url('share/directorates'),
					'method' => 'GET',
					'description' => 'Get all directorates',
					'parameters' => [],
					'example' => base_url('share/directorates')
				],
				'staff' => [
					'url' => base_url('share/staff'),
					'method' => 'GET',
					'description' => 'Get staff information',
					'parameters' => [],
					'example' => base_url('share/staff')
				],
				'get_current_staff' => [
					'url' => base_url('share/get_current_staff'),
					'method' => 'GET',
					'description' => 'Get current staff with filters',
					'parameters' => [
						'limit' => ['type' => 'integer', 'required' => false],
						'start' => ['type' => 'integer', 'required' => false]
					],
					'example' => base_url('share/get_current_staff?limit=10&start=0')
				],
				'users' => [
					'url' => base_url('share/users'),
					'method' => 'GET',
					'description' => 'Get users (with email from staff). For API consumers e.g. APM sync.',
					'parameters' => [
						'limit' => ['type' => 'integer', 'required' => false],
						'start' => ['type' => 'integer', 'required' => false]
					],
					'example' => base_url('share/users?limit=100&start=0')
				]
			],
			'response_format' => [
				'success' => 'boolean',
				'data' => 'array',
				'total' => 'integer (total records matching filters)',
				'count' => 'integer (records in current response)',
				'error' => 'string (only present on error)'
			],
			'status_codes' => [
				'200' => 'Success',
				'401' => 'Unauthorized - Invalid or missing authentication',
				'500' => 'Internal Server Error'
			]
		], JSON_PRETTY_PRINT);
   }

}