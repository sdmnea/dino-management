<?php
// test_functions.php
require_once 'config/config.php';

echo "<h1>Test Functions</h1>";

// Test 1: Cek apakah fungsi isLoggedIn ada
if (function_exists('isLoggedIn')) {
    echo "<p style='color: green;'>✓ Fungsi isLoggedIn() ADA</p>";
} else {
    echo "<p style='color: red;'>✗ Fungsi isLoggedIn() TIDAK ADA</p>";
}

// Test 2: Cek apakah fungsi redirect ada
if (function_exists('redirect')) {
    echo "<p style='color: green;'>✓ Fungsi redirect() ADA</p>";
} else {
    echo "<p style='color: red;'>✗ Fungsi redirect() TIDAK ADA</p>";
}

// Test 3: Cek apakah fungsi formatRupiah ada
if (function_exists('formatRupiah')) {
    echo "<p style='color: green;'>✓ Fungsi formatRupiah() ADA</p>";
    echo "<p>Test formatRupiah(1000000): " . formatRupiah(1000000) . "</p>";
} else {
    echo "<p style='color: red;'>✗ Fungsi formatRupiah() TIDAK ADA</p>";
}

// Test 4: Cek apakah session berjalan
echo "<h2>Session Info</h2>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session Status: " . session_status() . "</p>";

// Test 5: Cek apakah bisa koneksi database
echo "<h2>Database Test</h2>";
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        echo "<p style='color: green;'>✓ Koneksi database BERHASIL</p>";
        
        // Test query sederhana
        $stmt = $conn->query("SELECT DATABASE() as db");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>Database: " . $result['db'] . "</p>";
    } else {
        echo "<p style='color: red;'>✗ Koneksi database GAGAL</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error database: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='login.php'>Kembali ke Login</a></p>";
?>