<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * First URI segment => permission rule (aligned with templates/views/partials/nav.php).
 * Enforced by hooks/Portal_permission_guard.php (pre_controller).
 *
 * - type "all": user must have every permission_id listed.
 * - type "any": user must have at least one permission_id listed.
 * - require_nonzero_staff: staff_id must be non-zero (matches nav checks for leave/performance).
 */
$config['portal_segment_permissions'] = [
	'dashboard' => [
		'type' => 'all',
		'permission_ids' => [76], // View dashboard
	],
	'staff' => [
		'type' => 'any',
		'permission_ids' => [72, 41], // Staff Profile menu
	],
	'leave' => [
		'type' => 'all',
		'permission_ids' => [37],
		'require_nonzero_staff' => true,
	],
	'performance' => [
		'type' => 'all',
		'permission_ids' => [74],
		'require_nonzero_staff' => true,
	],
	'workplan' => [
		'type' => 'all',
		'permission_ids' => [79],
	],
	'tasks' => [
		'type' => 'all',
		'permission_ids' => [81],
	],
	'weektasks' => [
		'type' => 'all',
		'permission_ids' => [75],
	],
	'admanager' => [
		'type' => 'all',
		'permission_ids' => [77],
	],
	'settings' => [
		'type' => 'all',
		'permission_ids' => [15],
	],
	'permissions' => [
		'type' => 'all',
		'permission_ids' => [17],
	],
	'attendance' => [
		'type' => 'all',
		'permission_ids' => [83],
		'require_nonzero_staff' => true,
	],
];
