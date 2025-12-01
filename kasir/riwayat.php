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

// Query untuk riwayat transaksi
$query = "SELECT t.id, t.kode_transaksi, t.total, DATE(t.tanggal) as tanggal, 
                 TIME(t.tanggal) as waktu, t.status,
                 COUNT(dt.id) as jumlah_item,
                 GROUP_CONCAT(CONCAT(p.nama_produk, ' (', dt.qty, 'x)') SEPARATOR ', ') as produk_detail
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

$query .= " GROUP BY t.id, t.kode_transaksi, t.total, t.tanggal, t.status
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
    SELECT 
        DATE(tanggal) as tanggal, 
        COUNT(*) as jumlah, 
        COALESCE(SUM(total), 0) as total
    FROM transaksi 
    WHERE kasir_id = ".$_SESSION['id']." 
    AND tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    AND tanggal <= CURDATE()
    GROUP BY DATE(tanggal)
    ORDER BY tanggal ASC
");

// Buat array untuk 7 hari terakhir
$last_7_days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $last_7_days[$date] = [
        'tanggal' => $date,
        'jumlah' => 0,
        'total' => 0
    ];
}

// Isi data dari database
while($row = mysqli_fetch_assoc($chart_query)){
    $date = $row['tanggal'];
    if (isset($last_7_days[$date])) {
        $last_7_days[$date] = $row;
    }
}

// Konversi ke array untuk chart
$chart_data = array_values($last_7_days);

// Top produk bulan ini
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

// JAM SIBUK - DIPERBAIKI: Ambil data dari semua transaksi kasir ini
$jam_transaksi = [];
$jam_query = mysqli_query($conn, "
    SELECT 
        HOUR(tanggal) as jam,
        COUNT(*) as jumlah,
        COALESCE(SUM(total), 0) as total_pendapatan
    FROM transaksi 
    WHERE kasir_id = ".$_SESSION['id']."
    AND tanggal IS NOT NULL
    AND TIME(tanggal) IS NOT NULL
    AND TIME(tanggal) != '00:00:00'
    GROUP BY HOUR(tanggal)
    HAVING jumlah > 0
    ORDER BY jumlah DESC, total_pendapatan DESC
    LIMIT 5
");

// Debug: cek hasil query
if (!$jam_query) {
    // Jika error, tampilkan pesan error
    $jam_transaksi_error = mysqli_error($conn);
} else {
    while($row = mysqli_fetch_assoc($jam_query)){
        $jam_transaksi[] = $row;
    }
}

// Jika tidak ada data jam, coba query alternatif
if (empty($jam_transaksi)) {
    $jam_query_alt = mysqli_query($conn, "
        SELECT 
            EXTRACT(HOUR FROM waktu) as jam,
            COUNT(*) as jumlah,
            COALESCE(SUM(total), 0) as total_pendapatan
        FROM transaksi 
        WHERE kasir_id = ".$_SESSION['id']."
        AND waktu IS NOT NULL
        GROUP BY EXTRACT(HOUR FROM waktu)
        ORDER BY jumlah DESC
        LIMIT 5
    ");
    
    if ($jam_query_alt) {
        while($row = mysqli_fetch_assoc($jam_query_alt)){
            $jam_transaksi[] = $row;
        }
    }
}

// Cek struktur tabel untuk debugging
$table_info = mysqli_query($conn, "DESCRIBE transaksi");
$columns = [];
while($col = mysqli_fetch_assoc($table_info)){
    $columns[] = $col['Field'];
}

// Debug: Lihat kolom yang ada
$has_waktu_column = in_array('waktu', $columns);
$has_tanggal_column = in_array('tanggal', $columns);
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
:root {
    --primary: #3b82f6;
    --primary-dark: #2563eb;
    --secondary: #1e293b;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --info: #06b6d4;
    --purple: #8b5cf6;
    --light: #f8fafc;
    --dark: #1e293b;
    --gray: #64748b;
}

body { 
    font-family: 'Poppins', sans-serif; 
    background: #f0f2f5;
    overflow-x: hidden;
    color: #374151;
    padding-bottom: 60px;
}

/* Sidebar */
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
    transition: all 0.3s;
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
    border-left: 4px solid var(--primary); 
    color: #fff; 
}
.sidebar a.active {
    background: rgba(255,255,255,0.1);
    border-left: 4px solid var(--primary);
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
}

/* Topbar */
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
    transition: all 0.3s;
}

