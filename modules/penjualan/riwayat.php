<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

if (!isLoggedIn()) {
    redirect('../../login.php');
}

$page_title = 'Riwayat Penjualan';
$database = new Database();
$db = $database->getConnection();

// === AMBIL PARAMETER FILTER ===
$cari_produk = trim($_GET['cari_produk'] ?? '');
$tanggal_dari = $_GET['tanggal_dari'] ?? '';
$tanggal_sampai = $_GET['tanggal_sampai'] ?? '';
$cara_bayar = $_GET['cara_bayar'] ?? '';

// Bangun query dasar
// Query baru: Group per order, hitung total qty
$sql = "SELECT 
            j.id, 
            j.kode_transaksi, 
            j.tanggal, 
            j.waktu, 
            j.total_harga, 
            j.cara_bayar,
            COUNT(d.id) as jumlah_item,
            SUM(d.qty) as total_qty
        FROM penjualan j
        LEFT JOIN detail_penjualan d ON d.penjualan_id = j.id
        WHERE 1=1";

$params = [];

// Filter tetap sama
if ($cari_produk !== '') {
    $sql .= " AND EXISTS (
                SELECT 1 FROM detail_penjualan dd 
                JOIN produk pp ON pp.id = dd.produk_id 
                WHERE dd.penjualan_id = j.id 
                AND pp.nama_produk LIKE ?
              )";
    $params[] = "%$cari_produk%";
}
if ($tanggal_dari !== '') {
    $sql .= " AND j.tanggal >= ?";
    $params[] = $tanggal_dari;
}
if ($tanggal_sampai !== '') {
    $sql .= " AND j.tanggal <= ?";
    $params[] = $tanggal_sampai;
}
if ($cara_bayar !== '' && $cara_bayar !== 'semua') {
    $sql .= " AND j.cara_bayar = ?";
    $params[] = $cara_bayar;
}

$sql .= " GROUP BY j.id ORDER BY j.tanggal DESC, j.waktu DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>

