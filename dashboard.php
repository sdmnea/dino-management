<?php
// dashboard.php - FIXED VERSION

// Include config file FIRST - ini akan memulai session dengan konfigurasi yang benar
require_once 'config/config.php';

// Cek login menggunakan fungsi yang sudah ada
if (!isLoggedIn()) {
    redirect('login.php');
}

// Koneksi database
$database = new Database();
$db = $database->getConnection();

// Jika koneksi gagal
if (!$db) {
    die("Koneksi database gagal. Silakan cek konfigurasi.");
}

// Data untuk dashboard (Optimized queries)
$today = date('Y-m-d');
$month = date('Y-m');

// 1. Total Produk
$query_produk = "SELECT COUNT(*) as total FROM produk WHERE jenis = 'es_teh'";
$stmt_produk = $db->prepare($query_produk);
$stmt_produk->execute();
$total_produk = $stmt_produk->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 2. Penjualan Hari Ini
$query_penjualan_hari = "SELECT 
    COUNT(*) as jumlah_transaksi,
    COALESCE(SUM(total_harga), 0) as total_penjualan
    FROM penjualan 
    WHERE DATE(tanggal) = :today";
$stmt_penjualan_hari = $db->prepare($query_penjualan_hari);
$stmt_penjualan_hari->bindParam(':today', $today);
$stmt_penjualan_hari->execute();
$penjualan_hari = $stmt_penjualan_hari->fetch(PDO::FETCH_ASSOC);
$jumlah_transaksi = $penjualan_hari['jumlah_transaksi'] ?? 0;
$total_penjualan_hari = $penjualan_hari['total_penjualan'] ?? 0;

// 3. Pendapatan Bulan Ini
$query_pendapatan_bulan = "SELECT COALESCE(SUM(total_harga), 0) as total 
    FROM penjualan 
    WHERE DATE_FORMAT(tanggal, '%Y-%m') = :month";
$stmt_pendapatan_bulan = $db->prepare($query_pendapatan_bulan);
$stmt_pendapatan_bulan->bindParam(':month', $month);
$stmt_pendapatan_bulan->execute();
$total_pendapatan_bulan = $stmt_pendapatan_bulan->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 4. Stok Menipis
$query_stok_menipis = "SELECT COUNT(*) as total 
    FROM produk 
    WHERE stok <= min_stok AND stok > 0";
$stmt_stok_menipis = $db->prepare($query_stok_menipis);
$stmt_stok_menipis->execute();
$total_stok_menipis = $stmt_stok_menipis->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// 5. Produk Terlaris (7 hari terakhir)
$query_produk_terlaris = "SELECT 
    p.nama_produk,
    SUM(d.qty) as total_terjual
    FROM detail_penjualan d
    JOIN produk p ON d.produk_id = p.id
    JOIN penjualan j ON d.penjualan_id = j.id
    WHERE j.tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY p.id
    ORDER BY total_terjual DESC
    LIMIT 5";
$stmt_produk_terlaris = $db->prepare($query_produk_terlaris);
$stmt_produk_terlaris->execute();
$produk_terlaris = $stmt_produk_terlaris->fetchAll(PDO::FETCH_ASSOC);

// 6. Data untuk Chart (7 hari terakhir)
$query_chart_data = "SELECT 
    DATE(tanggal) as tanggal,
    COUNT(*) as jumlah_transaksi,
    COALESCE(SUM(total_harga), 0) as total_penjualan
    FROM penjualan
    WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(tanggal)
    ORDER BY tanggal";
$stmt_chart_data = $db->prepare($query_chart_data);
$stmt_chart_data->execute();
$chart_data = $stmt_chart_data->fetchAll(PDO::FETCH_ASSOC);

// Format data untuk Chart.js
$chart_labels = [];
$chart_transaksi = [];
$chart_penjualan = [];

foreach ($chart_data as $row) {
    $chart_labels[] = date('d/m', strtotime($row['tanggal']));
    $chart_transaksi[] = (int) $row['jumlah_transaksi'];
    $chart_penjualan[] = (float) $row['total_penjualan'];
}

