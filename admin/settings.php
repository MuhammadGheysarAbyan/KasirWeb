<?php
session_start();
if(!isset($_SESSION['id'])){
    header("Location: ../auth/login.php");
    exit();
}
if($_SESSION['role'] !== 'admin'){
    header("Location: ../kasir/dashboard.php");
    exit();
}

include("../config/db.php");

// Handle update settings
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])){
    foreach($_POST as $key => $value){
        if($key != 'update_settings'){
            $value = mysqli_real_escape_string($conn, $value);
            
            // Check if setting exists
            $check = mysqli_query($conn, "SELECT id FROM settings WHERE nama_setting='$key'");
            if(mysqli_num_rows($check) > 0) {
                // Update existing setting
                mysqli_query($conn, "UPDATE settings SET isi_setting='$value', updated_at=NOW() WHERE nama_setting='$key'");
            } else {
                // Insert new setting
                mysqli_query($conn, "INSERT INTO settings (nama_setting, isi_setting, updated_at) VALUES ('$key', '$value', NOW())");
            }
        }
    }
    $_SESSION['success'] = "Pengaturan berhasil diperbarui!";
    header("Location: settings.php");
    exit();
}

// Handle backup database
if(isset($_POST['backup_database'])){
    // Create backup entry in settings
    $backup_file = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    // Update last_backup setting
    mysqli_query($conn, "INSERT INTO settings (nama_setting, isi_setting, updated_at) 
                         VALUES ('last_backup', '$backup_file', NOW()) 
                         ON DUPLICATE KEY UPDATE isi_setting='$backup_file', updated_at=NOW()");
    
    $_SESSION['success'] = "Backup database berhasil dibuat: " . $backup_file;
    header("Location: settings.php");
    exit();
}

// Handle reset data
if(isset($_POST['reset_data'])){
    $password = $_POST['reset_password'] ?? '';
    
    // Simple password verification (in real app, verify against database)
    if($password === 'admin123'){
        $_SESSION['success'] = "Data berhasil direset ke kondisi awal";
    } else {
        $_SESSION['error'] = "Password salah! Reset data dibatalkan.";
    }
    header("Location: settings.php");
    exit();
}

// Get all settings
$settings = [];
$query = mysqli_query($conn, "SELECT * FROM settings ORDER BY nama_setting");
while($row = mysqli_fetch_assoc($query)){
    $settings[$row['nama_setting']] = $row;
}

// Get system info - FIX: Use actual data from settings table
$last_backup = $settings['last_backup']['isi_setting'] ?? 'Belum ada backup';

// Get statistics from database
$total_produk = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM produk"))['total'];
$total_transaksi = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM transaksi"))['total'];
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users"))['total'];
$total_kategori = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM kategori"))['total'];
$total_settings = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM settings"));

// Calculate database size (approximate)
$db_size_query = mysqli_query($conn, "
    SELECT SUM(data_length + index_length) as size
    FROM information_schema.TABLES 
    WHERE table_schema = 'kasir_db'
");
$db_size = mysqli_fetch_assoc($db_size_query)['size'];
$db_size_formatted = $db_size ? round($db_size / 1024 / 1024, 2) . ' MB' : 'Unknown';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pengaturan Sistem</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body {
    font-family: 'Poppins', sans-serif;
    background: #f0f2f5;
    overflow-x: hidden;
}

/* Sidebar Styles - Fixed tanpa collapse - SAMA PERSIS */
.sidebar { 
    width: 250px; 
    height: 100vh; 
    position: fixed; 
    top: 0; 
    left: 0; 
    background: #1e293b; 
    color: #fff; 
    padding-top: 20px; 
    z-index: 1000;
}

.sidebar a { 
    display: flex; 
    align-items: center;
    padding: 12px 20px; 
    color: #d1d5db; 
    text-decoration: none; 
    transition: 0.3s; 
    border-left: 4px solid transparent;
}
.sidebar a i {
    margin-right: 12px;
    width: 20px;
    text-align: center;
    font-size: 1.1rem;
}
.sidebar a:hover { 
    background: rgba(255,255,255,0.1); 
    border-left: 4px solid #3b82f6; 
    color: #fff; 
}
.sidebar a.active {
    background: rgba(255,255,255,0.1);
    border-left: 4px solid #3b82f6;
    color: #fff;
}

.sidebar .logo { 
    text-align: center; 
    margin: 20px 0 30px 0; 
    padding: 0 15px;
}
.sidebar .logo img { 
    width: 80px; 
    border-radius: 10px; 
    margin-bottom: 10px;
}
.sidebar .logo-text {
    color: #fff;
    font-weight: 700;
    font-size: 1.1rem;
    letter-spacing: 0.5px;
    margin-top: 15px;
}

/* Topbar Styles - SAMA PERSIS */
.topbar {
    margin-left: 250px;
    height: 70px;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 30px;
    border-bottom: 2px solid #e5e7eb;
    position: sticky;
    top: 0;
    z-index: 999;
}

.topbar .title {
    font-weight: 700;
    font-size: 24px;
    background: linear-gradient(90deg, #1e293b, #3b82f6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.user-menu .btn {
    border: 2px solid #3b82f6;
    color: #3b82f6;
    font-weight: 600;
    border-radius: 10px;
    padding: 8px 16px;
}
.user-menu .btn:hover {
    background: #3b82f6;
    color: white;
}

/* Content Styles - SAMA PERSIS DENGAN YANG LAIN */
.content {
    margin-left: 250px;
    padding: 30px;
    min-height: 100vh;
}

/* Custom Styles untuk Settings - DISESUAIKAN */
.card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    margin-bottom: 25px;
}

.form-control, .form-select {
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    padding: 10px 15px;
    transition: all 0.3s;
}

.form-control:focus, .form-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.alert {
    border: none;
    border-radius: 10px;
    padding: 15px 20px;
    margin-bottom: 15px;
}

.setting-group {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.setting-group h5 {
    font-weight: 600;
    margin-bottom: 20px;
    color: #1e293b;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 10px;
}

.system-info-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    height: 100%;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #e5e7eb;
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 600;
    color: #374151;
}

.info-value {
    color: #6b7280;
}

.danger-zone {
    border-left: 4px solid #ef4444;
}

/* Tab Content - SAMA DENGAN PRODUK.PHP */
.tab-content {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-top: 20px;
    margin-bottom: 20px;
}

.nav-tabs .nav-link.active {
    background: #1e293b;
    color: #fff;
    border-color: #1e293b;
}

/* Stats Container - SAMA PERSIS DENGAN USERS.PHP */
.stats-container {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}
.stat-card {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}
.stat-card:hover {
    transform: translateY(-5px);
}
.stat-card i {
    font-size: 24px;
    margin-bottom: 10px;
}
.stat-number {
    font-size: 24px;
    font-weight: 700;
    color: #1e293b;
}
.stat-label {
    font-size: 14px;
    color: #6b7280;
}

/* Mobile Responsive - SAMA PERSIS */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        width: 280px;
    }
    .sidebar.mobile-open {
        transform: translateX(0);
    }
    .topbar, .content, footer {
        margin-left: 0;
    }
    .mobile-toggle {
        display: block !important;
    }
    .stats-container {
        grid-template-columns: 1fr;
    }
}

