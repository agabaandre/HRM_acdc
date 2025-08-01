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

    public function add_content($table)
    {
        if ($table === 'duty_stations') {
            $data = [
                'duty_station_name' => $this->input->post('duty_station_name'),
                'country'           => $this->input->post('country'),
                'type'              => $this->input->post('type'),
            ];
        } elseif ($table === 'contracting_institutions') {
            $data = [
                'contracting_institution' => $this->input->post('contracting_institution'),
            ];
        } elseif ($table === 'contract_types') {
            $data = [
                'contract_type' => $this->input->post('contract_type'),
            ];
        } elseif ($table === 'divisions') {
            $data = [
                'division_name'           => $this->input->post('division_name', true),
                'division_head'           => $this->input->post('division_head', true),
                'focal_person'            => $this->input->post('focal_person', true),
                'finance_officer'         => $this->input->post('finance_officer', true),
                'admin_assistant'         => $this->input->post('admin_assistant', true),
                'directorate_id'          => $this->input->post('directorate_id', true),
                'head_oic_id'             => $this->input->post('head_oic_id', true),
                'head_oic_start_date'     => $this->input->post('head_oic_start_date', true),
                'head_oic_end_date'       => $this->input->post('head_oic_end_date', true),
                'director_id'             => $this->input->post('director_id', true),
                'director_oic_id'         => $this->input->post('director_oic_id', true),
                'director_oic_start_date' => $this->input->post('director_oic_start_date', true),
                'director_oic_end_date'   => $this->input->post('director_oic_end_date', true),
                'category'                => $this->input->post('category', true),
            ];
    
            $this->db->insert($table, $data);
    
            if (!empty($data['director_id']) && !empty($data['director_oic_id'])) {
                $update_data = [
                    'director_oic_id'         => $data['director_oic_id'],
                    'director_oic_start_date' => $data['director_oic_start_date'],
                    'director_oic_end_date'   => $data['director_oic_end_date'],
                ];
                $this->db->where('director_id', $data['director_id']);
                $this->db->update('divisions', $update_data);
            }
    
            return true;
        } elseif ($table === 'directorates') {
            $data = [
                'name'      => $this->input->post('directorate_name', true),
                'is_active' => (int)$this->input->post('is_active'),
                'created_at' => date('Y-m-d H:i:s'),
            ];
        } elseif ($table === 'nationalities') {
            $data = [
                'nationality'      => $this->input->post('nationality', true),
                'nationality_name' => $this->input->post('nationality_name', true),
                'continent'        => $this->input->post('continent', true),
                'iso2'             => strtoupper($this->input->post('iso2', true)),
                'iso3'             => strtoupper($this->input->post('iso3', true)),
                'region_id'        => (int)$this->input->post('region_id', true),
            ];
        } elseif ($table === 'grades') {
            $data = ['grade' => $this->input->post('grade')];
        } elseif ($table === 'jobs') {
            $data = ['job_name' => $this->input->post('job_name')];
        } elseif ($table === 'jobs_acting') {
            $data = ['job_acting' => $this->input->post('job_acting')];
        } elseif ($table === 'au_values') {
            $data = [
                'description' => $this->input->post('description'),
                'annotation'  => $this->input->post('annotation'),
                'score_5'     => $this->input->post('score_5'),
                'score_4'     => $this->input->post('score_4'),
                'score_3'     => $this->input->post('score_3'),
                'score_2'     => $this->input->post('score_2'),
                'score_1'     => $this->input->post('score_1'),
                'category'    => $this->input->post('category'),
                'version'     => $this->input->post('version'),
            ];
        } elseif ($table === 'funders') {
            $data = ['funder' => $this->input->post('funder')];
        } elseif ($table === 'leave_types') {
            $data = [
                'leave_name'   => $this->input->post('leave_name'),
                'leave_days'   => $this->input->post('leave_days'),
                'is_accrued'   => $this->input->post('is_accrued'),
                'accrual_rate' => $this->input->post('accrual_rate'),
            ];
        } elseif ($table === 'training_skills') {
            $data = ['skill' => $this->input->post('skill')];
        } elseif ($table === 'regions') {
            $data = ['region_name' => $this->input->post('region_name')];
        } elseif ($table === 'units') {
            $data = [
                'unit_name'   => $this->input->post('unit_name'),
                'staff_id'    => $this->input->post('staff_id'),
                'division_id' => $this->input->post('division_id'),
            ];
        }
    
        // For all other tables except 'divisions' (already inserted)
        if (!in_array($table, ['divisions'])) {
            $this->db->insert($table, $data);
        }
    
        return true;
    }
    
    

    public function update_content($table, $column_name, $caller_value)
    {
        if ($table === 'duty_stations') {
            $data = [
                'duty_station_name' => $this->input->post('duty_station_name'),
                'country'           => $this->input->post('country'),
                'type'              => $this->input->post('type'),
            ];
        } elseif ($table === 'contracting_institutions') {
            $data = [
                'contracting_institution' => $this->input->post('contracting_institution'),
            ];
        } elseif ($table === 'contract_types') {
            $data = [
                'contract_type' => $this->input->post('contract_type'),
            ];
        } elseif ($table === 'divisions') {
            $data = [
                'division_name'           => $this->input->post('division_name', true),
                'division_head'           => $this->input->post('division_head', true),
                'focal_person'            => $this->input->post('focal_person', true),
                'finance_officer'         => $this->input->post('finance_officer', true),
                'admin_assistant'         => $this->input->post('admin_assistant', true),
                'directorate_id'          => $this->input->post('directorate_id', true),
                'head_oic_id'             => $this->input->post('head_oic_id', true),
                'head_oic_start_date'     => $this->input->post('head_oic_start_date', true),
                'head_oic_end_date'       => $this->input->post('head_oic_end_date', true),
                'director_id'             => $this->input->post('director_id', true),
                'director_oic_id'         => $this->input->post('director_oic_id', true),
                'director_oic_start_date' => $this->input->post('director_oic_start_date', true),
                'director_oic_end_date'   => $this->input->post('director_oic_end_date', true),
                'category'                => $this->input->post('category', true),
            ];
    
            $this->db->where($column_name, $caller_value);
            $this->db->update($table, $data);
    
            if (!empty($data['director_id']) && !empty($data['director_oic_id'])) {
                $update_oic = [
                    'director_oic_id'         => $data['director_oic_id'],
                    'director_oic_start_date' => $data['director_oic_start_date'],
                    'director_oic_end_date'   => $data['director_oic_end_date'],
                ];
                $this->db->where('director_id', $data['director_id']);
                $this->db->update('divisions', $update_oic);
            }
    
            return true;
        } elseif ($table === 'directorates') {
            $data = [
                'name'       => $this->input->post('directorate_name', true),
                'is_active'  => (int)$this->input->post('is_active'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        } elseif ($table === 'nationalities') {
            $data = [
                'nationality'      => $this->input->post('nationality', true),
                'nationality_name' => $this->input->post('nationality_name', true),
                'continent'        => $this->input->post('continent', true),
                'iso2'             => strtoupper($this->input->post('iso2', true)),
                'iso3'             => strtoupper($this->input->post('iso3', true)),
                'region_id'        => (int)$this->input->post('region_id', true),
            ];
        } elseif ($table === 'grades') {
            $data = ['grade' => $this->input->post('grade')];
        } elseif ($table === 'jobs') {
            $data = ['job_name' => $this->input->post('job_name')];
        } elseif ($table === 'jobs_acting') {
            $data = ['job_acting' => $this->input->post('job_acting')];
        } elseif ($table === 'au_values') {
            $data = [
                'description' => $this->input->post('description'),
                'annotation'  => $this->input->post('annotation'),
                'score_5'     => $this->input->post('score_5'),
                'score_4'     => $this->input->post('score_4'),
                'score_3'     => $this->input->post('score_3'),
                'score_2'     => $this->input->post('score_2'),
                'score_1'     => $this->input->post('score_1'),
                'category'    => $this->input->post('category'),
                'version'     => $this->input->post('version'),
            ];
        } elseif ($table === 'funders') {
            $data = ['funder' => $this->input->post('funder')];
        } elseif ($table === 'leave_types') {
            $data = [
                'leave_name'   => $this->input->post('leave_name'),
                'leave_days'   => $this->input->post('leave_days'),
                'is_accrued'   => $this->input->post('is_accrued'),
                'accrual_rate' => $this->input->post('accrual_rate'),
            ];
        } elseif ($table === 'training_skills') {
            $data = ['skill' => $this->input->post('skill')];
        } elseif ($table === 'regions') {
            $data = ['region_name' => $this->input->post('region_name')];
        } elseif ($table === 'units') {
            $data = [
                'unit_name'   => $this->input->post('unit_name'),
                'staff_id'    => $this->input->post('staff_id'),
                'division_id' => $this->input->post('division_id'),
            ];
        }
    
        // Run update if not handled specially above
        if ($table !== 'divisions') {
            $this->db->where($column_name, $caller_value);
            $this->db->update($table, $data);
        }
    
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
    public function update_ppa_variables($data)

	{
        if(!empty($data)){
		$this->db->where('id', $data['id']);
        }
		$query = $this->db->update('ppa_configs', $data);
	return $query;
	}
   
	public function get_ppa()
	{
	return $this->db->get('ppa_configs')->row();
	}
    public function getSettings()
	{
	return $this->db->get('setting')->row();
	}


}

 
    
  
