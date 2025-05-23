<?php

use Illuminate\Database\Eloquent\Builder;

class Staff_mdl extends CI_Model
{

	public function __construct()
	{
		parent::__construct();
		$this->load->model("Employee");
		$this->load->model("Contracts");
	}

	public function get_active_staff_data($filters = array(), $limit = FALSE, $start = FALSE)
	{
		$subquery = $this->db
		->select('MAX(staff_contract_id)')
		->from('staff_contracts')
		->group_by('staff_id')
		->get_compiled_select();

		$this->db->select('
			s.SAPNO,s.title, s.fname, 
			s.lname, s.oname, sc.grade_id, g.grade, s.date_of_birth, 
			s.gender, sc.job_id, j.job_name, sc.job_acting_id, ja.job_acting, 
			ci.contracting_institution, ci.contracting_institution_id, 
			ct.contract_type, n.nationality, d.division_name, 
			sc.first_supervisor, sc.second_supervisor,f.funder, f.funder_id, ds.duty_station_name, 
			s.initiation_date, sc.status_id, sc.start_date,sc.end_date, st.status, sc.duty_station_id, sc.contract_type_id,
			s.email_status, s.email_disabled_at, s.email_disabled_by,
			sc.division_id, s.nationality_id, s.staff_id,s.tel_1, s.tel_2, s.whatsapp, s.work_email, s.photo,
			s.private_email, s.physical_location
		');
		
		$this->db->from('staff s');
		
		// Joins with explicit aliasing
		$this->db->join('staff_contracts sc', 'sc.staff_id = s.staff_id', 'left');
		$this->db->join('funders f','f.funder_id = sc.funder_id');
		$this->db->join('grades g', 'g.grade_id = sc.grade_id', 'left');
		$this->db->join('nationalities n', 'n.nationality_id = s.nationality_id', 'left');
		$this->db->join('divisions d', 'd.division_id = sc.division_id', 'left');
		$this->db->join('duty_stations ds', 'ds.duty_station_id = sc.duty_station_id', 'left');
		$this->db->join('contracting_institutions ci', 'ci.contracting_institution_id = sc.contracting_institution_id', 'left');
		$this->db->join('contract_types ct', 'ct.contract_type_id = sc.contract_type_id', 'left');
		$this->db->join('jobs j', 'j.job_id = sc.job_id', 'left');
		$this->db->join('jobs_acting ja', 'ja.job_acting_id = sc.job_acting_id', 'left');
		$this->db->join('status st', 'st.status_id = sc.status_id', 'left');
	    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
		// Apply active staff filter (status_id IN (1,2))
		$this->db->where_in('sc.status_id', [1, 2]);

			// Handle filters for divsion_id & dutystation
			if (!empty($filters['division_id'])) {
				$this->db->where_in('sc.division_id', $filters['division_id']);
			}
			if (!empty($filters['duty_station_id'])) {
				$this->db->where_in('sc.duty_station_id', $filters['duty_station_id']);
			}
			if (!empty($filters['funder_id'])) {
				$this->db->where_in('sc.funder_id', $filters['funder_id']);
			}
	
		// Handle filters dynamically
		@$csv = $filters['csv'];
		@$pdf = $filters['pdf'];
		@$lname = $filters['lname'];
		unset($filters['lname']);
		unset($filters['csv']);
		unset($filters['pdf']);
		if (isset($filters['division_id'])) {
			unset($filters['division_id']);
		}
		if (isset($filters['duty_station_id'])) {
			unset($filters['duty_station_id']);
		}
		if (isset($filters['funder_id'])) {
			unset($filters['funder_id']);
		}
	
		if (!empty($filters)) {
			foreach ($filters as $key => $value) {
				if (!empty($value) && $key != 'staff_id') {
					$this->db->where("s.$key", $value);
				} elseif ($key == 'staff_id') {
					$this->db->where("s.$key", $value);
				}
			}
		}
	
		// Search by last name or first name
		if (!empty($lname)) {
			$this->db->group_start();
			$this->db->like('s.lname', $lname, 'both');
			$this->db->or_like('s.fname', $lname, 'both');
			$this->db->group_end();
		}
		$this->db->order_by('fname','ASC');
		// Apply pagination limit if not exporting CSV
		if (($limit && $csv != 1 && $pdf != 1))  {
			$this->db->limit($limit, $start);
		}
	
		$query = $this->db->get();
	
		// Debugging query (Uncomment for debugging)
		// echo $this->db->last_query(); exit;
	
		return ($csv == 1) ? $query->result_array() : $query->result();
	}
	

	public function get_staff_by_division($division_id = false)
{
    // Subquery: Get latest contract per staff
    $subquery = $this->db
        ->select('MAX(staff_contract_id)', false)
        ->from('staff_contracts')
        ->group_by('staff_id')
        ->get_compiled_select();

    $this->db->select('
        s.staff_id, s.SAPNO, s.title, s.fname, s.lname, s.oname, 
        s.gender, s.date_of_birth, s.work_email, s.tel_1, s.tel_2, 
        s.whatsapp, s.photo, s.private_email, s.physical_location,
        sc.division_id, sc.job_id, j.job_name,
        sc.start_date, sc.end_date, sc.status_id,
        d.division_name, ds.duty_station_name,
        g.grade, st.status,f.funder
    ');

    $this->db->from('staff s');

    $this->db->join('staff_contracts sc', 'sc.staff_id = s.staff_id', 'left');
    $this->db->join('grades g', 'g.grade_id = sc.grade_id', 'left');
	$this->db->join('funders f','f.funder_id = sc.funder_id');
    $this->db->join('divisions d', 'd.division_id = sc.division_id', 'left');
    $this->db->join('duty_stations ds', 'ds.duty_station_id = sc.duty_station_id', 'left');
    $this->db->join('jobs j', 'j.job_id = sc.job_id', 'left');
    $this->db->join('status st', 'st.status_id = sc.status_id', 'left');

    // Where latest contract only
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);

    // Active contracts only
    $this->db->where_in('sc.status_id', [1, 2, 3, 7]);

    // Optional filter by division
    if (!empty($division_id)) {
        $this->db->where('sc.division_id', $division_id);
    }

    // Order by name
    $this->db->order_by('s.fname', 'ASC');

    return $this->db->get()->result();
}



	public function get_all_staff_data($filters = array(), $limit = FALSE, $start = FALSE)
	{
		
		$subquery = $this->db
		->select('MAX(staff_contract_id)')
		->from('staff_contracts')
		->group_by('staff_id')
		->get_compiled_select();
		
	
		$this->db->select('
			s.SAPNO,s.title, s.fname, 
			s.lname, s.oname, sc.grade_id, g.grade, s.date_of_birth, 
			s.gender, sc.job_id, j.job_name, sc.job_acting_id, ja.job_acting, 
			ci.contracting_institution, ci.contracting_institution_id, 
			ct.contract_type, n.nationality, d.division_name, 
			sc.first_supervisor, sc.second_supervisor,f.funder,f.funder_id, ds.duty_station_name, 
			s.initiation_date, sc.status_id, sc.start_date,sc.end_date, st.status, sc.duty_station_id, sc.contract_type_id,
			s.email_status, s.email_disabled_at, s.email_disabled_by,
			sc.division_id, s.nationality_id, s.staff_id,s.tel_1, s.tel_2, s.whatsapp, s.work_email, s.photo,
			s.private_email, s.physical_location
		');
		
		$this->db->from('staff s');
		
		// Joins with explicit aliasing
		$this->db->join('staff_contracts sc', 'sc.staff_id = s.staff_id', 'left');
		$this->db->join('grades g', 'g.grade_id = sc.grade_id', 'left');
		$this->db->join('nationalities n', 'n.nationality_id = s.nationality_id', 'left');
		$this->db->join('divisions d', 'd.division_id = sc.division_id', 'left');
		$this->db->join('duty_stations ds', 'ds.duty_station_id = sc.duty_station_id', 'left');
		$this->db->join('funders f', 'f.funder_id = sc.funder_id', 'left');
		$this->db->join('contracting_institutions ci', 'ci.contracting_institution_id = sc.contracting_institution_id', 'left');
		$this->db->join('contract_types ct', 'ct.contract_type_id = sc.contract_type_id', 'left');
		$this->db->join('jobs j', 'j.job_id = sc.job_id', 'left');
		$this->db->join('jobs_acting ja', 'ja.job_acting_id = sc.job_acting_id', 'left');
		$this->db->join('status st', 'st.status_id = sc.status_id', 'left');
		$this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
	
		// Apply all staff filter (status_id IN (1,2))
		$this->db->where_in('sc.status_id', [1,2,3,7]);
		

		// Handle filters for divsion_id & dutystation
		if (!empty($filters['division_id'])) {
			$this->db->where_in('sc.division_id', $filters['division_id']);
		}
		if (!empty($filters['duty_station_id'])) {
			$this->db->where_in('sc.duty_station_id', $filters['duty_station_id']);
		}
		if (!empty($filters['funder_id'])) {
			$this->db->where_in('sc.funder_id', $filters['funder_id']);
		}
	
		// Handle filters dynamically
		@$csv = $filters['csv'];
	    @$pdf = $filters['pdf'];
		@$lname = $filters['lname'];
		if (!empty($filters)) {
			if (isset($filters['lname'])) {
				unset($filters['lname']);
			}
			if (isset($filters['csv'])) {
				unset($filters['csv']);
			}
			if (isset($filters['pdf'])) {
				unset($filters['pdf']);
			}
			if (isset($filters['division_id'])) {
				unset($filters['division_id']);
			}
			if (isset($filters['duty_station_id'])) {
				unset($filters['duty_station_id']);
			}
			if (isset($filters['funder_id'])) {
				unset($filters['funder_id']);
			}
		}
		

	
		if (!empty($filters)) {
			foreach ($filters as $key => $value) {
				if (!empty($value) && $key != 'staff_id') {
					$this->db->where("s.$key", $value);
				} elseif ($key == 'staff_id') {
					$this->db->where("s.$key", $value);
				}
			}
		}
	
		// Search by last name or first name
		if (!empty($lname)) {
			$this->db->group_start();
			$this->db->like('s.lname', $lname, 'both');
			$this->db->or_like('s.fname', $lname, 'both');
			$this->db->group_end();
		}
		$this->db->order_by('fname','ASC');
		// Apply pagination limit if not exporting CSV
		if (($limit && $csv != 1 && $pdf != 1))  {
			$this->db->limit($limit, $start);
		}
	
		$query = $this->db->get();
	
		// Debugging query (Uncomment for debugging)
		 //echo $this->db->last_query(); exit;
	
		return ($csv == 1) ? $query->result_array() : $query->result();
	}
	// Get staff contracts
	public function get_staff_contracts($id)
	{
		$this->db->select('*');
		$this->db->from('staff_contracts');  // Select from staff_contracts
	
		// Specify the table alias in WHERE clause
		$this->db->where('staff_contracts.staff_id', $id);
	
		// Join Tables with Aliases
		$this->db->join('jobs', 'jobs.job_id = staff_contracts.job_id', 'left');
		$this->db->join('jobs_acting', 'jobs_acting.job_acting_id = staff_contracts.job_acting_id', 'left');
		$this->db->join('grades', 'grades.grade_id = staff_contracts.grade_id', 'left');
		$this->db->join('contracting_institutions', 'contracting_institutions.contracting_institution_id = staff_contracts.contracting_institution_id', 'left');
		$this->db->join('funders', 'funders.funder_id = staff_contracts.funder_id', 'left');
		$this->db->join('contract_types', 'contract_types.contract_type_id = staff_contracts.contract_type_id', 'left');
		$this->db->join('duty_stations', 'duty_stations.duty_station_id = staff_contracts.duty_station_id', 'left');
		$this->db->join('divisions', 'divisions.division_id = staff_contracts.division_id', 'left');
		$this->db->join('status', 'status.status_id = staff_contracts.status_id', 'left');
		$this->db->join('staff', 'staff.staff_id = staff_contracts.staff_id', 'left');  // Avoid ambiguity
		$this->db->join('nationalities', 'nationalities.nationality_id = staff.nationality_id', 'left');
	
		$this->db->order_by('staff_contracts.start_date', 'DESC');
	
		$query = $this->db->get(); // Removed redundant 'staff_contracts' argument
		return $query->result();
	}
	

	// Get staff contracts
	public function get_latest_contracts($id)
	{

		$latest_contract = $this->max_contract($id);
		//dd($latest_contract);
		$this->db->where('staff_contract_id', "$latest_contract");
		$this->db->where('staff_contracts.staff_id', $id);
		$this->db->join('jobs', 'jobs.job_id = staff_contracts.job_id');
		$this->db->join('jobs_acting', 'jobs_acting.job_acting_id = staff_contracts.job_acting_id','left');
		$this->db->join('grades', 'grades.grade_id = staff_contracts.grade_id');
		$this->db->join('contracting_institutions', 'contracting_institutions.contracting_institution_id = staff_contracts.contracting_institution_id');
		$this->db->join('funders', 'funders.funder_id = staff_contracts.funder_id');
		$this->db->join('contract_types', 'contract_types.contract_type_id = staff_contracts.contract_type_id');
		$this->db->join('duty_stations', 'duty_stations.duty_station_id = staff_contracts.duty_station_id');
		$this->db->join('divisions', 'divisions.division_id = staff_contracts.division_id');
		$this->db->join('status', 'status.status_id = staff_contracts.status_id');
		$this->db->join('staff', 'staff.staff_id = staff_contracts.staff_id');
		$this->db->join('nationalities', 'nationalities.nationality_id = staff.nationality_id');
		$query = $this->db->get('staff_contracts');

		//dd($this->db->last_query());
		return $query->row();
	}

	// New Contract
	public function add_new_contract($data)
	{
		$data = array(
			'staff_id' => $this->input->post('staff_id'),
			'job_id' => $this->input->post('job_id'),
			'job_acting_id' => $this->input->post('job_acting_id'),
			'grade_id' => $this->input->post('grade_id'),
			'contracting_institution_id' => $this->input->post('contracting_institution_id'),
			'funder_id' => $this->input->post('funder_id'),
			'first_supervisor' => $this->input->post('first_supervisor'),
			'second_supervisor' => $this->input->post('second_supervisor') !== '' ? $this->input->post('second_supervisor') : NULL,
			'contract_type_id' => $this->input->post('contract_type_id'),
			'duty_station_id' => $this->input->post('duty_station_id'),
			'division_id' => $this->input->post('division_id'),
			'unit_id' => $this->input->post('unit_id'),
			'start_date' => $this->input->post('start_date'),
			'end_date' => $this->input->post('end_date'),
			'status_id' => $this->input->post('status_id'),
			'comments' => $this->input->post('comments'),
		);
		$this->db->insert('staff_contracts', $data);
		$lastid = $this->db->insert_id();
		if ($this->db->affected_rows() > 0) {
           
            $this->mark_enabled($this->input->post('staff_id'),$this->input->post('status_id')) ;
			return  $lastid;

		} else {
			// Log or handle the error:
			$error = $this->db->error();
			log_message('error', 'DB Insert Error: ' . $error['message']);
			return false;
		}
	}

	public function mark_enabled($staff_id,$status_id){
        $user = $this->session->userdata('user')->staff_id; 
        //dd($user);
        $data['email_disabled_by']= 0;
        $data['email_status'] = 1;
        $data['email_disabled_at'] = date('Y-m-d H:i:s');
		if($status_id==1){

        $this->db->where('staff_id',$staff_id);
        $this->db->update('staff',$data);
		
	   echo  "OK";
		}

	else{
		echo "Failed";
	}

    }

   
	public function max_contract($staff_id)
	{
		$this->db->select_max('staff_contract_id');
		$this->db->where('staff_id', "$staff_id");
		return $contract = $this->db->from('staff_contracts')->get()->row()->staff_contract_id;
	}
	public function previous_contract($staff_id, $new_contract_id)
	{
		$this->db->select('staff_contract_id');
		$this->db->from('staff_contracts');
		$this->db->where('staff_contract_id !=', $new_contract_id);
		$this->db->where('staff_id', $staff_id);
		$this->db->order_by('staff_contract_id', 'DESC');
		$this->db->limit(1);
		
		$query = $this->db->get();
		
		if ($query->num_rows() > 0) {
			return $query->row()->staff_contract_id;
		}
		
		return null;
	}
	

	public function all_staff_attributes()
	{
		return  $query = Employee::all();
	}
	public function update_staff($data)
	{
		$this->db->where('staff_id', $data['staff_id']);
		$query = $this->db->update("staff", $data);
		$log_message = "Updated staff Bio Data details. Staff Name:". staff_name($data['staff_id']);
		log_user_action($log_message);
		return $query;
	}
	public function update_contract($data)
	{
		$this->db->where('staff_contract_id', $data['staff_contract_id']);
		$query = $this->db->update('staff_contracts', $data);
		if($query){
			$log_message = "Updated staff Contract details of". staff_name($data['staff_id']);
		log_user_action($log_message);
			$ppa['supervisor_id']=$data['first_supervisor'];
			// $ppa['supervisor2_id'] = NULL;
			$ppa['staff_id'] = $data['staff_id'];
		$this->update_ppa_details($ppa);

		
		}

		return $query;
	}
	public function update_ppa_details($data)
{
    // Ensure only expected fields are updated
    $update = [
        'supervisor_id'   => $data['supervisor_id'] ?? null,
        'supervisor2_id'  => $data['supervisor2_id'] ?? null,
        'updated_at'      => date('Y-m-d H:i:s'),
    ];

    $this->db->where('staff_id', $data['staff_id']);
    $this->db->where_in('draft_status', [0, 1]); // restrict to draft or submitted only

    $result = $this->db->update('ppa_entries', $update);

    // Optional: Log or return last query for debugging
   // log_message('debug', $this->db->last_query());
   $log_message = "Updated staff PPA supervisor details of". staff_name($data['staff_id']);
   log_user_action($log_message);

    return $result;
}




	public function get_status($filters, $limit = FALSE, $start = FALSE)
	{
		// Get latest contract per staff using subquery
		$subquery = $this->db
			->select('MAX(staff_contract_id)')
			->from('staff_contracts')
			->group_by('staff_id')
			->get_compiled_select();
			
	
		// Main query selecting required fields
		$this->db->select('
		    s.SAPNO,s.title, s.fname, 
			s.lname, s.oname, sc.grade_id, g.grade, s.date_of_birth, 
			s.gender, sc.job_id, j.job_name, sc.job_acting_id, ja.job_acting, 
			ci.contracting_institution, ci.contracting_institution_id, 
			ct.contract_type, n.nationality, d.division_name, 
			sc.first_supervisor, sc.second_supervisor,f.funder, ds.duty_station_name, 
			s.initiation_date, sc.status_id, sc.start_date,sc.end_date, st.status, sc.duty_station_id, sc.contract_type_id,
			s.email_status, s.email_disabled_at, s.email_disabled_by,
			sc.division_id, s.nationality_id, s.staff_id,s.tel_1, s.tel_2, s.whatsapp, s.work_email, s.photo,
			s.private_email, s.physical_location
		');
		
		$this->db->from('staff s');
		$this->db->join('staff_contracts sc', 'sc.staff_id = s.staff_id', 'inner');
		$this->db->join('grades g', 'g.grade_id = sc.grade_id', 'left');
		$this->db->join('funders f','f.funder_id = sc.funder_id','left');
		$this->db->join('nationalities n', 'n.nationality_id = s.nationality_id', 'left');
		$this->db->join('divisions d', 'd.division_id = sc.division_id', 'left');
		$this->db->join('duty_stations ds', 'ds.duty_station_id = sc.duty_station_id', 'left');
		$this->db->join('contracting_institutions ci', 'ci.contracting_institution_id = sc.contracting_institution_id', 'left');
		$this->db->join('contract_types ct', 'ct.contract_type_id = sc.contract_type_id', 'left');
		$this->db->join('jobs j', 'j.job_id = sc.job_id', 'left');
		$this->db->join('jobs_acting ja', 'ja.job_acting_id = sc.job_acting_id', 'left');
		$this->db->join('status st', 'st.status_id = sc.status_id', 'left');
	
		//Ensuring only latest contracts are selected
		$this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
	
		// Handle filters for divsion_id & dutystation
		if (!empty($filters['division_id'])) {
			$this->db->where_in('sc.division_id', $filters['division_id']);
		}
		if (!empty($filters['duty_station_id'])) {
			$this->db->where_in('sc.duty_station_id', $filters['duty_station_id']);
		}
		if (!empty($filters['status_id'])) {
			$this->db->where_in('sc.status_id', $filters['status_id']);
		}
		if (!empty($filters['funder_id'])) {
			$this->db->where_in('sc.funder_id', $filters['funder_id']);
		}
	
		if ($this->uri->segment(1) == 'admanager') {
			if ($this->uri->segment(2) == 'expired_accounts') {
				// $this->db->where('s.work_email IS NOT NULL', null, false); 
				$this->db->where('s.email_status', 1);
				$this->db->where_in('st.status_id', [3, 4]);
			} elseif ($this->uri->segment(2) == 'report') {
				$this->db->where_in('st.status_id', [3, 4]);
				// $this->db->where('s.work_email IS NOT NULL', null, false);
				$this->db->where('s.email_status', 0);
				if (!empty($filters['datefrom']) && !empty($filters['dateto'])) {
					$datefrom = $filters['datefrom'] . ' 00:00:00';
					$dateto = $filters['dateto'] . ' 23:59:59';
					$this->db->where("s.email_disabled_at BETWEEN '$datefrom' AND '$dateto'");
				}
			}
		}
	
		if (!empty($filters['lname'])) {
			$this->db->group_start();
			$this->db->like('s.lname', $filters['lname']);
			$this->db->or_like('s.fname', $filters['lname']);
			$this->db->group_end();
		}
		
		
	
		// Special keys to exclude from dynamic loop
		$exclude_keys = ['csv', 'pdf', 'lname', 'status_id', 'datefrom', 'dateto','division_id','duty_station_id','funder_id'];
		
	
		foreach ($filters as $key => $value) {
			if (!empty($value) && !in_array($key, $exclude_keys)) {
				$this->db->where("s.$key", $value);
			}
		}
	
		$this->db->order_by('s.fname', 'ASC');
	
		// Apply pagination if not exporting CSV or PDF
		 if ($limit && $filters['csv']!=1 && $filters['pdf']!=1)  {
			$this->db->limit($limit, $start);
		}
	
		$query = $this->db->get();

	
	return ((@$filters['csv'])==1) ? $query->result_array() : $query->result();
	//dd($this->db->last_query());
	}
	
public function getBirthdays($days)
{
    // Get the current date and the date for 30 days from now

 // Assuming it returns an integer

   
   $currentDate = date('Y-m-d');
   $nextDays = date('Y-m-d', strtotime($currentDate . "+$days days")); 
	
	
;
	

    $this->db->select('
        sc.status_id, st.status, sc.duty_station_id, sc.contract_type_id, 
        sc.division_id, s.nationality_id, s.staff_id, s.title, s.fname, 
        s.lname, s.oname, sc.grade_id, g.grade, s.date_of_birth, 
        s.gender, sc.job_id, j.job_name, sc.job_acting_id, ja.job_acting, 
        ci.contracting_institution, ci.contracting_institution_id, 
        ct.contract_type, n.nationality, d.division_name, 
        sc.first_supervisor, sc.second_supervisor,f.funder, ds.duty_station_name, 
        s.initiation_date, s.tel_1, s.tel_2, s.whatsapp, s.work_email, s.SAPNO, s.photo,
        s.private_email, s.physical_location
    ');
    
    $this->db->from('staff s');
    
    // Joins with explicit aliasing
    $this->db->join('staff_contracts sc', 'sc.staff_id = s.staff_id', 'left');
	$this->db->join('funders f','f.funder_id = sc.funder_id','left');
    $this->db->join('grades g', 'g.grade_id = sc.grade_id', 'left');
    $this->db->join('nationalities n', 'n.nationality_id = s.nationality_id', 'left');
    $this->db->join('divisions d', 'd.division_id = sc.division_id', 'left');
    $this->db->join('duty_stations ds', 'ds.duty_station_id = sc.duty_station_id', 'left');
    $this->db->join('contracting_institutions ci', 'ci.contracting_institution_id = sc.contracting_institution_id', 'left');
    $this->db->join('contract_types ct', 'ct.contract_type_id = sc.contract_type_id', 'left');
    $this->db->join('jobs j', 'j.job_id = sc.job_id', 'left');
    $this->db->join('jobs_acting ja', 'ja.job_acting_id = sc.job_acting_id', 'left');
    $this->db->join('status st', 'st.status_id = sc.status_id', 'left');

    // Filter by active contracts
    $this->db->where_in('st.status_id', [1, 2,7]);

    // Filter for employees with birthdays in the next 30 days
    $this->db->where("DATE_FORMAT(s.date_of_birth, '%m-%d') BETWEEN DATE_FORMAT('$currentDate', '%m-%d') AND DATE_FORMAT('$nextDays', '%m-%d')");

    // Ensure only the latest contract per employee
    $this->db->order_by('sc.staff_id', 'ASC'); 
    $this->db->order_by('sc.staff_contract_id', 'DESC'); 
    $this->db->group_by('sc.staff_id'); 
	// Group by staff_id to get only one contract per employee

    $query = $this->db->get()->result();
	//echo $this->db->last_query(); exit;
    return ($query);
}


	public function add_staff($sapno, $title, $fname, $lname, $oname, $dob, $gender, $nationality_id, $initiation_date, $tel_1, $tel_2, $whatsapp, $work_email, $private_email, $physical_location)
	{
		$data = array(
			'SAPNO' => $sapno,
			'title' => $title,
			'fname' => $fname,
			'lname' => $lname,
			'oname' => $oname,
			'date_of_birth' => $dob,
			'gender' => $gender,
			'nationality_id' => $nationality_id,
			'initiation_date' => $initiation_date,
			'tel_1' => $tel_1,
			'tel_2' => $tel_2,
			'whatsapp' => $whatsapp,
			'work_email' => $work_email,
			'private_email' => $private_email,
			'physical_location' => $physical_location,
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s')
		);
		$sapold = $this->db->query("SELECT * from staff where SAPNO='$sapno'")->num_rows();
		if ($sapold == 0) {
			$this->db->insert('staff', $data);
		} else {
			$this->db->where('SAPNO', $sapno);
			$this->db->update('staff', $data);
		}
		return $this->db->insert_id();
	}

	public function add_contract_information($staff_id, $job_id, $job_acting_id, $grade_id, $contracting_institution_id, $funder_id, $first_supervisor, $second_supervisor, $contract_type_id, $duty_station_id, $division_id, $start_date, $end_date, $status_id, $file_name, $comments)
	{
		$data = array(
			'staff_id' => $staff_id,
			'job_id' => $job_id,
			'job_acting_id' => $job_acting_id,
			'grade_id' => $grade_id,
			'contracting_institution_id' => $contracting_institution_id,
			'funder_id' => $funder_id,
			'first_supervisor' => $first_supervisor,
			'second_supervisor' => $second_supervisor,
			'contract_type_id' => $contract_type_id,
			'duty_station_id' => $duty_station_id,
			'division_id' => $division_id,
			'start_date' => $start_date,
			'end_date' => $end_date,
			'status_id' => $status_id,
			'file_name' => $file_name,
			'comments' => $comments,
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s')

		);

		//dd($data);

		$this->db->insert('staff_contracts', $data);
		return $this->db->insert_id();
	}
	
}
