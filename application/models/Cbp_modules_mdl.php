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

	public function __construct()
	{
		parent::__construct();
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
	 * @return list<array{href:string,label:string,icon:string,absolute:bool,desc:string}>
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
			$absolute = in_array($row->target_resolver, ['staff_app_token', 'finance_host'], true)
				|| preg_match('#^https?://#i', $resolved);
			$out[] = [
				'href' => $resolved,
				'label' => $row->system_name,
				'icon' => $row->icon_class,
				'absolute' => $absolute,
				'desc' => (string) ($row->description ?? ''),
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
			$token = urlencode(base64_encode(json_encode($sessionArray)));
			$base = rtrim(base_url(), '/');
			$seg = trim((string) $row->base_url, '/');

			return $seg === '' ? null : $base . '/' . $seg . '?token=' . $token;
		}
		if ($resolver === 'finance_host') {
			$token = urlencode(base64_encode(json_encode($sessionArray)));
			$host = $_SERVER['HTTP_HOST'] ?? '';
			$isLocal = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);
			if ($isLocal) {
				$devBase = trim((string) ($row->base_url_development ?? ''), '/');
				if ($devBase === '') {
					$devBase = 'http://localhost:3002';
				}
				if (strpos($devBase, 'http') !== 0) {
					$devBase = 'http://' . $devBase;
				}

				return rtrim($devBase, '/') . '?token=' . $token;
			}
			$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
			$prod = trim((string) ($row->base_url_production ?? ''), '/');
			if ($prod !== '') {
				if (preg_match('#^https?://#i', $prod)) {
					return rtrim($prod, '/') . '?token=' . $token;
				}

				return $scheme . '://' . $host . '/' . $prod . '?token=' . $token;
			}

			return $scheme . '://' . $host . '/finance?token=' . $token;
		}

		return null;
	}
}
