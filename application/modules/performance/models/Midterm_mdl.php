<?php

class Midterm_mdl extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }



    public function get_plan_by_entry_id($entry_id)
    {
        $query = $this->db->get_where('ppa_entries', ['entry_id' => $entry_id]);
        $result = $query->row();

        if ($result) {
            $result->objectives = $result->objectives ? json_decode($result->objectives, true) : [];
            $result->required_skills = $result->required_skills ? json_decode($result->required_skills, true) : [];

            $result->midterm_objectives = $result->midterm_objectives ? json_decode($result->midterm_objectives, true) : [];
            $result->midterm_competency = $result->midterm_competency ? json_decode($result->midterm_competency, true) : [];
            $result->midterm_recommended_skills = $result->midterm_recommended_skills ? json_decode($result->midterm_recommended_skills, true) : [];
        }

        return $result;
    }

    public function save_midterm_review($entry_id, $data)
    {
        if (empty($entry_id) || empty($data)) return false;

        $this->db->where('entry_id', $entry_id);
        return $this->db->update('ppa_entries', $data);
    }

    public function get_midterm_entries($filters = [], $limit = 40, $offset = 0)
    {
        $this->db->select('pe.*, CONCAT(s.fname, " ", s.lname) AS staff_name');
        $this->db->from('ppa_entries pe');
        $this->db->join('staff s', 's.staff_id = pe.staff_id');

        if (!empty($filters['performance_period'])) {
            $this->db->where('pe.performance_period', $filters['performance_period']);
        }

        if (!empty($filters['midterm_draft_status']) || $filters['midterm_draft_status'] === "0") {
            $this->db->where('pe.midterm_draft_status', $filters['midterm_draft_status']);
        }

        if (!empty($filters['staff_id'])) {
            $this->db->where('pe.staff_id', $filters['staff_id']);
        }

        $this->db->order_by('pe.midterm_updated_at', 'DESC');

        if ($limit > 0) {
            $this->db->limit($limit, $offset);
        }

        return $this->db->get()->result_array();
    }

    public function get_midterm_status($entry_id)
    {
        $entry = $this->db->get_where('ppa_entries', ['entry_id' => $entry_id])->row();
        if (!$entry) return 'Not Started';

        if ((int) $entry->midterm_draft_status === 0 && $entry->midterm_sign_off == 1) {
            return 'Submitted';
        } elseif ((int) $entry->midterm_draft_status === 1) {
            return 'Draft';
        } else {
            return 'Not Started';
        }
    }

    public function is_midterm_editable($entry_id, $staff_id)
    {
        $entry = $this->db->get_where('ppa_entries', ['entry_id' => $entry_id])->row();

        if (!$entry) return false;

        $status = (int) $entry->midterm_draft_status;
        $isOwner = $entry->staff_id == $staff_id;

        return ($status === 1 && $isOwner);
    }
    public function get_staff_plan_id($entry_id)
	{
		
		if ($entry_id) {
			$this->db->where('entry_id', $entry_id);
            return $this->db->get('ppa_entries')->row();
		}
        else{
            return FALSE;
        }
		
	}

    
public function get_approval_trail($entry_id)
{
    if (!$entry_id) return [];
    // genrate cuurent employees trails.
    $this->db->where("entry_id","$entry_id");
    return $this->db->get('ppa_approval_trail_midterm')->result();

}

