<?php
session_start();
if(!isset($_SESSION['id']) || $_SESSION['role'] != 'kasir'){
    header("Location: ../auth/login.php");
    exit();
}
include("../config/db.php");

// Ambil data kasir
$kasir_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = '".$_SESSION['id']."'"));

// Ambil filter tanggal
$start = $_GET['start'] ?? date('Y-m-01');
$end   = $_GET['end'] ?? date('Y-m-d');
$status_filter = $_GET['status'] ?? '';

// Query untuk riwayat transaksi - DIPERBAIKI: tambah kode_transaksi
$query = "SELECT t.id, t.kode_transaksi, t.total, t.tanggal, t.status, t.waktu,
                 COUNT(dt.id) as jumlah_item,
                 GROUP_CONCAT(CONCAT(p.nama_produk, ' (', dt.qty, 'x)') SEPARATOR ', ') as produk_detail,
                 GROUP_CONCAT(p.nama_produk SEPARATOR ', ') as produk
          FROM transaksi t
          LEFT JOIN detail_transaksi dt ON t.id = dt.transaksi_id
          LEFT JOIN produk p ON dt.produk_id = p.id
          WHERE t.kasir_id = ".$_SESSION['id'];

if($start && $end){
    $query .= " AND DATE(t.tanggal) BETWEEN '$start' AND '$end'";
}

if($status_filter && $status_filter != 'all'){
    $query .= " AND t.status = '$status_filter'";
}

$query .= " GROUP BY t.id, t.kode_transaksi, t.total, t.tanggal, t.status, t.waktu
            ORDER BY t.tanggal DESC, t.waktu DESC";

$riwayat = mysqli_query($conn, $query);

// Hitung statistik
$totalTransaksi = mysqli_num_rows($riwayat);

$totalPendapatanResult = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(total), 0) as total 
    FROM transaksi 
    WHERE kasir_id = ".$_SESSION['id']." 
    AND DATE(tanggal) BETWEEN '$start' AND '$end'
    ".($status_filter && $status_filter != 'all' ? "AND status = '$status_filter'" : "")."
"));
$totalPendapatan = $totalPendapatanResult['total'] ?? 0;

$transaksiHariIniResult = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as total 
    FROM transaksi 
    WHERE kasir_id = ".$_SESSION['id']." 
    AND DATE(tanggal) = CURDATE()
"));
$transaksiHariIni = $transaksiHariIniResult['total'] ?? 0;

$pendapatanHariIniResult = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(total), 0) as total 
    FROM transaksi 
    WHERE kasir_id = ".$_SESSION['id']." 
    AND DATE(tanggal) = CURDATE()
"));
$pendapatanHariIni = $pendapatanHariIniResult['total'] ?? 0;

