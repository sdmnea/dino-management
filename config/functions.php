<?php
// config/functions.php - TAMBAH FUNGSI PATH

// =============================================
// PATH & URL HELPER FUNCTIONS
// =============================================

/**
 * Get absolute URL untuk file/folder
 */
function base_url($path = '')
{
    $url = BASE_URL;
    if ($path) {
        // Pastikan tidak ada double slash
        $url = rtrim($url, '/') . '/' . ltrim($path, '/');
    }
    return $url;
}

/**
 * Get absolute path untuk file/folder
 */
function root_path($path = '')
{
    $root = ROOT_PATH;
    if ($path) {
        $root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }
    return $root;
}

/**
 * Redirect dengan URL absolute
 */
function redirect($url)
{
    // Jika URL relatif, konversi ke absolute
    if (strpos($url, 'http') !== 0) {
        $url = base_url($url);
    }

    if (!headers_sent()) {
        header("Location: " . $url);
        exit();
    } else {
        echo "<script>window.location.href='" . $url . "';</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url=" . $url . "'></noscript>";
        exit();
    }
}

/**
 * Cek apakah user sudah login
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Clean input data
 */
function cleanInput($data)
{
    if (empty($data))
        return '';
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Format Rupiah
 */
function formatRupiah($angka)
{
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

/**
 * Get greeting berdasarkan waktu
 */
function getGreeting()
{
    $hour = date('H');
    if ($hour < 12)
        return 'Selamat Pagi';
    if ($hour < 15)
        return 'Selamat Siang';
    if ($hour < 18)
        return 'Selamat Sore';
    return 'Selamat Malam';
}

/**
 * Validasi dan upload gambar
 */
function uploadGambar($file, $target_dir = 'assets/uploads/')
{
    $errors = [];

    // Cek jika ada file
    if (empty($file['name'])) {
        return ['errors' => ['Tidak ada file yang diupload']];
    }

    // Cek error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['errors' => ['Error upload file: ' . $file['error']]];
    }

    // Validasi ukuran
    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = "Ukuran file terlalu besar (maks " . (MAX_FILE_SIZE / 1024 / 1024) . "MB)";
    }

    // Validasi tipe file
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed)) {
        $errors[] = "Hanya file JPG, JPEG, PNG & GIF yang diperbolehkan";
    }

    // Cek ekstensi
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
        $errors[] = "Ekstensi file tidak valid";
    }

    if (!empty($errors)) {
        return ['errors' => $errors];
    }

    // Buat nama file unik
    $filename = uniqid() . '_' . time() . '.' . $ext;
    $target_path = root_path($target_dir . $filename);

    // Pastikan folder upload ada
    $upload_dir = dirname($target_path);
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Upload file
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return $target_dir . $filename; // Return relative path
    } else {
        return ['errors' => ['Gagal mengupload file']];
    }
}

/**
 * Generate kode transaksi
 */
function generateKodeTransaksi()
{
    return 'TRX-' . date('Ymd') . '-' . substr(strtoupper(uniqid()), -6);
}

/**
 * Debug helper
 */
function debug($data, $exit = true)
{
    echo '<pre>';
    var_dump($data);
    echo '</pre>';
    if ($exit)
        exit();
}

/**
 * Get current URL
 */
function current_url()
{
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
        . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Sanitize output
 */
function sanitizeOutput($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Get pagination links
 */
function pagination($total_pages, $current_page, $url)
{
    if ($total_pages <= 1)
        return '';

    $links = [];

    // Previous link
    if ($current_page > 1) {
        $links[] = '<a href="' . $url . '&page=' . ($current_page - 1) . '" class="pagination-link">« Prev</a>';
    }

    // Page links
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);

    for ($i = $start; $i <= $end; $i++) {
        $active = $i == $current_page ? 'active' : '';
        $links[] = '<a href="' . $url . '&page=' . $i . '" class="pagination-link ' . $active . '">' . $i . '</a>';
    }

    // Next link
    if ($current_page < $total_pages) {
        $links[] = '<a href="' . $url . '&page=' . ($current_page + 1) . '" class="pagination-link">Next »</a>';
    }

    return '<div class="pagination">' . implode('', $links) . '</div>';
}

/**
 * Check if a column exists in a table (returns bool)
 */
function columnExists(PDO $db, $table, $column)
{
    try {
        // Use INFORMATION_SCHEMA for more reliable checking
        $sql = "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = :tbl 
                AND COLUMN_NAME = :col";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':tbl', $table);
        $stmt->bindValue(':col', $column);
        $stmt->execute();
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return isset($res['cnt']) && $res['cnt'] > 0;
    } catch (Exception $e) {
        error_log('columnExists check failed: ' . $e->getMessage());
        return false;
    }
}
?>