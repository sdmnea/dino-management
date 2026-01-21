<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

if (!isLoggedIn()) {
    redirect('../../login.php');
}

$page_title = 'Laporan Pendapatan - PDF';

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
$cara_bayar = $_GET['cara_bayar'] ?? 'semua';

// Format filter
$filter_text = [];
if ($tanggal_dari && $tanggal_sampai) {
    $filter_text[] = "Periode: " . date('d F Y', strtotime($tanggal_dari)) . " - " . date('d F Y', strtotime($tanggal_sampai));
} elseif ($bulan) {
    $filter_text[] = "Bulan: " . date('F Y', strtotime($bulan . '-01'));
}
if ($cara_bayar !== '' && $cara_bayar !== 'semua') {
    $filter_text[] = "Pembayaran: " . ucfirst($cara_bayar);
}
if (!empty($selected_produk_ids)) {
    $filter_text[] = count($selected_produk_ids) . " produk dipilih";
}
$filter_display = !empty($filter_text) ? implode(' | ', $filter_text) : 'Semua Data';

// === QUERY DATA ===
$database = new Database();
$db = $database->getConnection();

// Query untuk data transaksi
$sql = "SELECT 
            j.kode_transaksi,
            j.tanggal,
            j.waktu,
            j.total_harga,
            j.cara_bayar,
            d.qty,
            p.nama_produk
        FROM penjualan j
        JOIN detail_penjualan d ON d.penjualan_id = j.id
        JOIN produk p ON p.id = d.produk_id
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
if ($cara_bayar !== '' && $cara_bayar !== 'semua') {
    $sql .= " AND j.cara_bayar = ?";
    $params[] = $cara_bayar;
}

$sql .= " ORDER BY j.tanggal DESC, j.waktu DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung total
$total_qty = 0;
$total_pendapatan = 0;
foreach ($transactions as $t) {
    $total_qty += $t['qty'];
    $total_pendapatan += $t['total_harga'];
}

// Query untuk produk terlaris
$terlaris_sql = "SELECT 
                    p.nama_produk,
                    SUM(d.qty) as total_qty,
                    SUM(d.subtotal) as total_nominal
                FROM detail_penjualan d
                JOIN produk p ON p.id = d.produk_id
                JOIN penjualan j ON j.id = d.penjualan_id
                WHERE 1=1";

$terlaris_params = [];

if (!empty($selected_produk_ids)) {
    $placeholders = implode(',', array_fill(0, count($selected_produk_ids), '?'));
    $terlaris_sql .= " AND p.id IN ($placeholders)";
    foreach ($selected_produk_ids as $id) {
        $terlaris_params[] = $id;
    }
}
if ($tanggal_dari) {
    $terlaris_sql .= " AND j.tanggal >= ?";
    $terlaris_params[] = $tanggal_dari;
}
if ($tanggal_sampai) {
    $terlaris_sql .= " AND j.tanggal <= ?";
    $terlaris_params[] = $tanggal_sampai;
}
if ($bulan) {
    $terlaris_sql .= " AND DATE_FORMAT(j.tanggal, '%Y-%m') = ?";
    $terlaris_params[] = $bulan;
}
if ($cara_bayar !== '' && $cara_bayar !== 'semua') {
    $terlaris_sql .= " AND j.cara_bayar = ?";
    $terlaris_params[] = $cara_bayar;
}

$terlaris_sql .= " GROUP BY p.id ORDER BY total_qty DESC LIMIT 1";
$terlaris_stmt = $db->prepare($terlaris_sql);
$terlaris_stmt->execute($terlaris_params);
$produk_terlaris = $terlaris_stmt->fetch(PDO::FETCH_ASSOC);

// Logo base64
$logoPath = ROOT_PATH . '/assets/images/logo-dino.png';
$logoBase64 = '';
if (file_exists($logoPath)) {
    $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
} else {
    $logoBase64 = BASE_URL . '/assets/images/logo-dino.png';
}

// Hitung rata-rata per item
$average_per_item = $total_qty > 0 ? $total_pendapatan / $total_qty : 0;
$average_per_transaction = count($transactions) > 0 ? $total_pendapatan / count($transactions) : 0;