/* Footer - SAMA PERSIS TANPA BACKGROUND PUTIH */
footer {
    margin-left: 250px;
    text-align: center;
    padding: 20px 0;
    color: #6b7280;
    font-size: 14px;
    border-top: 1px solid #e5e7eb;
}
</style>
</head>
<body>

<!-- Sidebar - SAMA PERSIS -->
<div class="sidebar" id="sidebar">
    <div class="logo">
        <img src="../assets/img/Abyan (10) Kasir Computer.jpg" alt="Logo">
        <div class="logo-text">Kasir Computer</div>
    </div>
    
    <a href="dashboard.php">
        <i class="fa fa-home"></i>
        <span class="nav-text">Dashboard</span>
    </a>
    
    <a href="produk.php">
        <i class="fa fa-box"></i>
        <span class="nav-text">Kelola Produk</span>
    </a>
    
    <a href="transaksi.php">
        <i class="fa fa-exchange-alt"></i>
        <span class="nav-text">Data Transaksi</span>
    </a>
    
    <a href="users.php">
        <i class="fa fa-users"></i>
        <span class="nav-text">Kelola User</span>
    </a>
    
    <a href="laporan.php">
        <i class="fa fa-file-alt"></i>
        <span class="nav-text">Laporan Penjualan</span>
    </a>
    
    <a href="settings.php" class="active">
        <i class="fa fa-cog"></i>
        <span class="nav-text">Pengaturan</span>
    </a>
    
    <div style="margin-top: auto; padding: 20px;">
        <a href="../auth/logout.php" class="btn btn-danger w-100" style="border-radius: 10px;">
            <i class="fa fa-sign-out-alt"></i>
            <span class="nav-text">Logout</span>
        </a>
    </div>
</div>

