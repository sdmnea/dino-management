<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

if (!isLoggedIn()) {
    redirect('../../login.php');
}

$page_title = 'Laporan Pendapatan Per Produk - PDF';

// Include DomPDF
require_once '../../vendor/autoload-dompdf.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// === AMBIL FILTER ===
$selected_produk_ids = $_GET['produk_ids'] ?? [];
if (!is_array($selected_produk_ids))
    $selected_produk_ids = [];

$tanggal_dari = $_GET['tanggal_dari'] ?? '';
$tanggal_sampai = $_GET['tanggal_sampai'] ?? '';
$bulan = $_GET['bulan'] ?? '';

// Format filter
$filter_text = [];
if ($tanggal_dari && $tanggal_sampai) {
    $filter_text[] = "Periode: " . date('d F Y', strtotime($tanggal_dari)) . " - " . date('d F Y', strtotime($tanggal_sampai));
} elseif ($bulan) {
    $filter_text[] = "Bulan: " . date('F Y', strtotime($bulan . '-01'));
}
if (!empty($selected_produk_ids)) {
    $filter_text[] = count($selected_produk_ids) . " produk dipilih";
}
$filter_display = !empty($filter_text) ? implode(' | ', $filter_text) : 'Semua Data';

// === QUERY DATA ===
$database = new Database();
$db = $database->getConnection();

$sql = "SELECT 
            p.nama_produk,
            COALESCE(SUM(d.qty), 0) as total_qty,
            COALESCE(SUM(d.subtotal), 0) as total_nominal
        FROM produk p
        LEFT JOIN detail_penjualan d ON d.produk_id = p.id
        LEFT JOIN penjualan j ON j.id = d.penjualan_id
        WHERE 1=1";

$params = [];

if (!empty($selected_produk_ids)) {
    $placeholders = implode(',', array_fill(0, count($selected_produk_ids), '?'));
    $sql .= " AND p.id IN ($placeholders)";
    foreach ($selected_produk_ids as $id)
        $params[] = $id;
}
if ($tanggal_dari) {
    $sql .= " AND j.tanggal >= ?";
    $params[] = $tanggal_dari;
}
if ($tanggal_sampai) {
    $sql .= " AND j.tanggal <= ?";
    $params[] = $tanggal_sampai;
}
if ($bulan) {
    $sql .= " AND DATE_FORMAT(j.tanggal, '%Y-%m') = ?";
    $params[] = $bulan;
}

$sql .= " GROUP BY p.id ORDER BY total_qty DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung total
$total_qty = 0;
$total_nominal = 0;
foreach ($products as $p) {
    $total_qty += $p['total_qty'];
    $total_nominal += $p['total_nominal'];
}

// Logo base64
$logoPath = ROOT_PATH . '/assets/images/logo-dino.png';
$logoBase64 = '';
if (file_exists($logoPath)) {
    $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
} else {
    $logoBase64 = BASE_URL . '/assets/images/logo-dino.png';
}

// === BIKIN ROW TABEL DULU DI PHP ===
$table_rows = '';
$no = 1;
foreach ($products as $p) {
    $percentage = $total_nominal > 0 ? round(($p['total_nominal'] / $total_nominal) * 100, 1) : 0;
    
    // Tentukan warna berdasarkan persentase
    $color = '#3b82f6'; // Default blue
    if ($percentage >= 70) $color = '#10b981';
    elseif ($percentage >= 40) $color = '#3b82f6';
    else $color = '#ef4444';

    $table_rows .= '
    <tr>
        <td class="text-center">' . $no++ . '</td>
        <td class="text-left">' . htmlspecialchars($p['nama_produk']) . '</td>
        <td class="text-center">' . number_format($p['total_qty']) . ' pcs</td>
        <td class="text-center">
            <div class="percentage-bar">
                <div class="bar-bg">
                    <div class="bar-fill" style="width: ' . $percentage . '%; background-color: ' . $color . ';"></div>
                </div>
                <span class="percentage-text">' . $percentage . '%</span>
            </div>
        </td>
        <td class="text-right amount-cell">' . formatRupiah($p['total_nominal']) . '</td>
    </tr>';
}

