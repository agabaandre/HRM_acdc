<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

// Use Mpdf based on PHP version
if (PHP_VERSION_ID >= 80000) { // PHP 8 and above
    require_once APPPATH . '../vendor/autoload.php'; // Load Composer autoload
    $mpdf = new \Mpdf\Mpdf();
} else { // PHP 7 and below
    include_once APPPATH . '/third_party/mpdf/mpdf.php';
    $mpdf = new \mPDF();
}

class ML_pdf {
    public $pdf;

    public function __construct($params = []) {
        // Default PDF settings
        $defaultConfig = [
            'mode' => 'utf-8',
            'format' => 'A4-L', // Landscape Mode
            'default_font' => 'Arial'
        ];

        // Merge user params with defaults
        $config = array_merge($defaultConfig, $params);

        // Initialize Mpdf
        if (PHP_VERSION_ID >= 80000) {
            $this->pdf = new \Mpdf\Mpdf($config);
        } else {
            $this->pdf = new \mPDF($config);
        }
    }

    public function loadHtml($html) {
        $this->pdf->WriteHTML($html);
    }

    public function output($filename = 'document.pdf', $destination = 'I') {
        return $this->pdf->Output($filename, $destination);
    }

    public function getInstance() {
        return $this->pdf;
    }
}
?>