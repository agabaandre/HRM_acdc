<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Enforce portal permissions by first URI segment (see config/portal_segment_permissions.php).
 * Runs pre_controller so protected controllers are not constructed without authorization.
 */
function portal_permission_guard()
{
	$CI = &get_instance();

	if (is_cli()) {
		return;
	}

	$seg1 = $CI->uri->segment(1);
	$key = $seg1 !== false && $seg1 !== null ? strtolower((string) $seg1) : '';

	$map = $CI->config->item('portal_segment_permissions');
	if (empty($map) || !is_array($map) || $key === '' || !isset($map[$key])) {
		return;
	}

	$rule = $map[$key];
	$user = $CI->session->userdata('user');

	if (!$user) {
		$CI->session->set_flashdata('error', 'Please sign in to continue.');
		redirect('auth');
		return;
	}

	if (!function_exists('staff_user_satisfies_portal_rule')) {
		$CI->load->helper('custom');
	}

	if (!staff_user_satisfies_portal_rule($user, $rule)) {
		$CI->session->set_flashdata('error', 'You do not have permission to access this page. If you need access, contact your administrator.');
		redirect('home/index');
		return;
	}
}
