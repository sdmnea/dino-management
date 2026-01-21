<?php
// includes/footer.php - FIXED NAVIGATION LINKS
?>
<!-- End of main content -->
</div><!-- End of main-container -->

<!-- Navigation Dropdown -->
<div id="nav-dropdown" class="nav-dropdown">
    <div class="nav-dropdown-header">
        <h3>Navigasi</h3>
        <button type="button" class="nav-dropdown-close">&times;</button>
    </div>

    <div class="nav-dropdown-body">
        <!-- Laporan Section -->
        <div class="nav-dropdown-section">
            <div class="nav-dropdown-section-header">
                <i class="fas fa-chart-bar"></i>
                <span>Laporan</span>
                <button type="button" class="nav-dropdown-section-toggle">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div class="nav-dropdown-section-content">
                <a href="<?php echo BASE_URL; ?>/modules/laporan/pendapatan.php" class="nav-dropdown-subitem">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Laporan Pendapatan</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/modules/laporan/sdm.php" class="nav-dropdown-subitem">
                    <i class="fas fa-users"></i>
                    <span>Laporan SDM</span>
                </a>
            </div>
        </div>

        <!-- Inventory Section -->
        <div class="nav-dropdown-section">
            <div class="nav-dropdown-section-header">
                <i class="fas fa-boxes"></i>
                <span>Inventory</span>
                <button type="button" class="nav-dropdown-section-toggle">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div class="nav-dropdown-section-content">
                <a href="<?php echo BASE_URL; ?>/modules/inventory/pengeluaran.php" class="nav-dropdown-subitem">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Input Pengeluaran</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/modules/inventory/gudang.php" class="nav-dropdown-subitem">
                    <i class="fas fa-warehouse"></i>
                    <span>Gudang</span>
                </a>
            </div>
        </div>

        <!-- Data Master Section -->
        <div class="nav-dropdown-section">
            <div class="nav-dropdown-section-header">
                <i class="fas fa-database"></i>
                <span>Data Master</span>
                <button type="button" class="nav-dropdown-section-toggle">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div class="nav-dropdown-section-content">
                <a href="<?php echo BASE_URL; ?>/modules/master/kategori.php" class="nav-dropdown-subitem">
                    <i class="fas fa-tags"></i>
                    <span>Kategori</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/modules/master/supplier.php" class="nav-dropdown-subitem">
                    <i class="fas fa-truck"></i>
                    <span>Supplier</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/modules/master/pelanggan.php" class="nav-dropdown-subitem">
                    <i class="fas fa-users"></i>
                    <span>Pelanggan</span>
                </a>
            </div>
        </div>

        <!-- Sistem Section -->
        <div class="nav-dropdown-section">
            <div class="nav-dropdown-section-header">
                <i class="fas fa-cog"></i>
                <span>Sistem</span>
                <button type="button" class="nav-dropdown-section-toggle">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <div class="nav-dropdown-section-content">
                <a href="<?php echo BASE_URL; ?>/modules/sistem/pengguna.php" class="nav-dropdown-subitem">
                    <i class="fas fa-user-cog"></i>
                    <span>Pengguna</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/modules/sistem/backup.php" class="nav-dropdown-subitem">
                    <i class="fas fa-database"></i>
                    <span>Backup Data</span>
                </a>
                <a href="<?php echo BASE_URL; ?>/modules/sistem/log.php" class="nav-dropdown-subitem">
                    <i class="fas fa-history"></i>
                    <span>Log Aktivitas</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Navigation -->
<nav class="mobile-nav">
    <a href="<?php echo BASE_URL; ?>/dashboard.php"
        class="nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
        <i class="fas fa-home"></i>
        <span>Dashboard</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/modules/produk/index.php"
        class="nav-item <?php echo $current_page == 'index.php' && strpos($_SERVER['REQUEST_URI'], 'produk') !== false ? 'active' : ''; ?>">
        <i class="fas fa-box"></i>
        <span>Produk</span>
    </a>
    <a href="<?php echo BASE_URL; ?>/modules/penjualan/index.php"
        class="nav-item <?php echo $current_page == 'index.php' && strpos($_SERVER['REQUEST_URI'], 'penjualan') !== false ? 'active' : ''; ?>">
        <i class="fas fa-cash-register"></i>
        <span>Jual</span>
    </a>
    <a href="javascript:void(0);" class="nav-item nav-trigger">
        <i class="fas fa-bars"></i>
        <span>Navigasi</span>
    </a>
