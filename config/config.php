<?php
// config/config.php - PERBAIKAN BASE_URL

// Set session configuration BEFORE starting session
if (session_status() === PHP_SESSION_NONE) {
    // Session configuration
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0);
    ini_set('session.gc_maxlifetime', 28800);

    // Set session name
    session_name('DinoManagementSession');

    // Start session
    session_start();
}

// =============================================
// BASE URL & PATH CONFIGURATION - FIXED
// =============================================

// Get protocol
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";

// Get server host
$host = $_SERVER['HTTP_HOST'];

// Compute BASE_URL relative to document root so it remains constant
// Normalize paths to forward slashes for cross-platform compatibility
$doc_root = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
$project_root = str_replace('\\', '/', realpath(dirname(__FILE__) . '/..'));

// Derive the web path to project root by removing document root prefix
$base_path = '';
if ($doc_root && strpos($project_root, $doc_root) === 0) {
    $base_path = substr($project_root, strlen($doc_root));
}

// Ensure leading slash but no trailing slash (except root)
$base_path = '/' . trim($base_path, '/');
if ($base_path === '/') {
    $base_path = '';
}

// Build BASE_URL using host + base path
define('BASE_URL', $protocol . '://' . $host . $base_path);

// Define SITE_NAME
define('SITE_NAME', 'Dino Management - Es Teh Dino');

// Define ROOT_PATH (absolute path to project root)
define('ROOT_PATH', realpath(dirname(__FILE__) . '/..'));

// Define APP_PATH
define('APP_PATH', __DIR__);

// File upload configuration
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('UPLOAD_PATH', ROOT_PATH . '/assets/uploads/');
define('UPLOAD_URL', BASE_URL . '/assets/uploads/');

// Timezone configuration
date_default_timezone_set('Asia/Jakarta');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', ROOT_PATH . '/logs/php_errors.log');

// Include functions
require_once __DIR__ . '/functions.php';

// Include database connection
require_once __DIR__ . '/database.php';

// Security: Regenerate session ID setiap 5 menit
if (!isset($_SESSION['last_regenerate']) || (time() - $_SESSION['last_regenerate'] > 300)) {
    session_regenerate_id(true);
    $_SESSION['last_regenerate'] = time();
}
?>