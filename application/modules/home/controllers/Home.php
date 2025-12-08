<?php

use SebastianBergmann\Type\FalseType;

defined('BASEPATH') or exit('No direct script access allowed');

class Home extends MX_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->module = "home";
	
	}

	public function index()
	{
		// Get session configuration
		$sessionDriver = $this->config->item('sess_driver');
		$sessionTable = $this->config->item('sess_save_path');
		$cookieName = $this->config->item('sess_cookie_name');
		
		// Get session ID from cookie
		$sessionId = $this->input->cookie($cookieName);
		
		// For database sessions, FIRST check if session exists in database
		// This prevents access even if CodeIgniter creates a new session
		if ($sessionDriver === 'database' && !empty($sessionTable)) {
			if (!empty($sessionId)) {
				// Check if session exists in database
				$sessionExists = $this->db->query("SELECT id FROM `{$sessionTable}` WHERE id = ?", [$sessionId])->row();
				
				if (empty($sessionExists)) {
					// Session doesn't exist in database - user was logged out
					// Destroy any local session and clear cookie
					$this->session->sess_destroy();
					setcookie($cookieName, '', time() - 3600, '/');
					redirect('auth/login');
					return;
				}
			} else {
				// No session cookie - user is not logged in
				$this->session->sess_destroy();
				redirect('auth/login');
				return;
			}
		}
		
		// Check if user data exists and is valid
		$user = $this->session->userdata('user');
		if (empty($user) || !isset($user->staff_id) || empty($user->staff_id)) {
			// User data is missing - destroy session and redirect
			$this->session->sess_destroy();
			if (!empty($cookieName)) {
				setcookie($cookieName, '', time() - 3600, '/');
			}
			redirect('auth/login');
			return;
		}
	
		$data['module'] = $this->module;
		$data['title'] = "Home";
		
		render('home', $data);
	}
}
