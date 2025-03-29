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
    // genrate cuurent employees trails.
    $this->db->where("entry_id","$entry_id");
    return $this->db->get('ppa_approval_trail')->result();
}

public function get_pending_ppa($staff_id)
{
    $sql = "
        SELECT p.entry_id, p.performance_period, p.staff_id,
               CONCAT(s.firstname, ' ', s.lastname) AS staff_name,
               a.action,
               CASE
                   WHEN a.action = 'Submitted' THEN
                       CASE
                           WHEN p.supervisor_id = ? AND p.supervisor2_id IS NULL THEN 'Pending Supervisor'
                           WHEN p.supervisor_id = ? THEN 'Pending First Supervisor'
                           WHEN p.supervisor2_id = ? THEN 'Pending Second Supervisor'
                       END
                   WHEN a.action = 'Approved' THEN 'Approved'
                   WHEN a.action = 'Returned' THEN 'Returned'
                   ELSE 'Unknown'
               END AS status
        FROM ppa_entries p
        JOIN staff s ON s.staff_id = p.staff_id
        LEFT JOIN ppa_approval_trail a ON a.id = (
            SELECT MAX(id) FROM ppa_approval_trail
            WHERE entry_id = p.entry_id
        )
        WHERE p.draft_status = 0
        AND (
            p.supervisor_id = ? OR p.supervisor2_id = ?
        )
    ";

    return $this->db->query($sql, [$staff_id, $staff_id, $staff_id, $staff_id, $staff_id])->result_array();
}



public function get_my_ppa($staff_id)
{
    $this->db->where('staff_id', $staff_id);
    $this->db->order_by('updated_at', 'DESC');
    return $this->db->get('ppa_entries')->result_array();
}


public function get_approved_ppas($staff_id, $role)
{
    if ($role === 'admin') {
        $this->db->where('status', 'Approved');
    } else {
        $this->db->where('ppa_entries.staff_id', $staff_id);
        $this->db->where('status', 'Approved');
    }
    $this->db->join('staff', 'staff.staff_id = ppa_entries.staff_id');
    $this->db->select('ppa_entries.*, CONCAT(staff.firstname, " ", staff.lastname) as staff_name');
    return $this->db->get('ppa_entries')->result_array();
}

public function get_all_ppas_for_user($staff_id)
{
    $sql = "
        SELECT p.*, 
               (
                   SELECT action 
                   FROM ppa_approval_trail 
                   WHERE entry_id = p.entry_id 
                   ORDER BY id DESC LIMIT 1
               ) as overall_status
        FROM ppa_entries p
        WHERE p.staff_id = ?
        ORDER BY p.created_at DESC
    ";
    return $this->db->query($sql, [$staff_id])->result_array();
}
	

}