// === HTML PDF ===
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>SH - Laporan Pendapatan Per Produk</title>
    <style>
        @page { 
            margin: 50px 40px 5px; /* Tambah margin bawah untuk footer */
        }
        
        body { 
            font-family: "Segoe UI", "DejaVu Sans", Arial, sans-serif; 
            margin: 0; 
            padding: 0; 
            font-size: 13px; 
            color: #333; 
            line-height: 1.5;
        }
        
        .container { 
            position: relative;
            min-height: 90vh;
        }
        
        /* HEADER KOP SURAT */
        .header-table {
            width:100%; 
            font-family:Segoe UI, Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* JUDUL LAPORAN */
        .report-title {
            text-align: center;
            margin: 30px 0 15px;
            position: relative;
            padding-bottom: 15px;
        }
        
        .report-title h1 {
            font-size: 24px;
            font-weight: 700;
            color: #1e40af;
            margin: 0 0 10px;
            position: relative;
            display: inline-block;
        }
        
        .report-title h1:after {
            content: "";
            position: absolute;
            bottom: -5px;
            left: 25%;
            right: 25%;
            height: 3px;
            background: linear-gradient(90deg, transparent, #10B981, transparent);
            border-radius: 3px;
        }
        
        .report-subtitle {
            padding: 2px;
            text-align: center;
            color: #ffffff;
            font-size: 14px;
            background: #5c5c5c;
            display: block;
            border-bottom: 1px solid #e2e8f0;
        }
        
        /* TABEL DATA */
        .table-container {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
            margin-bottom: 30px;
            width: 100%;
        }

        table {
            width: 100%;
        }

        table, th, td {
            border-collapse: collapse;
            font-size: 12.5px;
        }

        th {
            padding: 1px 12px 5px;
            text-align: center;
            font-weight: 600;
            font-size: 13px;
            border: none;
            color: #ffffff;
            background: #5c5c5c;
        }

        td {
            padding: 14px 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        
        tbody tr:last-child td {
            border-bottom: none;
        }
        
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        
        /* BAR PERSENTASE */
        .percentage-bar {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .bar-bg {
            flex: 1;
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: visible;
            position: relative;
        }

        .bar-fill {
            height: 100%;
            border-radius: 4px;
            min-width: 2px;
        }

        .percentage-text {
            font-size: 11px;
            font-weight: 600;
            color: #475569;
            min-width: 40px;
            text-align: right;
        }
        
        /* TOTAL ROW */
        .total-row {
            background: #5c5c5c;
            font-weight: 700;
            font-size: 14px;
        }
        
        .total-row td {
            padding: 16px 12px;
            color: #ffffff;
            border-bottom: none;
        }
        
        .amount-cell {
            font-weight: 600;
            color: #10B981;
        }
        
        /* FOOTER & TANDA TANGAN */
        .footer {
            position: fixed;
            bottom: 30px;
            right: 40px;
            width: 300px;
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            z-index: 100;
        }
        
        .signature-title {
            font-size: 14px;
            color: #475569;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .signature-box {
            width: 220px;
            height: 90px;
            margin: 0 auto 15px;
            border: 1.5px dashed #94a3b8;
            border-radius: 8px;
            position: relative;
            background: #f8fafc;
        }
        
        .signature-box:before {
            content: "Tanda Tangan & Stempel";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #94a3b8;
            font-size: 11px;
            white-space: nowrap;
        }
        
        .signature-placeholder {
            font-size: 12px;
            color: #64748b;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
        }
        
        /* PRINT INFO - Halaman Dinamis */
        .print-info {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 11px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding: 8px;
            background: white;
            z-index: 90;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 30px;
            box-sizing: border-box;
        }

        .print-info-left {
            visibility: visible; /* Pastikan visible */
        }

        .print-info-right {
            text-align: right;
            flex: 1;
            visibility: hidden; /* SEMBUNYIKAN teks HTML */
        }
        
        /* DIVIDER */
        .divider-line {
            height: 2px;
            background-color: #e5e7eb;
            margin: 15px 0 15px 0;
            border: none;
        }
        
        /* PAGE BREAK */
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <!-- HEADER - KOP SURAT -->
    <div class="header-table">
        <table style="width:100%; border-collapse:separate; border-spacing:0; background: #f8fafc; border:4px solid #5c5c5c; border-radius:10px; overflow:hidden;">
            <tr>
                <td style="width:25%; padding: 10px; text-align: center;">
                    <div style="display:inline-block; padding:15px; background:white; border-radius:12px; box-shadow:0 2px 10px rgba(0, 0, 0, 0.05); border:1px solid #e5e7eb;">
                        <img src="' . $logoBase64 . '" 
                            style="width:80px; height:auto; display:block; margin:0 auto; border-radius:12px;"
                            alt="Logo Es Teh Dino">
                    </div>
                    <div style="margin-top:12px; font-size:11px; color: #6b7280; font-weight:600; letter-spacing:0.5px; text-transform:uppercase;">
                        <div style="">
                            <span>Pemilik</span>
                            <span> : </span>
                            <span style= "color: #6c5ce7;">Rex</span>
                        </div>
                        <div style="">
                            <span>Cabang ke</span>
                            <span> : </span>
                            <span style= "color: #6c5ce7;">20</span>
                        </div>
                    </div>
                </td>
                
                <!-- Informasi (75%) -->
                <td style="width:75%; padding: 5px; border-left: 4px solid #5c5c5c;">
                    <div style="font-size: 14px; font-weight:800; vertical-align: text-top;">
                        <h1 style="color: #5c5c5c; margin: 0%;">Laporan Pendapatan Per Produk</h1>
                    </div>

                    <!-- Alamat -->
                    <div style="border-top:1px dashed #6b7280; padding: 2px;">
                        <div style="display:flex; align-items:flex-start;">
                            <div>
                                <div style="font-size:13px; color: #6b7280; font-weight:500; text-transform:uppercase;">Alamat Operasional</div>
                                <div style="font-size:12px; color: #4b5563; line-height:1.5;">
                                    Jl. Jalanin Aja Dulu No. 67, Kec. Haha Hihi, Kota Apayah<br>
                                    <span style="color: #6b7280; font-size:12px;">(Kode Pos: 12345)</span>
                                </div>
                                <div style="display:inline-block; align-items:center; margin-top: 9px;">
                                    <span style="font-size:12px; color: #6b7280; font-weight: 100; text-transform:uppercase;">Telpon/WA ke :</span>
                                    <span style="font-size:12px; color: #6c5ce7; font-weight: 50; letter-spacing: 1px;">0898983943</span>
                                </div>
                                <span style="">  </span>
                                <div style="display:inline-block; align-items:center;">
                                    <span style="font-size:12px; color: #6b7280; font-weight: 100; text-transform:uppercase;">Email :</span>
                                    <span style="font-size:12px; color: #6c5ce7; font-weight: 500;">esdinoSH@mail.com</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer kecil -->
                    <div style="border-top:1px dashed #6b7280; font-size:11px; color:#9ca3af; display:flex; justify-content:space-between;">
                        <div>
                            <span style="font-weight:600;">ID Laporan:</span> LAP-' . date('Ymd') . '-' . rand(100, 999) . '
                        </div>
                        <div>
                            <span style="font-weight:600;">Periode:</span> ' . date('F Y') . '
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- ISI LAPORAN -->
    <div class="container">
        <div class="divider-line"></div>

        <!-- TABEL DATA -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th colspan="5" style="width: 100%; paddding: 0%; margin: 0%;">
                            <div class="report-subtitle">
                                Berdasarkan: ' . htmlspecialchars($filter_display) . '
                            </div>
                        </th>
                    </tr>
                    <tr>
                        <th style="width: 8%;">No</th>
                        <th style="width: 35%;">Nama Produk</th>
                        <th style="width: 15%;">Qty Terjual</th>
                        <th style="width: 22%;">Persentase</th>
                        <th style="width: 20%;">Total Pendapatan</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- ISI TABLE -->
                    ' . $table_rows . '
                    <!-- TOTAL ROW -->
                    <tr class="total-row">
                        <td colspan="2" class="text-center"><strong>TOTAL KESELURUHAN</strong></td>
                        <td class="text-center"><strong>' . number_format($total_qty) . ' pcs</strong></td>
                        <td class="text-center"><strong>100%</strong></td>
                        <td class="text-right"><strong>' . formatRupiah($total_nominal) . '</strong></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- INFO CETAK - Fixed Position dengan Halaman Dinamis -->
    <div class="print-info">
        <div class="print-info-left">
            Laporan ini dicetak pada ' . date('d F Y H:i') . ' WIB
        </div>
        <div class="print-info-right">
        </div>
    </div>
</body>
</html>';

// === GENERATE PDF ===
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'Segoe UI');
$options->set('isFontSubsettingEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// TAMBAHKAN HALAMAN DINAMIS DI FOOTER DENGAN CANVAS
// Setelah $dompdf->render()
$canvas = $dompdf->getCanvas();
$fontMetrics = $dompdf->getFontMetrics();
$font = $fontMetrics->getFont("Arial, Helvetica, sans-serif", "normal");
$size = 9;
$color = array(148/255, 163/255, 184/255);

// Atur posisi untuk teks "Halaman" di kanan, sejajar dengan "Laporan ini dicetak"
$canvas->page_text(480, 810, "Halaman {PAGE_NUM} dari {PAGE_COUNT}", $font, $size, $color);

$filename = "Laporan_Pendapatan_Produk_" . date('Y-m-d_His') . ".pdf";
$dompdf->stream($filename, ['Attachment' => false]);