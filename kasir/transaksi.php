<?php
session_start();
if(!isset($_SESSION['id']) || $_SESSION['role'] != 'kasir'){
    header("Location: ../auth/login.php");
    exit();
}
include("../config/db.php");

// Ambil semua produk dengan stok tersedia
$produk = mysqli_query($conn, "
    SELECT p.*, k.nama_kategori 
    FROM produk p 
    LEFT JOIN kategori k ON p.kategori_id = k.id 
    WHERE p.stok > 0 
    ORDER BY p.nama_produk ASC
");

// Ambil data kasir
$kasir_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = '".$_SESSION['id']."'"));

// Proses transaksi
if(isset($_POST['bayar'])){
    $keranjang = json_decode($_POST['keranjang'], true);
    $bayar = (int)$_POST['bayar_uang'];
    $total = (int)$_POST['total_harga'];
    $diskon = (int)$_POST['diskon'] ?? 0;
    $catatan = mysqli_real_escape_string($conn, $_POST['catatan'] ?? '');

    if(empty($keranjang)){
        $error = "Keranjang masih kosong!";
    } elseif($bayar < $total){
        $error = "Uang bayar tidak cukup!";
    } else {
        // Mulai transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Generate kode transaksi
            $kode_transaksi = 'TRX' . date('YmdHis') . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
            
            // Insert transaksi utama
            mysqli_query($conn, "INSERT INTO transaksi (kode_transaksi, kasir_id, total, tanggal, status, waktu) 
                                 VALUES ('$kode_transaksi', '".$_SESSION['id']."', '$total', CURDATE(), 'selesai', NOW())");
            $transaksi_id = mysqli_insert_id($conn);
            
            foreach($keranjang as $item){
                $id_produk = $item['id'];
                $jumlah = $item['jumlah'];

                // Ambil stok sekarang
                $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT stok, harga FROM produk WHERE id=$id_produk"));
                $stok = $row['stok'];
                $harga = $row['harga'];
                $sub_total = $harga * $jumlah;

                if($jumlah > $stok){
                    throw new Exception("Stok tidak cukup untuk {$item['nama']}");
                }

                // Insert detail transaksi
                mysqli_query($conn, "INSERT INTO detail_transaksi (transaksi_id, produk_id, qty, harga, subtotal)
                                     VALUES ('$transaksi_id', '$id_produk', '$jumlah', '$harga', '$sub_total')");
                
                // Update stok
                mysqli_query($conn, "UPDATE produk SET stok = stok - $jumlah WHERE id = $id_produk");
            }

            mysqli_commit($conn);
            $success = "Transaksi berhasil!";
            $last_transaction_id = $transaksi_id;
            $last_kode_transaksi = $kode_transaksi;
            $kembalian = $bayar - $total;
            
            // Reset keranjang setelah sukses
            echo "<script>localStorage.removeItem('keranjang');</script>";
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}

// Ambil riwayat transaksi hari ini
$transaksi_hari_ini = mysqli_query($conn, "
    SELECT t.*, COUNT(dt.id) as jumlah_item 
    FROM transaksi t 
    LEFT JOIN detail_transaksi dt ON t.id = dt.transaksi_id 
    WHERE DATE(t.tanggal) = CURDATE() AND t.kasir_id = '".$_SESSION['id']."'
    GROUP BY t.id, t.kode_transaksi, t.total, t.tanggal, t.status, t.waktu
    ORDER BY t.tanggal DESC, t.waktu DESC 
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Transaksi Kasir</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
/* SAMA PERSIS DENGAN DASHBOARD KASIR */
body {
    font-family: 'Poppins', sans-serif;
    background: #f0f2f5;
    overflow-x: hidden;
}

/* Sidebar Styles - Fixed tanpa collapse */
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

/* Content Styles - SAMA PERSIS */
.content {
    margin-left: 250px;
    padding: 30px;
    min-height: 100vh;
}

/* Stats Container - SAMA PERSIS */
.stats-container {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
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

/* Card Styling - SAMA PERSIS */
.card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    margin-bottom: 25px;
    background: #fff;
    padding: 20px;
}

.graph-card, .summary-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    height: 100%;
}
.graph-card h4, .summary-card h4 {
    font-weight: 600;
    margin-bottom: 20px;
    color: #1e293b;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 10px;
}

/* Table Styling - SAMA PERSIS */
.table {
    margin-bottom: 0;
}

.table th {
    background-color: #1e293b;
    color: #fff;
    border-bottom: 2px solid #e5e7eb;
    font-weight: 600;
    padding: 15px;
}

.table td {
    padding: 12px 15px;
    vertical-align: middle;
    border-color: #e5e7eb;
}

/* Badge Styling - SAMA PERSIS */
.badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

/* Button Styling - SAMA PERSIS */
.btn-sm {
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 13px;
}

/* Form Controls - SAMA PERSIS */
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

/* Welcome Box */
.welcome-box {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: white;
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3);
}
.welcome-box h2 { 
    font-weight: 700; 
    margin: 0; 
    font-size: 1.8rem;
}
.welcome-box .date-info {
    background: rgba(255, 255, 255, 0.2);
    padding: 12px 20px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 1rem;
}

/* Mini Card */
.mini-card {
    background: #fff;
    border-radius: 12px;
    padding: 18px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 15px;
    border-left: 4px solid #3b82f6;
}
.mini-card.warning { border-left-color: #f59e0b; }
.mini-card.danger { border-left-color: #ef4444; }
.mini-card.success { border-left-color: #10b981; }
.mini-card h6 { 
    margin: 0 0 8px 0; 
    font-weight: 600;
    font-size: 1rem;
}
.mini-card p { 
    margin: 0; 
    color: #6b7280; 
    font-size: 14px; 
}

/* Quick Stats */
.quick-stats {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-top: 20px;
}
.quick-stat {
    background: #f8fafc;
    padding: 15px;
    border-radius: 10px;
    text-align: center;
}
.quick-stat .value {
    font-weight: 700;
    font-size: 20px;
    color: #1e293b;
}
.quick-stat .label {
    font-size: 13px;
    color: #6b7280;
}

/* Cart Item Styles */
.cart-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #e5e7eb;
}
.cart-item:last-child {
    border-bottom: none;
}
.cart-item-info {
    flex: 1;
}
.cart-item-name {
    font-weight: 500;
    color: #1e293b;
}
.cart-item-meta {
    font-size: 0.85rem;
    color: #6b7280;
}
.cart-item-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}
.quantity-control {
    display: flex;
    align-items: center;
    gap: 8px;
}
.quantity-btn {
    width: 30px;
    height: 30px;
    border: 1px solid #d1d5db;
    background: white;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}
.quantity-btn:hover {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
}
.quantity-input {
    width: 50px;
    text-align: center;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 4px;
}

/* Payment Section */
.payment-section {
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    color: white;
    border-radius: 15px;
    padding: 25px;
    margin-top: 20px;
}
.payment-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 20px;
}
.payment-item {
    text-align: center;
    padding: 15px;
    background: rgba(255,255,255,0.1);
    border-radius: 10px;
    backdrop-filter: blur(10px);
}
.payment-label {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-bottom: 5px;
}
.payment-value {
    font-size: 1.2rem;
    font-weight: 700;
}

/* Quick Actions */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 20px;
}
.quick-action-btn {
    background: #f8fafc;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    padding: 10px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}
.quick-action-btn:hover {
    border-color: #3b82f6;
    background: white;
    transform: translateY(-2px);
}

/* Search Box */
.search-container {
    position: relative;
    margin-bottom: 20px;
}
.search-container i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #6b7280;
}
.search-container input {
    padding-left: 45px;
    border-radius: 10px;
    border: 2px solid #e5e7eb;
    transition: all 0.3s ease;
}
.search-container input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Empty States */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #6b7280;
}
.empty-state i {
    font-size: 3rem;
    margin-bottom: 15px;
    opacity: 0.5;
}

/* Cart Summary */
.cart-summary {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius: 12px;
    padding: 20px;
    border-left: 4px solid #3b82f6;
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
    .welcome-box {
        flex-direction: column;
        text-align: center;
        gap: 15px;
    }
    .payment-info, .quick-actions {
        grid-template-columns: 1fr;
    }
}

/* Footer - SAMA PERSIS */
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

<!-- Sidebar - MENU KASIR -->
<div class="sidebar" id="sidebar">
    <div class="logo">
        <img src="../assets/img/Abyan (10) Kasir Computer.jpg" alt="Logo">
        <div class="logo-text">Kasir Computer</div>
    </div>
    
    <a href="dashboard.php">
        <i class="fa fa-home"></i>
        <span class="nav-text">Dashboard</span>
    </a>
    
    <a href="transaksi.php" class="active">
        <i class="fa fa-shopping-cart"></i>
        <span class="nav-text">Transaksi Baru</span>
    </a>
    
    <a href="riwayat.php">
        <i class="fa fa-history"></i>
        <span class="nav-text">Riwayat Transaksi</span>
    </a>
    
    <a href="laporan.php">
        <i class="fa fa-file-alt"></i>
        <span class="nav-text">Laporan Penjualan</span>
    </a>
    
    <!-- LOGOUT BUTTON - SAMA PERSIS -->
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
        <div class="title">
            Transaksi Kasir
        </div>
    </div>
    <div class="user-menu">
        <div class="dropdown">
            <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fa fa-user me-2"></i>
                <?= htmlspecialchars($_SESSION['username']); ?>
                <span class="badge bg-success ms-2">Kasir</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><span class="dropdown-item-text">
                    <small>Logged in as</small><br>
                    <strong><?= htmlspecialchars($_SESSION['username']); ?></strong>
                </span></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="riwayat.php">
                    <i class="fa fa-history me-2"></i>Riwayat Saya
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
    <?php if(isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa fa-exclamation-circle me-2"></i>
            <?= $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif(isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa fa-check-circle me-2"></i>
            <?= $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <?php if(isset($last_transaction_id)): ?>
                <div class="mt-2">
                    <strong>Kode Transaksi: <?= $last_kode_transaksi; ?></strong> | 
                    Total: Rp <?= number_format($total, 0, ',', '.'); ?> | 
                    Kembalian: Rp <?= number_format($kembalian, 0, ',', '.'); ?>
                    <button class="btn btn-sm btn-outline-primary ms-2" onclick="printStruk(<?= $last_transaction_id; ?>)">
                        <i class="fa fa-print me-1"></i>Cetak Struk
                    </button>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Quick Stats -->
    <div class="stats-container">
        <div class="stat-card">
            <i class="fa fa-box text-primary"></i>
            <div class="stat-number"><?= mysqli_num_rows($produk); ?></div>
            <div class="stat-label">Produk Tersedia</div>
        </div>
        <div class="stat-card">
            <i class="fa fa-shopping-cart text-success"></i>
            <div class="stat-number">
                <?php 
                $total_hari_ini = mysqli_fetch_assoc(mysqli_query($conn, 
                    "SELECT COUNT(*) as total FROM transaksi 
                     WHERE DATE(tanggal) = CURDATE() AND kasir_id = '".$_SESSION['id']."'"
                ));
                echo $total_hari_ini['total'];
                ?>
            </div>
            <div class="stat-label">Transaksi Hari Ini</div>
        </div>
        <div class="stat-card">
            <i class="fa fa-money-bill-wave text-warning"></i>
            <div class="stat-number">
                <?php
                $pendapatan_hari_ini = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT COALESCE(SUM(total), 0) as total FROM transaksi 
                     WHERE DATE(tanggal) = CURDATE() AND kasir_id = '".$_SESSION['id']."'"
                ));
                echo 'Rp ' . number_format($pendapatan_hari_ini['total'], 0, ',', '.');
                ?>
            </div>
            <div class="stat-label">Pendapatan Hari Ini</div>
        </div>
    </div>

    <div class="row">
        <!-- Produk Section -->
        <div class="col-lg-8">
            <div class="card">
                <h4><i class="fa fa-boxes text-primary me-2"></i>Daftar Produk Tersedia</h4>
                
                <div class="search-container">
                    <i class="fa fa-search"></i>
                    <input type="text" id="searchProduk" class="form-control" placeholder="Cari produk...">
                </div>

                <div class="table-responsive">
                    <table class="table table-hover" id="tabelProduk">
                        <thead>
                            <tr>
                                <th width="100">Aksi</th>
                                <th>Nama Produk</th>
                                <th width="120">Harga</th>
                                <th width="100">Stok</th>
                                <th width="120">Kategori</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($produk) > 0): ?>
                                <?php while($p = mysqli_fetch_assoc($produk)): 
                                    $stock_class = $p['stok'] <= 5 ? 'bg-warning' : 'bg-success';
                                ?>
                                <tr>
                                    <td>
                                        <button class="btn btn-success btn-sm tambahKeranjang" 
                                                data-id="<?= $p['id']; ?>" 
                                                data-nama="<?= htmlspecialchars($p['nama_produk']); ?>" 
                                                data-harga="<?= $p['harga']; ?>" 
                                                data-stok="<?= $p['stok']; ?>"
                                                data-kategori="<?= htmlspecialchars($p['nama_kategori']); ?>">
                                            <i class="fa fa-plus me-1"></i>Tambah
                                        </button>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($p['nama_produk']); ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($p['kode']); ?></small>
                                    </td>
                                    <td class="fw-bold text-success">Rp <?= number_format($p['harga'],0,',','.'); ?></td>
                                    <td>
                                        <span class="badge <?= $stock_class ?>"><?= $p['stok']; ?> unit</span>
                                        <?php if($p['stok'] <= 5): ?>
                                            <br><small class="text-warning"><i class="fa fa-exclamation-triangle me-1"></i>Menipis</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($p['nama_kategori']); ?></span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="fa fa-box-open"></i>
                                            <h6>Tidak ada produk tersedia</h6>
                                            <p>Semua produk sedang habis</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Riwayat Transaksi Hari Ini -->
            <div class="card">
                <h4><i class="fa fa-history text-info me-2"></i>Riwayat Transaksi Hari Ini</h4>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Kode Transaksi</th>
                                <th>Waktu</th>
                                <th>Total</th>
                                <th>Item</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($transaksi_hari_ini) > 0): ?>
                                <?php while($transaksi = mysqli_fetch_assoc($transaksi_hari_ini)): ?>
                                <tr>
                                    <td><strong><?= $transaksi['kode_transaksi']; ?></strong></td>
                                    <td><?= date('H:i', strtotime($transaksi['waktu'])); ?></td>
                                    <td class="text-success">Rp <?= number_format($transaksi['total'], 0, ',', '.'); ?></td>
                                    <td><span class="badge bg-primary"><?= $transaksi['jumlah_item']; ?> item</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="printStruk(<?= $transaksi['id']; ?>)">
                                            <i class="fa fa-print"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="fa fa-history"></i>
                                            <h6>Belum ada transaksi hari ini</h6>
                                            <p>Mulai transaksi pertama Anda</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Keranjang Section -->
        <div class="col-lg-4">
            <form method="POST" id="formTransaksi">
                <input type="hidden" name="keranjang" id="keranjangInput">
                <input type="hidden" name="total_harga" id="totalHargaInput">
                <input type="hidden" name="diskon" id="diskonInput" value="0">
                
                <div class="card">
                    <h4><i class="fa fa-shopping-cart text-success me-2"></i>Keranjang Belanja</h4>

                    <!-- Quick Actions -->
                    <div class="quick-actions mb-3">
                        <div class="quick-action-btn" onclick="clearCart()">
                            <i class="fa fa-trash text-danger me-1"></i>
                            <span class="small">Kosongkan</span>
                        </div>
                        <div class="quick-action-btn" onclick="applyDiscount(10)">
                            <i class="fa fa-tag text-warning me-1"></i>
                            <span class="small">Diskon 10%</span>
                        </div>
                        <div class="quick-action-btn" onclick="removeDiscount()">
                            <i class="fa fa-times text-danger me-1"></i>
                            <span class="small">Hapus Diskon</span>
                        </div>
                    </div>

                    <div id="keranjangContainer">
                        <div class="empty-state">
                            <i class="fa fa-shopping-basket"></i>
                            <h6>Keranjang Kosong</h6>
                            <p>Tambahkan produk dari daftar di samping</p>
                        </div>
                    </div>

                    <!-- Cart Summary -->
                    <div class="cart-summary mt-3">
                        <div class="row text-center mb-3">
                            <div class="col-6">
                                <div class="payment-item">
                                    <div class="payment-label">Total Item</div>
                                    <div class="payment-value text-primary" id="totalItem">0</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="payment-item">
                                    <div class="payment-label">Total Harga</div>
                                    <div class="payment-value text-success" id="totalHargaDisplay">Rp 0</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="payment-item">
                                    <div class="payment-label">Diskon</div>
                                    <div class="payment-value text-warning" id="diskonDisplay">Rp 0</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="payment-item">
                                    <div class="payment-label">Total Bayar</div>
                                    <div class="payment-value text-danger" id="totalBayar">Rp 0</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Section -->
                    <div class="payment-section">
                        <div class="payment-info">
                            <div class="payment-item">
                                <div class="payment-label">Uang Bayar</div>
                                <div class="payment-value" id="uangBayarDisplay">Rp 0</div>
                            </div>
                            <div class="payment-item">
                                <div class="payment-label">Kembalian</div>
                                <div class="payment-value" id="kembalianDisplay">Rp 0</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-white">Uang Bayar</label>
                            <input type="number" name="bayar_uang" class="form-control" placeholder="Masukkan jumlah uang" min="0" required id="uangBayarInput">
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-white">Catatan (Opsional)</label>
                            <textarea name="catatan" class="form-control" placeholder="Catatan transaksi..." rows="2"></textarea>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="bayar" class="btn btn-light btn-lg">
                                <i class="fa fa-credit-card me-2"></i>Proses Pembayaran
                            </button>
                            <button type="button" id="batalTransaksi" class="btn btn-outline-light">
                                <i class="fa fa-times me-2"></i>Batalkan Transaksi
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<footer>
    &copy; <?= date('Y'); ?> Kasir Computer — Developed by Abyan
