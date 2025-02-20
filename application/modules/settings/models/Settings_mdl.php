<?php

class Settings_mdl extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model("Employee");
        $this->load->model("Contracts");
    }

    public function get_content($table_name, $column_name = FALSE, $caller_value = FALSE){
        $this->db->select('*');
        $this->db->from($table_name);
        $query = $this->db->get();
        return $query;
    }

    // Add Content
    public function add_content($table){

        if($table === 'duty_stations'){
            $data = array(
                'duty_station_name' => $this->input->post('duty_station_name'),
                'country' => $this->input->post('country'),
                'type' => $this->input->post('type'),
            );
        }elseif($table === 'contracting_institutions'){
            $data = array(
                'contracting_institution' => $this->input->post('contracting_institution'),
            );
        }elseif($table === 'contract_types'){
            $data = array(
                'contract_type' => $this->input->post('contract_type'),
            );
        }elseif($table === 'divisions'){
            $data = array(
                'division_name' => $this->input->post('division_name'),
                'division_head' => $this->input->post('division_head'),
            );
        }elseif($table === 'grades'){
            $data = array(
                'grade' => $this->input->post('grade'),
            );
        }elseif($table === 'jobs'){
            $data = array(
                'job_name' => $this->input->post('job_name'),
            );
        }elseif($table === 'jobs_acting'){
            $data = array(
                'job_acting' => $this->input->post('job_acting'),
            );
        }elseif($table === 'au_values'){
            $data = array(
                'description' => $this->input->post('description'),
                'annotation' => $this->input->post('annotation'),
                'score_5' => $this->input->post('score_5'),
                'score_4' => $this->input->post('score_4'),
                'score_3' => $this->input->post('score_3'),
                'score_2' => $this->input->post('score_2'),
                'score_1' => $this->input->post('score_1'),
                'category' => $this->input->post('category'),
                'version' => $this->input->post('version'),
            );
        }elseif($table === 'funders'){
            $data = array(
                'funder' => $this->input->post('funder'),
            );
        }elseif($table === 'leave_types'){
            $data = array(
                'leave_name' => $this->input->post('leave_name'),
                'leave_days' => $this->input->post('leave_days'),
                'is_accrued' => $this->input->post('is_accrued'),
                'accrual_rate' => $this->input->post('accrual_rate'),
            );
        }elseif($table === 'training_skills'){
            $data = array(
                'skill' => $this->input->post('skill'),
            );
        }elseif($table === 'regions'){
            $data = array(
                'region_name' => $this->input->post('region_name'),
            );
        }
        elseif($table === 'units'){
            $data = array(
                'unit_name' => $this->input->post('unit_name'),
                'staff_id' => $this->input->post('staff_id'),
                'division_id' => $this->input->post('division_id'),
            );
        }
        $this->db->insert($table, $data);
        return true;

    }

    // Update Content
    public function update_content($table, $column_name, $caller_value){

        if($table === 'duty_stations'){
            $data = array(
                'duty_station_name' => $this->input->post('duty_station_name'),
                'country' => $this->input->post('country'),
                'type' => $this->input->post('type'),
            );
        }elseif($table === 'contracting_institutions'){
            $data = array(
                'contracting_institution' => $this->input->post('contracting_institution'),
            );
        }elseif($table === 'contract_types'){
            $data = array(
                'contract_type' => $this->input->post('contract_type'),
            );
        }elseif($table === 'divisions'){
            $data = array(
                'division_name' => $this->input->post('division_name'),
                'division_head' => $this->input->post('division_head'),
            );
        }elseif($table === 'grades'){
            $data = array(
                'grade' => $this->input->post('grade'),
            );
        }elseif($table === 'jobs'){
            $data = array(
                'job_name' => $this->input->post('job_name'),
            );
        }elseif($table === 'jobs_acting'){
            $data = array(
                'job_acting' => $this->input->post('job_acting'),
            );
        }elseif($table === 'au_values'){
            $data = array(
                'description' => $this->input->post('description'),
                'annotation' => $this->input->post('annotation'),
                'score_5' => $this->input->post('score_5'),
                'score_4' => $this->input->post('score_4'),
                'score_3' => $this->input->post('score_3'),
                'score_2' => $this->input->post('score_2'),
                'score_1' => $this->input->post('score_1'),
                'category' => $this->input->post('category'),
                'version' => $this->input->post('version'),
            );
        }elseif($table === 'funders'){
            $data = array(
                'funder' => $this->input->post('funder'),
            );
        }elseif($table === 'leave_types'){
            $data = array(
                'leave_name' => $this->input->post('leave_name'),
                'leave_days' => $this->input->post('leave_days'),
                'is_accrued' => $this->input->post('is_accrued'),
                'accrual_rate' => $this->input->post('accrual_rate'),
            );
        }elseif($table === 'training_skills'){
            $data = array(
                'skill' => $this->input->post('skill'),
            );
        }elseif($table === 'regions'){
            $data = array(
                'region_name' => $this->input->post('region_name'),
            );
        }
        elseif($table === 'units'){
            $data = array(
                'unit_name' => $this->input->post('unit_name'),
                'staff_id' => $this->input->post('staff_id'),
                'division_id' => $this->input->post('division_id'),
            );
        }
        $this->db->where($column_name, $caller_value);
        $this->db->update($table, $data);
        return true;

    }

    // Delete Content
    public function delete_content($table, $column_name, $caller_value){
        $this->db->where($column_name, $caller_value);
        $this->db->delete($table);
        return true;

    }
	public function update_variables($data)
	{
		$this->db->where('id', $data['id']);
		$query = $this->db->update('setting', $data);
	return $query;
	}
	public function getSettings()
	{
	return $this->db->get('setting')->row();
	}


}

 
    
  
