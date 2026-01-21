<?php
// modules/produk/hapus.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

if (!isLoggedIn())
    redirect('login.php');

// Cek apakah ada parameter id
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirect('index.php');
}

$id = (int) $_GET['id'];

// Koneksi database
$database = new Database();
$db = $database->getConnection();

// Ambil data produk untuk mendapatkan nama dan gambar
$query_select = "SELECT nama_produk, gambar_produk FROM produk WHERE id = :id";
$stmt_select = $db->prepare($query_select);
$stmt_select->bindParam(':id', $id);
$stmt_select->execute();

if ($stmt_select->rowCount() === 0) {
    redirect('index.php?error=produk_tidak_ditemukan');
}

$produk = $stmt_select->fetch(PDO::FETCH_ASSOC);
// Prefer soft-delete (mark as deleted) so we don't violate FK constraints.
$canSoftDelete = false;
if (function_exists('columnExists')) {
    // If column exists, we can soft-delete
    if (columnExists($db, 'produk', 'is_deleted')) {
        $canSoftDelete = true;
    } else {
        // Try to add the column if we have privileges
        try {
            $db->exec("ALTER TABLE produk ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0");
            $canSoftDelete = true;
        } catch (Exception $ex) {
            // Could not add column (insufficient privileges), will fallback to hard-delete flow
            error_log('Could not add is_deleted column: ' . $ex->getMessage());
            $canSoftDelete = false;
        }
    }
}

if ($canSoftDelete) {
    // Mark product as deleted (soft-delete). This is safe even if product is referenced.
    try {
        $stmt_up = $db->prepare("UPDATE produk SET is_deleted = 1 WHERE id = :id");
        $stmt_up->bindParam(':id', $id, PDO::PARAM_INT);
        $ok = $stmt_up->execute();

        if ($ok) {
            // Do not unlink image on soft-delete to preserve file for historical references
            $defaultReturn = 'modules/produk/index.php?success=produk_dihapus&nama=' . urlencode($produk['nama_produk']);
            if (isset($_GET['return']) && !empty($_GET['return'])) {
                $ret = urldecode($_GET['return']);
                if (!preg_match('#^https?://#i', $ret)) {
                    $ret = ltrim($ret, '/');
                    $sep = strpos($ret, '?') === false ? '?' : '&';
                    redirect($ret . $sep . 'success=produk_dihapus&nama=' . urlencode($produk['nama_produk']));
                }
            }
            redirect($defaultReturn);
        } else {
            $errReturn = (isset($_GET['return']) && !preg_match('#^https?://#i', urldecode($_GET['return']))) ? ltrim(urldecode($_GET['return']), '/') . '?error=gagal_hapus' : 'modules/produk/index.php?error=gagal_hapus';
            redirect($errReturn);
        }
    } catch (Exception $e) {
        error_log('Soft delete failed: ' . $e->getMessage());
        $errReturn = (isset($_GET['return']) && !preg_match('#^https?://#i', urldecode($_GET['return']))) ? ltrim(urldecode($_GET['return']), '/') . '?error=error_db' : 'modules/produk/index.php?error=error_db';
        redirect($errReturn);
    }
} else {
    // Fallback: attempt hard delete but prevent FK violation
    $check_rel = "SELECT COUNT(*) as cnt FROM detail_penjualan WHERE produk_id = :id";
    $stmt_check = $db->prepare($check_rel);
    $stmt_check->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt_check->execute();
    $refCount = (int) ($stmt_check->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

    if ($refCount > 0) {
        // Produk terkait transaksi, jangan hapus. Kembalikan pesan error yang informatif.
        $errorMsg = 'produk_terkait';
        $defaultErr = 'modules/produk/index.php?error=' . $errorMsg . '&nama=' . urlencode($produk['nama_produk']);
        if (isset($_GET['return']) && !empty($_GET['return'])) {
            $ret = urldecode($_GET['return']);
            if (!preg_match('#^https?://#i', $ret)) {
                $ret = ltrim($ret, '/');
                $sep = strpos($ret, '?') === false ? '?' : '&';
                redirect($ret . $sep . 'error=' . $errorMsg . '&nama=' . urlencode($produk['nama_produk']));
            }
        }
        redirect($defaultErr);
    }

    // No references, safe to hard delete
    $query_delete = "DELETE FROM produk WHERE id = :id";
    try {
        $stmt_delete = $db->prepare($query_delete);
        $stmt_delete->bindParam(':id', $id, PDO::PARAM_INT);
        $ok = $stmt_delete->execute();

        if ($ok) {
            // Hapus gambar setelah data berhasil dihapus
            if (!empty($produk['gambar_produk']) && file_exists(root_path($produk['gambar_produk']))) {
                @unlink(root_path($produk['gambar_produk']));
            }

            $defaultReturn = 'modules/produk/index.php?success=produk_dihapus&nama=' . urlencode($produk['nama_produk']);
            if (isset($_GET['return']) && !empty($_GET['return'])) {
                $ret = urldecode($_GET['return']);
                if (!preg_match('#^https?://#i', $ret)) {
                    $ret = ltrim($ret, '/');
                    $sep = strpos($ret, '?') === false ? '?' : '&';
                    redirect($ret . $sep . 'success=produk_dihapus&nama=' . urlencode($produk['nama_produk']));
                }
            }
            redirect($defaultReturn);
        } else {
            $errReturn = (isset($_GET['return']) && !preg_match('#^https?://#i', urldecode($_GET['return']))) ? ltrim(urldecode($_GET['return']), '/') . '?error=gagal_hapus' : 'modules/produk/index.php?error=gagal_hapus';
            redirect($errReturn);
        }
    } catch (PDOException $e) {
        error_log('Produk delete failed: ' . $e->getMessage());
        $err = 'error_db';
        $defaultErr = 'modules/produk/index.php?error=' . $err . '&nama=' . urlencode($produk['nama_produk']);
        if (isset($_GET['return']) && !empty($_GET['return'])) {
            $ret = urldecode($_GET['return']);
            if (!preg_match('#^https?://#i', $ret)) {
                $ret = ltrim($ret, '/');
                $sep = strpos($ret, '?') === false ? '?' : '&';
                redirect($ret . $sep . 'error=' . $err . '&nama=' . urlencode($produk['nama_produk']));
            }
        }
        redirect($defaultErr);
    }
}

?>