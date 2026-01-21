<?php
// includes/auth_check.php

// Cek session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    // Redirect ke halaman login
    header("Location: ../login.php");
    exit();
}

// Cek session timeout (8 jam = 28800 detik)
$session_timeout = 28800;
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > $session_timeout)) {
    // Session expired
    session_unset();
    session_destroy();
    header("Location: ../login.php?error=session_expired");
    exit();
}

// Perbarui waktu login
$_SESSION['login_time'] = time();

// User data
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$nama_lengkap = $_SESSION['nama_lengkap'] ?? 'Admin';
?>