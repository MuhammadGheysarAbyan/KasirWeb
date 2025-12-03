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

// Data statistik utama
$totalProduk = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as jml FROM produk"))['jml'] ?? 0;
$totalTransaksi = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as jml FROM transaksi"))['jml'] ?? 0;
$totalUser = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as jml FROM users"))['jml'] ?? 0;

// Data pendapatan - FIX: sesuaikan dengan struktur database
$pendapatanBulanResult = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(total), 0) as total FROM transaksi
    WHERE MONTH(tanggal)=MONTH(NOW()) AND YEAR(tanggal)=YEAR(NOW())
"));
$pendapatanBulan = $pendapatanBulanResult['total'] ?? 0;

$pendapatanHariResult = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(total), 0) as total FROM transaksi
    WHERE DATE(tanggal)=CURDATE()
"));
$pendapatanHari = $pendapatanHariResult['total'] ?? 0;

$pendapatanKemarinResult = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(total), 0) as total FROM transaksi
    WHERE DATE(tanggal)=DATE_SUB(CURDATE(), INTERVAL 1 DAY)
"));
$pendapatanKemarin = $pendapatanKemarinResult['total'] ?? 0;

// Perbandingan dengan kemarin
$perubahanHari = 0;
if ($pendapatanKemarin > 0) {
    $perubahanHari = (($pendapatanHari - $pendapatanKemarin) / $pendapatanKemarin) * 100;
}

