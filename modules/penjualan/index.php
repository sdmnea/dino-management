<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$page_title = 'Input Penjualan';
$database = new Database();
$db = $database->getConnection();

// Load available products - HANYA ES TEH yang belum dihapus
$query = "SELECT id, kode_produk, nama_produk, stok, harga_jual, gambar_produk, min_stok, satuan 
          FROM produk 
          WHERE jenis = 'es_teh' AND is_deleted = 0 
          ORDER BY nama_produk";
$stmt = $db->prepare($query);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fungsi untuk mendapatkan warna konsisten dari teks
function getColorFromText($text)
{
    $colors = [
        '#9ACD32', // Green
        '#8A2BE2', // Purple
        '#FF6B6B', // Red
        '#4ECDC4', // Teal
        '#FFD166', // Yellow
        '#06D6A0', // Mint
        '#118AB2', // Blue
        '#EF476F', // Pink
        '#073B4C', // Dark Blue
        '#7209B7', // Violet
    ];

    $hash = crc32($text);
    $index = abs($hash) % count($colors);
    return $colors[$index];
}

// Fungsi untuk mendapatkan HTML gambar yang optimal
function getOptimizedImageHtml($gambar_produk, $product_name)
{
    if (!empty($gambar_produk)) {
        // Trim prefix kalau ada, tapi pakai full path dari DB
        $gambar_produk = ltrim($gambar_produk, '/'); // Hilang slash awal kalau ada

        // Path absolut file (sesuai DB prefix 'assets/uploads/produk/')
        $original_path = ROOT_PATH . '/' . $gambar_produk;

        // Debug kritis: Log kalau file tidak ada (lihat di error_log atau console hosting)
        if (!file_exists($original_path)) {
            error_log("Gambar tidak ditemukan: " . $original_path . " untuk produk: " . $product_name);
        }

        // Cek apakah file ada
        if (file_exists($original_path)) {
            // Buat URL gambar
            $image_url = BASE_URL . '/' . $gambar_produk;

            // Return HTML img dengan lazy load
            return '<img src="' . $image_url . '" 
                alt="' . htmlspecialchars($product_name) . '" 
                class="product-image loading" 
                loading="lazy" 
                decoding="async" 
                srcset="' . $image_url . ' 400w, ' . $image_url . '?w=200 200w" 
                sizes="(max-width: 768px) 200px, 400px" 
                onload="this.classList.add(\'loaded\')"
                onerror="this.onerror=null; this.parentNode.innerHTML = \'<div class=\\\'no-image\\\'>Tidak ada gambar</div>\'">';
        }
    }

    // Fallback: Kosong dengan keterangan
    return '<div class="no-image">Tidak ada gambar</div>';
}

include '../../includes/header.php';
?>

