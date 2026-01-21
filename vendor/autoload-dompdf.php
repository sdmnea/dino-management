<?php
// vendor/autoload-dompdf.php

require_once __DIR__ . '/dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// INI YANG WAJIB DITAMBAHKAN — 3 BARIS INI YANG BIKIN LOGO MUNCUL!
$options = new Options();
$options->set('isRemoteEnabled', true);           // gambar dari URL
$options->set('isHtml5ParserEnabled', true);      // penting
$options->set('isPhpEnabled', true);              // kalau pakai PHP di HTML
$options->set('chroot', __DIR__ . '/..');         // izinkan akses folder proyek
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isFontSubsettingEnabled', true);   // font lebih kecil

// Optional: debug
// $options->set('debugPng', true);
// $options->set('debugKeepTemp', true);
// $options->set('debugCss', true);