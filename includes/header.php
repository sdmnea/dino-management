<?php
// includes/header.php - FIXED NAVIGATION

// Start session jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_name('DinoManagementSession');
    session_start();
}

// Cek apakah sudah login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

// Session timeout (8 jam)
$session_timeout = 28800;
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > $session_timeout)) {
    session_unset();
    session_destroy();
    header("Location: " . BASE_URL . "/login.php?error=timeout");
    exit();
}

// Update last activity
$_SESSION['last_activity'] = time();

// User info
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$nama_lengkap = $_SESSION['nama_lengkap'] ?? 'Admin';

// Determine active page
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Dino Management</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* Global Styles */
        :root {
            --primary: #9ACD32;
            /* --primarynew : #74B652; */
            --secondary: #FFD700;
            --accent: #8A2BE2;
            --dark: #2F1800;
            --light: #F5F5F5;
            --gray: #6B7280;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            color: var(--dark);
            padding-bottom: 70px;
            /* Space for mobile nav */
        }

        /* Top Bar */
        .top-bar {
            /* background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%); */
            background-color: #8A2BE2;
            color: white;
            padding: 15px 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            position: -webkit-sticky;
            /* For Safari */
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        /* Desktop: tetap sticky */
        @media (min-width: 769px) {
            .top-bar {
                position: -webkit-sticky;
                position: sticky;
                top: 0;
            }
        }

        /* Mobile: non-sticky */
        @media (max-width: 768px) {
            .top-bar {
                position: static;
            }

            body {
                padding-bottom: 60px;
                /* Kurangi karena header tidak sticky */
            }
        }

        /* Desktop Layout - Tampil di desktop saja */
        .desktop-layout {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            width: 100%;
        }

        /* Mobile Layout - Sembunyi di desktop */
        .mobile-layout {
            display: none;
        }

        /* Mobile Grid Layout */
        .mobile-header-grid {
            display: grid;
            grid-template-columns: 1fr auto 50px;
            gap: 10px;
            align-items: center;
            width: 100%;
        }

        .mobile-col-left {
            text-align: left;
            overflow: hidden;
        }

        .mobile-col-center {
            text-align: left;
            overflow: hidden;
        }

        .mobile-col-right {
            text-align: center;
        }

        .mobile-page-title {
            font-size: 16px;
            font-weight: 700;
            color: white;
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mobile-date {
            font-size: 11px;
            opacity: 0.9;
            color: white;
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mobile-user-name {
            font-size: 13px;
            font-weight: 600;
            color: white;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mobile-user-username {
            font-size: 11px;
            opacity: 0.9;
            color: white;
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mobile-logout-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .mobile-logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
        }

        /* Responsive: Mobile */
        @media (max-width: 768px) {
            .desktop-layout {
                display: none;
            }

            .mobile-layout {
                display: block;
                width: 100%;
            }

            .top-content {
                padding: 10px 15px;
            }

            .mobile-header-grid {
                grid-template-columns: 1fr 1fr 50px;
                gap: 12px;
            }

            .mobile-col-left {
                min-width: 0;
            }

            .mobile-col-center {
                min-width: 0;
                text-align: left;
                padding-left: 5px;
            }

            .mobile-page-title {
                font-size: 15px;
            }

            .mobile-user-name {
                font-size: 12px;
            }

            .mobile-logout-btn {
                width: 36px;
                height: 36px;
                font-size: 14px;
            }
        }

        /* Responsive: Very Small Mobile */
        @media (max-width: 480px) {
            .mobile-header-grid {
                grid-template-columns: 1fr 1fr 45px;
                gap: 8px;
            }

            .mobile-page-title {
                font-size: 14px;
            }

            .mobile-date {
                font-size: 10px;
            }

            .mobile-user-name {
                font-size: 11px;
            }

            .mobile-user-username {
                font-size: 10px;
            }

            .mobile-logout-btn {
                width: 34px;
                height: 34px;
                font-size: 13px;
            }

            .top-content {
                padding: 8px 10px;
            }
        }

        /* Tambahkan untuk konten utama agar tidak tertutup header */
        @media (max-width: 768px) {
            .main-container {
                padding-top: 15px;
            }

            /* Jika ada konten dengan margin-top sebelumnya */
            .content-with-top-margin {
                margin-top: 10px !important;
            }
        }

        .top-content {
            width: 100%;
        }

        .page-title {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }

        .page-subtitle {
            font-size: 12px;
            opacity: 0.9;
            margin: 5px 0 0 0;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            font-size: 14px;
        }

        .user-username {
            font-size: 12px;
            opacity: 0.9;
        }

        .logout-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
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
            padding: 10px 0;
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
        }

        .nav-item {
            flex: 1;
            text-align: center;
            text-decoration: none;
            color: var(--gray);
            font-size: 11px;
            transition: all 0.3s;
            padding: 5px 0;
        }

        .nav-item i {
            font-size: 20px;
            display: block;
            margin-bottom: 4px;
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

        /* Main Container */
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .top-content {
                flex-direction: column;
                text-align: center;
            }

            .user-section {
                width: 100%;
                justify-content: center;
            }

            .user-info {
                text-align: center;
            }

            .main-container {
                padding: 15px;
            }

            .nav-item {
                font-size: 10px;
            }

            .nav-item i {
                font-size: 18px;
            }
        }

        @media (max-width: 480px) {
            .main-container {
                padding: 10px;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.3s ease-out;
        }
    </style>
</head>

<body>
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="top-content">
            <!-- Desktop Layout (Tetap seperti semula) -->
            <div class="desktop-layout">
                <div>
                    <h1 class="page-title"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h1>
                    <p class="page-subtitle"><?php echo date('d F Y'); ?></p>
                </div>
                <div class="user-section">
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($nama_lengkap); ?></div>
                        <div class="user-username"><?php echo htmlspecialchars($username); ?></div>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>

            <!-- Mobile Layout (Baru) -->
            <div class="mobile-layout">
                <div class="mobile-header-grid">
                    <div class="mobile-col-left">
                        <div class="mobile-page-title"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?>
                        </div>
                        <div class="mobile-date"><?php echo date('d F Y'); ?></div>
                    </div>

                    <div class="mobile-col-center">
                        <div class="mobile-user-name"><?php echo htmlspecialchars($nama_lengkap); ?></div>
                        <div class="mobile-user-username"><?php echo htmlspecialchars($username); ?></div>
                    </div>

                    <div class="mobile-col-right">
                        <a href="<?php echo BASE_URL; ?>/logout.php" class="mobile-logout-btn">
                            <i class="fas fa-sign-out-alt"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container fade-in">
        <!-- Content akan dimasukkan di sini -->