<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Aleave extends MX_Controller
{


	public  function __construct()
	{
		parent::__construct();

		$this->module = "Aleave";
		$this->load->model("Aleave_mdl", 'aleave_mdl');
		
	}

	public function annual_statements()
    {
        $data['title'] = 'Annual Leave Statements';
		$data['module'] = $this->module;
        $data['staff_list'] = $this->staff_model->get_all_active_staff();
    
        $data['statements'] = $this->aleave_mdl->get_all_leave_statements();
       render('leave/annual_statement', $data);
    }

    public function add_annual_transaction()
    {
        if ($_POST) {
            $staff_id = $this->input->post('staff_id');
          
            $transaction = $this->input->post('leave_transaction');
            $days = $this->input->post('days');

            $insert = [
                'staff_id' => $staff_id,
                'staff_contract_id' => $this->auth_mdl->contract_info($staff_id),
                'leave_transaction' => $transaction,
                'days' => $days,
                'created_by' => $this->session->userdata('user')->staff_id,
                'updated_by' => $this->session->userdata('user')->staff_id
            ];

            $this->db->insert('staff_leave_annual_statement', $insert);

            $this->session->set_flashdata('msg', 'Leave transaction added.');
        }

        redirect('leave/annual_statements');
    }


	
}
