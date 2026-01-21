<?php
require_once 'config/config.php';
require_once 'config/database.php';

$db = (new Database())->getConnection();

echo "<h2>Table Schema Diagnostics</h2>\n\n";

// Check penjualan table
echo "<h3>penjualan table structure:</h3>\n";
$stmt = $db->query("DESCRIBE penjualan");
echo "<pre>";
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
echo "</pre>\n";

// Check detail_penjualan table
echo "<h3>detail_penjualan table structure:</h3>\n";
$stmt = $db->query("DESCRIBE detail_penjualan");
echo "<pre>";
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
echo "</pre>\n";

// Check produk table
echo "<h3>produk table structure:</h3>\n";
$stmt = $db->query("DESCRIBE produk");
echo "<pre>";
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
echo "</pre>\n";

echo "<h3>Attempting INSERT to test which variant works:</h3>\n";

try {
    echo "Attempt 1: INSERT with kode_transaksi, metode...<br>";
    $db->exec("INSERT INTO penjualan (tanggal, total_harga, metode, kode_transaksi) VALUES (NOW(), 10000, 'tunai', 'TRX-TEST-001')");
    echo "SUCCESS<br>";
    $db->exec("DELETE FROM penjualan WHERE kode_transaksi = 'TRX-TEST-001'");
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "<br>";
}

try {
    echo "Attempt 2: INSERT with kode_transaksi only...<br>";
    $db->exec("INSERT INTO penjualan (tanggal, total_harga, kode_transaksi) VALUES (NOW(), 10000, 'TRX-TEST-002')");
    echo "SUCCESS<br>";
    $db->exec("DELETE FROM penjualan WHERE kode_transaksi = 'TRX-TEST-002'");
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "<br>";
}

try {
    echo "Attempt 3: INSERT with metode only...<br>";
    $db->exec("INSERT INTO penjualan (tanggal, total_harga, metode) VALUES (NOW(), 10000, 'tunai')");
    echo "SUCCESS<br>";
    $db->exec("DELETE FROM penjualan WHERE metode = 'tunai'");
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "<br>";
}

try {
    echo "Attempt 4: INSERT with minimal columns...<br>";
    $db->exec("INSERT INTO penjualan (tanggal, total_harga) VALUES (NOW(), 10000)");
    echo "SUCCESS<br>";
    $db->exec("DELETE FROM penjualan WHERE total_harga = 10000");
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "<br>";
}

?>