</footer>

<!-- Struk Printing Template (Hidden) -->
<div id="strukTemplate" style="display: none;"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Keranjang belanja
let keranjang = JSON.parse(localStorage.getItem('keranjang') || '[]');
let diskonPersen = 0;

// Format angka ke Rupiah
function formatRupiah(angka) {
    return 'Rp ' + angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

// Update tampilan keranjang
function updateKeranjang() {
    const container = document.getElementById('keranjangContainer');
    const totalItemEl = document.getElementById('totalItem');
    const totalHargaEl = document.getElementById('totalHargaDisplay');
    const diskonEl = document.getElementById('diskonDisplay');
    const totalBayarEl = document.getElementById('totalBayar');
    const keranjangInput = document.getElementById('keranjangInput');
    const totalHargaInput = document.getElementById('totalHargaInput');
    const diskonInput = document.getElementById('diskonInput');
    
    if (keranjang.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fa fa-shopping-basket"></i>
                <h6>Keranjang Kosong</h6>
                <p>Tambahkan produk dari daftar di samping</p>
            </div>
        `;
        totalItemEl.textContent = '0';
        totalHargaEl.textContent = 'Rp 0';
        diskonEl.textContent = 'Rp 0';
        totalBayarEl.textContent = 'Rp 0';
        keranjangInput.value = '';
        totalHargaInput.value = '0';
        diskonInput.value = '0';
        return;
    }
    
    let html = '';
    let totalItem = 0;
    let totalHarga = 0;
    
    keranjang.forEach((item, index) => {
        totalItem += item.jumlah;
        const subtotal = item.harga * item.jumlah;
        totalHarga += subtotal;
        
        html += `
            <div class="cart-item">
                <div class="cart-item-info">
                    <div class="cart-item-name">${item.nama}</div>
                    <div class="cart-item-meta">
                        ${item.kategori} | ${formatRupiah(item.harga)}
                    </div>
                </div>
                <div class="cart-item-actions">
                    <div class="quantity-control">
                        <button class="quantity-btn" onclick="updateQty(${index}, ${item.jumlah - 1})">
                            <i class="fa fa-minus"></i>
                        </button>
                        <input type="text" class="quantity-input" value="${item.jumlah}" readonly>
                        <button class="quantity-btn" onclick="updateQty(${index}, ${item.jumlah + 1})" 
                                ${item.jumlah >= item.stok ? 'disabled' : ''}>
                            <i class="fa fa-plus"></i>
                        </button>
                    </div>
                    <button class="btn btn-sm btn-danger" onclick="removeFromCart(${index})">
                        <i class="fa fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    // Hitung diskon
    const diskon = totalHarga * (diskonPersen / 100);
    const totalBayar = totalHarga - diskon;
    
    // Update tampilan
    totalItemEl.textContent = totalItem;
    totalHargaEl.textContent = formatRupiah(totalHarga);
    diskonEl.textContent = formatRupiah(diskon);
    totalBayarEl.textContent = formatRupiah(totalBayar);
    
    // Update input hidden
    keranjangInput.value = JSON.stringify(keranjang);
    totalHargaInput.value = totalBayar;
    diskonInput.value = diskon;
    
    // Update uang bayar dan kembalian
    updatePayment();
}

// Tambah ke keranjang
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.tambahKeranjang').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const nama = this.getAttribute('data-nama');
            const harga = parseInt(this.getAttribute('data-harga'));
            const stok = parseInt(this.getAttribute('data-stok'));
            const kategori = this.getAttribute('data-kategori');
            
            // Cek apakah sudah ada di keranjang
            const existingItem = keranjang.find(item => item.id === id);
            
            if (existingItem) {
                if (existingItem.jumlah < stok) {
                    existingItem.jumlah++;
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Stok Tidak Cukup',
                        text: 'Stok produk tidak mencukupi!'
                    });
                    return;
                }
            } else {
                if (stok > 0) {
                    keranjang.push({
                        id: id,
                        nama: nama,
                        harga: harga,
                        stok: stok,
                        kategori: kategori,
                        jumlah: 1
                    });
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Stok Habis',
                        text: 'Produk ini sudah habis!'
                    });
                    return;
                }
            }
            
            localStorage.setItem('keranjang', JSON.stringify(keranjang));
            updateKeranjang();
            
            // Animasi feedback
            this.classList.add('btn-success');
            setTimeout(() => {
                this.classList.remove('btn-success');
            }, 300);
        });
    });
    
    // Inisialisasi keranjang
    updateKeranjang();
    
    // Search produk
    document.getElementById('searchProduk').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('#tabelProduk tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });
    
    // Update uang bayar real-time
    document.getElementById('uangBayarInput').addEventListener('input', updatePayment);
    
    // Batalkan transaksi
    document.getElementById('batalTransaksi').addEventListener('click', function() {
        Swal.fire({
            title: 'Batalkan Transaksi?',
            text: "Semua item di keranjang akan dihapus!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Batalkan!',
            cancelButtonText: 'Kembali'
        }).then((result) => {
            if (result.isConfirmed) {
                clearCart();
                Swal.fire('Dibatalkan!', 'Transaksi telah dibatalkan.', 'success');
            }
        });
    });
});

