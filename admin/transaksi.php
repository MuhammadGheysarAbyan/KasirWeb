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

// Handle filter
$filter_tanggal = $_GET['tanggal'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_kasir = $_GET['kasir'] ?? '';

$where_conditions = [];
$params = [];

if($filter_tanggal){
    $where_conditions[] = "DATE(t.tanggal) = '$filter_tanggal'";
}
if($filter_status && $filter_status != 'all'){
    $where_conditions[] = "t.status = '$filter_status'";
}
if($filter_kasir && $filter_kasir != 'all'){
    $where_conditions[] = "t.kasir_id = '$filter_kasir'";
}

$where_sql = '';
if(!empty($where_conditions)){
    $where_sql = "WHERE " . implode(' AND ', $where_conditions);
}

// Get transactions
$transaksi = [];
$query = mysqli_query($conn, "
    SELECT t.*, u.username, COUNT(dt.id) as jumlah_item
    FROM transaksi t
    JOIN users u ON t.kasir_id = u.id
    LEFT JOIN detail_transaksi dt ON t.id = dt.transaksi_id
    $where_sql
    GROUP BY t.id, t.kode_transaksi, t.tanggal, t.waktu, t.total, t.status, u.username
    ORDER BY t.tanggal DESC, t.waktu DESC
");
while($row = mysqli_fetch_assoc($query)){
    $transaksi[] = $row;
}

// Get kasir list for filter
$kasir_list = [];
$query_kasir = mysqli_query($conn, "SELECT id, username FROM users WHERE role='kasir'");
while($row = mysqli_fetch_assoc($query_kasir)){
    $kasir_list[] = $row;
}

// Statistik transaksi
$total_transaksi = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM transaksi"))['total'];
$transaksi_hari_ini = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM transaksi WHERE DATE(tanggal) = CURDATE()"))['total'];
$pendapatan_hari_ini = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(total), 0) as total FROM transaksi WHERE DATE(tanggal) = CURDATE()"))['total'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Data Transaksi</title>
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

/* Stats Container - SAMA DENGAN USERS.PHP (4 kolom) */
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

/* Filter Card - SAMA DENGAN LAINNYA */
.filter-card {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

/* Card Styling - SAMA PERSIS */
.card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    margin-bottom: 25px;
}

/* Table Styling - SAMA PERSIS */
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

/* Badge Styling - SAMA PERSIS */
.badge {
    padding: 8px 12px;
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

/* Status Badges */
.status-completed { background-color: #10b981; color: white; }
.status-pending { background-color: #f59e0b; color: white; }
.status-cancelled { background-color: #ef4444; color: white; }

/* Action Buttons - SAMA PERSIS */
.action-buttons {
    display: flex;
    gap: 8px;
    justify-content: center;
}

/* Product Image - SAMA PERSIS */
.product-image {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    object-fit: cover;
    border: 2px solid #e5e7eb;
}

/* Detail Section - SAMA PERSIS */
.detail-section {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
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
    .action-buttons {
        flex-direction: column;
        gap: 5px;
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
    
    <a href="transaksi.php" class="active">
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

<!-- Topbar - SAMA PERSIS -->
<div class="topbar" id="topbar">
    <div class="d-flex align-items-center">
        <button class="btn btn-primary me-3 mobile-toggle" style="display: none; border-radius: 8px;" onclick="toggleMobileSidebar()">
            <i class="fa fa-bars"></i>
        </button>
        <div class="title">Data Transaksi</div>
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
    <!-- Statistik Transaksi - 4 KOLOM SAMA DENGAN USERS.PHP -->
    <div class="stats-container">
        <div class="stat-card">
            <i class="fa fa-receipt text-primary"></i>
            <div class="stat-number"><?= $total_transaksi ?></div>
            <div class="stat-label">Total Transaksi</div>
        </div>
        <div class="stat-card">
            <i class="fa fa-calendar-day text-success"></i>
            <div class="stat-number"><?= $transaksi_hari_ini ?></div>
            <div class="stat-label">Transaksi Hari Ini</div>
        </div>
        <div class="stat-card">
            <i class="fa fa-money-bill-wave text-warning"></i>
            <div class="stat-number">Rp <?= number_format($pendapatan_hari_ini, 0, ',', '.') ?></div>
            <div class="stat-label">Pendapatan Hari Ini</div>
        </div>
        <div class="stat-card">
            <i class="fa fa-users text-info"></i>
            <div class="stat-number"><?= count($kasir_list) ?></div>
            <div class="stat-label">Total Kasir</div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-card">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Tanggal</label>
                <input type="date" class="form-control" name="tanggal" value="<?= htmlspecialchars($filter_tanggal); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="all">Semua Status</option>
                    <option value="selesai" <?= $filter_status == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                    <option value="batal" <?= $filter_status == 'batal' ? 'selected' : ''; ?>>Batal</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Kasir</label>
                <select class="form-select" name="kasir">
                    <option value="all">Semua Kasir</option>
                    <?php foreach($kasir_list as $kasir): ?>
                        <option value="<?= $kasir['id']; ?>" <?= $filter_kasir == $kasir['id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($kasir['username']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fa fa-filter me-2"></i>Filter
                </button>
                <?php if($filter_tanggal || $filter_status != 'all' || $filter_kasir != 'all'): ?>
                <a href="transaksi.php" class="btn btn-outline-secondary ms-2">
                    <i class="fa fa-refresh"></i>
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Transactions Table -->
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="fa fa-exchange-alt me-2 text-primary"></i>Daftar Transaksi</h4>
                <span class="text-muted">Total: <strong><?= count($transaksi); ?></strong> transaksi</span>
            </div>
            
            <?php if(count($transaksi) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Kode Transaksi</th>
                                <th>Tanggal/Waktu</th>
                                <th>Kasir</th>
                                <th>Jumlah Item</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th width="150" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($transaksi as $trx): ?>
                                <tr>
                                    <td>
                                        <strong class="text-primary"><?= htmlspecialchars($trx['kode_transaksi']); ?></strong>
                                    </td>
                                    <td>
                                        <div><?= date('d/m/Y', strtotime($trx['tanggal'])); ?></div>
                                        <small class="text-muted"><?= date('H:i:s', strtotime($trx['waktu'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" 
                                                 style="width: 32px; height: 32px; font-size: 14px;">
                                                <?= strtoupper(substr($trx['username'], 0, 1)); ?>
                                            </div>
                                            <?= htmlspecialchars($trx['username']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= $trx['jumlah_item']; ?> item</span>
                                    </td>
                                    <td>
                                        <strong class="text-success">Rp <?= number_format($trx['total'], 0, ',', '.'); ?></strong>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_class = '';
                                        $status_icon = '';
                                        switch($trx['status']){
                                            case 'selesai': 
                                                $status_class = 'bg-success';
                                                $status_icon = 'fa-check-circle';
                                                break;
                                            case 'batal': 
                                                $status_class = 'bg-danger';
                                                $status_icon = 'fa-times-circle';
                                                break;
                                            default:
                                                $status_class = 'bg-secondary';
                                                $status_icon = 'fa-question-circle';
                                        }
                                        ?>
                                        <span class="badge <?= $status_class; ?> text-white">
                                            <i class="fa <?= $status_icon ?> me-1"></i>
                                            <?= ucfirst($trx['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-info btn-sm" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#detailModal"
                                                    onclick="showDetail(<?= $trx['id']; ?>)"
                                                    title="Detail Transaksi">
                                                <i class="fa fa-eye"></i>
                                            </button>
                                            <button class="btn btn-warning btn-sm"
                                                    onclick="printStruk(<?= $trx['id']; ?>)"
                                                    title="Print Struk">
                                                <i class="fa fa-print"></i>
                                            </button>
                                            <?php if($trx['status'] == 'selesai'): ?>
                                            <button class="btn btn-danger btn-sm"
                                                    onclick="updateStatus(<?= $trx['id']; ?>, 'batal')"
                                                    title="Batalkan Transaksi">
                                                <i class="fa fa-times"></i>
                                            </button>
                                            <?php elseif($trx['status'] == 'batal'): ?>
                                            <button class="btn btn-success btn-sm"
                                                    onclick="updateStatus(<?= $trx['id']; ?>, 'selesai')"
                                                    title="Aktifkan Transaksi">
                                                <i class="fa fa-check"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fa fa-receipt fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Tidak ada data transaksi</h5>
                    <p class="text-muted">
                        <?php if($filter_tanggal || $filter_status != 'all' || $filter_kasir != 'all'): ?>
                        Coba ubah filter pencarian atau 
                        <?php endif; ?>
                        <a href="transaksi.php" class="text-primary">lihat semua transaksi</a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Detail Transaksi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="detailContent">
                        <!-- Detail will be loaded here via JavaScript -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary" onclick="printStruk(currentDetailId)">
                        <i class="fa fa-print me-2"></i>Print Struk
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer - SAMA PERSIS -->
<footer>
    &copy; <?= date('Y'); ?> Kasir Computer — Developed by Abyan
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentDetailId = 0;

// Show transaction detail
function showDetail(transaksiId) {
    currentDetailId = transaksiId;
    
    // Show loading
    document.getElementById('detailContent').innerHTML = `
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
    fetch(`get_transaction_detail.php?id=${transaksiId}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('detailContent').innerHTML = data;
        })
        .catch(error => {
            document.getElementById('detailContent').innerHTML = `
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

// Print struk
function printStruk(transaksiId) {
    window.open(`print_struk.php?id=${transaksiId}`, '_blank', 'width=400,height=600');
}

// Update transaction status
function updateStatus(transaksiId, status) {
    const statusText = status === 'selesai' ? 'selesaikan' : 'batalkan';
    
    Swal.fire({
        title: `Yakin ingin ${statusText} transaksi?`,
        text: "Status transaksi akan diubah!",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: status === 'selesai' ? '#10b981' : '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: `Ya, ${statusText}!`,
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location = `update_status.php?id=${transaksiId}&status=${status}`;
        }
    });
}

// === Mobile sidebar toggle - SAMA PERSIS ===
function toggleMobileSidebar() {
    document.getElementById('sidebar').classList.toggle('mobile-open');
}

// === Mobile detection - SAMA PERSIS ===
document.addEventListener('DOMContentLoaded', function() {
    if (window.innerWidth <= 768) {
        document.querySelector('.mobile-toggle').style.display = 'block';
    }
});

// === Responsive handling - SAMA PERSIS ===
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