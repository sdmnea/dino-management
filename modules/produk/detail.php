<?php
// modules/produk/detail.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

if (!isLoggedIn())
    redirect('login.php');
if (!isset($_GET['id']))
    redirect('index.php');

$id = (int) $_GET['id'];
$database = new Database();
$db = $database->getConnection();

$query = "SELECT p.*, k.nama_kategori FROM produk p 
          LEFT JOIN kategori_produk k ON p.kategori_id = k.id 
          WHERE p.id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();

if ($stmt->rowCount() === 0)
    redirect('index.php');
$produk = $stmt->fetch(PDO::FETCH_ASSOC);

$page_title = "Detail: " . htmlspecialchars($produk['nama_produk']);
include '../../includes/header.php';
?>

<div style="background: white; border-radius: 15px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="color: #2F1800; font-size: 20px;">
            <i class="fas fa-info-circle" style="color: #9ACD32;"></i> Detail Produk
        </h2>
        <a href="index.php"
            style="background: #F5F5F5; color: #2F1800; padding: 10px 20px; border-radius: 8px; text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
        <div>
            <!-- Gambar Produk -->
            <div style="margin-bottom: 20px;">
                <?php if (!empty($produk['gambar_produk']) && file_exists(root_path($produk['gambar_produk']))): ?>
                    <img src="<?php echo BASE_URL . '/' . $produk['gambar_produk']; ?>"
                        alt="<?php echo htmlspecialchars($produk['nama_produk']); ?>"
                        style="width: 100%; max-height: 300px; object-fit: cover; border-radius: 10px;">
                <?php else: ?>
                    <div
                        style="width: 100%; height: 200px; background: linear-gradient(135deg, #9ACD32, #8A2BE2); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white;">
                        <i class="fas fa-box" style="font-size: 64px;"></i>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Informasi Dasar -->
            <div style="background: #F5F5F5; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                <h3 style="color: #2F1800; margin-bottom: 15px; font-size: 18px;">
                    <i class="fas fa-box"></i> Informasi Produk
                </h3>
                <table style="width: 100%;">
                    <tr>
                        <td style="padding: 8px 0; color: #666; width: 120px;">Kode Produk</td>
                        <td style="padding: 8px 0; font-weight: 600;"><?php echo $produk['kode_produk']; ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666;">Nama Produk</td>
                        <td style="padding: 8px 0; font-weight: 600;">
                            <?php echo htmlspecialchars($produk['nama_produk']); ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666;">Jenis</td>
                        <td style="padding: 8px 0;">
                            <span style="background: <?php echo $produk['jenis'] == 'es_teh' ? 'rgba(154, 205, 50, 0.1)' : 'rgba(255, 215, 0, 0.1)'; ?>; 
                                 color: <?php echo $produk['jenis'] == 'es_teh' ? '#9ACD32' : '#FFD700'; ?>; 
                                 padding: 4px 10px; border-radius: 20px; font-size: 12px;">
                                <?php echo $produk['jenis'] == 'es_teh' ? 'ES TEH' : 'ALAT/BAHAN'; ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666;">Kategori</td>
                        <td style="padding: 8px 0;">
                            <?php echo !empty($produk['nama_kategori']) ? htmlspecialchars($produk['nama_kategori']) : '-'; ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666;">Dibuat</td>
                        <td style="padding: 8px 0;"><?php echo date('d/m/Y H:i', strtotime($produk['created_at'])); ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div>
            <!-- Harga & Stok -->
            <div style="background: #F5F5F5; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                <h3 style="color: #2F1800; margin-bottom: 15px; font-size: 18px;">
                    <i class="fas fa-chart-line"></i> Harga & Stok
                </h3>
                <table style="width: 100%;">
                    <tr>
                        <td style="padding: 8px 0; color: #666; width: 120px;">Harga Beli</td>
                        <td style="padding: 8px 0; font-weight: 600; color: #666;">
                            <?php echo formatRupiah($produk['harga_beli']); ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666;">Harga Jual</td>
                        <td style="padding: 8px 0; font-weight: 600; color: #2F1800;">
                            <?php echo formatRupiah($produk['harga_jual']); ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666;">Profit</td>
                        <td style="padding: 8px 0; font-weight: 600; color: #9ACD32;">
                            <?php
                            $profit = $produk['harga_jual'] - $produk['harga_beli'];
                            $profit_percent = $produk['harga_beli'] > 0 ? ($profit / $produk['harga_beli'] * 100) : 0;
                            echo formatRupiah($profit) . ' (' . number_format($profit_percent, 1) . '%)';
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666;">Stok</td>
                        <td style="padding: 8px 0; font-weight: 600;">
                            <?php echo $produk['stok']; ?> <?php echo $produk['satuan']; ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666;">Stok Minimum</td>
                        <td style="padding: 8px 0;"><?php echo $produk['min_stok']; ?> <?php echo $produk['satuan']; ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666;">Status</td>
                        <td style="padding: 8px 0;">
                            <?php
                            $status_class = '';
                            $status_text = '';
                            $status_icon = '';

                            if ($produk['stok'] == 0) {
                                $status_class = 'danger';
                                $status_text = 'HABIS';
                                $status_icon = 'fa-times-circle';
                            } elseif ($produk['stok'] <= $produk['min_stok']) {
                                $status_class = 'warning';
                                $status_text = 'MENIPIS';
                                $status_icon = 'fa-exclamation-triangle';
                            } else {
                                $status_class = 'success';
                                $status_text = 'TERSEDIA';
                                $status_icon = 'fa-check-circle';
                            }
                            ?>
                            <span
                                style="background: <?php echo $status_class == 'success' ? 'rgba(16, 185, 129, 0.1)' : ($status_class == 'warning' ? 'rgba(245, 158, 11, 0.1)' : 'rgba(239, 68, 226, 0.1)'); ?>; 
                                    color: <?php echo $status_class == 'success' ? '#10B981' : ($status_class == 'warning' ? '#F59E0B' : '#EF4444'); ?>; 
                                    padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                                <i class="fas <?php echo $status_icon; ?>"></i>
                                <?php echo $status_text; ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Deskripsi -->
            <div style="background: #F5F5F5; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                <h3 style="color: #2F1800; margin-bottom: 15px; font-size: 18px;">
                    <i class="fas fa-align-left"></i> Deskripsi
                </h3>
                <div style="color: #666; line-height: 1.6;">
                    <?php echo !empty($produk['deskripsi']) ? nl2br(htmlspecialchars($produk['deskripsi'])) : '<span style="color: #999;">Tidak ada deskripsi</span>'; ?>
                </div>
            </div>

            <!-- Actions -->
            <div style="display: flex; gap: 10px;">
                <a href="edit.php?id=<?php echo $id; ?>"
                    style="background: #9ACD32; color: white; padding: 12px 20px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; flex: 1; justify-content: center;">
                    <i class="fas fa-edit"></i> Edit Produk
                </a>
                <a href="index.php"
                    style="background: #F5F5F5; color: #2F1800; padding: 12px 20px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; flex: 1; justify-content: center;">
                    <i class="fas fa-list"></i> Daftar Produk
                </a>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>