<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

if (!isLoggedIn()) {
    redirect('../../login.php');
}

if (!isset($_GET['id'])) {
    redirect('riwayat.php');
}

$id = intval($_GET['id']);
$database = new Database();
$db = $database->getConnection();

// Query utama: ambil data penjualan + detail + harga_jual dari produk
$sql = "SELECT j.*, d.qty, d.harga_satuan, d.subtotal, 
               p.nama_produk, p.harga_jual
        FROM penjualan j
        JOIN detail_penjualan d ON d.penjualan_id = j.id
        JOIN produk p ON p.id = d.produk_id
        WHERE j.id = ?
        LIMIT 1";

$stmt = $db->prepare($sql);
$stmt->execute([$id]);
$transaksi = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaksi) {
    die('<div style="padding:30px; text-align:center; color:#ef4444; font-size:18px;">Transaksi tidak ditemukan.</div>');
}

// Ambil semua item (untuk future multi-item, sudah siap)
$items_sql = "SELECT d.qty, d.harga_satuan, d.subtotal, 
                     p.nama_produk, p.harga_jual
              FROM detail_penjualan d
              JOIN produk p ON p.id = d.produk_id
              WHERE d.penjualan_id = ?";
$items_stmt = $db->prepare($items_sql);
$items_stmt->execute([$id]);
$items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div style="background:white; padding:18px; border-radius:12px; box-shadow:0 6px 18px rgba(0,0,0,0.06);">
    <div
        style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
        <h2 style="margin:0; font-size:19px;">
            Detail Penjualan #<?php echo $transaksi['id']; ?>
            <small style="font-weight:normal; color:#6b7280;">• <?php echo $transaksi['kode_transaksi']; ?></small>
        </h2>
        <div style="display:flex; gap:8px;">
            <a href="<?php echo BASE_URL; ?>/modules/penjualan/riwayat.php"
                style="background:#F5F5F5; padding:9px 14px; border-radius:8px; text-decoration:none; color:#333;">
                Kembali
            </a>
            <a href="javascript:void(0)" onclick="if(confirm('Yakin ingin menghapus transaksi ini secara permanen?\nKode: <?php echo $transaksi['kode_transaksi']; ?>')) 
                        window.location='hapus.php?id=<?php echo $id; ?>'"
                style="background:#ef4444; color:white; padding:9px 14px; border-radius:8px; text-decoration:none; font-size:13.5px;">
                Hapus Transaksi
            </a>
        </div>
    </div>

    <!-- INFO UTAMA -->
    <div style="background:#f8fafc; padding:14px; border-radius:10px; margin-bottom:16px; font-size:14.5px;">
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px;">
            <div>
                <strong>Tanggal & Jam</strong><br>
                <span style="color:#374151;">
                    <?php echo date('d/m/Y', strtotime($transaksi['tanggal'])); ?>
                    <span style="color:#64748b;"><?php echo date('H:i', strtotime($transaksi['waktu'])); ?></span>
                </span>
            </div>
            <div>
                <strong>Cara Bayar</strong><br>
                <?php
                $cara = strtoupper($transaksi['cara_bayar']);
                if ($cara == 'NON_TUNAI')
                    $cara = 'NON TUNAI';
                echo '<span style="color:#7c3aed; font-weight:600;">' . $cara . '</span>';
                ?>
            </div>
            <div>
                <strong>Total Transaksi</strong><br>
                <span style="font-size:18px; font-weight:700; color:#1f2937;">
                    <?php echo formatRupiah($transaksi['total_harga']); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- DAFTAR ITEM -->
    <div style="background:#fafafa; border-radius:10px; padding:14px;">
        <h3 style="margin:0 0 12px 0; font-size:16px; color:#1f2937;">Item yang Dijual</h3>
        <?php foreach ($items as $item): ?>
            <div
                style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px dashed #e5e7eb;">
                <div style="flex:1;">
                    <div style="font-weight:600; color:#1f2937;">
                        <?php echo htmlspecialchars($item['nama_produk']); ?>
                    </div>
                    <div style="font-size:13px; color:#6b7280; margin-top:4px;">
                        Qty: <strong><?php echo $item['qty']; ?></strong> ×
                        <?php echo formatRupiah($item['harga_satuan']); ?>
                        <?php if ($item['harga_satuan'] != $item['harga_jual']): ?>
                            <span style="color:#f59e0b;">(Master: <?php echo formatRupiah($item['harga_jual']); ?>)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="font-weight:700; color:#1f2937; font-size:15px;">
                    <?php echo formatRupiah($item['subtotal']); ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- TOTAL AKHIR -->
        <div
            style="margin-top:14px; padding-top:12px; border-top:2px solid #9ACD32; display:flex; justify-content:space-between; font-size:17px;">
            <strong>Total Bayar</strong>
            <strong style="color:#1f2937;"><?php echo formatRupiah($transaksi['total_harga']); ?></strong>
        </div>
    </div>

    <?php if (!empty($transaksi['keterangan'])): ?>
        <div style="margin-top:16px; padding:12px; background:#fef3c7; border-radius:8px; font-size:13.5px;">
            <strong>Catatan:</strong> <?php echo nl2br(htmlspecialchars($transaksi['keterangan'])); ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>