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


	public function get_active_staff_data($limit=FALSE, $start=FALSE, $filters=FALSE)
{
    $this->db->select('staff.*, staff_contracts.*, jobs.*, jobs_acting.*, grades.*, 
                        contracting_institutions.*, funders.*, contract_types.*, 
                        duty_stations.*, divisions.*, status.*, nationalities.*');
    
    $this->db->from('staff'); // Explicitly selecting the base table
	//
	@$lname =  $filters['lname'];
	unset($filters['lname']);
	
    $active_staff =array('1,2');

	           $this->db->where_in('staff_contracts.status_id',$active_staff);
	if (!empty($filters)) { // Ensure filters are not empty
		foreach ($filters as $key => $value) {
			if (!empty($value)&&($key!='staff_id')) { // Apply only if value is not empty
				$this->db->like("staff.$key", $value,'start'); // Use table alias
			}
			elseif($key=='staff_id'){
				$this->db->where("staff.$key", $value);

			}
		}
	}
	if(!empty($lname)){
		$this->db->group_start();
		$this->db->like('lname', "$lname","both");
		$this->db->or_like('fname', "$lname","both");
		$this->db->group_end();
	}
	

    // Joins with Aliases
    $this->db->join('staff_contracts', 'staff_contracts.staff_id = staff.staff_id');
    $this->db->join('jobs', 'jobs.job_id = staff_contracts.job_id');
    $this->db->join('jobs_acting', 'jobs_acting.job_acting_id = staff_contracts.job_acting_id');
    $this->db->join('grades', 'grades.grade_id = staff_contracts.grade_id');
    $this->db->join('contracting_institutions', 'contracting_institutions.contracting_institution_id = staff_contracts.contracting_institution_id');
    $this->db->join('funders', 'funders.funder_id = staff_contracts.funder_id');
    $this->db->join('contract_types', 'contract_types.contract_type_id = staff_contracts.contract_type_id');
    $this->db->join('duty_stations', 'duty_stations.duty_station_id = staff_contracts.duty_station_id');
    $this->db->join('divisions', 'divisions.division_id = staff_contracts.division_id');
    $this->db->join('status', 'status.status_id = staff_contracts.status_id');
    $this->db->join('nationalities', 'nationalities.nationality_id = staff.nationality_id');

    // Apply pagination limit
    if ($limit) {
        $this->db->limit($limit, $start);
    }

    $query = $this->db->get();

	//dd($this->db->last_query());


    return  $query->result();
}



public function get_all_staff_data($limit=FALSE, $start=FALSE, $filters=FALSE)
{
    $this->db->select('staff.*, nationalities.*');
    
    $this->db->from('staff'); // Explicitly selecting the base table
	//
	if (!empty($filters)) { // Ensure filters are not empty
		foreach ($filters as $key => $value) {
			if (!empty($value)&&($key!='staff_id')) { // Apply only if value is not empty
				$this->db->like("staff.$key", $value,'start'); // Use table alias
			}
			elseif($key=='staff_id'){
				$this->db->where("staff.$key", $value);

			}
		}
	}
	$staff = array(1,2,3,7);
	$this->db->where_in('staff_contracts.status_id',$staff);

    // Joins with Aliases
    $this->db->join('staff_contracts', 'staff_contracts.staff_id = staff.staff_id');
    // $this->db->join('jobs', 'jobs.job_id = staff_contracts.job_id');
    // $this->db->join('jobs_acting', 'jobs_acting.job_acting_id = staff_contracts.job_acting_id');
    // $this->db->join('grades', 'grades.grade_id = staff_contracts.grade_id');
    // $this->db->join('contracting_institutions', 'contracting_institutions.contracting_institution_id = staff_contracts.contracting_institution_id');
    // $this->db->join('funders', 'funders.funder_id = staff_contracts.funder_id');
    // $this->db->join('contract_types', 'contract_types.contract_type_id = staff_contracts.contract_type_id');
    // $this->db->join('duty_stations', 'duty_stations.duty_station_id = staff_contracts.duty_station_id');
    // $this->db->join('divisions', 'divisions.division_id = staff_contracts.division_id');
    // $this->db->join('status', 'status.status_id = staff_contracts.status_id');
    $this->db->join('nationalities', 'nationalities.nationality_id = staff.nationality_id');

    // Apply pagination limit
    if ($limit) {
        $this->db->limit($limit, $start);
    }

    $query = $this->db->get();

	//dd($this->db->last_query());


    return  $query->result();
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
		$this->db->where('staff_contract_id', "$latest_contract");
		$this->db->where('staff_contracts.staff_id', $id);
		$this->db->join('jobs', 'jobs.job_id = staff_contracts.job_id');
		$this->db->join('jobs_acting', 'jobs_acting.job_acting_id = staff_contracts.job_acting_id');
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
			'second_supervisor' => $this->input->post('second_supervisor'),
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
		if ($this->db->affected_rows() > 0) {
			return $this->db->insert_id();
		} else {
			// Log or handle the error:
			$error = $this->db->error();
			log_message('error', 'DB Insert Error: ' . $error['message']);
			return false;
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
		return $query;
	}
	public function update_contract($data)
	{
		$this->db->where('staff_contract_id', $data['staff_contract_id']);
		$query = $this->db->update('staff_contracts', $data);
		return $query;
	}



	public function get_status($flag)
	{
		return Employee::with(['contracts' => function ($query) use ($flag) {
				if (!is_null($flag)) {
					$query->where('status_id', $flag);
				}
			}, 'contracts.funder'])
			->when($flag, function ($query) use ($flag) {
				$query->whereHas('contracts', function (Builder $query) use ($flag) {
					$query->where('status_id', $flag);
				});
			})
			->orderBy("lname", "desc")
			->take(400)
			->skip(0)
			->get();
	}

	public function getBirthdaysForToday()
	{
		// Get the current date
		$currentDate = date('Y-m-d');
	
		// Retrieve employees with birthdays for today and with contracts having status_id in (1, 2)
		return Employee::whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') = DATE_FORMAT('$currentDate', '%m-%d')")
			->whereHas('contracts', function ($query) {
				$query->whereIn('status_id', [1, 2]);
			})
			->with('contracts')
			->get();
	}
	

	public function getBirthdaysForTomorrow()
	{
		// Get the date for tomorrow
		$tomorrowDate = date('Y-m-d', strtotime('+1 day'));

		// Retrieve employees with birthdays for tomorrow
		return Employee::whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') = DATE_FORMAT('$tomorrowDate', '%m-%d')")
		->whereHas('contracts', function ($query) {
			$query->whereIn('status_id', [1, 2]);
		})
			->with('contracts')
			->get();
	}

	public function getBirthdaysForNextSevenDays()
	{
		// Get the current date
		$currentDate = date('Y-m-d');

		// Get the date for 7 days from now
		$nextSevenDays = date('Y-m-d', strtotime('+7 days'));

		// Retrieve employees with birthdays in the next 7 days
		return Employee::whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') BETWEEN DATE_FORMAT('$currentDate', '%m-%d') AND DATE_FORMAT('$nextSevenDays', '%m-%d')")
		->whereHas('contracts', function ($query) {
			$query->whereIn('status_id', [1, 2]);
		})
			->with('contracts')
			->get();
	}

	public function getBirthdaysForNextThirtyDays()
	{
		// Get the current date
		$currentDate = date('Y-m-d');

		// Get the date for 30 days from now
		$nextThirtyDays = date('Y-m-d', strtotime('+30 days'));

		// Retrieve employees with birthdays in the next 30 days
		return Employee::whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') BETWEEN DATE_FORMAT('$currentDate', '%m-%d') AND DATE_FORMAT('$nextThirtyDays', '%m-%d')")
		->whereHas('contracts', function ($query) {
			$query->whereIn('status_id', [1, 2]);
		})
			->with('contracts')
			->get();
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

		$this->db->insert('staff_contracts', $data);
		return $this->db->insert_id();
	}
	
}