// 7. Pengeluaran Bulan Ini
$query_pengeluaran = "SELECT COALESCE(SUM(jumlah), 0) as total 
    FROM pengeluaran 
    WHERE DATE_FORMAT(tanggal, '%Y-%m') = :month";
$stmt_pengeluaran = $db->prepare($query_pengeluaran);
$stmt_pengeluaran->bindParam(':month', $month);
$stmt_pengeluaran->execute();
$total_pengeluaran = $stmt_pengeluaran->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Hitung profit
$profit_bulan = $total_pendapatan_bulan - $total_pengeluaran;

// Set page title
$page_title = "Dashboard";

// User info dari session
$username = $_SESSION['username'];
$nama_lengkap = $_SESSION['nama_lengkap'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Dino Management</title>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #9ACD32;
            --secondary: #FFD700;
            --accent: #8A2BE2;
            --dark: #2F1800;
            --light: #F5F5F5;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            padding: 15px;
            padding-bottom: 80px;
            /* Space for mobile nav */
        }

        /* Mobile Navigation */
        .mobile-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 2px solid var(--primary);
            z-index: 1000;
            display: flex;
            justify-content: space-around;
            padding: 12px 0;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
        }

        .nav-item {
            text-align: center;
            color: #666;
            text-decoration: none;
            font-size: 11px;
            flex: 1;
            transition: all 0.3s ease;
        }

        .nav-item i {
            font-size: 22px;
            display: block;
            margin-bottom: 6px;
            color: var(--accent);
        }

        .nav-item.active {
            color: var(--primary);
        }

        .nav-item.active i {
            color: var(--primary);
        }

        .nav-item:hover {
            color: var(--primary);
            transform: translateY(-2px);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .dashboard-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            color: white;
            padding: 25px 20px;
            position: relative;
            overflow: hidden;
        }

        .dashboard-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="10" cy="10" r="8" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="30" r="12" fill="rgba(255,255,255,0.05)"/><circle cx="30" cy="90" r="10" fill="rgba(255,255,255,0.08)"/></svg>');
        }

        .header-content {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .welcome-section h1 {
            font-size: 24px;
            margin-bottom: 5px;
            color: white;
        }

        .welcome-section p {
            opacity: 0.9;
            font-size: 14px;
        }

        .user-info {
            background: rgba(255, 255, 255, 0.15);
            padding: 12px 18px;
            border-radius: 12px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .user-info p {
            margin: 3px 0;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logout-btn {
            background: white;
            color: var(--accent);
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 14px;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .dashboard-content {
            padding: 25px 20px;
        }

        .welcome-banner {
            background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            right: -50%;
            bottom: -50%;
            background: radial-gradient(circle at 30% 30%, rgba(255, 255, 255, 0.1) 1%, transparent 20%);
        }

        .welcome-content {
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .date-box {
            background: rgba(255, 255, 255, 0.2);
            padding: 10px 15px;
            border-radius: 10px;
            text-align: center;
            min-width: 80px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
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

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: inherit;
        }

        .stat-card:nth-child(1) {
            border-color: var(--primary);
        }

        .stat-card:nth-child(2) {
            border-color: var(--secondary);
        }

        .stat-card:nth-child(3) {
            border-color: var(--accent);
        }

        .stat-card:nth-child(4) {
            border-color: var(--dark);
        }

        .stat-card:hover {
            transform: translateY(-5px);
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
            background: rgba(154, 205, 50, 0.1);
        }

        .stat-card:nth-child(2) .stat-icon {
            background: rgba(255, 215, 0, 0.1);
        }

        .stat-card:nth-child(3) .stat-icon {
            background: rgba(138, 43, 226, 0.1);
        }

        .stat-card:nth-child(4) .stat-icon {
            background: rgba(47, 24, 0, 0.1);
        }

        .stat-icon i {
            font-size: 24px;
        }

        .stat-card:nth-child(1) .stat-icon i {
            color: var(--primary);
        }

        .stat-card:nth-child(2) .stat-icon i {
            color: var(--secondary);
        }

        .stat-card:nth-child(3) .stat-icon i {
            color: var(--accent);
        }

        .stat-card:nth-child(4) .stat-icon i {
            color: var(--dark);
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
            line-height: 1;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .stat-subtext {
            font-size: 12px;
            color: #888;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 30px;
        }

        .action-btn {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            padding: 18px;
            border-radius: 12px;
            border: none;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(154, 205, 50, 0.3);
        }

        .action-btn.secondary {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
        }

        .action-btn.accent {
            background: linear-gradient(135deg, var(--accent), #6a11cb);
        }

        .action-btn.light {
            background: #F5F5F5;
            color: var(--dark);
        }

        .notifications {
            margin-top: 30px;
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .notification-item {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .notification-item.warning {
            background: #FEF3C7;
            border-left-color: #F59E0B;
            color: #92400E;
        }

        .notification-item.success {
            background: #D1FAE5;
            border-left-color: #10B981;
            color: #065F46;
        }

        .notification-item.info {
            background: #DBEAFE;
            border-left-color: #3B82F6;
            color: #1E40AF;
        }

        .notification-item.danger {
            background: #FEE2E2;
            border-left-color: #EF4444;
            color: #991B1B;
        }

        .notification-icon {
            font-size: 20px;
            flex-shrink: 0;
        }

        .dashboard-footer {
            padding: 20px;
            background: var(--light);
            border-top: 1px solid #E5E7EB;
            text-align: center;
            color: #666;
            font-size: 13px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-header {
                padding: 20px 15px;
            }

            .header-content {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .welcome-section h1 {
                font-size: 22px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .actions-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-content {
                padding: 20px 15px;
            }

            .stat-card {
                padding: 18px;
            }

            .stat-value {
                font-size: 26px;
            }

            .nav-item {
                font-size: 10px;
            }

            .nav-item i {
                font-size: 20px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
                padding-bottom: 70px;
            }

            .container {
                border-radius: 15px;
            }

            .stat-value {
                font-size: 24px;
            }

            .action-btn {
                padding: 15px;
                font-size: 14px;
            }
        }

        /* Animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--accent);
        }
        
        /* Navigation Modal Styles untuk Dashboard */
        .nav-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: flex-start;
            z-index: 9999;
            overflow-y: auto;
            padding: 20px;
        }
        
        .nav-modal.active {
            display: flex;
        }
        
        .nav-modal-content {
            background: white;
            border-radius: 12px;
            width: 100%;
            max-width: 400px;
            margin-top: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .nav-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .nav-modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
        }
        
        .nav-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s ease;
        }
        
        .nav-modal-close:hover {
            background-color: #f3f4f6;
            color: #374151;
        }
        
        .nav-modal-body {
            padding: 16px;
        }
        
        /* Navigation Sections */
        .nav-section {
            margin-bottom: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .nav-section-header {
            display: flex;
            align-items: center;
            padding: 14px 16px;
            background-color: #f9fafb;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .nav-section-header:hover {
            background-color: #f3f4f6;
        }
        
        .nav-section-header i {
            margin-right: 12px;
            color: #4b5563;
            font-size: 16px;
            width: 20px;
            text-align: center;
        }
        
        .nav-section-header span {
            flex: 1;
            font-weight: 600;
            color: #374151;
            font-size: 15px;
        }
        
        .nav-section-toggle {
            background: none;
            border: none;
            cursor: pointer;
            color: #6b7280;
            padding: 4px;
            transition: transform 0.3s ease;
        }
        
        .nav-section.active .nav-section-toggle {
            transform: rotate(180deg);
        }
        
        .nav-section-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .nav-section.active .nav-section-content {
            max-height: 200px;
        }
        
        /* Navigation Subitems */
        .nav-subitem {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            padding-left: 44px;
            text-decoration: none;
            color: #4b5563;
            border-top: 1px solid #e5e7eb;
            transition: all 0.2s ease;
        }
        
        .nav-subitem:hover {
            background-color: #f3f4f6;
            color: #1f2937;
        }
        
        .nav-subitem i {
            margin-right: 12px;
            color: #6b7280;
            font-size: 14px;
            width: 16px;
            text-align: center;
        }
        
        .nav-subitem span {
            font-size: 14px;
            font-weight: 500;
        }
        
        /* Mobile Navigation Trigger */
        .nav-trigger {
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .nav-modal {
                padding: 16px;
                align-items: center;
            }
            
            .nav-modal-content {
                max-width: 90%;
                margin-top: 0;
            }
            
            .nav-modal-header {
                padding: 16px;
            }
            
            .nav-modal-body {
                padding: 12px;
            }
            
            .nav-section-header {
                padding: 12px;
            }
            
            .nav-subitem {
                padding: 10px 12px;
                padding-left: 40px;
            }
        }
        
        @media (max-width: 480px) {
            .nav-modal {
                padding: 12px;
            }
            
            .nav-modal-content {
                max-width: 95%;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation Modal -->
    <!-- <div id="nav-modal" class="nav-modal">
        <div class="nav-modal-content">
            <div class="nav-modal-header">
                <h3>Navigasi</h3>
                <button type="button" class="nav-modal-close">&times;</button>
            </div>
            
            <div class="nav-modal-body">
                <div class="nav-section">
                    <div class="nav-section-header">
                        <i class="fas fa-chart-bar"></i>
                        <span>Laporan</span>
                        <button type="button" class="nav-section-toggle">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <div class="nav-section-content">
                        <a href="<?php echo BASE_URL; ?>/modules/laporan/pendapatan.php" class="nav-subitem">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Laporan Pendapatan</span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/modules/laporan/sdm.php" class="nav-subitem">
                            <i class="fas fa-users"></i>
                            <span>Laporan SDM</span>
                        </a>
                    </div>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-header">
                        <i class="fas fa-boxes"></i>
                        <span>Inventory</span>
                        <button type="button" class="nav-section-toggle">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <div class="nav-section-content">
                        <a href="<?php echo BASE_URL; ?>/modules/inventory/pengeluaran.php" class="nav-subitem">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <span>Input Pengeluaran</span>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/modules/inventory/gudang.php" class="nav-subitem">
                            <i class="fas fa-warehouse"></i>
                            <span>Gudang</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div> -->

    <!-- Mobile Navigation di dashboard.php - DIUBAH -->
    <!-- <nav class="mobile-nav">
        <a href="<?php echo BASE_URL; ?>/dashboard.php" class="nav-item active">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="<?php echo BASE_URL; ?>/modules/produk/index.php" class="nav-item">
            <i class="fas fa-box"></i>
            <span>Produk</span>
        </a>
        <a href="<?php echo BASE_URL; ?>/modules/penjualan/index.php" class="nav-item">
            <i class="fas fa-cash-register"></i>
            <span>Jual</span>
        </a>
        <a href="javascript:void(0);" class="nav-item nav-trigger">
            <i class="fas fa-bars"></i>
            <span>Navigasi</span>
        </a>
    </nav> -->

    <div class="container fade-in">
        <div class="dashboard-header">
            <div class="header-content">
                <div class="welcome-section">
                    <h1>Dino Management System</h1>
                    <p>Kelola bisnis es teh Anda dengan mudah</p>
                </div>

                <div class="user-info">
                    <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($nama_lengkap); ?></p>
                    <p><i class="fas fa-user-tag"></i> <?php echo htmlspecialchars($username); ?></p>
                </div>

                <a href="<?php echo BASE_URL; ?>/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <div class="dashboard-content">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="welcome-content">
                    <div>
                        <h2 style="color: white; margin-bottom: 8px; font-size: 20px;"><?php echo getGreeting(); ?>,
                            <?php echo htmlspecialchars($nama_lengkap); ?>! 👋</h2>
                        <p style="opacity: 0.9; font-size: 14px;">Selamat datang di sistem management Es Teh Dino</p>
                    </div>
                    <div class="date-box">
                        <div style="font-size: 12px; opacity: 0.9;">Hari Ini</div>
                        <div style="font-weight: 600; font-size: 20px;"><?php echo date('d'); ?></div>
                        <div style="font-size: 12px;"><?php echo date('F Y'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <div>
                            <div class="stat-value"><?php echo $total_produk; ?></div>
                            <div class="stat-label">Produk Terdaftar</div>
                            <div class="stat-subtext">Jumlah produk es teh</div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div>
                            <div class="stat-value"><?php echo $jumlah_transaksi; ?></div>
                            <div class="stat-label">Transaksi Hari Ini</div>
                            <div class="stat-subtext" style="color: #9ACD32; font-weight: 600;">
                                <?php echo formatRupiah($total_penjualan_hari); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div>
                            <div class="stat-value"><?php echo formatRupiah($profit_bulan); ?></div>
                            <div class="stat-label">Profit Bulan Ini</div>
                            <div class="stat-subtext">
                                Pendapatan: <?php echo formatRupiah($total_pendapatan_bulan); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-content">
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div>
                            <div class="stat-value"><?php echo $total_stok_menipis; ?></div>
                            <div class="stat-label">Stok Menipis</div>
                            <?php if ($total_stok_menipis > 0): ?>
                                <div class="stat-subtext" style="color: #EF4444; font-weight: 600;">
                                    Perlu restock segera!
                                </div>
                            <?php else: ?>
                                <div class="stat-subtext" style="color: #10B981;">
                                    Semua stok aman
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <h3 style="margin-bottom: 15px; color: var(--dark); display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-bolt" style="color: var(--secondary);"></i> Aksi Cepat
            </h3>
            <div class="actions-grid">
                <a href="<?php echo BASE_URL; ?>/modules/penjualan/index.php" class="action-btn">
                    <i class="fas fa-cash-register"></i> Input Penjualan
                </a>
                <a href="<?php echo BASE_URL; ?>/modules/produk/tambah.php" class="action-btn secondary">
                    <i class="fas fa-plus"></i> Tambah Produk
                </a>
                <a href="<?php echo BASE_URL; ?>/modules/laporan/index.php" class="action-btn accent">
                    <i class="fas fa-file-alt"></i> Lihat Laporan
                </a>
                <a href="<?php echo BASE_URL; ?>/modules/penjualan/riwayat.php" class="action-btn light">
                    <i class="fas fa-history"></i> Riwayat Penjualan
                </a>
            </div>

            <!-- Notifications -->
            <div class="notifications">
                <h3 style="margin-bottom: 15px; color: var(--dark); display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-bell" style="color: var(--accent);"></i> Notifikasi Sistem
                </h3>
                <div>
                    <?php if ($total_stok_menipis > 0): ?>
                        <div class="notification-item warning">
                            <div class="notification-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600;">Stok Menipis!</div>
                                <div style="font-size: 14px;">Ada <?php echo $total_stok_menipis; ?> produk yang stoknya
                                    hampir habis. Segera lakukan restock.</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="notification-item success">
                            <div class="notification-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600;">Stok Aman</div>
                                <div style="font-size: 14px;">Semua stok produk dalam kondisi aman.</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($profit_bulan < 0): ?>
                        <div class="notification-item danger">
                            <div class="notification-icon">
                                <i class="fas fa-chart-line-down"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600;">Kerugian Bulan Ini</div>
                                <div style="font-size: 14px;">Bulan ini mengalami kerugian sebesar
                                    <?php echo formatRupiah(abs($profit_bulan)); ?>. Periksa pengeluaran dan strategi
                                    penjualan.</div>
                            </div>
                        </div>
                    <?php elseif ($profit_bulan > 0): ?>
                        <div class="notification-item success">
                            <div class="notification-icon">
                                <i class="fas fa-chart-line-up"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600;">Profit Positif</div>
                                <div style="font-size: 14px;">Bulan ini mendapatkan profit sebesar
                                    <?php echo formatRupiah($profit_bulan); ?>. Pertahankan!</div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($jumlah_transaksi == 0): ?>
                        <div class="notification-item info">
                            <div class="notification-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600;">Belum Ada Transaksi</div>
                                <div style="font-size: 14px;">Belum ada transaksi hari ini. Yuk mulai input penjualan
                                    pertama!</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="notification-item success">
                            <div class="notification-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600;">Transaksi Hari Ini</div>
                                <div style="font-size: 14px;">Hari ini sudah ada <?php echo $jumlah_transaksi; ?> transaksi
                                    dengan total <?php echo formatRupiah($total_penjualan_hari); ?>.</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- <div class="dashboard-footer">
            <p>&copy; <?php echo date('Y'); ?> Es Teh Dino Management System v1.0</p>
            <p style="margin-top: 5px; font-size: 12px; color: #666;">
                <span style="color: #10B981;">● Online</span> |
                Session: <?php echo substr(session_id(), 0, 12) . '...'; ?> |
                Server: <?php echo date('H:i:s'); ?>
            </p>
        </div> -->
    </div>

    <script>
        console.log('Dashboard loaded successfully for user: <?php echo $username; ?>');
        console.log('Session ID: <?php echo session_id(); ?>');

        // Session warning sebelum timeout (15 menit sebelum timeout 8 jam)
        setTimeout(() => {
            if (confirm('Session Anda akan segera berakhir dalam 15 menit. Lanjutkan session?')) {
                // Refresh page untuk memperpanjang session
                window.location.reload();
            }
        }, 23400000); // 6.5 jam (1.5 jam sebelum timeout 8 jam)

        // Auto-refresh setiap 10 menit untuk menjaga session
        setInterval(() => {
            // Ping server untuk menjaga session
            fetch(window.location.href, {
                method: 'HEAD',
                cache: 'no-store'
            }).catch(err => console.log('Session ping skipped'));
        }, 600000); // 10 menit

        // Add active class to nav items
        document.addEventListener('DOMContentLoaded', function () {
            const navItems = document.querySelectorAll('.nav-item');
            const currentPage = window.location.pathname.split('/').pop();

            navItems.forEach(item => {
                item.classList.remove('active');
                if (item.getAttribute('href') === currentPage) {
                    item.classList.add('active');
                }
            });

            // Performance monitoring
            const startTime = performance.now();
            window.addEventListener('load', function () {
                const loadTime = performance.now() - startTime;
                console.log('Page loaded in', (loadTime / 1000).toFixed(2), 'seconds');

                // Show notification if load time is too long
                if (loadTime > 3000) {
                    const notification = document.createElement('div');
                    notification.className = 'notification-item warning';
                    notification.innerHTML = `
                        <div class="notification-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600;">Performance Warning</div>
                            <div style="font-size: 14px;">Halaman dimuat dalam ${(loadTime / 1000).toFixed(1)} detik</div>
                        </div>
                    `;
                    document.querySelector('.notifications').prepend(notification);
                }
            });
            
            // Navigation Modal Functionality untuk Dashboard
            const navModal = document.getElementById('nav-modal');
            const navTrigger = document.querySelector('.nav-trigger');
            const navClose = document.querySelector('.nav-modal-close');
            const navSections = document.querySelectorAll('.nav-section');
            
            // Open modal when clicking nav trigger
            if (navTrigger) {
                navTrigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    navModal.classList.add('active');
                    document.body.style.overflow = 'hidden'; // Prevent scrolling behind modal
                });
            }
            
            // Close modal when clicking close button
            if (navClose) {
                navClose.addEventListener('click', function() {
                    navModal.classList.remove('active');
                    document.body.style.overflow = ''; // Restore scrolling
                });
            }
            
            // Close modal when clicking outside content
            navModal.addEventListener('click', function(e) {
                if (e.target === navModal) {
                    navModal.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
            
            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && navModal.classList.contains('active')) {
                    navModal.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
            
            // Toggle navigation sections
            navSections.forEach(section => {
                const header = section.querySelector('.nav-section-header');
                const toggleBtn = section.querySelector('.nav-section-toggle');
                
                function toggleSection() {
                    section.classList.toggle('active');
                }
                
                if (header) {
                    header.addEventListener('click', toggleSection);
                }
                
                if (toggleBtn) {
                    toggleBtn.addEventListener('click', function(e) {
                        e.stopPropagation(); // Prevent double trigger
                        toggleSection();
                    });
                }
            });
            
            // Auto-expand section if current page matches
            navSections.forEach(section => {
                const links = section.querySelectorAll('.nav-subitem');
                let shouldExpand = false;
                
                links.forEach(link => {
                    if (window.location.href.includes(link.getAttribute('href'))) {
                        shouldExpand = true;
                    }
                });
                
                if (shouldExpand) {
                    section.classList.add('active');
                }
            });
        });
    </script>
<?php
// Set current page untuk footer
$current_page = 'dashboard.php';

// Include footer yang sama dengan modul lain
include 'includes/footer.php';
?>
</body>

</html>
