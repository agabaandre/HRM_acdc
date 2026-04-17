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
		$filename = trim($filename);
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

		$owner = $this->resolve_staff_upload_owner($col, $type, $filename);
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

	/**
	 * staff.photo (and similar) may be stored as a bare basename or with a legacy path prefix;
	 * disk files are always basename-only under uploads/staff/… .
	 */
	private function resolve_staff_upload_owner(string $col, string $type, string $filename)
	{
		$possible = [$filename, trim($filename)];
		$photo_prefixes = ['uploads/staff/', './uploads/staff/', '/uploads/staff/'];
		$sig_prefixes = ['uploads/staff/signature/', './uploads/staff/signature/', '/uploads/staff/signature/'];
		$pass_prefixes = ['uploads/staff/passport_biodata/', './uploads/staff/passport_biodata/', '/uploads/staff/passport_biodata/'];
		if ($type === 'photo') {
			foreach ($photo_prefixes as $p) {
				$possible[] = $p . $filename;
			}
		} elseif ($type === 'signature') {
			foreach ($sig_prefixes as $p) {
				$possible[] = $p . $filename;
			}
		} elseif ($type === 'passport_biodata') {
			foreach ($pass_prefixes as $p) {
				$possible[] = $p . $filename;
			}
		}
		$possible = array_values(array_unique(array_filter($possible, static function ($v) {
			return $v !== '';
		})));

		$col_sql = '`' . str_replace('`', '', $col) . '`';
		$escaped_list = implode(',', array_map(function ($p) {
			return $this->db->escape($p);
		}, $possible));
		$row = $this->db->query("SELECT * FROM staff WHERE TRIM({$col_sql}) IN ({$escaped_list}) LIMIT 1")->row();
		if ($row) {
			return $row;
		}

		// Match by basename when the column still holds a non-normalized path (slashes or stray spaces).
		$esc = $this->db->escape($filename);
		$sql = "SELECT * FROM staff WHERE TRIM({$col_sql}) <> '' AND (
			SUBSTRING_INDEX(REPLACE(TRIM({$col_sql}), CHAR(92), '/'), '/', -1) = {$esc}
			OR LOWER(SUBSTRING_INDEX(REPLACE(TRIM({$col_sql}), CHAR(92), '/'), '/', -1)) = LOWER({$esc})
		) LIMIT 1";

		return $this->db->query($sql)->row();
	}
}