// Data penjualan per bulan (12 bulan terakhir) - FIX: sesuaikan field
$bulan = [];
$total = [];
$query = mysqli_query($conn, "
    SELECT DATE_FORMAT(tanggal, '%b') as bulan, COALESCE(SUM(total), 0) as total
    FROM transaksi
    WHERE tanggal >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY YEAR(tanggal), MONTH(tanggal)
    ORDER BY YEAR(tanggal), MONTH(tanggal)
");
while($row = mysqli_fetch_assoc($query)){
    $bulan[] = $row['bulan'];
    $total[] = $row['total'];
}

// Produk terlaris - FIX: sesuaikan dengan struktur detail_transaksi
$produkTerlaris = [];
$queryProduk = mysqli_query($conn, "
    SELECT p.nama_produk, p.stok, COALESCE(SUM(dt.qty), 0) as total_terjual, 
           COALESCE(SUM(dt.qty * dt.harga), 0) as total_pendapatan
    FROM produk p
    LEFT JOIN detail_transaksi dt ON p.id = dt.produk_id
    GROUP BY p.id, p.nama_produk, p.stok
    ORDER BY total_terjual DESC
    LIMIT 6
");
while($row = mysqli_fetch_assoc($queryProduk)){
    $produkTerlaris[] = $row;
}

// Transaksi terbaru - UPDATE: ambil kode_transaksi dan format waktu yang benar
$transaksiTerbaru = [];
$queryTransaksi = mysqli_query($conn, "
    SELECT t.kode_transaksi, t.tanggal, t.waktu, COALESCE(t.total, 0) as total, 
           u.username, COUNT(dt.id) as qty_item
    FROM transaksi t
    JOIN users u ON t.kasir_id = u.id
    LEFT JOIN detail_transaksi dt ON t.id = dt.transaksi_id
    GROUP BY t.id, t.kode_transaksi, t.tanggal, t.waktu, t.total, u.username
    ORDER BY t.tanggal DESC, t.waktu DESC
    LIMIT 6
");
while($row = mysqli_fetch_assoc($queryTransaksi)){
    $transaksiTerbaru[] = $row;
}

// Kategori produk - FIX: sesuaikan dengan struktur
$kategoriProduk = [];
$queryKategori = mysqli_query($conn, "
    SELECT k.nama_kategori, COUNT(p.id) as qty 
    FROM kategori k
    LEFT JOIN produk p ON k.id = p.kategori_id
    GROUP BY k.id, k.nama_kategori
    ORDER BY qty DESC
    LIMIT 6
");
while($row = mysqli_fetch_assoc($queryKategori)){
    $kategoriProduk[] = $row;
}

// Performa kasir - FIX: sesuaikan field
$performaKasir = [];
$queryKasir = mysqli_query($conn, "
    SELECT u.username, 
           COUNT(t.id) as total_transaksi, 
           COALESCE(SUM(t.total), 0) as total_penjualan
    FROM users u
    LEFT JOIN transaksi t ON u.id = t.kasir_id
    WHERE u.role = 'kasir'
    GROUP BY u.id, u.username
    ORDER BY total_penjualan DESC
    LIMIT 5
");
while($row = mysqli_fetch_assoc($queryKasir)){
    $performaKasir[] = $row;
}

// Data untuk chart kategori
$labelsKategori = [];
$dataKategori = [];
$colorsKategori = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];

foreach($kategoriProduk as $index => $kategori) {
    $labelsKategori[] = $kategori['nama_kategori'];
    $dataKategori[] = $kategori['qty'];
}

// Stok produk menipis
$stokMenipis = [];
$queryStok = mysqli_query($conn, "
    SELECT nama_produk, stok, harga
    FROM produk
    WHERE stok <= 10 AND stok > 0
    ORDER BY stok ASC
    LIMIT 5
");
while($row = mysqli_fetch_assoc($queryStok)){
    $stokMenipis[] = $row;
}

// Stok habis
$stokHabis = [];
$queryHabis = mysqli_query($conn, "
    SELECT nama_produk, stok, harga
    FROM produk
    WHERE stok = 0
    ORDER BY nama_produk
    LIMIT 5
");
while($row = mysqli_fetch_assoc($queryHabis)){
    $stokHabis[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body {
    font-family: 'Poppins', sans-serif;
    background: #f0f2f5;
    overflow-x: hidden;
}

/* Sidebar Styles */
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

/* Topbar Styles */
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

/* Content Styles */
.content {
    margin-left: 250px;
    padding: 30px;
    min-height: 100vh;
}

/* Dashboard Specific Styles */
.welcome-box {
    background: #fff;
    color: #111;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.welcome-box h2 { 
    font-weight: 700; 
    margin: 0; 
    font-size: 1.8rem;
}
.welcome-box .date-info {
    background: #f8fafc;
    padding: 12px 20px;
    border-radius: 10px;
    font-weight: 600;
    color: #475569;
    font-size: 1rem;
}

.row .card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    padding: 25px;
    text-align: center;
    background: #fff;
    transition: transform 0.3s, box-shadow 0.3s;
    height: 100%;
}
.row .card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}
.row .card i {
    font-size: 2.5rem;
    margin-bottom: 15px;
}
.row .card h3 {
    font-weight: 700;
    color: #111827;
    margin: 15px 0;
    font-size: 2rem;
}
.row .card h5 {
    font-weight: 600;
    color: #374151;
    margin-bottom: 10px;
}
.row .card .card-subtitle {
    font-size: 14px;
    color: #6b7280;
}
.row .card .trend {
    font-size: 13px;
    font-weight: 600;
    margin-top: 8px;
}
.row .card .trend.up { color: #10b981; }
.row .card .trend.down { color: #ef4444; }

.graph-card, .summary-card {
    background: #fff;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    height: 100%;
}
.graph-card h4, .summary-card h4 {
    font-weight: 600;
    margin-bottom: 25px;
    color: #1e293b;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 1.3rem;
}
.graph-card h4 .badge, .summary-card h4 .badge {
    font-size: 12px;
    padding: 6px 12px;
}

.summary-table {
    width: 100%;
    border-collapse: collapse;
}
.summary-table th, .summary-table td {
    padding: 15px;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}
.summary-table th {
    background-color: #f8fafc;
    font-weight: 600;
    color: #374151;
}
.summary-table tr:last-child td {
    border-bottom: none;
}
.summary-table .badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
}

.mini-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    margin-bottom: 15px;
    border-left: 4px solid #3b82f6;
}
.mini-card.warning { border-left-color: #f59e0b; }
.mini-card.danger { border-left-color: #ef4444; }
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

.progress {
    height: 8px;
    margin-top: 8px;
}

footer {
    margin-left: 250px;
    text-align: center;
    padding: 20px 0;
    color: #6b7280;
    font-size: 14px;
    border-top: 1px solid #e5e7eb;
}

.chart-container {
    position: relative;
    height: 320px;
    width: 100%;
}

.quick-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
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

/* Mobile Responsive */
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
    .welcome-box {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
}
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="logo">
        <img src="../assets/img/Abyan (10) Kasir Computer.jpg" alt="Logo">
        <div class="logo-text">Kasir Computer</div>
    </div>
    
    <a href="dashboard.php" class="active">
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
    
    <a href="settings.php">
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

<!-- Topbar -->
<div class="topbar" id="topbar">
    <div class="d-flex align-items-center">
        <button class="btn btn-primary me-3 mobile-toggle" style="display: none; border-radius: 8px;" onclick="toggleMobileSidebar()">
            <i class="fa fa-bars"></i>
        </button>
        <div class="title">Dashboard Admin</div>
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
    <div class="welcome-box">
        <h2>Selamat Datang, <?= htmlspecialchars($_SESSION['username']); ?> ! 🎉</h2>
        <div class="date-info">
            <i class="fa fa-calendar me-2"></i><?= date('d F Y'); ?>
        </div>
    </div>

    <!-- Kartu Statistik Utama -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card">
                <i class="fa fa-box text-primary"></i>
                <h5>Total Produk</h5>
                <h3 class="counter" data-target="<?= $totalProduk; ?>">0</h3>
                <div class="card-subtitle">Tersedia di sistem</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <i class="fa fa-receipt text-success"></i>
                <h5>Total Transaksi</h5>
                <h3 class="counter" data-target="<?= $totalTransaksi; ?>">0</h3>
                <div class="card-subtitle">Sejak awal sistem</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <i class="fa fa-users text-info"></i>
                <h5>Total User</h5>
                <h3 class="counter" data-target="<?= $totalUser; ?>">0</h3>
                <div class="card-subtitle">Admin & Kasir</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <i class="fa fa-money-bill-wave text-warning"></i>
                <h5>Pendapatan Hari Ini</h5>
                <h3>Rp <?= number_format($pendapatanHari, 0, ',', '.'); ?></h3>
                <div class="card-subtitle">
                    <?php if($perubahanHari > 0): ?>
                        <span class="trend up"><i class="fa fa-arrow-up"></i> +<?= number_format($perubahanHari, 1); ?>% dari kemarin</span>
                    <?php elseif($perubahanHari < 0): ?>
                        <span class="trend down"><i class="fa fa-arrow-down"></i> <?= number_format($perubahanHari, 1); ?>% dari kemarin</span>
                    <?php else: ?>
                        <span class="trend">Sama dengan kemarin</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Baris kedua: Grafik dan Ringkasan -->
    <div class="row g-4">
        <!-- Grafik Penjualan -->
        <div class="col-md-8">
            <div class="graph-card">
                <h4>
                    <span><i class="fa fa-chart-line me-2 text-primary"></i>Grafik Penjualan (12 Bulan Terakhir)</span>
                    <span class="badge bg-primary"><?= date('Y'); ?></span>
                </h4>
                <div class="chart-container">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Ringkasan Cepat -->
        <div class="col-md-4">
            <div class="summary-card">
                <h4><i class="fa fa-tachometer-alt me-2 text-success"></i>Ringkasan Cepat</h4>
                <div class="quick-stats">
                    <div class="quick-stat">
                        <div class="value">Rp <?= number_format($pendapatanBulan, 0, ',', '.'); ?></div>
                        <div class="label">Pendapatan Bulan Ini</div>
                    </div>
                    <div class="quick-stat">
                        <div class="value"><?= count($stokMenipis); ?></div>
                        <div class="label">Stok Menipis</div>
                    </div>
                    <div class="quick-stat">
                        <div class="value"><?= count($stokHabis); ?></div>
                        <div class="label">Stok Habis</div>
                    </div>
                    <div class="quick-stat">
                        <div class="value"><?= count($performaKasir); ?></div>
                        <div class="label">Kasir Aktif</div>
                    </div>
                </div>

                <!-- Alert Stok -->
                <?php if(count($stokMenipis) > 0): ?>
                    <div class="mini-card warning mt-3">
                        <h6><i class="fa fa-exclamation-triangle me-2"></i>Stok Menipis</h6>
                        <p><?= count($stokMenipis); ?> produk perlu restok segera</p>
                        <div class="mt-2">
                            <?php foreach($stokMenipis as $produk): ?>
                                <small class="text-muted d-block">• <?= htmlspecialchars($produk['nama_produk']); ?> (<?= $produk['stok']; ?> stok)</small>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if(count($stokHabis) > 0): ?>
                    <div class="mini-card danger mt-2">
                        <h6><i class="fa fa-times-circle me-2"></i>Stok Habis</h6>
                        <p><?= count($stokHabis); ?> produk sudah habis</p>
                        <div class="mt-2">
                            <?php foreach($stokHabis as $produk): ?>
                                <small class="text-muted d-block">• <?= htmlspecialchars($produk['nama_produk']); ?></small>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Baris ketiga: Produk dan Kategori -->
    <div class="row g-4 mt-4">
        <!-- Produk Terlaris -->
        <div class="col-md-6">
            <div class="summary-card">
                <h4>
                    <span><i class="fa fa-star me-2 text-warning"></i>Produk Terlaris</span>
                    <span class="badge bg-warning">Top 6</span>
                </h4>
                <?php if(count($produkTerlaris) > 0): ?>
                    <table class="summary-table">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Terjual</th>
                                <th>Pendapatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($produkTerlaris as $produk): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($produk['nama_produk']); ?></strong>
                                            <div class="text-muted" style="font-size: 12px;">Stok: <?= $produk['stok']; ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span><?= $produk['total_terjual']; ?></span>
                                            <div class="progress" style="width: 60px;">
                                                <div class="progress-bar bg-success" style="width: <?= min(100, ($produk['total_terjual'] / max(1, $produkTerlaris[0]['total_terjual'])) * 100); ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>Rp <?= number_format($produk['total_pendapatan'], 0, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center text-muted">Belum ada data penjualan produk</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Distribusi Kategori -->
        <div class="col-md-6">
            <div class="summary-card">
                <h4>
                    <span><i class="fa fa-chart-pie me-2 text-info"></i>Distribusi Kategori</span>
                    <span class="badge bg-info"><?= count($kategoriProduk); ?> Kategori</span>
                </h4>
                <div class="chart-container">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Baris keempat: Transaksi dan Performa -->
    <div class="row g-4 mt-4">
 <!-- Transaksi Terbaru -->
<div class="col-md-6">
    <div class="summary-card">
        <h4>
            <span><i class="fa fa-clock me-2 text-primary"></i>Transaksi Terbaru</span>
            <span class="badge bg-primary">6 Terbaru</span>
        </h4>
        <?php if(count($transaksiTerbaru) > 0): ?>
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>Kode Transaksi</th>
                        <th>Waktu</th>
                        <th>Kasir</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($transaksiTerbaru as $transaksi): ?>
                        <tr>
                            <td>
                                <div class="badge bg-light text-dark border">
                                    <?= htmlspecialchars($transaksi['kode_transaksi']); ?>
                                </div>
                            </td>
                            <td>
                                <div><?= date('d/m/Y', strtotime($transaksi['tanggal'])); ?></div>
                                <small class="text-muted">
                                    <?php 
                                    // Menampilkan waktu dari field waktu
                                    if(!empty($transaksi['waktu'])) {
                                        echo date('H:i', strtotime($transaksi['waktu']));
                                    } else {
                                        // Fallback: jika waktu kosong, gunakan tanggal saja
                                        echo date('H:i', strtotime($transaksi['tanggal']));
                                    }
                                    ?>
                                </small>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="fa fa-user-circle me-2 text-muted"></i>
                                    <?= htmlspecialchars($transaksi['username']); ?>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <strong class="text-success">Rp <?= number_format($transaksi['total'], 0, ',', '.'); ?></strong>
                                    <div class="text-muted" style="font-size: 12px;">
                                        <i class="fa fa-box me-1"></i><?= $transaksi['qty_item']; ?> item
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="fa fa-receipt fa-3x text-muted mb-3"></i>
                <p class="text-muted">Belum ada transaksi</p>
            </div>
        <?php endif; ?>
    </div>
</div>

        <!-- Performa Kasir -->
        <div class="col-md-6">
            <div class="summary-card">
                <h4>
                    <span><i class="fa fa-trophy me-2 text-success"></i>Performa Kasir</span>
                    <span class="badge bg-success">Top 5</span>
                </h4>
                <?php if(count($performaKasir) > 0): ?>
                    <table class="summary-table">
                        <thead>
                            <tr>
                                <th>Kasir</th>
                                <th>Transaksi</th>
                                <th>Total Penjualan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($performaKasir as $index => $kasir): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 14px;">
                                                <?= $index + 1; ?>
                                            </div>
                                            <?= htmlspecialchars($kasir['username']); ?>
                                        </div>
                                    </td>
                                    <td><?= $kasir['total_transaksi']; ?></td>
                                    <td>Rp <?= number_format($kasir['total_penjualan'], 0, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center text-muted">Tidak ada data kasir</p>
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
// Mobile sidebar toggle
function toggleMobileSidebar() {
    document.getElementById('sidebar').classList.toggle('mobile-open');
}

// Counter animasi
document.addEventListener('DOMContentLoaded', function() {
    const counters = document.querySelectorAll('.counter');
    counters.forEach(counter => {
        const target = +counter.getAttribute('data-target');
        const duration = 2000;
        const step = target / (duration / 16);
        let current = 0;
        
        const updateCounter = () => {
            current += step;
            if (current < target) {
                counter.innerText = Math.ceil(current);
                requestAnimationFrame(updateCounter);
            } else {
                counter.innerText = target;
            }
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    updateCounter();
                    observer.unobserve(entry.target);
                }
            });
        });
        
        observer.observe(counter);
    });

    // Chart.js - Grafik Penjualan
    const salesCtx = document.getElementById('salesChart');
    if (salesCtx) {
        new Chart(salesCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?= json_encode($bulan); ?>,
                datasets: [{
                    label: 'Total Penjualan (Rp)',
                    data: <?= json_encode($total); ?>,
                    fill: true,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59,130,246,0.1)',
                    tension: 0.4,
                    pointRadius: 4,
                    pointBackgroundColor: '#2563eb',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { 
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });
    }

    // Chart.js - Grafik Kategori
    const categoryCtx = document.getElementById('categoryChart');
    if (categoryCtx) {
        new Chart(categoryCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($labelsKategori); ?>,
                datasets: [{
                    data: <?= json_encode($dataKategori); ?>,
                    backgroundColor: <?= json_encode($colorsKategori); ?>,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                }
            }
        });
    }

    // Mobile detection
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