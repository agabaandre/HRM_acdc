<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

class ML_pdf {

    private $mpdf;

    public function __construct($params = []) {
        // Load MPDF based on PHP version
        if (PHP_VERSION_ID >= 80000) { // PHP 8+ requires Composer autoload
            require_once FCPATH . 'vendor/autoload.php'; // Load Composer Autoload
        } else { // PHP 7 and below manually loads MPDF
            require_once APPPATH . 'third_party/mpdf/mpdf.php'; // Load MPDF manually
        }

        // Default PDF settings
        $defaultConfig = [
            'mode' => 'utf-8',
            'format' => 'A4', // Default to A4 format
            'default_font' => 'Arial'
        ];

        // Merge user-defined params with defaults
        $config = array_merge($defaultConfig, $params);

        // Initialize MPDF instance
        $this->mpdf = new \Mpdf\Mpdf($config);
    }

    // Load HTML content into the PDF
    public function loadHtml($html) {
        $this->mpdf->WriteHTML($html);
    }

    // Output the generated PDF
    public function output($filename = 'document.pdf', $destination = 'I') {
        return $this->mpdf->Output($filename, $destination);
    }

    // Get MPDF instance for direct access if needed
    public function getInstance() {
        return $this->mpdf;
    }
}
?>
