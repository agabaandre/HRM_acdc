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
	// }
	public function staff($key)
	{
		if($this->auth($key)){

		$sql11 = "SELECT sc.start_date,sc.end_date,f.funder,s.SAPNO,st.status,ds.duty_station_name,s.title,s.fname,s.lname,s.oname,g.grade,s.date_of_birth,s.gender,j.job_name,ja.job_acting,ci.contracting_institution,ct.contract_type,n.nationality,d.division_name,sc.first_supervisor,sc.second_supervisor,ds.duty_station_name,s.initiation_date,s.tel_1,s.tel_2,s.whatsapp,s.work_email,s.private_email,s.physical_location FROM staff s,staff_contracts sc,grades g,nationalities n,divisions d,duty_stations ds,contracting_institutions ci,contract_types ct,jobs_acting ja,status st,jobs j,funders f WHERE n.nationality_id=s.nationality_id AND d.division_id=sc.division_id AND ds.duty_station_id=sc.duty_station_id AND ci.contracting_institution_id=sc.contracting_institution_id AND ct.contract_type_id=sc.contract_type_id AND s.staff_id=sc.staff_id AND sc.grade_id=g.grade_id AND ja.job_acting_id=sc.job_acting_id AND st.status_id=sc.status_id AND sc.status_id IN(1,2,3) AND j.job_id=sc.job_id AND f.funder_id=sc.funder_id AND s.work_email !='' AND sc.division_id != '' AND sc.division_id != '27' AND s.work_email NOT LIKE'xx%'";
        $result = $this->db->query($sql11)->result_array();
		 
        foreach ($result as $row):
		$row['start_date'] = date('m/d/Y', strtotime($row['start_date']));
		$row['end_date'] = date('m/d/Y', strtotime($row['end_date']));
		$row['date_of_birth'] = date('m/d/Y', strtotime($row['date_of_birth']));
		$row['initiation_date'] = date('m/d/Y', strtotime($row['initiation_date']));
		$f = $row['first_supervisor'];
		$s = $row['second_supervisor'];
		
		
		$row['first_supervisor_email'] = $this->get_supervisor_mail($f);
			
		$row['second_supervisor_email'] = $this->get_supervisor_mail($s);
		
	   
		// Concatenate other name and last name as names
	 
		
		if(!empty($row['oname'])){
		$row['name']=trim($row['fname']).' '.trim($row['oname']) . ' ' . trim($row['lname']);
		}
		else{
		$row['name']=trim($row['fname']).' '.trim($row['lname']);
		}
		
		// Remove unnecessary keys
		unset($row['fname']);
		unset($row['lname']);
		unset($row['oname']);
		unset($row['first_supervisor']);
		unset($row['second_supervisor']);
		$data[] = $row;
	     endforeach;
				// Return JSON response
		header('Content-Type: application/json');
			echo json_encode($data);
		}
		else{
			header('Content-Type: application/json');
			echo json_encode(array('success'=> false,'error'=> 'Invalid Reuest'));
		}
	}
	function get_supervisor_mail($h){
   
    //Collect first and second supervisor email
    $result12 = $this->db->query("SELECT work_email FROM staff  WHERE staff_id=$h");
   $row12 = $result12->row();
        
    return $row12->work_email;
        
    }

	
public function auth($key){
    if($key=="YWZyY2FjZGNzdGFmZnRyYWNrZXI"){
		return true;
	}
		else{
			return false;
		}


  }

  public function divisions($key){
	if($this->auth($key)){
	$result = $this->db->get("divisions")->result_array();
	header('Content-Type: application/json');
			echo json_encode($result);
	}
	else{
		header('Content-Type: application/json');
		echo json_encode(array('success'=> false,'error'=> 'Invalid Reuest'));
	}
}




	
}
