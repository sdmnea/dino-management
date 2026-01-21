<?php
// File: modules/laporan/export/pendapatan-produk-excel.php

// ==================== PERBAIKAN PATH ====================
// Gunakan __DIR__ untuk mendapatkan path absolut file ini
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/functions.php';

if (!isLoggedIn()) {
    // Redirect ke login dengan path yang benar
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// ==================== KONEKSI DATABASE ====================
$database = new Database();
$db = $database->getConnection();

// ==================== AMBIL PARAMETER FILTER ====================
$selected_produk_ids = $_GET['produk_ids'] ?? [];
if (!is_array($selected_produk_ids)) {
    $selected_produk_ids = [];
}

$tanggal_dari = $_GET['tanggal_dari'] ?? '';
$tanggal_sampai = $_GET['tanggal_sampai'] ?? '';
$bulan = $_GET['bulan'] ?? '';

// ==================== QUERY DATA ====================
$sql = "SELECT 
            p.id,
            p.nama_produk,
            COALESCE(SUM(d.qty), 0) as total_qty,
            COALESCE(SUM(d.subtotal), 0) as total_nominal
        FROM produk p
        LEFT JOIN detail_penjualan d ON d.produk_id = p.id
        LEFT JOIN penjualan j ON j.id = d.penjualan_id
        WHERE 1=1";

$params = [];

// Filter produk (multiple)
if (!empty($selected_produk_ids)) {
    $placeholders = implode(',', array_fill(0, count($selected_produk_ids), '?'));
    $sql .= " AND p.id IN ($placeholders)";
    foreach ($selected_produk_ids as $id) {
        $params[] = $id;
    }
}

// Filter tanggal dari-sampai
if ($tanggal_dari !== '') {
    $sql .= " AND j.tanggal >= ?";
    $params[] = $tanggal_dari;
}
if ($tanggal_sampai !== '') {
    $sql .= " AND j.tanggal <= ?";
    $params[] = $tanggal_sampai;
}

// Filter bulan
if ($bulan !== '') {
    $sql .= " AND DATE_FORMAT(j.tanggal, '%Y-%m') = ?";
    $params[] = $bulan;
}

$sql .= " GROUP BY p.id, p.nama_produk ORDER BY total_qty DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products_report = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung total
$total_qty_all = 0;
$total_nominal_all = 0;
foreach ($products_report as $pr) {
    $total_qty_all += $pr['total_qty'];
    $total_nominal_all += $pr['total_nominal'];
}

// ==================== HEADER EXCEL ====================
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="laporan-pendapatan-produk_' . date('Y-m-d_H-i-s') . '.xls"');
header('Cache-Control: max-age=0');

// ==================== OUTPUT EXCEL (HTML TABLE) ====================
echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Pendapatan Per Produk</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th { background-color: #f2f2f2; font-weight: bold; padding: 8px; border: 1px solid #ddd; }
        td { padding: 8px; border: 1px solid #ddd; }
        .total { background-color: #e8f4fd; font-weight: bold; }
        .header { background-color: #4CAF50; color: white; font-size: 18px; padding: 10px; }
    </style>
</head>
<body>';

echo '<table border="1">';
echo '<tr><td colspan="4" class="header">LAPORAN PENDAPATAN PER PRODUK</td></tr>';
echo '<tr><td colspan="4"><strong>Tanggal Export:</strong> ' . date('d/m/Y H:i:s') . '</td></tr>';

// Info filter
if (!empty($selected_produk_ids) || $tanggal_dari || $tanggal_sampai || $bulan) {
    echo '<tr><td colspan="4"><strong>Filter:</strong> ';
    $filter_info = [];
    if (!empty($selected_produk_ids)) {
        $filter_info[] = count($selected_produk_ids) . ' produk terpilih';
    }
    if ($tanggal_dari) {
        $filter_info[] = 'Dari: ' . $tanggal_dari;
    }
    if ($tanggal_sampai) {
        $filter_info[] = 'Sampai: ' . $tanggal_sampai;
    }
    if ($bulan) {
        $filter_info[] = 'Bulan: ' . $bulan;
    }
    echo implode(' | ', $filter_info) . '</td></tr>';
}

echo '<tr><td colspan="4">&nbsp;</td></tr>';

// Header tabel
echo '<tr>
        <th style="width: 50px;"><strong>No</strong></th>
        <th><strong>Nama Produk</strong></th>
        <th style="width: 120px;"><strong>Qty Terjual</strong></th>
        <th style="width: 150px;"><strong>Total Nominal</strong></th>
      </tr>';

// Data
$no = 1;
foreach ($products_report as $pr) {
    echo '<tr>';
    echo '<td>' . $no++ . '</td>';
    echo '<td>' . htmlspecialchars($pr['nama_produk']) . '</td>';
    echo '<td align="right">' . number_format($pr['total_qty']) . '</td>';
    echo '<td align="right">' . formatRupiahExcel($pr['total_nominal']) . '</td>';
    echo '</tr>';
}

// Total
echo '<tr class="total">';
echo '<td colspan="2" align="right"><strong>TOTAL KESELURUHAN:</strong></td>';
echo '<td align="right"><strong>' . number_format($total_qty_all) . '</strong></td>';
echo '<td align="right"><strong>' . formatRupiahExcel($total_nominal_all) . '</strong></td>';
echo '</tr>';

echo '</table>';

// ==================== FOOTER ====================
echo '<br><br>';
echo '<div style="font-size: 11px; color: #666;">';
echo '<strong>Catatan:</strong> Data ini diambil pada ' . date('d/m/Y H:i:s') . ' dari sistem Dino Management';
echo '</div>';

echo '</body></html>';

// ==================== FUNGSI BANTU ====================
function formatRupiahExcel($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
?>