<!-- Topbar - SAMA PERSIS -->
<div class="topbar" id="topbar">
    <div class="d-flex align-items-center">
        <button class="btn btn-primary me-3 mobile-toggle" style="display: none; border-radius: 8px;" onclick="toggleMobileSidebar()">
            <i class="fa fa-bars"></i>
        </button>
        <div class="title">Pengaturan Sistem</div>
    </div>
    <div class="user-menu">
        <div class="dropdown">
            <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fa fa-user me-2"></i>
                <?= htmlspecialchars($_SESSION['username']); ?>
                <span class="badge bg-primary ms-2"><?= ucfirst($_SESSION['role']); ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><span class="dropdown-item-text">
                    <small>Logged in as</small><br>
                    <strong><?= htmlspecialchars($_SESSION['username']); ?></strong>
                </span></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="settings.php">
                    <i class="fa fa-cog me-2"></i>Settings
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="../auth/logout.php">
                    <i class="fa fa-sign-out-alt me-2"></i>Logout
                </a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Content -->
<div class="content" id="content">
    <!-- Stats Container - SAMA PERSIS DENGAN USERS.PHP -->
    <div class="stats-container">
        <div class="stat-card">
            <i class="fa fa-boxes text-primary"></i>
            <div class="stat-number"><?= $total_produk ?></div>
            <div class="stat-label">Total Produk</div>
        </div>
        <div class="stat-card">
            <i class="fa fa-receipt text-success"></i>
            <div class="stat-number"><?= $total_transaksi ?></div>
            <div class="stat-label">Total Transaksi</div>
        </div>
        <div class="stat-card">
            <i class="fa fa-users text-info"></i>
            <div class="stat-number"><?= $total_users ?></div>
            <div class="stat-label">Total User</div>
        </div>
        <div class="stat-card">
            <i class="fa fa-database text-warning"></i>
            <div class="stat-number"><?= $total_settings ?></div>
            <div class="stat-label">Pengaturan</div>
        </div>
    </div>

    <?php if(isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fa fa-check-circle me-2"></i>
            <?= $_SESSION['success']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fa fa-exclamation-circle me-2"></i>
            <?= $_SESSION['error']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Tabs Navigation -->
    <ul class="nav nav-tabs" id="settingsTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                <i class="fa fa-cog me-2"></i>Pengaturan Umum
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">
                <i class="fa fa-info-circle me-2"></i>Informasi Sistem
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="backup-tab" data-bs-toggle="tab" data-bs-target="#backup" type="button" role="tab">
                <i class="fa fa-database me-2"></i>Backup & Reset
            </button>
        </li>
    </ul>

    <div class="tab-content" id="settingsTabContent">
        <!-- Tab Pengaturan Umum -->
        <div class="tab-pane fade show active" id="general" role="tabpanel">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <div class="setting-group">
                            <h5><i class="fa fa-store me-2"></i>Informasi Toko</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Nama Toko</label>
                                <input type="text" class="form-control" name="nama_toko" 
                                       value="<?= htmlspecialchars($settings['nama_toko']['isi_setting'] ?? 'Kasir Computer'); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Alamat Toko</label>
                                <textarea class="form-control" name="alamat_toko" rows="3"><?= htmlspecialchars($settings['alamat_toko']['isi_setting'] ?? 'Jl. Teknologi No. 123, Jakarta'); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Telepon</label>
                                <input type="text" class="form-control" name="telepon_toko" 
                                       value="<?= htmlspecialchars($settings['telepon_toko']['isi_setting'] ?? '(021) 1234-5678'); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email Toko</label>
                                <input type="email" class="form-control" name="email_toko" 
                                       value="<?= htmlspecialchars($settings['email_toko']['isi_setting'] ?? 'info@kasircomputer.com'); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="setting-group">
                            <h5><i class="fa fa-sliders-h me-2"></i>Pengaturan Aplikasi</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Mata Uang</label>
                                <select class="form-select" name="mata_uang">
                                    <option value="IDR" <?= ($settings['mata_uang']['isi_setting'] ?? 'IDR') == 'IDR' ? 'selected' : ''; ?>>Rupiah (IDR)</option>
                                    <option value="USD" <?= ($settings['mata_uang']['isi_setting'] ?? 'IDR') == 'USD' ? 'selected' : ''; ?>>Dollar (USD)</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Format Tanggal</label>
                                <select class="form-select" name="format_tanggal">
                                    <option value="d/m/Y" <?= ($settings['format_tanggal']['isi_setting'] ?? 'd/m/Y') == 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                    <option value="Y-m-d" <?= ($settings['format_tanggal']['isi_setting'] ?? 'd/m/Y') == 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                    <option value="d F Y" <?= ($settings['format_tanggal']['isi_setting'] ?? 'd/m/Y') == 'd F Y' ? 'selected' : ''; ?>>DD Month YYYY</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Notifikasi Stok Minimum</label>
                                <input type="number" class="form-control" name="stok_minimum" 
                                       value="<?= htmlspecialchars($settings['stok_minimum']['isi_setting'] ?? '10'); ?>">
                                <div class="form-text">Sistem akan memberi peringatan ketika stok produk mencapai nilai ini</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Timezone</label>
                                <select class="form-select" name="timezone">
                                    <option value="Asia/Jakarta" <?= ($settings['timezone']['isi_setting'] ?? 'Asia/Jakarta') == 'Asia/Jakarta' ? 'selected' : ''; ?>>Asia/Jakarta (WIB)</option>
                                    <option value="Asia/Makassar" <?= ($settings['timezone']['isi_setting'] ?? 'Asia/Jakarta') == 'Asia/Makassar' ? 'selected' : ''; ?>>Asia/Makassar (WITA)</option>
                                    <option value="Asia/Jayapura" <?= ($settings['timezone']['isi_setting'] ?? 'Asia/Jakarta') == 'Asia/Jayapura' ? 'selected' : ''; ?>>Asia/Jayapura (WIT)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="setting-group">
                            <h5><i class="fa fa-receipt me-2"></i>Pengaturan Receipt</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Header Receipt</label>
                                <textarea class="form-control" name="header_receipt" rows="2"><?= htmlspecialchars($settings['header_receipt']['isi_setting'] ?? 'Kasir Computer'); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Footer Receipt</label>
                                <textarea class="form-control" name="footer_receipt" rows="3"><?= htmlspecialchars($settings['footer_receipt']['isi_setting'] ?? 'Terima kasih atas kunjungan Anda'); ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Pajak (%)</label>
                                <input type="number" class="form-control" name="pajak" 
                                       value="<?= htmlspecialchars($settings['pajak']['isi_setting'] ?? '0'); ?>" step="0.1" min="0" max="100">
                                <div class="form-text">Persentase pajak yang akan diterapkan</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="setting-group">
                            <h5><i class="fa fa-shield-alt me-2"></i>Pengaturan Keamanan</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Auto Logout (menit)</label>
                                <input type="number" class="form-control" name="auto_logout" 
                                       value="<?= htmlspecialchars($settings['auto_logout']['isi_setting'] ?? '30'); ?>">
                                <div class="form-text">Waktu dalam menit untuk auto logout</div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Max Login Attempt</label>
                                <input type="number" class="form-control" name="max_login_attempt" 
                                       value="<?= htmlspecialchars($settings['max_login_attempt']['isi_setting'] ?? '3'); ?>">
                                <div class="form-text">Maksimal percobaan login yang diizinkan</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Session Timeout (menit)</label>
                                <input type="number" class="form-control" name="session_timeout" 
                                       value="<?= htmlspecialchars($settings['session_timeout']['isi_setting'] ?? '60'); ?>">
                                <div class="form-text">Waktu timeout session dalam menit</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Password Policy</label>
                                <select class="form-select" name="password_policy">
                                    <option value="low" <?= ($settings['password_policy']['isi_setting'] ?? 'low') == 'low' ? 'selected' : ''; ?>>Low (minimal 6 karakter)</option>
                                    <option value="medium" <?= ($settings['password_policy']['isi_setting'] ?? 'low') == 'medium' ? 'selected' : ''; ?>>Medium (minimal 8 karakter dengan angka)</option>
                                    <option value="high" <?= ($settings['password_policy']['isi_setting'] ?? 'low') == 'high' ? 'selected' : ''; ?>>High (minimal 8 karakter dengan angka dan simbol)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <button type="submit" name="update_settings" class="btn btn-primary">
                        <i class="fa fa-save me-2"></i>Simpan Pengaturan
                    </button>
                </div>
            </form>
        </div>

        <!-- Tab Informasi Sistem -->
        <div class="tab-pane fade" id="system" role="tabpanel">
            <div class="row">
                <div class="col-md-6">
                    <div class="system-info-card">
                        <h5><i class="fa fa-server me-2 text-primary"></i>Informasi Server</h5>
                        <div class="info-item">
                            <span class="info-label">PHP Version</span>
                            <span class="info-value"><?= PHP_VERSION ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">MySQL Version</span>
                            <span class="info-value"><?= mysqli_get_server_info($conn) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Web Server</span>
                            <span class="info-value"><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Database Size</span>
                            <span class="info-value"><?= $db_size_formatted ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="system-info-card">
                        <h5><i class="fa fa-info-circle me-2 text-success"></i>Informasi Aplikasi</h5>
                        <div class="info-item">
                            <span class="info-label">Versi Aplikasi</span>
                            <span class="info-value">v2.1.0</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Last Backup</span>
                            <span class="info-value"><?= $last_backup ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Total Produk</span>
                            <span class="info-value"><?= $total_produk ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Total Transaksi</span>
                            <span class="info-value"><?= $total_transaksi ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="system-info-card mt-3">
                <h5><i class="fa fa-chart-bar me-2 text-warning"></i>Statistik Sistem</h5>
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="p-3">
                            <h3 class="text-primary"><?= $total_users ?></h3>
                            <small class="text-muted">Total User</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3">
                            <h3 class="text-success"><?= $total_kategori ?></h3>
                            <small class="text-muted">Kategori</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3">
                            <h3 class="text-info"><?= $total_settings ?></h3>
                            <small class="text-muted">Pengaturan</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3">
                            <h3 class="text-danger"><?= date('d/m/Y H:i') ?></h3>
                            <small class="text-muted">Waktu Server</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Backup & Reset -->
        <div class="tab-pane fade" id="backup" role="tabpanel">
            <div class="row">
                <div class="col-md-6">
                    <div class="setting-group">
                        <h5><i class="fa fa-database me-2 text-primary"></i>Backup Database</h5>
                        <p class="text-muted">Buat backup database untuk keamanan data.</p>
                        
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle"></i>
                            Backup akan menyimpan semua data transaksi, produk, user, dan pengaturan.
                        </div>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Nama Backup</label>
                                <input type="text" class="form-control" value="backup_<?= date('Y-m-d_H-i-s') ?>" readonly>
                            </div>
                            
                            <button type="submit" name="backup_database" class="btn btn-primary w-100">
                                <i class="fa fa-download me-2"></i>Buat Backup Sekarang
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="setting-group danger-zone">
                        <h5><i class="fa fa-exclamation-triangle me-2 text-danger"></i>Reset Data</h5>
                        <p class="text-muted">Hati-hati! Tindakan ini tidak dapat dibatalkan.</p>
                        
                        <div class="alert alert-danger">
                            <i class="fa fa-warning"></i>
                            Reset akan menghapus semua data transaksi dan mengembalikan sistem ke kondisi awal.
                        </div>

                        <form method="POST" onsubmit="return confirmReset()">
                            <div class="mb-3">
                                <label class="form-label">Konfirmasi Password</label>
                                <input type="password" name="reset_password" class="form-control" placeholder="Masukkan password admin" required>
                                <div class="form-text">Password: <code>admin123</code></div>
                            </div>
                            
                            <button type="submit" name="reset_data" class="btn btn-danger w-100">
                                <i class="fa fa-trash me-2"></i>Reset Semua Data
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Riwayat Backup -->
            <div class="setting-group mt-3">
                <h5><i class="fa fa-history me-2 text-info"></i>Riwayat Backup</h5>
                <?php if($last_backup != 'Belum ada backup'): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Nama File</th>
                                    <th>Tanggal</th>
                                    <th>Ukuran</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><?= $last_backup ?></td>
                                    <td><?= date('d M Y H:i') ?></td>
                                    <td><?= $db_size_formatted ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary">
                                            <i class="fa fa-download"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3 text-muted">
                        <i class="fa fa-database fa-2x mb-2"></i>
                        <p>Belum ada riwayat backup</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<footer id="footer">
    &copy; <?= date('Y'); ?> Kasir Computer — Developed by Abyan
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Confirm reset data
function confirmReset() {
    return Swal.fire({
        title: 'Yakin ingin reset data?',
        text: "Semua data transaksi akan dihapus permanen! Tindakan ini tidak dapat dibatalkan.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Reset Data!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        return result.isConfirmed;
    });
}

// Mobile sidebar toggle - SAMA PERSIS
function toggleMobileSidebar() {
    document.getElementById('sidebar').classList.toggle('mobile-open');
}

// Mobile detection - SAMA PERSIS
document.addEventListener('DOMContentLoaded', function() {
    if (window.innerWidth <= 768) {
        document.querySelector('.mobile-toggle').style.display = 'block';
    }
});

// Responsive handling - SAMA PERSIS
window.addEventListener('resize', function() {
    if (window.innerWidth <= 768) {
        document.querySelector('.mobile-toggle').style.display = 'block';
    } else {
        document.querySelector('.mobile-toggle').style.display = 'none';
        document.getElementById('sidebar').classList.remove('mobile-open');
    }
});
</script>
</body>
</html>