<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

if (!isLoggedIn()) {
    redirect('../../login.php');
}

$database = new Database();
$db = $database->getConnection();

// Ambil kategori
$kategori_sql = "SELECT id, nama_kategori FROM kategori_produk ORDER BY nama_kategori";
$kategori_stmt = $db->query($kategori_sql);
$kategori = $kategori_stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();

        // Input data
        $nama_produk = trim($_POST['nama_produk']);
        $kategori_id = !empty($_POST['kategori_id']) ? intval($_POST['kategori_id']) : NULL; // Optional, NULL jika kosong
        $jenis = $_POST['jenis'];
        $harga_beli = floatval($_POST['harga_beli']);
        $harga_jual = floatval($_POST['harga_jual']);
        $stok = isset($_POST['stok']) ? intval($_POST['stok']) : 0; // Default 0
        $satuan = trim($_POST['satuan']);
        $min_stok = intval($_POST['min_stok']);
        $deskripsi = trim($_POST['deskripsi'] ?? '');

        // Validasi dasar
        if (empty($nama_produk) || empty($jenis) || empty($satuan) || $harga_jual <= 0) {
            throw new Exception("Data tidak lengkap atau invalid.");
        }

        // Stok wajib hanya jika jenis 'alat_bahan'
        if ($jenis === 'alat_bahan' && $stok < 0) {
            throw new Exception("Stok wajib untuk alat/bahan.");
        }

        // Generate kode_produk otomatis
        $kode_produk = 'PROD-' . date('Ymd') . '-' . rand(100, 999);

        // Insert
        $insert_sql = "INSERT INTO produk (kode_produk, nama_produk, kategori_id, jenis, harga_beli, harga_jual, stok, satuan, min_stok, deskripsi) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($insert_sql);
        $stmt->execute([$kode_produk, $nama_produk, $kategori_id, $jenis, $harga_beli, $harga_jual, $stok, $satuan, $min_stok, $deskripsi]);

        $db->commit();
        header("Location: index.php?success=Produk berhasil ditambahkan");
        exit;
    } catch (Exception $e) {
        $db->rollback();
        header("Location: tambah.php?error=" . urlencode($e->getMessage()));
        exit;
    }
}

include '../../includes/header.php';
?>

<div style="background:white; padding:15px; border-radius:12px; box-shadow:0 6px 18px rgba(0,0,0,0.06);">
    <h2 style="margin:0 0 12px 0;">Tambah Produk</h2>
    <?php if (isset($_GET['error'])): ?>
        <div style="background:#EF4444; color:white; padding:10px; border-radius:8px; margin-bottom:12px;"><?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>

    <form method="POST">
        <div style="display:grid; gap:10px; grid-template-columns:1fr 1fr;">
            <div>
                <label style="font-weight:600; font-size:13px;">Nama Produk</label>
                <input type="text" name="nama_produk" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #e5e7eb;">
            </div>
            <div>
                <label style="font-weight:600; font-size:13px;">Kategori</label>
                <select name="kategori_id" style="width:100%; padding:10px; border-radius:8px; border:1px solid #e5e7eb;">
                    <option value="">-- Pilih Kategori --</option>
                    <?php foreach ($kategori as $k): ?>
                        <option value="<?php echo $k['id']; ?>"><?php echo htmlspecialchars($k['nama_kategori']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-weight:600; font-size:13px;">Jenis</label>
                <select name="jenis" id="jenis" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #e5e7eb;">
                    <option value="es_teh">Es Teh</option>
                    <option value="alat_bahan">Alat/Bahan</option>
                </select>
            </div>
            <div>
                <label style="font-weight:600; font-size:13px;">Harga Beli</label>
                <input type="number" name="harga_beli" min="0" value="0" style="width:100%; padding:10px; border-radius:8px; border:1px solid #e5e7eb;">
            </div>
            <div>
                <label style="font-weight:600; font-size:13px;">Harga Jual</label>
                <input type="number" name="harga_jual" required min="1" style="width:100%; padding:10px; border-radius:8px; border:1px solid #e5e7eb;">
            </div>
            <div>
                <label style="font-weight:600; font-size:13px;">Stok Awal</label>
                <input type="number" name="stok" id="stok_field" min="0" value="0" style="width:100%; padding:10px; border-radius:8px; border:1px solid #e5e7eb;">
            </div>
            <div>
                <label style="font-weight:600; font-size:13px;">Satuan</label>
                <input type="text" name="satuan" required placeholder="gelas/pcs/kg" style="width:100%; padding:10px; border-radius:8px; border:1px solid #e5e7eb;">
            </div>
            <div>
                <label style="font-weight:600; font-size:13px;">Min. Stok</label>
                <input type="number" name="min_stok" min="0" value="5" style="width:100%; padding:10px; border-radius:8px; border:1px solid #e5e7eb;">
            </div>
        </div>
        <div style="margin-top:12px;">
            <label style="font-weight:600; font-size:13px;">Deskripsi</label>
            <textarea name="deskripsi" style="width:100%; height:80px; padding:10px; border-radius:8px; border:1px solid #e5e7eb;"></textarea>
        </div>
        <button type="submit" style="margin-top:12px; background:linear-gradient(135deg,#9ACD32,#8A2BE2); color:white; border:none; padding:12px 16px; border-radius:10px; font-weight:600;">Simpan</button>
    </form>
</div>

<script>
document.getElementById('jenis').addEventListener('change', function() {
    const stokField = document.getElementById('stok_field');
    if (this.value === 'es_teh') {
        stokField.required = false;
        stokField.placeholder = "Opsional";
    } else {
        stokField.required = true;
        stokField.placeholder = "";
    }
});
</script>

<?php include '../../includes/footer.php'; ?>