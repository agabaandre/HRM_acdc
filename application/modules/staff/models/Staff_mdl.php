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


	public function get_staff_data($limit,$start, $filters)
	{
		if (count($filters) > 0) {
			foreach ($filters as $key => $value) {
				$this->db->where($key, $value);
			}
		}
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
		if ($limit) {
		$this->db->limit($limit, $start);
		}
		$query = $this->db->get('staff');
		if (count($filters) > 0) {
			return $query->row_array();
		} else {
			return $query->result();
		}
	}

	// Get staff contracts
	public function get_staff_contracts($id)
	{
		$this->db->select('*');
		$this->db->from('staff_contracts');
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
		$query = $this->db->get();
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
	public function add_new_contract($table_name)
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
			'start_date' => $this->input->post('start_date'),
			'end_date' => $this->input->post('end_date'),
			'status_id' => $this->input->post('status_id'),
			'file_name' => $this->input->post('file_name'),
			'comments' => $this->input->post('comments'),
		);
		$this->db->insert($table_name, $data);
		return true;
	}

	// Renew Contract
	public function renew_contract($table_name, $staff_contract_id, $renew)
	{
		$renew_data = array(
			'status_id' => $renew
		);
		$this->db->where('staff_contract_id', $staff_contract_id);
		$this->db->update($table_name, $renew_data);
		return true;
	}

	// End Contract
	public function end_contract($table_name, $staff_contract_id, $end)
	{
		$end_data = array(
			'status_id' => $end
		);
		$this->db->where('staff_contract_id', $staff_contract_id);
		$this->db->update($table_name, $end_data);
		return true;
	}

	public function max_contract($staff_id)
	{
		$this->db->select_max('staff_contract_id');
		$this->db->where('staff_id', "$staff_id");
		return $contract = $this->db->from('staff_contracts')->get()->row()->staff_contract_id;
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
		$this->db->where('staff_id', $data['staff_id']);
		$query = $this->db->update('staff_contracts', $data);
		return $query;
	}

	public function get_status($flag)
	{
		$query = Employee::orderBy("lname", "desc");

		$results = $query->with('contracts', 'contracts.funder')
			->when($flag, function ($query, $flag) {
				$query->whereHas('contracts', function (Builder $query) use ($flag) {
					$query->where('status_id', '=', $flag);
				});
			})
			->take(400)
			->skip(0)
			->get();

		return $results;
	}
	public function getBirthdaysForToday()
	{
		// Get the current date
		$currentDate = date('Y-m-d');

		// Retrieve employees with birthdays for today
		return Employee::whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') = DATE_FORMAT('$currentDate', '%m-%d')")
			->with('contracts')
			->get();
	}

	public function getBirthdaysForTomorrow()
	{
		// Get the date for tomorrow
		$tomorrowDate = date('Y-m-d', strtotime('+1 day'));

		// Retrieve employees with birthdays for tomorrow
		return Employee::whereRaw("DATE_FORMAT(date_of_birth, '%m-%d') = DATE_FORMAT('$tomorrowDate', '%m-%d')")
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
