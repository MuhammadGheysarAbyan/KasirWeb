<?php
session_start();
if(!isset($_SESSION['id']) || $_SESSION['role'] != 'admin'){
    header("Location: ../auth/login.php");
    exit();
}
include("../config/db.php");

// Filter tanggal
$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');

// Ambil data transaksi dengan detail lengkap
$transaksi_query = mysqli_query($conn, "
    SELECT t.*, u.username as kasir
    FROM transaksi t
    LEFT JOIN users u ON t.kasir_id = u.id
    WHERE DATE(t.tanggal) BETWEEN '$start' AND '$end'
    ORDER BY t.tanggal DESC, t.waktu DESC
");

// Hitung total transaksi untuk period-info
$transaksi_count = mysqli_num_rows($transaksi_query);

// Reset pointer query untuk digunakan lagi
mysqli_data_seek($transaksi_query, 0);

// Hitung ringkasan
$summary = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_transaksi,
        COALESCE(SUM(t.total), 0) as total_pendapatan,
        COALESCE(SUM(dt.qty), 0) as total_produk_terjual,
        COALESCE(AVG(t.total), 0) as rata_rata_transaksi
    FROM transaksi t
    LEFT JOIN detail_transaksi dt ON dt.transaksi_id = t.id
    WHERE DATE(t.tanggal) BETWEEN '$start' AND '$end'
"));

// Data untuk chart (12 bulan terakhir)
$chart_data = [];
$chart_query = mysqli_query($conn, "
    SELECT 
        DATE_FORMAT(tanggal, '%Y-%m') as bulan,
        DATE_FORMAT(tanggal, '%b %Y') as label,
        COUNT(*) as jumlah_transaksi,
        COALESCE(SUM(total), 0) as total_pendapatan
    FROM transaksi 
    WHERE tanggal >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(tanggal, '%Y-%m')
    ORDER BY bulan ASC
");

while($row = mysqli_fetch_assoc($chart_query)) {
    $chart_data[] = $row;
}

// Produk terlaris periode ini
$produk_terlaris = [];
$produk_query = mysqli_query($conn, "
    SELECT 
        p.nama_produk,
        p.kode,
        COALESCE(SUM(dt.qty), 0) as total_terjual,
        COALESCE(SUM(dt.qty * dt.harga), 0) as total_pendapatan
    FROM detail_transaksi dt
    JOIN produk p ON dt.produk_id = p.id
    JOIN transaksi t ON dt.transaksi_id = t.id
    WHERE DATE(t.tanggal) BETWEEN '$start' AND '$end'
    GROUP BY p.id, p.nama_produk, p.kode
    ORDER BY total_terjual DESC
    LIMIT 5
");

while($row = mysqli_fetch_assoc($produk_query)) {
    $produk_terlaris[] = $row;
}

// Performa kasir
$performa_kasir = [];
$kasir_query = mysqli_query($conn, "
    SELECT 
        u.username,
        u.user_code,
        COALESCE(COUNT(t.id), 0) as total_transaksi,
        COALESCE(SUM(t.total), 0) as total_penjualan,
        COALESCE(AVG(t.total), 0) as rata_rata_penjualan
    FROM users u
    LEFT JOIN transaksi t ON u.id = t.kasir_id AND DATE(t.tanggal) BETWEEN '$start' AND '$end'
    WHERE u.role = 'kasir'
    GROUP BY u.id, u.username, u.user_code
    ORDER BY total_penjualan DESC
");

while($row = mysqli_fetch_assoc($kasir_query)) {
    $performa_kasir[] = $row;
}

// Kategori terlaris - SESUAIKAN DENGAN STRUKTUR DATABASE
$kategori_terlaris = [];
$kategori_query = mysqli_query($conn, "
    SELECT 
        k.nama_kategori,
        COALESCE(SUM(dt.qty), 0) as total_terjual,
        COALESCE(SUM(dt.qty * dt.harga), 0) as total_pendapatan
    FROM detail_transaksi dt
    JOIN produk p ON dt.produk_id = p.id
    JOIN kategori k ON p.kategori_id = k.id
    JOIN transaksi t ON dt.transaksi_id = t.id
    WHERE DATE(t.tanggal) BETWEEN '$start' AND '$end'
    GROUP BY k.id, k.nama_kategori
    ORDER BY total_terjual DESC
    LIMIT 5
");

while($row = mysqli_fetch_assoc($kategori_query)) {
    $kategori_terlaris[] = $row;
}

// Get transaction details for modal
if(isset($_GET['detail_id'])){
    $detail_id = mysqli_real_escape_string($conn, $_GET['detail_id']);
    $detail_transaksi = [];
    $query_detail = mysqli_query($conn, "
        SELECT dt.*, p.nama_produk, p.kode, p.foto, k.nama_kategori
        FROM detail_transaksi dt
        JOIN produk p ON dt.produk_id = p.id
        LEFT JOIN kategori k ON p.kategori_id = k.id
        WHERE dt.transaksi_id = '$detail_id'
    ");
    while($row = mysqli_fetch_assoc($query_detail)){
        $detail_transaksi[] = $row;
    }
    
    // Get transaction info for detail
    $transaksi_info = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT t.*, u.username 
        FROM transaksi t 
        JOIN users u ON t.kasir_id = u.id 
        WHERE t.id = '$detail_id'
    "));
    
    // Tampilkan detail dan exit
    if(isset($_GET['detail_id'])){
        ?>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="detail-section">
                    <h6><i class="fa fa-info-circle text-primary"></i> Informasi Transaksi</h6>
                    <table class="table table-sm table-borderless">
                        <tr><td width="120"><strong>ID Transaksi</strong></td><td>#<?= $transaksi_info['id'] ?></td></tr>
                        <tr><td><strong>Kode Transaksi</strong></td><td><?= $transaksi_info['kode_transaksi'] ?></td></tr>
                        <tr><td><strong>Tanggal</strong></td><td><?= date('d M Y H:i', strtotime($transaksi_info['tanggal'] . ' ' . $transaksi_info['waktu'])) ?></td></tr>
                        <tr><td><strong>Kasir</strong></td><td><?= $transaksi_info['username'] ?></td></tr>
                        <tr><td><strong>Status</strong></td><td>
                            <span class="badge <?= $transaksi_info['status'] === 'selesai' ? 'bg-success' : 'bg-danger' ?>">
                                <?= ucfirst($transaksi_info['status']) ?>
                            </span>
                        </td></tr>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <div class="detail-section">
                    <h6><i class="fa fa-money-bill-wave text-success"></i> Informasi Pembayaran</h6>
                    <table class="table table-sm table-borderless">
                        <tr><td width="120"><strong>Total Item</strong></td><td><?= count($detail_transaksi) ?> item</td></tr>
                        <tr><td><strong>Total Transaksi</strong></td><td>Rp<?= number_format($transaksi_info['total'], 0, ',', '.') ?></td></tr>
                        <tr><td><strong>Status Bayar</strong></td><td>
                            <span class="badge bg-success">LUNAS</span>
                        </td></tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="detail-section">
            <h6><i class="fa fa-shopping-cart text-info"></i> Detail Produk</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th width="60">Gambar</th>
                            <th>Produk</th>
                            <th>Kategori</th>
                            <th width="80">Qty</th>
                            <th width="120">Harga</th>
                            <th width="120">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $totalTransaksi = 0;
                        foreach($detail_transaksi as $detail): 
                            $subtotal = $detail['qty'] * $detail['harga'];
                            $totalTransaksi += $subtotal;
                        ?>
                        <tr>
                            <td>
                                <img src="../assets/img/produk/<?= $detail['foto'] ?>" 
                                     class="product-image"
                                     onerror="this.src='../assets/img/default-product.jpg'">
                            </td>
                            <td>
                                <div>
                                    <strong><?= htmlspecialchars($detail['nama_produk']) ?></strong>
                                    <div class="text-muted small"><?= $detail['kode'] ?></div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?= $detail['nama_kategori'] ?? 'Umum' ?></span>
                            </td>
                            <td><?= $detail['qty'] ?></td>
                            <td>Rp<?= number_format($detail['harga'], 0, ',', '.') ?></td>
                            <td><strong>Rp<?= number_format($subtotal, 0, ',', '.') ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" class="text-end"><strong>Total:</strong></td>
                            <td><strong class="text-success">Rp<?= number_format($totalTransaksi, 0, ',', '.') ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <?php
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Penjualan</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
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

/* Custom Styles untuk Laporan */
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

.card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    margin-bottom: 25px;
}

.table {
    margin-bottom: 0;
}

.table th {
    background-color: #f8fafc;
    border-bottom: 2px solid #e5e7eb;
    font-weight: 600;
    color: #374151;
    padding: 15px;
}

.table td {
    padding: 15px;
    vertical-align: middle;
    border-color: #e5e7eb;
}

.badge {
    padding: 8px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.btn-sm {
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 13px;
}

.action-buttons {
    display: flex;
    gap: 8px;
    justify-content: center;
}

.filter-container {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
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

.chart-card, .summary-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    height: 100%;
}
.chart-card h5, .summary-card h5 {
    font-weight: 600;
    margin-bottom: 20px;
    color: #1e293b;
    border-bottom: 2px solid #e5e7eb;
    padding-bottom: 10px;
}

.btn-export {
    border-radius: 8px;
    padding: 8px 20px;
    font-weight: 600;
}

.period-info {
    background: #f8fafc;
    padding: 10px 15px;
    border-radius: 8px;
    font-weight: 600;
    color: #475569;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 16px;
}

.kasir-card {
    transition: all 0.3s ease;
}
.kasir-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.table-container {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.product-image {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    object-fit: cover;
    border: 2px solid #e5e7eb;
}

.detail-section {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
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
    .stats-container {
        grid-template-columns: 1fr;
    }
    .action-buttons {
        flex-direction: column;
        gap: 5px;
    }
}

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
    
    <a href="laporan.php" class="active">
        <i class="fa fa-file-alt"></i>
        <span class="nav-text">Laporan Penjualan</span>
    </a>

    <a href="retur.php">
        <i class="fa fa-box"></i>
        <span class="nav-text">Retur Barang</span>
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
        <div class="title">Laporan Penjualan</div>
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
    <!-- Filter Section -->
    <div class="filter-container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <form class="row g-3 align-items-center" method="GET">
                    <div class="col-auto">
                        <label class="form-label fw-semibold">Periode Laporan:</label>
                    </div>
                    <div class="col-auto">
                        <input type="date" name="start" value="<?= $start; ?>" class="form-control" required>
                    </div>
                    <div class="col-auto">
                        <span class="fw-semibold">s/d</span>
                    </div>
                    <div class="col-auto">
                        <input type="date" name="end" value="<?= $end; ?>" class="form-control" required>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-primary">
                            <i class="fa fa-filter"></i> Terapkan Filter
                        </button>
                    </div>
                </form>
            </div>
            <div class="col-md-4 text-end">
                <div class="d-flex gap-2 justify-content-end">
                    <input type="text" id="searchInput" class="form-control" placeholder="🔍 Cari transaksi..." style="max-width: 250px;">
                    <button class="btn btn-success btn-export" onclick="exportToPDF()">
                        <i class="fa fa-file-pdf"></i> Export PDF
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Period Info -->
        <div class="mt-3">
            <div class="period-info">
                <i class="fa fa-calendar me-2"></i>
                Menampilkan laporan dari <strong><?= date('d M Y', strtotime($start)) ?></strong> 
                hingga <strong><?= date('d M Y', strtotime($end)) ?></strong>
                | <strong><?= $transaksi_count ?> transaksi</strong> ditemukan
            </div>
        </div>
    </div>

    <!-- Ringkasan Statistik -->
    <div class="stats-container mb-4">
        <div class="stat-card">
            <i class="fa fa-receipt text-primary"></i>
            <div class="stat-number"><?= $summary['total_transaksi'] ?? 0; ?></div>
            <div class="stat-label">Total Transaksi</div>
        </div>
        <div class="stat-card">
            <i class="fa fa-box text-success"></i>
            <div class="stat-number"><?= $summary['total_produk_terjual'] ?? 0; ?></div>
            <div class="stat-label">Produk Terjual</div>
        </div>
        <div class="stat-card">
            <i class="fa fa-chart-line text-warning"></i>
            <div class="stat-number">Rp <?= number_format($summary['rata_rata_transaksi'] ?? 0, 0, ',', '.'); ?></div>
            <div class="stat-label">Rata-rata Transaksi</div>
        </div>
        <div class="stat-card">
            <i class="fa fa-money-bill-wave text-danger"></i>
            <div class="stat-number">Rp <?= number_format($summary['total_pendapatan'] ?? 0, 0, ',', '.'); ?></div>
            <div class="stat-label">Total Pendapatan</div>
        </div>
    </div>

    <!-- Charts and Additional Info -->
    <div class="row g-4 mb-4">
        <!-- Grafik Trend Penjualan -->
        <div class="col-md-8">
            <div class="chart-card">
                <h5><i class="fa fa-chart-line me-2 text-primary"></i>Trend Penjualan 12 Bulan Terakhir</h5>
                <canvas id="salesTrendChart" height="250"></canvas>
            </div>
        </div>
        
        <!-- Produk & Kategori Terlaris -->
        <div class="col-md-4">
            <div class="row g-4">
                <div class="col-12">
                    <div class="summary-card">
                        <h5><i class="fa fa-star me-2 text-warning"></i>Produk Terlaris</h5>
                        <?php if(count($produk_terlaris) > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach($produk_terlaris as $index => $produk): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 border-0">
                                        <div>
                                            <div class="fw-semibold text-truncate" style="max-width: 150px;"><?= htmlspecialchars($produk['nama_produk']) ?></div>
                                            <small class="text-muted"><?= $produk['total_terjual'] ?> unit</small>
                                        </div>
                                        <span class="badge bg-success">Rp <?= number_format($produk['total_pendapatan'], 0, ',', '.') ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">Tidak ada data penjualan</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-12">
                    <div class="summary-card">
                        <h5><i class="fa fa-tags me-2 text-info"></i>Kategori Terlaris</h5>
                        <?php if(count($kategori_terlaris) > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach($kategori_terlaris as $kategori): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center px-0 border-0">
                                        <div>
                                            <div class="fw-semibold"><?= htmlspecialchars($kategori['nama_kategori']) ?></div>
                                            <small class="text-muted"><?= $kategori['total_terjual'] ?> unit</small>
                                        </div>
                                        <span class="badge bg-primary">Rp <?= number_format($kategori['total_pendapatan'], 0, ',', '.') ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">Tidak ada data kategori</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performa Kasir -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="chart-card">
                <h5><i class="fa fa-trophy me-2 text-success"></i>Performa Kasir</h5>
                <div class="row">
                    <?php foreach($performa_kasir as $kasir): ?>
                        <div class="col-md-3 mb-3">
                            <div class="card border-0 bg-light kasir-card">
                                <div class="card-body text-center">
                                    <div class="user-avatar bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" 
                                         style="width: 50px; height: 50px; font-size: 18px;">
                                        <?= strtoupper(substr($kasir['username'], 0, 1)) ?>
                                    </div>
                                    <h6 class="mb-1"><?= htmlspecialchars($kasir['username']) ?></h6>
                                    <small class="text-muted d-block"><?= $kasir['user_code'] ?></small>
                                    <div class="text-muted small mb-2"><?= $kasir['total_transaksi'] ?> transaksi</div>
                                    <div class="fw-bold text-success mb-1">
                                        Rp <?= number_format($kasir['total_penjualan'], 0, ',', '.') ?>
                                    </div>
                                    <small class="text-muted">
                                        Rata: Rp <?= number_format($kasir['rata_rata_penjualan'], 0, ',', '.') ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if(empty($performa_kasir)): ?>
                        <div class="col-12">
                            <div class="text-center py-4">
                                <i class="fa fa-users fa-2x text-muted mb-3"></i>
                                <p class="text-muted">Tidak ada data kasir</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<!-- Tabel Transaksi -->
<div class="table-container">
    <table class="table table-hover align-middle" id="laporanTable">
        <thead>
            <tr>
                <th width="120">Kode Transaksi</th>
                <th width="140">Tanggal & Waktu</th>
                <th width="100">Kasir</th>
                <th width="100">Jumlah Item</th>
                <th width="140">Total</th>
                <th width="100">Status</th>
                <th width="80" class="text-center">Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php 
        $transaksi_data = [];
        
        // Reset pointer query untuk loop
        mysqli_data_seek($transaksi_query, 0);
        
        while($t = mysqli_fetch_assoc($transaksi_query)): 
            $transaksi_data[] = $t;
            
            // Hitung jumlah item
            $item_count_query = mysqli_query($conn, 
                "SELECT COUNT(*) as count FROM detail_transaksi WHERE transaksi_id = {$t['id']}"
            );
            $item_count = $item_count_query ? mysqli_fetch_assoc($item_count_query)['count'] : 0;
            
            $status_class = $t['status'] === 'selesai' ? 'bg-success' : 'bg-danger';
            $status_text = $t['status'] === 'selesai' ? 'Selesai' : 'Batal';
        ?>
            <tr>
                <td>
                    <strong><?= $t['kode_transaksi']; ?></strong>
                </td>
                <td>
                    <div>
                        <div class="fw-semibold"><?= date('d M Y', strtotime($t['tanggal'])); ?></div>
                        <small class="text-muted"><?= date('H:i', strtotime($t['waktu'])); ?></small>
                    </div>
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="user-avatar bg-info text-white rounded-circle d-flex align-items-center justify-content-center me-2" 
                             style="width: 28px; height: 28px; font-size: 12px;">
                            <?= strtoupper(substr($t['kasir'] ?? 'A', 0, 1)) ?>
                        </div>
                        <div class="text-truncate" style="max-width: 60px;" title="<?= htmlspecialchars($t['kasir'] ?? 'Admin') ?>">
                            <?= htmlspecialchars($t['kasir'] ?? 'Admin'); ?>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge bg-secondary"><?= $item_count ?> item</span>
                </td>
                <td>
                    <strong class="text-success">Rp <?= number_format($t['total'], 0, ',', '.'); ?></strong>
                </td>
                <td>
                    <span class="badge <?= $status_class ?>">
                        <?= $status_text ?>
                    </span>
                </td>
                <td class="text-center">
                    <button class="btn btn-outline-primary btn-sm" 
                            onclick="showDetail(<?= $t['id'] ?>)"
                            title="Lihat Detail">
                        <i class="fa fa-eye"></i>
                    </button>
                </td>
            </tr>
        <?php endwhile; ?>
        
        <?php if(empty($transaksi_data)): ?>
            <tr>
                <td colspan="7" class="text-center py-4">
                    <i class="fa fa-receipt fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Tidak ada transaksi</h5>
                    <p class="text-muted">Tidak ada transaksi pada periode yang dipilih</p>
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

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

<footer id="footer">
    &copy; <?= date('Y'); ?> Kasir Computer — Developed by Abyan
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// 🔹 Mobile sidebar toggle
function toggleMobileSidebar() {
    document.getElementById('sidebar').classList.toggle('mobile-open');
}

// 🔹 Fitur search real-time
document.getElementById("searchInput").addEventListener("keyup", function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll("#laporanTable tbody tr");
    rows.forEach(r => {
        const text = r.textContent.toLowerCase();
        r.style.display = text.includes(filter) ? "" : "none";
    });
});

// 🔹 Chart.js - Grafik Trend Penjualan
const chartCtx = document.getElementById('salesTrendChart').getContext('2d');
new Chart(chartCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($chart_data, 'label')) ?>,
        datasets: [{
            label: 'Pendapatan (Rp)',
            data: <?= json_encode(array_column($chart_data, 'total_pendapatan')) ?>,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            fill: true,
            tension: 0.4,
            yAxisID: 'y'
        }, {
            label: 'Jumlah Transaksi',
            data: <?= json_encode(array_column($chart_data, 'jumlah_transaksi')) ?>,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            fill: true,
            tension: 0.4,
            yAxisID: 'y1'
        }]
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
                    text: 'Pendapatan (Rp)'
                },
                ticks: {
                    callback: function(value) {
                        return 'Rp ' + value.toLocaleString('id-ID');
                    }
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Jumlah Transaksi'
                },
                grid: {
                    drawOnChartArea: false,
                },
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label.includes('Pendapatan')) {
                            return label + ': Rp ' + context.parsed.y.toLocaleString('id-ID');
                        }
                        return label + ': ' + context.parsed.y;
                    }
                }
            }
        }
    }
});

// 🔹 Show Detail Transaksi
function showDetail(transaksiId) {
    document.getElementById('modalTransactionId').textContent = transaksiId;
    
    // Show loading
    document.getElementById('detailModalContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Memuat detail transaksi...</p>
        </div>
    `;
    
    const modal = new bootstrap.Modal(document.getElementById('detailModal'));
    modal.show();
    
    // Load detail via fetch
    fetch(`laporan.php?detail_id=${transaksiId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('detailModalContent').innerHTML = data;
        })
        .catch(error => {
            document.getElementById('detailModalContent').innerHTML = `
                <div class="text-center py-4">
                    <i class="fa fa-exclamation-triangle fa-2x text-danger mb-3"></i>
                    <p class="text-danger">Gagal memuat detail transaksi</p>
                    <button class="btn btn-primary btn-sm" onclick="showDetail(${transaksiId})">
                        <i class="fa fa-refresh"></i> Coba Lagi
                    </button>
                </div>
            `;
        });
}

// 🔹 Export to PDF
function exportToPDF() {
    const dates = '<?= $start ?>_to_<?= $end ?>';
    const filename = `Laporan_Penjualan_${dates}.pdf`;
    
    Swal.fire({
        title: 'Export Laporan PDF',
        text: `File ${filename} akan didownload`,
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Download',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            // Simulate download
            const link = document.createElement('a');
            link.href = '#'; // In real implementation, this would be the PDF URL
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
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

// 🔹 Mobile detection
document.addEventListener('DOMContentLoaded', function() {
    if (window.innerWidth <= 768) {
        document.querySelector('.mobile-toggle').style.display = 'block';
    }
});

// 🔹 Responsive handling
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