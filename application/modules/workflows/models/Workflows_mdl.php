<?php

class Workflows_mdl extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
 
    }
   
    public function get_submission($id)
    {
        return $this->db->get_where('submissions', ['id' => $id])->row();
    }

    public function get_next_approver($workflow_id, $submission)
    {
        $conditions = $this->db->get_where('approval_conditions', [
            'workflow_id' => $workflow_id,
            'is_enabled' => 1
        ])->result();

        foreach ($conditions as $cond) {
            $value = $submission->{$cond->column_name};

            if ($this->evaluate_condition($value, $cond->operator, $cond->value)) {
                return $this->get_approver_by_definition($cond->workflow_definition_id);
            }
        }

        return null;
    }

    private function evaluate_condition($value, $operator, $expected)
    {
        switch ($operator) {
            case '=': return $value == $expected;
            case '>': return $value > $expected;
            case '<': return $value < $expected;
            case 'IN': return in_array($value, explode(',', $expected));
            default: return false;
        }
    }

    public function get_approver_by_definition($workflow_definition_id)
    {
        $this->db->select('a.staff_id, s.fname, s.lname, s.work_email');
        $this->db->from('approvers a');
        $this->db->join('staff s', 's.staff_id = a.staff_id');
        $this->db->where('a.workflow_dfn_id', $workflow_definition_id);
        return $this->db->get()->row();
    }

    public function record_approval_step($submission_id, $approver_id, $status)
    {
        $this->db->insert('approval_trail', [
            'submission_id' => $submission_id,
            'approver_id' => $approver_id,
            'status' => $status,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function update_status($submission_id, $approver_id, $status)
    {
        $this->db->where('submission_id', $submission_id);
        $this->db->where('approver_id', $approver_id);
        $this->db->update('approval_trail', ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
    }


}

 
    
  