// === BIKIN ROW TABEL DULU DI PHP ===
$table_rows = '';
$no = 1;
foreach ($transactions as $t) {
    $cara_bayar_text = strtoupper($t['cara_bayar']);
    if ($cara_bayar_text == 'NON_TUNAI')
        $cara_bayar_text = 'NON TUNAI';

    // Tentukan warna badge berdasarkan cara bayar
    $badge_color = '';
    switch ($t['cara_bayar']) {
        case 'tunai':
            $badge_color = 'background-color: #d1fae5; color: #065f46;';
            break;
        case 'qris':
            $badge_color = 'background-color: #dbeafe; color: #1e40af;';
            break;
        case 'transfer':
            $badge_color = 'background-color: #f3e8ff; color: #5b21b6;';
            break;
        case 'split':
            $badge_color = 'background-color: #fef3c7; color: #92400e;';
            break;
        case 'non_tunai':
            $badge_color = 'background-color: #fee2e2; color: #991b1b;';
            break;
        default:
            $badge_color = 'background-color: #e5e7eb; color: #374151;';
    }

    $table_rows .= '
    <tr>
        <td class="text-center">' . $no++ . '</td>
        <td>
            <div style="font-weight: 600; color: #1f2937; font-size: 13px;">
                ' . date('d/m/Y', strtotime($t['tanggal'])) . '
            </div>
            <div style="font-size: 11px; color: #6b7280;">
                ' . date('H:i', strtotime($t['waktu'])) . ' WIB
            </div>
            <div style="font-size: 11px; color: #8a2be2; font-weight: 500; margin-top: 3px;">
                ' . htmlspecialchars($t['kode_transaksi']) . '
            </div>
        </td>
        <td class="text-left" style="font-weight: 500;">' . htmlspecialchars($t['nama_produk']) . '</td>
        <td class="text-center">
            <span style="padding: 4px 10px; border-radius: 20px; font-weight: 600; font-size: 12px; background-color: #f3f4f6;">
                ' . number_format($t['qty']) . '
            </span>
        </td>
        <td class="text-right amount-cell">' . formatRupiah($t['total_harga']) . '</td>
        <td class="text-center">
            <span style="padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; ' . $badge_color . '">
                ' . $cara_bayar_text . '
            </span>
        </td>
    </tr>';
}

