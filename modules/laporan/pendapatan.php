<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

if (!isLoggedIn()) {
    redirect('../../login.php');
}

$page_title = 'Laporan Pendapatan';
$database = new Database();
$db = $database->getConnection();

// === PAGINATION CONFIG ===
$records_per_page = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$all_pages = isset($_GET['all_pages']) ? (int) $_GET['all_pages'] : 0;
if ($page < 1)
    $page = 1;

// === FILTER ===
$selected_produk_ids = $_GET['produk_ids'] ?? [];
// Pastikan produk_ids adalah array
if (!is_array($selected_produk_ids)) {
    $selected_produk_ids = [];
}

$tanggal_dari = $_GET['tanggal_dari'] ?? '';
$tanggal_sampai = $_GET['tanggal_sampai'] ?? '';
$bulan = $_GET['bulan'] ?? ''; // format YYYY-MM
$cara_bayar = $_GET['cara_bayar'] ?? 'semua';

// Ambil semua produk untuk dropdown
$produk_sql = "SELECT id, nama_produk FROM produk ORDER BY nama_produk";
$produk_stmt = $db->prepare($produk_sql);
$produk_stmt->execute();
$all_products = $produk_stmt->fetchAll(PDO::FETCH_ASSOC);

// Build query utama untuk TOTAL KESELURUHAN (tanpa pagination)
$sql_total = "SELECT 
            COALESCE(SUM(j.total_harga), 0) as total_pendapatan,
            COALESCE(SUM(d.qty), 0) as total_qty,
            COUNT(*) as total_rows
        FROM penjualan j
        JOIN detail_penjualan d ON d.penjualan_id = j.id
        JOIN produk p ON p.id = d.produk_id
        WHERE 1=1";

$params_total = [];

// Filter produk (multiple) untuk query total
if (!empty($selected_produk_ids)) {
    $placeholders = implode(',', array_fill(0, count($selected_produk_ids), '?'));
    $sql_total .= " AND p.id IN ($placeholders)";
    foreach ($selected_produk_ids as $id) {
        $params_total[] = $id;
    }
}

// Filter tanggal dari-sampai untuk query total
if ($tanggal_dari !== '') {
    $sql_total .= " AND j.tanggal >= ?";
    $params_total[] = $tanggal_dari;
}
if ($tanggal_sampai !== '') {
    $sql_total .= " AND j.tanggal <= ?";
    $params_total[] = $tanggal_sampai;
}

// Filter bulan untuk query total
if ($bulan !== '') {
    $sql_total .= " AND DATE_FORMAT(j.tanggal, '%Y-%m') = ?";
    $params_total[] = $bulan;
}

// Filter cara bayar untuk query total
if ($cara_bayar !== '' && $cara_bayar !== 'semua') {
    $sql_total .= " AND j.cara_bayar = ?";
    $params_total[] = $cara_bayar;
}

// Hitung total keseluruhan berdasarkan filter
$stmt_total = $db->prepare($sql_total);
$stmt_total->execute($params_total);
$total_result = $stmt_total->fetch(PDO::FETCH_ASSOC);

$total_pendapatan_all = $total_result['total_pendapatan'] ?? 0;
$total_qty_all = $total_result['total_qty'] ?? 0;
$total_rows = $total_result['total_rows'] ?? 0;

// Hitung total pages untuk pagination
$total_pages = $all_pages ? 1 : ceil($total_rows / $records_per_page);
if ($page > $total_pages && $total_pages > 0)
    $page = $total_pages;

// Build query utama dengan data detail (dengan pagination)
$sql = "SELECT 
            j.id,
            j.kode_transaksi,
            j.tanggal,
            j.waktu,
            j.total_harga,
            j.cara_bayar,
            d.qty,
            p.nama_produk,
            p.id as produk_id
        FROM penjualan j
        JOIN detail_penjualan d ON d.penjualan_id = j.id
        JOIN produk p ON p.id = d.produk_id
        WHERE 1=1";

$params = [];

// Filter produk (multiple) untuk query data detail
if (!empty($selected_produk_ids)) {
    $placeholders = implode(',', array_fill(0, count($selected_produk_ids), '?'));
    $sql .= " AND p.id IN ($placeholders)";
    foreach ($selected_produk_ids as $id) {
        $params[] = $id;
    }
}

// Filter tanggal dari-sampai untuk query data detail
if ($tanggal_dari !== '') {
    $sql .= " AND j.tanggal >= ?";
    $params[] = $tanggal_dari;
}
if ($tanggal_sampai !== '') {
    $sql .= " AND j.tanggal <= ?";
    $params[] = $tanggal_sampai;
}

// Filter bulan untuk query data detail
if ($bulan !== '') {
    $sql .= " AND DATE_FORMAT(j.tanggal, '%Y-%m') = ?";
    $params[] = $bulan;
}

// Filter cara bayar untuk query data detail
if ($cara_bayar !== '' && $cara_bayar !== 'semua') {
    $sql .= " AND j.cara_bayar = ?";
    $params[] = $cara_bayar;
}

$sql .= " ORDER BY j.tanggal DESC, j.waktu DESC";

