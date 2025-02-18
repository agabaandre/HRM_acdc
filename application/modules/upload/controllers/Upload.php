<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Upload extends MX_Controller
{


	public  function __construct()
	{
		parent::__construct();

		$this->module = "upload";
	
	}

	public function image_upload() {
		$this->load->library('upload');
        // Set the response header to JSON
        header('Content-Type: application/json');
        // Ensure a file is being uploaded
        if(isset($_FILES['file']['name']) && $_FILES['file']['name'] != ''){
            // Configure upload options
            $config['upload_path']   = './uploads/summernote'; // Ensure this directory exists and is writable
            $config['allowed_types'] = 'gif|jpg|jpeg|png';
            $config['max_size']      = 2048; // Maximum size in KB (2MB)
            $config['encrypt_name']  = TRUE; // Encrypt the filename for security

            $this->upload->initialize($config);

            // Attempt to upload the file
            if(!$this->upload->do_upload('file')){
                // Capture and return error messages
                $error = $this->upload->display_errors();
                echo json_encode(['error' => $error]);
                return;
            } else {
                // Upload successful: Retrieve file data
                $data = $this->upload->data();
                // Build the image URL using base_url (make sure your base_url is configured in config.php)
                $image_url = base_url('uploads/summernote/' . $data['file_name']);
                echo json_encode(['url' => $image_url]);
                return;
            }
        } else {
            // No file provided in the request
            echo json_encode(['error' => 'No file uploaded.']);
        }
    }
}