</nav>

<footer style="text-align: center; padding: 20px; color: #666; font-size: 12px; margin-top: 30px;">
    <p>&copy; <?php echo date('Y'); ?> Es Teh Dino Management System v1.0</p>
    <p style="margin-top: 5px; font-size: 11px; color: #999;">
        <span style="color: #10B981;">● Online</span> |
        <?php echo BASE_URL; ?>
    </p>
</footer>

<style>
    /* Navigation Dropdown Styles */
    .nav-dropdown {
        position: fixed;
        bottom: 80px;
        /* Posisi di atas tombol navigasi mobile */
        right: 20px;
        background: white;
        border-radius: 12px;
        width: 300px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        display: none;
        z-index: 9999;
        border: 1px solid #e5e7eb;
        animation: dropdownFadeIn 0.2s ease;
        max-height: 70vh;
        overflow-y: auto;
    }

    @keyframes dropdownFadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .nav-dropdown.active {
        display: block;
    }

    /* Untuk desktop/tablet - posisi dropdown tepat di atas tombol navigasi */
    @media (min-width: 769px) {
        .nav-dropdown {
            /* Hitung posisi tombol navigasi ke-4 (tombol terakhir) */
            /* Tombol navigasi ada 4, masing-masing 25% lebar layar */
            /* Tombol ke-4 berada di posisi 75%-100% dari kiri */
            /* Kita posisikan dropdown agar muncul di atas tombol ke-4 */
            left: auto;
            right: 2%;
            /* Sesuaikan dengan margin kanan */
            bottom: 85px;
            /* Naikkan sedikit dari mobile */
            width: 320px;
            transform: none;
            /* Hapus transform centering */
        }

        /* Untuk layar yang sangat besar, kita bisa kalkulasi lebih tepat */
        @media (min-width: 1024px) {
            .nav-dropdown {
                width: 350px;
                bottom: 90px;
                right: calc(12.5% - 175px);
                /* Pusatkan di atas tombol ke-4 */
                /* Penjelasan: 
               - Layar 100%, 4 tombol = 25% per tombol
               - Tombol ke-4 berada di 75%-100% dari kiri
               - Pusat tombol ke-4 = 87.5% dari kiri
               - Untuk centering: right = (100% - 87.5%) - (width/2) 
               - right = 12.5% - 175px (setengah dari 350px)
            */
            }
        }
    }

    /* Untuk mobile - dropdown full width dan di tengah */
    @media (max-width: 768px) {
        .nav-dropdown {
            width: calc(100% - 40px);
            left: 20px;
            right: 20px;
            bottom: 70px;
            margin: 0 auto;
            /* Center di mobile */
        }
    }

    @media (max-width: 480px) {
        .nav-dropdown {
            width: calc(100% - 24px);
            left: 12px;
            right: 12px;
            bottom: 65px;
        }
    }

    .nav-dropdown-header {
        padding: 16px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        background: white;
        z-index: 1;
        border-radius: 12px 12px 0 0;
    }

    .nav-dropdown-header h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
        color: #1f2937;
    }

    .nav-dropdown-close {
        background: none;
        border: none;
        font-size: 20px;
        cursor: pointer;
        color: #6b7280;
        padding: 4px;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.2s ease;
    }

    .nav-dropdown-close:hover {
        background-color: #f3f4f6;
        color: #374151;
    }

    .nav-dropdown-body {
        padding: 12px;
    }

    /* Navigation Sections dalam dropdown */
    .nav-dropdown-section {
        margin-bottom: 8px;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        overflow: hidden;
        background: white;
    }

    .nav-dropdown-section-header {
        display: flex;
        align-items: center;
        padding: 12px 14px;
        background-color: #f9fafb;
        cursor: pointer;
        transition: background-color 0.2s ease;
        user-select: none;
    }

    .nav-dropdown-section-header:hover {
        background-color: #f3f4f6;
    }

    .nav-dropdown-section-header i {
        margin-right: 10px;
        color: #4b5563;
        font-size: 14px;
        width: 18px;
        text-align: center;
    }

    .nav-dropdown-section-header span {
        flex: 1;
        font-weight: 600;
        color: #374151;
        font-size: 14px;
    }

    .nav-dropdown-section-toggle {
        background: none;
        border: none;
        cursor: pointer;
        color: #6b7280;
        padding: 4px;
        transition: transform 0.3s ease;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
    }

    .nav-dropdown-section-toggle:hover {
        background-color: #f3f4f6;
    }

    .nav-dropdown-section.active .nav-dropdown-section-toggle {
        transform: rotate(180deg);
    }

    .nav-dropdown-section-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
    }

    .nav-dropdown-section.active .nav-dropdown-section-content {
        max-height: 300px;
    }

    /* Navigation Subitems dalam dropdown */
    .nav-dropdown-subitem {
        display: flex;
        align-items: center;
        padding: 10px 14px;
        padding-left: 42px;
        /* Menyesuaikan dengan header */
        text-decoration: none;
        color: #4b5563;
        border-top: 1px solid #e5e7eb;
        transition: all 0.2s ease;
        font-size: 13px;
    }

    .nav-dropdown-subitem:hover {
        background-color: #f3f4f6;
        color: #1f2937;
    }

    .nav-dropdown-subitem i {
        margin-right: 10px;
        color: #6b7280;
        font-size: 13px;
        width: 16px;
        text-align: center;
    }

    .nav-dropdown-subitem.active {
        color: #10B981;
        background-color: #f0fdf4;
        font-weight: 600;
    }

    .nav-dropdown-subitem.active i {
        color: #10B981;
    }

    /* Mobile Navigation Trigger - tambah indicator */
    .mobile-nav .nav-item.nav-trigger {
        position: relative;
        transition: all 0.3s ease;
    }

    .mobile-nav .nav-item.nav-trigger.active {
        color: #10B981;
        background-color: #f0fdf4;
    }

    .mobile-nav .nav-item.nav-trigger.active i {
        transform: rotate(90deg);
        transition: transform 0.3s ease;
    }

    /* Mobile Navigation Styles (existing) */
    .mobile-nav {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: white;
        display: flex;
        justify-content: space-around;
        padding: 10px 0;
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
        z-index: 1000;
        border-top: 1px solid #e5e7eb;
        width: 100%;
    }

    .mobile-nav .nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-decoration: none;
        color: #6b7280;
        padding: 8px 12px;
        border-radius: 8px;
        transition: all 0.2s ease;
        min-width: 60px;
        flex: 1;
        max-width: 25%;
        /* 4 item, masing-masing 25% */
    }

    .mobile-nav .nav-item i {
        font-size: 20px;
        margin-bottom: 4px;
        transition: transform 0.3s ease;
    }

    .mobile-nav .nav-item span {
        font-size: 11px;
        font-weight: 500;
    }

    .mobile-nav .nav-item.active {
        color: #10B981;
        background-color: #f0fdf4;
    }

    .mobile-nav .nav-item:hover {
        color: #374151;
        background-color: #f9fafb;
    }

    /* Untuk layar desktop yang lebih besar */
    @media (min-width: 769px) {
        .mobile-nav {
            max-width: 100%;
            /* Ubah dari 500px ke 100% */
            margin: 0;
            left: 0;
            transform: none;
            /* Hapus transform */
            border-radius: 0;
            bottom: 0;
            padding: 12px 0;
            /* Sedikit lebih besar di desktop */
        }

        /* Tombol navigasi di desktop */
        .mobile-nav .nav-item {
            position: relative;
        }

        /* Tombol navigasi ke-4 (tombol terakhir) */
        .mobile-nav .nav-item:nth-child(4) {
            /* Tidak ada style khusus, hanya untuk referensi */
        }
    }

    /* Untuk layar yang sangat besar (desktop besar) */
    @media (min-width: 1024px) {
        .mobile-nav {
            padding: 15px 0;
        }

        .mobile-nav .nav-item {
            min-width: 80px;
        }

        .mobile-nav .nav-item i {
            font-size: 22px;
        }

        .mobile-nav .nav-item span {
            font-size: 12px;
        }
    }