<style>
    /* Tambahkan di CSS sekitar baris awal style */
    body {
        padding-bottom: 120px !important;
        /* Beri ruang untuk floating bar */
    }

    /* Atau lebih spesifik: */
    div[style*="margin-bottom: 80px"] {
        margin-bottom: 150px !important;
        /* Tambah dari 80px ke 150px */
    }

    /* Product Grid */
    .products-grid {
        display: grid;
        gap: 15px;
        margin: 20px 0;
    }

    /* Desktop: 1 baris untuk metode pembayaran, total, dan button simpan */
    @media (min-width: 769px) {
        .payment-grid {
            display: flex !important;
            flex-direction: row !important;
            align-items: flex-end !important;
            /* Ubah dari center ke flex-end */
            gap: 15px !important;
            height: 80px;
            /* Beri tinggi tetap untuk konsistensi */
        }

        .payment-field {
            flex: 1;
            margin-bottom: 0 !important;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            /* Pastikan konten di bottom */
            height: 100%;
        }

        .payment-field:first-child {
            flex: 1.5;
        }

        .payment-field:last-child {
            flex: 1;
        }

        #save-transaction-floating {
            margin-top: 0 !important;
            width: auto !important;
            min-width: 200px;
            flex-shrink: 0;
            height: 56px;
            /* Sama dengan total-amount */
            align-self: flex-end;
            /* Pastikan di bottom */
        }

        /* Pastikan field-label tetap di atas */
        .field-label {
            margin-bottom: 8px;
            flex-shrink: 0;
            /* Jangan menyusut */
        }

        /* Pastikan form-input dan total-amount memiliki tinggi yang sama */
        .form-input,
        .total-amount {
            height: 56px;
            box-sizing: border-box;
            display: flex;
            align-items: center;
            margin-top: auto;
            /* Dorong ke bawah */
        }

        .total-amount {
            padding: 0 15px;
            font-size: 18px;
            justify-content: center;
        }

        /* Container untuk form-input agar bisa diatur bottom */
        .payment-field>.form-input,
        .payment-field>.total-amount {
            margin-top: auto;
        }
    }

    /* Mobile: floating bar lebih tinggi dari footer mobile */
    @media (max-width: 768px) {
        .floating-bar {
            bottom: 10px !important;
            /* Naikkan dari 0 ke 10px */
        }
    }

    /* Desktop: 4-5 produk per baris */
    @media (min-width: 1024px) {
        .products-grid {
            grid-template-columns: repeat(5, 1fr);
        }
    }

    @media (min-width: 768px) and (max-width: 1023px) {
        .products-grid {
            grid-template-columns: repeat(4, 1fr);
        }
    }

    /* Tablet: 3 produk per baris */
    @media (min-width: 640px) and (max-width: 767px) {
        .products-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    /* Mobile: 2 produk per baris */
    @media (max-width: 639px) {
        .products-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    /* Product Card */
    .product-card {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        border: 1px solid #e5e7eb;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .product-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
    }

    .product-card.active {
        border-color: #9ACD32;
        box-shadow: 0 4px 12px rgba(154, 205, 50, 0.25);
    }

    /* Product Image - OPTIMASI GAMBAR */
    .product-image-container {
        width: 100%;
        height: 140px;
        overflow: hidden;
        background: linear-gradient(135deg, #f5f5f5, #e5e7eb);
        position: relative;
    }

    /* Controls di dalam image */
    /* Controls di dalam image */
    .image-quantity-controls {
        position: absolute;
        top: 10px;
        right: 10px;
        display: flex;
        gap: 5px;
        z-index: 10;
    }

    /* Title container untuk nama produk dan harga */
    .image-quantity-title {
        position: absolute;
        bottom: 0px;
        left: 0px;
        right: 0px;
        display: flex;
        flex-direction: column;
        gap: 4px;
        z-index: 10;
        background: linear-gradient(to top, rgba(0, 0, 0, 0.8) 0%, rgba(0, 0, 0, 0.6) 50%, rgba(0, 0, 0, 0) 100%);
        backdrop-filter: blur();
        border-radius: 12px 12px 8px 8px;
        padding: 12px 15px;
        color: white;
        max-width: calc(100%);
        box-sizing: border-box;
        overflow: hidden;
    }

    /* Nama produk di atas */
    .image-quantity-title .product-name {
        font-weight: 600;
        font-size: 14px;
        line-height: 1.3;
        color: white;
        margin: 0;
        padding: 0;
        text-shadow: 0 1px 3px rgba(0, 0, 0, 0.5);
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Harga di bawah nama produk */
    .image-quantity-title .product-price {
        font-weight: 700;
        font-size: 15px;
        color: #9ACD32;
        margin: 0;
        padding: 0;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .image-quantity-title {
            padding: 10px 12px;
            border-radius: 10px 10px 6px 6px;
        }

        .image-quantity-title .product-name {
            font-size: 13px;
        }

        .image-quantity-title .product-price {
            font-size: 14px;
        }
    }

    /* Hover effect untuk product card */
    .product-card:hover .image-quantity-title {
        background: linear-gradient(to top, rgba(0, 0, 0, 0.9) 0%, rgba(0, 0, 0, 0.7) 50%, rgba(0, 0, 0, 0.1) 100%);
    }

    .image-qty-btn {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: none;
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 14px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        transition: all 0.2s ease;
    }

    .image-qty-btn:hover {
        transform: scale(1.1);
        box-shadow: 0 3px 12px rgba(0, 0, 0, 0.3);
    }

    .image-qty-display {
        font-size: 14px;
        font-weight: 600;
        color: #1f2937;
        min-width: 20px;
        text-align: center;
        margin: 0 5px;
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(4px);
        /* Hapus border-radius agar tidak bulat sendiri, tapi menyatu */
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        padding: 6px 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        /* Tambahkan border-radius kecil agar ujungnya sedikit melengkung, tapi tidak bulat penuh */
        border-radius: 30%;
    }

    .image-qty-minus {
        color: #EF4444;
    }

    .image-qty-plus {
        color: #10B981;
    }


    .product-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
        /* Kompresi gambar melalui CSS */
        image-rendering: -webkit-optimize-contrast;
        image-rendering: crisp-edges;
        -ms-interpolation-mode: bicubic;
    }

    /* Efek loading untuk gambar */
    .product-image.loading {
        opacity: 0;
    }

    .product-image.loaded {
        opacity: 1;
        transition: opacity 0.3s ease;
    }

    .product-card:hover .product-image {
        transform: scale(1.05);
    }

    .product-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        color: white;
        padding: 4px 8px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 4px;
        z-index: 2;
        backdrop-filter: blur(4px);
        background: rgba(0, 0, 0, 0.7);
    }

    .badge-stock {
        background: linear-gradient(135deg, #10B981, #059669);
    }

    .badge-warning {
        background: linear-gradient(135deg, #F59E0B, #D97706);
    }

    .badge-danger {
        background: linear-gradient(135deg, #EF4444, #DC2626);
    }

    /* No Image Placeholder */
    .no-image {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        font-size: 14px;
        font-weight: 500;
        color: #999;
        background: #f8fafc;
        padding: 20px;
        box-sizing: border-box;
    }

    /* Product Info */
    .product-info {
        padding: 15px;
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .product-name {
        font-weight: 600;
        color: #1f2937;
        font-size: 14px;
        line-height: 1.3;
        height: auto;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }

    .product-price {
        color: #10B981;
        font-weight: 700;
        font-size: 16px;
        margin-bottom: 8px;
    }

    .product-stock {
        color: #6b7280;
        font-size: 12px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    /* Quantity Controls */
    .quantity-controls {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: auto;
    }

    .qty-btn {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 16px;
        transition: all 0.2s ease;
        background: #f3f4f6;
        color: #374151;
    }

    .qty-btn:hover:not(:disabled) {
        background: #e5e7eb;
        transform: scale(1.05);
    }

    .qty-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .qty-minus {
        color: #EF4444;
    }

    .qty-plus {
        color: #10B981;
    }

    .quantity-display {
        font-size: 16px;
        font-weight: 600;
        color: #1f2937;
        min-width: 30px;
        text-align: center;
    }

    /* Floating Bar Modern - DIPERBAIKI TANPA OVERLAY DAN CLOSE */
    .floating-bar {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: white;
        padding: 15px;
        box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
        z-index: 1050 !important;
        /* Tingkatkan dari 999 ke 1050 */
        display: none;
        border-top-left-radius: 20px;
        border-top-right-radius: 20px;
        transition: transform 0.3s ease, opacity 0.3s ease;
        opacity: 0;
        transform: translateY(100%);
        max-height: 80vh;
        overflow-y: auto;
        /* Tidak ada overlay di belakang */
    }

    footer {
        z-index: 100 !important;
    }

    .floating-bar.visible {
        display: block;
        opacity: 1;
        transform: translateY(0);
    }

    /* Payment Section di Floating Bar */
    .payment-section {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-radius: 12px;
        padding: 15px;
        margin-bottom: 15px;
        border: 1px solid #e2e8f0;
    }

    .payment-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    @media (max-width: 768px) {
        .payment-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        .floating-bar {
            padding: 12px;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
        }

        .payment-section {
            padding: 12px;
        }
    }

    @media (max-width: 480px) {
        .floating-bar {
            padding: 10px;
        }

        .payment-section {
            padding: 10px;
        }
    }

    .payment-field {
        margin-bottom: 0;
    }

    .total-amount {
        font-size: 20px;
        font-weight: 700;
        color: #9ACD32;
        text-align: center;
        padding: 10px;
        background: white;
        border-radius: 8px;
        border: 2px solid #e5e7eb;
        min-height: 56px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    @media (max-width: 768px) {
        .total-amount {
            font-size: 18px;
            padding: 8px;
            min-height: 52px;
        }
    }

    .form-input {
        width: 100%;
        padding: 12px;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        font-size: 15px;
        box-sizing: border-box;
    }

    @media (max-width: 768px) {
        .form-input {
            padding: 10px;
            font-size: 14px;
        }
    }

    .field-label {
        font-weight: 600;
        display: block;
        margin-bottom: 8px;
        color: #374151;
        font-size: 14px;
    }

    /* Selected Products Summary di Floating Bar */
    .selected-summary {
        background: white;
        border-radius: 10px;
        padding: 12px;
        margin-bottom: 15px;
        border: 1px solid #e5e7eb;
        max-height: 150px;
        overflow-y: auto;
    }

    @media (max-width: 768px) {
        .selected-summary {
            max-height: 120px;
            padding: 10px;
        }
    }

    .summary-header {
        font-weight: 600;
        color: #374151;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 14px;
    }

    .summary-items {
        max-height: 100px;
        overflow-y: auto;
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 6px 0;
        border-bottom: 1px solid #f3f4f6;
        font-size: 13px;
    }

    .summary-item:last-child {
        border-bottom: none;
    }

    /* Button Simpan di Floating Bar - DIPERBAIKI */
    .btn-primary {
        background: linear-gradient(135deg, #9ACD32, #8A2BE2);
        color: white;
        border: none;
        padding: 14px 24px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 16px;
        cursor: pointer;
        transition: all 0.3s ease;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-sizing: border-box;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(154, 205, 50, 0.3);
    }

    @media (max-width: 768px) {
        .btn-primary {
            padding: 12px 20px;
            font-size: 15px;
        }
    }

    /* Tombol Save di Sticky Container (untuk desktop) */
    .sticky-save-container {
        position: sticky;
        top: 70px;
        z-index: 100;
        background: white;
        padding: 15px;
        margin: -20px -20px 20px -20px;
        border-radius: 0 0 12px 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
    }

    .sticky-save-container.scrolled {
        padding: 12px 20px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
    }

    @media (max-width: 768px) {
        .sticky-save-container {
            top: 0;
            margin: -15px -15px 15px -15px;
            padding: 12px 15px;
        }

        .sticky-save-container.scrolled {
            padding: 10px 15px;
        }
    }

    /* Empty State */
    .empty-products {
        text-align: center;
        padding: 40px 20px;
    }

    .empty-icon {
        font-size: 64px;
        color: #e5e7eb;
        margin-bottom: 20px;
    }

    /* Alert Messages */
    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 15px;
        font-size: 14px;
    }

    .alert-success {
        background-color: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }

    .alert-error {
        background-color: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }

    /* Transaction List */
    .transaction-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-top: 15px;
    }

    .transaction-item {
        background-color: #fafafa;
        padding: 14px;
        border-radius: 10px;
        border-left: 4px solid #9ACD32;
        transition: all 0.2s ease;
    }

    .transaction-item:hover {
        background-color: #f4f4f5;
        transform: translateX(2px);
    }

    .transaction-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .transaction-details {
        flex: 1;
    }

    .transaction-product {
        font-weight: 600;
        color: #1f2937;
        font-size: 15px;
        margin-bottom: 4px;
    }

    .transaction-meta {
        font-size: 13px;
        color: #6b7280;
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .transaction-meta span {
        display: inline-block;
    }

    .payment-method {
        color: #7c3aed;
        font-weight: 600;
    }

    .transaction-actions {
        display: flex;
        gap: 8px;
    }

    .btn-link {
        color: #2563eb;
        font-size: 13px;
        text-decoration: none;
        font-weight: 500;
        padding: 6px 12px;
        border-radius: 6px;
        transition: all 0.2s ease;
    }

    .btn-link:hover {
        background-color: #dbeafe;
    }

    /* Loading Animation */
    .loading-spinner {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        z-index: 9999;
        justify-content: center;
        align-items: center;
    }

    .spinner {
        width: 50px;
        height: 50px;
        border: 5px solid #f3f4f6;
        border-top-color: #9ACD32;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Card Styles */
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }

    .card-title {
        font-size: 20px;
        margin: 0;
        color: #1f2937;
        font-weight: 600;
    }

    .btn-secondary {
        background: #74B652;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-secondary:hover {
        background-color: #e5e7eb;
        transform: translateY(-1px);
    }

    /* Cari bagian CSS untuk .payment-grid (sekitar baris 300-310) dan UBAH: */
    .payment-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    /* Ganti media query untuk mobile menjadi: */
    @media (max-width: 768px) {
        .payment-grid {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }

        .payment-field {
            flex: 1;
            min-width: 150px;
            margin-bottom: 0;
        }

        /* Optional: Sesuaikan ukuran font untuk mobile */
        .form-input {
            padding: 8px 10px;
            font-size: 14px;
        }

        .total-amount {
            font-size: 16px;
            padding: 8px;
            min-height: auto;
        }
    }

    /* Untuk layar sangat kecil */
    @media (max-width: 480px) {
        .payment-grid {
            flex-direction: row;
            gap: 8px;
        }

        .payment-field {
            min-width: 140px;
        }
    }
</style>

<div
    style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 6px 18px rgba(0,0,0,0.06); max-width: 100%; overflow: hidden; position: relative; margin-bottom: 80px;">
    <div class="card-header">
        <h2 class="card-title">Input Penjualan</h2>
        <a href="<?php echo BASE_URL; ?>/modules/penjualan/riwayat.php" class="btn-secondary">
            <i class="fas fa-history"></i> Riwayat
        </a>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <!-- Product Grid -->
    <div class="products-grid" id="products-grid">
        <?php foreach ($products as $product): ?>
            <?php
            // Dapatkan warna konsisten untuk produk
            $bg_color = getColorFromText($product['nama_produk']);
            $initial = strtoupper(substr($product['nama_produk'], 0, 1));

            // Dapatkan URL gambar yang optimal
            $image_html = getOptimizedImageHtml($product['gambar_produk'], $product['nama_produk']);

            // Tentukan badge berdasarkan stok
            $badge_class = '';
            $badge_text = '';
            $badge_icon = '';

            // if ($product['stok'] <= 0) {
            //     $badge_class = 'badge-danger';
            //     $badge_text = 'HABIS';
            //     $badge_icon = 'fa-times';
            // } elseif ($product['stok'] <= $product['min_stok']) {
            //     $badge_class = 'badge-warning';
            //     $badge_text = 'MENIPIS';
            //     $badge_icon = 'fa-exclamation';
            // } else {
            //     $badge_class = 'badge-stock';
            //     $badge_text = 'TERSEDIA';
            //     $badge_icon = 'fa-check';
            // }
            ?>
            <div class="product-card" data-product-id="<?php echo $product['id']; ?>">
                <div class="product-image-container" style="background: <?php echo $bg_color; ?>;">
                    <!-- image/gambar produk -->
                    <?php echo $image_html; ?>

                    <!-- Tombol quantity di dalam image container -->
                    <div class="image-quantity-controls">
                        <button type="button" class="image-qty-btn image-qty-minus"
                            data-product-id="<?php echo $product['id']; ?>" style="display:none;">
                            <i class="fas fa-minus"></i>
                        </button>
                        <span class="image-qty-display" id="image-qty-<?php echo $product['id']; ?>"
                            style="display:none;">0</span>
                        <button type="button" class="image-qty-btn image-qty-plus"
                            data-product-id="<?php echo $product['id']; ?>">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>

                    <!-- Title container di bagian bawah -->
                    <div class="image-quantity-title">
                        <div class="product-name"><?php echo htmlspecialchars($product['nama_produk']); ?></div>
                        <div class="product-price"><?php echo formatRupiah($product['harga_jual']); ?></div>
                    </div>
                </div>

                <!-- <div class="product-info">
                    <div class="product-name"><?php echo htmlspecialchars($product['nama_produk']); ?></div>
                    <div class="product-price"><?php echo formatRupiah($product['harga_jual']); ?></div>

                    <div class="product-stock">
                        <i class="fas fa-box"></i>
                        <span>Stok: <?php echo $product['stok']; ?>
                            <?php echo htmlspecialchars($product['satuan']); ?></span>
                    </div>

                </div> -->
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($products)): ?>
        <div class="empty-products">
            <div class="empty-icon">
                <i class="fas fa-box-open"></i>
            </div>
            <h3 style="color: #666; margin-bottom: 10px;">Belum ada produk es teh tersedia</h3>
            <p style="color: #999;">
                Tambahkan produk es teh terlebih dahulu di menu Manajemen Produk
            </p>
            <a href="<?php echo BASE_URL; ?>/modules/produk/tambah.php" class="btn-secondary" style="margin-top: 15px;">
                <i class="fas fa-plus"></i> Tambah Produk
            </a>
        </div>
    <?php endif; ?>

    <!-- Hidden Form for Submission -->
    <form id="penjualan-form" method="POST" action="<?php echo BASE_URL; ?>/modules/penjualan/proses_jual.php"
        style="display: none;">
        <!-- Product IDs and quantities will be added here -->
        <input type="hidden" name="simpan_penjualan" value="1">
    </form>

    <!-- Loading Spinner -->
    <div class="loading-spinner" id="loading-spinner">
        <div class="spinner"></div>
    </div>

    <!-- Floating Bar (Payment + Total + Summary + Button Simpan) - TANPA OVERLAY -->
    <div class="floating-bar" id="floating-bar">
        <!-- Selected Products Summary -->
        <div class="selected-summary">
            <div class="summary-header">
                <span>Produk Terpilih</span>
                <span id="summary-count">0 produk</span>
            </div>
            <div class="summary-items" id="summary-items">
                <!-- Summary items will be added here -->
            </div>
        </div>

        <!-- Payment Method and Total -->
        <div class="payment-section">
            <div class="payment-grid">
                <div class="payment-field">
                    <label class="field-label">
                        Metode Pembayaran
                    </label>
                    <select name="metode" id="payment-method" required class="form-input">
                        <option value="tunai">Tunai</option>
                        <option value="qris">Qris</option>
                        <option value="transfer">Transfer</option>
                        <option value="split">Split (tunai & non)</option>
                        <option value="non_tunai">Non Tunai</option>
                    </select>
                </div>

                <div class="payment-field">
                    <label class="field-label">
                        Total Pembayaran
                    </label>
                    <div class="total-amount" id="total-amount">Rp 0</div>
                </div>

                <!-- Button Simpan dipindahkan ke sini dan TAMBAHKAN ID -->
                <button type="button" id="save-transaction-floating" class="btn-primary">
                    <i class="fas fa-save"></i> Simpan Penjualan
                </button>
            </div>
        </div>
    </div>

    <hr style="margin:25px 0; border-color:#eee;">

    <h3 style="font-size:16px; margin-bottom:12px; color:#374151;">Transaksi Hari Ini</h3>

    <?php
    $today = date('Y-m-d');

    // Query: ambil semua order hari ini
    $q = "SELECT 
        j.id, 
        j.kode_transaksi, 
        j.tanggal, 
        j.waktu, 
        j.total_harga, 
        j.cara_bayar
      FROM penjualan j
      WHERE DATE(j.tanggal) = :today
      ORDER BY j.tanggal DESC, j.waktu DESC
      LIMIT 15";

    $s = $db->prepare($q);
    $s->bindParam(':today', $today);
    $s->execute();
    $orders = $s->fetchAll(PDO::FETCH_ASSOC);

    if (empty($orders)) {
        echo '<div class="alert" style="background-color:#f3f4f6; color:#6b7280; text-align:center; padding:20px;">';
        echo '<p style="margin:0; font-style:italic;"><i class="fas fa-clipboard-list"></i> Belum ada transaksi hari ini.</p>';
        echo '</div>';
    } else {
        echo '<div class="transaction-list">';

        foreach ($orders as $order) {
            // Ambil semua item dalam order ini
            $items_q = "SELECT d.qty, p.nama_produk
                    FROM detail_penjualan d
                    JOIN produk p ON p.id = d.produk_id
                    WHERE d.penjualan_id = ?
                    ORDER BY p.nama_produk";
            $items_s = $db->prepare($items_q);
            $items_s->execute([$order['id']]);
            $items = $items_s->fetchAll(PDO::FETCH_ASSOC);

            // Format cara bayar
            $cara = strtoupper($order['cara_bayar']);
            if ($cara == 'NON_TUNAI')
                $cara = 'NON TUNAI';
            if ($cara == 'QRIS')
                $cara = 'QRIS';

            echo '<div class="transaction-item">';
            echo '<div class="transaction-info">';
            echo '<div class="transaction-details">';

            // Kode transaksi
            echo '<div class="transaction-product"><strong>' . htmlspecialchars($order['kode_transaksi']) . '</strong></div>';

            // Produk + qty (satu per baris)
            echo '<div style="margin:8px 0; color:#374151; line-height:1.6;">';
            foreach ($items as $item) {
                echo '<div>' . htmlspecialchars($item['nama_produk']) . ' <span style="color:#10B981; font-weight:600;">• ' . $item['qty'] . '</span></div>';
            }
            echo '</div>';

            // Total & cara bayar di bawah
            echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px; flex-wrap:wrap; gap:8px;">';
            echo '<div style="font-weight:700; font-size:16px; color:#1f2937;">' . formatRupiah($order['total_harga']) . ' | ' . $cara . '</div>';
            // echo '<div style="font-weight:600; color:#7c3aed;">' . $cara . '</div>';
            echo '</div>';

            echo '</div>'; // transaction-details
    
            // Tombol Detail di kanan
            echo '<div class="transaction-actions">';
            // echo '<a href="' . BASE_URL . '/modules/penjualan/detail.php?id=' . $order['id'] . '" class="btn-link">👁️</a>';
            echo '<a href="' . BASE_URL . '/modules/penjualan/detail.php?id=' . $order['id'] . '" class="btn-link" title="Detail">' .
                '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#374151" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                        <path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    </a>';
            echo '</div>';

            echo '</div>'; // transaction-info
            echo '</div>'; // transaction-item
        }

        echo '</div>';
    }
    ?>

</div>

<script>
    // Data produk dari PHP
    const productsData = <?php echo json_encode($products); ?>;

    // State untuk menyimpan quantity per produk
    const productQuantities = {};

    // State untuk metode pembayaran
    let selectedPaymentMethod = 'tunai';

    // Inisialisasi quantity semua produk dengan 0
    productsData.forEach(product => {
        productQuantities[product.id] = 0;
    });

    // Fungsi untuk format angka ke Rupiah
    function formatRupiah(amount) {
        const num = parseInt(amount) || 0;
        return 'Rp ' + num.toLocaleString('id-ID');
    }

    // Fungsi update quantity display untuk image controls
    function updateQuantityDisplay(productId) {
        const qtyElement = document.getElementById(`qty-${productId}`);
        const minusBtn = document.querySelector(`.image-qty-minus[data-product-id="${productId}"]`);
        const plusBtn = document.querySelector(`.image-qty-plus[data-product-id="${productId}"]`);
        const productCard = document.querySelector(`[data-product-id="${productId}"]`);
        // Tambahkan update untuk display angka di image controls
        const imageQtyElement = document.getElementById(`image-qty-${productId}`);
        // Pastikan update display angka selalu dipanggil dan textContent di-set ulang
        if (imageQtyElement) {
            imageQtyElement.textContent = productQuantities[productId]; // Pastikan text diupdate
            if (productQuantities[productId] > 0) {
                imageQtyElement.style.display = 'inline-flex';
            } else {
                imageQtyElement.style.display = 'none';
            }
        }

        if (qtyElement) {
            qtyElement.textContent = productQuantities[productId];
        }

        // Logika tampilan tombol
        if (productQuantities[productId] > 0) {
            // Tampilkan minus button
            if (minusBtn) minusBtn.style.display = 'flex';
            // Update posisi plus button
            if (plusBtn) plusBtn.style.marginLeft = '0';

            if (productCard) productCard.classList.add('active');
        } else {
            // Sembunyikan minus button
            if (minusBtn) minusBtn.style.display = 'none';
            // Reset posisi plus button
            if (plusBtn) plusBtn.style.marginLeft = 'auto';

            if (productCard) productCard.classList.remove('active');
        }
    }

    // Fungsi untuk update total amount dan summary
    function updateTotalAmount() {
        let total = 0;
        let selectedCount = 0;
        let selectedItems = [];

        productsData.forEach(product => {
            const qty = productQuantities[product.id] || 0;
            if (qty > 0) {
                const subtotal = product.harga_jual * qty;
                total += subtotal;
                selectedCount++;
                selectedItems.push({
                    name: product.nama_produk,
                    qty: qty,
                    price: product.harga_jual,
                    subtotal: subtotal
                });
            }
        });

        // Update total amount display di floating bar
        const totalElement = document.getElementById('total-amount');
        if (totalElement) {
            totalElement.textContent = formatRupiah(total);
        }

        // Update summary
        const summaryCount = document.getElementById('summary-count');
        const summaryItems = document.getElementById('summary-items');

        if (selectedCount > 0) {
            if (summaryCount) summaryCount.textContent = `${selectedCount} produk`;

            // Update summary items
            if (summaryItems) {
                summaryItems.innerHTML = '';
                selectedItems.forEach(item => {
                    const itemDiv = document.createElement('div');
                    itemDiv.className = 'summary-item';
                    itemDiv.innerHTML = `
                        <span>${item.name}</span>
                        <span>${item.qty} × ${formatRupiah(item.price)}</span>
                    `;
                    summaryItems.appendChild(itemDiv);
                });
            }
        }

        // Update floating bar visibility
        const floatingBar = document.getElementById('floating-bar');
        const stickyContainer = document.getElementById('sticky-save-container');

        if (total > 0) {
            floatingBar.classList.add('visible');
            if (stickyContainer) stickyContainer.style.display = 'block';
        } else {
            floatingBar.classList.remove('visible');
            if (stickyContainer) stickyContainer.style.display = 'none';
        }

        return { total, selectedCount, selectedItems };
    }

    // Fungsi untuk handle plus button
    function handlePlus(productId) {
        const product = productsData.find(p => p.id == productId);
        if (!product) return;

        // Untuk produk es teh, TIDAK ADA validasi stok
        productQuantities[productId] = (productQuantities[productId] || 0) + 1;
        updateQuantityDisplay(productId);
        updateTotalAmount();
    }

    // Fungsi untuk handle minus button
    function handleMinus(productId) {
        const currentQty = productQuantities[productId] || 0;
        if (currentQty > 0) {
            productQuantities[productId] = currentQty - 1;
            updateQuantityDisplay(productId);
            updateTotalAmount();
        }
    }

    // Fungsi untuk menyimpan transaksi
    function saveTransaction() {
        // Ambil data terbaru dari updateTotalAmount
        const { total, selectedCount, selectedItems } = updateTotalAmount();

        // Cek minimal ada 1 produk dengan quantity > 0
        if (selectedCount === 0) {
            alert('Pilih minimal 1 produk dengan mengklik tombol "+"');
            return;
        }

        // Cek metode pembayaran dari select di floating bar
        const paymentMethodSelect = document.getElementById('payment-method');
        if (!paymentMethodSelect || !paymentMethodSelect.value) {
            alert('Pilih metode pembayaran');
            return;
        }

        const paymentMethod = paymentMethodSelect.value;

        // Tampilkan loading spinner
        const loadingSpinner = document.getElementById('loading-spinner');
        if (loadingSpinner) loadingSpinner.style.display = 'flex';

        // Siapkan form data
        const form = document.getElementById('penjualan-form');
        if (!form) {
            alert('Form tidak ditemukan');
            if (loadingSpinner) loadingSpinner.style.display = 'none';
            return;
        }

        // Clear previous inputs kecuali hidden field pertama
        const existingInputs = form.querySelectorAll('input[name="produk_id[]"], input[name="qty[]"]');
        existingInputs.forEach(input => input.remove());

        // Tambahkan produk dengan quantity > 0 ke form
        selectedItems.forEach(item => {
            const product = productsData.find(p => p.nama_produk === item.name);
            if (product && item.qty > 0) {
                // Add product_id input
                const productIdInput = document.createElement('input');
                productIdInput.type = 'hidden';
                productIdInput.name = 'produk_id[]';
                productIdInput.value = product.id;
                form.appendChild(productIdInput);

                // Add qty input
                const qtyInput = document.createElement('input');
                qtyInput.type = 'hidden';
                qtyInput.name = 'qty[]';
                qtyInput.value = item.qty;
                form.appendChild(qtyInput);
            }
        });

        // Tambahkan metode pembayaran jika belum ada
        let methodInput = form.querySelector('input[name="metode"]');
        if (!methodInput) {
            methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = 'metode';
            form.appendChild(methodInput);
        }
        methodInput.value = paymentMethod;

        // Submit form
        setTimeout(() => {
            form.submit();
        }, 500);
    }

    // Event Listeners
    document.addEventListener('DOMContentLoaded', function () {
        // Event delegation untuk tombol di image container
        document.addEventListener('click', function (e) {
            const plusBtn = e.target.closest('.image-qty-plus');
            const minusBtn = e.target.closest('.image-qty-minus');

            if (plusBtn) {
                const productId = plusBtn.getAttribute('data-product-id');
                handlePlus(productId);
            }

            if (minusBtn) {
                const productId = minusBtn.getAttribute('data-product-id');
                handleMinus(productId);
            }
        });

        // Event listener untuk save button di floating bar
        const saveButtonFloating = document.getElementById('save-transaction-floating');
        if (saveButtonFloating) {
            saveButtonFloating.addEventListener('click', saveTransaction);
        }

        // Event listener untuk save button di sticky container
        const saveButtonSticky = document.getElementById('save-transaction-sticky');
        if (saveButtonSticky) {
            saveButtonSticky.addEventListener('click', saveTransaction);
        }

        // Event listener untuk reset form (klik dua kali pada save button)
        if (saveButtonFloating) {
            saveButtonFloating.addEventListener('dblclick', function () {
                if (confirm('Reset semua pilihan produk?')) {
                    // Reset semua quantity ke 0
                    productsData.forEach(product => {
                        productQuantities[product.id] = 0;
                        updateQuantityDisplay(product.id);
                    });
                    updateTotalAmount();
                }
            });
        }

        // Event listener untuk metode pembayaran
        const paymentMethodSelect = document.getElementById('payment-method');
        if (paymentMethodSelect) {
            paymentMethodSelect.addEventListener('change', function () {
                selectedPaymentMethod = this.value;
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function (e) {
            // Ctrl + S untuk save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                saveTransaction();
            }
        });

        // Handle scroll untuk sticky container
        window.addEventListener('scroll', function () {
            const stickyContainer = document.getElementById('sticky-save-container');
            if (stickyContainer && stickyContainer.style.display === 'block') {
                if (window.scrollY > 100) {
                    stickyContainer.classList.add('scrolled');
                } else {
                    stickyContainer.classList.remove('scrolled');
                }
            }
        });

        // Initial update
        updateTotalAmount();

        // Optimasi gambar: Tambahkan event listener untuk semua gambar
        document.querySelectorAll('.product-image').forEach(img => {
            // Jika gambar sudah dimuat, tampilkan
            if (img.complete) {
                img.classList.add('loaded');
            }
        });
    });

    // Fallback untuk browser yang tidak support Intersection Observer
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                    }
                    imageObserver.unobserve(img);
                }
            });
        });

        // Observe semua gambar yang belum dimuat
        document.querySelectorAll('.product-image[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
</script>

<?php include '../../includes/footer.php'; ?>