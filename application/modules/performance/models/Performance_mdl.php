<?php

class Performance_mdl extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
    }

	public function get_staff_plan($staff_id, $period = null)
	{
		$this->db->where('staff_id', $staff_id);
		if ($period) {
			$this->db->where('performance_period', $period);
		}
		return $this->db->get('ppa_entries')->row();
	}

	public function get_plan_by_entry_id($entry_id)
{
    $query = $this->db->get_where('ppa_entries', ['entry_id' => $entry_id]);
    $result = $query->row();

    if ($result) {
        // Decode JSON fields
        $result->objectives = json_decode($result->objectives);
        $result->required_skills = json_decode($result->required_skills);
    }

    return $result;
}
public function get_approval_trail($entry_id)
{
    if (!$entry_id) return [];

    return $this->db->get('ppa_approval_trail')->result();
}


	

}
