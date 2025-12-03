<?php
session_start();
if(!isset($_SESSION['id'])){
    header("Location: ../auth/login.php");
    exit();
}
if($_SESSION['role'] !== 'kasir'){
    header("Location: ../auth/login.php");
    exit();
}

include("../config/db.php");

// Handle filter
$filter_bulan = $_GET['bulan'] ?? date('Y-m');
$filter_tanggal = $_GET['tanggal'] ?? '';

// Get kasir ID
$kasir_id = $_SESSION['id'];

// Laporan harian
$where_daily = "kasir_id = '$kasir_id'";
if($filter_tanggal){
    $where_daily .= " AND DATE(tanggal) = '$filter_tanggal'";
} else {
    $where_daily .= " AND DATE(tanggal) = CURDATE()";
}

// Laporan bulanan
$where_monthly = "kasir_id = '$kasir_id'";
if($filter_bulan){
    $bulan_tahun = explode('-', $filter_bulan);
    if(count($bulan_tahun) == 2){
        $tahun = $bulan_tahun[0];
        $bulan = $bulan_tahun[1];
        $where_monthly .= " AND YEAR(tanggal) = '$tahun' AND MONTH(tanggal) = '$bulan'";
    }
}

// Get daily statistics
$query_daily = mysqli_query($conn, "
    SELECT 
        COUNT(*) as jumlah_transaksi,
        COALESCE(SUM(total), 0) as total_pendapatan,
        AVG(total) as rata_rata_transaksi,
        MIN(total) as transaksi_terkecil,
        MAX(total) as transaksi_terbesar
    FROM transaksi 
    WHERE $where_daily AND status = 'selesai'
");

$daily_stats = mysqli_fetch_assoc($query_daily);

// Get monthly statistics
$query_monthly = mysqli_query($conn, "
    SELECT 
        COUNT(*) as jumlah_transaksi,
        COALESCE(SUM(total), 0) as total_pendapatan,
        AVG(total) as rata_rata_transaksi,
        MIN(total) as transaksi_terkecil,
        MAX(total) as transaksi_terbesar
    FROM transaksi 
    WHERE $where_monthly AND status = 'selesai'
");

$monthly_stats = mysqli_fetch_assoc($query_monthly);

// Get daily transactions
$query_daily_trans = mysqli_query($conn, "
    SELECT t.*, u.username 
    FROM transaksi t
    JOIN users u ON t.kasir_id = u.id
    WHERE $where_daily AND t.status = 'selesai'
    ORDER BY t.tanggal DESC, t.waktu DESC
    LIMIT 10
");

$daily_transactions = [];
while($row = mysqli_fetch_assoc($query_daily_trans)){
    $daily_transactions[] = $row;
}

// Get monthly top products
$query_top_products = mysqli_query($conn, "
    SELECT 
        p.nama_produk,
        p.kode,
        SUM(dt.qty) as total_terjual,
        COALESCE(SUM(dt.qty * dt.harga), 0) as total_pendapatan
    FROM detail_transaksi dt
    JOIN transaksi t ON dt.transaksi_id = t.id
    JOIN produk p ON dt.produk_id = p.id
    WHERE $where_monthly AND t.status = 'selesai'
    GROUP BY p.id, p.nama_produk, p.kode
    ORDER BY total_terjual DESC
    LIMIT 5
");

$top_products = [];
while($row = mysqli_fetch_assoc($query_top_products)){
    $top_products[] = $row;
}

// Get monthly chart data
$query_chart = mysqli_query($conn, "
    SELECT 
        DAY(tanggal) as hari,
        COUNT(*) as jumlah_transaksi,
        COALESCE(SUM(total), 0) as total_pendapatan
    FROM transaksi
    WHERE $where_monthly AND status = 'selesai'
    GROUP BY DAY(tanggal)
    ORDER BY hari
");

$chart_data = [];
while($row = mysqli_fetch_assoc($query_chart)){
    $chart_data[] = $row;
}

// Get current month name
$month_names = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April', '05' => 'Mei', '06' => 'Juni',
    '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

$selected_month = date('m');
$selected_year = date('Y');
if($filter_bulan){
    $parts = explode('-', $filter_bulan);
    if(count($parts) == 2){
        $selected_year = $parts[0];
        $selected_month = $parts[1];
    }
}

$current_month_name = $month_names[$selected_month] . ' ' . $selected_year;
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Penjualan - Kasir</title>
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
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

.card-header {
    background: #fff;
    border-bottom: 2px solid #e5e7eb;
    padding: 20px 25px;
    border-radius: 15px 15px 0 0 !important;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h4 {
    margin: 0;
    font-weight: 700;
    color: #1e293b;
    display: flex;
    align-items: center;
}

.card-header h4 i {
    margin-right: 10px;
    color: #3b82f6;
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

.filter-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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

.chart-container {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.product-ranking {
    display: flex;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #e5e7eb;
}

.product-ranking:last-child {
    border-bottom: none;
}

.rank-badge {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    margin-right: 15px;
    font-size: 14px;
}

.rank-1 { background: linear-gradient(135deg, #f59e0b, #fbbf24); color: white; }
.rank-2 { background: linear-gradient(135deg, #6b7280, #9ca3af); color: white; }
.rank-3 { background: linear-gradient(135deg, #92400e, #b45309); color: white; }
.rank-other { background: #e5e7eb; color: #374151; }

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
    
    <a href="transaksi.php">
        <i class="fa fa-shopping-cart"></i>
        <span class="nav-text">Transaksi Baru</span>
    </a>
    
    <a href="riwayat.php">
        <i class="fa fa-history"></i>
        <span class="nav-text">Riwayat Transaksi</span>
    </a>
    
    <a href="laporan.php" class="active">
        <i class="fa fa-file-alt"></i>
        <span class="nav-text">Laporan Penjualan</span>
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
    <div class="card filter-card">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Laporan Harian</label>
                    <input type="date" class="form-control" name="tanggal" value="<?= htmlspecialchars($filter_tanggal); ?>">
                    <small class="text-muted">Kosongkan untuk melihat hari ini</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Laporan Bulanan</label>
                    <input type="month" class="form-control" name="bulan" value="<?= htmlspecialchars($filter_bulan); ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100 me-2">
                        <i class="fa fa-filter me-2"></i>Filter
                    </button>
                    <?php if($filter_tanggal || $filter_bulan != date('Y-m')): ?>
                    <a href="laporan.php" class="btn btn-outline-secondary">
                        <i class="fa fa-refresh"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistik Harian -->
    <div class="card">
        <div class="card-header">
            <h4><i class="fa fa-calendar-day"></i> Statistik Harian</h4>
            <div class="text-muted">
                <?= $filter_tanggal ? date('d F Y', strtotime($filter_tanggal)) : 'Hari Ini (' . date('d/m/Y') . ')'; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="stats-container">
                <div class="stat-card">
                    <i class="fa fa-receipt text-primary"></i>
                    <div class="stat-number"><?= $daily_stats['jumlah_transaksi'] ?? 0 ?></div>
                    <div class="stat-label">Jumlah Transaksi</div>
                </div>
                <div class="stat-card">
                    <i class="fa fa-money-bill-wave text-success"></i>
                    <div class="stat-number">Rp <?= number_format($daily_stats['total_pendapatan'] ?? 0, 0, ',', '.') ?></div>
                    <div class="stat-label">Total Pendapatan</div>
                </div>
                <div class="stat-card">
                    <i class="fa fa-chart-line text-warning"></i>
                    <div class="stat-number">Rp <?= number_format($daily_stats['rata_rata_transaksi'] ?? 0, 0, ',', '.') ?></div>
                    <div class="stat-label">Rata-rata Transaksi</div>
                </div>
                <div class="stat-card">
                    <i class="fa fa-arrow-down text-danger"></i>
                    <div class="stat-number">Rp <?= number_format($daily_stats['transaksi_terkecil'] ?? 0, 0, ',', '.') ?></div>
                    <div class="stat-label">Transaksi Terkecil</div>
                </div>
                <div class="stat-card">
                    <i class="fa fa-arrow-up text-info"></i>
                    <div class="stat-number">Rp <?= number_format($daily_stats['transaksi_terbesar'] ?? 0, 0, ',', '.') ?></div>
                    <div class="stat-label">Transaksi Terbesar</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart dan Produk Terlaris -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4><i class="fa fa-chart-bar"></i> Grafik Penjualan Bulan <?= $current_month_name ?></h4>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h4><i class="fa fa-trophy"></i> 5 Produk Terlaris</h4>
                </div>
                <div class="card-body">
                    <?php if(count($top_products) > 0): ?>
                        <?php foreach($top_products as $index => $product): ?>
                            <div class="product-ranking">
                                <div class="rank-badge rank-<?= $index + 1 ?>">
                                    <?= $index + 1 ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-bold"><?= htmlspecialchars($product['nama_produk']) ?></div>
                                    <small class="text-muted">Kode: <?= htmlspecialchars($product['kode']) ?></small>
                                    <div class="d-flex justify-content-between mt-2">
                                        <span class="badge bg-success">
                                            <?= $product['total_terjual'] ?> terjual
                                        </span>
                                        <span class="fw-bold text-primary">
                                            Rp <?= number_format($product['total_pendapatan'], 0, ',', '.') ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fa fa-box-open fa-3x text-muted mb-3"></i>
                            <p class="text-muted">Tidak ada data produk terlaris</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistik Bulanan -->
    <div class="card">
        <div class="card-header">
            <h4><i class="fa fa-calendar-alt"></i> Statistik Bulanan - <?= $current_month_name ?></h4>
        </div>
        <div class="card-body">
            <div class="stats-container">
                <div class="stat-card">
                    <i class="fa fa-chart-pie text-primary"></i>
                    <div class="stat-number"><?= $monthly_stats['jumlah_transaksi'] ?? 0 ?></div>
                    <div class="stat-label">Total Transaksi Bulan Ini</div>
                </div>
                <div class="stat-card">
                    <i class="fa fa-money-bill-trend-up text-success"></i>
                    <div class="stat-number">Rp <?= number_format($monthly_stats['total_pendapatan'] ?? 0, 0, ',', '.') ?></div>
                    <div class="stat-label">Total Pendapatan Bulan Ini</div>
                </div>
                <div class="stat-card">
                    <i class="fa fa-calculator text-warning"></i>
                    <div class="stat-number">Rp <?= number_format($monthly_stats['rata_rata_transaksi'] ?? 0, 0, ',', '.') ?></div>
                    <div class="stat-label">Rata-rata per Transaksi</div>
                </div>
                <div class="stat-card">
                    <i class="fa fa-target text-info"></i>
                    <div class="stat-number"><?= count($chart_data) ?> Hari</div>
                    <div class="stat-label">Hari Aktif Penjualan</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transaksi Harian Terbaru -->
    <div class="card">
        <div class="card-header">
            <h4><i class="fa fa-history"></i> Transaksi Terbaru</h4>
            <div class="text-muted">10 transaksi terakhir hari ini</div>
        </div>
        <div class="card-body">
            <?php if(count($daily_transactions) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Kode Transaksi</th>
                                <th>Tanggal/Waktu</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($daily_transactions as $trx): ?>
                                <tr>
                                    <td>
                                        <strong class="text-primary"><?= htmlspecialchars($trx['kode_transaksi']); ?></strong>
                                    </td>
                                    <td>
                                        <div><?= date('d/m/Y', strtotime($trx['tanggal'])); ?></div>
                                        <small class="text-muted"><?= date('H:i:s', strtotime($trx['waktu'])); ?></small>
                                    </td>
                                    <td>
                                        <strong class="text-success">Rp <?= number_format($trx['total'], 0, ',', '.'); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-success text-white">
                                            <i class="fa fa-check-circle me-1"></i>
                                            <?= ucfirst($trx['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-info btn-sm"
                                                onclick="printStruk(<?= $trx['id']; ?>)"
                                                title="Print Struk">
                                            <i class="fa fa-print"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fa fa-receipt fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Belum ada transaksi hari ini</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Export Button -->
    <div class="text-center mb-4">
        <button class="btn btn-success btn-lg" onclick="exportLaporan()">
            <i class="fa fa-file-excel me-2"></i>Export Laporan Excel
        </button>
        <button class="btn btn-danger btn-lg ms-2" onclick="printLaporan()">
            <i class="fa fa-file-pdf me-2"></i>Print Laporan PDF
        </button>
    </div>
</div>

<footer>
    &copy; <?= date('Y'); ?> Kasir Computer — Developed by Abyan
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Chart.js Configuration
const chartData = <?= json_encode($chart_data); ?>;
const daysInMonth = new Date(<?= $selected_year ?>, <?= $selected_month ?>, 0).getDate();

// Prepare data for chart
const labels = Array.from({length: daysInMonth}, (_, i) => i + 1);
const salesData = Array(daysInMonth).fill(0);
const transactionData = Array(daysInMonth).fill(0);

chartData.forEach(item => {
    salesData[item.hari - 1] = item.total_pendapatan;
    transactionData[item.hari - 1] = item.jumlah_transaksi;
});

// Initialize Chart
const ctx = document.getElementById('salesChart').getContext('2d');
const salesChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [
            {
                label: 'Pendapatan (Rp)',
                data: salesData,
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                yAxisID: 'y'
            },
            {
                label: 'Jumlah Transaksi',
                data: transactionData,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
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
        stacked: false,
        plugins: {
            title: {
                display: true,
                text: 'Grafik Penjualan per Hari'
            },
            legend: {
                position: 'top',
            }
        },
        scales: {
            x: {
                title: {
                    display: true,
                    text: 'Hari'
                },
                grid: {
                    display: false
                }
            },
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
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// Mobile sidebar toggle
function toggleMobileSidebar() {
    document.getElementById('sidebar').classList.toggle('mobile-open');
}

// Print struk
function printStruk(transaksiId) {
    window.open(`print_struk.php?id=${transaksiId}`, '_blank', 'width=400,height=600');
}

// Export laporan
function exportLaporan() {
    const tanggal = '<?= $filter_tanggal ?>' || '<?= date('Y-m-d') ?>';
    const bulan = '<?= $filter_bulan ?>';
    
    Swal.fire({
        title: 'Export Laporan',
        text: 'Pilih format export:',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Excel',
        cancelButtonText: 'PDF',
        showDenyButton: true,
        denyButtonText: 'Print',
        denyButtonColor: '#3b82f6'
    }).then((result) => {
        if (result.isConfirmed) {
            // Export Excel
            window.open(`export_laporan.php?format=excel&tanggal=${tanggal}&bulan=${bulan}`, '_blank');
        } else if (result.isDenied) {
            // Print
            window.open(`export_laporan.php?format=print&tanggal=${tanggal}&bulan=${bulan}`, '_blank');
        } else if (result.dismiss === Swal.DismissReason.cancel) {
            // Export PDF
            window.open(`export_laporan.php?format=pdf&tanggal=${tanggal}&bulan=${bulan}`, '_blank');
        }
    });
}

// Print laporan
function printLaporan() {
    window.print();
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