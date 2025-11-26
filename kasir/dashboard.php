<?php
session_start();
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'kasir') {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/db.php");

// Data statistik khusus kasir
$totalProduk = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as jml FROM produk"))['jml'] ?? 0;

// Transaksi hari ini oleh kasir ini
$transaksiHariIniResult = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as jml FROM transaksi 
    WHERE DATE(tanggal) = CURDATE() AND kasir_id = {$_SESSION['id']}
"));
$transaksiHariIni = $transaksiHariIniResult['jml'] ?? 0;

// Total transaksi oleh kasir ini
$totalTransaksiResult = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as jml FROM transaksi 
    WHERE kasir_id = {$_SESSION['id']}
"));
$totalTransaksi = $totalTransaksiResult['jml'] ?? 0;

// Pendapatan hari ini oleh kasir ini
$pendapatanHariIniResult = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(total), 0) as total FROM transaksi 
    WHERE DATE(tanggal) = CURDATE() AND kasir_id = {$_SESSION['id']}
"));
$pendapatanHariIni = $pendapatanHariIniResult['total'] ?? 0;

// Pendapatan bulan ini oleh kasir ini
$pendapatanBulanResult = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(total), 0) as total FROM transaksi 
    WHERE MONTH(tanggal)=MONTH(NOW()) AND YEAR(tanggal)=YEAR(NOW()) AND kasir_id = {$_SESSION['id']}
"));
$pendapatanBulan = $pendapatanBulanResult['total'] ?? 0;

// Rata-rata transaksi kasir ini
$avgTransactionResult = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(AVG(total), 0) as avg_total FROM transaksi 
    WHERE kasir_id = {$_SESSION['id']}
"));
$avgTransaction = $avgTransactionResult['avg_total'] ?? 0;

