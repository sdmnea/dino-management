<?php
// index.php - FIXED VERSION
require_once 'config/config.php';

// Redirect berdasarkan status login
if (isLoggedIn()) {
    redirect('dashboard.php');
} else {
    redirect('login.php');
}
?>