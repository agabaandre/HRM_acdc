<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Dashboard extends MX_Controller
{
    protected $dashmodule;

	public  function __construct()
	{
		parent::__construct();
        $this->db->query('SET SESSION sql_mode = ""');
		$this->dashmodule = "dashboard";
		$this->load->model("dashboard_mdl",'dash_mdl');
	}

	public function index()
	{
		$data['module'] = $this->dashmodule;
		$data['title'] = "Main Dashboard";
		$data['uptitle'] = "Main Dashboard";
		
		// Load filter options
		$data['divisions'] = cache_list('divisions', function () {
			return $this->db->order_by('division_name')->get('divisions')->result();
		}, 120);
		$data['duty_stations'] = cache_list('duty_stations', function () {
			return $this->db->order_by('duty_station_name')->get('duty_stations')->result();
		}, 120);
		$data['funders'] = cache_list('funders', function () {
			return $this->db->order_by('funder')->get('funders')->result();
		}, 120);
		$data['jobs'] = cache_list('jobs', function () {
			return $this->db->order_by('job_name')->get('jobs')->result();
		}, 120);

		// Pass permissions to view for dashboard tabs
		$user = $this->session->userdata('user');
		$data['permissions'] = isset($user->permissions) ? $user->permissions : [];

		render('home', $data);
	}
	
	public function fetch_dashboard_data()
	{
		$division_id = $this->input->get('division_id');
		$duty_station_id = $this->input->get('duty_station_id');
		$funder_id = $this->input->get('funder_id');
		$job_id = $this->input->get('job_id');
		
		$data = $this->dash_mdl->get_dashboard_data($division_id, $duty_station_id, $funder_id, $job_id);
		
		header('Content-Type: application/json');
		echo json_encode($data);
	}
	
	public function get_birthday_events()
	{
		// Clear any existing output
		if (ob_get_level()) {
			ob_end_clean();
		}
		ob_start();

		try {
			$division_id = $this->input->get('division_id');
			$duty_station_id = $this->input->get('duty_station_id');
			$funder_id = $this->input->get('funder_id');
			$job_id = $this->input->get('job_id');
			$start = $this->input->get('start');
			$end = $this->input->get('end');
			
			// Convert empty strings to null
			$division_id = (!empty($division_id)) ? $division_id : null;
			$duty_station_id = (!empty($duty_station_id)) ? $duty_station_id : null;
			$funder_id = (!empty($funder_id)) ? $funder_id : null;
			$job_id = (!empty($job_id)) ? $job_id : null;
			$start = (!empty($start)) ? $start : null;
			$end = (!empty($end)) ? $end : null;
			
			$events = $this->dash_mdl->get_birthday_events($division_id, $duty_station_id, $funder_id, $job_id, $start, $end);
			
			$this->output
				->set_content_type('application/json; charset=utf-8')
				->set_output(json_encode($events, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		} catch (Exception $e) {
			log_message('error', 'Error in get_birthday_events: ' . $e->getMessage());
			log_message('error', 'Stack trace: ' . $e->getTraceAsString());
			$this->output
				->set_status_header(500)
				->set_content_type('application/json; charset=utf-8')
				->set_output(json_encode(['error' => 'Failed to load birthday events', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		} finally {
			// Ensure output buffer is cleaned even if an error occurs
			while (ob_get_level()) {
				ob_end_clean();
			}
		}
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