// Update quantity
function updateQty(index, newQty) {
    if (newQty < 1) {
        removeFromCart(index);
        return;
    }
    
    if (newQty > keranjang[index].stok) {
        Swal.fire({
            icon: 'warning',
            title: 'Stok Tidak Cukup',
            text: 'Stok produk tidak mencukupi!'
        });
        return;
    }
    
    keranjang[index].jumlah = newQty;
    localStorage.setItem('keranjang', JSON.stringify(keranjang));
    updateKeranjang();
}

// Hapus dari keranjang
function removeFromCart(index) {
    keranjang.splice(index, 1);
    localStorage.setItem('keranjang', JSON.stringify(keranjang));
    updateKeranjang();
}

// Kosongkan keranjang
function clearCart() {
    keranjang = [];
    localStorage.removeItem('keranjang');
    updateKeranjang();
}

// Terapkan diskon
function applyDiscount(persen) {
    diskonPersen = persen;
    updateKeranjang();
    Swal.fire({
        icon: 'success',
        title: 'Diskon Diterapkan',
        text: `Diskon ${persen}% berhasil diterapkan!`
    });
}

// Hapus diskon
function removeDiscount() {
    diskonPersen = 0;
    updateKeranjang();
    Swal.fire({
        icon: 'info',
        title: 'Diskon Dihapus',
        text: 'Diskon telah dihapus dari transaksi!'
    });
}

