<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

if (!isLoggedIn()) {
    redirect('../../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/modules/penjualan/index.php?error=Invalid request');
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();

    // Ambil data produk (array karena bisa multiple)
    $produk_ids = $_POST['produk_id'] ?? [];
    $qtys = $_POST['qty'] ?? [];
    $metode = $_POST['metode'];

    $allowed_metode = ['tunai', 'qris', 'transfer', 'split', 'non_tunai'];
    if (!in_array($metode, $allowed_metode)) {
        throw new Exception("Metode pembayaran tidak valid.");
    }
    $cara_bayar = $metode;

    // Validasi minimal ada 1 produk
    if (empty($produk_ids) || empty($qtys)) {
        throw new Exception("Minimal harus ada 1 produk.");
    }

    // Validasi setiap produk
    $total_harga = 0;
    $product_details = [];

    foreach ($produk_ids as $index => $produk_id) {
        $produk_id = intval($produk_id);
        $qty = intval($qtys[$index] ?? 0);

        if ($qty <= 0 || $produk_id <= 0) {
            throw new Exception("Jumlah atau produk tidak valid pada baris " . ($index + 1) . ".");
        }

        $stmt_produk = $db->prepare("SELECT jenis, harga_jual, stok, nama_produk FROM produk WHERE id = ?");
        $stmt_produk->execute([$produk_id]);
        $produk_data = $stmt_produk->fetch(PDO::FETCH_ASSOC);

        if (!$produk_data) {
            throw new Exception("Produk tidak ditemukan pada baris " . ($index + 1) . ".");
        }

        $harga_satuan = floatval($produk_data['harga_jual']);
        $stok = intval($produk_data['stok']);
        $jenis = $produk_data['jenis'];

        // Cek stok hanya jika alat_bahan
        if ($jenis === 'alat_bahan' && $stok < $qty) {
            throw new Exception("Stok tidak cukup untuk produk: " . $produk_data['nama_produk'] . ". Stok tersedia: " . $stok);
        }

        $subtotal = $qty * $harga_satuan;
        $total_harga += $subtotal;

        // Simpan detail produk untuk insert nanti
        $product_details[] = [
            'produk_id' => $produk_id,
            'qty' => $qty,
            'harga_satuan' => $harga_satuan,
            'subtotal' => $subtotal,
            'jenis' => $jenis,
            'stok' => $stok
        ];
    }

    $keterangan = count($product_details) > 1 ? 'Multi Produk' : 'Single Produk';

    // ==================== GENERATE KODE TRANSAKSI UNIK DENGAN RETRY LOGIC ====================
    $max_retries = 5; // Maksimal percobaan jika terjadi duplikat
    $retry_count = 0;
    $insert_success = false;
    $kode_transaksi = '';
    $penjualan_id = null;

    while (!$insert_success && $retry_count < $max_retries) {
        try {
            // ========== GENERATE KODE TRANSAKSI UNIK ==========
            // 1. Ambil sequence terakhir untuk hari ini
            $stmt_seq = $db->prepare("
                SELECT kode_transaksi 
                FROM penjualan 
                WHERE DATE(tanggal) = CURDATE() 
                AND kode_transaksi LIKE 'ORDER-" . date('Ymd') . "-%'
                ORDER BY id DESC 
                LIMIT 1
            ");
            $stmt_seq->execute();
            $last_kode = $stmt_seq->fetchColumn();

            $sequence = 1; // Default jika belum ada transaksi hari ini

            if ($last_kode) {
                // 2. Parse sequence number dari kode terakhir
                // Format: ORDER-20251223-001
                $parts = explode('-', $last_kode);
                if (count($parts) >= 3) {
                    $last_sequence = intval($parts[2]);
                    $sequence = $last_sequence + 1 + $retry_count; // Tambah retry_count untuk variasi
                }
            }

            // 3. Format kode transaksi
            if ($sequence > 999) {
                // Jika lebih dari 999, gunakan kombinasi timestamp dan microtime untuk keunikan
                $microtime = explode(' ', microtime());
                $unique_suffix = substr($microtime[1], -4) . substr($microtime[0], 2, 3);
                $kode_transaksi = 'ORDER-' . date('Ymd') . '-T' . $unique_suffix;
            } else {
                $kode_transaksi = 'ORDER-' . date('Ymd') . '-' . str_pad($sequence, 3, '0', STR_PAD_LEFT);
            }
            // ========== END GENERATE KODE ==========

            // Insert penjualan dengan kode unik
            $stmt_penjualan = $db->prepare("INSERT INTO penjualan (kode_transaksi, tanggal, waktu, total_harga, cara_bayar, keterangan) VALUES (?, CURDATE(), CURTIME(), ?, ?, ?)");
            $stmt_penjualan->execute([$kode_transaksi, $total_harga, $cara_bayar, $keterangan]);

            $penjualan_id = $db->lastInsertId();
            $insert_success = true;

        } catch (PDOException $e) {
            // Cek jika error karena duplicate entry (error code 1062 untuk MySQL)
            if ($e->errorInfo[1] == 1062) {
                $retry_count++;

                // Debug log untuk testing
                error_log("Duplicate entry detected for kode_transaksi: $kode_transaksi. Retry attempt: $retry_count");

                // Tunggu sebentar sebelum retry (exponential backoff)
                usleep(100000 * $retry_count); // 100ms, 200ms, 300ms...

                // Continue ke loop berikutnya untuk generate kode baru
                continue;
            } else {
                // Error lain, lempar exception
                throw $e;
            }
        }
    }

    if (!$insert_success) {
        throw new Exception("Gagal membuat kode transaksi unik setelah $max_retries percobaan. Silakan coba lagi.");
    }

    // Verifikasi penjualan_id sudah ada
    if (!$penjualan_id) {
        throw new Exception("Gagal mendapatkan ID transaksi.");
    }
    // ==================== END GENERATE KODE TRANSAKSI ====================

    // Insert ke detail_penjualan untuk setiap produk
    $stmt_detail = $db->prepare("INSERT INTO detail_penjualan (penjualan_id, produk_id, qty, harga_satuan, subtotal) VALUES (?, ?, ?, ?, ?)");

    foreach ($product_details as $product) {
        $stmt_detail->execute([
            $penjualan_id,
            $product['produk_id'],
            $product['qty'],
            $product['harga_satuan'],
            $product['subtotal']
        ]);

        // Update stok hanya jika alat_bahan
        if ($product['jenis'] === 'alat_bahan') {
            $stmt_update_stok = $db->prepare("UPDATE produk SET stok = stok - ? WHERE id = ?");
            $stmt_update_stok->execute([$product['qty'], $product['produk_id']]);
        }
    }

    $db->commit();

    // Redirect dengan success message
    $success_msg = "Penjualan berhasil disimpan! Kode: $kode_transaksi (" . count($product_details) . " produk)";
    header("Location: " . BASE_URL . "/modules/penjualan/index.php?success=" . urlencode($success_msg));
    exit;

} catch (Exception $e) {
    // Rollback transaction jika ada error
    if ($db->inTransaction()) {
        $db->rollback();
    }

    // Debug log untuk error
    error_log("Error in proses_jual.php: " . $e->getMessage());

    // Redirect dengan error message
    header("Location: " . BASE_URL . "/modules/penjualan/index.php?error=" . urlencode($e->getMessage()));
    exit;
}