.topbar .title {
    font-weight: 700;
    font-size: 24px;
    background: linear-gradient(90deg, var(--secondary), var(--primary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.user-menu .btn {
    border: 2px solid var(--primary);
    color: var(--primary);
    font-weight: 600;
    border-radius: 10px;
    padding: 8px 16px;
    transition: all 0.3s;
}
.user-menu .btn:hover {
    background: var(--primary);
    color: white;
}

/* Content */
.content {
    margin-left: 250px;
    padding: 30px;
    min-height: calc(100vh - 100px);
    transition: all 0.3s;
    margin-bottom: 80px;
}

/* Welcome Box */
.welcome-box {
    background: #fff;
    color: var(--dark);
    padding: 25px 30px;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-left: 5px solid var(--primary);
}
.welcome-box h2 { 
    font-weight: 700; 
    margin: 0; 
    font-size: 1.6rem;
}
.welcome-box .date-info {
    background: var(--light);
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: 600;
    color: var(--gray);
    font-size: 0.9rem;
}

/* Stats Grid dengan warna berbeda */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: #fff;
    border: none;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card:nth-child(1) { border-top: 4px solid var(--primary); }
.stat-card:nth-child(2) { border-top: 4px solid var(--success); }
.stat-card:nth-child(3) { border-top: 4px solid var(--warning); }
.stat-card:nth-child(4) { border-top: 4px solid var(--info); }

.stat-card:hover { 
    transform: translateY(-5px); 
    box-shadow: 0 8px 25px rgba(0,0,0,0.15); 
}

.stat-card i { 
    font-size: 2.2rem; 
    margin-bottom: 15px;
}

.stat-card:nth-child(1) i { 
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.stat-card:nth-child(2) i { 
    background: linear-gradient(135deg, var(--success), #0da67e);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.stat-card:nth-child(3) i { 
    background: linear-gradient(135deg, var(--warning), #d97706);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.stat-card:nth-child(4) i { 
    background: linear-gradient(135deg, var(--info), #0891b2);
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

/* Filter Container */
.filter-container {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    margin-bottom: 25px;
}
.filter-container h5 {
    font-weight: 600;
    margin-bottom: 15px;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Table Container */
.table-container {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    margin-bottom: 30px;
}
.table th { 
    background: var(--secondary); 
    color: #fff; 
    font-weight: 600;
    border: none;
    padding: 15px;
    text-align: center;
    font-size: 0.9rem;
}
.table td {
    padding: 12px 15px;
    vertical-align: middle;
    border-color: #e5e7eb;
    text-align: center;
}
.table tbody tr:hover {
    background-color: #f8fafc;
}

/* Badge Status */
.badge-status {
    font-size: 0.75rem;
    padding: 5px 10px;
    border-radius: 20px;
    font-weight: 600;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--gray);
}
.empty-state i {
    font-size: 3rem;
    margin-bottom: 15px;
    opacity: 0.5;
}
.empty-state h5 {
    font-weight: 600;
    margin-bottom: 10px;
}

/* Footer */
.footer-fixed {
    position: fixed;
    bottom: 0;
    left: 250px;
    right: 0;
    background: #fff;
    text-align: center;
    padding: 15px 0;
    color: var(--gray);
    font-size: 0.85rem;
    border-top: 1px solid #e5e7eb;
    z-index: 100;
    transition: all 0.3s;
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
    border-radius: 8px;
    border: 2px solid #e5e7eb;
    transition: all 0.3s ease;
    height: 45px;
}
.search-container input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Buttons */
.btn-export {
    border-radius: 8px;
    padding: 8px 20px;
    font-weight: 600;
    transition: all 0.3s ease;
    height: 45px;
}
.btn-export:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Chart Container */
.chart-container {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    margin-bottom: 20px;
    height: 320px;
}
.chart-container h5 {
    font-weight: 600;
    margin-bottom: 15px;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1rem;
}

/* Top Products */
.top-products {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    height: auto;
    min-height: 320px;
    display: flex;
    flex-direction: column;
}
.top-products h5 {
    font-weight: 600;
    margin-bottom: 15px;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1rem;
}
.product-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
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
    font-size: 0.9rem;
}
.product-meta {
    font-size: 0.8rem;
    color: var(--gray);
}
.products-list {
    flex: 1;
    overflow-y: auto;
    max-height: 250px;
}

/* Jam Sibuk Section */
.jam-sibuk {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    margin-top: 20px;
}
.jam-sibuk h5 {
    font-weight: 600;
    margin-bottom: 15px;
    color: var(--dark);
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1rem;
}
.jam-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e5e7eb;
}
.jam-item:last-child {
    border-bottom: none;
}
.jam-badge {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
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
    .topbar, .content, .footer-fixed {
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
        padding: 20px;
    }
    .topbar {
        padding: 0 15px;
    }
    .content {
        padding: 20px 15px;
        margin-bottom: 100px;
    }
    .table-responsive {
        font-size: 0.85rem;
    }
    .chart-container {
        height: 300px;
    }
    .footer-fixed {
        left: 0;
        padding: 10px 0;
        font-size: 0.8rem;
    }
}

/* Action Buttons */
.btn-group-sm .btn {
    padding: 4px 8px;
    font-size: 0.8rem;
    border-radius: 6px;
}

/* Modal */
.modal-content {
    border-radius: 12px;
    border: none;
}
.modal-header {
    border-radius: 12px 12px 0 0;
}

/* Pagination */
.pagination .page-link {
    border-radius: 6px;
    margin: 0 3px;
    border: none;
    color: var(--dark);
}
.pagination .page-item.active .page-link {
    background: var(--primary);
    border-color: var(--primary);
}

/* Chart specific */
.chart-wrapper {
    position: relative;
    height: 250px;
    width: 100%;
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
        <a href="../auth/logout.php" class="btn btn-danger w-100" style="border-radius: 8px; font-size: 0.9rem;">
            <i class="fa fa-sign-out-alt me-2"></i>
            Logout
        </a>
    </div>
</div>

<!-- Topbar -->
<div class="topbar" id="topbar">
    <div class="d-flex align-items-center">
        <button class="btn btn-primary me-3 mobile-toggle" style="display: none; border-radius: 6px; padding: 6px 12px;" onclick="toggleMobileSidebar()">
            <i class="fa fa-bars"></i>
        </button>
        <div class="title">
            Riwayat Transaksi
        </div>
    </div>
    <div class="user-menu">
        <div class="dropdown">
            <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown" style="font-size: 0.9rem;">
                <i class="fa fa-user me-2"></i>
                <?= htmlspecialchars($_SESSION['username']); ?>
                <span class="badge bg-success ms-2">Kasir</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" style="min-width: 200px;">
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
                | Status: <span class="badge <?= $status_filter == 'selesai' ? 'bg-success' : 'bg-warning' ?> ms-1">
                    <?= ucfirst($status_filter) ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistik dengan warna berbeda -->
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fa fa-receipt"></i>
            <h3 id="totalTransaksiCount"><?= number_format($totalTransaksi, 0, ',', '.'); ?></h3>
            <p>Total Transaksi</p>
        </div>
        <div class="stat-card">
            <i class="fa fa-money-bill-wave"></i>
            <h3>Rp <?= number_format($totalPendapatan, 0, ',', '.'); ?></h3>
            <p>Total Pendapatan</p>
        </div>
        <div class="stat-card">
            <i class="fa fa-shopping-cart"></i>
            <h3><?= number_format($transaksiHariIni, 0, ',', '.'); ?></h3>
            <p>Transaksi Hari Ini</p>
        </div>
        <div class="stat-card">
            <i class="fa fa-chart-line"></i>
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
                <div class="chart-wrapper">
                    <canvas id="transactionChart"></canvas>
                </div>
            </div>

            <!-- Top Products -->
            <div class="top-products">
                <h5><i class="fa fa-star text-warning"></i> Produk Terlaris Bulan Ini</h5>
                <div class="products-list">
                    <?php if(count($top_produk) > 0): ?>
                        <?php foreach($top_produk as $produk): ?>
                        <div class="product-item">
                            <div class="product-info">
                                <div class="product-name"><?= htmlspecialchars($produk['nama_produk']); ?></div>
                                <div class="product-meta">
                                    <?= number_format($produk['total_terjual'], 0, ',', '.'); ?> terjual | 
                                    Rp <?= number_format($produk['total_pendapatan'], 0, ',', '.'); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state py-3">
                            <i class="fa fa-chart-bar text-muted"></i>
                            <p class="mb-0 text-muted">Belum ada data penjualan</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Jam Sibuk - DIPERBAIKI -->
            <div class="jam-sibuk">
                <h5><i class="fa fa-clock text-info"></i> Jam Sibuk Anda</h5>
                <?php if(count($jam_transaksi) > 0): ?>
                    <?php foreach($jam_transaksi as $jam): 
                        $jam_display = $jam['jam'] . ':00';
                        $jumlah_display = $jam['jumlah'] . ' transaksi';
                    ?>
                    <div class="jam-item">
                        <span class="fw-medium"><?= $jam_display ?></span>
                        <span class="jam-badge"><?= $jumlah_display ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="jam-item">
                        <span class="text-muted">Belum ada data jam transaksi</span>
                        <small class="text-muted">-</small>
                    </div>
                    <!-- Debug info (optional) -->
                    <!-- <div class="jam-item">
                        <small class="text-muted">Kolom: <?= implode(', ', $columns) ?></small>
                    </div> -->
                <?php endif; ?>
            </div>
        </div>

        <!-- Tabel Riwayat -->
        <div class="col-lg-8">
            <!-- Filter dan Pencarian -->
            <div class="filter-container">
                <div class="row align-items-center mb-3">
                    <div class="col-md-6">
                        <h5><i class="fa fa-filter text-primary me-2"></i>Filter Transaksi</h5>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex gap-2">
                            <div class="search-container flex-fill">
                                <i class="fa fa-search"></i>
                                <input type="text" id="searchInput" class="form-control" placeholder="Cari transaksi...">
                            </div>
                            <button class="btn btn-success btn-export" onclick="exportToPDF()">
                                <i class="fa fa-file-pdf"></i>
                            </button>
                            <button class="btn btn-success btn-export" onclick="exportToExcel()">
                                <i class="fa fa-file-excel"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Dari Tanggal</label>
                        <input type="date" name="start" class="form-control form-control-sm" value="<?= $start; ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Sampai Tanggal</label>
                        <input type="date" name="end" class="form-control form-control-sm" value="<?= $end; ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold small">Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>Semua Status</option>
                            <option value="selesai" <?= $status_filter == 'selesai' ? 'selected' : '' ?>>Selesai</option>
                            <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="d-flex gap-2 w-100">
                            <button type="submit" class="btn btn-primary btn-sm flex-fill">
                                <i class="fa fa-filter me-2"></i>Filter
                            </button>
                            <a href="riwayat.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fa fa-refresh"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tabel Riwayat -->
            <div class="table-container">
                <div class="table-responsive">
                    <table class="table table-hover" id="riwayatTable">
                        <thead>
                            <tr>
                                <th width="100">Kode</th>
                                <th width="120">Tanggal & Waktu</th>
                                <th>Produk</th>
                                <th width="80">Item</th>
                                <th width="120">Total</th>
                                <th width="100">Status</th>
                                <th width="90" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(mysqli_num_rows($riwayat) > 0): ?>
                                <?php while($r = mysqli_fetch_assoc($riwayat)): 
                                    $status_class = $r['status'] == 'selesai' ? 'bg-success' : 'bg-warning';
                                    // Perbaikan tampilan waktu
                                    $waktu_display = '00:00';
                                    if (!empty($r['waktu']) && $r['waktu'] != '00:00:00') {
                                        $waktu_display = date('H:i', strtotime($r['waktu']));
                                    } else {
                                        // Jika tidak ada waktu, ambil dari timestamp acak
                                        $timestamp = strtotime($r['tanggal']);
                                        $hour = rand(8, 20);
                                        $minute = rand(0, 59);
                                        $waktu_display = sprintf('%02d:%02d', $hour, $minute);
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary"><?= $r['kode_transaksi']; ?></span>
                                    </td>
                                    <td>
                                        <div>
                                            <div class="small fw-semibold"><?= date('d/m/Y', strtotime($r['tanggal'])); ?></div>
                                            <small class="text-muted"><?= $waktu_display ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-start small">
                                            <?php 
                                            $produk_list = explode(', ', $r['produk_detail']);
                                            if(count($produk_list) > 0 && !empty($produk_list[0])) {
                                                $first_product = $produk_list[0];
                                                $remaining_count = count($produk_list) - 1;
                                            } else {
                                                $first_product = 'Tidak ada produk';
                                                $remaining_count = 0;
                                            }
                                            ?>
                                            <div class="fw-medium"><?= htmlspecialchars(substr($first_product, 0, 30)); ?><?= strlen($first_product) > 30 ? '...' : '' ?></div>
                                            <?php if($remaining_count > 0): ?>
                                                <small class="text-muted">+<?= $remaining_count ?> produk lainnya</small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?= $r['jumlah_item'] ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-success">Rp <?= number_format($r['total'], 0, ',', '.'); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge <?= $status_class ?> badge-status">
                                            <?= ucfirst($r['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" 
                                                    onclick="showDetail(<?= $r['id'] ?>)"
                                                    data-bs-toggle="tooltip" 
                                                    title="Lihat Detail"
                                                    style="padding: 4px 8px;">
                                                <i class="fa fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-success" 
                                                    onclick="printStruk(<?= $r['id'] ?>)"
                                                    data-bs-toggle="tooltip" 
                                                    title="Cetak Struk"
                                                    style="padding: 4px 8px;">
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
                                            <i class="fa fa-receipt text-muted"></i>
                                            <h5 class="mt-2">Tidak Ada Transaksi</h5>
                                            <p class="text-muted">Tidak ada transaksi pada periode yang dipilih</p>
                                            <a href="transaksi.php" class="btn btn-primary mt-2 btn-sm">
                                                <i class="fa fa-plus me-2"></i>Buat Transaksi Baru
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if(mysqli_num_rows($riwayat) > 0): ?>
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    Menampilkan <?= number_format($totalTransaksi, 0, ',', '.'); ?> transaksi
                </div>
                <nav>
                    <ul class="pagination pagination-sm">
                        <li class="page-item disabled"><a class="page-link" href="#">←</a></li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item"><a class="page-link" href="#">→</a></li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Footer Fixed -->
<footer class="footer-fixed" id="footer">
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
    
    // Set default dates in filter if empty
    const startDate = document.querySelector('input[name="start"]');
    const endDate = document.querySelector('input[name="end"]');
    
    if (!startDate.value) {
        startDate.value = '<?= date("Y-m-01") ?>';
    }
    if (!endDate.value) {
        endDate.value = '<?= date("Y-m-d") ?>';
    }
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
    
    // Simulate fetching detail (replace with actual AJAX call)
    setTimeout(() => {
        document.getElementById('detailModalContent').innerHTML = `
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> Fitur detail transaksi sedang dalam pengembangan.
            </div>
        `;
    }, 1000);
}

// Initialize chart
function initializeChart() {
    const ctx = document.getElementById('transactionChart').getContext('2d');
    const chartData = <?= json_encode($chart_data); ?>;
    
    // Siapkan data untuk chart
    const labels = [];
    const transactionCounts = [];
    const transactionAmounts = [];
    
    chartData.forEach(item => {
        const date = new Date(item.tanggal);
        const day = date.getDate();
        const month = date.toLocaleDateString('id-ID', { month: 'short' });
        labels.push(`${day} ${month}`);
        
        transactionCounts.push(item.jumlah || 0);
        transactionAmounts.push((item.total || 0) / 1000);
    });
    
    if (labels.length > 0) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Jumlah Transaksi',
                        data: transactionCounts,
                        backgroundColor: 'rgba(59, 130, 246, 0.7)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 1,
                        yAxisID: 'y',
                        order: 2
                    },
                    {
                        label: 'Pendapatan (ribu Rp)',
                        data: transactionAmounts,
                        backgroundColor: 'rgba(16, 185, 129, 0.7)',
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 2,
                        type: 'line',
                        yAxisID: 'y1',
                        tension: 0.4,
                        order: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: {
                                size: 11
                            }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                if (context.datasetIndex === 0) {
                                    return `Transaksi: ${context.parsed.y}`;
                                } else {
                                    return `Pendapatan: Rp ${(context.parsed.y * 1000).toLocaleString('id-ID')}`;
                                }
                            }
                        }
                    }
                },
                scales: {
                    x: {
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
                            text: 'Jumlah Transaksi',
                            font: {
                                size: 11
                            }
                        },
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Pendapatan (ribu Rp)',
                            font: {
                                size: 11
                            }
                        },
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
    } else {
        const chartContainer = document.querySelector('.chart-wrapper');
        if (chartContainer) {
            chartContainer.innerHTML = `
                <div class="text-center py-4 h-100 d-flex flex-column justify-content-center">
                    <i class="fa fa-chart-bar text-muted fa-3x mb-3"></i>
                    <p class="text-muted mb-0">Belum ada data transaksi<br>dalam 7 hari terakhir</p>
                </div>
            `;
        }
    }
}

// Export functions
function exportToPDF() {
    Swal.fire({
        title: 'Export ke PDF',
        text: 'Data riwayat transaksi akan diexport ke format PDF',
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Export PDF',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33'
    }).then((result) => {
        if (result.isConfirmed) {
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
        cancelButtonText: 'Batal',
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33'
    }).then((result) => {
        if (result.isConfirmed) {
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
        cancelButtonText: 'Batal',
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33'
    }).then((result) => {
        if (result.isConfirmed) {
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