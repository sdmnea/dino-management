<?php
// modules/produk/index.php - FIXED VERSION
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Cek login
if (!isLoggedIn()) {
    redirect('login.php');
}

// Set page title
$page_title = "Manajemen Produk";

// Koneksi database
$database = new Database();
$db = $database->getConnection();

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Filter
$filter_jenis = isset($_GET['jenis']) ? cleanInput($_GET['jenis']) : '';
$filter_kategori = isset($_GET['kategori']) ? (int)$_GET['kategori'] : 0;
$filter_stok = isset($_GET['stok']) ? cleanInput($_GET['stok']) : '';

// Build WHERE clause
$where = [];
$params = [];

if (!empty($filter_jenis)) {
    $where[] = "p.jenis = :jenis";
    $params[':jenis'] = $filter_jenis;
}

if (!empty($filter_kategori)) {
    $where[] = "p.kategori_id = :kategori";
    $params[':kategori'] = $filter_kategori;
}

if ($filter_stok === 'menipis') {
    $where[] = "p.stok <= p.min_stok AND p.stok > 0";
} elseif ($filter_stok === 'habis') {
    $where[] = "p.stok = 0";
}

// Jika tabel produk memiliki kolom is_deleted, sembunyikan record yang ditandai terhapus
if (function_exists('columnExists') && columnExists($db, 'produk', 'is_deleted')) {
    $where[] = "p.is_deleted = 0";
}

$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Hitung total data
$query_count = "SELECT COUNT(*) as total FROM produk p $where_clause";
$stmt_count = $db->prepare($query_count);
foreach ($params as $key => $value) {
    $stmt_count->bindValue($key, $value);
}
$stmt_count->execute();
$total_data = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Query data produk
$query = "SELECT 
    p.*, 
    k.nama_kategori,
    k.warna_kategori
FROM produk p
LEFT JOIN kategori_produk k ON p.kategori_id = k.id
$where_clause
ORDER BY p.created_at DESC
LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);

// Bind parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$produk = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query kategori untuk filter
$query_kategori = "SELECT id, nama_kategori FROM kategori_produk ORDER BY nama_kategori";
$stmt_kategori = $db->prepare($query_kategori);
$stmt_kategori->execute();
$kategori = $stmt_kategori->fetchAll(PDO::FETCH_ASSOC);

// Hitung total halaman
$total_pages = ceil($total_data / $limit);

// Build filter URL untuk pagination
$filter_url = '';
if (!empty($filter_jenis)) $filter_url .= '&jenis=' . urlencode($filter_jenis);
if (!empty($filter_kategori)) $filter_url .= '&kategori=' . $filter_kategori;
if (!empty($filter_stok)) $filter_url .= '&stok=' . urlencode($filter_stok);

// Include header
include '../../includes/header.php';
?>
<style>
/* Responsive styles untuk semua modul */
@media (max-width: 768px) {
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .mobile-stats {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 10px !important;
    }
    
    .mobile-padding {
        padding: 10px !important;
    }
    
    .mobile-margin-top {
        margin-top: 15px !important;
    }
}
</style>

