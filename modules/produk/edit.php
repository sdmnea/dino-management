<?php
// modules/produk/edit.php - WORKING VERSION
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Cek login
if (!isLoggedIn()) {
    redirect('login.php');
}

// Cek ID produk
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirect('index.php');
}

$id = (int)$_GET['id'];

// Koneksi database
$database = new Database();
$db = $database->getConnection();

// Ambil data produk
$query = "SELECT * FROM produk WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    redirect('index.php?error=produk_tidak_ditemukan');
}

$produk = $stmt->fetch(PDO::FETCH_ASSOC);

// Set page title
$page_title = "Edit Produk: " . htmlspecialchars($produk['nama_produk']);

// Ambil data kategori
$query_kategori = "SELECT id, nama_kategori FROM kategori_produk ORDER BY nama_kategori";
$stmt_kategori = $db->prepare($query_kategori);
$stmt_kategori->execute();
$kategori = $stmt_kategori->fetchAll(PDO::FETCH_ASSOC);

// Inisialisasi variabel
$errors = [];
$success = false;

// Proses form jika disubmit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validasi input
    $nama_produk = cleanInput($_POST['nama_produk'] ?? '');
    $kategori_id = !empty($_POST['kategori_id']) ? (int)$_POST['kategori_id'] : null;
    $jenis = cleanInput($_POST['jenis'] ?? 'es_teh');
    $harga_beli = (float)str_replace(['.', ','], ['', '.'], $_POST['harga_beli'] ?? '0');
    $harga_jual = (float)str_replace(['.', ','], ['', '.'], $_POST['harga_jual'] ?? '0');
    $stok = (int)($_POST['stok'] ?? 0);
    $min_stok = (int)($_POST['min_stok'] ?? 5);
    $satuan = cleanInput($_POST['satuan'] ?? 'pcs');
    $deskripsi = cleanInput($_POST['deskripsi'] ?? '');
    
    // Validasi
    if (empty($nama_produk)) {
        $errors[] = "Nama produk wajib diisi";
    }
    
    if ($harga_jual <= 0) {
        $errors[] = "Harga jual harus lebih dari 0";
    }
    
    if ($stok < 0) {
        $errors[] = "Stok tidak boleh negatif";
    }
    
    if ($min_stok < 0) {
        $errors[] = "Stok minimum tidak boleh negatif";
    }
    
    // Upload gambar jika ada
    $gambar_produk = $produk['gambar_produk'];
    
    if (!empty($_FILES['gambar_produk']['name'])) {
        $upload_result = uploadGambar($_FILES['gambar_produk'], 'assets/uploads/produk/');
        
        if (is_array($upload_result) && isset($upload_result['errors'])) {
            $errors = array_merge($errors, $upload_result['errors']);
        } else {
            // Hapus gambar lama jika ada
            if (!empty($produk['gambar_produk']) && file_exists(root_path($produk['gambar_produk']))) {
                unlink(root_path($produk['gambar_produk']));
            }
            $gambar_produk = $upload_result;
        }
    }
    
    // Jika tidak ada error, update data
    if (empty($errors)) {
        try {
            $query_update = "UPDATE produk SET 
                nama_produk = :nama_produk,
                kategori_id = :kategori_id,
                jenis = :jenis,
                harga_beli = :harga_beli,
                harga_jual = :harga_jual,
                stok = :stok,
                min_stok = :min_stok,
                satuan = :satuan,
                gambar_produk = :gambar_produk,
                deskripsi = :deskripsi,
                updated_at = NOW()
            WHERE id = :id";
            
            $stmt_update = $db->prepare($query_update);
            
            $params = [
                ':id' => $id,
                ':nama_produk' => $nama_produk,
                ':kategori_id' => $kategori_id,
                ':jenis' => $jenis,
                ':harga_beli' => $harga_beli,
                ':harga_jual' => $harga_jual,
                ':stok' => $stok,
                ':min_stok' => $min_stok,
                ':satuan' => $satuan,
                ':gambar_produk' => $gambar_produk,
                ':deskripsi' => $deskripsi
            ];
            
            if ($stmt_update->execute($params)) {
                $success = true;
                // Refresh data produk
                $stmt->execute();
                $produk = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $errors[] = "Gagal memperbarui produk. Silakan coba lagi.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Include header
include '../../includes/header.php';
?>

<div style="background: white; border-radius: 15px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h2 style="color: #2F1800; font-size: 20px; margin-bottom: 5px;">
                <i class="fas fa-edit" style="color: #9ACD32;"></i> Edit Produk
            </h2>
            <p style="color: #666; font-size: 14px;"><?php echo htmlspecialchars($produk['nama_produk']); ?></p>
        </div>
        <div>
            <a href="index.php" 
               style="background: #F5F5F5; color: #2F1800; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>
    </div>
    
    <?php if ($success): ?>
        <div style="background: #D1FAE5; padding: 15px; border-radius: 10px; border-left: 4px solid #10B981; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 10px; color: #065F46;">
                <i class="fas fa-check-circle"></i>
                <div>
                    <div style="font-weight: 600;">Produk berhasil diperbarui!</div>
                    <div>Perubahan telah disimpan ke database.</div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <div style="background: #FEE2E2; padding: 15px; border-radius: 10px; border-left: 4px solid #EF4444; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 10px; color: #991B1B;">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <div style="font-weight: 600;">Terjadi kesalahan:</div>
                    <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Form Edit -->
    <form method="POST" action="" enctype="multipart/form-data">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <!-- Kolom Kiri -->
            <div>
                <!-- Informasi Produk -->
                <div style="margin-bottom: 25px;">
                    <h3 style="color: #2F1800; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #F5F5F5;">
                        <i class="fas fa-info-circle"></i> Informasi Produk
                    </h3>
                    
                    <!-- Kode Produk -->
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2F1800;">Kode Produk</label>
                        <input type="text" 
                               value="<?php echo htmlspecialchars($produk['kode_produk']); ?>" 
                               style="width: 100%; padding: 10px; border: 2px solid #E5E7EB; border-radius: 8px; background: #F8F9FA;"
                               readonly>
                    </div>
                    
                    <!-- Nama Produk -->
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2F1800;">Nama Produk *</label>
                        <input type="text" 
                               name="nama_produk" 
                               value="<?php echo htmlspecialchars($produk['nama_produk']); ?>" 
                               style="width: 100%; padding: 10px; border: 2px solid #E5E7EB; border-radius: 8px;"
                               required>
                    </div>
                    
                    <!-- Jenis Produk -->
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2F1800;">Jenis Produk *</label>
                        <div style="display: flex; gap: 15px;">
                            <label style="display: flex; align-items: center; gap: 8px;">
                                <input type="radio" 
                                       name="jenis" 
                                       value="es_teh" 
                                       <?php echo $produk['jenis'] == 'es_teh' ? 'checked' : ''; ?>
                                       style="accent-color: #9ACD32;">
                                <span>Es Teh</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px;">
                                <input type="radio" 
                                       name="jenis" 
                                       value="alat_bahan" 
                                       <?php echo $produk['jenis'] == 'alat_bahan' ? 'checked' : ''; ?>
                                       style="accent-color: #FFD700;">
                                <span>Alat & Bahan</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Kategori -->
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2F1800;">Kategori</label>
                        <select name="kategori_id" 
                                style="width: 100%; padding: 10px; border: 2px solid #E5E7EB; border-radius: 8px;">
                            <option value="">Pilih Kategori</option>
                            <?php foreach ($kategori as $kat): ?>
                                <option value="<?php echo $kat['id']; ?>" 
                                        <?php echo $produk['kategori_id'] == $kat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($kat['nama_kategori']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Deskripsi -->
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2F1800;">Deskripsi</label>
                        <textarea name="deskripsi" 
                                  rows="4" 
                                  style="width: 100%; padding: 10px; border: 2px solid #E5E7EB; border-radius: 8px; resize: vertical;"><?php echo htmlspecialchars($produk['deskripsi']); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Kolom Kanan -->
            <div>
                <!-- Harga & Stok -->
                <div style="margin-bottom: 25px;">
                    <h3 style="color: #2F1800; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #F5F5F5;">
                        <i class="fas fa-chart-line"></i> Harga & Stok
                    </h3>
                    
                    <!-- Harga Beli -->
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2F1800;">Harga Beli</label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #666;">Rp</span>
                            <input type="text" 
                                   name="harga_beli" 
                                   value="<?php echo number_format($produk['harga_beli'], 0, ',', '.'); ?>" 
                                   style="width: 100%; padding: 10px 10px 10px 35px; border: 2px solid #E5E7EB; border-radius: 8px;"
                                   oninput="formatCurrency(this)">
                        </div>
                    </div>
                    
                    <!-- Harga Jual -->
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2F1800;">Harga Jual *</label>
                        <div style="position: relative;">
                            <span style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #666;">Rp</span>
                            <input type="text" 
                                   name="harga_jual" 
                                   value="<?php echo number_format($produk['harga_jual'], 0, ',', '.'); ?>" 
                                   style="width: 100%; padding: 10px 10px 10px 35px; border: 2px solid #E5E7EB; border-radius: 8px;"
                                   required
                                   oninput="formatCurrency(this)">
                        </div>
                    </div>
                    
                    <!-- Stok -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2F1800;">Stok</label>
                            <input type="number" 
                                   name="stok" 
                                   value="<?php echo $produk['stok']; ?>" 
                                   style="width: 100%; padding: 10px; border: 2px solid #E5E7EB; border-radius: 8px;"
                                   min="0"
                                   step="1">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2F1800;">Satuan</label>
                            <select name="satuan" 
                                    style="width: 100%; padding: 10px; border: 2px solid #E5E7EB; border-radius: 8px;">
                                <option value="pcs" <?php echo $produk['satuan'] == 'pcs' ? 'selected' : ''; ?>>Pcs</option>
                                <option value="gelas" <?php echo $produk['satuan'] == 'gelas' ? 'selected' : ''; ?>>Gelas</option>
                                <option value="botol" <?php echo $produk['satuan'] == 'botol' ? 'selected' : ''; ?>>Botol</option>
                                <option value="bungkus" <?php echo $produk['satuan'] == 'bungkus' ? 'selected' : ''; ?>>Bungkus</option>
                                <option value="kg" <?php echo $produk['satuan'] == 'kg' ? 'selected' : ''; ?>>Kg</option>
                                <option value="gram" <?php echo $produk['satuan'] == 'gram' ? 'selected' : ''; ?>>Gram</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Stok Minimum -->
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2F1800;">Stok Minimum</label>
                        <input type="number" 
                               name="min_stok" 
                               value="<?php echo $produk['min_stok']; ?>" 
                               style="width: 100%; padding: 10px; border: 2px solid #E5E7EB; border-radius: 8px;"
                               min="0"
                               step="1">
                    </div>
                </div>
                
                <!-- Gambar Produk -->
                <div style="margin-bottom: 25px;">
                    <h3 style="color: #2F1800; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #F5F5F5;">
                        <i class="fas fa-image"></i> Gambar Produk
                    </h3>
                    
                    <?php if (!empty($produk['gambar_produk']) && file_exists(root_path($produk['gambar_produk']))): ?>
                        <div style="margin-bottom: 15px;">
                            <img src="<?php echo BASE_URL . '/' . $produk['gambar_produk']; ?>" 
                                 alt="Gambar Produk" 
                                 style="max-width: 200px; max-height: 200px; border-radius: 8px; border: 2px solid #E5E7EB;">
                        </div>
                    <?php endif; ?>
                    
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2F1800;">Upload Gambar Baru</label>
                        <input type="file" 
                               name="gambar_produk" 
                               accept="image/*"
                               style="width: 100%; padding: 10px; border: 2px solid #E5E7EB; border-radius: 8px;">
                        <p style="color: #666; font-size: 12px; margin-top: 5px;">Format: JPG, PNG, GIF (Maks. 5MB)</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div style="display: flex; gap: 15px; margin-top: 30px; padding-top: 20px; border-top: 2px solid #F5F5F5;">
            <a href="index.php" 
               style="background: #F5F5F5; color: #2F1800; padding: 12px 25px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                <i class="fas fa-times"></i> Batal
            </a>
            <button type="submit" 
                    style="background: linear-gradient(135deg, #9ACD32, #8A2BE2); color: white; padding: 12px 30px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                <i class="fas fa-save"></i> Simpan Perubahan
            </button>
        </div>
    </form>
</div>

<script>
// Format currency input
function formatCurrency(input) {
    let value = input.value.replace(/[^\d]/g, '');
    if (value) {
        value = parseInt(value, 10).toLocaleString('id-ID');
    }
    input.value = value;
}

// Profit calculation
function calculateProfit() {
    const hargaBeli = document.querySelector('input[name="harga_beli"]').value.replace(/\./g, '') || 0;
    const hargaJual = document.querySelector('input[name="harga_jual"]').value.replace(/\./g, '') || 0;
    
    const profit = hargaJual - hargaBeli;
    const profitPercent = hargaBeli > 0 ? (profit / hargaBeli * 100) : 0;
    
    console.log('Profit:', profit, 'Percent:', profitPercent);
}

// Attach event listeners
document.addEventListener('DOMContentLoaded', function() {
    const hargaInputs = document.querySelectorAll('input[name="harga_beli"], input[name="harga_jual"]');
    hargaInputs.forEach(input => {
        input.addEventListener('input', calculateProfit);
    });
    
    calculateProfit(); // Initial calculation
});
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>