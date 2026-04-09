<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Serve staff uploads only to logged-in users who may view the file
 * (owner or staff-module permissions). Direct /uploads/staff/* is denied via .htaccess.
 */
class Secure_upload extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();
		$this->load->helper(['url', 'file']);
	}

	/**
	 * @param string $type photo|signature|passport_biodata
	 * @param string $filename basename only
	 */
	public function staff($type = '', $filename = '')
	{
		if (!$this->session->userdata('user')) {
			show_error('You must be signed in to view this file.', 403);
		}

		$filename = $filename !== '' ? rawurldecode($filename) : '';
		$filename = basename(str_replace('\\', '/', $filename));
		if ($filename === '' || $filename === '.' || $filename === '..' || !preg_match('/^[a-zA-Z0-9_.-]+$/', $filename)) {
			show_404();
		}

		$allowed = ['photo', 'signature', 'passport_biodata'];
		if (!in_array($type, $allowed, true)) {
			show_404();
		}

		$paths = [
			'photo' => FCPATH . 'uploads/staff/',
			'signature' => FCPATH . 'uploads/staff/signature/',
			'passport_biodata' => FCPATH . 'uploads/staff/passport_biodata/',
		];
		$columns = [
			'photo' => 'photo',
			'signature' => 'signature',
			'passport_biodata' => 'passport_biodata_page',
		];

		$dir = $paths[$type];
		$col = $columns[$type];
		$full = $dir . $filename;

		if (!is_file($full) || !is_readable($full)) {
			show_404();
		}

		$this->db->where($col, $filename);
		$this->db->limit(1);
		$owner = $this->db->get('staff')->row();
		if (!$owner) {
			show_error('Forbidden', 403);
		}

		// File must be registered on a staff row (above). Any signed-in portal user may view it;
		// this blocks anonymous hotlinking and guessing paths under /uploads/staff/.

		$mime = 'application/octet-stream';
		if (function_exists('mime_content_type')) {
			$detected = @mime_content_type($full);
			if (is_string($detected) && $detected !== '') {
				$mime = $detected;
			}
		} elseif (function_exists('finfo_open')) {
			$f = @finfo_open(FILEINFO_MIME_TYPE);
			if ($f) {
				$detected = @finfo_file($f, $full);
				finfo_close($f);
				if (is_string($detected) && $detected !== '') {
					$mime = $detected;
				}
			}
		}

		while (ob_get_level()) {
			ob_end_clean();
		}

		$this->output->set_status_header(200);
		$this->output->set_content_type($mime);
		$this->output->set_header('X-Content-Type-Options: nosniff');
		$this->output->set_header('Cache-Control: private, max-age=3600');
		$this->output->set_header('Content-Length: ' . (string) filesize($full));
		$this->output->set_output(file_get_contents($full));
	}
}