// Tambahkan pagination jika bukan all_pages
if (!$all_pages && $total_rows > 0) {
    $offset = ($page - 1) * $records_per_page;
    $sql .= " LIMIT ? OFFSET ?";
    // Tambahkan parameter LIMIT dan OFFSET sebagai integer
    $params[] = (int) $records_per_page;
    $params[] = (int) $offset;
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$transaksi = $stmt->fetchAll(PDO::FETCH_ASSOC);

// === Hitung Total Per Halaman ===
$total_pendapatan_page = 0;
$total_qty_page = 0;
foreach ($transaksi as $t) {
    $total_pendapatan_page += $t['total_harga'];
    $total_qty_page += $t['qty'];
}

// === Produk Terlaris (berdasarkan qty terjual) ===
$terlaris_sql = "SELECT 
                    p.nama_produk,
                    SUM(d.qty) as total_qty,
                    SUM(d.subtotal) as total_nominal
                FROM detail_penjualan d
                JOIN produk p ON p.id = d.produk_id
                JOIN penjualan j ON j.id = d.penjualan_id
                WHERE 1=1";

$terlaris_params = [];

// Filter produk (multiple) untuk query terlaris
if (!empty($selected_produk_ids)) {
    $placeholders = implode(',', array_fill(0, count($selected_produk_ids), '?'));
    $terlaris_sql .= " AND p.id IN ($placeholders)";
    foreach ($selected_produk_ids as $id) {
        $terlaris_params[] = $id;
    }
}

// Filter tanggal untuk query terlaris
if ($tanggal_dari !== '') {
    $terlaris_sql .= " AND j.tanggal >= ?";
    $terlaris_params[] = $tanggal_dari;
}
if ($tanggal_sampai !== '') {
    $terlaris_sql .= " AND j.tanggal <= ?";
    $terlaris_params[] = $tanggal_sampai;
}
if ($bulan !== '') {
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

// Load Select2 untuk dropdown produk
include '../../includes/header.php';
?>

<!-- Load Select2 CSS dan JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* Style untuk filter form */
    .filter-form {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        padding: 20px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        margin-bottom: 25px;
    }

    .filter-grid {
        display: grid;
        gap: 15px;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    }

    .form-group {
        margin-bottom: 0;
    }

    .form-label {
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 6px;
        display: block;
    }

    .form-input {
        width: 100%;
        padding: 10px 12px;
        border-radius: 8px;
        border: 1px solid #d1d5db;
        font-size: 14px;
        transition: all 0.2s ease;
        background: white;
    }

    .form-input:focus {
        outline: none;
        border-color: #9ACD32;
        box-shadow: 0 0 0 3px rgba(154, 205, 50, 0.1);
    }

    .filter-actions {
        margin-top: 15px;
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }

    .btn-primary {
        background: linear-gradient(135deg, #9ACD32, #8A2BE2);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(154, 205, 50, 0.2);
    }

    .btn-secondary {
        background: #e5e7eb;
        color: #374151;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .btn-secondary:hover {
        background: #d1d5db;
    }

    .btn-by-product {
        background: linear-gradient(135deg, #3B82F6, #1D4ED8);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-by-product:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
    }

    /* Style untuk stat cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 15px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
        border-top: 4px solid;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .stat-card:nth-child(1) {
        border-color: #10B981;
    }

    .stat-card:nth-child(2) {
        border-color: #8A2BE2;
    }

    .stat-card:nth-child(3) {
        border-color: #F59E0B;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);
    }

    .stat-content {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .stat-card:nth-child(1) .stat-icon {
        background: rgba(16, 185, 129, 0.1);
    }

    .stat-card:nth-child(2) .stat-icon {
        background: rgba(138, 43, 226, 0.1);
    }

    .stat-card:nth-child(3) .stat-icon {
        background: rgba(245, 158, 11, 0.1);
    }

    .stat-icon i {
        font-size: 24px;
    }

    .stat-card:nth-child(1) .stat-icon i {
        color: #10B981;
    }

    .stat-card:nth-child(2) .stat-icon i {
        color: #8A2BE2;
    }

    .stat-card:nth-child(3) .stat-icon i {
        color: #F59E0B;
    }

    .stat-value {
        font-size: 24px;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 4px;
        line-height: 1;
    }

    .stat-label {
        color: #6b7280;
        font-size: 14px;
        margin-bottom: 4px;
    }

    .stat-subtext {
        font-size: 12px;
        color: #9ca3af;
    }

    /* Style untuk Select2 */
    .select2-container .select2-selection--multiple {
        min-height: 42px;
        border: 1px solid #d1d5db !important;
        border-radius: 8px !important;
    }

    .select2-container .select2-search--inline .select2-search__field {
        margin-top: 8px;
        padding-left: 8px;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #9ACD32;
        border: none;
        border-radius: 6px;
        color: white;
        padding: 4px 8px;
        font-size: 12px;
        margin-top: 6px;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: white;
        margin-right: 4px;
    }

    .select2-dropdown {
        border: 1px solid #e5e7eb !important;
        border-radius: 8px !important;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
    }

    /* Style untuk tabel */
    .table-container {
        overflow-x: auto;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
        background: white;
        margin-bottom: 20px;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }

    .data-table thead {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    }

    .data-table th {
        padding: 14px 12px;
        text-align: left;
        font-weight: 600;
        color: #374151;
        border-bottom: 2px solid #e2e8f0;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .data-table td {
        padding: 12px;
        border-bottom: 1px solid #f1f5f9;
        color: #4b5563;
    }

    .data-table tbody tr:hover {
        background-color: #f9fafb;
    }

    .data-table tbody tr:last-child td {
        border-bottom: none;
    }

    .payment-badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        display: inline-block;
    }

    .payment-tunai {
        background-color: #d1fae5;
        color: #065f46;
    }

    .payment-qris {
        background-color: #dbeafe;
        color: #1e40af;
    }

    .payment-transfer {
        background-color: #f3e8ff;
        color: #5b21b6;
    }

    .payment-split {
        background-color: #fef3c7;
        color: #92400e;
    }

    .payment-non_tunai {
        background-color: #fee2e2;
        color: #991b1b;
    }

    /* Total row */
    .total-row {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        font-weight: 700;
        color: #1f2937;
    }

    .total-row td {
        padding: 16px 12px;
        border-top: 2px solid #e2e8f0;
        font-size: 15px;
    }

    /* Pagination Styles */
    .pagination-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 20px;
        padding: 15px;
        background: #f8fafc;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
        flex-wrap: wrap;
        gap: 10px;
    }

    .pagination-info {
        font-size: 14px;
        color: #6b7280;
        font-weight: 500;
    }

    .pagination-controls {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .pagination-btn {
        padding: 8px 14px;
        border: 1px solid #d1d5db;
        background: white;
        color: #374151;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .pagination-btn:hover:not(:disabled) {
        background: #f3f4f6;
        border-color: #9ca3af;
    }

    .pagination-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .pagination-btn.active {
        background: linear-gradient(135deg, #9ACD32, #8A2BE2);
        color: white;
        border-color: transparent;
    }

    .pagination-dots {
        padding: 8px 8px;
        color: #9ca3af;
    }

    .page-size-selector {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .all-pages-btn {
        padding: 8px 14px;
        background: #10B981;
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .all-pages-btn:hover {
        background: #0DA271;
    }

    .per-page-btn {
        padding: 8px 14px;
        background: #3B82F6;
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .per-page-btn:hover {
        background: #2563eb;
    }

    /* Responsive */
    /* ==================== PERBAIKAN FILTER ACTIONS ==================== */
    /* Desktop: Filter | Export (dengan separator) */
    .filter-actions-desktop {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .filter-buttons-group {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .export-buttons-group {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-left: 10px;
        padding-left: 10px;
        position: relative;
    }

    .export-buttons-group::before {
        content: "|";
        position: absolute;
        left: -5px;
        color: #d1d5db;
        font-weight: bold;
    }

    .btn-excel {
        background: linear-gradient(135deg, #10B981, #059669);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-excel:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
    }

    .btn-pdf {
        background: #DC2626;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-pdf:hover {
        background: #B91C1C;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.2);
    }

    /* ==================== RESPONSIVE DESIGN ==================== */
    @media (max-width: 768px) {

        /* 1. KOTAK STATISTIK */
        .stats-grid.horizontal-scroll {
            display: flex;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            padding-bottom: 10px;
            gap: 10px;
            grid-template-columns: unset;
            flex-wrap: nowrap;
            margin-bottom: 15px;
            max-height: none;
        }

        .stats-grid.horizontal-scroll .stat-card {
            flex: 0 0 auto;
            width: 280px;
            min-width: 280px;
            padding: 12px;
            min-height: 90px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        /* PERBAIKAN: Layout row untuk konten statistik */
        .stats-grid.horizontal-scroll .stat-card .stat-content {
            flex-direction: row;
            gap: 10px;
            text-align: left;
            height: 100%;
            align-items: center;
        }

        /* Logo/icon di sebelah kiri */
        .stats-grid.horizontal-scroll .stat-card .stat-icon {
            width: 40px;
            height: 40px;
            margin: 0;
            flex-shrink: 0;
        }

        .stats-grid.horizontal-scroll .stat-card .stat-icon i {
            font-size: 20px;
        }

        /* Konten teks di sebelah kanan */
        .stats-grid.horizontal-scroll .stat-card .stat-content>div {
            flex: 1;
            min-width: 0;
        }

        /* Perbaikan untuk teks di mobile */
        .stats-grid.horizontal-scroll .stat-card .stat-value {
            font-size: 12px;
            line-height: 1.2;
            margin-bottom: 2px;
            white-space: normal;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            max-height: 2.4em;
        }

        .stats-grid.horizontal-scroll .stat-card .stat-label {
            font-size: 11px;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* TAMPILKAN STAT-SUBTEXT DI MOBILE */
        .stats-grid.horizontal-scroll .stat-card .stat-subtext {
            display: block !important;
            font-size: 9px;
            color: #9ca3af;
            line-height: 1.3;
            white-space: normal;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            max-height: 2.6em;
            margin-top: 2px;
        }

        /* 2. FILTER FORM - Grid 2 kolom di mobile */
        .filter-form {
            padding: 15px;
        }

        .filter-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-label {
            font-size: 12px;
            margin-bottom: 4px;
        }

        .form-input {
            padding: 8px 10px;
            font-size: 13px;
        }

        /* Select2 di mobile */
        .select2-container .select2-selection--multiple {
            min-height: 38px;
        }

        .select2-container .select2-search--inline .select2-search__field {
            font-size: 13px;
            margin-top: 6px;
        }

        /* 3. FILTER ACTIONS MOBILE - Perbaikan layout tombol */
        .filter-actions-desktop {
            flex-direction: column;
            gap: 12px;
        }

        .filter-buttons-group,
        .export-buttons-group {
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .export-buttons-group::before {
            display: none;
        }

        .filter-buttons-group .btn-primary,
        .filter-buttons-group .btn-secondary,
        .export-buttons-group .btn-pdf,
        .export-buttons-group .btn-excel {
            width: 100%;
            padding: 10px 12px;
            font-size: 13px;
            justify-content: center;
        }

        /* 4. HEADER PAGE */
        .page-header {
            flex-direction: column;
            align-items: stretch;
            margin-bottom: 15px;
            gap: 10px;
        }

        .page-header h2 {
            text-align: center;
            font-size: 16px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .btn-by-product {
            width: 100%;
            justify-content: center;
            padding: 10px 12px;
            font-size: 13px;
        }

        /* 5. TABEL LAPORAN */
        .table-container {
            font-size: 12px;
            border-radius: 8px;
        }

        .data-table {
            min-width: 600px;
        }

        .data-table th {
            padding: 10px 8px;
            font-size: 12px;
        }

        .data-table td {
            padding: 10px 8px;
        }

        /* 6. PAGINATION */
        .pagination-container {
            padding: 12px;
            gap: 12px;
            flex-direction: column;
        }

        .pagination-info {
            font-size: 12px;
            text-align: center;
            width: 100%;
        }

        .pagination-controls {
            justify-content: center;
            flex-wrap: wrap;
        }

        .pagination-btn {
            padding: 6px 10px;
            font-size: 12px;
            min-width: 36px;
        }

        .all-pages-btn,
        .per-page-btn {
            padding: 6px 10px;
            font-size: 12px;
            width: 100%;
            text-align: center;
        }

        /* 7. FILTER TOGGLE DI MOBILE */
        .filter-header {
            padding: 14px 16px;
        }

        .filter-header h3 {
            font-size: 15px;
        }

        .filter-section.expanded .filter-content {
            padding: 16px;
        }
    }

    /* ========== MOBILE VERY SMALL (≤480px) ========== */
    @media (max-width: 480px) {

        /* 1. Statistik - Horizontal scroll dengan layout row */
        .stats-grid.horizontal-scroll .stat-card {
            min-width: 260px;
            width: 260px;
            padding: 10px;
            scroll-snap-align: start;
        }

        .stats-grid.horizontal-scroll .stat-card .stat-content {
            gap: 8px;
        }

        .stats-grid.horizontal-scroll .stat-card .stat-icon {
            width: 36px;
            height: 36px;
        }

        .stats-grid.horizontal-scroll .stat-card .stat-icon i {
            font-size: 18px;
        }

        .stats-grid.horizontal-scroll .stat-card .stat-value {
            font-size: 11px;
        }

        .stats-grid.horizontal-scroll .stat-card .stat-label {
            font-size: 10px;
        }

        .stats-grid.horizontal-scroll .stat-card .stat-subtext {
            font-size: 8px;
            -webkit-line-clamp: 2;
            max-height: 2.6em;
        }

        /* 2. Filter - 1 kolom di very small */
        .filter-grid {
            grid-template-columns: 1fr;
        }

        /* 3. Tabel - lebih kecil */
        .data-table th,
        .data-table td {
            padding: 8px 6px;
            font-size: 11px;
        }

        /* 4. Filter toggle di very small */
        .filter-header {
            padding: 12px 14px;
        }

        .filter-section.expanded .filter-content {
            padding: 12px;
        }
    }

    /* Tambahkan di mobile media query */
    @media (max-width: 768px) {
        .badge-count {
            display: none !important;
        }
    }

    /* ==================== MOBILE LAYOUT FIX ==================== */
    @media (max-width: 768px) {

        /* 1. KOTAK STATISTIK - Layout row untuk logo di kiri, konten di kanan */
        .stats-grid.horizontal-scroll {
            display: flex;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            padding-bottom: 10px;
            gap: 10px;
            grid-template-columns: unset;
            flex-wrap: nowrap;
            margin-bottom: 15px;
            max-height: none;
            /* Hapus max-height */
        }

        .stats-grid.horizontal-scroll .stat-card {
            flex: 0 0 auto;
            width: 280px;
            min-width: 280px;
            padding: 12px;
            min-height: 50px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        /* PERBAIKAN: Layout row untuk konten statistik */
        .stats-grid.horizontal-scroll .stat-card .stat-content {
            flex-direction: row;
            /* Logo di kiri, konten di kanan */
            gap: 10px;
            text-align: left;
            /* Teks rata kiri */
            height: 100%;
            align-items: center;
            /* Pusatkan vertikal */
        }

        /* Logo/icon di sebelah kiri */
        .stats-grid.horizontal-scroll .stat-card .stat-icon {
            width: 10px;
            /* Sedikit lebih besar untuk mobile */
            height: 10px;
            margin: 0;
            /* Hapus margin auto */
            flex-shrink: 0;
            /* Mencegah icon mengecil */
        }

        .stats-grid.horizontal-scroll .stat-card .stat-icon i {
            font-size: 10px;
        }

        /* Konten teks di sebelah kanan */
        .stats-grid.horizontal-scroll .stat-card .stat-content>div {
            flex: 1;
            /* Ambil sisa ruang */
            min-width: 0;
            /* Penting untuk text ellipsis */
        }

        /* Perbaikan untuk teks di mobile */
        .stats-grid.horizontal-scroll .stat-card .stat-value {
            font-size: 12px;
            line-height: 1.2;
            margin-bottom: 2px;
            white-space: normal;
            /* Bisa wrap */
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            /* Maksimal 2 baris */
            -webkit-box-orient: vertical;
            max-height: 2.4em;
            /* 2 baris x 1.2 line-height */
        }

        .stats-grid.horizontal-scroll .stat-card .stat-label {
            font-size: 11px;
            margin-bottom: 2px;
            white-space: nowrap;
            /* Label dalam 1 baris */
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* TAMPILKAN STAT-SUBTEXT DI MOBILE */
        .stats-grid.horizontal-scroll .stat-card .stat-subtext {
            display: block !important;
            /* Tampilkan di mobile */
            font-size: 9px;
            color: #9ca3af;
            line-height: 1.3;
            white-space: normal;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            /* Maksimal 2 baris */
            -webkit-box-orient: vertical;
            max-height: 2.6em;
            /* 2 baris x 1.3 line-height */
            margin-top: 2px;
        }

        /* Perbaikan khusus untuk produk terlaris (nama produk panjang) */
        .stats-grid.horizontal-scroll .stat-card:nth-child(1) .stat-value {
            font-size: 11px;
            -webkit-line-clamp: 2;
            max-height: 2.4em;
        }

        .stats-grid.horizontal-scroll .stat-card:nth-child(1) .stat-subtext {
            font-size: 8px;
            -webkit-line-clamp: 2;
            max-height: 2.6em;
        }

        /* Perbaikan untuk total pendapatan */
        .stats-grid.horizontal-scroll .stat-card:nth-child(2) .stat-value {
            font-size: 13px;
            font-weight: 700;
            color: #1f2937;
            -webkit-line-clamp: 1;
        }

        .stats-grid.horizontal-scroll .stat-card:nth-child(2) .stat-subtext {
            font-size: 9px;
        }

        /* Perbaikan untuk produk dipilih */
        .stats-grid.horizontal-scroll .stat-card:nth-child(3) .stat-value {
            font-size: 13px;
            -webkit-line-clamp: 1;
        }

        .stats-grid.horizontal-scroll .stat-card:nth-child(3) .stat-subtext {
            font-size: 9px;
            -webkit-line-clamp: 2;
            max-height: 2.6em;
        }
    }

    /* ========== MOBILE VERY SMALL (≤480px) ========== */
    @media (max-width: 480px) {

        /* 1. Statistik - Horizontal scroll dengan layout row */
        .stats-grid.horizontal-scroll .stat-card {
            min-width: 260px;
            /* Sedikit lebih kecil di very small */
            width: 260px;
            padding: 10px;
            scroll-snap-align: start;
        }

        .stats-grid.horizontal-scroll .stat-card .stat-content {
            gap: 8px;
        }

        .stats-grid.horizontal-scroll .stat-card .stat-icon {
            width: 36px;
            height: 36px;
        }

        .stats-grid.horizontal-scroll .stat-card .stat-icon i {
            font-size: 18px;
        }

        .stats-grid.horizontal-scroll .stat-card .stat-value {
            font-size: 11px;
        }

        .stats-grid.horizontal-scroll .stat-card .stat-label {
            font-size: 10px;
        }

        .stats-grid.horizontal-scroll .stat-card .stat-subtext {
            font-size: 8px;
            -webkit-line-clamp: 2;
            max-height: 2.6em;
        }
    }

    /* ========== TABLET (768px - 1024px) ========== */
    @media (min-width: 769px) and (max-width: 1024px) {
        .stats-grid.horizontal-scroll {
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .stat-card {
            padding: 18px 15px;
        }

        .stat-content {
            flex-direction: row;
            /* Logo di kiri, konten di kanan untuk tablet juga */
            align-items: center;
            text-align: left;
            gap: 12px;
        }

        .stat-icon {
            margin: 0;
            /* Hapus margin auto */
            width: 45px;
            height: 45px;
            flex-shrink: 0;
        }

        .stat-value {
            font-size: 16px;
            white-space: normal;
        }

        .stat-label {
            font-size: 13px;
        }

        .stat-subtext {
            display: block;
            font-size: 11px;
        }
    }

    /* Tambahkan di mobile media query */
    @media (max-width: 768px) {
        .badge-count {
            display: none !important;
            /* Sembunyikan jumlah data di mobile */
        }
    }

    /* Tambahkan di mobile CSS (max-width: 768px) */
    /* .stats-grid.horizontal-scroll {
        display: flex;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        padding-bottom: 10px;
        gap: 10px;
        grid-template-columns: unset;
        flex-wrap: nowrap;
    }

    .stats-grid.horizontal-scroll .stat-card {
        flex: 0 0 auto;
        width: 280px;
        min-width: 280px;
    } */

    /* ==================== STATISTICS GRID - FIXED FOR DESKTOP ==================== */

    /* Tampilan Desktop (default) - Tampilkan 3 kolom dengan lebar penuh */
    .stats-grid.horizontal-scroll {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        /* 3 kolom dengan lebar sama */
        gap: 20px;
        /* Jarak antar kartu lebih longgar */
        margin-bottom: 25px;
        overflow: visible;
        /* Hilangkan overflow */
        padding-bottom: 0;
        /* Hilangkan padding bawah */
        justify-items: center;
        /* Pusatkan kartu secara horizontal */
        align-items: stretch;
        /* Pastikan semua kartu sama tinggi */
    }

    /* Styling untuk setiap kartu statistik di desktop */
    .stats-grid.horizontal-scroll .stat-card {
        width: 100%;
        /* Isi penuh kolom grid */
        min-width: auto;
        /* Hilangkan min-width fixed */
        flex: none;
        /* Nonaktifkan flex untuk grid */
        max-width: 400px;
        /* Batas maksimum lebar kartu */
        height: 100%;
        /* Gunakan tinggi penuh */
    }

    /* Konten di dalam kartu - Pusatkan secara vertikal */
    .stats-grid.horizontal-scroll .stat-card .stat-content {
        height: 100%;
        display: flex;
        align-items: center;
        /* Pusatkan vertikal */
        justify-content: center;
        /* Pusatkan horizontal */
        text-align: center;
        /* Teks rata tengah */
        gap: 15px;
        /* Jarak antara icon dan teks */
    }

    /* Untuk desktop besar (≥ 1024px) - Tambahkan ruang lebih */
    @media (min-width: 1024px) {
        .stats-grid.horizontal-scroll {
            gap: 25px;
            margin-bottom: 30px;
        }

        .stats-grid.horizontal-scroll .stat-card {
            padding: 25px 20px;
        }
    }

    /* ==================== MOBILE TAMPILAN (TIDAK DIRUBAH) ==================== */
    @media (max-width: 768px) {

        /* 1. KOTAK STATISTIK - Grid horizontal untuk 3 kotak */
        .stats-grid {
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 15px;
            max-height: 100px;
            overflow: hidden;
        }

        .stat-card {
            padding: 12px;
            min-height: 90px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
        }

        .stat-content {
            flex-direction: column;
            gap: 8px;
            text-align: center;
            height: 100%;
        }

        .stat-icon {
            width: 36px;
            height: 36px;
            margin: 0 auto;
        }

        .stat-icon i {
            font-size: 18px;
        }

        .stat-value {
            font-size: 12px;
            line-height: 1.2;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .stat-label {
            font-size: 11px;
            margin-bottom: 2px;
        }

        .stat-subtext {
            font-size: 10px;
            display: none;
        }

        .stat-card:nth-child(1) .stat-value {
            font-size: 11px;
        }

        /* Khusus untuk scroll horizontal di mobile (original style) */
        .stats-grid.horizontal-scroll {
            display: flex;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            padding-bottom: 10px;
            gap: 10px;
            grid-template-columns: unset;
            flex-wrap: nowrap;
        }

        .stats-grid.horizontal-scroll .stat-card {
            flex: 0 0 auto;
            width: 280px;
            min-width: 280px;
        }
    }

    /* ========== MOBILE VERY SMALL (≤480px) ========== */
    @media (max-width: 480px) {

        /* 1. Statistik - Horizontal scroll */
        .stats-grid {
            grid-template-columns: repeat(3, 100px);
            overflow-x: auto;
            overflow-y: hidden;
            padding-bottom: 5px;
            gap: 8px;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
        }

        .stat-card {
            min-width: 100px;
            scroll-snap-align: start;
        }

        .stat-value {
            font-size: 11px;
        }

        .stat-label {
            font-size: 10px;
        }
    }

    /* ========== TABLET (768px - 1024px) ========== */
    @media (min-width: 769px) and (max-width: 1024px) {
        .stats-grid.horizontal-scroll {
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .stat-card {
            padding: 18px 15px;
        }

        .stat-content {
            flex-direction: row;
            /* Kembalikan ke horizontal untuk tablet */
            text-align: left;
            /* Teks rata kiri untuk tablet */
            justify-content: flex-start;
            /* Mulai dari kiri */
        }

        .stat-icon {
            margin: 0;
            /* Reset margin auto */
            width: 45px;
            height: 45px;
        }

        .stat-value {
            font-size: 16px;
        }

        .stat-label {
            font-size: 13px;
        }

        .stat-subtext {
            display: block;
            /* Tampilkan kembali subtext di tablet */
            font-size: 11px;
        }
    }

    /* ==================== TAMBAHAN UNTUK DESKTOP LAYAR KECIL (769px - 900px) ==================== */
    @media (min-width: 769px) and (max-width: 900px) {
        .stats-grid.horizontal-scroll {
            gap: 15px;
        }

        .stat-card {
            padding: 15px;
        }

        .stat-content {
            gap: 12px;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
        }

        .stat-icon i {
            font-size: 18px;
        }

        .stat-value {
            font-size: 14px;
        }

        .stat-label {
            font-size: 12px;
        }
    }

    /* ==================== OPTIMASI TULISAN PANJANG ==================== */
    /* Untuk produk dengan nama panjang di desktop */
    .stats-grid.horizontal-scroll .stat-card:nth-child(1) .stat-value {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        /* Maksimal 2 baris */
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.3;
        max-height: 2.6em;
        /* 2 baris x 1.3 line-height */
    }

    /* Untuk produk terpilih dengan nama panjang */
    .stats-grid.horizontal-scroll .stat-card:nth-child(3) .stat-subtext {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        /* Maksimal 2 baris */
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.4;
        max-height: 2.8em;
        /* 2 baris x 1.4 line-height */
    }

    /* ==================== FILTER TOGGLE SYSTEM ==================== */
    .filter-section {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        margin-bottom: 25px;
        overflow: hidden;
    }

    .filter-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        cursor: pointer;
        user-select: none;
        transition: background-color 0.2s ease;
        border-bottom: 1px solid transparent;
    }

    .filter-header:hover {
        background-color: #f1f5f9;
    }

    .filter-header-title {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
    }

    .filter-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
        color: #374151;
    }

    .filter-header i {
        color: #9ACD32;
        font-size: 16px;
    }

    .filter-badge {
        background: linear-gradient(135deg, #9ACD32, #8A2BE2);
        color: white;
        font-size: 11px;
        font-weight: 600;
        padding: 3px 8px;
        border-radius: 12px;
        margin-left: 8px;
    }

    .filter-toggle-btn {
        background: none;
        border: none;
        color: #6b7280;
        font-size: 16px;
        cursor: pointer;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    .filter-toggle-btn:hover {
        background-color: #e5e7eb;
        color: #374151;
    }

    .filter-content {
        padding: 0;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease, padding 0.3s ease;
    }

    .filter-section.expanded .filter-content {
        max-height: 1000px;
        /* Nilai tinggi yang cukup besar */
        padding: 20px;
    }

    .filter-section.expanded .filter-header {
        border-bottom: 1px solid #e2e8f0;
    }

    .filter-section.expanded .filter-toggle-btn i {
        transform: rotate(180deg);
    }

    /* Untuk mobile */
    @media (max-width: 768px) {
        .filter-header {
            padding: 14px 16px;
        }

        .filter-header h3 {
            font-size: 15px;
        }

        .filter-section.expanded .filter-content {
            padding: 16px;
        }
    }

    @media (max-width: 480px) {
        .filter-header {
            padding: 12px 14px;
        }

        .filter-section.expanded .filter-content {
            padding: 12px;
        }
    }
</style>

<div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 6px 18px rgba(0,0,0,0.06);">
    <!-- Page Header with By Produk Button -->
    <div class="page-header"
        style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:15px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <h2 style="margin:0; font-size:20px; color:#1f2937; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-chart-line" style="color: #9ACD32;"></i> Laporan Pendapatan
            </h2>
            <?php if ($total_rows > 0): ?>
                <span
                    style="background:#f3f4f6; color:#6b7280; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600; display:inline-block;"
                    class="badge-count">
                    <?php echo number_format($total_rows); ?> data
                </span>
            <?php endif; ?>
        </div>
        <a href="<?php echo BASE_URL; ?>/modules/laporan/pendapatan-produk.php" class="btn-by-product">
            <i class="fas fa-list"></i> By Produk
        </a>
    </div>

    <!-- KOTAK INFO STATISTIK -->
    <div class="stats-grid horizontal-scroll">
        <div class="stat-card">
            <div class="stat-content">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div>
                    <div class="stat-value">
                        <?php echo $produk_terlaris ? htmlspecialchars($produk_terlaris['nama_produk']) : '—'; ?>
                    </div>
                    <div class="stat-label">Produk Terlaris</div>
                    <div class="stat-subtext">
                        Terjual:
                        <strong><?php echo $produk_terlaris ? number_format($produk_terlaris['total_qty']) : 0; ?>
                            pcs</strong>
                        • <?php echo $produk_terlaris ? formatRupiah($produk_terlaris['total_nominal']) : 'Rp 0'; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-content">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo formatRupiah($total_pendapatan_all); ?></div>
                    <div class="stat-label">Total Pendapatan</div>
                    <div class="stat-subtext">
                        <?php echo number_format($total_rows); ?> transaksi •
                        <?php echo number_format($total_qty_all); ?> pcs
                    </div>
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-content">
                <div class="stat-icon">
                    <i class="fas fa-filter"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo count($selected_produk_ids); ?> produk</div>
                    <div class="stat-label">Produk Dipilih</div>
                    <div class="stat-subtext">
                        <?php
                        if (!empty($selected_produk_ids)) {
                            $selected_names = [];
                            foreach ($all_products as $p) {
                                if (in_array($p['id'], $selected_produk_ids)) {
                                    $selected_names[] = $p['nama_produk'];
                                }
                            }
                            echo htmlspecialchars(implode(', ', array_slice($selected_names, 0, 3)));
                            if (count($selected_names) > 3)
                                echo '...';
                        } else {
                            echo 'Semua produk';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTER SECTION DENGAN TOGGLE -->
    <div class="filter-section">
        <div class="filter-header" id="filterToggle">
            <div class="filter-header-title">
                <i class="fas fa-filter"></i>
                <h3>Filter Laporan</h3>
                <?php if ($tanggal_dari || $tanggal_sampai || $bulan || $cara_bayar !== 'semua' || !empty($selected_produk_ids)): ?>
                    <span class="filter-badge">Aktif</span>
                <?php endif; ?>
            </div>
            <button type="button" class="filter-toggle-btn">
                <i class="fas fa-chevron-down"></i>
            </button>
        </div>

        <div class="filter-content" id="filterContent" style="display: none;">
            <form method="GET" class="filter-form" id="filterForm">
                <!-- Hidden fields untuk pagination -->
                <input type="hidden" name="page" value="1">
                <input type="hidden" name="all_pages" value="0">

                <div class="filter-grid">
                    <!-- Form fields tetap sama seperti sebelumnya -->
                    <div class="form-group">
                        <label class="form-label">Pilih Produk</label>
                        <select name="produk_ids[]" id="produk-select" class="form-input" multiple="multiple">
                            <?php foreach ($all_products as $product): ?>
                                <option value="<?php echo $product['id']; ?>" <?php echo in_array($product['id'], $selected_produk_ids) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($product['nama_produk']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Dari Tanggal</label>
                        <input type="date" name="tanggal_dari" value="<?php echo htmlspecialchars($tanggal_dari); ?>"
                            class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Sampai Tanggal</label>
                        <input type="date" name="tanggal_sampai"
                            value="<?php echo htmlspecialchars($tanggal_sampai); ?>" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Bulan</label>
                        <input type="month" name="bulan" value="<?php echo htmlspecialchars($bulan); ?>"
                            class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Cara Bayar</label>
                        <select name="cara_bayar" class="form-input">
                            <option value="semua">Semua Metode</option>
                            <option value="tunai" <?php echo $cara_bayar == 'tunai' ? 'selected' : ''; ?>>Tunai</option>
                            <option value="qris" <?php echo $cara_bayar == 'qris' ? 'selected' : ''; ?>>QRIS</option>
                            <option value="transfer" <?php echo $cara_bayar == 'transfer' ? 'selected' : ''; ?>>Transfer
                            </option>
                            <option value="split" <?php echo $cara_bayar == 'split' ? 'selected' : ''; ?>>Split</option>
                            <option value="non_tunai" <?php echo $cara_bayar == 'non_tunai' ? 'selected' : ''; ?>>Non
                                Tunai
                            </option>
                        </select>
                    </div>
                </div>

                <!-- PERBAIKAN: Filter Actions dengan layout baru -->
                <div class="filter-actions filter-actions-desktop">
                    <div class="filter-buttons-group">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-filter"></i> Terapkan Filter
                        </button>
                        <a href="pendapatan.php" class="btn-secondary">
                            <i class="fas fa-redo"></i> Reset Filter
                        </a>
                    </div>

                    <div class="export-buttons-group">
                        <a href="<?php echo BASE_URL; ?>/modules/DomPDF/pendapatan-pdf.php?<?php echo http_build_query($_GET); ?>"
                            target="_blank" class="btn-pdf">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </a>
                        <a href="<?php echo BASE_URL; ?>/modules/laporan/export/pendapatan-excel.php?<?php echo http_build_query($_GET); ?>"
                            target="_blank" class="btn-excel">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </a>
                    </div>

                    <div style="flex: 1;"></div>
                    <small style="color: #6b7280; font-size: 12px;">
                        <i class="fas fa-info-circle"></i>
                        Total: <?php echo formatRupiah($total_pendapatan_all); ?> •
                        Qty: <?php echo number_format($total_qty_all); ?> pcs
                    </small>
                </div>
            </form>
        </div>
    </div>

    <!-- TABEL LAPORAN -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th style="width: 150px;">Tanggal & Jam</th>
                    <th>Produk</th>
                    <th style="width: 80px; text-align: center;">Qty</th>
                    <th style="width: 150px;">Total</th>
                    <th style="width: 120px;">Bayar</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transaksi)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: #6b7280;">
                            <i class="fas fa-inbox"
                                style="font-size: 40px; color: #d1d5db; margin-bottom: 10px; display: block;"></i>
                            Tidak ada data penjualan dengan filter ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php
                    $running_total = 0;
                    $running_qty = 0;
                    $start_number = $all_pages ? 1 : (($page - 1) * $records_per_page + 1);

                    foreach ($transaksi as $i => $t):
                        $running_total += $t['total_harga'];
                        $running_qty += $t['qty'];
                        $cara = strtoupper($t['cara_bayar']);
                        if ($cara == 'NON_TUNAI')
                            $cara = 'NON TUNAI';
                        $payment_class = 'payment-' . $t['cara_bayar'];
                        ?>
                        <tr>
                            <td style="color: #9ca3af;"><?php echo $start_number + $i; ?></td>
                            <td>
                                <div style="font-weight: 600; color: #1f2937;">
                                    <?php echo date('d/m/Y', strtotime($t['tanggal'])); ?>
                                </div>
                                <div style="font-size: 12px; color: #6b7280;">
                                    <?php echo date('H:i', strtotime($t['waktu'])); ?>
                                </div>
                            </td>
                            <td style="color: #1f2937; font-weight: 500;"><?php echo htmlspecialchars($t['nama_produk']); ?>
                            </td>
                            <td style="text-align: center;">
                                <span
                                    style="background: #f3f4f6; padding: 4px 10px; border-radius: 20px; font-weight: 600; font-size: 13px;">
                                    <?php echo $t['qty']; ?>
                                </span>
                            </td>
                            <td style="font-weight: 600; color: #10B981;">
                                <?php echo formatRupiah($t['total_harga']); ?>
                            </td>
                            <td>
                                <span class="payment-badge <?php echo $payment_class; ?>">
                                    <?php echo $cara; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <!-- TOTAL ROW untuk halaman ini -->
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;">
                            <i class="fas fa-calculator"></i> TOTAL HALAMAN INI:
                        </td>
                        <td style="text-align: center; font-size: 16px; color: #8A2BE2;">
                            <?php echo number_format($running_qty); ?>
                        </td>
                        <td style="font-size: 16px; color: #10B981;">
                            <?php echo formatRupiah($running_total); ?>
                        </td>
                        <td></td>
                    </tr>

                    <!-- TOTAL ROW untuk semua data -->
                    <tr class="total-row" style="background: linear-gradient(135deg, #e0f2fe, #dbeafe);">
                        <td colspan="3" style="text-align: right;">
                            <i class="fas fa-chart-bar"></i> <strong>TOTAL KESELURUHAN:</strong>
                        </td>
                        <td style="text-align: center; font-size: 18px; color: #1D4ED8; font-weight: 800;">
                            <?php echo number_format($total_qty_all); ?>
                        </td>
                        <td style="font-size: 18px; color: #10B981; font-weight: 800;">
                            <?php echo formatRupiah($total_pendapatan_all); ?>
                        </td>
                        <td></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    <?php if (!$all_pages && $total_pages > 1): ?>
        <div class="pagination-container">
            <div class="pagination-info">
                Menampilkan <strong><?php echo min($records_per_page, count($transaksi)); ?></strong> dari
                <strong><?php echo number_format($total_rows); ?></strong> data
                • Halaman <strong><?php echo $page; ?></strong> dari <strong><?php echo $total_pages; ?></strong>
            </div>

            <div class="pagination-controls">
                <!-- First Page -->
                <?php if ($page > 1): ?>
                    <a href="<?php echo generatePaginationLink(1, $selected_produk_ids, $tanggal_dari, $tanggal_sampai, $bulan, $cara_bayar); ?>"
                        class="pagination-btn">
                        <i class="fas fa-angle-double-left"></i>
                        <!-- Awal -->
                    </a>
                <?php else: ?>
                    <span class="pagination-btn" disabled>
                        <i class="fas fa-angle-double-left"></i>
                        <!-- Awal -->
                    </span>
                <?php endif; ?>

                <!-- Previous Page -->
                <?php if ($page > 1): ?>
                    <a href="<?php echo generatePaginationLink($page - 1, $selected_produk_ids, $tanggal_dari, $tanggal_sampai, $bulan, $cara_bayar); ?>"
                        class="pagination-btn">
                        <i class="fas fa-angle-left"></i>
                        <!-- Sebelumnya -->
                    </a>
                <?php else: ?>
                    <span class="pagination-btn" disabled>
                        <i class="fas fa-angle-left"></i>
                        <!-- Sebelumnya -->
                    </span>
                <?php endif; ?>

                <!-- Page Numbers -->
                <?php
                // Tampilkan 5 halaman di sekitar halaman aktif
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);

                // Jika di awal, pastikan tampil 5 halaman
                if ($page <= 3) {
                    $start_page = 1;
                    $end_page = min(5, $total_pages);
                }

                // Jika di akhir, pastikan tampil 5 halaman
                if ($page >= $total_pages - 2) {
                    $start_page = max(1, $total_pages - 4);
                    $end_page = $total_pages;
                }

                // Tampilkan ellipsis di awal jika perlu
                if ($start_page > 1): ?>
                    <span class="pagination-dots">...</span>
                <?php endif; ?>

                <?php for ($p = $start_page; $p <= $end_page; $p++): ?>
                    <a href="<?php echo generatePaginationLink($p, $selected_produk_ids, $tanggal_dari, $tanggal_sampai, $bulan, $cara_bayar); ?>"
                        class="pagination-btn <?php echo $p == $page ? 'active' : ''; ?>">
                        <?php echo $p; ?>
                    </a>
                <?php endfor; ?>

                <!-- Tampilkan ellipsis di akhir jika perlu -->
                <?php if ($end_page < $total_pages): ?>
                    <span class="pagination-dots">...</span>
                <?php endif; ?>

                <!-- Next Page -->
                <?php if ($page < $total_pages): ?>
                    <a href="<?php echo generatePaginationLink($page + 1, $selected_produk_ids, $tanggal_dari, $tanggal_sampai, $bulan, $cara_bayar); ?>"
                        class="pagination-btn">
                        <i class="fas fa-angle-right"></i>
                        <!-- Selanjutnya -->
                    </a>
                <?php else: ?>
                    <span class="pagination-btn" disabled>
                        <i class="fas fa-angle-right"></i>
                        <!-- Selanjutnya -->
                    </span>
                <?php endif; ?>

                <!-- Last Page -->
                <?php if ($page < $total_pages): ?>
                    <a href="<?php echo generatePaginationLink($total_pages, $selected_produk_ids, $tanggal_dari, $tanggal_sampai, $bulan, $cara_bayar); ?>"
                        class="pagination-btn">
                        <i class="fas fa-angle-double-right"></i>
                        <!-- Akhir -->
                    </a>
                <?php else: ?>
                    <span class="pagination-btn" disabled>
                        <i class="fas fa-angle-double-right"></i>
                        <!-- Akhir -->
                    </span>
                <?php endif; ?>
            </div>

            <div class="page-size-selector">
                <a href="<?php echo generateAllPagesLink($selected_produk_ids, $tanggal_dari, $tanggal_sampai, $bulan, $cara_bayar); ?>"
                    class="all-pages-btn">
                    <i class="fas fa-list-alt"></i> Tampilkan Semua
                </a>
            </div>
        </div>
    <?php elseif ($all_pages && $total_rows > $records_per_page): ?>
        <div class="pagination-container">
            <div class="pagination-info">
                Menampilkan semua <strong><?php echo number_format($total_rows); ?></strong> data
            </div>

            <div class="page-size-selector">
                <a href="<?php echo generatePerPageLink($selected_produk_ids, $tanggal_dari, $tanggal_sampai, $bulan, $cara_bayar); ?>"
                    class="per-page-btn">
                    <i class="fas fa-file-alt"></i> 10 Data Per Halaman
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Load jQuery dan Select2 -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // ==================== FILTER TOGGLE FUNCTIONALITY ====================
        const filterToggle = document.getElementById('filterToggle');
        const filterContent = document.getElementById('filterContent');
        const filterSection = document.querySelector('.filter-section');

        // Cek jika filter aktif (ada nilai filter)
        const hasActiveFilters = <?php
        echo ($tanggal_dari || $tanggal_sampai || $bulan ||
            $cara_bayar !== 'semua' || !empty($selected_produk_ids)) ? 'true' : 'false';
        ?>;

        // Jika filter aktif, expand secara default
        if (hasActiveFilters && filterSection) {
            filterSection.classList.add('expanded');
            if (filterContent) {
                filterContent.style.display = 'block';
                // Set tinggi untuk animasi
                setTimeout(() => {
                    filterContent.style.maxHeight = filterContent.scrollHeight + 'px';
                }, 10);
            }
        }

        // Toggle functionality
        if (filterToggle && filterContent) {
            filterToggle.addEventListener('click', function (e) {
                e.preventDefault();
                filterSection.classList.toggle('expanded');

                if (filterSection.classList.contains('expanded')) {
                    filterContent.style.display = 'block';
                    // Trigger animation
                    setTimeout(() => {
                        filterContent.style.maxHeight = filterContent.scrollHeight + 'px';
                    }, 10);
                } else {
                    filterContent.style.maxHeight = '0';
                    setTimeout(() => {
                        filterContent.style.display = 'none';
                    }, 300);
                }
            });
        }

        // ==================== SELECT2 INITIALIZATION (FIX AUTO-FOCUS) ====================
        if ($('#produk-select').length) {
            // Inisialisasi Select2 dengan konfigurasi untuk mencegah auto-focus
            $('#produk-select').select2({
                placeholder: "Pilih produk (bisa lebih dari satu)...",
                allowClear: true,
                width: '100%',
                closeOnSelect: false,
                language: {
                    noResults: function () {
                        return "Produk tidak ditemukan";
                    }
                },
                // Tambahkan konfigurasi untuk mencegah auto-focus
                dropdownAutoWidth: true,
                selectOnClose: false,
                // Nonaktifkan auto-focus saat dropdown terbuka
                dropdownParent: $('#produk-select').parent()
            }).on('select2:open', function (e) {
                // Mencegah auto-focus dengan menghapus fokus dari input pencarian
                setTimeout(function () {
                    // Cari input pencarian Select2 dan hilangkan fokusnya
                    $('.select2-search__field').blur();
                    // Juga hilangkan fokus dari elemen Select2 itu sendiri
                    $(e.target).blur();
                }, 0);
            });

            // Fitur "Select All" untuk produk
            const selectAllOption = function ($select) {
                const $options = $select.find('option');
                const $container = $select.next('.select2-container');

                // Tambahkan checkbox "Select All" jika belum ada
                if (!$container.find('.select-all-checkbox').length) {
                    const $selectAll = $('<div class="select-all-checkbox" style="padding: 8px; border-bottom: 1px solid #eee;">' +
                        '<label style="cursor: pointer; display: flex; align-items: center; gap: 8px;">' +
                        '<input type="checkbox" id="select-all-products">' +
                        '<span style="font-weight: 600;">Pilih Semua Produk</span>' +
                        '</label></div>');

                    $container.find('.select2-dropdown').prepend($selectAll);

                    // Event listener untuk checkbox "Select All"
                    $('#select-all-products').on('change', function () {
                        if ($(this).is(':checked')) {
                            $options.prop('selected', true);
                        } else {
                            $options.prop('selected', false);
                        }
                        $select.trigger('change');
                    });

                    // Update status checkbox saat ada perubahan di select
                    $select.on('change', function () {
                        const selectedCount = $(this).val() ? $(this).val().length : 0;
                        const totalCount = $options.length;
                        const $checkbox = $('#select-all-products');

                        $checkbox.prop('checked', selectedCount === totalCount);
                        $checkbox.prop('indeterminate', selectedCount > 0 && selectedCount < totalCount);
                    });

                    // Inisialisasi status awal
                    const selectedCount = $select.val() ? $select.val().length : 0;
                    const totalCount = $options.length;
                    const $checkbox = $('#select-all-products');

                    $checkbox.prop('checked', selectedCount === totalCount);
                    $checkbox.prop('indeterminate', selectedCount > 0 && selectedCount < totalCount);
                }
            };

            // Panggil fungsi selectAllOption
            setTimeout(() => {
                selectAllOption($('#produk-select'));
            }, 100);

            // Tambahkan event untuk mencegah auto-focus saat form submit
            $('#filterForm').on('submit', function () {
                // Simpan posisi scroll sebelum submit
                sessionStorage.setItem('scrollPos', window.pageYOffset || document.documentElement.scrollTop);

                // Hilangkan fokus dari semua elemen input sebelum submit
                $('input, select, textarea').blur();

                // Nonaktifkan fokus pada Select2
                $('.select2-search__field').prop('disabled', true);

                // Kembalikan setelah delay kecil
                setTimeout(function () {
                    $('.select2-search__field').prop('disabled', false);
                }, 100);
            });
        }

        // ==================== FORM VALIDATION & HANDLING ====================
        // Validasi tanggal (tanggal mulai tidak boleh > tanggal selesai)
        const tanggalDari = document.querySelector('input[name="tanggal_dari"]');
        const tanggalSampai = document.querySelector('input[name="tanggal_sampai"]');

        if (tanggalDari && tanggalSampai) {
            tanggalDari.addEventListener('change', function () {
                if (this.value && tanggalSampai.value && this.value > tanggalSampai.value) {
                    alert('Tanggal mulai tidak boleh lebih besar dari tanggal selesai');
                    this.value = '';
                }
            });

            tanggalSampai.addEventListener('change', function () {
                if (this.value && tanggalDari.value && this.value < tanggalDari.value) {
                    alert('Tanggal selesai tidak boleh lebih kecil dari tanggal mulai');
                    this.value = '';
                }
            });
        }

        // Pastikan form filter selalu reset page ke 1 saat submit
        const filterForm = document.getElementById('filterForm');
        if (filterForm) {
            filterForm.addEventListener('submit', function () {
                const pageInput = this.querySelector('input[name="page"]');
                const allPagesInput = this.querySelector('input[name="all_pages"]');

                if (pageInput) pageInput.value = 1;
                if (allPagesInput) allPagesInput.value = 0;
            });
        }

        // ==================== RESPONSIVE HANDLING ====================
        // Handle window resize untuk filter content height
        window.addEventListener('resize', function () {
            if (filterSection && filterSection.classList.contains('expanded') && filterContent) {
                filterContent.style.maxHeight = filterContent.scrollHeight + 'px';
            }
        });

        // ==================== KEYBOARD NAVIGATION ====================
        // Mencegah form submit saat tekan Enter di dalam dropdown Select2
        $(document).on('keydown', '.select2-search__field', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });

        // Close filter section dengan Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && filterSection && filterSection.classList.contains('expanded')) {
                filterSection.classList.remove('expanded');
                if (filterContent) {
                    filterContent.style.maxHeight = '0';
                    setTimeout(() => {
                        filterContent.style.display = 'none';
                    }, 300);
                }
            }
        });

        // ==================== FOCUS MANAGEMENT ====================
        // Nonaktifkan auto-focus pada semua elemen
        setTimeout(() => {
            // Hapus focus dari semua elemen yang sedang fokus
            if (document.activeElement &&
                document.activeElement.tagName !== 'BODY' &&
                !document.activeElement.classList.contains('select2-search__field')) {
                document.activeElement.blur();
            }

            // Nonaktifkan auto-focus di browser
            const inputs = document.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.removeAttribute('autofocus');
                // Tambahkan event untuk mencegah auto-focus
                input.addEventListener('focus', function (e) {
                    // Jika ini adalah input Select2 search, biarkan user fokus manual
                    if (!this.classList.contains('select2-search__field')) {
                        // Untuk input lain, kita bisa biarkan atau atur sesuai kebutuhan
                    }
                });
            });
        }, 100);

        // ==================== RESTORE SCROLL POSITION ====================
        // Pulihkan posisi scroll setelah filter diterapkan
        window.addEventListener('load', function () {
            const scrollPos = sessionStorage.getItem('scrollPos');
            if (scrollPos) {
                setTimeout(function () {
                    window.scrollTo(0, parseInt(scrollPos));
                    sessionStorage.removeItem('scrollPos');
                }, 100);
            }
        });
    });
</script>
<?php include '../../includes/footer.php'; ?>

<?php
// Fungsi untuk generate link pagination
function generatePaginationLink($page, $produk_ids, $tanggal_dari, $tanggal_sampai, $bulan, $cara_bayar)
{
    $params = [];

    if (!empty($produk_ids)) {
        foreach ($produk_ids as $id) {
            $params['produk_ids[]'] = $id;
        }
    }

    if ($tanggal_dari)
        $params['tanggal_dari'] = $tanggal_dari;
    if ($tanggal_sampai)
        $params['tanggal_sampai'] = $tanggal_sampai;
    if ($bulan)
        $params['bulan'] = $bulan;
    if ($cara_bayar && $cara_bayar !== 'semua')
        $params['cara_bayar'] = $cara_bayar;

    $params['page'] = $page;
    $params['all_pages'] = 0;

    return 'pendapatan.php?' . http_build_query($params);
}

// Fungsi untuk generate link all pages
function generateAllPagesLink($produk_ids, $tanggal_dari, $tanggal_sampai, $bulan, $cara_bayar)
{
    $params = [];

    if (!empty($produk_ids)) {
        foreach ($produk_ids as $id) {
            $params['produk_ids[]'] = $id;
        }
    }

    if ($tanggal_dari)
        $params['tanggal_dari'] = $tanggal_dari;
    if ($tanggal_sampai)
        $params['tanggal_sampai'] = $tanggal_sampai;
    if ($bulan)
        $params['bulan'] = $bulan;
    if ($cara_bayar && $cara_bayar !== 'semua')
        $params['cara_bayar'] = $cara_bayar;

    $params['all_pages'] = 1;

    return 'pendapatan.php?' . http_build_query($params);
}

// Fungsi untuk generate link per page (10 data per halaman)
function generatePerPageLink($produk_ids, $tanggal_dari, $tanggal_sampai, $bulan, $cara_bayar)
{
    $params = [];

    if (!empty($produk_ids)) {
        foreach ($produk_ids as $id) {
            $params['produk_ids[]'] = $id;
        }
    }

    if ($tanggal_dari)
        $params['tanggal_dari'] = $tanggal_dari;
    if ($tanggal_sampai)
        $params['tanggal_sampai'] = $tanggal_sampai;
    if ($bulan)
        $params['bulan'] = $bulan;
    if ($cara_bayar && $cara_bayar !== 'semua')
        $params['cara_bayar'] = $cara_bayar;

    return 'pendapatan.php?' . http_build_query($params);
}
?>