<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Home dashboard. CBP module cards are driven by table `cbp_modules`:
 * - module_key, system_name, description, base_url, base_url_development, base_url_production
 * - icon_class, permission_code, uses_staff_portal_token, is_production, is_enabled, show_in_apm_menu
 * - alternate_base_url, alternate_for_role_id, target_resolver (codeigniter|staff_app_token|finance_host|external_microservice), sort_order
 * Schema and INSERT defaults: application/sql/create_cbp_modules_table.sql; PHP seed: Cbp_modules_mdl::default_seed_rows().
 */
class Home extends MX_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->module = "home";
	
	}

	public function index()
	{
		$user = $this->current_home_user();
		if ($user === null) {
			redirect('auth/login');
			return;
		}

		$data['module'] = $this->module;
		$data['title'] = "Home";
		$data['cbp_home_modules'] = $this->resolve_cbp_home_modules($user);

		render('home', $data);
	}

	/**
	 * AJAX: module keys visible to the user that match search query (name, description, module key).
	 * GET home/module_search?q=
	 */
	public function module_search()
	{
		$user = $this->current_home_user();
		if ($user === null) {
			return $this->output
				->set_status_header(403)
				->set_content_type('application/json', 'utf-8')
				->set_output(json_encode(['ok' => false, 'message' => 'Unauthorized', 'module_keys' => []]));
		}

		$q = $this->input->get('q', true);
		$q = $q !== null && $q !== false ? strtolower(trim((string) $q)) : '';

		$modules = $this->resolve_cbp_home_modules($user);
		$keys = [];
		foreach ($modules as $mod) {
			$key = isset($mod['module_key']) ? (string) $mod['module_key'] : '';
			if ($key === '') {
				continue;
			}
			if ($q === '') {
				$keys[] = $key;
				continue;
			}
			$hay = strtolower($key . ' ' . ($mod['label'] ?? '') . ' ' . ($mod['desc'] ?? ''));
			if (strpos($hay, $q) !== false) {
				$keys[] = $key;
			}
		}
		$keys = array_values(array_unique($keys));

		return $this->output
			->set_content_type('application/json', 'utf-8')
			->set_output(json_encode(['ok' => true, 'query' => $q, 'module_keys' => $keys]));
	}

	/**
	 * @return object|null Session user, or null if not allowed to view home (session cleared where needed).
	 */
	private function current_home_user(): ?object
	{
		$sessionDriver = $this->config->item('sess_driver');
		$sessionTable = $this->config->item('sess_save_path');
		$cookieName = $this->config->item('sess_cookie_name');
		$sessionId = $this->input->cookie($cookieName);

		if ($sessionDriver === 'database' && !empty($sessionTable)) {
			if (!empty($sessionId)) {
				$sessionExists = $this->db->query("SELECT id FROM `{$sessionTable}` WHERE id = ?", [$sessionId])->row();
				if (empty($sessionExists)) {
					$this->session->sess_destroy();
					setcookie($cookieName, '', time() - 3600, '/');

					return null;
				}
			} else {
				$this->session->sess_destroy();

				return null;
			}
		}

		$user = $this->session->userdata('user');
		if (empty($user) || !isset($user->staff_id) || empty($user->staff_id)) {
			$this->session->sess_destroy();
			if (!empty($cookieName)) {
				setcookie($cookieName, '', time() - 3600, '/');
			}

			return null;
		}

		return $user;
	}

	/**
	 * @param object $user Session user
	 * @return list<array{href:string,label:string,icon:string,absolute:bool,desc:string,module_key:string}>
	 */
	private function resolve_cbp_home_modules(object $user): array
	{
		$session = (array) $user;
		$session['base_url'] = base_url();

		if ($this->db->table_exists('cbp_modules')) {
			$this->load->model('cbp_modules_mdl');
			$this->cbp_modules_mdl->seed_defaults_if_empty();
			$cards = $this->cbp_modules_mdl->get_home_cards_for_user($user, $session);
			if (!empty($cards)) {
				return $cards;
			}
		}

		return $this->legacy_cbp_home_modules($user, $session);
	}

	/**
	 * Fallback when cbp_modules table is missing or empty (matches previous home.php behaviour).
	 *
	 * @return list<array{href:string,label:string,icon:string,absolute:bool,desc:string,module_key:string}>
	 */
	private function legacy_cbp_home_modules(object $user, array $session): array
	{
		$permissions = isset($user->permissions) ? (array) $user->permissions : [];
		$settings = [];

		if (in_array('84', $permissions, true) || in_array(84, $permissions, true)) {
			$hrPath = ((int) ($user->role ?? 0) === 17) ? 'auth/profile' : 'dashboard';
			$settings[] = [
				'href' => $hrPath,
				'label' => 'Staff Portal',
				'icon' => 'fa-users',
				'absolute' => false,
				'desc' => 'Manage staff details, contracts, appraisals and access HR services efficiently.',
				'module_key' => 'staff_portal',
			];
		}

		if (in_array('85', $permissions, true) || in_array(85, $permissions, true)) {
			$token = urlencode(base64_encode(json_encode($session)));
			$settings[] = [
				'href' => rtrim(base_url(), '/') . '/apm?token=' . $token,
				'label' => 'Approvals Management (APM)',
				'icon' => 'fa-sitemap',
				'absolute' => true,
				'desc' => 'Tracks submissions, reviews, and approvals for travel matrices, single and special memos, change, DSA and ARF requests.',
				'module_key' => 'approvals_management',
			];
		}

		if (in_array('92', $permissions, true) || in_array(92, $permissions, true)) {
			$token = urlencode(base64_encode(json_encode($session)));
			$host = $_SERVER['HTTP_HOST'] ?? '';
			if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
				$financeUrl = 'http://localhost:3002?token=' . $token;
			} else {
				$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
				$financeUrl = $scheme . '://' . $host . '/finance?token=' . $token;
			}
			$settings[] = [
				'href' => $financeUrl,
				'label' => 'Finance Management',
				'icon' => 'fa-wallet',
				'absolute' => true,
				'desc' => 'Manage financial reports, invoices, budgets, transactions, and vendor information.',
				'module_key' => 'finance_management',
			];
		}

		return $settings;
	}
}