<div style="background: white; border-radius: 12px; padding: 18px; box-shadow: 0 6px 18px rgba(0,0,0,0.06);">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:16px;">
        <h2 style="margin:0;">Riwayat Penjualan</h2>
        <a href="<?php echo BASE_URL; ?>/modules/penjualan/index.php"
            style="background:#F5F5F5; padding:8px 12px; border-radius:8px; text-decoration:none; color:#333;">
            Input Penjualan
        </a>
    </div>

    <!-- NOTIFIKASI HAPUS -->
    <?php if (isset($_GET['delete']) && $_GET['delete'] == 'success'): ?>
        <div style="background:#10B981; color:white; padding:10px; border-radius:8px; margin-bottom:15px;">
            Transaksi berhasil dihapus!
        </div>
    <?php endif; ?>

    <!-- FORM FILTER -->
    <form method="GET" style="background:#f8fafc; padding:14px; border-radius:10px; margin-bottom:18px;">
        <div style="display:grid; gap:12px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
            <div>
                <label style="font-size:13px; font-weight:600; color:#374151;">Cari Produk</label>
                <input type="text" name="cari_produk" value="<?php echo htmlspecialchars($cari_produk); ?>"
                    placeholder="Nama produk..."
                    style="width:100%; padding:9px; border-radius:8px; border:1px solid #d1d5db; margin-top:4px;">
            </div>
            <div>
                <label style="font-size:13px; font-weight:600; color:#374151;">Tanggal Dari</label>
                <input type="date" name="tanggal_dari" value="<?php echo htmlspecialchars($tanggal_dari); ?>"
                    style="width:100%; padding:9px; border-radius:8px; border:1px solid #d1d5db; margin-top:4px;">
            </div>
            <div>
                <label style="font-size:13px; font-weight:600; color:#374151;">Tanggal Sampai</label>
                <input type="date" name="tanggal_sampai" value="<?php echo htmlspecialchars($tanggal_sampai); ?>"
                    style="width:100%; padding:9px; border-radius:8px; border:1px solid #d1d5db; margin-top:4px;">
            </div>
            <div>
                <label style="font-size:13px; font-weight:600; color:#374151;">Cara Bayar</label>
                <select name="cara_bayar"
                    style="width:100%; padding:9px; border-radius:8px; border:1px solid #d1d5db; margin-top:4px;">
                    <option value="semua" <?php echo $cara_bayar === '' || $cara_bayar === 'semua' ? 'selected' : ''; ?>>
                        Semua
                    </option>
                    <option value="tunai" <?php echo $cara_bayar === 'tunai' ? 'selected' : ''; ?>>Tunai</option>
                    <option value="qris" <?php echo $cara_bayar === 'qris' ? 'selected' : ''; ?>>QRIS</option>
                    <option value="transfer" <?php echo $cara_bayar === 'transfer' ? 'selected' : ''; ?>>Transfer</option>
                    <option value="split" <?php echo $cara_bayar === 'split' ? 'selected' : ''; ?>>Split</option>
                    <option value="non_tunai" <?php echo $cara_bayar === 'non_tunai' ? 'selected' : ''; ?>>Non Tunai
                    </option>
                </select>
            </div>
        </div>
        <div style="margin-top:12px; display:flex; gap:10px;">
            <button type="submit"
                style="background:linear-gradient(135deg,#9ACD32,#8A2BE2); color:white; border:none; padding:10px 16px; border-radius:8px; font-weight:600;">
                Filter
            </button>
            <a href="riwayat.php"
                style="background:#e5e7eb; color:#374151; padding:10px 16px; border-radius:8px; text-decoration:none;">
                Reset
            </a>
        </div>
    </form>

    <!-- TABEL HASIL -->
    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:14px;">
            <thead>
                <tr style="background:#f8fafc; text-align:left;">
                    <th style="padding:12px 10px; border-bottom:2px solid #e2e8f0;">No</th>
                    <th style="padding:12px 10px; border-bottom:2px solid #e2e8f0;">Tanggal & Jam</th>
                    <th style="padding:12px 10px; border-bottom:2px solid #e2e8f0;">Produk</th>
                    <th style="padding:12px 10px; border-bottom:2px solid #e2e8f0;">Qty</th>
                    <th style="padding:12px 10px; border-bottom:2px solid #e2e8f0;">Total</th>
                    <th style="padding:12px 10px; border-bottom:2px solid #e2e8f0;">Bayar</th>
                    <th style="padding:12px 10px; border-bottom:2px solid #e2e8f0;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; padding:20px; color:#6b7280;">
                            Tidak ada data yang sesuai filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1;
                    foreach ($orders as $o): ?>
                        <?php
                        $cara = strtoupper($o['cara_bayar']);
                        if ($cara == 'NON_TUNAI')
                            $cara = 'NON TUNAI';
                        ?>
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:12px 10px;"><?php echo $no++; ?></td>
                            <td style="padding:12px 10px;">
                                <?php echo date('d/m/Y', strtotime($o['tanggal'])) . ' <span style="color:#64748b;">' . date('H:i', strtotime($o['waktu'])) . '</span>'; ?>
                            </td>
                            <td style="padding:12px 10px; color:#1f2937;">
                                <strong><?php echo htmlspecialchars($o['kode_transaksi']); ?></strong><br>
                                <small style="color:#6b7280;"><?php echo $o['jumlah_item']; ?> item •
                                    <?php echo $o['total_qty']; ?> pcs</small>
                            </td>
                            <td style="padding:12px 10px; text-align:center;">
                                <?php echo $o['total_qty']; ?>
                            </td>
                            <td style="padding:12px 10px; color:#1f2937;"><?php echo formatRupiah($o['total_harga']); ?></td>
                            <td style="padding:12px 10px; font-weight:600; color:#7c3aed;"><?php echo $cara; ?></td>
                            <td style="padding:12px 10px;">
                                <a href="detail.php?id=<?php echo $o['id']; ?>"
                                    style="color:#2563eb; margin-right:10px;">Detail</a>
                                <a href="javascript:void(0)"
                                    onclick="if(confirm('Yakin hapus ORDER ini?\nKode: <?php echo $o['kode_transaksi']; ?>\nSemua item akan terhapus!')) window.location='hapus.php?id=<?php echo $o['id']; ?>'"
                                    style="color:#ef4444;">Hapus</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>