// Update pembayaran
function updatePayment() {
    const uangBayarInput = document.getElementById('uangBayarInput');
    const uangBayarDisplay = document.getElementById('uangBayarDisplay');
    const kembalianDisplay = document.getElementById('kembalianDisplay');
    const totalBayarEl = document.getElementById('totalBayar');
    
    const totalBayar = parseInt(totalBayarEl.textContent.replace(/[^0-9]/g, '') || 0);
    const uangBayar = parseInt(uangBayarInput.value) || 0;
    const kembalian = uangBayar - totalBayar;
    
    uangBayarDisplay.textContent = formatRupiah(uangBayar);
    kembalianDisplay.textContent = formatRupiah(kembalian < 0 ? 0 : kembalian);
    
    // Warna kembalian
    if (kembalian < 0) {
        kembalianDisplay.style.color = '#ef4444';
    } else {
        kembalianDisplay.style.color = '#10b981';
    }
}

// Print struk
function printStruk(transaksiId) {
    fetch(`get_struk.php?id=${transaksiId}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                Swal.fire('Error', 'Gagal mengambil data struk', 'error');
                return;
            }
            
            const strukTemplate = document.getElementById('strukTemplate');
            const struk = data.struk;
            
            strukTemplate.innerHTML = `
                <style>
                    @media print {
                        body * {
                            visibility: hidden;
                        }
                        #strukTemplate, #strukTemplate * {
                            visibility: visible;
                        }
                        #strukTemplate {
                            position: absolute;
                            left: 0;
                            top: 0;
                            width: 80mm;
                            font-family: 'Courier New', monospace;
                            font-size: 12px;
                            padding: 10px;
                        }
                        .struk-header, .struk-footer {
                            text-align: center;
                            margin: 5px 0;
                        }
                        .struk-item {
                            display: flex;
                            justify-content: space-between;
                            border-bottom: 1px dashed #000;
                            padding: 3px 0;
                        }
                        .struk-total {
                            font-weight: bold;
                            margin-top: 10px;
                            border-top: 2px solid #000;
                            padding-top: 5px;
                        }
                    }
                </style>
                <div class="struk">
                    <div class="struk-header">
                        <h4 style="margin: 0;">Kasir Computer</h4>
                        <p style="margin: 2px 0;">Jl. Contoh No. 123</p>
                        <p style="margin: 2px 0;">Telp: (021) 123-4567</p>
                        <hr style="border-top: 2px solid #000; margin: 5px 0;">
                    </div>
                    
                    <div class="struk-info">
                        <p style="margin: 2px 0;">No: ${struk.kode_transaksi}</p>
                        <p style="margin: 2px 0;">Kasir: ${struk.kasir}</p>
                        <p style="margin: 2px 0;">Tanggal: ${struk.tanggal} ${struk.waktu}</p>
                        <hr style="border-top: 1px dashed #000; margin: 5px 0;">
                    </div>
                    
                    <div class="struk-items">
                        ${struk.items.map(item => `
                            <div class="struk-item">
                                <div>${item.nama_produk}</div>
                                <div>${item.qty} x ${formatRupiah(item.harga)}</div>
                            </div>
                            <div class="struk-item">
                                <div></div>
                                <div>${formatRupiah(item.subtotal)}</div>
                            </div>
                        `).join('')}
                    </div>
                    
                    <div class="struk-total">
                        <div class="struk-item">
                            <div>Total:</div>
                            <div>${formatRupiah(struk.total)}</div>
                        </div>
                        <div class="struk-item">
                            <div>Bayar:</div>
                            <div>${formatRupiah(struk.uang_bayar)}</div>
                        </div>
                        <div class="struk-item">
                            <div>Kembali:</div>
                            <div>${formatRupiah(struk.kembalian)}</div>
                        </div>
                    </div>
                    
                    <div class="struk-footer">
                        <hr style="border-top: 2px solid #000; margin: 5px 0;">
                        <p style="margin: 5px 0;">Terima kasih atas kunjungan Anda</p>
                        <p style="margin: 2px 0; font-size: 10px;">www.kasircomputer.com</p>
                    </div>
                </div>
            `;
            
            // Print struk
            window.print();
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'Terjadi kesalahan saat mencetak struk', 'error');
        });
}

// Mobile sidebar toggle
function toggleMobileSidebar() {
    document.getElementById('sidebar').classList.toggle('mobile-open');
}

// Mobile detection
document.addEventListener('DOMContentLoaded', function() {
    if (window.innerWidth <= 768) {
        document.querySelector('.mobile-toggle').style.display = 'block';
    }
});

// Responsive handling
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