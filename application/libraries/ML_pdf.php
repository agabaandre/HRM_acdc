<?php
use Mpdf\Mpdf;
use Mpdf\Config\Config;

class M_pdf {
public $pdf;

public function __construct()
{
$config = new Config();
$config->set('orientation', 'L'); // 'L' for landscape, 'P' for portrait
$this->pdf = new Mpdf($config);
}
}
