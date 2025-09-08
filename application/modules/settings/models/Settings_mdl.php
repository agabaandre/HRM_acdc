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

    public function get_divisions_paginated($limit = 15, $offset = 0, $search = '') {
        $this->db->select('d.*, 
            dh.fname as head_fname, dh.lname as head_lname,
            fp.fname as focal_fname, fp.lname as focal_lname,
            fa.fname as admin_fname, fa.lname as admin_lname,
            fo.fname as finance_fname, fo.lname as finance_lname');
        $this->db->from('divisions d');
        $this->db->join('staff dh', 'dh.staff_id = d.division_head', 'left');
        $this->db->join('staff fp', 'fp.staff_id = d.focal_person', 'left');
        $this->db->join('staff fa', 'fa.staff_id = d.admin_assistant', 'left');
        $this->db->join('staff fo', 'fo.staff_id = d.finance_officer', 'left');
        
        // Search functionality
        if (!empty($search)) {
            $this->db->group_start();
            $this->db->like('d.division_name', $search);
            $this->db->or_like('d.division_short_name', $search);
            $this->db->or_like('d.category', $search);
            $this->db->or_like('CONCAT(dh.fname, " ", dh.lname)', $search);
            $this->db->or_like('CONCAT(fp.fname, " ", fp.lname)', $search);
            $this->db->group_end();
        }
        
        $this->db->order_by('d.division_name', 'ASC');
        $this->db->limit($limit, $offset);
        
        $query = $this->db->get();
        return $query;
    }

    public function get_divisions_count($search = '') {
        $this->db->from('divisions d');
        $this->db->join('staff dh', 'dh.staff_id = d.division_head', 'left');
        $this->db->join('staff fp', 'fp.staff_id = d.focal_person', 'left');
        $this->db->join('staff fa', 'fa.staff_id = d.admin_assistant', 'left');
        $this->db->join('staff fo', 'fo.staff_id = d.finance_officer', 'left');
        
        // Search functionality
        if (!empty($search)) {
            $this->db->group_start();
            $this->db->like('d.division_name', $search);
            $this->db->or_like('d.division_short_name', $search);
            $this->db->or_like('d.category', $search);
            $this->db->or_like('CONCAT(dh.fname, " ", dh.lname)', $search);
            $this->db->or_like('CONCAT(fp.fname, " ", fp.lname)', $search);
            $this->db->group_end();
        }
        
        return $this->db->count_all_results();
    }

    public function get_divisions_for_datatables() {
        $this->db->select('d.*, 
            dh.fname as head_fname, dh.lname as head_lname,
            fp.fname as focal_fname, fp.lname as focal_lname,
            fa.fname as admin_fname, fa.lname as admin_lname,
            fo.fname as finance_fname, fo.lname as finance_lname');
        $this->db->from('divisions d');
        $this->db->join('staff dh', 'dh.staff_id = d.division_head', 'left');
        $this->db->join('staff fp', 'fp.staff_id = d.focal_person', 'left');
        $this->db->join('staff fa', 'fa.staff_id = d.admin_assistant', 'left');
        $this->db->join('staff fo', 'fo.staff_id = d.finance_officer', 'left');
        $this->db->order_by('d.division_name', 'ASC');
        
        $query = $this->db->get();
        return $query;
    }

    public function get_divisions_datatables($start, $length, $search_value, $order_by, $order_dir) {
        $this->db->select('d.*, 
            dh.fname as head_fname, dh.lname as head_lname,
            fp.fname as focal_fname, fp.lname as focal_lname,
            fa.fname as admin_fname, fa.lname as admin_lname,
            fo.fname as finance_fname, fo.lname as finance_lname');
        $this->db->from('divisions d');
        $this->db->join('staff dh', 'dh.staff_id = d.division_head', 'left');
        $this->db->join('staff fp', 'fp.staff_id = d.focal_person', 'left');
        $this->db->join('staff fa', 'fa.staff_id = d.admin_assistant', 'left');
        $this->db->join('staff fo', 'fo.staff_id = d.finance_officer', 'left');
        
        // Search functionality
        if (!empty($search_value)) {
            $this->db->group_start();
            $this->db->like('d.division_name', $search_value);
            $this->db->or_like('d.division_short_name', $search_value);
            $this->db->or_like('d.category', $search_value);
            $this->db->or_like('CONCAT(dh.fname, " ", dh.lname)', $search_value);
            $this->db->or_like('CONCAT(fp.fname, " ", fp.lname)', $search_value);
            $this->db->or_like('CONCAT(fa.fname, " ", fa.lname)', $search_value);
            $this->db->or_like('CONCAT(fo.fname, " ", fo.lname)', $search_value);
            $this->db->group_end();
        }
        
        // Ordering
        $this->db->order_by($order_by, $order_dir);
        
        // Pagination
        $this->db->limit($length, $start);
        
        $query = $this->db->get();
        $result = array();
        
        foreach ($query->result() as $row) {
            $result[] = array(
                $row->division_id,
                $row->division_name,
                !empty($row->division_short_name) ? $row->division_short_name : '-',
                !empty($row->category) ? $row->category : '-',
                !empty($row->head_fname) ? $row->head_fname . ' ' . $row->head_lname : 'N/A',
                !empty($row->focal_fname) ? $row->focal_fname . ' ' . $row->focal_lname : 'N/A',
                !empty($row->finance_fname) ? $row->finance_fname . ' ' . $row->finance_lname : 'N/A',
                !empty($row->admin_fname) ? $row->admin_fname . ' ' . $row->admin_lname : 'N/A',
                $this->get_action_buttons($row->division_id)
            );
        }
        
        return $result;
    }

    private function get_action_buttons($division_id) {
        $session = $this->session->userdata('user');
        $permissions = $session->permissions;
        
        $buttons = '<div class="btn-group" role="group">';
        $buttons .= '<button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#update_divisions' . $division_id . '" title="Edit Division">';
        $buttons .= '<i class="fa fa-edit"></i>';
        $buttons .= '</button>';
        
        if (in_array('77', $permissions)) {
            $buttons .= '<button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delete_divisions' . $division_id . '" title="Delete Division">';
            $buttons .= '<i class="fa fa-trash"></i>';
            $buttons .= '</button>';
        }
        
        $buttons .= '</div>';
        
        return $buttons;
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
            // Helper function to clean date fields
            $cleanDate = function($date) {
                return (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') ? null : $date;
            };
            
            $data = [
                'division_name'           => $this->input->post('division_name', true),
                'division_short_name'     => $this->input->post('division_short_name', true),
                'division_head'           => $this->input->post('division_head', true),
                'focal_person'            => $this->input->post('focal_person', true),
                'finance_officer'         => $this->input->post('finance_officer', true),
                'admin_assistant'         => $this->input->post('admin_assistant', true),
                'directorate_id'          => $this->input->post('directorate_id', true),
                'head_oic_id'             => $this->input->post('head_oic_id', true),
                'head_oic_start_date'     => $cleanDate($this->input->post('head_oic_start_date', true)),
                'head_oic_end_date'       => $cleanDate($this->input->post('head_oic_end_date', true)),
                'director_id'             => $this->input->post('director_id', true),
                'director_oic_id'         => $this->input->post('director_oic_id', true),
                'director_oic_start_date' => $cleanDate($this->input->post('director_oic_start_date', true)),
                'director_oic_end_date'   => $cleanDate($this->input->post('director_oic_end_date', true)),
                'category'                => $this->input->post('category', true),
            ];
    
            $result = $this->db->insert($table, $data);
    
            return $result;
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
            // Helper function to clean date fields
            $cleanDate = function($date) {
                return (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') ? null : $date;
            };
            
            $data = [
                'division_name'           => $this->input->post('division_name', true),
                'division_short_name'     => $this->input->post('division_short_name', true),
                'division_head'           => $this->input->post('division_head', true),
                'focal_person'            => $this->input->post('focal_person', true),
                'finance_officer'         => $this->input->post('finance_officer', true),
                'admin_assistant'         => $this->input->post('admin_assistant', true),
                'directorate_id'          => $this->input->post('directorate_id', true),
                'head_oic_id'             => $this->input->post('head_oic_id', true),
                'head_oic_start_date'     => $cleanDate($this->input->post('head_oic_start_date', true)),
                'head_oic_end_date'       => $cleanDate($this->input->post('head_oic_end_date', true)),
                'director_id'             => $this->input->post('director_id', true),
                'director_oic_id'         => $this->input->post('director_oic_id', true),
                'director_oic_start_date' => $cleanDate($this->input->post('director_oic_start_date', true)),
                'director_oic_end_date'   => $cleanDate($this->input->post('director_oic_end_date', true)),
                'category'                => $this->input->post('category', true),
            ];
    
            // Debug logging
            log_message('debug', 'Updating division with data: ' . json_encode($data));
            log_message('debug', 'Where clause: ' . $column_name . ' = ' . $caller_value);
    
            $this->db->where($column_name, $caller_value);
            $result = $this->db->update($table, $data);
    
            // Debug logging
            log_message('debug', 'Update result: ' . ($result ? 'true' : 'false'));
            if (!$result) {
                log_message('error', 'Database error: ' . $this->db->last_query());
                log_message('error', 'Database error message: ' . $this->db->_error_message());
            }
    
            return $result;
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

	/**
	 * Generate short code from division name
	 * @param string $name Division name
	 * @return string Generated short code
	 */
	public function generateShortCodeFromDivision($name) {
		$ignore = ['of', 'and', 'for', 'the', 'in', 'a', 'an'];
		$words = preg_split('/\s+/', strtolower(trim($name)));
		$initials = array_map(function ($word) use ($ignore) {
			// Skip empty words or ignored words
			if (empty($word) || in_array($word, $ignore)) {
				return '';
			}
			return strtoupper($word[0]);
		}, $words);
		return implode('', array_filter($initials));
	}

	/**
	 * Update all divisions with generated short names
	 * @return array Results of the update operation
	 */
	public function updateDivisionsWithShortNames() {
		// First, let's check if the column exists
		$columns = $this->db->list_fields('divisions');
		$hasShortNameColumn = in_array('division_short_name', $columns);
		
		if (!$hasShortNameColumn) {
			return array(
				'total_processed' => 0,
				'updated' => 0,
				'errors' => 1,
				'results' => array(array(
					'id' => 0,
					'name' => 'System',
					'short_name' => '',
					'status' => 'error',
					'error' => 'Column division_short_name does not exist in divisions table'
				))
			);
		}
		
		// Get all divisions that don't have short names
		$this->db->where('(division_short_name IS NULL OR division_short_name = "")');
		$divisions = $this->db->get('divisions')->result();
		
		$updated = 0;
		$errors = 0;
		$results = [];
		
		// Log the number of divisions found
		log_message('debug', 'Found ' . count($divisions) . ' divisions without short names');
		
		foreach ($divisions as $division) {
			try {
				$shortName = $this->generateShortCodeFromDivision($division->division_name);
				
				// Ensure short name is not empty
				if (empty($shortName)) {
					$shortName = 'DIV' . $division->division_id;
				}
				
				// Check if short name already exists (reset query builder first)
				$this->db->flush_cache();
				$this->db->where('division_short_name', $shortName);
				$this->db->where('division_id !=', $division->division_id);
				$existing = $this->db->get('divisions')->row();
				
				if ($existing) {
					// Add division ID to make it unique
					$shortName = $shortName . $division->division_id;
				}
				
				// Log the update attempt
				log_message('debug', 'Updating division ' . $division->division_id . ' (' . $division->division_name . ') with short name: ' . $shortName);
				
				// Update the division (reset query builder first)
				$this->db->flush_cache();
				$this->db->where('division_id', $division->division_id);
				$updateResult = $this->db->update('divisions', array('division_short_name' => $shortName));
				
				// Log the SQL query for debugging
				log_message('debug', 'Last SQL Query: ' . $this->db->last_query());
				
				if ($updateResult) {
					$updated++;
					$results[] = array(
						'id' => $division->division_id,
						'name' => $division->division_name,
						'short_name' => $shortName,
						'status' => 'success'
					);
					log_message('debug', 'Successfully updated division ' . $division->division_id);
				} else {
					$errors++;
					$dbError = $this->db->error();
					$results[] = array(
						'id' => $division->division_id,
						'name' => $division->division_name,
						'short_name' => '',
						'status' => 'error',
						'error' => 'Database update failed: ' . $dbError['message']
					);
					log_message('error', 'Failed to update division ' . $division->division_id . ': ' . $dbError['message']);
				}
			} catch (Exception $e) {
				$errors++;
				$results[] = array(
					'id' => $division->division_id,
					'name' => $division->division_name,
					'short_name' => '',
					'status' => 'error',
					'error' => $e->getMessage()
				);
				log_message('error', 'Exception updating division ' . $division->division_id . ': ' . $e->getMessage());
			}
		}
		
		return array(
			'total_processed' => count($divisions),
			'updated' => $updated,
			'errors' => $errors,
			'results' => $results
		);
	}

	/**
	 * Get divisions without short names
	 * @return array Divisions that need short names
	 */
	public function getDivisionsWithoutShortNames() {
		$this->db->where('(division_short_name IS NULL OR division_short_name = "")');
		$this->db->select('division_id, division_name, division_short_name');
		return $this->db->get('divisions')->result();
	}


}

 
    
  
