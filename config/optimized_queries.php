<?php
// config/optimized_queries.php
// Optimized queries with caching for better performance

class OptimizedQueries {
    private $db;
    private $cache = [];
    private $cache_duration = 300; // 5 minutes
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Get dashboard statistics with caching
     */
    public function getDashboardStats($force_refresh = false) {
        $cache_key = 'dashboard_stats_' . date('Y-m-d');
        
        if (!$force_refresh && isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        $today = date('Y-m-d');
        $month = date('Y-m');
        
        // Single query untuk semua stats
        $query = "
            SELECT 
                (SELECT COUNT(*) FROM produk WHERE jenis = 'es_teh') as total_produk,
                (SELECT COUNT(*) FROM penjualan WHERE DATE(tanggal) = :today) as transaksi_hari,
                (SELECT COALESCE(SUM(total_harga), 0) FROM penjualan WHERE DATE(tanggal) = :today) as penjualan_hari,
                (SELECT COALESCE(SUM(total_harga), 0) FROM penjualan WHERE DATE_FORMAT(tanggal, '%Y-%m') = :month) as pendapatan_bulan,
                (SELECT COUNT(*) FROM produk WHERE stok <= min_stok AND stok > 0) as stok_menipis,
                (SELECT COALESCE(SUM(jumlah), 0) FROM pengeluaran WHERE DATE_FORMAT(tanggal, '%Y-%m') = :month) as pengeluaran_bulan
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':today', $today);
        $stmt->bindParam(':month', $month);
        $stmt->execute();
        
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Hitung profit
        $stats['profit_bulan'] = $stats['pendapatan_bulan'] - $stats['pengeluaran_bulan'];
        
        // Cache the results
        $this->cache[$cache_key] = $stats;
        
        return $stats;
    }
    
    /**
     * Get best selling products with limit
     */
    public function getBestSellers($days = 7, $limit = 5) {
        $cache_key = 'best_sellers_' . $days . '_' . $limit;
        
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        $query = "
            SELECT 
                p.nama_produk,
                SUM(d.qty) as total_terjual,
                SUM(d.subtotal) as total_pendapatan
            FROM detail_penjualan d
            JOIN produk p ON d.produk_id = p.id
            JOIN penjualan j ON d.penjualan_id = j.id
            WHERE j.tanggal >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY p.id
            ORDER BY total_terjual DESC
            LIMIT :limit
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':days', (int)$days, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->cache[$cache_key] = $results;
        
        return $results;
    }
    
    /**
     * Get sales data for charts
     */
    public function getChartData($days = 7) {
        $cache_key = 'chart_data_' . $days;
        
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        $query = "
            SELECT 
                DATE(tanggal) as tanggal,
                COUNT(*) as jumlah_transaksi,
                COALESCE(SUM(total_harga), 0) as total_penjualan
            FROM penjualan
            WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY DATE(tanggal)
            ORDER BY tanggal
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':days', (int)$days, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->cache[$cache_key] = $results;
        
        return $results;
    }
    
    /**
     * Clear cache
     */
    public function clearCache($key = null) {
        if ($key) {
            unset($this->cache[$key]);
        } else {
            $this->cache = [];
        }
    }
    
    /**
     * Get low stock products
     */
    public function getLowStockProducts($limit = 10) {
        $query = "
            SELECT 
                kode_produk,
                nama_produk,
                stok,
                min_stok,
                satuan
            FROM produk
            WHERE stok <= min_stok AND stok > 0
            ORDER BY stok ASC
            LIMIT :limit
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get recent sales
     */
    public function getRecentSales($limit = 5) {
        $query = "
            SELECT 
                kode_transaksi,
                tanggal,
                waktu,
                total_harga,
                cara_bayar
            FROM penjualan
            ORDER BY tanggal DESC, waktu DESC
            LIMIT :limit
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>