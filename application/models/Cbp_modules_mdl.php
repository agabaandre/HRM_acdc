<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Central Business Platform modules (home dashboard + APM menu).
 *
 * @see application/sql/create_cbp_modules_table.sql
 */
class Cbp_modules_mdl extends CI_Model
{
	protected $table = 'cbp_modules';

	/** @var list<string> */
	public const TARGET_RESOLVERS = ['codeigniter', 'staff_app_token', 'finance_host', 'external_microservice'];

	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Labels for settings UI (link target dropdown).
	 *
	 * @return array<string, string>
	 */
	public static function target_resolver_labels(): array
	{
		return [
			'codeigniter' => 'Staff portal — internal path (no token)',
			'staff_app_token' => 'Staff host — path with session token (like APM)',
			'finance_host' => 'Finance app — dev / prod host rules',
			'external_microservice' => 'External microservice — different server (HTTPS URL)',
		];
	}

	public static function is_allowed_resolver(string $resolver): bool
	{
		return in_array($resolver, self::TARGET_RESOLVERS, true);
	}

	/**
	 * Validate POST data for create/update; returns error message or null.
	 */
	public function validate_target_configuration(array $data): ?string
	{
		$resolver = isset($data['target_resolver']) ? (string) $data['target_resolver'] : 'codeigniter';
		if (!self::is_allowed_resolver($resolver)) {
			return 'Invalid link target. Choose a valid option from the list.';
		}
		if ($resolver === 'staff_app_token' && trim((string) ($data['base_url'] ?? '')) === '') {
			return '“Staff host + token” requires a path segment under the Staff app (e.g. apm).';
		}
		if ($resolver === 'external_microservice' && !$this->external_microservice_has_any_url($data)) {
			return 'External microservice: provide a development URL, production URL, or a single URL for all environments.';
		}
		if ($resolver === 'codeigniter' && trim((string) ($data['base_url'] ?? '')) === ''
			&& trim((string) ($data['alternate_base_url'] ?? '')) === '') {
			return 'Staff portal path: set “Base path” or an alternate path with role ID.';
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function external_microservice_has_any_url(array $data): bool
	{
		foreach (['base_url_development', 'base_url_production', 'base_url'] as $k) {
			if (trim((string) ($data[$k] ?? '')) !== '') {
				return true;
			}
		}

		return false;
	}

	public function table_exists(): bool
	{
		return $this->db->table_exists($this->table);
	}

	public function count_all(): int
	{
		return (int) $this->db->count_all($this->table);
	}

	/**
	 * Seed default rows when the table exists but is empty (uses Home::cbp_module_default_seed_rows).
	 */
	public function seed_defaults_if_empty(): void
	{
		if (!$this->table_exists() || $this->count_all() > 0) {
			return;
		}
		foreach (self::default_seed_rows() as $row) {
			$this->db->insert($this->table, $row);
		}
	}

	/**
	 * Default rows (mirrors application/sql/create_cbp_modules_table.sql INSERT IGNORE).
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function default_seed_rows(): array
	{
		return [
			[
				'module_key' => 'staff_portal',
				'system_name' => 'Staff Portal',
				'description' => 'Manage staff details, contracts, appraisals and access HR services efficiently.',
				'base_url' => 'dashboard',
				'base_url_development' => null,
				'base_url_production' => null,
				'icon_class' => 'fa-users',
				'permission_code' => '84',
				'uses_staff_portal_token' => 0,
				'is_production' => 1,
				'is_enabled' => 1,
				'show_in_apm_menu' => 1,
				'alternate_base_url' => 'auth/profile',
				'alternate_for_role_id' => 17,
				'target_resolver' => 'codeigniter',
				'sort_order' => 10,
			],
			[
				'module_key' => 'approvals_management',
				'system_name' => 'Approvals Management (APM)',
				'description' => 'Tracks submissions, reviews, and approvals for travel matrices, single and special memos, change, DSA and ARF requests.',
				'base_url' => 'apm',
				'base_url_development' => null,
				'base_url_production' => null,
				'icon_class' => 'fa-sitemap',
				'permission_code' => '85',
				'uses_staff_portal_token' => 1,
				'is_production' => 1,
				'is_enabled' => 1,
				'show_in_apm_menu' => 0,
				'alternate_base_url' => null,
				'alternate_for_role_id' => null,
				'target_resolver' => 'staff_app_token',
				'sort_order' => 20,
			],
			[
				'module_key' => 'finance_management',
				'system_name' => 'Finance Management',
				'description' => 'Manage financial reports, invoices, budgets, transactions, and vendor information.',
				'base_url' => '',
				'base_url_development' => 'http://localhost:3002',
				'base_url_production' => null,
				'icon_class' => 'fa-wallet',
				'permission_code' => '92',
				'uses_staff_portal_token' => 1,
				'is_production' => 1,
				'is_enabled' => 1,
				'show_in_apm_menu' => 1,
				'alternate_base_url' => null,
				'alternate_for_role_id' => null,
				'target_resolver' => 'finance_host',
				'sort_order' => 30,
			],
		];
	}

	public function get_all_ordered(): array
	{
		if (!$this->table_exists()) {
			return [];
		}
		return $this->db->from($this->table)
			->order_by('sort_order', 'ASC')
			->order_by('system_name', 'ASC')
			->get()
			->result();
	}

	public function get_by_id(int $id): ?object
	{
		if (!$this->table_exists()) {
			return null;
		}
		$row = $this->db->get_where($this->table, ['id' => $id])->row();
		return $row ?: null;
	}

	public function module_key_exists(string $module_key): bool
	{
		if (!$this->table_exists() || $module_key === '') {
			return false;
		}
		$n = (int) $this->db->where('module_key', $module_key)->count_all_results($this->table);

		return $n > 0;
	}

	public function next_sort_order(): int
	{
		if (!$this->table_exists()) {
			return 100;
		}
		$row = $this->db->select_max('sort_order', 'mx')->get($this->table)->row();

		return (int) ($row->mx ?? 0) + 10;
	}

	/**
	 * @return array{ok:bool,message:string}
	 */
	public function insert_module(array $data): array
	{
		if (!$this->table_exists()) {
			return ['ok' => false, 'message' => 'The cbp_modules table is missing.'];
		}
		$key = isset($data['module_key']) ? strtolower(trim((string) $data['module_key'])) : '';
		$key = preg_replace('/[^a-z0-9_]+/', '_', $key);
		$key = trim($key, '_');
		if ($key === '' || !preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key)) {
			return ['ok' => false, 'message' => 'Module key is required: start with a letter, then letters, digits, or underscores (max 64 characters).'];
		}
		if ($this->module_key_exists($key)) {
			return ['ok' => false, 'message' => 'That module key is already in use.'];
		}
		$name = isset($data['system_name']) ? trim((string) $data['system_name']) : '';
		if ($name === '') {
			return ['ok' => false, 'message' => 'System name is required.'];
		}
		$perm = isset($data['permission_code']) ? trim((string) $data['permission_code']) : '';
		if ($perm === '') {
			return ['ok' => false, 'message' => 'Permission code is required.'];
		}
		$validation = $this->validate_target_configuration($data);
		if ($validation !== null) {
			return ['ok' => false, 'message' => $validation];
		}
		$resolver = isset($data['target_resolver']) ? (string) $data['target_resolver'] : 'codeigniter';
		if (!self::is_allowed_resolver($resolver)) {
			$resolver = 'codeigniter';
		}
		$icon = isset($data['icon_class']) ? trim((string) $data['icon_class']) : 'fa-th';
		if ($icon === '') {
			$icon = 'fa-th';
		}
		$row = [
			'module_key' => $key,
			'system_name' => function_exists('mb_substr') ? mb_substr($name, 0, 191) : substr($name, 0, 191),
			'description' => isset($data['description']) ? (string) $data['description'] : null,
			'base_url' => isset($data['base_url']) ? (string) $data['base_url'] : '',
			'base_url_development' => $this->nullable_string($data['base_url_development'] ?? null),
			'base_url_production' => $this->nullable_string($data['base_url_production'] ?? null),
			'icon_class' => function_exists('mb_substr') ? mb_substr($icon, 0, 128) : substr($icon, 0, 128),
			'permission_code' => function_exists('mb_substr') ? mb_substr($perm, 0, 32) : substr($perm, 0, 32),
			'uses_staff_portal_token' => !empty($data['uses_staff_portal_token']) ? 1 : 0,
			'is_production' => !empty($data['is_production']) ? 1 : 0,
			'is_enabled' => !empty($data['is_enabled']) ? 1 : 0,
			'show_in_apm_menu' => !empty($data['show_in_apm_menu']) ? 1 : 0,
			'alternate_base_url' => $this->nullable_string($data['alternate_base_url'] ?? null),
			'alternate_for_role_id' => $this->nullable_uint($data['alternate_for_role_id'] ?? null),
			'target_resolver' => $resolver,
			'sort_order' => (isset($data['sort_order']) && $data['sort_order'] !== '') ? (int) $data['sort_order'] : $this->next_sort_order(),
		];
		$ok = (bool) $this->db->insert($this->table, $row);
		if (!$ok) {
			$err = $this->db->error();
			$msg = !empty($err['message']) ? $err['message'] : 'Insert failed.';

			return ['ok' => false, 'message' => $msg];
		}

		return ['ok' => true, 'message' => 'Module created.'];
	}

	private function nullable_string($v): ?string
	{
		if ($v === null || $v === '') {
			return null;
		}
		$s = trim((string) $v);

		return $s === '' ? null : $s;
	}

	private function nullable_uint($v): ?int
	{
		if ($v === null || $v === '') {
			return null;
		}
		$n = (int) $v;

		return $n > 0 ? $n : null;
	}

	public function update_module(int $id, array $data): bool
	{
		if (!$this->table_exists()) {
			return false;
		}
		unset($data['id'], $data['module_key']);
		$allowed = [
			'system_name', 'description', 'base_url', 'base_url_development', 'base_url_production',
			'icon_class', 'permission_code', 'uses_staff_portal_token', 'is_production', 'is_enabled',
			'show_in_apm_menu', 'alternate_base_url', 'alternate_for_role_id', 'target_resolver', 'sort_order',
		];
		$update = array_intersect_key($data, array_flip($allowed));
		foreach (['uses_staff_portal_token', 'is_production', 'is_enabled', 'show_in_apm_menu'] as $b) {
			$update[$b] = !empty($data[$b]) ? 1 : 0;
		}
		if (isset($update['sort_order'])) {
			$update['sort_order'] = (int) $update['sort_order'];
		}
		if (isset($update['alternate_for_role_id']) && $update['alternate_for_role_id'] === '') {
			$update['alternate_for_role_id'] = null;
		}
		if (isset($update['alternate_base_url']) && trim((string) $update['alternate_base_url']) === '') {
			$update['alternate_base_url'] = null;
		}
		$this->db->where('id', $id);
		return (bool) $this->db->update($this->table, $update);
	}

	/**
	 * @param object $user Session user (role, permissions)
	 * @param array $sessionArray (array) $user + base_url for token JSON
	 * @return list<array{href:string,label:string,icon:string,absolute:bool,desc:string,module_key:string}>
	 */
	public function get_home_cards_for_user(object $user, array $sessionArray): array
	{
		if (!$this->table_exists()) {
			return [];
		}
		$rows = $this->db->from($this->table)
			->where('is_enabled', 1)
			->order_by('sort_order', 'ASC')
			->get()
			->result();
		$out = [];
		$permList = isset($user->permissions) ? array_map('strval', (array) $user->permissions) : [];
		$roleId = isset($user->role) ? (int) $user->role : 0;

		foreach ($rows as $row) {
			$perm = (string) $row->permission_code;
			if (!in_array($perm, $permList, true)) {
				continue;
			}
			if ((int) $row->is_production === 0 && $roleId !== 10) {
				continue;
			}
			$resolved = $this->resolve_href($row, $sessionArray, $roleId);
			if ($resolved === null || $resolved === '') {
				continue;
			}
			$absolute = in_array($row->target_resolver, ['staff_app_token', 'finance_host', 'external_microservice'], true)
				|| preg_match('#^https?://#i', $resolved);
			$mkey = trim((string) ($row->module_key ?? ''));
			if ($mkey === '') {
				$mkey = 'cbp_module_' . (int) $row->id;
			}
			$out[] = [
				'href' => $resolved,
				'label' => $row->system_name,
				'icon' => $row->icon_class,
				'absolute' => $absolute,
				'desc' => (string) ($row->description ?? ''),
				'module_key' => $mkey,
			];
		}

		return $out;
	}

	/**
	 * @param object $row cbp_modules row
	 * @param array $sessionArray session user as array + base_url
	 */
	public function resolve_href(object $row, array $sessionArray, int $roleId = 0): ?string
	{
		$resolver = $row->target_resolver;
		if ($resolver === 'codeigniter') {
			$path = $row->base_url;
			if (!empty($row->alternate_for_role_id) && (int) $row->alternate_for_role_id === $roleId && !empty($row->alternate_base_url)) {
				$path = $row->alternate_base_url;
			}
			$path = trim((string) $path, '/');

			return $path === '' ? null : $path;
		}
		if ($resolver === 'staff_app_token') {
			$base = rtrim(base_url(), '/');
			$seg = trim((string) $row->base_url, '/');
			if ($seg === '') {
				return null;
			}
			$url = $base . '/' . $seg;
			if (!empty($row->uses_staff_portal_token)) {
				$url = $this->append_staff_portal_token_to_url($url, $sessionArray);
			}

			return $url;
		}
		if ($resolver === 'finance_host') {
			$host = $_SERVER['HTTP_HOST'] ?? '';
			$isLocal = $this->is_request_local_host();
			$url = '';
			if ($isLocal) {
				$devBase = trim((string) ($row->base_url_development ?? ''), '/');
				if ($devBase === '') {
					$devBase = 'http://localhost:3002';
				}
				if (strpos($devBase, 'http') !== 0) {
					$devBase = 'http://' . $devBase;
				}
				$url = rtrim($devBase, '/');
			} else {
				$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
				$prod = trim((string) ($row->base_url_production ?? ''), '/');
				if ($prod !== '') {
					if (preg_match('#^https?://#i', $prod)) {
						$url = rtrim($prod, '/');
					} else {
						$url = $scheme . '://' . $host . '/' . $prod;
					}
				} else {
					$url = $scheme . '://' . $host . '/finance';
				}
			}
			if (!empty($row->uses_staff_portal_token)) {
				$url = $this->append_staff_portal_token_to_url($url, $sessionArray);
			}

			return $url;
		}
		if ($resolver === 'external_microservice') {
			return $this->resolve_external_microservice_href($row, $sessionArray);
		}

		return null;
	}

	private function is_request_local_host(): bool
	{
		$host = $_SERVER['HTTP_HOST'] ?? '';

		return $host !== '' && (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);
	}

	/**
	 * Appends Staff session token as query parameter (same encoding as APM / Finance).
	 */
	private function append_staff_portal_token_to_url(string $url, array $sessionArray): string
	{
		$token = urlencode(base64_encode(json_encode($sessionArray)));
		$sep = strpos($url, '?') !== false ? '&' : '?';

		return $url . $sep . 'token=' . $token;
	}

	/**
	 * External HTTPS microservice: pick dev vs prod URL from host, optional single base_url fallback.
	 */
	private function resolve_external_microservice_href(object $row, array $sessionArray): ?string
	{
		$isLocal = $this->is_request_local_host();
		$url = '';
		if ($isLocal) {
			$url = trim((string) ($row->base_url_development ?? ''));
			if ($url === '') {
				$url = trim((string) ($row->base_url ?? ''));
			}
		} else {
			$url = trim((string) ($row->base_url_production ?? ''));
			if ($url === '') {
				$url = trim((string) ($row->base_url ?? ''));
			}
		}
		if ($url === '') {
			return null;
		}
		if (!preg_match('#^https?://#i', $url)) {
			$url = 'https://' . ltrim(preg_replace('#^[\\/]+#', '', $url), '/');
		}
		$url = rtrim($url, '/');
		if ($url === '' || preg_match('#^https?:/?$#i', $url)) {
			return null;
		}
		if (!empty($row->uses_staff_portal_token)) {
			$url = $this->append_staff_portal_token_to_url($url, $sessionArray);
		}

		return $url;
	}
}
