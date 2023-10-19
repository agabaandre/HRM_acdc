<?php

class Performance_mdl extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
        $this->load->model("Employee");
        $this->load->model("Contracts");
    }

	public function myplans($limit, $start, $id, $period, $status)
	{
		if ($id) {
			$this->db->where("staff_id", "$id");
		}
		if ($period) {
			$this->db->where("period", "$period");
		}
		if ($status) {
			$this->db->where("status", "$status");
		}
		if ($limit) {
			$this->db->limit($limit, $start);
		}

		$this->db->join('staff', 'ppa_primary.staff_id=staff.staff_id');
		$query = $this->db->get('ppa_primary');
		
		return $query->result_array();
	}

}
