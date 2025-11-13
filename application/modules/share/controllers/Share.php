<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Share extends MX_Controller
{


	public  function __construct()
	{
		parent::__construct();

		$this->module = "share";
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

}