<div style="background: white; border-radius: 15px; padding: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-bottom: 20px; margin-top: 20px;" class="mobile-padding">
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
        <h2 style="color: #2F1800; font-size: 20px;">
            <i class="fas fa-box" style="color: #9ACD32;"></i> Daftar Produk
        </h2>
        <a href="<?php echo BASE_URL; ?>/modules/produk/tambah.php" 
           style="background: linear-gradient(135deg, #9ACD32, #8A2BE2); color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
            <i class="fas fa-plus"></i> Tambah Produk
        </a>
    </div>
    
    <!-- Filter Form -->
    <div style="background: #F5F5F5; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
        <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2F1800;">Jenis Produk</label>
                <select name="jenis" style="width: 100%; padding: 10px; border: 2px solid #E5E7EB; border-radius: 8px;">
                    <option value="">Semua Jenis</option>
                    <option value="es_teh" <?php echo $filter_jenis == 'es_teh' ? 'selected' : ''; ?>>Es Teh</option>
                    <option value="alat_bahan" <?php echo $filter_jenis == 'alat_bahan' ? 'selected' : ''; ?>>Alat & Bahan</option>
                </select>
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2F1800;">Kategori</label>
                <select name="kategori" style="width: 100%; padding: 10px; border: 2px solid #E5E7EB; border-radius: 8px;">
                    <option value="0">Semua Kategori</option>
                    <?php foreach ($kategori as $kat): ?>
                        <option value="<?php echo $kat['id']; ?>" <?php echo $filter_kategori == $kat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($kat['nama_kategori']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2F1800;">Status Stok</label>
                <select name="stok" style="width: 100%; padding: 10px; border: 2px solid #E5E7EB; border-radius: 8px;">
                    <option value="">Semua Stok</option>
                    <option value="menipis" <?php echo $filter_stok == 'menipis' ? 'selected' : ''; ?>>Stok Menipis</option>
                    <option value="habis" <?php echo $filter_stok == 'habis' ? 'selected' : ''; ?>>Stok Habis</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 10px; align-items: flex-end;">
                <button type="submit" 
                        style="background: #9ACD32; color: white; padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; flex: 1;">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="index.php" 
                   style="background: #F5F5F5; color: #2F1800; padding: 10px; border-radius: 8px; text-decoration: none; display: flex; align-items: center; justify-content: center; width: 42px;">
                    <i class="fas fa-redo"></i>
                </a>
            </div>
        </form>
    </div>
    
    <!-- Product List -->
    <?php if (empty($produk)): ?>
        <div style="text-align: center; padding: 40px 20px;">
            <i class="fas fa-box-open" style="font-size: 64px; color: #E5E7EB; margin-bottom: 20px;"></i>
            <h3 style="color: #666; margin-bottom: 10px;"><?php echo $total_data > 0 ? 'Tidak ada produk yang sesuai filter' : 'Belum ada produk'; ?></h3>
            <p style="color: #999;">
                <?php if ($total_data > 0): ?>
                    Coba ubah filter pencarian
                <?php else: ?>
                    Tambahkan produk pertama Anda
                <?php endif; ?>
            </p>
            <a href="tambah.php" 
               style="background: linear-gradient(135deg, #9ACD32, #8A2BE2); color: white; padding: 12px 25px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; margin-top: 15px;">
                <i class="fas fa-plus"></i> Tambah Produk
            </a>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; min-width: 800px;">
                <thead>
                    <tr style="background: #F5F5F5;">
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #E5E7EB;">Produk</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #E5E7EB;">Kategori</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #E5E7EB;">Harga</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #E5E7EB;">Stok</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #E5E7EB;">Status</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #E5E7EB;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produk as $item): ?>
                    <tr style="border-bottom: 1px solid #E5E7EB; transition: background 0.3s;">
                        <td style="padding: 12px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 50px; height: 50px; border-radius: 8px; background: linear-gradient(135deg, #9ACD32, #8A2BE2); display: flex; align-items: center; justify-content: center; color: white;">
                                    <i class="fas fa-box"></i>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: #2F1800; margin-bottom: 3px;">
                                        <?php echo htmlspecialchars($item['nama_produk']); ?>
                                    </div>
                                    <div style="font-size: 12px; color: #666;">
                                        <span style="background: <?php echo $item['jenis'] == 'es_teh' ? 'rgba(154, 205, 50, 0.1)' : 'rgba(255, 215, 0, 0.1)'; ?>; 
                                             color: <?php echo $item['jenis'] == 'es_teh' ? '#9ACD32' : '#FFD700'; ?>; 
                                             padding: 3px 8px; border-radius: 20px; font-size: 11px;">
                                            <?php echo $item['jenis'] == 'es_teh' ? 'ES TEH' : 'ALAT/BAHAN'; ?>
                                        </span>
                                        <span style="margin-left: 8px; color: #999;">
                                            <?php echo $item['kode_produk']; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td style="padding: 12px; color: #666;">
                            <?php echo !empty($item['nama_kategori']) ? htmlspecialchars($item['nama_kategori']) : '<span style="color: #999;">-</span>'; ?>
                        </td>
                        <td style="padding: 12px;">
                            <div style="font-weight: 600; color: #2F1800;">
                                <?php echo formatRupiah($item['harga_jual']); ?>
                            </div>
                            <div style="font-size: 12px; color: #999;">
                                Beli: <?php echo formatRupiah($item['harga_beli']); ?>
                            </div>
                        </td>
                        <td style="padding: 12px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="font-weight: 600; color: #2F1800;">
                                    <?php echo $item['stok']; ?> <?php echo $item['satuan']; ?>
                                </div>
                                <?php if ($item['stok'] <= $item['min_stok']): ?>
                                    <span style="font-size: 12px; color: #EF4444; background: rgba(239, 68, 68, 0.1); padding: 2px 8px; border-radius: 20px;">
                                        Min: <?php echo $item['min_stok']; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="padding: 12px;">
                            <?php
                            $status_class = '';
                            $status_text = '';
                            $status_icon = '';
                            
                            if ($item['stok'] == 0) {
                                $status_class = 'danger';
                                $status_text = 'HABIS';
                                $status_icon = 'fa-times-circle';
                            } elseif ($item['stok'] <= $item['min_stok']) {
                                $status_class = 'warning';
                                $status_text = 'MENIPIS';
                                $status_icon = 'fa-exclamation-triangle';
                            } else {
                                $status_class = 'success';
                                $status_text = 'TERSEDIA';
                                $status_icon = 'fa-check-circle';
                            }
                            ?>
                            <span style="background: <?php echo $status_class == 'success' ? 'rgba(16, 185, 129, 0.1)' : ($status_class == 'warning' ? 'rgba(245, 158, 11, 0.1)' : 'rgba(239, 68, 226, 0.1)'); ?>; 
                                  color: <?php echo $status_class == 'success' ? '#10B981' : ($status_class == 'warning' ? '#F59E0B' : '#EF4444'); ?>; 
                                  padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;">
                                <i class="fas <?php echo $status_icon; ?>"></i>
                                <?php echo $status_text; ?>
                            </span>
                        </td>
                        <td style="padding: 12px;">
                            <div style="display: flex; gap: 8px;">
                                <a href="detail.php?id=<?php echo $item['id']; ?>" 
                                   title="Detail"
                                   style="background: rgba(59, 130, 246, 0.1); color: #3B82F6; width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; text-decoration: none;">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $item['id']; ?>" 
                                   title="Edit"
                                   style="background: rgba(154, 205, 50, 0.1); color: #9ACD32; width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; text-decoration: none;">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="confirmDelete(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['nama_produk'])); ?>')"
                                        title="Hapus"
                                        style="background: rgba(239, 68, 68, 0.1); color: #EF4444; width: 36px; height: 36px; border-radius: 8px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div style="display: flex; justify-content: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #E5E7EB;">
            <div style="display: flex; gap: 5px; align-items: center;">
                <?php if ($page > 1): ?>
                    <a href="?page=1<?php echo $filter_url; ?>" 
                       style="padding: 8px 12px; background: #F5F5F5; color: #2F1800; border-radius: 6px; text-decoration: none;">
                        « First
                    </a>
                    <a href="?page=<?php echo $page - 1; ?><?php echo $filter_url; ?>" 
                       style="padding: 8px 12px; background: #F5F5F5; color: #2F1800; border-radius: 6px; text-decoration: none;">
                        ‹ Prev
                    </a>
                <?php endif; ?>
                
                <?php 
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                
                for ($i = $start; $i <= $end; $i++): 
                ?>
                    <a href="?page=<?php echo $i; ?><?php echo $filter_url; ?>" 
                       style="padding: 8px 12px; <?php echo $i == $page ? 'background: #9ACD32; color: white;' : 'background: #F5F5F5; color: #2F1800;'; ?> border-radius: 6px; text-decoration: none;">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo $filter_url; ?>" 
                       style="padding: 8px 12px; background: #F5F5F5; color: #2F1800; border-radius: 6px; text-decoration: none;">
                        Next ›
                    </a>
                    <a href="?page=<?php echo $total_pages; ?><?php echo $filter_url; ?>" 
                       style="padding: 8px 12px; background: #F5F5F5; color: #2F1800; border-radius: 6px; text-decoration: none;">
                        Last »
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <!-- Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px;">
        <div style="background: white; padding: 15px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
            <div style="font-size: 24px; font-weight: 700; color: #2F1800; margin-bottom: 5px;">
                <?php echo $total_data; ?>
            </div>
            <div style="color: #666; font-size: 14px;">Total Produk</div>
        </div>
        
        <?php
        // Hitung statistik
        $es_teh = 0;
        $alat_bahan = 0;
        $stok_menipis = 0;
        
        foreach ($produk as $item) {
            if ($item['jenis'] == 'es_teh') $es_teh++;
            if ($item['jenis'] == 'alat_bahan') $alat_bahan++;
            if ($item['stok'] <= $item['min_stok'] && $item['stok'] > 0) $stok_menipis++;
        }
        ?>
        
        <div style="background: white; padding: 15px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
            <div style="font-size: 24px; font-weight: 700; color: #2F1800; margin-bottom: 5px;">
                <?php echo $es_teh; ?>
            </div>
            <div style="color: #666; font-size: 14px;">Es Teh</div>
        </div>
        
        <div style="background: white; padding: 15px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
            <div style="font-size: 24px; font-weight: 700; color: #2F1800; margin-bottom: 5px;">
                <?php echo $alat_bahan; ?>
            </div>
            <div style="color: #666; font-size: 14px;">Alat & Bahan</div>
        </div>
        
        <div style="background: white; padding: 15px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
            <div style="font-size: 24px; font-weight: 700; color: #2F1800; margin-bottom: 5px;">
                <?php echo $stok_menipis; ?>
            </div>
            <div style="color: #666; font-size: 14px;">Stok Menipis</div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, nama) {
    if (confirm('Apakah Anda yakin ingin menghapus produk "' + nama + '"?\n\nTindakan ini tidak dapat dibatalkan!')) {
        // Sertakan URL kembali agar pengguna tetap berada di halaman produk setelah hapus
        var returnPath = 'modules/produk/index.php' + window.location.search;
        window.location.href = 'hapus.php?id=' + id + '&return=' + encodeURIComponent(returnPath);
    }
}

// Filter form submission
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.querySelector('form[method="GET"]');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            // Reset page to 1 when filtering
            const pageInput = document.createElement('input');
            pageInput.type = 'hidden';
            pageInput.name = 'page';
            pageInput.value = '1';
            this.appendChild(pageInput);
        });
    }
});
</script>

<?php
// Include footer
include '../../includes/footer.php';
?>