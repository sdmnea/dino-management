<?php
/**
 * Test DomPDF dengan konfigurasi dinamis BASE_URL
 * Versi sederhana tanpa heredoc kompleks
 */

// 1. Include config.php terlebih dahulu
require_once __DIR__ . '/config/config.php';

// 2. Debug mode (matikan saat production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 3. Include DomPDF
require_once __DIR__ . '/vendor/autoload-dompdf.php';

use Dompdf\Dompdf;

// ============ KONFIGURASI GAMBAR ============
$relativePath = '/assets/images/logo-dino.png';
$absolutePath = ROOT_PATH . $relativePath;
$imageUrl = BASE_URL . $relativePath;

// Tentukan sumber gambar
if (file_exists($absolutePath)) {
    $imageData = base64_encode(file_get_contents($absolutePath));
    $imgSrc = 'data:image/png;base64,' . $imageData;
    $imageSource = 'Base64 (Local File)';
    $fileExists = 'YA ✓';
} else {
    $imgSrc = $imageUrl;
    $imageSource = 'Remote URL';
    $fileExists = 'TIDAK ✗';
}

// Data untuk HTML
$currentDate = date('d F Y H:i:s');
$phpVersion = phpversion();
$serverSoftware = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown';
$documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : 'Unknown';
$hostName = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'HTTPS' : 'HTTP';

// Versi DomPDF
$versionFile = __DIR__ . '/vendor/dompdf/VERSION';
$dompdfVersion = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : 'Unknown';

// Generate document ID
$docId = uniqid('PDF_', true);

// ============ HTML CONTENT ============
$html = '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test DomPDF - Es Teh Dino</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: "DejaVu Sans", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f7fa;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eaeaea;
        }
        
        .logo {
            width: 150px;
            height: 150px;
            border-radius: 10px;
            border: 3px solid #10B981;
            margin: 0 auto 20px;
            display: block;
        }
        
        h1 { 
            color: #10B981;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .status {
            background: #10B981;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            display: inline-block;
            margin: 15px 0;
            font-weight: bold;
        }
        
        .subtitle {
            color: #8A2BE2;
            font-size: 18px;
            margin-bottom: 20px;
        }
        
        .info-section {
            margin: 25px 0;
        }
        
        .info-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
            border-left: 4px solid #667eea;
        }
        
        .debug-box {
            background: #e8f4fc;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
            font-family: monospace;
            font-size: 13px;
        }
        
        .info-row {
            display: flex;
            margin: 8px 0;
            padding-bottom: 8px;
            border-bottom: 1px dashed #e2e8f0;
        }
        
        .info-label {
            flex: 0 0 150px;
            font-weight: bold;
            color: #4A5568;
        }
        
        .info-value {
            flex: 1;
            color: #2D3748;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eaeaea;
            color: #718096;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <img src="' . $imgSrc . '" class="logo" alt="Logo Es Teh Dino">
            <h1>Es Teh Dino Management System</h1>
            <div class="status">✓ DOMPDF BERHASIL DIJALANKAN</div>
            <p class="subtitle">Laporan Sistem PDF Generator</p>
        </div>
        
        <!-- Informasi Debug -->
        <div class="info-section">
            <div class="info-box">
                <h3>Informasi Konfigurasi</h3>
                <div class="debug-box">
                    <div class="info-row">
                        <div class="info-label">Sumber Gambar:</div>
                        <div class="info-value">' . $imageSource . '</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">File Exists:</div>
                        <div class="info-value">' . $fileExists . '</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">BASE_URL:</div>
                        <div class="info-value">' . BASE_URL . '</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">ROOT_PATH:</div>
                        <div class="info-value">' . ROOT_PATH . '</div>
                    </div>
                </div>
            </div>
            
            <!-- Informasi Sistem -->
            <div class="info-box">
                <h3>Informasi Sistem</h3>
                <div class="info-row">
                    <div class="info-label">Tanggal & Waktu:</div>
                    <div class="info-value">' . $currentDate . ' WIB</div>
                </div>
                <div class="info-row">
                    <div class="info-label">DomPDF Version:</div>
                    <div class="info-value">' . $dompdfVersion . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">PHP Version:</div>
                    <div class="info-value">' . $phpVersion . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Server Software:</div>
                    <div class="info-value">' . $serverSoftware . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Document Root:</div>
                    <div class="info-value">' . $documentRoot . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Host Name:</div>
                    <div class="info-value">' . $hostName . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Protocol:</div>
                    <div class="info-value">' . $protocol . '</div>
                </div>
            </div>
            
            <!-- Tips -->
            <div class="info-box">
                <h3>Tips & Catatan</h3>
                <ul style="margin-left: 20px; color: #4A5568;">
                    <li>Pastikan folder /assets/images/ ada dan memiliki permission yang tepat</li>
                    <li>Gunakan format PNG/JPG dengan kualitas baik</li>
                    <li>Base64 encoding adalah metode paling stabil untuk gambar lokal</li>
                    <li>Ukuran gambar disarankan maksimal 500KB</li>
                </ul>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>Dokumen ini dibuat otomatis oleh <strong>Es Teh Dino Management System</strong></p>
            <p>© ' . date('Y') . ' - Document ID: ' . $docId . '</p>
        </div>
    </div>
</body>
</html>';

// ============ GENERATE PDF ============
try {
    $dompdf = new Dompdf();
    
    // Set options sederhana
    $options = $dompdf->getOptions();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    $dompdf->setOptions($options);
    
    // Load HTML
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    
    // Render PDF
    $dompdf->render();
    
    // Output PDF
    $filename = 'test-dompdf-' . date('Y-m-d') . '.pdf';
    $dompdf->stream($filename, [
        'Attachment' => false,
        'compress' => true
    ]);

} catch (Exception $e) {
    // Error handling sederhana
    echo '<div style="padding:20px; background:#fef2f2; border:2px solid #dc2626; border-radius:5px; margin:20px;">
            <h2 style="color:#dc2626;">⚠️ Error Generating PDF</h2>
            <p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>
            <p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . ' (Line: ' . htmlspecialchars($e->getLine()) . ')</p>
            <p><strong>BASE_URL:</strong> ' . (defined('BASE_URL') ? BASE_URL : 'Not defined') . '</p>
            <p><strong>Image Path:</strong> ' . $absolutePath . '</p>
            <p><a href="javascript:history.back()" style="color:#3b82f6;">← Kembali</a></p>
          </div>';
    exit;
}