// === HTML PDF ===
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>SH - Laporan Pendapatan</title>
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
                        <h1 style="color: #5c5c5c; margin: 0%;">Laporan Pendapatan Transaksi</h1>
                    </div>

                    <!-- Alamat Operasional -->
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
                            <span style="font-weight:600;">ID Laporan:</span> LAP-TRX-' . date('Ymd') . '-' . rand(100, 999) . '
                        </div>
                        <div>
                            <span style="font-weight:600;">Periode:</span> ' . date('F Y') . '
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- KOTAK INFO STATISTIK - 1 BARIS 3 KOLOM -->
    <table style="width: 100%; border-collapse: separate; border-spacing: 15px; margin: 20px 0 15px 0;">
        <tr>
            <!-- KOTAK 1: PRODUK TERLARIS -->
            <td style="width: 33.33%; background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; border-top: 4px solid #10B981; vertical-align: top;">
                <div style="display: flex; align-items: flex-start; gap: 15px;">
                    <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(16, 185, 129, 0.1); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <div style="color: #10B981; font-size: 24px; font-weight: bold;">★</div>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-size: 14px; color: #6b7280; margin-bottom: 6px; font-weight: 600;">PRODUK TERLARIS</div>
                        <div style="font-size: 16px; font-weight: 700; color: #1f2937; margin-bottom: 4px; line-height: 1.3; min-height: 40px;">
                            ' . ($produk_terlaris ? htmlspecialchars($produk_terlaris['nama_produk']) : '—') . '
                        </div>
                        <div style="font-size: 12px; color: #9ca3af; line-height: 1.4;">
                            <strong style="color: #10B981;">' . ($produk_terlaris ? number_format($produk_terlaris['total_qty']) : 0) . ' pcs</strong> terjual<br>
                            ' . ($produk_terlaris ? formatRupiah($produk_terlaris['total_nominal']) : 'Rp 0') . '
                        </div>
                    </div>
                </div>
            </td>
            
            <!-- KOTAK 2: TOTAL TRANSAKSI -->
            <td style="width: 33.33%; background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; border-top: 4px solid #3B82F6; vertical-align: top;">
                <div style="display: flex; align-items: flex-start; gap: 15px;">
                    <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(59, 130, 246, 0.1); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <div style="color: #3B82F6; font-size: 24px; font-weight: bold;">📊</div>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-size: 14px; color: #6b7280; margin-bottom: 6px; font-weight: 600;">TOTAL TRANSAKSI</div>
                        <div style="font-size: 20px; font-weight: 700; color: #1f2937; margin-bottom: 4px;">
                            ' . number_format(count($transactions)) . '
                        </div>
                        <div style="font-size: 12px; color: #9ca3af; line-height: 1.4;">
                            <strong style="color: #3B82F6;">' . number_format($total_qty) . ' pcs</strong> terjual<br>
                            Rata-rata: ' . formatRupiah($average_per_transaction) . '
                        </div>
                    </div>
                </div>
            </td>
            
            <!-- KOTAK 3: TOTAL PENDAPATAN -->
            <td style="width: 33.33%; background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; border-top: 4px solid #8A2BE2; vertical-align: top;">
                <div style="display: flex; align-items: flex-start; gap: 15px;">
                    <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(138, 43, 226, 0.1); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <div style="color: #8A2BE2; font-size: 24px; font-weight: bold;">💰</div>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-size: 14px; color: #6b7280; margin-bottom: 6px; font-weight: 600;">TOTAL PENDAPATAN</div>
                        <div style="font-size: 20px; font-weight: 700; color: #1f2937; margin-bottom: 4px;">
                            ' . formatRupiah($total_pendapatan) . '
                        </div>
                        <div style="font-size: 12px; color: #9ca3af; line-height: 1.4;">
                            Rata-rata per item: ' . formatRupiah($average_per_item) . '<br>
                            <span style="color: #8A2BE2; font-weight: 600;">Profitabilitas Tinggi</span>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- ISI LAPORAN -->
    <div class="container">
        <div class="divider-line"></div>

        <!-- TABEL DATA -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th colspan="6" style="width: 100%; paddding: 0%; margin: 0%;">
                            <div class="report-subtitle">
                                Berdasarkan: ' . htmlspecialchars($filter_display) . '
                            </div>
                        </th>
                    </tr>
                    <tr>
                        <th style="width: 5%;">No</th>
                        <th style="width: 20%;">Tanggal & Jam</th>
                        <th style="width: 30%;">Produk</th>
                        <th style="width: 10%;">Qty</th>
                        <th style="width: 20%;">Total</th>
                        <th style="width: 15%;">Pembayaran</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- ISI TABLE -->
                    ' . $table_rows . '
                    <!-- TOTAL ROW -->
                    <tr class="total-row">
                        <td colspan="3" class="text-center"><strong>TOTAL KESELURUHAN</strong></td>
                        <td class="text-center"><strong>' . number_format($total_qty) . ' pcs</strong></td>
                        <td class="text-right"><strong>' . formatRupiah($total_pendapatan) . '</strong></td>
                        <td class="text-center"><strong>' . count($transactions) . ' Transaksi</strong></td>
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
$canvas = $dompdf->getCanvas();
$fontMetrics = $dompdf->getFontMetrics();
$font = $fontMetrics->getFont("Arial, Helvetica, sans-serif", "normal");
$size = 9;
$color = array(148 / 255, 163 / 255, 184 / 255);

// Atur posisi untuk teks "Halaman" di kanan, sejajar dengan "Laporan ini dicetak"
$canvas->page_text(480, 810, "Halaman {PAGE_NUM} dari {PAGE_COUNT}", $font, $size, $color);

$filename = "Laporan_Pendapatan_" . date('Y-m-d_His') . ".pdf";
$dompdf->stream($filename, ['Attachment' => false]);