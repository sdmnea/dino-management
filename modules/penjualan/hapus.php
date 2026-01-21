<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

if (!isLoggedIn()) redirect('login.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) redirect('modules/penjualan/riwayat.php');

$db = (new Database())->getConnection();

try {
    $db->beginTransaction();

    // Get details to restore stock
    $stmt = $db->prepare('SELECT produk_id, qty FROM detail_penjualan WHERE penjualan_id = :id');
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($details as $d) {
        $u = $db->prepare('UPDATE produk SET stok = stok + :qty WHERE id = :id');
        $u->bindParam(':qty', $d['qty'], PDO::PARAM_INT);
        $u->bindParam(':id', $d['produk_id'], PDO::PARAM_INT);
        $u->execute();
    }

    // Delete details
    $delt = $db->prepare('DELETE FROM detail_penjualan WHERE penjualan_id = :id');
    $delt->bindParam(':id', $id, PDO::PARAM_INT);
    $delt->execute();

    // Delete penjualan
    $del = $db->prepare('DELETE FROM penjualan WHERE id = :id');
    $del->bindParam(':id', $id, PDO::PARAM_INT);
    $del->execute();

    $db->commit();
    header('Location: ' . BASE_URL . '/modules/penjualan/riwayat.php?msg=' . urlencode('Data berhasil dihapus'));
    exit();
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log($e->getMessage());
    header('Location: ' . BASE_URL . '/modules/penjualan/riwayat.php?error=' . urlencode('Gagal menghapus'));
    exit();
}
