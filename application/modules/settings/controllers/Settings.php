<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Settings extends MX_Controller
{


	public  function __construct()
	{
		parent::__construct();

		$this->module = "settings";
		$this->load->model("settings_mdl", 'settings_mdl');
	}

	public function index()
	{
		$data['module'] = $this->module;
		$data['title'] = "Settings";
		render('settings', $data);
	}

	// Add Content to the Database
	public function add_content()
	{
		$this->load->model('settings_mdl');

		$table = $this->input->post('table');
		// $redirect = $this->input->post('redirect');

		$this->settings_mdl->add_content($table);
		redirect('settings/'.$table);
		
	}

	// Update Content to the Database
	public function update_content()
	{
		$this->load->model('settings_mdl');

		$table = $this->input->post('table');
		$column_name = $this->input->post('column_name');
		$caller_value = $this->input->post('caller_value');
		$redirect = $this->input->post('redirect');

		// Debug logging
		log_message('debug', 'Update content called for table: ' . $table);
		log_message('debug', 'Column name: ' . $column_name . ', Caller value: ' . $caller_value);
		log_message('debug', 'Redirect field: ' . $redirect);

		$res = $this->settings_mdl->update_content($table, $column_name, $caller_value);
		$message = "Updated";

		if ($res) {
			$msg = array(
				'msg' => 'Successfully ' . $message,
				'type' => 'success'
			);
			Modules::run('utility/setFlash', $msg);
			
			// Use redirect field if provided, otherwise use table
			$redirect_url = $redirect ? 'settings/' . $redirect : 'settings/' . $table;
			log_message('debug', 'Update successful, redirecting to ' . $redirect_url);
			redirect($redirect_url);
		} else {
			$msg = array(
				'msg' => 'Failed to update division',
				'type' => 'error'
			);
			Modules::run('utility/setFlash', $msg);
			log_message('error', 'Update failed for table: ' . $table);
		}

		// Use redirect field if provided, otherwise use table
		$redirect_url = $redirect ? 'settings/' . $redirect : 'settings/' . $table;
		redirect($redirect_url);
		
	}

	// Delete Content
	public function delete_content()
	{
		$this->load->model('settings_mdl');

		$table = $this->input->post('table');
		$column_name = $this->input->post('column_name');
		$caller_value = $this->input->post('caller_value');
		// $redirect = $this->input->post('redirect');

		$res = $this->settings_mdl->delete_content($table, $column_name, $caller_value);
		$message = "Deleted";

		if ($res) {
			$msg = array(
				'msg' => 'Successfully ' . $message,
				'type' => 'success'
			);
			Modules::run('utility/setFlash', $msg);
			redirect('settings/' . $table);
		} else {
			$msg = array(
				'msg' => 'Failed',
				'type' => 'error'
			);
		}

		redirect('settings/'.$table);
		
	}

	
	public function duty_stations()
	{
		$this->load->model('settings_mdl');
		$data['countries'] = $this->settings_mdl->get_content('nationalities');
		$data['duties'] = $this->settings_mdl->get_content('duty_stations');

		$data['module'] = $this->module;
		$data['title'] = "Duty Stations";
		render('duty_stations', $data);
	}
	public function nationalities()
	{
		
		$data['nationalities'] = $this->settings_mdl->get_content('nationalities');

		$data['module'] = $this->module;
		$data['title'] = "Nationalities";
		render('nationalities', $data);
	}

	public function contracting_institutions()
	{

		$this->load->model('settings_mdl');
		$data['institutions'] = $this->settings_mdl->get_content('contracting_institutions');

		$data['module'] = $this->module;
		$data['title'] = "Contracting Institutions";
		render('contracting_institutions', $data);
	}

	public function units()
	{

		$this->load->model('settings_mdl');
		$data['units'] = $this->settings_mdl->get_content('units');

		$data['module'] = $this->module;
		$data['title'] = "Units";
		render('units', $data);
	}

	public function contract_types()
	{
		$this->load->model('settings_mdl');
		$data['contract_types'] = $this->settings_mdl->get_content('contract_types');

		$data['module'] = $this->module;
		$data['title'] = "Contract Types";
		render('contract_types', $data);
	}

	public function divisions()
	{
		$this->load->model('settings_mdl');
		
		$data['module'] = $this->module;
		$data['title'] = "Divisions";
		render('divisions', $data);
	}

	public function kin_relationship_types()
	{
		$this->load->model('settings_mdl');
		$data['kin_relationship_types'] = $this->db->table_exists('kin_relationship_types')
			? $this->settings_mdl->get_content('kin_relationship_types')
			: null;

		$data['module'] = $this->module;
		$data['title'] = "Next of kin relationships";
		render('kin_relationship_types', $data);
	}

	public function divisions_datatables()
	{
		$this->load->model('settings_mdl');
		
		// DataTables parameters
		$draw = intval($this->input->post("draw"));
		$start = intval($this->input->post("start"));
		$length = intval($this->input->post("length"));
		$search_value = $this->input->post("search")["value"];
		$order_column = intval($this->input->post("order")[0]["column"]);
		$order_dir = $this->input->post("order")[0]["dir"];
		
		// Column mapping for ordering
		$columns = array(
			0 => 'd.division_id',
			1 => 'd.division_name',
			2 => 'd.division_short_name',
			3 => 'd.category',
			4 => 'dh.fname',
			5 => 'fp.fname',
			6 => 'fo.fname',
			7 => 'fa.fname'
		);
		
		$order_by = isset($columns[$order_column]) ? $columns[$order_column] : 'd.division_name';
		
		// Get data
		$data = $this->settings_mdl->get_divisions_datatables($start, $length, $search_value, $order_by, $order_dir);
		$total_records = $this->settings_mdl->get_divisions_count();
		$filtered_records = $this->settings_mdl->get_divisions_count($search_value);
		
		// Prepare response
		$response = array(
			"draw" => $draw,
			"recordsTotal" => $total_records,
			"recordsFiltered" => $filtered_records,
			"data" => $data
		);
		
		$this->output
			->set_content_type('application/json')
			->set_output(json_encode($response));
	}
	public function directorates()
	{
		$this->load->model('settings_mdl');
	
		$data['directorates'] = $this->settings_mdl->get_content('directorates');

	
		// Additional metadata
		$data['module'] = $this->module;
		$data['title'] = "Directorates";
	
		render('directorates', $data);
	}
	
	public function grades()
	{
		$this->load->model('settings_mdl');
		$data['grades'] = $this->settings_mdl->get_content('grades');

		$data['module'] = $this->module;
		$data['title'] = "Grades";
		render('grades', $data);
	}

	public function jobs()
	{
		$this->load->model('settings_mdl');
		$data['jobs'] = $this->settings_mdl->get_content('jobs');

		$data['module'] = $this->module;
		$data['title'] = "Jobs";
		render('jobs', $data);
	}

	public function jobs_acting()
	{
		$this->load->model('settings_mdl');
		$data['jobs_acting'] = $this->settings_mdl->get_content('jobs_acting');

		$data['module'] = $this->module;
		$data['title'] = "Jobs Acting";
		render('jobs_acting', $data);
	}

	public function au_values()
	{
		$this->load->model('settings_mdl');
		$data['au_values'] = $this->settings_mdl->get_content('au_values');

		$data['module'] = $this->module;
		$data['title'] = "AU Values";
		render('au_values', $data);
	}

	public function funders()
	{
		$this->load->model('settings_mdl');
		$data['funders'] = $this->settings_mdl->get_content('funders');

		$data['module'] = $this->module;
		$data['title'] = "Funders";
		render('funders', $data);
	}

	public function contract_status()
	{
		$data['module'] = $this->module;
		$data['title'] = "Contract Status";
		render('contract_status', $data);
	}

	public function leave_types()
	{
		$this->load->model('settings_mdl');
		$data['leaves'] = $this->settings_mdl->get_content('leave_types');

		$data['module'] = $this->module;
		$data['title'] = "Leave Types";
		render('leave_types', $data);
	}

	public function member_states()
	{
		$data['module'] = $this->module;
		$data['title'] = "Member States";
		render('member_states', $data);
	}

	public function training_skills()
	{
		$this->load->model('settings_mdl');
		$data['skills'] = $this->settings_mdl->get_content('training_skills');

		$data['module'] = $this->module;
		$data['title'] = "Training Skills";
		render('training_skills', $data);
	}

	public function regions()
	{
		$this->load->model('settings_mdl');
		$data['regions'] = $this->settings_mdl->get_content('regions');

		$data['module'] = $this->module;
		$data['title'] = "Regions";
		render('regions', $data);
	}
public function sysvariables()
{
	$data['title'] = "Settings - Constants & Variables";
	$data['uptitle'] = "Constants & Variables";
	$data['module'] = 'settings';
	$data['view'] = "sys_variables";
	$postdata = $this->input->post();
	$data['setting'] = $this->settings_mdl->getSettings();

	if ($this->input->method() === 'post' && $this->input->post('language')) {
		unset($postdata['africacdc_csrf_cookie']);
		//dd($postdata);
		$res = $this->settings_mdl->update_variables($postdata);

		//dd($data);

		if ($this->input->is_ajax_request()) {
			// Respond to AJAX
			return $this->output
				->set_content_type('application/json')
				->set_output(json_encode([
					'status' => $res ? 'success' : 'error',
					'message' => $res ? 'Successfully Saved' : 'Failed to save'
				]));
		} else {
			// Non-AJAX fallback
			$msg = array(
				'msg' => $res ? 'Successfully Saved' : 'Failed',
				'type' => $res ? 'success' : 'error'
			);
			Modules::run('utility/setFlash', $msg);
			redirect('settings/sysvariables');
		}
	} else {
		echo Modules::run('templates/main', $data);
	}
}


public function ppa_variables()
{
    $data['title'] = "PPA Configuration";
    $data['uptitle'] = "PPA Configuration";
    $data['module'] = 'settings';
    $data['view'] = "ppa_variables";

    $postdata = $this->input->post();

   
    unset($postdata['africacdc_csrf_cookie'], $postdata['africacdc_csrf_token']);

    $data['setting'] = $this->settings_mdl->get_ppa();

    if ($this->input->post()) {
        $res = $this->settings_mdl->update_ppa_variables($postdata);
        if ($res) {
            $msg = ['msg' => 'Successfully Saved', 'type' => 'success'];
        } else {
            $msg = ['msg' => 'Failed', 'type' => 'error'];
        }
        Modules::run('utility/setFlash', $msg);
        redirect('settings/ppa_variables');
    } else {
        echo Modules::run('templates/main', $data);
    }
}

/**
 * Generate short names for existing divisions - Preview page
 */
public function generate_division_short_names() {
    $this->load->model('settings_mdl');
    
    // Check if column exists
    $columns = $this->db->list_fields('divisions');
    $hasShortNameColumn = in_array('division_short_name', $columns);
    
    if (!$hasShortNameColumn) {
        $msg = array(
            'msg' => 'Column division_short_name does not exist in divisions table',
            'type' => 'error'
        );
        Modules::run('utility/setFlash', $msg);
        redirect('settings/divisions');
    }
    
    // Get divisions without short names
    $divisions_without_short_names = $this->settings_mdl->getDivisionsWithoutShortNames();
    
    $data = array(
        'module' => $this->module,
        'title' => 'Generate Division Short Names',
        'has_short_name_column' => $hasShortNameColumn,
        'divisions_without_short_names' => $divisions_without_short_names,
        'total_divisions' => count($divisions_without_short_names)
    );
    
    render('generate_short_names', $data);
}

/**
 * Preview short names before generating
 */
public function preview_short_names() {
    $this->load->model('settings_mdl');
    $divisions = $this->settings_mdl->getDivisionsWithoutShortNames();
    
    $preview = array();
    foreach ($divisions as $division) {
        $shortName = $this->settings_mdl->generateShortCodeFromDivision($division->division_name);
        
        // Ensure short name is not empty
        if (empty($shortName)) {
            $shortName = 'DIV' . $division->division_id;
        }
        
        $preview[] = array(
            'id' => $division->division_id,
            'name' => $division->division_name,
            'proposed_short_name' => $shortName
        );
    }
    
    header('Content-Type: application/json');
    echo json_encode($preview);
}

/**
 * Debug function to check database structure and data
 */
public function debug_divisions() {
    $this->load->model('settings_mdl');
    
    // Check if column exists
    $columns = $this->db->list_fields('divisions');
    $hasShortNameColumn = in_array('division_short_name', $columns);
    
    // Get sample data
    $this->db->select('division_id, division_name, division_short_name');
    $this->db->limit(5);
    $sampleData = $this->db->get('divisions')->result();
    
    echo "<h3>Debug Information</h3>";
    echo "<p><strong>Column exists:</strong> " . ($hasShortNameColumn ? 'YES' : 'NO') . "</p>";
    echo "<p><strong>All columns:</strong> " . implode(', ', $columns) . "</p>";
    echo "<h4>Sample Data:</h4>";
    echo "<pre>";
    print_r($sampleData);
    echo "</pre>";
    
    // Test the generation function
    if (!empty($sampleData)) {
        echo "<h4>Test Generation:</h4>";
        foreach ($sampleData as $division) {
            $shortName = $this->settings_mdl->generateShortCodeFromDivision($division->division_name);
            echo "<p><strong>{$division->division_name}</strong> → <strong>{$shortName}</strong></p>";
        }
    }
}

/**
 * Simple test function to manually update one division
 */
public function test_update() {
    $this->load->model('settings_mdl');
    
    // Get the first division
    $this->db->select('division_id, division_name, division_short_name');
    $this->db->limit(1);
    $division = $this->db->get('divisions')->row();
    
    if ($division) {
        echo "<h3>Testing Update for Division: {$division->division_name}</h3>";
        
        // Generate short name
        $shortName = $this->settings_mdl->generateShortCodeFromDivision($division->division_name);
        if (empty($shortName)) {
            $shortName = 'DIV' . $division->division_id;
        }
        
        echo "<p><strong>Current short name:</strong> " . ($division->division_short_name ?: 'NULL') . "</p>";
        echo "<p><strong>Proposed short name:</strong> {$shortName}</p>";
        
        // Try to update
        $this->db->where('division_id', $division->division_id);
        $result = $this->db->update('divisions', array('division_short_name' => $shortName));
        
        echo "<p><strong>Update result:</strong> " . ($result ? 'SUCCESS' : 'FAILED') . "</p>";
        echo "<p><strong>Last query:</strong> " . $this->db->last_query() . "</p>";
        
        if (!$result) {
            $error = $this->db->error();
            echo "<p><strong>Database error:</strong> " . $error['message'] . "</p>";
        }
        
        // Check the result
        $this->db->select('division_id, division_name, division_short_name');
        $this->db->where('division_id', $division->division_id);
        $updated = $this->db->get('divisions')->row();
        
        echo "<p><strong>After update:</strong> " . ($updated->division_short_name ?: 'NULL') . "</p>";
    } else {
        echo "<p>No divisions found!</p>";
    }
}


/**
 * Force generate short names - direct method without form
 */
public function force_generate_short_names() {
    $this->load->model('settings_mdl');
    
    // Generate short names
    $result = $this->settings_mdl->updateDivisionsWithShortNames();
    
    // Set flash message with detailed results
    $message = "Processed {$result['total_processed']} divisions. ";
    $message .= "Updated: {$result['updated']}, Errors: {$result['errors']}";
    
    if ($result['errors'] > 0) {
        $message .= "<br><strong>Errors:</strong><br>";
        foreach ($result['results'] as $item) {
            if ($item['status'] == 'error') {
                $message .= "Division {$item['id']} ({$item['name']}): {$item['error']}<br>";
            }
        }
    }
    
    // Also show successful updates
    if ($result['updated'] > 0) {
        $message .= "<br><strong>Successfully Updated:</strong><br>";
        foreach ($result['results'] as $item) {
            if ($item['status'] == 'success') {
                $message .= "Division {$item['id']} ({$item['name']}): {$item['short_name']}<br>";
            }
        }
    }
    
    Modules::run('utility/setFlash', $message, $result['errors'] > 0 ? 'error' : 'success');
    redirect('settings/divisions');
}

	public function cbp_modules()
	{
		$this->load->model('cbp_modules_mdl');
		$data['module'] = $this->module;
		$data['title'] = 'CBP modules';
		$data['table_exists'] = $this->cbp_modules_mdl->table_exists();
		$data['modules'] = $data['table_exists'] ? $this->cbp_modules_mdl->get_all_ordered() : [];
		$data['next_sort_order'] = $data['table_exists'] ? $this->cbp_modules_mdl->next_sort_order() : 100;
		$data['icon_options'] = $this->cbp_fa_icon_options();
		$data['resolver_options'] = Cbp_modules_mdl::target_resolver_labels();
		$data['next_permission_id_hint'] = $data['table_exists'] ? $this->cbp_modules_mdl->next_permission_id_hint() : 1;
		render('cbp_modules', $data);
	}

	public function cbp_modules_save()
	{
		if ($this->input->method() !== 'post') {
			show_404();
			return;
		}
		$this->load->model('cbp_modules_mdl');
		$id = (int) $this->input->post('id');
		if ($id < 1 || !$this->cbp_modules_mdl->table_exists()) {
			$msg = ['msg' => 'Invalid request or the cbp_modules table is missing. Run the SQL migration.', 'type' => 'error'];
			Modules::run('utility/setFlash', $msg);
			redirect('settings/cbp_modules');
			return;
		}
		$post = $this->input->post();
		$validation = $this->cbp_modules_mdl->validate_target_configuration($post);
		if ($validation !== null) {
			$msg = ['msg' => $validation, 'type' => 'error'];
			Modules::run('utility/setFlash', $msg);
			redirect('settings/cbp_modules');
			return;
		}
		$res = $this->cbp_modules_mdl->update_module($id, $post);
		if ($res) {
			$this->cbp_modules_mdl->ensure_module_permission_assigned_to_admin($id);
		}
		$msg = [
			'msg' => $res ? 'Module saved.' : 'Could not save module.',
			'type' => $res ? 'success' : 'error',
		];
		Modules::run('utility/setFlash', $msg);
		redirect('settings/cbp_modules');
	}

	public function cbp_modules_create()
	{
		if ($this->input->method() !== 'post') {
			show_404();
			return;
		}
		$this->load->model('cbp_modules_mdl');
		if (!$this->cbp_modules_mdl->table_exists()) {
			$msg = ['msg' => 'The cbp_modules table is missing. Run the SQL migration.', 'type' => 'error'];
			Modules::run('utility/setFlash', $msg);
			redirect('settings/cbp_modules');
			return;
		}
		$post = $this->input->post();
		$result = $this->cbp_modules_mdl->insert_module($post);
		$msg = [
			'msg' => $result['message'],
			'type' => $result['ok'] ? 'success' : 'error',
		];
		Modules::run('utility/setFlash', $msg);
		redirect('settings/cbp_modules');
	}

	private function cbp_fa_icon_options(): array
	{
		return [
			'fa-th' => 'Default grid',
			'fa-users' => 'Users',
			'fa-user' => 'User',
			'fa-sitemap' => 'Sitemap',
			'fa-wallet' => 'Wallet',
			'fa-chart-line' => 'Chart line',
			'fa-building' => 'Building',
			'fa-briefcase' => 'Briefcase',
			'fa-cogs' => 'Cogs',
			'fa-th-large' => 'Grid',
			'fa-file-alt' => 'File',
			'fa-globe' => 'Globe',
			'fa-hand-holding-usd' => 'Hand holding USD',
			'fa-shield-alt' => 'Shield',
			'fa-tachometer-alt' => 'Dashboard',
			'fa-project-diagram' => 'Project diagram',
			'fa-envelope' => 'Envelope',
			'fa-key' => 'Key',
			'fa-external-link-alt' => 'External link',
		];
	}

	/** Same permission as Settings menu (nav permission id 15). */
	private function _require_settings_access()
	{
		$user = $this->session->userdata('user');
		if (!$user) {
			show_error('You must be signed in.', 403);
		}
		$perms = isset($user->permissions) ? (array) $user->permissions : [];
		$permStrings = array_map('strval', $perms);
		if (!in_array('15', $permStrings, true)) {
			show_error('You do not have permission to access this page.', 403);
		}
	}

	/**
	 * Jobs that can be triggered once from the browser (maps to jobs/jobs methods).
	 * Add entries here as new jobs are exposed.
	 */
	private function staff_jobs_instant_definitions()
	{
		return [
			'notify_staff_profile_extension' => [
				'label' => 'Profile completion reminder emails',
				'route' => 'jobs/jobs/notify_staff_incomplete_profile_extension',
			],
			'staff_birthday' => [
				'label' => 'Staff birthday',
				'route' => 'jobs/jobs/staff_birthday',
			],
			'mark_due_contracts' => [
				'label' => 'Mark due contracts',
				'route' => 'jobs/jobs/mark_due_contracts',
			],
			'cron_register' => [
				'label' => 'Cron register (bundle)',
				'route' => 'jobs/jobs/cron_register',
			],
			'send_instant_mails' => [
				'label' => 'Instant mail queue (one pass)',
				'route' => 'jobs/jobs/send_instant_mails',
			],
			'send_mails' => [
				'label' => 'Full mail queue (one pass)',
				'route' => 'jobs/jobs/send_mails',
			],
			'manage_accounts' => [
				'label' => 'Manage accounts',
				'route' => 'jobs/jobs/manage_accounts',
			],
			'performance_approval_reminder' => [
				'label' => 'Performance approval reminders',
				'route' => 'jobs/jobs/notify_supervisors_pending_performance_approval',
			],
			'performance_notifications_bundle' => [
				'label' => 'Performance notifications (PPA, Midterm, Endterm)',
				'bundle' => 'performance_notifications',
			],
		];
	}

	private function _staff_jobs_run_performance_notifications_bundle()
	{
		foreach ([
			'jobs/jobs/notify_supervisors_pending_ppas',
			'jobs/jobs/notify_supervisors_pending_midterms',
			'jobs/jobs/notify_supervisors_pending_endterms',
		] as $route) {
			Modules::run($route);
		}
	}

	public function staff_jobs()
	{
		$this->_require_settings_access();
		$this->load->helper('staff_jobs_schedule');
		$data['module'] = $this->module;
		$data['title'] = 'Staff jobs (schedule & run)';
		$data['schedule'] = staff_jobs_schedule_resolved();
		$data['schedule_path'] = staff_jobs_schedule_path();
		$data['daily_jobs_meta'] = [
			'staff_profile_completion_reminder' => [
				'label' => 'Profile completion reminder',
				'help' => 'Email staff (eligible contracts) who are missing extended profile fields.',
			],
			'staff_birthday' => [
				'label' => 'Staff birthday',
				'help' => 'Birthday notifications.',
			],
			'mark_due_contracts' => [
				'label' => 'Mark due contracts',
				'help' => 'Updates contract due status.',
			],
			'performance_notifications' => [
				'label' => 'Performance notifications (PPA / Mid / End)',
				'help' => 'Queues supervisor reminder emails for PPA, midterm, and endterm.',
			],
			'performance_approval_reminder' => [
				'label' => 'Performance approval reminders',
				'help' => 'Pending performance approval reminders to supervisors.',
			],
			'cron_register' => [
				'label' => 'Cron register bundle',
				'help' => 'Legacy bundle (birthday, accounts, contracts).',
			],
		];
		$data['instant_jobs'] = $this->staff_jobs_instant_definitions();
		render('staff_jobs', $data);
	}

	public function staff_jobs_save_schedule()
	{
		$this->_require_settings_access();
		if ($this->input->method() !== 'post') {
			show_404();
			return;
		}
		$this->load->helper('staff_jobs_schedule');
		$payload = staff_jobs_schedule_from_post($this->input->post());
		if (staff_jobs_schedule_write($payload)) {
			Modules::run('utility/setFlash', [
				'msg' => 'Schedule saved. The next cron tick will use these values.',
				'type' => 'success',
			]);
		} else {
			Modules::run('utility/setFlash', [
				'msg' => 'Could not save schedule. Ensure application/cache is writable by the web server.',
				'type' => 'error',
			]);
		}
		redirect('settings/staff_jobs');
	}

	public function staff_jobs_run_now()
	{
		$this->_require_settings_access();
		if ($this->input->method() !== 'post') {
			show_404();
			return;
		}
		$key = (string) $this->input->post('job_key', true);
		$defs = $this->staff_jobs_instant_definitions();
		if ($key === '' || !isset($defs[$key])) {
			Modules::run('utility/setFlash', ['msg' => 'Unknown job.', 'type' => 'error']);
			redirect('settings/staff_jobs');
			return;
		}
		$out = '';
		ob_start();
		try {
			if (!empty($defs[$key]['bundle']) && $defs[$key]['bundle'] === 'performance_notifications') {
				$this->_staff_jobs_run_performance_notifications_bundle();
			} else {
				Modules::run($defs[$key]['route']);
			}
			$out = ob_get_clean();
		} catch (Exception $e) {
			if (ob_get_level() > 0) {
				ob_end_clean();
			}
			log_message('error', 'staff_jobs_run_now: ' . $e->getMessage());
			Modules::run('utility/setFlash', ['msg' => 'Job failed: ' . $e->getMessage(), 'type' => 'error']);
			redirect('settings/staff_jobs');
			return;
		}
		log_message('debug', 'staff_jobs_run_now ' . $key . ': ' . $out);
		Modules::run('utility/setFlash', [
			'msg' => 'Ran: ' . $defs[$key]['label'] . '.',
			'type' => 'success',
		]);
		redirect('settings/staff_jobs');
	}

}