// Data penjualan 7 hari terakhir oleh kasir ini
$hari = [];
$total = [];
$query = mysqli_query($conn, "
    SELECT DATE_FORMAT(tanggal, '%a') as hari, COALESCE(SUM(total), 0) as total
    FROM transaksi
    WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND kasir_id = {$_SESSION['id']}
    GROUP BY DATE(tanggal)
    ORDER BY DATE(tanggal)
");
while ($row = mysqli_fetch_assoc($query)) {
    $hari[] = $row['hari'];
    $total[] = $row['total'];
}

// Produk terlaris bulan ini oleh kasir ini
$produkTerlaris = [];
$produkQuery = mysqli_query($conn, "
    SELECT p.nama_produk, SUM(dt.qty) as total_terjual
    FROM detail_transaksi dt
    JOIN transaksi t ON dt.transaksi_id = t.id
    JOIN produk p ON dt.produk_id = p.id
    WHERE MONTH(t.tanggal) = MONTH(NOW()) 
    AND YEAR(t.tanggal) = YEAR(NOW())
    AND t.kasir_id = {$_SESSION['id']}
    GROUP BY p.id, p.nama_produk
    ORDER BY total_terjual DESC
    LIMIT 5
");
while ($row = mysqli_fetch_assoc($produkQuery)) {
    $produkTerlaris[] = $row;
}

// Kategori terpopuler untuk kasir ini
$kategoriPopuler = [];
$kategoriQuery = mysqli_query($conn, "
    SELECT k.nama_kategori, COUNT(dt.id) as jumlah_transaksi
    FROM detail_transaksi dt
    JOIN transaksi t ON dt.transaksi_id = t.id
    JOIN produk p ON dt.produk_id = p.id
    JOIN kategori k ON p.kategori_id = k.id
    WHERE t.kasir_id = {$_SESSION['id']}
    GROUP BY k.id, k.nama_kategori
    ORDER BY jumlah_transaksi DESC
    LIMIT 5
");
while ($row = mysqli_fetch_assoc($kategoriQuery)) {
    $kategoriPopuler[] = $row;
}

// Jam sibuk untuk kasir ini
$jamSibuk = [];
$jamQuery = mysqli_query($conn, "
    SELECT HOUR(waktu) as jam, COUNT(*) as jumlah_transaksi
    FROM transaksi
    WHERE kasir_id = {$_SESSION['id']}
    GROUP BY HOUR(waktu)
    ORDER BY jumlah_transaksi DESC
    LIMIT 5
");
while ($row = mysqli_fetch_assoc($jamQuery)) {
    $jamSibuk[] = $row;
}

// Grafik kategori penjualan untuk kasir ini
$kategoriChart = [];
$totalPerKategori = [];
$kategoriChartQuery = mysqli_query($conn, "
    SELECT k.nama_kategori, COALESCE(SUM(dt.qty * dt.harga), 0) as total_penjualan
    FROM detail_transaksi dt
    JOIN transaksi t ON dt.transaksi_id = t.id
    JOIN produk p ON dt.produk_id = p.id
    JOIN kategori k ON p.kategori_id = k.id
    WHERE t.kasir_id = {$_SESSION['id']}
    GROUP BY k.id, k.nama_kategori
    ORDER BY total_penjualan DESC
    LIMIT 6
");
while ($row = mysqli_fetch_assoc($kategoriChartQuery)) {
    $kategoriChart[] = $row['nama_kategori'];
    $totalPerKategori[] = $row['total_penjualan'];
}

// Transaksi terbaru oleh kasir ini
$transaksiTerbaru = [];
$transaksiQuery = mysqli_query($conn, "
    SELECT t.id, t.kode_transaksi, t.tanggal, t.total, COUNT(dt.id) as jumlah_item, t.status
    FROM transaksi t
    LEFT JOIN detail_transaksi dt ON t.id = dt.transaksi_id
    WHERE t.kasir_id = {$_SESSION['id']}
    GROUP BY t.id, t.kode_transaksi, t.tanggal, t.total, t.status
    ORDER BY t.tanggal DESC
    LIMIT 6
");
while ($row = mysqli_fetch_assoc($transaksiQuery)) {
    $transaksiTerbaru[] = $row;
}

// Stok menipis
$stokMenipis = [];
$stokQuery = mysqli_query($conn, "
    SELECT nama_produk, stok, harga, kode
    FROM produk
    WHERE stok <= 10 AND stok > 0
    ORDER BY stok ASC
    LIMIT 5
");
while ($row = mysqli_fetch_assoc($stokQuery)) {
    $stokMenipis[] = $row;
}

// Stok habis
$stokHabis = [];
$stokHabisQuery = mysqli_query($conn, "
    SELECT nama_produk, kode
    FROM produk
    WHERE stok = 0
    ORDER BY nama_produk ASC
    LIMIT 5
");
while ($row = mysqli_fetch_assoc($stokHabisQuery)) {
    $stokHabis[] = $row;
}

// Target penjualan kasir (contoh target 5 juta per bulan)
$targetBulanan = 5000000;
$persentaseTarget = $pendapatanBulan > 0 ? min(100, ($pendapatanBulan / $targetBulanan) * 100) : 0;

// Warna untuk chart kategori
$colorsKategori = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Kasir</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* CSS styles tetap sama seperti sebelumnya */
body {
    font-family: 'Poppins', sans-serif;
    background: #f0f2f5;
    overflow-x: hidden;
}

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

.content {
    margin-left: 250px;
    padding: 30px;
    min-height: 100vh;
}

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

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.status-selesai { background: #d1fae5; color: #065f46; }
.status-pending { background: #fef3c7; color: #92400e; }

.chart-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    height: 300px;
}

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
    .chart-grid {
        grid-template-columns: 1fr;
        height: auto;
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
    
    <a href="transaksi.php">
        <i class="fa fa-shopping-cart"></i>
        <span class="nav-text">Transaksi Baru</span>
    </a>
    
    <a href="riwayat.php">
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
        <div class="title">Dashboard Kasir</div>
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
    <div class="welcome-box">
        <h2>Selamat Datang, <?= htmlspecialchars($_SESSION['username']); ?>! 🎉</h2>
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
                <i class="fa fa-shopping-cart text-success"></i>
                <h5>Transaksi Hari Ini</h5>
                <h3 class="counter" data-target="<?= $transaksiHariIni; ?>">0</h3>
                <div class="card-subtitle">Oleh Anda</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <i class="fa fa-money-bill-wave text-warning"></i>
                <h5>Pendapatan Hari Ini</h5>
                <h3>Rp <?= number_format($pendapatanHariIni, 0, ',', '.'); ?></h3>
                <div class="card-subtitle">Dari transaksi Anda</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <i class="fa fa-chart-line text-info"></i>
                <h5>Rata-rata Transaksi</h5>
                <h3>Rp <?= number_format($avgTransaction, 0, ',', '.'); ?></h3>
                <div class="card-subtitle">Per transaksi</div>
            </div>
        </div>
    </div>

    <!-- Baris kedua: Grafik dan Ringkasan -->
    <div class="row g-4">
        <!-- Grafik Penjualan -->
        <div class="col-md-8">
            <div class="graph-card">
                <h4>
                    <span><i class="fa fa-chart-line me-2 text-primary"></i>Analisis Penjualan Anda</span>
                    <span class="badge bg-primary">7 Hari Terakhir</span>
                </h4>
                <div class="chart-grid">
                    <div>
                        <canvas id="salesChart"></canvas>
                    </div>
                    <div>
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Ringkasan Cepat -->
        <div class="col-md-4">
            <div class="summary-card">
                <h4><i class="fa fa-tachometer-alt me-2 text-success"></i>Ringkasan Cepat</h4>
                
                <!-- Target Bulanan -->
                <div class="progress-container">
                    <div class="progress-label">
                        <span>Target Bulanan</span>
                        <span><?= number_format($persentaseTarget, 1); ?>%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?= $persentaseTarget; ?>%" 
                             aria-valuenow="<?= $persentaseTarget; ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                        </div>
                    </div>
                    <div class="progress-label">
                        <span>Rp <?= number_format($pendapatanBulan, 0, ',', '.'); ?></span>
                        <span>Rp <?= number_format($targetBulanan, 0, ',', '.'); ?></span>
                    </div>
                </div>

                <div class="quick-stats">
                    <div class="quick-stat">
                        <div class="value">Rp <?= number_format($pendapatanBulan, 0, ',', '.'); ?></div>
                        <div class="label">Pendapatan Bulan Ini</div>
                    </div>
                    <div class="quick-stat">
                        <div class="value"><?= $totalTransaksi; ?></div>
                        <div class="label">Total Transaksi</div>
                    </div>
                    <div class="quick-stat">
                        <div class="value"><?= $transaksiHariIni; ?></div>
                        <div class="label">Transaksi Hari Ini</div>
                    </div>
                    <div class="quick-stat">
                        <div class="value"><?= $totalProduk; ?></div>
                        <div class="label">Total Produk</div>
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
    
    <!-- Baris ketiga: Analisis Data -->
    <div class="row g-4 mt-4">
        <!-- Produk Terlaris -->
        <div class="col-md-4">
            <div class="summary-card">
                <h4>
                    <span><i class="fa fa-fire me-2 text-danger"></i>Produk Terlaris Anda</span>
                    <span class="badge bg-danger">Bulan Ini</span>
                </h4>
                <?php if(count($produkTerlaris) > 0): ?>
                    <table class="summary-table">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Terjual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($produkTerlaris as $produk): ?>
                                <tr>
                                    <td><?= htmlspecialchars($produk['nama_produk']); ?></td>
                                    <td>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span><?= $produk['total_terjual']; ?></span>
                                            <div class="progress" style="width: 60px;">
                                                <div class="progress-bar bg-success" style="width: <?= min(100, ($produk['total_terjual'] / max(1, $produkTerlaris[0]['total_terjual'])) * 100); ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center text-muted">Belum ada data penjualan</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Kategori Populer -->
        <div class="col-md-4">
            <div class="summary-card">
                <h4>
                    <span><i class="fa fa-star me-2 text-warning"></i>Kategori Favorit</span>
                    <span class="badge bg-warning">Pelanggan Anda</span>
                </h4>
                <?php if(count($kategoriPopuler) > 0): ?>
                    <table class="summary-table">
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th>Transaksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($kategoriPopuler as $kategori): ?>
                                <tr>
                                    <td><?= htmlspecialchars($kategori['nama_kategori']); ?></td>
                                    <td><?= $kategori['jumlah_transaksi']; ?>x</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center text-muted">Belum ada data kategori</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Jam Sibuk -->
        <div class="col-md-4">
            <div class="summary-card">
                <h4>
                    <span><i class="fa fa-clock me-2 text-info"></i>Jam Sibuk Anda</span>
                    <span class="badge bg-info">Waktu Transaksi</span>
                </h4>
                <?php if(count($jamSibuk) > 0): ?>
                    <table class="summary-table">
                        <thead>
                            <tr>
                                <th>Jam</th>
                                <th>Transaksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($jamSibuk as $jam): ?>
                                <tr>
                                    <td><?= $jam['jam']; ?>:00</td>
                                    <td><?= $jam['jumlah_transaksi']; ?> transaksi</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center text-muted">Belum ada data jam sibuk</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Baris keempat: Transaksi Terbaru -->
    <div class="row g-4 mt-4">
        <div class="col-12">
            <div class="summary-card">
                <h4>
                    <span><i class="fa fa-clock me-2 text-primary"></i>Transaksi Terbaru Anda</span>
                    <span class="badge bg-primary">6 Terbaru</span>
                </h4>
                <?php if(count($transaksiTerbaru) > 0): ?>
                    <table class="summary-table">
                        <thead>
                            <tr>
                                <th>Kode Transaksi</th>
                                <th>Tanggal</th>
                                <th>Jumlah Item</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($transaksiTerbaru as $transaksi): ?>
                                <tr>
                                    <td><?= $transaksi['kode_transaksi']; ?></td>
                                    <td>
                                        <div><?= date('d/m/Y', strtotime($transaksi['tanggal'])); ?></div>
                                        <small class="text-muted"><?= date('H:i', strtotime($transaksi['tanggal'])); ?></small>
                                    </td>
                                    <td><?= $transaksi['jumlah_item']; ?> item</td>
                                    <td>Rp <?= number_format($transaksi['total'], 0, ',', '.'); ?></td>
                                    <td>
                                        <span class="status-badge <?= $transaksi['status'] == 'selesai' ? 'status-selesai' : 'status-pending'; ?>">
                                            <?= ucfirst($transaksi['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center text-muted">Belum ada transaksi</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<footer id="footer">
    &copy; <?= date('Y'); ?> Kasir Computer — Kasir Panel
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// JavaScript tetap sama seperti sebelumnya
function toggleMobileSidebar() {
    document.getElementById('sidebar').classList.toggle('mobile-open');
}

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

    const salesCtx = document.getElementById('salesChart');
    if (salesCtx) {
        new Chart(salesCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?= json_encode($hari); ?>,
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

    const categoryCtx = document.getElementById('categoryChart');
    if (categoryCtx) {
        new Chart(categoryCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($kategoriChart); ?>,
                datasets: [{
                    data: <?= json_encode($totalPerKategori); ?>,
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
                            usePointStyle: true,
                            boxWidth: 12
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Rp ' + context.parsed.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });
    }

    if (window.innerWidth <= 768) {
        document.querySelector('.mobile-toggle').style.display = 'block';
    }
});

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