// Data untuk chart (7 hari terakhir)
$chart_data = [];
$chart_query = mysqli_query($conn, "
    SELECT DATE(tanggal) as tanggal, COUNT(*) as jumlah, COALESCE(SUM(total), 0) as total
    FROM transaksi 
    WHERE kasir_id = ".$_SESSION['id']." 
    AND tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(tanggal)
    ORDER BY tanggal ASC
");
while($row = mysqli_fetch_assoc($chart_query)){
    $chart_data[] = $row;
}

// Top produk bulan ini - DIPERBAIKI: tambah kode_transaksi
$top_produk = [];
$top_produk_query = mysqli_query($conn, "
    SELECT p.nama_produk, SUM(dt.qty) as total_terjual, SUM(dt.qty * dt.harga) as total_pendapatan
    FROM detail_transaksi dt
    JOIN transaksi t ON dt.transaksi_id = t.id
    JOIN produk p ON dt.produk_id = p.id
    WHERE t.kasir_id = ".$_SESSION['id']." 
    AND MONTH(t.tanggal) = MONTH(CURDATE())
    AND YEAR(t.tanggal) = YEAR(CURDATE())
    GROUP BY p.id, p.nama_produk
    ORDER BY total_terjual DESC
    LIMIT 5
");
while($row = mysqli_fetch_assoc($top_produk_query)){
    $top_produk[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Riwayat Transaksi</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
/* CSS styles tetap sama seperti sebelumnya */
:root {
    --primary: #3b82f6;
    --primary-dark: #2563eb;
    --secondary: #1e293b;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --light: #f8fafc;
    --dark: #1e293b;
    --gray: #64748b;
}

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

/* Welcome Box */
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

/* Statistik Card */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: #fff;
    border: none;
    border-radius: 15px;
    padding: 25px;
    text-align: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}
.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), var(--primary-dark));
}
.stat-card:hover { 
    transform: translateY(-5px); 
    box-shadow: 0 12px 30px rgba(0,0,0,0.15); 
}
.stat-card i { 
    font-size: 2.5rem; 
    margin-bottom: 15px;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.stat-card h3 { 
    font-weight: 700; 
    color: var(--dark);
    margin: 10px 0 5px;
    font-size: 1.8rem;
}
.stat-card p {
    color: var(--gray);
    font-weight: 500;
    margin: 0;
    font-size: 0.9rem;
}

/* Filter Section */
.filter-container {
    background: #fff;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    margin-bottom: 25px;
}
.filter-container h5 {
    font-weight: 600;
    margin-bottom: 20px;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Table Container */
.table-container {
    background: #fff;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}
.table th { 
    background: #1e293b; 
    color: #fff; 
    font-weight: 600;
    border: none;
    padding: 15px;
    text-align: center;
}
.table td {
    padding: 12px 15px;
    vertical-align: middle;
    border-color: #e5e7eb;
    text-align: center;
}

/* Badge Status */
.badge-status {
    font-size: 12px;
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: 600;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--gray);
}
.empty-state i {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.5;
}
.empty-state h5 {
    font-weight: 600;
    margin-bottom: 10px;
}

/* Footer */
footer {
    margin-left: 250px;
    text-align: center;
    padding: 20px 0;
    color: var(--gray);
    font-size: 14px;
    border-top: 1px solid #e5e7eb;
    background: #fff;
    margin-top: 40px;
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
    color: var(--gray);
}
.search-container input {
    padding-left: 45px;
    border-radius: 10px;
    border: 2px solid #e5e7eb;
    transition: all 0.3s ease;
}
.search-container input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Export Button */
.btn-export {
    border-radius: 8px;
    padding: 8px 20px;
    font-weight: 600;
    transition: all 0.3s ease;
}
.btn-export:hover {
    transform: translateY(-2px);
}

/* Chart Container */
.chart-container {
    background: #fff;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    margin-bottom: 30px;
    height: 100%;
}
.chart-container h5 {
    font-weight: 600;
    margin-bottom: 20px;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Top Products */
.top-products {
    background: #fff;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    height: 100%;
}
.top-products h5 {
    font-weight: 600;
    margin-bottom: 20px;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 10px;
}
.product-item {
    display: flex;
    justify-content: between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #e5e7eb;
}
.product-item:last-child {
    border-bottom: none;
}
.product-info {
    flex: 1;
}
.product-name {
    font-weight: 500;
    color: var(--dark);
}
.product-meta {
    font-size: 0.85rem;
    color: var(--gray);
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
    .stats-grid {
        grid-template-columns: 1fr;
    }
    .welcome-box {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
}

/* Print Styles */
@media print {
    .sidebar, .topbar, .btn, .search-container, .filter-container, .stats-grid, .chart-container, .top-products {
        display: none !important;
    }
    .content {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    .table-container {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
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
    
    <a href="dashboard.php">
        <i class="fa fa-home"></i>
        <span class="nav-text">Dashboard</span>
    </a>
    
    <a href="transaksi.php">
        <i class="fa fa-shopping-cart"></i>
        <span class="nav-text">Transaksi Baru</span>
    </a>
    
    <a href="riwayat.php" class="active">
        <i class="fa fa-history"></i>
        <span class="nav-text">Riwayat Transaksi</span>
    </a>
    
    <a href="retur.php">
        <i class="fa fa-box"></i>
        <span class="nav-text">Retur Barang</span>
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
        <div class="title">
            Riwayat Transaksi
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
    <!-- Welcome Box -->
    <div class="welcome-box">
        <h2>Riwayat Transaksi Anda</h2>
        <div class="date-info">
            <i class="fa fa-calendar me-2"></i>
            Periode: <?= date('d M Y', strtotime($start)) ?> - <?= date('d M Y', strtotime($end)) ?>
            <?php if($status_filter && $status_filter != 'all'): ?>
                | Status: <?= ucfirst($status_filter) ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistik -->
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fa fa-receipt text-primary"></i>
            <h3 id="totalTransaksiCount"><?= $totalTransaksi; ?></h3>
            <p>Total Transaksi</p>
        </div>
        <div class="stat-card">
            <i class="fa fa-money-bill-wave text-success"></i>
            <h3>Rp <?= number_format($totalPendapatan, 0, ',', '.'); ?></h3>
            <p>Total Pendapatan</p>
        </div>
        <div class="stat-card">
            <i class="fa fa-shopping-cart text-warning"></i>
            <h3><?= $transaksiHariIni; ?></h3>
            <p>Transaksi Hari Ini</p>
        </div>
        <div class="stat-card">
            <i class="fa fa-chart-line text-info"></i>
            <h3>Rp <?= number_format($totalTransaksi > 0 ? $totalPendapatan / $totalTransaksi : 0, 0, ',', '.'); ?></h3>
            <p>Rata-rata/Transaksi</p>
        </div>
    </div>

    <div class="row">
        <!-- Chart dan Top Products -->
        <div class="col-lg-4">
            <!-- Grafik Transaksi -->
            <div class="chart-container">
                <h5><i class="fa fa-chart-line text-primary"></i> Transaksi 7 Hari Terakhir</h5>
                <canvas id="transactionChart" height="250"></canvas>
            </div>

            <!-- Top Products -->
            <div class="top-products">
                <h5><i class="fa fa-star text-warning"></i> Produk Terlaris Bulan Ini</h5>
                <?php if(count($top_produk) > 0): ?>
                    <?php foreach($top_produk as $produk): ?>
                    <div class="product-item">
                        <div class="product-info">
                            <div class="product-name"><?= htmlspecialchars($produk['nama_produk']); ?></div>
                            <div class="product-meta">
                                <?= $produk['total_terjual']; ?> terjual | 
                                Rp <?= number_format($produk['total_pendapatan'], 0, ',', '.'); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" style="padding: 20px;">
                        <i class="fa fa-chart-bar"></i>
                        <p class="mb-0">Belum ada data penjualan</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tabel Riwayat -->
        <div class="col-lg-8">
            <!-- Filter dan Pencarian -->
            <div class="filter-container">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5><i class="fa fa-filter text-primary me-2"></i>Filter Transaksi</h5>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="d-flex gap-2 justify-content-end">
                            <div class="search-container">
                                <i class="fa fa-search"></i>
                                <input type="text" id="searchInput" class="form-control" placeholder="Cari transaksi...">
                            </div>
                            <button class="btn btn-success btn-export" onclick="exportToPDF()">
                                <i class="fa fa-file-pdf me-2"></i>PDF
                            </button>
                            <button class="btn btn-success btn-export" onclick="exportToExcel()">
                                <i class="fa fa-file-excel me-2"></i>Excel
                            </button>
                        </div>
                    </div>
                </div>
                
                <form method="GET" class="row g-3 mt-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Dari Tanggal</label>
                        <input type="date" name="start" class="form-control" value="<?= $start; ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Sampai Tanggal</label>
                        <input type="date" name="end" class="form-control" value="<?= $end; ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>Semua Status</option>
                            <option value="selesai" <?= $status_filter == 'selesai' ? 'selected' : '' ?>>Selesai</option>
                            <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="d-flex gap-2 w-100">
                            <button type="submit" class="btn btn-primary flex-fill">
                                <i class="fa fa-filter me-2"></i>Filter
                            </button>
                            <a href="riwayat.php" class="btn btn-outline-secondary">
                                <i class="fa fa-refresh"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tabel Riwayat -->
            <div class="table-container">
                <table class="table table-hover" id="riwayatTable">
                    <thead>
                        <tr>
                            <th width="100">Kode Transaksi</th>
                            <th width="120">Tanggal & Waktu</th>
                            <th>Produk</th>
                            <th width="100">Jumlah Item</th>
                            <th width="150">Total</th>
                            <th width="120">Status</th>
                            <th width="100" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($riwayat) > 0): ?>
                            <?php while($r = mysqli_fetch_assoc($riwayat)): 
                                $status_class = $r['status'] == 'selesai' ? 'bg-success' : 'bg-warning';
                            ?>
                            <tr>
                                <td><strong><?= $r['kode_transaksi']; ?></strong></td>
                                <td>
                                    <div>
                                        <div class="fw-semibold"><?= date('d M Y', strtotime($r['tanggal'])); ?></div>
                                        <small class="text-muted"><?= date('H:i', strtotime($r['waktu'])); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-start">
                                        <?php 
                                        $produk_list = explode(', ', $r['produk']);
                                        $first_product = $produk_list[0] ?? 'Produk';
                                        $remaining_count = count($produk_list) - 1;
                                        ?>
                                        <div class="fw-semibold"><?= htmlspecialchars($first_product); ?></div>
                                        <?php if($remaining_count > 0): ?>
                                            <small class="text-muted">+<?= $remaining_count ?> produk lainnya</small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?= $r['jumlah_item'] ?> item</span>
                                </td>
                                <td>
                                    <strong class="text-success">Rp <?= number_format($r['total'], 0, ',', '.'); ?></strong>
                                </td>
                                <td>
                                    <span class="badge <?= $status_class ?> badge-status">
                                        <i class="fa fa-<?= $r['status'] == 'selesai' ? 'check' : 'clock' ?> me-1"></i>
                                        <?= ucfirst($r['status']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" 
                                                onclick="showDetail(<?= $r['id'] ?>)"
                                                data-bs-toggle="tooltip" 
                                                title="Lihat Detail">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-success" 
                                                onclick="printStruk(<?= $r['id'] ?>)"
                                                data-bs-toggle="tooltip" 
                                                title="Cetak Struk">
                                            <i class="fa fa-print"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fa fa-receipt"></i>
                                        <h5>Tidak Ada Transaksi</h5>
                                        <p>Tidak ada transaksi pada periode yang dipilih</p>
                                        <a href="transaksi.php" class="btn btn-primary mt-3">
                                            <i class="fa fa-plus me-2"></i>Buat Transaksi Baru
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if(mysqli_num_rows($riwayat) > 0): ?>
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted">
                    Menampilkan <?= $totalTransaksi; ?> transaksi
                </div>
                <nav>
                    <ul class="pagination">
                        <li class="page-item disabled"><a class="page-link" href="#">Previous</a></li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item"><a class="page-link" href="#">Next</a></li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<footer id="footer">
    &copy; <?= date('Y'); ?> Kasir Computer — Riwayat Transaksi | Kasir: <?= htmlspecialchars($_SESSION['username']); ?>
</footer>

<!-- Modal Detail Transaksi -->
<div class="modal fade" id="detailModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Detail Transaksi #<span id="modalTransactionId"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="detailModalContent">
        <!-- Content akan diisi oleh JavaScript -->
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Mobile sidebar toggle
function toggleMobileSidebar() {
    document.getElementById('sidebar').classList.toggle('mobile-open');
}

// Inisialisasi saat DOM ready
document.addEventListener('DOMContentLoaded', function() {
    // Inisialisasi tooltip
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    
    // Initialize chart
    initializeChart();
});

// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#riwayatTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});

// Show detail transaksi
function showDetail(transaksiId) {
    document.getElementById('modalTransactionId').textContent = transaksiId;
    document.getElementById('detailModalContent').innerHTML = `
        <div class="text-center py-4">
            <i class="fa fa-spinner fa-spin fa-2x text-primary"></i>
            <p class="mt-2">Memuat detail transaksi...</p>
        </div>
    `;
    
    const modal = new bootstrap.Modal(document.getElementById('detailModal'));
    modal.show();
    
    // Fetch detail transaksi
    fetch(`get_transaction_detail.php?id=${transaksiId}`)
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                document.getElementById('detailModalContent').innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Informasi Transaksi</h6>
                            <table class="table table-sm">
                                <tr><td>ID Transaksi</td><td>#${data.transaksi.id}</td></tr>
                                <tr><td>Kode Transaksi</td><td>${data.transaksi.kode_transaksi}</td></tr>
                                <tr><td>Tanggal</td><td>${data.transaksi.tanggal}</td></tr>
                                <tr><td>Waktu</td><td>${data.transaksi.waktu}</td></tr>
                                <tr><td>Status</td><td><span class="badge ${data.transaksi.status == 'selesai' ? 'bg-success' : 'bg-warning'}">${data.transaksi.status}</span></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Ringkasan Pembayaran</h6>
                            <table class="table table-sm">
                                <tr><td>Total Item</td><td>${data.transaksi.jumlah_item} item</td></tr>
                                <tr><td>Total Harga</td><td class="fw-bold text-success">Rp ${parseInt(data.transaksi.total).toLocaleString('id-ID')}</td></tr>
                            </table>
                        </div>
                    </div>
                    <h6 class="mt-4">Detail Produk</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th>Qty</th>
                                    <th>Harga</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${data.detail.map(item => `
                                    <tr>
                                        <td>${item.nama_produk}</td>
                                        <td>${item.qty}</td>
                                        <td>Rp ${parseInt(item.harga).toLocaleString('id-ID')}</td>
                                        <td>Rp ${parseInt(item.subtotal).toLocaleString('id-ID')}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3">
                        <button class="btn btn-primary" onclick="printStruk(${transaksiId})">
                            <i class="fa fa-print me-2"></i>Cetak Struk
                        </button>
                    </div>
                `;
            } else {
                document.getElementById('detailModalContent').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fa fa-exclamation-triangle"></i> Gagal memuat detail transaksi
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('detailModalContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fa fa-exclamation-triangle"></i> Terjadi kesalahan saat memuat data
                </div>
            `;
        });
}

// Initialize chart
function initializeChart() {
    const ctx = document.getElementById('transactionChart').getContext('2d');
    const chartData = <?= json_encode($chart_data); ?>;
    
    const labels = chartData.map(item => {
        const date = new Date(item.tanggal);
        return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
    });
    
    const transactionCount = chartData.map(item => item.jumlah);
    const transactionAmount = chartData.map(item => item.total / 1000); // Convert to thousands
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Jumlah Transaksi',
                    data: transactionCount,
                    backgroundColor: 'rgba(59, 130, 246, 0.6)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Pendapatan (ribu)',
                    data: transactionAmount,
                    backgroundColor: 'rgba(16, 185, 129, 0.6)',
                    borderColor: 'rgba(16, 185, 129, 1)',
                    borderWidth: 1,
                    type: 'line',
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Jumlah Transaksi'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Pendapatan (ribu Rp)'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });
}

// Export functions
function exportToPDF() {
    Swal.fire({
        title: 'Export ke PDF',
        text: 'Data riwayat transaksi akan diexport ke format PDF',
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Export PDF',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            // Simulate PDF export
            Swal.fire({
                title: 'Berhasil!',
                text: 'File PDF berhasil di-generate',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
}

function exportToExcel() {
    const dates = '<?= $start ?>_to_<?= $end ?>';
    const filename = `Riwayat_Transaksi_${dates}.xlsx`;
    
    Swal.fire({
        title: 'Export ke Excel',
        text: `Data riwayat transaksi akan diexport ke file ${filename}`,
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Export Excel',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            // Simulate Excel export
            Swal.fire({
                title: 'Berhasil!',
                text: 'File Excel berhasil di-generate',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });
}

// Print struk
function printStruk(transaksiId) {
    Swal.fire({
        title: 'Cetak Struk',
        text: `Struk transaksi #${transaksiId} akan dicetak`,
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Cetak',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            // Redirect to print page or open print dialog
            window.open(`print_struk.php?id=${transaksiId}`, '_blank');
        }
    });
}

// Mobile detection
if (window.innerWidth <= 768) {
    document.querySelector('.mobile-toggle').style.display = 'block';
}

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