</style>

<script>
    // Debug info
    console.log('Base URL:', '<?php echo BASE_URL; ?>');
    console.log('Current Page:', '<?php echo $current_page; ?>');
    console.log('Full URL:', window.location.href);

    // Form validation
    document.addEventListener('submit', function (e) {
        const submitBtn = e.target.querySelector('button[type="submit"]');
        if (submitBtn) {
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';

            // Restore button after 5 seconds (fallback)
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }, 5000);
        }
    });

    // Auto-focus first input in forms
    document.addEventListener('DOMContentLoaded', function () {
        const firstInput = document.querySelector('input[type="text"], input[type="email"], input[type="number"], textarea');
        if (firstInput && firstInput.type !== 'hidden') {
            firstInput.focus();
        }

        // Navigation Dropdown Functionality
        const navDropdown = document.getElementById('nav-dropdown');
        const navTrigger = document.querySelector('.nav-trigger');
        const navClose = document.querySelector('.nav-dropdown-close');
        const navSections = document.querySelectorAll('.nav-dropdown-section');
        const navSubitems = document.querySelectorAll('.nav-dropdown-subitem');

        // Open dropdown when clicking nav trigger
        if (navTrigger) {
            navTrigger.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                navDropdown.classList.toggle('active');
                this.classList.toggle('active');
            });
        }

        // Close dropdown when clicking close button
        if (navClose) {
            navClose.addEventListener('click', function (e) {
                e.stopPropagation();
                navDropdown.classList.remove('active');
                if (navTrigger) {
                    navTrigger.classList.remove('active');
                }
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (navDropdown && navDropdown.classList.contains('active') &&
                !navDropdown.contains(e.target) &&
                !(navTrigger && navTrigger.contains(e.target))) {
                navDropdown.classList.remove('active');
                if (navTrigger) {
                    navTrigger.classList.remove('active');
                }
            }
        });

        // Close dropdown with Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && navDropdown && navDropdown.classList.contains('active')) {
                navDropdown.classList.remove('active');
                if (navTrigger) {
                    navTrigger.classList.remove('active');
                }
            }
        });

        // Toggle dropdown sections
        navSections.forEach(section => {
            const header = section.querySelector('.nav-dropdown-section-header');
            const toggleBtn = section.querySelector('.nav-dropdown-section-toggle');

            function toggleSection() {
                section.classList.toggle('active');
            }

            if (header) {
                header.addEventListener('click', toggleSection);
            }

            if (toggleBtn) {
                toggleBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    toggleSection();
                });
            }
        });

        // Auto-expand section if current page matches
        navSections.forEach(section => {
            const links = section.querySelectorAll('.nav-dropdown-subitem');
            let shouldExpand = false;

            links.forEach(link => {
                const href = link.getAttribute('href');
                if (href && window.location.href.includes(href)) {
                    shouldExpand = true;
                    link.classList.add('active');
                }
            });

            if (shouldExpand) {
                section.classList.add('active');
            }
        });

        // Handle navigation subitem clicks
        navSubitems.forEach(item => {
            item.addEventListener('click', function () {
                // Close dropdown after navigation
                setTimeout(() => {
                    navDropdown.classList.remove('active');
                    if (navTrigger) {
                        navTrigger.classList.remove('active');
                    }
                }, 300);
            });
        });

        // Prevent dropdown from closing when clicking inside
        navDropdown.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    });
</script>
</body>

</html>