<?php

class Performance_mdl extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    public function isapproved($entry_id)
    {
        if (!empty($entry_id)) {
            $this->db->where('entry_id', $entry_id);
            $this->db->where('draft_status', 2);
            return ($this->db->get('ppa_entries')->num_rows() > 0);
        }
        return FALSE;
    }
    
    public function get_staff_plan_id($entry_id)
    {
        if (!empty($entry_id)) {
            $this->db->where('entry_id', $entry_id);
            return ($this->db->get('ppa_entries')->num_rows() > 0);
        }
        return FALSE;
    }

    public function ismidterm_available($entry_id)
    {
        if (!empty($entry_id)) {
            $this->db->where('midterm_created_at IS NOT NULL', null, false);
            $this->db->where('entry_id', $entry_id);
            return ($this->db->get('ppa_entries')->num_rows() > 0);
        }
        return FALSE;
    }
    
    public function isendterm_available($entry_id)
    {
        if (!empty($entry_id)) {
            $this->db->where('endterm_created_at IS NOT NULL', null, false);
            $this->db->where('entry_id', $entry_id);
            return ($this->db->get('ppa_entries')->num_rows() > 0);
        }
        return FALSE;
    }
    
    
    

    public function get_plan_by_entry_id($entry_id)
    {
        $query = $this->db->get_where('ppa_entries', ['entry_id' => $entry_id]);
        $result = $query->row();
    
        if ($result) {
            // Decode only if the field is not null
            $result->objectives = $result->objectives ? json_decode($result->objectives) : [];
            $result->required_skills = $result->required_skills ? json_decode($result->required_skills) : [];
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
    $staff_id_escaped = (int)$staff_id;
    $sql = "
        SELECT 
            p.*, 
            CONCAT(s.fname, ' ', s.lname) AS staff_name,

            -- Supervisor 1 last action
            (
                SELECT a1.action 
                FROM ppa_approval_trail a1
                WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id
                ORDER BY a1.id DESC LIMIT 1
            ) AS supervisor1_action,

            -- Supervisor 2 last action
            (
                SELECT a2.action 
                FROM ppa_approval_trail a2
                WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.supervisor2_id
                ORDER BY a2.id DESC LIMIT 1
            ) AS supervisor2_action,

            -- Compute overall status
            CASE 
                WHEN p.draft_status = 1 THEN 'Pending (Draft)'
                WHEN p.supervisor2_id IS NULL AND
                    COALESCE((
                        SELECT a1.action 
                        FROM ppa_approval_trail a1 
                        WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id 
                        ORDER BY a1.id DESC LIMIT 1
                    ), '') = 'Approved'
                    -- AND there's no 'Returned' action after the approval
                    AND NOT EXISTS (
                        SELECT 1 
                        FROM ppa_approval_trail a_returned 
                        WHERE a_returned.entry_id = p.entry_id 
                        AND a_returned.action = 'Returned'
                        AND a_returned.id > (
                            SELECT MAX(a_approved.id) 
                            FROM ppa_approval_trail a_approved 
                            WHERE a_approved.entry_id = p.entry_id 
                            AND a_approved.staff_id = p.supervisor_id 
                            AND a_approved.action = 'Approved'
                        )
                    )
                THEN 'Approved'

                WHEN p.supervisor2_id IS NOT NULL AND
                    COALESCE((
                        SELECT a1.action 
                        FROM ppa_approval_trail a1 
                        WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id 
                        ORDER BY a1.id DESC LIMIT 1
                    ), '') = 'Approved' AND
                    COALESCE((
                        SELECT a2.action 
                        FROM ppa_approval_trail a2 
                        WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.supervisor2_id 
                        ORDER BY a2.id DESC LIMIT 1
                    ), '') = 'Approved'
                    -- AND there's no 'Returned' action after either approval
                    AND NOT EXISTS (
                        SELECT 1 
                        FROM ppa_approval_trail a_returned 
                        WHERE a_returned.entry_id = p.entry_id 
                        AND a_returned.action = 'Returned'
                        AND (
                            a_returned.id > (
                                SELECT MAX(a_approved.id) 
                                FROM ppa_approval_trail a_approved 
                                WHERE a_approved.entry_id = p.entry_id 
                                AND a_approved.staff_id = p.supervisor_id 
                                AND a_approved.action = 'Approved'
                            )
                            OR
                            a_returned.id > (
                                SELECT MAX(a_approved.id) 
                                FROM ppa_approval_trail a_approved 
                                WHERE a_approved.entry_id = p.entry_id 
                                AND a_approved.staff_id = p.supervisor2_id 
                                AND a_approved.action = 'Approved'
                            )
                        )
                    )
                THEN 'Approved'

                WHEN p.draft_status = 1 AND COALESCE((
                    SELECT a2.action 
                    FROM ppa_approval_trail a2 
                    WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.supervisor2_id 
                    ORDER BY a2.id DESC LIMIT 1
                ), '') = 'Returned'
                THEN 'Returned'

                WHEN p.draft_status = 1 AND COALESCE((
                    SELECT a1.action 
                    FROM ppa_approval_trail a1 
                    WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id 
                    ORDER BY a1.id DESC LIMIT 1
                ), '') = 'Returned'
                THEN 'Returned'

                WHEN p.supervisor2_id IS NOT NULL AND
                    COALESCE((
                        SELECT a1.action 
                        FROM ppa_approval_trail a1 
                        WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id 
                        ORDER BY a1.id DESC LIMIT 1
                    ), '') = 'Approved'
                    -- AND there's no 'Returned' action after the approval
                    AND NOT EXISTS (
                        SELECT 1 
                        FROM ppa_approval_trail a_returned 
                        WHERE a_returned.entry_id = p.entry_id 
                        AND a_returned.action = 'Returned'
                        AND a_returned.id > (
                            SELECT MAX(a_approved.id) 
                            FROM ppa_approval_trail a_approved 
                            WHERE a_approved.entry_id = p.entry_id 
                            AND a_approved.staff_id = p.supervisor_id 
                            AND a_approved.action = 'Approved'
                        )
                    )
                THEN 'Pending Second Supervisor'

                ELSE 'Pending First Supervisor'
            END AS overall_status

        FROM ppa_entries p
        JOIN staff s ON s.staff_id = p.staff_id
        WHERE p.draft_status = 0
        AND (
            -- First supervisor: must be submitted (draft_status = 0)
            -- Show if entry is submitted and not yet approved by first supervisor
            -- This includes entries that were returned (by anyone) and then resubmitted
            (p.supervisor_id = {$staff_id_escaped} AND 
             (p.staff_sign_off = 1 OR p.staff_sign_off IS NULL) AND
             (
               -- Check if most recent action by first supervisor is NOT 'Approved'
               -- This handles: NULL (never approved), 'Returned' (first supervisor returned it)
               COALESCE((
                        SELECT a1.action
                        FROM ppa_approval_trail a1
                        WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id
                        ORDER BY a1.id DESC LIMIT 1
               ), '') != 'Approved'
               OR
               -- OR if entry was returned by ANYONE (first or second supervisor, HR officer, etc.) and then resubmitted
               -- Check if there's a 'Returned' action (by anyone) followed by a 'Submitted' or 'Updated' action
               EXISTS (
                   SELECT 1 
                   FROM ppa_approval_trail a_returned 
                   WHERE a_returned.entry_id = p.entry_id 
                   AND a_returned.action = 'Returned'
                   AND EXISTS (
                       SELECT 1 
                       FROM ppa_approval_trail a_resubmitted 
                       WHERE a_resubmitted.entry_id = p.entry_id 
                       AND a_resubmitted.action IN ('Submitted', 'Updated')
                       AND a_resubmitted.id > a_returned.id
                )
            )
             ))
            OR
            -- Second supervisor: can see if first approved (even if still draft)
            (p.supervisor2_id = {$staff_id_escaped} AND
             p.supervisor2_id IS NOT NULL AND
             COALESCE((
                        SELECT a1.action
                        FROM ppa_approval_trail a1
                        WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id
                        ORDER BY a1.id DESC LIMIT 1
             ), '') = 'Approved'
             -- AND there's no 'Returned' action after the approval
             AND NOT EXISTS (
                 SELECT 1 
                 FROM ppa_approval_trail a_returned 
                 WHERE a_returned.entry_id = p.entry_id 
                 AND a_returned.action = 'Returned'
                 AND a_returned.id > (
                     SELECT MAX(a_approved.id) 
                     FROM ppa_approval_trail a_approved 
                     WHERE a_approved.entry_id = p.entry_id 
                     AND a_approved.staff_id = p.supervisor_id 
                     AND a_approved.action = 'Approved'
                 )
             )
             AND COALESCE((
                            SELECT a2.action
                            FROM ppa_approval_trail a2
                            WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.supervisor2_id
                            ORDER BY a2.id DESC LIMIT 1
             ), '') != 'Approved')
        )
        ORDER BY p.created_at DESC
    ";

    return $this->db->query($sql)->result_array();
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
        
        -- Last action by Supervisor 1
        (
            SELECT a1.action 
            FROM ppa_approval_trail a1
            WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id
            ORDER BY a1.id DESC LIMIT 1
        ) AS supervisor1_action,
        
        -- Last action by Supervisor 2
        (
            SELECT a2.action 
            FROM ppa_approval_trail a2
            WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.supervisor2_id
            ORDER BY a2.id DESC LIMIT 1
        ) AS supervisor2_action

    FROM ppa_entries p
    JOIN staff s ON s.staff_id = p.staff_id
    WHERE p.staff_id = ?

    AND (
        -- Case 1: Only one supervisor and they approved
        (p.supervisor2_id IS NULL AND
         (
            SELECT a1.action 
            FROM ppa_approval_trail a1
            WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id
            ORDER BY a1.id DESC LIMIT 1
         ) = 'Approved')

        -- Case 2: Both supervisors and both approved
        OR (
            p.supervisor2_id IS NOT NULL AND
            (
                SELECT a1.action 
                FROM ppa_approval_trail a1
                WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id
                ORDER BY a1.id DESC LIMIT 1
            ) = 'Approved' AND
            (
                SELECT a2.action 
                FROM ppa_approval_trail a2
                WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.supervisor2_id
                ORDER BY a2.id DESC LIMIT 1
            ) = 'Approved'
        )
    )

    ORDER BY p.created_at DESC
    ";

    return $this->db->query($sql, [$staff_id])->result_array();
}

public function get_recent_ppas_for_user($staff_id, $period)
{
    $sql = "
        SELECT 
            p.*, 
            CONCAT(s.fname, ' ', s.lname) AS staff_name,

            -- Last action by Supervisor 1
            (
                SELECT a1.action 
                FROM ppa_approval_trail a1
                WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id
                ORDER BY a1.id DESC LIMIT 1
            ) AS supervisor1_action,
            
            -- Last action by Supervisor 2
            (
                SELECT a2.action 
                FROM ppa_approval_trail a2
                WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.supervisor2_id
                ORDER BY a2.id DESC LIMIT 1
            ) AS supervisor2_action,
            
            -- Final status decision with draft consideration
            CASE 
                WHEN p.draft_status = 1 THEN 'Pending (Draft)'
                ELSE (
                    CASE
                        WHEN p.supervisor2_id IS NULL AND
                            (
                                SELECT a1.action 
                                FROM ppa_approval_trail a1
                                WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id
                                ORDER BY a1.id DESC LIMIT 1
                            ) = 'Approved'
                        THEN 'Approved'

                        WHEN p.supervisor2_id IS NOT NULL AND
                            (
                                SELECT a1.action 
                                FROM ppa_approval_trail a1
                                WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id
                                ORDER BY a1.id DESC LIMIT 1
                            ) = 'Approved' AND
                            (
                                SELECT a2.action 
                                FROM ppa_approval_trail a2
                                WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.supervisor2_id
                                ORDER BY a2.id DESC LIMIT 1
                            ) = 'Approved'
                        THEN 'Approved'

                        WHEN (
                                SELECT a2.action 
                                FROM ppa_approval_trail a2
                                WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.supervisor2_id
                                ORDER BY a2.id DESC LIMIT 1
                            ) = 'Returned'
                        THEN 'Returned'

                        WHEN (
                                SELECT a1.action 
                                FROM ppa_approval_trail a1
                                WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id
                                ORDER BY a1.id DESC LIMIT 1
                            ) = 'Returned'
                        THEN 'Returned'

                        WHEN p.supervisor2_id IS NOT NULL AND
                            (
                                SELECT a1.action 
                                FROM ppa_approval_trail a1
                                WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.supervisor_id
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
        ORDER BY p.created_at DESC
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
            p.created_at,
            CONCAT(st.fname, ' ', st.lname) AS staff_name,
            a.created_at AS approval_date,
            a.comments

        FROM ppa_entries p
        JOIN staff st ON st.staff_id = p.staff_id
        JOIN ppa_approval_trail a ON a.id = (
            SELECT MAX(id) 
            FROM ppa_approval_trail 
            WHERE entry_id = p.entry_id
            AND action = 'Approved'
        )
        WHERE 
            (p.supervisor_id = ? OR p.supervisor2_id = ?)
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
            // For completed PPAs: show all who completed regardless of contract status
            // First, get staff who completed PPAs
            if ($type === 'total') {
                // Staff who have submitted PPAs (not drafts)
                $this->db->select('pe.staff_id, pe.entry_id');
                $this->db->from('ppa_entries pe');
                if ($period) $this->db->where('pe.performance_period', $period);
                $this->db->where('pe.draft_status !=', 1); // PPA submitted
            } elseif ($type === 'approved') {
                // Staff whose PPAs have been approved
                // Use WHERE EXISTS with subquery to handle collation properly
                $this->db->select('pe.staff_id, pe.entry_id');
                $this->db->from('ppa_entries pe');
                $this->db->where('pe.draft_status !=', 1);
                if ($period) $this->db->where('pe.performance_period', $period);
                // Use raw WHERE clause for approval check
                $this->db->where("EXISTS (
                    SELECT 1 FROM ppa_approval_trail pat
                    WHERE pat.entry_id = pe.entry_id
                    AND pat.action = 'Approved'
                    AND pat.id = (
                        SELECT MAX(id) 
                        FROM ppa_approval_trail 
                        WHERE entry_id = pe.entry_id
                    )
                )", null, false);
            } elseif ($type === 'with_pdp') {
                // Staff who have training recommendations in their PPA
                $this->db->select('pe.staff_id, pe.entry_id, pe.required_skills');
                $this->db->from('ppa_entries pe');
                $this->db->where('pe.training_recommended', 'Yes');
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
                    if (isset($entry->required_skills)) {
                        $skill_ids = json_decode($entry->required_skills ?? '[]', true);
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

            // Staff who haven't submitted PPAs (active staff without PPA submissions)
            return array_filter($active_staff, function ($staff) use ($period) {
                $this->db->select('staff_id');
                $this->db->from('ppa_entries');
                if ($period) $this->db->where('performance_period', $period);
                $this->db->where('draft_status !=', 1); // PPA submitted
                $this->db->where('staff_id', $staff->staff_id);
                return $this->db->count_all_results() == 0;
            });

        default:
            return [];
    }
}


public function get_dashboard_data()
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

    // For completed PPAs: count all regardless of contract status, but apply division filters
    // Summary counts (regardless of contract status)
    $this->db->select("
        COUNT(pe.entry_id) AS total,
        SUM(CASE WHEN latest.action = 'Approved' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN latest.action = 'Submitted' THEN 1 ELSE 0 END) AS submitted", false);
    $this->db->from('ppa_entries pe');
    $this->db->join("(SELECT pat1.* FROM ppa_approval_trail pat1
        INNER JOIN (SELECT entry_id, MAX(id) AS max_id FROM ppa_approval_trail GROUP BY entry_id) latest
        ON pat1.id = latest.max_id) latest", 'latest.entry_id = pe.entry_id', 'left');
    // Join with contracts to apply division filter (but no contract status filter)
    $this->db->join('staff_contracts sc', 'sc.staff_id = pe.staff_id', 'left');
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
    $this->db->where('pe.draft_status !=', 1);
    $this->db->where('pe.performance_period', $period);
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

    // Submission trend (regardless of contract status)
    $this->db->select("DATE(pe.created_at) AS date, COUNT(pe.entry_id) AS count");
    $this->db->from("ppa_entries pe");
    $this->db->join('staff_contracts sc', 'sc.staff_id = pe.staff_id', 'left');
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
    $this->db->where('pe.draft_status !=', 1);
    $this->db->where("pe.performance_period", $period);
    $this->db->group_by("DATE(pe.created_at)");
    $this->db->order_by("DATE(pe.created_at)", "ASC");
    $trend = array_map(function ($r) {
        return ['date' => $r->date, 'count' => (int)$r->count];
    }, $this->db->get()->result());

    // Average approval time (regardless of contract status)
    $this->db->select("pe.created_at AS submitted_date, latest.created_at AS approved_date");
    $this->db->from("ppa_entries pe");
    $this->db->join("(SELECT pat1.* FROM ppa_approval_trail pat1
        INNER JOIN (SELECT entry_id, MAX(id) AS max_id FROM ppa_approval_trail WHERE action = 'Approved' GROUP BY entry_id) latest
        ON pat1.id = latest.max_id) latest", "latest.entry_id = pe.entry_id", "left");
    $this->db->join('staff_contracts sc', 'sc.staff_id = pe.staff_id', 'left');
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
    $this->db->where("pe.draft_status !=", 1);
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

    // Division-wise PPA count (regardless of contract status)
    $this->db->select("d.division_name, COUNT(pe.entry_id) AS count");
    $this->db->from("ppa_entries pe");
    $this->db->join("staff_contracts sc", "sc.staff_id = pe.staff_id", "left");
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    $this->db->join("divisions d", "d.division_id = sc.division_id", "left");
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
    $this->db->where("pe.draft_status !=", 1);
    $this->db->where("pe.performance_period", $period);
    $this->db->group_by("sc.division_id");
    $divisions = array_map(fn($r) => ['name' => $r->division_name, 'y' => (int)$r->count], $this->db->get()->result());

    // Contract types (based on latest contract only) (regardless of contract status)
    $this->db->select("ct.contract_type, COUNT(pe.entry_id) AS total");
    $this->db->from("ppa_entries pe");
    $this->db->join("staff_contracts sc", "sc.staff_id = pe.staff_id", "left");
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    $this->db->join("contract_types ct", "ct.contract_type_id = sc.contract_type_id", "left");
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
    $this->db->where("pe.draft_status !=", 1);
    $this->db->where("pe.performance_period", $period);
    $this->db->group_by("ct.contract_type_id");
    $by_contract = array_map(fn($r) => ['name' => $r->contract_type, 'y' => (int)$r->total], $this->db->get()->result());

    // Staff with PPA (regardless of contract status, but filter to active staff for "without" calculation)
    $this->db->select("pe.staff_id")->from("ppa_entries pe");
    $this->db->join('staff_contracts sc', 'sc.staff_id = pe.staff_id', 'left');
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
    $this->db->where("pe.performance_period", $period);
    $this->db->where("pe.draft_status !=", 1);
    $all_ppa_staff = array_column($this->db->get()->result(), 'staff_id');
    // Filter to only active staff for "without" calculation
    $ppa_staff = array_intersect($all_ppa_staff, $staff_ids);

    // Staff with PDP (regardless of contract status)
    $this->db->select("pe.staff_id")->from("ppa_entries pe");
    $this->db->join('staff_contracts sc', 'sc.staff_id = pe.staff_id', 'left');
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
    $this->db->where("pe.training_recommended", "Yes");
    $this->db->where("pe.draft_status !=", 1);
    $this->db->where("pe.performance_period", $period);
    $pdp_staff = array_column($this->db->get()->result(), 'staff_id');

    // Periods list - only get distinct periods from actual PPA entries
    $this->db->distinct();
    $this->db->select("pe.performance_period");
    $this->db->from("ppa_entries pe");
    $this->db->where("pe.draft_status !=", 1); // Only non-draft PPAs
    if ($is_restricted) $this->db->where("pe.staff_id", $staff_id);
    $this->db->order_by("pe.performance_period", "DESC");
    $periods_result = $this->db->get()->result();
    $periods = array_column($periods_result, 'performance_period');
    $periods = array_unique($periods); // Ensure distinct values
    $current_period = !empty($periods) ? $periods[0] : $period;

    // Age groups (regardless of contract status)
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
        if ($min !== null) $this->db->where("TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) >=", $min);
        if ($max !== null) $this->db->where("TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) <=", $max);
        $count = $this->db->count_all_results();
        $age_data[] = ['group' => $label, 'count' => $count];
    }

    // Training categories (regardless of contract status)
    $this->db->select("tc.category_name AS name, COUNT(*) AS y", false);
    $this->db->from("ppa_entries pe");
    $this->db->join("training_skills ts", "JSON_CONTAINS(pe.required_skills, JSON_QUOTE(CAST(ts.id AS CHAR)), '$')", "inner", false);
    $this->db->join("training_categories tc", "tc.id = ts.category_id", "left");
    $this->db->join('staff_contracts sc', 'sc.staff_id = pe.staff_id', 'left');
    $this->db->where("sc.staff_contract_id IN ($subquery)", null, false);
    if ($division_id) $this->db->where('sc.division_id', $division_id);
    if ($is_restricted) $this->db->where('pe.staff_id', $staff_id);
    $this->db->where("pe.draft_status !=", 1);
    $this->db->where("pe.performance_period", $period);
    $this->db->group_by("ts.category_id");
    $training_categories = $this->db->get()->result();

    // Top 10 training skills
    $this->db->select("ts.skill AS name, COUNT(*) AS y", false);
    $this->db->from("ppa_entries pe");
    $this->db->join("training_skills ts", "JSON_CONTAINS(pe.required_skills, JSON_QUOTE(CAST(ts.id AS CHAR)), '$')", "inner", false);
    $this->db->where_in("pe.staff_id", $staff_ids);
    $this->db->where("pe.draft_status !=", 1);
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
        'staff_without_ppas' => max(0, count($staff_ids) - count($ppa_staff)),
        'staff_with_pdps' => count($pdp_staff),
        'periods' => $periods,
        'current_period' => $current_period,
    ];
}





public function get_supervisors_with_pending_ppas($period)
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
            ON s.staff_id = p.supervisor_id OR s.staff_id = p.supervisor2_id
        WHERE p.performance_period = ?
        AND p.draft_status = 0
        AND p.entry_id NOT IN (
            SELECT entry_id FROM ppa_approval_trail WHERE action = 'Approved'
        )
        ORDER BY s.fname ASC
    ";

    return $this->db->query($sql, [$period])->result();
}

public function get_pending_by_supervisor_with_staff($supervisor_id)
{
    $subquery = $this->db->select('entry_id')
        ->from('ppa_approval_trail')
        ->where('action', 'Approved')
        ->get_compiled_select();

    $this->db->select("
        p.entry_id,
        p.staff_id,
        CONCAT(s.title, ' ', s.fname, ' ', s.lname) AS staff_name,
        p.performance_period,
        p.created_at
    ");
    $this->db->from('ppa_entries p');
    $this->db->join('staff s', 's.staff_id = p.staff_id', 'left');
    $this->db->group_start()
             ->where('p.supervisor_id', $supervisor_id)
             ->or_where('p.supervisor2_id', $supervisor_id)
             ->group_end();
    $this->db->where('p.draft_status', 0);
    $this->db->where("p.entry_id NOT IN ($subquery)", null, false);
    $this->db->order_by("p.created_at", "DESC");

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
                WHEN p.draft_status = 1 THEN 'Draft'
                ELSE (
                    CASE
                        WHEN p.supervisor2_id IS NULL AND (
                            SELECT action FROM ppa_approval_trail 
                            WHERE entry_id = p.entry_id AND staff_id = p.supervisor_id 
                            ORDER BY id DESC LIMIT 1
                        ) = 'Approved'
                        THEN 'Approved'

                        WHEN p.supervisor2_id IS NOT NULL AND (
                            SELECT action FROM ppa_approval_trail 
                            WHERE entry_id = p.entry_id AND staff_id = p.supervisor_id 
                            ORDER BY id DESC LIMIT 1
                        ) = 'Approved' AND (
                            SELECT action FROM ppa_approval_trail 
                            WHERE entry_id = p.entry_id AND staff_id = p.supervisor2_id 
                            ORDER BY id DESC LIMIT 1
                        ) = 'Approved'
                        THEN 'Approved'

                        WHEN (
                            SELECT action FROM ppa_approval_trail 
                            WHERE entry_id = p.entry_id AND staff_id = p.supervisor2_id 
                            ORDER BY id DESC LIMIT 1
                        ) = 'Returned'
                        THEN 'Returned'

                        WHEN (
                            SELECT action FROM ppa_approval_trail 
                            WHERE entry_id = p.entry_id AND staff_id = p.supervisor_id 
                            ORDER BY id DESC LIMIT 1
                        ) = 'Returned'
                        THEN 'Returned'

                        WHEN p.supervisor2_id IS NOT NULL AND (
                            SELECT action FROM ppa_approval_trail 
                            WHERE entry_id = p.entry_id AND staff_id = p.supervisor_id 
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
        $sql .= " AND p.draft_status = ?";
        $params[] = $filters['draft_status'];
    }

    if (!empty($filters['created_at'])) {
        $sql .= " AND DATE(p.created_at) = ?";
        $params[] = $filters['created_at'];
    }

    if (!empty($filters['division_id'])) {
        $sql .= " AND sc.division_id = ?";
        $params[] = $filters['division_id'];
    }

    $sql .= " ORDER BY p.created_at DESC";

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

public function get_staff_without_ppa($period = null, $division_id = null)
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
    $this->db->where_not_in('sc.contract_type_id', [1, 3, 5, 7]);
    $this->db->where("TRIM(s.work_email) !=", '');
    $this->db->where("TRIM(s.work_email) !=", 'xx%');
    // Excluded contract types

    if ($division_id) {
        $this->db->where('sc.division_id', $division_id);
    }

    $active_staff = $this->db->get()->result();

    if (empty($active_staff)) return [];

    // STEP 2: Extract staff_ids from active staff
    $staff_ids = array_map('intval', array_column($active_staff, 'staff_id'));

    // STEP 3: Get staff with submitted PPAs (excluding draft_status = 1)
    $this->db->select('staff_id');
    $this->db->from('ppa_entries');
    $this->db->where_in('staff_id', $staff_ids);
    $this->db->where('draft_status !=', 1); // exclude drafts
    if ($period) {
        $this->db->where('performance_period', $period);
    }

    $submitted_ids_raw = $this->db->get()->result_array();
    $submitted_ids = array_map(fn($r) => (int)$r['staff_id'], $submitted_ids_raw);

    // DEBUGGING CHECKS
    // dd($staff_ids, $submitted_ids);

    // STEP 4: Filter only staff without submissions
    return array_values(array_filter($active_staff, function ($staff) use ($submitted_ids) {
        return !in_array((int)$staff->staff_id, $submitted_ids, true);
    }));
}

public function ppa_exists($entry_id){

}

// --- MIDTERM PENDING APPROVALS BASED ON MIDTERM SUPERVISORS ---

/**
 * Get the last action by a supervisor for an endterm entry
 */
private function _get_endterm_last_action($entry_id, $staff_id)
{
    if (empty($entry_id) || empty($staff_id)) {
        return '';
    }
    
    // Use raw query to handle collation
    $sql = "SELECT action 
            FROM ppa_approval_trail_end_term 
            WHERE entry_id COLLATE utf8mb4_general_ci = ? 
            AND staff_id = ? 
            ORDER BY id DESC 
            LIMIT 1";
    $result = $this->db->query($sql, [$entry_id, $staff_id])->row();
    
    return $result ? $result->action : '';
}

/**
 * Get the most recent action overall for an endterm entry
 */
private function _get_endterm_most_recent_action($entry_id)
{
    if (empty($entry_id)) {
        return '';
    }
    
    // Use raw query to handle collation
    $sql = "SELECT action 
            FROM ppa_approval_trail_end_term 
            WHERE entry_id COLLATE utf8mb4_general_ci = ? 
            ORDER BY id DESC 
            LIMIT 1";
    $result = $this->db->query($sql, [$entry_id])->row();
    
    return $result ? $result->action : '';
}

/**
 * Check if there's a return after a supervisor's approval
 */
private function _has_return_after_approval($entry_id, $supervisor_id)
{
    if (empty($entry_id) || empty($supervisor_id)) {
        return false;
    }
    
    // Get the ID of the supervisor's last approval using raw query
    $sql = "SELECT MAX(id) as id 
            FROM ppa_approval_trail_end_term 
            WHERE entry_id COLLATE utf8mb4_general_ci = ? 
            AND staff_id = ? 
            AND action = 'Approved'";
    $approval_result = $this->db->query($sql, [$entry_id, $supervisor_id])->row();
    
    if (!$approval_result || !$approval_result->id) {
        return false; // No approval found
    }
    
    $approval_id = $approval_result->id;
    
    // Check if there's a return after this approval
    $sql = "SELECT 1 
            FROM ppa_approval_trail_end_term 
            WHERE entry_id COLLATE utf8mb4_general_ci = ? 
            AND action = 'Returned' 
            AND id > ? 
            LIMIT 1";
    $result = $this->db->query($sql, [$entry_id, $approval_id]);
    
    return $result->num_rows() > 0;
}

/**
 * Check if there's a return after staff consent
 */
private function _has_return_after_consent($entry_id)
{
    if (empty($entry_id)) {
        return false;
    }
    
    // Get the ID of the "Employee Consent" action
    $sql = "SELECT MAX(id) as id 
            FROM ppa_approval_trail_end_term 
            WHERE entry_id COLLATE utf8mb4_general_ci = ? 
            AND action = 'Employee Consent'";
    $consent_result = $this->db->query($sql, [$entry_id])->row();
    
    if ($consent_result && $consent_result->id) {
        // Found "Employee Consent" action, check for returns after it
        $consent_id = $consent_result->id;
        $sql = "SELECT 1 
                FROM ppa_approval_trail_end_term 
                WHERE entry_id COLLATE utf8mb4_general_ci = ? 
                AND action = 'Returned' 
                AND id > ? 
                LIMIT 1";
        $result = $this->db->query($sql, [$entry_id, $consent_id]);
        return $result->num_rows() > 0;
    }
    
    // No "Employee Consent" action found, check using consent timestamp
    // Get the consent timestamp from ppa_entries
    $this->db->select('endterm_staff_consent_at');
    $this->db->from('ppa_entries');
    $this->db->where('entry_id', $entry_id);
    $entry = $this->db->get()->row();
    
    if (!$entry || empty($entry->endterm_staff_consent_at)) {
        // No consent timestamp, so can't have a return after it
        return false;
    }
    
    // Check if there's a return after the consent timestamp
    $sql = "SELECT 1 
            FROM ppa_approval_trail_end_term 
            WHERE entry_id COLLATE utf8mb4_general_ci = ? 
            AND action = 'Returned' 
            AND created_at > ? 
            LIMIT 1";
    $result = $this->db->query($sql, [$entry_id, $entry->endterm_staff_consent_at]);
    
    // Return true if there IS a return after consent (bad), false if there isn't (good)
    return $result->num_rows() > 0;
}

/**
 * Check if first supervisor can see the endterm for approval
 */
private function _can_first_supervisor_see_endterm($entry_id, $staff_id)
{
    if (empty($entry_id) || empty($staff_id)) {
        return false;
    }
    
    // Get entry details
    $this->db->select('endterm_supervisor_1, endterm_draft_status, endterm_sign_off');
    $this->db->from('ppa_entries');
    $this->db->where('entry_id', $entry_id);
    $entry = $this->db->get()->row();
    
    if (!$entry || $entry->endterm_supervisor_1 != $staff_id) {
        return false;
    }
    
    // Must be submitted (draft_status = 0)
    if ($entry->endterm_draft_status != 0) {
        return false;
    }
    
    // Check sign_off condition (must be 1 or NULL)
    if ($entry->endterm_sign_off != 1 && $entry->endterm_sign_off !== null) {
        return false;
    }
    
    // Check if most recent action by first supervisor is NOT 'Approved'
    $last_action = $this->_get_endterm_last_action($entry_id, $staff_id);
    $not_approved = ($last_action != 'Approved');
    
    // OR if entry was returned by anyone and then resubmitted
    $was_returned_and_resubmitted = $this->_was_returned_and_resubmitted($entry_id);
    
    return $not_approved || $was_returned_and_resubmitted;
}

/**
 * Check if entry was returned and then resubmitted
 */
private function _was_returned_and_resubmitted($entry_id)
{
    if (empty($entry_id)) {
        return false;
    }
    
    // Check if there's a 'Returned' action followed by 'Submitted' or 'Updated'
    $sql = "SELECT id 
            FROM ppa_approval_trail_end_term 
            WHERE entry_id COLLATE utf8mb4_general_ci = ? 
            AND action = 'Returned'";
    $returns = $this->db->query($sql, [$entry_id])->result();
    
    foreach ($returns as $return) {
        // Check if there's a Submitted or Updated action after this return
        $sql = "SELECT 1 
                FROM ppa_approval_trail_end_term 
                WHERE entry_id COLLATE utf8mb4_general_ci = ? 
                AND action IN ('Submitted', 'Updated') 
                AND id > ? 
                LIMIT 1";
        $result = $this->db->query($sql, [$entry_id, $return->id]);
        
        if ($result->num_rows() > 0) {
            return true;
        }
    }
    
    return false;
}

/**
 * Check if second supervisor can see the endterm for approval
 */
private function _can_second_supervisor_see_endterm($entry_id, $staff_id)
{
    if (empty($entry_id) || empty($staff_id)) {
        log_message('debug', "Endterm visibility check failed: empty entry_id or staff_id. entry_id={$entry_id}, staff_id={$staff_id}");
        return false;
    }
    
    // Get entry details
    $this->db->select('endterm_supervisor_1, endterm_supervisor_2, endterm_staff_consent_at');
    $this->db->from('ppa_entries');
    $this->db->where('entry_id', $entry_id);
    $entry = $this->db->get()->row();
    
    if (!$entry) {
        log_message('debug', "Endterm visibility check failed: entry not found. entry_id={$entry_id}");
        return false;
    }
    
    // Verify this is the second supervisor (with type casting for safety)
    $supervisor2_id = (int)$entry->endterm_supervisor_2;
    $staff_id_int = (int)$staff_id;
    
    if (empty($entry->endterm_supervisor_2) || $supervisor2_id != $staff_id_int) {
        log_message('debug', "Endterm visibility: supervisor mismatch. supervisor2={$supervisor2_id}, staff_id={$staff_id_int}, entry_id={$entry_id}");
        return false;
    }
    
    // First supervisor must have approved
    if (empty($entry->endterm_supervisor_1)) {
        log_message('debug', "Endterm visibility: no first supervisor. entry_id={$entry_id}");
        return false;
    }
    
    $first_supervisor_action = $this->_get_endterm_last_action($entry_id, $entry->endterm_supervisor_1);
    log_message('debug', "Endterm visibility: first supervisor action={$first_supervisor_action}, entry_id={$entry_id}");
    if ($first_supervisor_action != 'Approved') {
        log_message('debug', "Endterm visibility: first supervisor not approved. entry_id={$entry_id}");
        return false;
    }
    
    // No return after first supervisor's approval
    $has_return_after_approval = $this->_has_return_after_approval($entry_id, $entry->endterm_supervisor_1);
    if ($has_return_after_approval) {
        log_message('debug', "Endterm visibility: return after first supervisor approval. entry_id={$entry_id}");
        return false;
    }
    
    // Staff must have consented
    if (empty($entry->endterm_staff_consent_at)) {
        log_message('debug', "Endterm visibility: staff not consented. entry_id={$entry_id}");
        return false;
    }
    
    // No return after staff consent
    $has_return_after_consent = $this->_has_return_after_consent($entry_id);
    log_message('debug', "Endterm visibility: has_return_after_consent=" . ($has_return_after_consent ? 'true' : 'false') . ", entry_id={$entry_id}");
    if ($has_return_after_consent) {
        log_message('debug', "Endterm visibility: return after staff consent. entry_id={$entry_id}");
        return false;
    }
    
    // Second supervisor hasn't approved yet, OR their approval was invalidated by a return
    $second_supervisor_action = $this->_get_endterm_last_action($entry_id, $staff_id);
    log_message('debug', "Endterm visibility: second supervisor action={$second_supervisor_action}, entry_id={$entry_id}");
    
    if ($second_supervisor_action == 'Approved') {
        // Check if there's a return after the second supervisor's approval
        // If there is, their approval is invalidated and they should see it again
        $has_return_after_second_approval = $this->_has_return_after_approval($entry_id, $staff_id);
        log_message('debug', "Endterm visibility: second supervisor approved, checking for return after. has_return={$has_return_after_second_approval}, entry_id={$entry_id}");
        
        if (!$has_return_after_second_approval) {
            // No return after approval, so they've already approved and it's still valid
            log_message('debug', "Endterm visibility: second supervisor already approved (no return after). entry_id={$entry_id}");
            return false;
        }
        // If there's a return after their approval, continue (they can see it again)
        log_message('debug', "Endterm visibility: second supervisor approved but was returned, allowing visibility. entry_id={$entry_id}");
    }
    
    log_message('debug', "Endterm visibility: PASSED - second supervisor can see. entry_id={$entry_id}, staff_id={$staff_id}");
    return true;
}

/**
 * Calculate overall status for an endterm entry
 */
private function _calculate_endterm_status($entry, $supervisor1_action, $supervisor2_action)
{
    // Check if returned
    if ($entry->endterm_draft_status == 1) {
        if ($supervisor2_action == 'Returned' || $supervisor1_action == 'Returned') {
            return 'Returned';
        }
    }
    
    // Single supervisor scenario
    if (empty($entry->endterm_supervisor_2)) {
        if ($supervisor1_action == 'Approved' && 
            !$this->_has_return_after_approval($entry->entry_id, $entry->endterm_supervisor_1)) {
            return 'Approved';
        }
        return 'Pending First Supervisor';
    }
    
    // Two supervisor scenario
    if ($supervisor1_action == 'Approved' && $supervisor2_action == 'Approved') {
        // Check if there's a return after either approval
        if (!$this->_has_return_after_approval($entry->entry_id, $entry->endterm_supervisor_1) &&
            !$this->_has_return_after_approval($entry->entry_id, $entry->endterm_supervisor_2)) {
            return 'Approved';
        }
    }
    
    // Check if pending second supervisor
    if ($supervisor1_action == 'Approved' && 
        !$this->_has_return_after_approval($entry->entry_id, $entry->endterm_supervisor_1) &&
        !empty($entry->endterm_staff_consent_at)) {
        return 'Pending Second Supervisor';
    }
    
    return 'Pending First Supervisor';
}

public function get_all_pending_approvals($staff_id)
{
    // Get pending PPA approvals
    $pending_ppas = $this->get_pending_ppa($staff_id);
    foreach ($pending_ppas as &$ppa) {
        $ppa['approval_type'] = 'ppa';
    }

    // Get pending Midterm approvals based on midterm supervisors
    // Exclude entries that have already been approved
    $staff_id_escaped = (int)$staff_id;
    $sql = "SELECT 
                p.*, 
                CONCAT(s.fname, ' ', s.lname) AS staff_name,
                'midterm' as approval_type,
                p.midterm_supervisor_1 as supervisor_id,
                p.midterm_supervisor_2 as supervisor2_id,
                -- Midterm Supervisor 1 last action
                (
                    SELECT a1.action 
                    FROM ppa_approval_trail_midterm a1
                    WHERE a1.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci 
                    AND a1.staff_id = p.midterm_supervisor_1
                    ORDER BY a1.id DESC LIMIT 1
                ) AS supervisor1_action,
                -- Midterm Supervisor 2 last action
                (
                    SELECT a2.action 
                    FROM ppa_approval_trail_midterm a2
                    WHERE a2.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci 
                    AND a2.staff_id = p.midterm_supervisor_2
                    ORDER BY a2.id DESC LIMIT 1
                ) AS supervisor2_action,
                -- Compute overall status
                CASE 
                    WHEN p.midterm_supervisor_2 IS NULL AND
                        COALESCE((
                            SELECT a1.action 
                            FROM ppa_approval_trail_midterm a1 
                            WHERE a1.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci 
                            AND a1.staff_id = p.midterm_supervisor_1 
                            ORDER BY a1.id DESC LIMIT 1
                        ), '') = 'Approved'
                        -- AND there's no 'Returned' action after the approval
                        AND NOT EXISTS (
                            SELECT 1 
                            FROM ppa_approval_trail_midterm a_returned 
                            WHERE a_returned.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci 
                            AND a_returned.action = 'Returned'
                            AND a_returned.id > (
                                SELECT MAX(a_approved.id) 
                                FROM ppa_approval_trail_midterm a_approved 
                                WHERE a_approved.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci 
                                AND a_approved.staff_id = p.midterm_supervisor_1 
                                AND a_approved.action = 'Approved'
                            )
                        )
                    THEN 'Approved'
                    WHEN p.midterm_supervisor_2 IS NOT NULL AND
                        COALESCE((
                            SELECT a1.action 
                            FROM ppa_approval_trail_midterm a1 
                            WHERE a1.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci 
                            AND a1.staff_id = p.midterm_supervisor_1 
                            ORDER BY a1.id DESC LIMIT 1
                        ), '') = 'Approved' AND
                        COALESCE((
                            SELECT a2.action 
                            FROM ppa_approval_trail_midterm a2 
                            WHERE a2.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci 
                            AND a2.staff_id = p.midterm_supervisor_2 
                            ORDER BY a2.id DESC LIMIT 1
                        ), '') = 'Approved'
                        -- AND there's no 'Returned' action after either approval
                        AND NOT EXISTS (
                            SELECT 1 
                            FROM ppa_approval_trail_midterm a_returned 
                            WHERE a_returned.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci 
                            AND a_returned.action = 'Returned'
                            AND (
                                a_returned.id > (
                                    SELECT MAX(a_approved.id) 
                                    FROM ppa_approval_trail_midterm a_approved 
                                    WHERE a_approved.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci 
                                    AND a_approved.staff_id = p.midterm_supervisor_1 
                                    AND a_approved.action = 'Approved'
                                )
                                OR
                                a_returned.id > (
                                    SELECT MAX(a_approved.id) 
                                    FROM ppa_approval_trail_midterm a_approved 
                                    WHERE a_approved.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci 
                                    AND a_approved.staff_id = p.midterm_supervisor_2 
                                    AND a_approved.action = 'Approved'
                                )
                            )
                        )
                    THEN 'Approved'
                    WHEN p.midterm_draft_status = 1 AND COALESCE((
                        SELECT a2.action 
                        FROM ppa_approval_trail_midterm a2 
                        WHERE a2.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci 
                        AND a2.staff_id = p.midterm_supervisor_2 
                        ORDER BY a2.id DESC LIMIT 1
                    ), '') = 'Returned'
                    THEN 'Returned'
                    WHEN p.midterm_draft_status = 1 AND COALESCE((
                        SELECT a1.action 
                        FROM ppa_approval_trail_midterm a1 
                        WHERE a1.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci 
                        AND a1.staff_id = p.midterm_supervisor_1 
                        ORDER BY a1.id DESC LIMIT 1
                    ), '') = 'Returned'
                    THEN 'Returned'
                    WHEN p.midterm_supervisor_2 IS NOT NULL AND
                        COALESCE((
                            SELECT a1.action 
                            FROM ppa_approval_trail_midterm a1 
                            WHERE a1.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci 
                            AND a1.staff_id = p.midterm_supervisor_1 
                            ORDER BY a1.id DESC LIMIT 1
                        ), '') = 'Approved'
                        -- AND there's no 'Returned' action after the approval
                        AND NOT EXISTS (
                            SELECT 1 
                            FROM ppa_approval_trail_midterm a_returned 
                            WHERE a_returned.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci 
                            AND a_returned.action = 'Returned'
                            AND a_returned.id > (
                                SELECT MAX(a_approved.id) 
                                FROM ppa_approval_trail_midterm a_approved 
                                WHERE a_approved.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci 
                                AND a_approved.staff_id = p.midterm_supervisor_1 
                                AND a_approved.action = 'Approved'
                            )
                        )
                    THEN 'Pending Second Supervisor'
                    ELSE 'Pending First Supervisor'
                END AS overall_status
            FROM ppa_entries p
            JOIN staff s ON s.staff_id = p.staff_id
            WHERE
              p.midterm_created_at IS NOT NULL
              AND (
                -- First supervisor: must be submitted (draft_status = 0)
                -- Show if entry is submitted and not yet approved by first supervisor
                -- This includes entries that were returned (by anyone) and then resubmitted
                (p.midterm_supervisor_1 = {$staff_id_escaped} AND 
                 p.midterm_draft_status = 0 AND
                 (p.midterm_sign_off = 1 OR p.midterm_sign_off IS NULL) AND
                 (
                   -- Check if most recent action by first supervisor is NOT 'Approved'
                   -- This handles: NULL (never approved), 'Returned' (first supervisor returned it)
                   COALESCE((
                       SELECT a1.action 
                       FROM ppa_approval_trail_midterm a1 
                       WHERE a1.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci 
                       AND a1.staff_id = p.midterm_supervisor_1 
                       ORDER BY a1.id DESC LIMIT 1
                   ), '') != 'Approved'
                   OR
                   -- OR if entry was returned by ANYONE (first or second supervisor, HR officer, etc.) and then resubmitted
                   -- Check if there's a 'Returned' action (by anyone) followed by a 'Submitted' or 'Updated' action
                   EXISTS (
                       SELECT 1 
                       FROM ppa_approval_trail_midterm a_returned 
                       WHERE a_returned.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci 
                       AND a_returned.action = 'Returned'
                       AND EXISTS (
                           SELECT 1 
                           FROM ppa_approval_trail_midterm a_resubmitted 
                           WHERE a_resubmitted.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci 
                           AND a_resubmitted.action IN ('Submitted', 'Updated')
                           AND a_resubmitted.id > a_returned.id
                       )
                   )
                 ))
                OR
                -- Second supervisor: can see if first approved (even if still draft)
                (p.midterm_supervisor_2 = {$staff_id_escaped} AND
                 p.midterm_supervisor_2 IS NOT NULL AND
                 COALESCE((
                     SELECT a1.action 
                     FROM ppa_approval_trail_midterm a1 
                     WHERE a1.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci 
                     AND a1.staff_id = p.midterm_supervisor_1 
                     ORDER BY a1.id DESC LIMIT 1
                 ), '') = 'Approved' AND
                 COALESCE((
                     SELECT a2.action 
                     FROM ppa_approval_trail_midterm a2 
                     WHERE a2.entry_id COLLATE utf8mb4_general_ci = p.entry_id COLLATE utf8mb4_general_ci 
                     AND a2.staff_id = p.midterm_supervisor_2 
                     ORDER BY a2.id DESC LIMIT 1
                 ), '') != 'Approved')
              )
            ORDER BY p.midterm_created_at DESC";
    $pending_midterms = $this->db->query($sql)->result_array();

    // Get pending Endterm approvals using helper functions
    $this->db->select('p.*, CONCAT(s.fname, " ", s.lname) AS staff_name, "endterm" as approval_type, 
                       p.endterm_supervisor_1 as supervisor_id, p.endterm_supervisor_2 as supervisor2_id');
    $this->db->from('ppa_entries p');
    $this->db->join('staff s', 's.staff_id = p.staff_id');
    $this->db->where('p.endterm_created_at IS NOT NULL', null, false);
    
    // Get all endterm entries first
    $all_endterms = $this->db->get()->result();
    
    $pending_endterms = [];
    foreach ($all_endterms as $entry) {
        $can_see = false;
        $status = 'Pending First Supervisor';
        
        // Check if first supervisor can see it
        if (!empty($entry->endterm_supervisor_1) && (int)$entry->endterm_supervisor_1 == (int)$staff_id && 
            $this->_can_first_supervisor_see_endterm($entry->entry_id, $staff_id)) {
            $can_see = true;
        }
        
        // Check if second supervisor can see it
        if (!empty($entry->endterm_supervisor_2) && (int)$entry->endterm_supervisor_2 == (int)$staff_id) {
            $can_second_see = $this->_can_second_supervisor_see_endterm($entry->entry_id, $staff_id);
            log_message('debug', "Second supervisor check: entry_id={$entry->entry_id}, staff_id={$staff_id}, supervisor2={$entry->endterm_supervisor_2}, can_see=" . ($can_second_see ? 'true' : 'false'));
            if ($can_second_see) {
                $can_see = true;
                $status = 'Pending Second Supervisor';
            }
        }
        
        if ($can_see) {
            // Get supervisor actions for status calculation
            $supervisor1_action = $this->_get_endterm_last_action($entry->entry_id, $entry->endterm_supervisor_1);
            $supervisor2_action = $entry->endterm_supervisor_2 ? 
                $this->_get_endterm_last_action($entry->entry_id, $entry->endterm_supervisor_2) : '';
            
            // Calculate overall status
            $status = $this->_calculate_endterm_status($entry, $supervisor1_action, $supervisor2_action);
            
            // Convert to array and add fields
            $entry_array = (array)$entry;
            $entry_array['supervisor1_action'] = $supervisor1_action;
            $entry_array['supervisor2_action'] = $supervisor2_action;
            $entry_array['overall_status'] = $status;
            
            $pending_endterms[] = $entry_array;
        }
    }
    
    // Sort by created_at descending
    usort($pending_endterms, function($a, $b) {
        return strtotime($b['endterm_created_at']) - strtotime($a['endterm_created_at']);
    });

    // Merge and return
    return array_merge($pending_ppas, $pending_midterms, $pending_endterms);
}

public function get_pending_midterm($staff_id)
{
    $sql = "SELECT 
                p.*, 
                CONCAT(s.fname, ' ', s.lname) AS staff_name,
                'midterm' as approval_type,
                (
                    SELECT a1.action 
                    FROM ppa_approval_trail_midterm a1
                    WHERE a1.entry_id = p.entry_id AND a1.staff_id = p.midterm_supervisor_1
                    ORDER BY a1.id DESC LIMIT 1
                ) AS supervisor1_action,
                (
                    SELECT a2.action 
                    FROM ppa_approval_trail_midterm a2
                    WHERE a2.entry_id = p.entry_id AND a2.staff_id = p.midterm_supervisor_2
                    ORDER BY a2.id DESC LIMIT 1
                ) AS supervisor2_action
            FROM ppa_entries p
            JOIN staff s ON s.staff_id = p.staff_id
            WHERE (p.midterm_1_supervisor_id = ? OR p.midterm_supervisor_2 = ?)
              AND p.midterm_draft_status = 0
              -- Add more logic here if you want to filter by approval status
            ORDER BY p.created_at DESC";
    return $this->db->query($sql, [$staff_id, $staff_id])->result_array();
}



}