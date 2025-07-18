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
	


	public function staff()
{
    if ($this->api_login())  {

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
        echo json_encode($data);
    } else {
        header('Content-Type: application/json');
        echo json_encode(array('success' => false, 'error' => 'Authentication Failed! Invalid Request'));
    }
}

public function visualise()
{
    if ($this->api_login()) {

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



// SELECT `s`.`staff_id`, `s`.`SAPNO`, `s`.`title`, `s`.`fname`, `s`.`lname`, `s`.`oname`, `s`.`gender`, `s`.`date_of_birth`, `s`.`work_email`, `s`.`tel_1`, `s`.`tel_2`, `s`.`whatsapp`, `s`.`photo`, `s`.`private_email`, `s`.`physical_location`, `sc`.`division_id`, `sc`.`job_id`, `j`.`job_name`, `sc`.`start_date`, `sc`.`end_date`, `sc`.`status_id`, `d`.`division_name`, `ds`.`duty_station_name`, `g`.`grade`, `st`.`status` ◀
// FROM `staff` `s`
// LEFT JOIN `staff_contracts` `sc` ON `sc`.`staff_id` = `s`.`staff_id`
// LEFT JOIN `grades` `g` ON `g`.`grade_id` = `sc`.`grade_id`
// LEFT JOIN `divisions` `d` ON `d`.`division_id` = `sc`.`division_id`
// LEFT JOIN `duty_stations` `ds` ON `ds`.`duty_station_id` = `sc`.`duty_station_id`
// LEFT JOIN `jobs` `j` ON `j`.`job_id` = `sc`.`job_id`
// LEFT JOIN `status` `st` ON `st`.`status_id` = `sc`.`status_id`
// WHERE sc.staff_contract_id IN (SELECT MAX(staff_contract_id)
// FROM `staff_contracts`
// GROUP BY `staff_id`)
// AND `sc`.`status_id` IN(1, 2, 3, 7)
// ORDER BY `s`.`fname` ASC

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
        echo json_encode($data);
    } else {
        header('Content-Type: application/json');
        echo json_encode(array('success' => false, 'error' => 'Authentication Failed! Invalid Request'));
    }
}

	function get_supervisor_mail($h){
   
    //Collect first and second supervisor email
    $result12 = $this->db->query("SELECT work_email FROM staff  WHERE staff_id='$h'");
   $row12 = $result12->row();
        
    return $row12->work_email;
        
    }

	

  public function divisions(){
	if($this->api_login()){

	$result = $this->db->get("divisions")->result_array();
	
	

	header('Content-Type: application/json');
			echo json_encode($result);
	}
	else{
		header('Content-Type: application/json');
		echo json_encode(array('success'=> false,'error'=> 'Authentication Failed! Invalid Request'));
	}
}


public function directorates(){
	if($this->api_login()){
	
	$result = $this->db->get("directorates")->result_array();
	
	header('Content-Type: application/json');
			echo json_encode($result);
	}
	else{
		header('Content-Type: application/json');
		echo json_encode(array('success'=> false,'error'=> 'Authentication Failed! Invalid Request'));
	}
}

public function get_current_staff(){

    if($this->api_login()){

    $filters = $this->input->get();
    $limit = $filters['limit'];
    $start = $filters['start'];
    
$data =  $this->staff_mdl->get_active_staff_data($filters = array(), $limit = FALSE, $start = FALSE);   

header('Content-Type: application/json');
			echo json_encode($data);
	}
	else{
		header('Content-Type: application/json');
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
        $log_message = $username." API Authentication Failed ";
        log_user_action($log_message);
        exit;
    }

    // Validate user
    $user = $this->auth_mdl->login(['email' => $username]);
    if (empty($user) || !$this->validate_password($password, $user->password)) {
        header('HTTP/1.0 401 Unauthorized');
        echo json_encode(['status' => false, 'message' => 'Invalid credentials']);
        $log_message = $username." API Authentication Failed ";
        log_user_action($log_message);
        exit;
    }
        $log_message = $username." API Authentication Succeded";
        log_user_action($log_message);
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