public function get_pending_ppa($staff_id)
{
    $sql = "
        SELECT 
            p.*, 
            CONCAT(s.fname, ' ', s.lname) AS staff_name,

            -- Midterm Supervisor 1 last action
            (
                SELECT a1.action 
                FROM ppa_approval_trail_midterm a1
                WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.midterm_supervisor_1
                ORDER BY a1.id DESC LIMIT 1
            ) AS supervisor1_action,

            -- Midterm Supervisor 2 last action
            (
                SELECT a2.action 
                FROM ppa_approval_trail_midterm a2
                WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.midterm_supervisor_2
                ORDER BY a2.id DESC LIMIT 1
            ) AS supervisor2_action,

            -- Compute overall status
            CASE 
                WHEN p.midterm_draft_status = 1 THEN 'Pending (Draft)'
                WHEN p.midterm_supervisor_2 IS NULL AND
                    (
                        SELECT a1.action 
                        FROM ppa_approval_trail_midterm a1 
                        WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.midterm_supervisor_1 
                        ORDER BY a1.id DESC LIMIT 1
                    ) = 'Approved'
                THEN 'Approved'

                WHEN p.midterm_supervisor_2 IS NOT NULL AND
                    (
                        SELECT a1.action 
                        FROM ppa_approval_trail_midterm a1 
                        WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.midterm_supervisor_1 
                        ORDER BY a1.id DESC LIMIT 1
                    ) = 'Approved' AND
                    (
                        SELECT a2.action 
                        FROM ppa_approval_trail_midterm a2 
                        WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.midterm_supervisor_2 
                        ORDER BY a2.id DESC LIMIT 1
                    ) = 'Approved'
                THEN 'Approved'

                WHEN (
                    SELECT a2.action 
                    FROM ppa_approval_trail_midterm a2 
                    WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.midterm_supervisor_2 
                    ORDER BY a2.id DESC LIMIT 1
                ) = 'Returned'
                THEN 'Returned'

                WHEN (
                    SELECT a1.action 
                    FROM ppa_approval_trail_midterm a1 
                    WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.midterm_supervisor_1 
                    ORDER BY a1.id DESC LIMIT 1
                ) = 'Returned'
                THEN 'Returned'

                WHEN p.midterm_supervisor_2 IS NOT NULL AND
                    (
                        SELECT a1.action 
                        FROM ppa_approval_trail_midterm a1 
                        WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.midterm_supervisor_1 
                        ORDER BY a1.id DESC LIMIT 1
                    ) = 'Approved'
                THEN 'Pending Second Supervisor'

                ELSE 'Pending First Supervisor'
            END AS overall_status

        FROM ppa_entries p
        JOIN staff s ON s.staff_id = p.staff_id
        WHERE p.midterm_draft_status = 0
        AND (
            -- First midterm supervisor
            (
                p.midterm_supervisor_1 = ? AND (
                    (
                        SELECT a1.action
                        FROM ppa_approval_trail_midterm a1
                        WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.midterm_supervisor_1
                        ORDER BY a1.id DESC LIMIT 1
                    ) IS NULL OR
                    (
                        SELECT a1.action
                        FROM ppa_approval_trail_midterm a1
                        WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.midterm_supervisor_1
                        ORDER BY a1.id DESC LIMIT 1
                    ) != 'Approved'
                )
            )
            OR
            -- Second midterm supervisor (after first has approved)
            (
                p.midterm_supervisor_2 = ? AND (
                    (
                        SELECT a1.action
                        FROM ppa_approval_trail_midterm a1
                        WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.midterm_supervisor_1
                        ORDER BY a1.id DESC LIMIT 1
                    ) = 'Approved'
                    AND (
                        (
                            SELECT a2.action
                            FROM ppa_approval_trail_midterm a2
                            WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.midterm_supervisor_2
                            ORDER BY a2.id DESC LIMIT 1
                        ) IS NULL OR
                        (
                            SELECT a2.action
                            FROM ppa_approval_trail_midterm a2
                            WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.midterm_supervisor_2
                            ORDER BY a2.id DESC LIMIT 1
                        ) != 'Approved'
                    )
                )
            )
        )
        ORDER BY p.midterm_created_at DESC
    ";

    return $this->db->query($sql, [$staff_id, $staff_id])->result_array();
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

public function get_all_approved_ppas_for_user($staff_id)
{
    $sql = "
    SELECT 
        p.*, 
        CONCAT(s.fname, ' ', s.lname) AS staff_name,
        'Approved' AS overall_status,
        
        -- Last action by Midterm Supervisor 1
        (
            SELECT a1.action 
            FROM ppa_approval_trail_midterm a1
            WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.midterm_supervisor_1
            ORDER BY a1.id DESC LIMIT 1
        ) AS supervisor1_action,
        
        -- Last action by Midterm Supervisor 2
        (
            SELECT a2.action 
            FROM ppa_approval_trail_midterm a2
            WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.midterm_supervisor_2
            ORDER BY a2.id DESC LIMIT 1
        ) AS supervisor2_action

    FROM ppa_entries p
    JOIN staff s ON s.staff_id = p.staff_id
    WHERE p.staff_id = ?

    AND (
        -- Case 1: Only one midterm supervisor and they approved
        (p.midterm_supervisor_2 IS NULL AND
         (
            SELECT a1.action 
            FROM ppa_approval_trail_midterm a1
            WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.midterm_supervisor_1
            ORDER BY a1.id DESC LIMIT 1
         ) = 'Approved')

        -- Case 2: Both midterm supervisors and both approved
        OR (
            p.midterm_supervisor_2 IS NOT NULL AND
            (
                SELECT a1.action 
                FROM ppa_approval_trail_midterm a1
                WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.midterm_supervisor_1
                ORDER BY a1.id DESC LIMIT 1
            ) = 'Approved' AND
            (
                SELECT a2.action 
                FROM ppa_approval_trail_midterm a2
                WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.midterm_supervisor_2
                ORDER BY a2.id DESC LIMIT 1
            ) = 'Approved'
        )
    )

    ORDER BY p.midterm_created_at DESC
    ";

    return $this->db->query($sql, [$staff_id])->result_array();
}

public function get_recent_ppas_for_user($staff_id, $period)
{
    $sql = "
        SELECT 
            p.*, 
            CONCAT(s.fname, ' ', s.lname) AS staff_name,

            -- Last action by Midterm Supervisor 1
            (
                SELECT a1.action 
                FROM ppa_approval_trail_midterm a1
                WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.midterm_supervisor_1
                ORDER BY a1.id DESC LIMIT 1
            ) AS supervisor1_action,
            
            -- Last action by Midterm Supervisor 2
            (
                SELECT a2.action 
                FROM ppa_approval_trail_midterm a2
                WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.midterm_supervisor_2
                ORDER BY a2.id DESC LIMIT 1
            ) AS supervisor2_action,
            
            -- Final status decision with draft consideration
            CASE 
                WHEN p.midterm_draft_status = 1 THEN 'Pending (Draft)'
                ELSE (
                    CASE
                        WHEN p.midterm_supervisor_2 IS NULL AND
                            (
                                SELECT a1.action 
                                FROM ppa_approval_trail_midterm a1
                                WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.midterm_supervisor_1
                                ORDER BY a1.id DESC LIMIT 1
                            ) = 'Approved'
                        THEN 'Approved'

                        WHEN p.midterm_supervisor_2 IS NOT NULL AND
                            (
                                SELECT a1.action 
                                FROM ppa_approval_trail_midterm a1
                                WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.midterm_supervisor_1
                                ORDER BY a1.id DESC LIMIT 1
                            ) = 'Approved' AND
                            (
                                SELECT a2.action 
                                FROM ppa_approval_trail_midterm a2
                                WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.midterm_supervisor_2
                                ORDER BY a2.id DESC LIMIT 1
                            ) = 'Approved'
                        THEN 'Approved'

                        WHEN (
                                SELECT a2.action 
                                FROM ppa_approval_trail_midterm a2
                                WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.midterm_supervisor_2
                                ORDER BY a2.id DESC LIMIT 1
                            ) = 'Returned'
                        THEN 'Returned'

                        WHEN (
                                SELECT a1.action 
                                FROM ppa_approval_trail_midterm a1
                                WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.midterm_supervisor_1
                                ORDER BY a1.id DESC LIMIT 1
                            ) = 'Returned'
                        THEN 'Returned'

                        WHEN p.midterm_supervisor_2 IS NOT NULL AND
                            (
                                SELECT a1.action 
                                FROM ppa_approval_trail_midterm a1
                                WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.midterm_supervisor_1
                                ORDER BY a1.id DESC LIMIT 1
                            ) = 'Approved'
                        THEN 'Pending Second Supervisor'

                        ELSE 'Pending First Supervisor'
                    END
                )
            END AS overall_status

        FROM ppa_entries p
        JOIN staff s ON s.staff_id = p.staff_id
        WHERE p.staff_id = ? AND p.performance_period = ?
        ORDER BY p.midterm_created_at DESC
    ";

    return $this->db->query($sql, [$staff_id, $period])->result_array();
}


public function get_approved_by_supervisor($supervisor_id)
{
    $sql = "
        SELECT 
            p.entry_id,
            p.staff_id,
            p.performance_period,
            p.midterm_created_at AS created_at,
            CONCAT(st.fname, ' ', st.lname) AS staff_name,
            a.created_at AS approval_date,
            a.comments

        FROM ppa_entries p
        JOIN staff st ON st.staff_id = p.staff_id
        JOIN ppa_approval_trail_midterm a ON a.id = (
            SELECT MAX(id) 
            FROM ppa_approval_trail_midterm 
            WHERE entry_id = p.entry_id
            AND action = 'Approved'
        )
        WHERE 
            (p.midterm_supervisor_1 = ? OR p.midterm_supervisor_2 = ?)
        ORDER BY a.created_at DESC
    ";

    return $this->db->query($sql, [$supervisor_id, $supervisor_id])->result_array();
}

public function get_staff_by_type($type, $division_id = null, $period = null)
{
    // Get latest contract for each staff
    $subquery = $this->db->select('MAX(staff_contract_id)', false)
        ->from('staff_contracts')
        ->group_by('staff_id')
        ->get_compiled_select();

    switch ($type) {
        case 'total':
        case 'approved':
        case 'with_pdp':
            // For completed reviews: show all who completed regardless of contract status
            // First, get staff who completed midterms
            if ($type === 'total') {
                // Staff who have submitted midterm reviews (not drafts)
                $this->db->select('pe.staff_id, pe.entry_id');
                $this->db->from('ppa_entries pe');
                if ($period) $this->db->where('pe.performance_period', $period);
                $this->db->where('pe.draft_status !=', 1); // PPA submitted
                $this->db->where('pe.midterm_draft_status !=', 1); // Submitted
            } elseif ($type === 'approved') {
                // Staff whose midterm reviews have been approved
                // Use WHERE EXISTS with subquery to handle collation properly
                $this->db->select('pe.staff_id, pe.entry_id');
                $this->db->from('ppa_entries pe');
                $this->db->where('pe.draft_status !=', 1);
                $this->db->where('pe.midterm_draft_status !=', 1);
                if ($period) $this->db->where('pe.performance_period', $period);
                // Use raw WHERE clause for approval check
                $this->db->where("EXISTS (
                    SELECT 1 FROM ppa_approval_trail_midterm pat
                    WHERE pat.entry_id = pe.entry_id
                    AND pat.action = 'Approved'
                    AND pat.id = (
                        SELECT MAX(id) 
                        FROM ppa_approval_trail_midterm 
                        WHERE entry_id = pe.entry_id
                    )
                )", null, false);
            } elseif ($type === 'with_pdp') {
                // Staff who have training recommendations in their midterm review
                $this->db->select('pe.staff_id, pe.entry_id, pe.midterm_recommended_skills');
                $this->db->from('ppa_entries pe');
                $this->db->where('pe.midterm_draft_status !=', 1); // Submitted
                $this->db->where('pe.draft_status !=', 1); // PPA submitted
                if ($period) $this->db->where('pe.performance_period', $period);
            }
            
            $completed_entries = $this->db->get()->result();
            if (empty($completed_entries)) return [];
            
            $completed_staff_ids = array_column($completed_entries, 'staff_id');
            $entry_map = [];
            foreach ($completed_entries as $row) {
                $entry_map[$row->staff_id] = $row->entry_id;
            }

            // Now get staff details with contract info (no contract status filter)
            $this->db->select('
                s.staff_id, s.fname, s.lname, s.oname, s.work_email, s.SAPNO,
                d.division_name, ct.contract_type, st.status
            ');
            $this->db->from('staff s');
            $this->db->join('staff_contracts sc', 'sc.staff_id = s.staff_id', 'left');
            $this->db->join('divisions d', 'd.division_id = sc.division_id', 'left');
            $this->db->join('contract_types ct', 'ct.contract_type_id = sc.contract_type_id', 'left');
            $this->db->join('status st', 'st.status_id = sc.status_id', 'left');
            $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
            $this->db->where_in('s.staff_id', $completed_staff_ids);
            // Apply division filter if provided
            if ($division_id) {
                $this->db->where('sc.division_id', $division_id);
            }
            
            $staff_list = $this->db->get()->result();
            
            // For with_pdp, we need to add skills data
            if ($type === 'with_pdp') {
                // Map skills
                $this->db->select('id, skill');
                $skills_map = [];
                foreach ($this->db->get('training_skills')->result() as $s) {
                    $skills_map[$s->id] = $s->skill;
                }
                
                // Get skills for each entry
                $pdp_data = [];
                foreach ($completed_entries as $entry) {
                    if (isset($entry->midterm_recommended_skills)) {
                        $skill_ids = json_decode($entry->midterm_recommended_skills ?? '[]', true);
                        if (!empty($skill_ids)) {
                            $skill_names = array_map(fn($id) => $skills_map[$id] ?? '', $skill_ids);
                            $pdp_data[$entry->staff_id] = array_filter($skill_names);
                        }
                    }
                }
                
                // Add skills to staff list
                return array_map(function ($staff) use ($entry_map, $pdp_data) {
                    if (isset($entry_map[$staff->staff_id])) {
                        $staff->entry_id = $entry_map[$staff->staff_id];
                        if (isset($pdp_data[$staff->staff_id])) {
                            $staff->training_skills = $pdp_data[$staff->staff_id];
                        }
                    }
                    return $staff;
                }, $staff_list);
            }
            
            // Add entry_id to each staff member
            return array_map(function ($staff) use ($entry_map) {
                if (isset($entry_map[$staff->staff_id])) {
                    $staff->entry_id = $entry_map[$staff->staff_id];
                }
                return $staff;
            }, $staff_list);

        case 'without_ppa':
            // For without_ppa: contract status filtering applies (include Under Renewal = 7)
            // Base staff query with contract status filter
            $this->db->select('
                s.staff_id, s.fname, s.lname, s.oname, s.work_email, s.SAPNO,
                d.division_name, ct.contract_type, st.status
            ');
            $this->db->from('staff s');
            $this->db->join('staff_contracts sc', 'sc.staff_id = s.staff_id', 'left');
            $this->db->join('divisions d', 'd.division_id = sc.division_id', 'left');
            $this->db->join('contract_types ct', 'ct.contract_type_id = sc.contract_type_id', 'left');
            $this->db->join('status st', 'st.status_id = sc.status_id', 'left');
            $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
            $this->db->where_in('sc.status_id', [1, 2, 7]); // Active, Due, and Under Renewal
            $this->db->where_not_in('sc.contract_type_id', [1, 5, 3, 7]); // Exclude Regular, Fixed, AUYVC, ALD
            if ($division_id) {
                $this->db->where('sc.division_id', $division_id);
            }

            $active_staff = $this->db->get()->result();
            $staff_ids = array_column($active_staff, 'staff_id');

            if (empty($staff_ids)) return [];

            // Staff who have submitted PPAs but haven't submitted midterm reviews
            $this->db->select('pe.staff_id');
            $this->db->from('ppa_entries pe');
            if ($period) $this->db->where('pe.performance_period', $period);
            $this->db->where('pe.draft_status !=', 1); // PPA submitted
            $this->db->where_in('pe.staff_id', $staff_ids);
            $ppa_submitted = $this->db->get()->result();
            $ppa_submitted_ids = array_column($ppa_submitted, 'staff_id');

            // Get staff who have submitted midterms
            $this->db->select('pe.staff_id');
            $this->db->from('ppa_entries pe');
            if ($period) $this->db->where('pe.performance_period', $period);
            $this->db->where('pe.draft_status !=', 1); // PPA submitted
            $this->db->where('pe.midterm_draft_status !=', 1); // Submitted
            $this->db->where_in('pe.staff_id', $staff_ids);
            $midterm_submitted = $this->db->get()->result();
            $midterm_submitted_ids = array_column($midterm_submitted, 'staff_id');

            // Return staff who have PPAs but no midterms
            $without_midterm_ids = array_diff($ppa_submitted_ids, $midterm_submitted_ids);
            return array_filter($active_staff, fn($s) => in_array($s->staff_id, $without_midterm_ids));

        default:
            return [];
    }
}


public function get_midterm_dashboard_data()
{
    $division_id = $this->input->get('division_id');
    $period = $this->input->get('period');
    $current_period = str_replace(' ', '-', current_period());
    $period = !empty($period) ? $period : $current_period;

    $user = $this->session->userdata('user');
    $is_restricted = ($user && isset($user->role) && $user->role == 17);
    $staff_id = $is_restricted ? $user->staff_id : null;

    // Get latest contract for each staff
    $subquery = $this->db->select('MAX(staff_contract_id)', false)
        ->from('staff_contracts')
        ->group_by('staff_id')
        ->get_compiled_select();

    // For completed reviews: count all regardless of contract status, but apply division filters
    // Summary counts for midterm (regardless of contract status)
    $this->db->select("
        COUNT(pe.entry_id) AS total,
        SUM(CASE WHEN latest.action = 'Approved' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN latest.action = 'Submitted' THEN 1 ELSE 0 END) AS submitted", false);
    $this->db->from('ppa_entries pe');
    $this->db->join("(SELECT pat1.* FROM ppa_approval_trail_midterm pat1
        INNER JOIN (SELECT entry_id, MAX(id) AS max_id FROM ppa_approval_trail_midterm GROUP BY entry_id) latest
        ON pat1.id = latest.max_id) latest", 'latest.entry_id = pe.entry_id', 'left');
    // Join with contracts to apply division filter (but no contract status filter)
    $this->db->join('staff_contracts sc', 'sc.staff_id = pe.staff_id', 'left');
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
    $this->db->where('pe.draft_status !=', 1);
    $this->db->where('pe.performance_period', $period);
    $this->db->where('pe.midterm_draft_status !=', 1);
    $summary = $this->db->get()->row();

    // Get active staff for "without_ppa" calculation (with contract status filter including Under Renewal)
    $this->db->select('s.staff_id');
    $this->db->from('staff s');
    $this->db->join('staff_contracts sc', 'sc.staff_id = s.staff_id', 'left');
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    $this->db->where_in('sc.status_id', [1, 2, 7]); // Active, Due, and Under Renewal
    $this->db->where_not_in('sc.contract_type_id', [1, 5, 3, 7]); // Exclude Regular, Fixed, AUYVC, ALD
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($is_restricted) $this->db->where('s.staff_id', $staff_id);
    $staff_ids = array_column($this->db->get()->result(), 'staff_id');

    // Submission trend for midterm (regardless of contract status)
    $this->db->select("DATE(pe.midterm_updated_at) AS date, COUNT(pe.entry_id) AS count");
    $this->db->from("ppa_entries pe");
    $this->db->join('staff_contracts sc', 'sc.staff_id = pe.staff_id', 'left');
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
    $this->db->where('pe.draft_status !=', 1);
    $this->db->where('pe.midterm_draft_status !=', 1);
    $this->db->where("pe.performance_period", $period);
    $this->db->group_by("DATE(pe.midterm_updated_at)");
    $this->db->order_by("DATE(pe.midterm_updated_at)", "ASC");
    $trend = array_map(function ($r) {
        return ['date' => $r->date, 'count' => (int)$r->count];
    }, $this->db->get()->result());

    // Average approval time for midterm (regardless of contract status)
    $this->db->select("pe.midterm_updated_at AS submitted_date, latest.created_at AS approved_date");
    $this->db->from("ppa_entries pe");
    $this->db->join("(SELECT pat1.* FROM ppa_approval_trail_midterm pat1
        INNER JOIN (SELECT entry_id, MAX(id) AS max_id FROM ppa_approval_trail_midterm WHERE action = 'Approved' GROUP BY entry_id) latest
        ON pat1.id = latest.max_id) latest", "latest.entry_id = pe.entry_id", "left");
    $this->db->join('staff_contracts sc', 'sc.staff_id = pe.staff_id', 'left');
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
    $this->db->where("pe.draft_status !=", 1);
    $this->db->where("pe.midterm_draft_status !=", 1);
    $this->db->where("pe.performance_period", $period);
    $approvals = $this->db->get()->result();

    $total_days = 0;
    $count = 0;
    foreach ($approvals as $a) {
        if ($a->submitted_date && $a->approved_date) {
            $days = (strtotime($a->approved_date) - strtotime($a->submitted_date)) / 86400;
            $total_days += $days;
            $count++;
        }
    }
    $avg_days = $count ? round($total_days / $count, 1) : 0;

    // Division-wise midterm count (regardless of contract status)
    $this->db->select("d.division_name, COUNT(pe.entry_id) AS count");
    $this->db->from("ppa_entries pe");
    $this->db->join("staff_contracts sc", "sc.staff_id = pe.staff_id", "left");
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    $this->db->join("divisions d", "d.division_id = sc.division_id", "left");
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
    $this->db->where("pe.draft_status !=", 1);
    $this->db->where("pe.midterm_draft_status !=", 1);
    $this->db->where("pe.performance_period", $period);
    $this->db->group_by("sc.division_id");
    $divisions = array_map(fn($r) => ['name' => $r->division_name, 'y' => (int)$r->count], $this->db->get()->result());

    // Contract types (based on latest contract only) for midterm (regardless of contract status)
    $this->db->select("ct.contract_type, COUNT(pe.entry_id) AS total");
    $this->db->from("ppa_entries pe");
    $this->db->join("staff_contracts sc", "sc.staff_id = pe.staff_id", "left");
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    $this->db->join("contract_types ct", "ct.contract_type_id = sc.contract_type_id", "left");
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
    $this->db->where("pe.draft_status !=", 1);
    $this->db->where("pe.midterm_draft_status !=", 1);
    $this->db->where("pe.performance_period", $period);
    $this->db->group_by("ct.contract_type_id");
    $by_contract = array_map(fn($r) => ['name' => $r->contract_type, 'y' => (int)$r->total], $this->db->get()->result());

    // Staff with midterm (regardless of contract status)
    $this->db->select("pe.staff_id")->from("ppa_entries pe");
    $this->db->join('staff_contracts sc', 'sc.staff_id = pe.staff_id', 'left');
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
    $this->db->where("pe.performance_period", $period);
    $this->db->where("pe.draft_status !=", 1);
    $this->db->where("pe.midterm_draft_status !=", 1);
    $midterm_staff = array_column($this->db->get()->result(), 'staff_id');

    // Staff with PDP at midterm (actual skills recommended) - regardless of contract status
    $this->db->select("pe.staff_id, pe.midterm_recommended_skills")->from("ppa_entries pe");
    $this->db->join('staff_contracts sc', 'sc.staff_id = pe.staff_id', 'left');
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
    $this->db->where("pe.draft_status !=", 1);
    $this->db->where("pe.midterm_draft_status !=", 1);
    $this->db->where("pe.performance_period", $period);
    $pdp_entries = $this->db->get()->result();

    $pdp_staff = [];
    foreach ($pdp_entries as $entry) {
        $skills = json_decode($entry->midterm_recommended_skills ?? '[]', true);
        if (!empty($skills)) {
            $pdp_staff[] = $entry->staff_id;
        }
    }
    $pdp_staff = array_unique($pdp_staff);

    // Calculate staff without midterms (active staff with PPAs but without midterms)
    $this->db->select("pe.staff_id");
    $this->db->from("ppa_entries pe");
    $this->db->where_in("pe.staff_id", $staff_ids);
    $this->db->where("pe.performance_period", $period);
    $this->db->where("pe.draft_status !=", 1);
    $this->db->where("pe.midterm_draft_status !=", 1);
    $ppa_with_midterm = array_column($this->db->get()->result(), 'staff_id');
    
    // Periods list (midterm) - only get distinct periods from actual midterm entries
    $this->db->distinct();
    $this->db->select("pe.performance_period");
    $this->db->from("ppa_entries pe");
    $this->db->where("pe.midterm_draft_status !=", 1); // Only non-draft midterms
    $this->db->where("pe.draft_status !=", 1); // Only non-draft PPAs
    $this->db->where("pe.midterm_updated_at IS NOT NULL", null, false); // Only periods with actual midterm data
    if ($is_restricted) $this->db->where("pe.staff_id", $staff_id);
    $this->db->order_by("pe.performance_period", "DESC");
    $periods_result = $this->db->get()->result();
    $periods = array_column($periods_result, 'performance_period');
    $periods = array_unique($periods); // Ensure distinct values
    $current_period = !empty($periods) ? $periods[0] : $period;

    // Age groups for midterm (regardless of contract status)
    $age_groups = [
        'Under 30' => [null, 29],
        '30 - 39' => [30, 39],
        '40 - 49' => [40, 49],
        '50 - 59' => [50, 59],
        '60+' => [60, null],
    ];
    $age_data = [];
    foreach ($age_groups as $label => [$min, $max]) {
        $this->db->from("ppa_entries pe");
        $this->db->join("staff s", "s.staff_id = pe.staff_id", "left");
        $this->db->join('staff_contracts sc', 'sc.staff_id = pe.staff_id', 'left');
        $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
        if ($division_id) $this->db->where('sc.division_id', $division_id);
        if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
        $this->db->where("pe.performance_period", $period);
        $this->db->where("pe.draft_status !=", 1);
        $this->db->where("pe.midterm_draft_status !=", 1);
        if ($min !== null) $this->db->where("TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) >=", $min);
        if ($max !== null) $this->db->where("TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) <=", $max);
        $count = $this->db->count_all_results();
        $age_data[] = ['group' => $label, 'count' => $count];
    }

    // Training categories for midterm
    $this->db->select("tc.category_name AS name, COUNT(*) AS y", false);
    $this->db->from("ppa_entries pe");
    $this->db->join("training_skills ts", "JSON_CONTAINS(pe.required_skills, JSON_QUOTE(CAST(ts.id AS CHAR)), '$')", "inner", false);
    $this->db->join("training_categories tc", "tc.id = ts.category_id", "left");
    $this->db->where_in("pe.staff_id", $staff_ids);
    $this->db->where("pe.draft_status !=", 1);
    $this->db->where("pe.midterm_draft_status !=", 1);
    $this->db->where("pe.performance_period", $period);
    $this->db->group_by("ts.category_id");
    $training_categories = $this->db->get()->result();

    // Top 10 training skills for midterm
    $this->db->select("ts.skill AS name, COUNT(*) AS y", false);
    $this->db->from("ppa_entries pe");
    $this->db->join("training_skills ts", "JSON_CONTAINS(pe.required_skills, JSON_QUOTE(CAST(ts.id AS CHAR)), '$')", "inner", false);
    $this->db->where_in("pe.staff_id", $staff_ids);
    $this->db->where("pe.draft_status !=", 1);
    $this->db->where("pe.midterm_draft_status !=", 1);
    $this->db->where("pe.performance_period", $period);
    $this->db->group_by("ts.id");
    $this->db->order_by("y DESC");
    $this->db->limit(10);
    $training_skills = $this->db->get()->result();

    return [
        'total' => (int)$summary->total,
        'approved' => (int)$summary->approved,
        'submitted' => (int)$summary->submitted,
        'trend' => $trend,
        'avg_approval_days' => $avg_days,
        'by_division' => $divisions,
        'by_contract' => $by_contract,
        'by_age' => $age_data,
        'training_categories' => $training_categories,
        'training_skills' => $training_skills,
        'staff_count' => count($staff_ids),
        'staff_without_midterms' => count($this->get_staff_without_midterm($period, $division_id)),
        'staff_with_pdps' => count($pdp_staff),
        'periods' => $periods,
        'current_period' => $current_period,
    ];
}





public function get_supervisors_with_pending_midterms($period)
{
    $sql = "
        SELECT 
            DISTINCT s.staff_id AS supervisor_id,
            s.title,
            s.fname,
            s.lname,
            s.work_email
        FROM staff s
        JOIN ppa_entries p 
            ON s.staff_id = p.midterm_supervisor_1 OR s.staff_id = p.midterm_supervisor_2
        WHERE p.performance_period = ?
        AND p.midterm_draft_status = 0
        AND p.midterm_sign_off = 1
        AND p.entry_id NOT IN (
            SELECT entry_id FROM ppa_approval_trail_midterm WHERE action = 'Approved'
        )
        ORDER BY s.fname ASC
    ";

    return $this->db->query($sql, [$period])->result();
}

public function get_pending_by_supervisor_with_staff($supervisor_id)
{
    // Subquery: entries that have been approved in the midterm approval trail
    $subquery = $this->db->select('entry_id')
        ->from('ppa_approval_trail_midterm')
        ->where('action', 'Approved')
        ->get_compiled_select();

    $this->db->select("
        p.entry_id,
        p.staff_id,
        CONCAT(s.title, ' ', s.fname, ' ', s.lname) AS staff_name,
        p.performance_period,
        p.midterm_updated_at AS created_at
    ");
    $this->db->from('ppa_entries p');
    $this->db->join('staff s', 's.staff_id = p.staff_id', 'left');
    $this->db->group_start()
             ->where('p.midterm_supervisor_1', $supervisor_id)
             ->or_where('p.midterm_supervisor_2', $supervisor_id)
             ->group_end();
    $this->db->where('p.midterm_draft_status', 0);
    $this->db->where('p.midterm_sign_off', 1);
    $this->db->where("p.entry_id NOT IN ($subquery)", null, false);
    $this->db->order_by("p.midterm_updated_at", "DESC");

    return $this->db->get()->result();
}

public function get_all_ppas_filtered($filters, $limit = 40, $offset = 0)
{
    // Default to current performance period
    if (empty($filters['period'])) {
        $filters['period'] = str_replace(' ', '-', current_period());
    }

    $sql = "
        SELECT 
            p.*, 
            d.division_name,
            CONCAT(s.fname, ' ', s.lname) AS staff_name,

            CASE 
                WHEN p.midterm_draft_status = 1 THEN 'Draft'
                ELSE (
                    CASE
                        WHEN p.midterm_supervisor_2 IS NULL AND (
                            SELECT action FROM ppa_approval_trail_midterm 
                            WHERE entry_id = p.entry_id AND staff_id = p.midterm_supervisor_1 
                            ORDER BY id DESC LIMIT 1
                        ) = 'Approved'
                        THEN 'Approved'

                        WHEN p.midterm_supervisor_2 IS NOT NULL AND (
                            SELECT action FROM ppa_approval_trail_midterm 
                            WHERE entry_id = p.entry_id AND staff_id = p.midterm_supervisor_1 
                            ORDER BY id DESC LIMIT 1
                        ) = 'Approved' AND (
                            SELECT action FROM ppa_approval_trail_midterm 
                            WHERE entry_id = p.entry_id AND staff_id = p.midterm_supervisor_2 
                            ORDER BY id DESC LIMIT 1
                        ) = 'Approved'
                        THEN 'Approved'

                        WHEN (
                            SELECT action FROM ppa_approval_trail_midterm 
                            WHERE entry_id = p.entry_id AND staff_id = p.midterm_supervisor_2 
                            ORDER BY id DESC LIMIT 1
                        ) = 'Returned'
                        THEN 'Returned'

                        WHEN (
                            SELECT action FROM ppa_approval_trail_midterm 
                            WHERE entry_id = p.entry_id AND staff_id = p.midterm_supervisor_1 
                            ORDER BY id DESC LIMIT 1
                        ) = 'Returned'
                        THEN 'Returned'

                        WHEN p.midterm_supervisor_2 IS NOT NULL AND (
                            SELECT action FROM ppa_approval_trail_midterm 
                            WHERE entry_id = p.entry_id AND staff_id = p.midterm_supervisor_1 
                            ORDER BY id DESC LIMIT 1
                        ) = 'Approved'
                        THEN 'Pending Second Supervisor'

                        ELSE 'Pending First Supervisor'
                    END
                )
            END AS overall_status

        FROM ppa_entries p
        JOIN staff s ON s.staff_id = p.staff_id

        LEFT JOIN (
            SELECT sc.*
            FROM staff_contracts sc
            INNER JOIN (
                SELECT staff_id, MAX(staff_contract_id) AS max_id
                FROM staff_contracts
                GROUP BY staff_id
            ) latest ON latest.staff_id = sc.staff_id AND latest.max_id = sc.staff_contract_id
        ) sc ON sc.staff_id = p.staff_id

        LEFT JOIN divisions d ON d.division_id = sc.division_id

        WHERE p.performance_period = ? 

    ";

    $params = [$filters['period']];

    // Filters
    if (!empty($filters['staff_name'])) {
        $sql .= " AND (s.fname LIKE ? OR s.lname LIKE ?)";
        $params[] = '%' . $filters['staff_name'] . '%';
        $params[] = '%' . $filters['staff_name'] . '%';
    }

    if ($filters['draft_status'] !== '' && $filters['draft_status'] !== null) {
        $sql .= " AND p.midterm_draft_status = ?";
        $params[] = $filters['draft_status'];
    }

    if (!empty($filters['created_at'])) {
        $sql .= " AND DATE(p.midterm_created_at) = ?";
        $params[] = $filters['created_at'];
    }

    if (!empty($filters['division_id'])) {
        $sql .= " AND sc.division_id = ?";
        $params[] = $filters['division_id'];
    }

    $sql .= " ORDER BY p.midterm_created_at DESC";

    if ($limit > 0) {
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = (int) $limit;
        $params[] = (int) $offset;
    }

    $query = $this->db->query($sql, $params);
    // Debug output
// log_message('debug', "SQL: " . $this->db->last_query());
// log_message('debug', "Results: " . json_encode($query->result_array()));

return $query->result_array();
}



public function count_ppas_filtered($filters)
{
    return count($this->get_all_ppas_filtered($filters, 0, 0));
}

/**
 * Returns staff who have submitted a PPA for the given period but have NOT submitted a midterm.
 * - Only considers staff with active contracts (status_id 1 or 2, not in excluded contract types).
 * - Only considers PPA entries that are not drafts (draft_status != 1).
 * - Returns staff details for those missing a midterm (midterm_draft_status is NULL or 1).
 */
public function get_staff_without_midterm($period = null, $division_id = null)
{
    // STEP 1: Get latest contract for each staff
    $subquery = $this->db->select('MAX(staff_contract_id)', false)
        ->from('staff_contracts')
        ->group_by('staff_id')
        ->get_compiled_select();

    $this->db->select('
        s.staff_id, s.title, s.fname, s.lname, s.oname, s.work_email, s.SAPNO,
        d.division_name, ct.contract_type, st.status
    ');
    $this->db->from('staff s');
    $this->db->join('staff_contracts sc', 'sc.staff_id = s.staff_id', 'left');
    $this->db->join('divisions d', 'd.division_id = sc.division_id', 'left');
    $this->db->join('contract_types ct', 'ct.contract_type_id = sc.contract_type_id', 'left');
    $this->db->join('status st', 'st.status_id = sc.status_id', 'left');
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    $this->db->where_in('sc.status_id', [1, 2]); // Active or Due
    $this->db->where_not_in('sc.contract_type_id', [1, 5, 3, 7]); // Exclude Regular, Fixed, AUYVC, ALD (same as PPA dashboard)
    $this->db->where("TRIM(s.work_email) !=", '');
    $this->db->where("TRIM(s.work_email) !=", 'xx%');

    if ($division_id) {
        $this->db->where('sc.division_id', $division_id);
    }

    $active_staff = $this->db->get()->result();

    if (empty($active_staff)) return [];

    // STEP 2: Extract staff_ids from active staff
    $staff_ids = array_map('intval', array_column($active_staff, 'staff_id'));
    if (empty($staff_ids)) return [];

    // STEP 3: Get staff who have submitted a PPA (not draft) for the period
    $this->db->select('staff_id, entry_id');
    $this->db->from('ppa_entries');
    $this->db->where_in('staff_id', $staff_ids);
    $this->db->where('draft_status !=', 1); // exclude drafts
    if ($period) {
        $this->db->where('performance_period', $period);
    }
    $ppa_entries = $this->db->get()->result_array();

    // Map: staff_id => entry_id
    $ppa_staff_map = [];
    foreach ($ppa_entries as $row) {
        $ppa_staff_map[(int)$row['staff_id']] = $row['entry_id'];
    }

    if (empty($ppa_staff_map)) return [];

    // STEP 4: Get staff who have submitted a midterm (midterm_draft_status != 1) for the period
    $this->db->select('staff_id');
    $this->db->from('ppa_entries');
    $this->db->where_in('staff_id', array_keys($ppa_staff_map));
    if ($period) {
        $this->db->where('performance_period', $period);
    }
    $this->db->where('midterm_draft_status !=', 1); // Submitted (not draft)
    $midterm_submitted = $this->db->get()->result_array();
    $midterm_submitted_ids = array_map(fn($r) => (int)$r['staff_id'], $midterm_submitted);

    // STEP 5: Filter staff who have PPA but missing midterm (midterm_draft_status is NULL or 1)
    $missing_midterm_ids = array_diff(array_keys($ppa_staff_map), $midterm_submitted_ids);

    // Return staff details for those missing midterms
    return array_values(array_filter($active_staff, function ($staff) use ($missing_midterm_ids) {
        return in_array((int)$staff->staff_id, $missing_midterm_ids, true);
    }));
}

public function ppa_exists($entry_id){

}

public function get_recent_midterm_for_user($entry_id, $period)
{
    $sql = "
        SELECT 
            p.*, 
            CONCAT(s.fname, ' ', s.lname) AS staff_name,
            (
                SELECT a.action 
                FROM ppa_approval_trail_midterm a
                WHERE a.entry_id = p.entry_id
                ORDER BY a.id DESC LIMIT 1
            ) AS last_action,
            (
                CASE
                    WHEN p.midterm_draft_status = 1 THEN 'Draft'
                    WHEN (
                        SELECT a.action 
                        FROM ppa_approval_trail_midterm a
                        WHERE a.entry_id = p.entry_id
                        ORDER BY a.id DESC LIMIT 1
                    ) = 'Approved' THEN 'Approved'
                    WHEN (
                        SELECT a.action 
                        FROM ppa_approval_trail_midterm a
                        WHERE a.entry_id = p.entry_id
                        ORDER BY a.id DESC LIMIT 1
                    ) = 'Returned' THEN 'Returned'
                    WHEN (
                        SELECT a.action 
                        FROM ppa_approval_trail_midterm a
                        WHERE a.entry_id = p.entry_id
                        ORDER BY a.id DESC LIMIT 1
                    ) = 'Submitted' THEN 'Pending'
                    ELSE 'Pending'
                END
            ) AS midterm_status
        FROM ppa_entries p
        JOIN staff s ON s.staff_id = p.staff_id
        WHERE p.entry_id = ? AND p.performance_period = ?
        ORDER BY p.midterm_created_at DESC
        LIMIT 1
    ";
    return $this->db->query($sql, [$entry_id, $period])->row_array();
}

public function get_all_approved_midterms_for_user($staff_id)
{
    $sql = "
        SELECT 
            p.*, 
            CONCAT(s.fname, ' ', s.lname) AS staff_name,
            (
                SELECT a.action 
                FROM ppa_approval_trail_midterm a
                WHERE a.entry_id = p.entry_id
                ORDER BY a.id DESC LIMIT 1
            ) AS last_action,
            (
                CASE
                    WHEN p.midterm_draft_status = 1 THEN 'Draft'
                    WHEN (
                        SELECT a.action 
                        FROM ppa_approval_trail_midterm a
                        WHERE a.entry_id = p.entry_id
                        ORDER BY a.id DESC LIMIT 1
                    ) = 'Approved' THEN 'Approved'
                    WHEN (
                        SELECT a.action 
                        FROM ppa_approval_trail_midterm a
                        WHERE a.entry_id = p.entry_id
                        ORDER BY a.id DESC LIMIT 1
                    ) = 'Returned' THEN 'Returned'
                    WHEN (
                        SELECT a.action 
                        FROM ppa_approval_trail_midterm a
                        WHERE a.entry_id = p.entry_id
                        ORDER BY a.id DESC LIMIT 1
                    ) = 'Submitted' THEN 'Pending Supervisor'
                    ELSE 'Pending'
                END
            ) AS midterm_status
        FROM ppa_entries p
        JOIN staff s ON s.staff_id = p.staff_id
        WHERE p.staff_id = ?
        AND p.midterm_draft_status != 1
        ORDER BY p.midterm_created_at DESC
    ";
    return $this->db->query($sql, [$staff_id])->result_array();
}

public function get_midterms_approved_by_supervisor($supervisor_id)
{
    $sql = "
        SELECT 
            p.*, 
            CONCAT(s.fname, ' ', s.lname) AS staff_name,
            a.created_at AS approval_date,
            a.comments,
            a.staff_id AS approver_id,
            (
                CASE
                    WHEN p.midterm_draft_status = 1 THEN 'Draft'
                    WHEN (
                        SELECT a2.action 
                        FROM ppa_approval_trail_midterm a2
                        WHERE a2.entry_id = p.entry_id
                        ORDER BY a2.id DESC LIMIT 1
                    ) = 'Approved' THEN 'Approved'
                    WHEN (
                        SELECT a2.action 
                        FROM ppa_approval_trail_midterm a2
                        WHERE a2.entry_id = p.entry_id
                        ORDER BY a2.id DESC LIMIT 1
                    ) = 'Returned' THEN 'Returned'
                    WHEN (
                        SELECT a2.action 
                        FROM ppa_approval_trail_midterm a2
                        WHERE a2.entry_id = p.entry_id
                        ORDER BY a2.id DESC LIMIT 1
                    ) = 'Submitted' THEN 'Pending Supervisor'
                    ELSE 'Pending'
                END
            ) AS midterm_status
        FROM ppa_entries p
        JOIN staff s ON s.staff_id = p.staff_id
        JOIN ppa_approval_trail_midterm a ON a.entry_id = p.entry_id
        WHERE a.action = 'Approved'
          AND a.staff_id = ?
        ORDER BY a.created_at DESC
    ";
    return $this->db->query($sql, [$supervisor_id])->result_array();
}






}
