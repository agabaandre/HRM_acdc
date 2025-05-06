<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Dashboard extends MX_Controller
{


	public  function __construct()
	{
		parent::__construct();

		$this->dashmodule = "dashboard";
		$this->load->model("dashboard_mdl",'dash_mdl');
	}

	public function index()
	{
		$data['module'] = $this->dashmodule;
		$data['title'] = "Main Dashboard";
		$data['staff'] = $this->dash_mdl->all_staff();
		$data['staff_renewal'] = $this->dash_mdl->staff_renewal();
		$data['two_months'] = $this->dash_mdl->due_contracts();
		$data['expired'] = $this->dash_mdl->expired_contracts();
		$data['member_states'] = $this->dash_mdl->nationalities();
		$data['data_points'] = $this->dash_mdl->staff_by_gender();
		$data['staff_by_member_state'] = $this->dash_mdl->staff_by_member_state();
		$data['staff_by_contract'] = $this->dash_mdl->staff_by_contract();
		$data['staff_by_division'] = $this->dash_mdl->staff_by_division();
		$data['today'] = $this->staff_mdl->getBirthdays(0);
		//dd($data['today']);
		$data['tomorrow'] = $this->staff_mdl->getBirthdays(1);
		$data['week'] = $this->staff_mdl->getBirthdays(7);
		$data['month'] = $this->staff_mdl->getBirthdays(30);
		$data['uptitle'] = "Main Dashboard";
		//dd($this->dash_mdl->get_all());

		render('home', $data);
	}
	public function fetch_messages_ajax() {
		$staff_id = $this->session->userdata('user')->staff_id;
		$this->db->where('staff_id', $staff_id);
		$this->db->order_by('created_at', 'DESC');
		$this->db->limit(5);
		$messages = $this->db->get('email_notifications')->result();
	
		// Format message times
		foreach ($messages as &$message) {
			$message->time_ago = time_ago($message->created_at) . ' ago';
			$message->trigger = ucwords($message->trigger);
		}
	
		echo json_encode($messages);
	}
	
	public function dashboardData()
	{

	}
	public function search_staff()
	{
		$this->load->model('staff_mdl');
		$query = $this->input->post('query');
		$results = $this->dash_mdl->search_staff($query);
	
		$response = [
			'data' => $results,
			'csrfName' => $this->security->get_csrf_token_name(),
			'csrfHash' => $this->security->get_csrf_hash()
		];
	
		echo json_encode($response);
	}
	
public function all_messages() {
    $data['module'] = $this->dashmodule;
    $data['title'] = "Main Dashboard";

    render('all_messages', $data); // Will use views/messages.php
}

public function search_messages() {
    $staff_id = $this->session->userdata('user')->staff_id;
    $query = $this->input->get('query');

    $this->db->where('staff_id', $staff_id);

    if (!empty($query)) {
        $this->db->group_start()
            ->like('subject', $query)
            ->or_like('body', $query)
            ->or_like('trigger', $query)
            ->group_end();
    }

    $this->db->order_by('created_at', 'DESC');
    $messages = $this->db->get('email_notifications')->result();

    foreach ($messages as &$message) {
        $message->time_ago = time_ago($message->created_at);
        $message->trigger = ucwords($message->trigger);
    }
	//dd($messages);

    echo json_encode($messages);
}

}
