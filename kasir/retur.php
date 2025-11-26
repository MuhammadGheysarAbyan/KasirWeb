<?php
session_start();
if(!isset($_SESSION['id']) || $_SESSION['role'] != 'kasir'){
    header("Location: ../auth/login.php");
    exit();
}
include("../config/db.php");

// Ambil data kasir
$kasir_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = '".$_SESSION['id']."'"));

// Proses retur
if(isset($_POST['proses_retur'])){
    $transaksi_id = $_POST['transaksi_id'];
    $produk_id = $_POST['produk_id'];
    $qty_retur = $_POST['qty_retur'];
    $alasan = mysqli_real_escape_string($conn, $_POST['alasan']);
    
    // Ambil data transaksi dan produk
    $transaksi = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT t.*, dt.qty, dt.harga, p.nama_produk, p.stok 
        FROM transaksi t
        JOIN detail_transaksi dt ON t.id = dt.transaksi_id
        JOIN produk p ON dt.produk_id = p.id
        WHERE t.id = '$transaksi_id' AND dt.produk_id = '$produk_id'
    "));
    
    if($transaksi){
        if($qty_retur > $transaksi['qty']){
            $error = "Jumlah retur tidak boleh melebihi jumlah pembelian!";
        } else {
            mysqli_begin_transaction($conn);
            
            try {
                // Update stok produk
                mysqli_query($conn, "UPDATE produk SET stok = stok + $qty_retur WHERE id = '$produk_id'");
                
                // Insert data retur (diasumsikan ada tabel retur)
                mysqli_query($conn, "
                    INSERT INTO retur (transaksi_id, produk_id, kasir_id, qty, alasan, tanggal, status) 
                    VALUES ('$transaksi_id', '$produk_id', '".$_SESSION['id']."', '$qty_retur', '$alasan', NOW(), 'selesai')
                ");
                
                // Update status transaksi jika semua item diretur
                $sisa_qty = $transaksi['qty'] - $qty_retur;
                if($sisa_qty == 0){
                    mysqli_query($conn, "UPDATE detail_transaksi SET status = 'diretur' WHERE transaksi_id = '$transaksi_id' AND produk_id = '$produk_id'");
                }
                
                mysqli_commit($conn);
                $success = "Retur berhasil diproses! Stok produk telah ditambahkan.";
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = "Terjadi kesalahan: " . $e->getMessage();
            }
        }
    } else {
        $error = "Data transaksi tidak ditemukan!";
    }
}

// Ambil data transaksi untuk retur
$transaksi_query = mysqli_query($conn, "
    SELECT t.id, t.tanggal, t.total, u.username as kasir,
           COUNT(dt.id) as jumlah_item,
           GROUP_CONCAT(CONCAT(p.nama_produk, ' (', dt.qty, 'x)') SEPARATOR ', ') as produk_detail
    FROM transaksi t
    JOIN users u ON t.kasir_id = u.id
    LEFT JOIN detail_transaksi dt ON t.id = dt.transaksi_id
    LEFT JOIN produk p ON dt.produk_id = p.id
    WHERE t.status = 'selesai'
    GROUP BY t.id, t.tanggal, t.total, u.username
    ORDER BY t.tanggal DESC
    LIMIT 50
");

// Ambil riwayat retur
$riwayat_retur = mysqli_query($conn, "
    SELECT r.*, t.id as transaksi_id, p.nama_produk, p.kode, u.username as kasir
    FROM retur r
    JOIN transaksi t ON r.transaksi_id = t.id
    JOIN produk p ON r.produk_id = p.id
    JOIN users u ON r.kasir_id = u.id
    ORDER BY r.tanggal DESC
    LIMIT 20
");

// Hitung statistik
$total_retur = mysqli_num_rows($riwayat_retur);

$total_produk_retur = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(qty), 0) as total 
    FROM retur 
    WHERE DATE(tanggal) = CURDATE()
"));
$total_produk_retur = $total_produk_retur['total'];

$retur_hari_ini = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as total 
    FROM retur 
    WHERE DATE(tanggal) = CURDATE()
"));
$retur_hari_ini = $retur_hari_ini['total'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Retur Produk</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
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
}
.stat-card.primary::before { background: linear-gradient(90deg, var(--primary), var(--primary-dark)); }
.stat-card.warning::before { background: linear-gradient(90deg, var(--warning), #f97316); }
.stat-card.danger::before { background: linear-gradient(90deg, var(--danger), #dc2626); }
.stat-card.info::before { background: linear-gradient(90deg, #06b6d4, #0ea5e9); }

.stat-card:hover { 
    transform: translateY(-5px); 
    box-shadow: 0 12px 30px rgba(0,0,0,0.15); 
}
.stat-card i { 
    font-size: 2.5rem; 
    margin-bottom: 15px;
}
.stat-card.primary i { 
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.stat-card.warning i { 
    background: linear-gradient(135deg, var(--warning), #f97316);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.stat-card.danger i { 
    background: linear-gradient(135deg, var(--danger), #dc2626);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}
.stat-card.info i { 
    background: linear-gradient(135deg, #06b6d4, #0ea5e9);
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

/* Card Styles */
.card {
    background: #fff;
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    margin-bottom: 25px;
    overflow: hidden;
}
.card-header {
    background: linear-gradient(135deg, #1e293b, #374151);
    color: white;
    padding: 20px 25px;
    border-bottom: none;
}
.card-header h5 {
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.card-body {
    padding: 25px;
}

/* Table Styles */
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

/* Badge Styles */
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

/* Form Styles */
.form-container {
    background: #fff;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    margin-bottom: 25px;
}
.form-label {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 8px;
}

/* Alert Styles */
.alert-retur {
    border-left: 4px solid var(--warning);
    background: #fffbeb;
    border-color: #fef3c7;
}

/* Button Styles */
.btn-retur {
    border-radius: 8px;
    padding: 8px 20px;
    font-weight: 600;
    transition: all 0.3s ease;
}
.btn-retur:hover {
    transform: translateY(-2px);
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

/* Animation */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.fade-in {
    animation: fadeIn 0.3s ease;
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
    
    <a href="retur.php" class="active">
        <i class="fa fa-undo"></i>
        <span class="nav-text">Retur Produk</span>
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
            <i class="fa fa-undo me-2"></i>Retur Produk
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
        </div>
    <?php endif; ?>

    <!-- Welcome Box -->
    <div class="welcome-box">
        <h2>Manajemen Retur Produk</h2>
        <div class="date-info">
            <i class="fa fa-calendar me-2"></i>
            <?= date('d F Y'); ?>
        </div>
    </div>

    <!-- Statistik -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <i class="fa fa-exchange-alt"></i>
            <h3><?= $total_retur; ?></h3>
            <p>Total Retur</p>
        </div>
        <div class="stat-card warning">
            <i class="fa fa-undo"></i>
            <h3><?= $retur_hari_ini; ?></h3>
            <p>Retur Hari Ini</p>
        </div>
        <div class="stat-card danger">
            <i class="fa fa-boxes"></i>
            <h3><?= $total_produk_retur; ?></h3>
            <p>Produk Dikembalikan</p>
        </div>
        <div class="stat-card info">
            <i class="fa fa-clock-rotate-left"></i>
            <h3><?= mysqli_num_rows($transaksi_query); ?></h3>
            <p>Transaksi Tersedia</p>
        </div>
    </div>

    <div class="row">
        <!-- Form Retur -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fa fa-undo me-2"></i>Form Retur Produk</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="formRetur">
                        <div class="mb-3">
                            <label class="form-label">Pilih Transaksi</label>
                            <select class="form-select" id="selectTransaksi" required>
                                <option value="">-- Pilih Transaksi --</option>
                                <?php while($t = mysqli_fetch_assoc($transaksi_query)): ?>
                                    <option value="<?= $t['id']; ?>" data-detail="<?= htmlspecialchars($t['produk_detail']); ?>">
                                        #<?= $t['id']; ?> - <?= date('d/m/Y H:i', strtotime($t['tanggal'])); ?> - 
                                        Rp <?= number_format($t['total'], 0, ',', '.'); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Detail Produk</label>
                            <div id="produkContainer" class="alert alert-info" style="display: none;">
                                <i class="fa fa-info-circle me-2"></i>
                                <span id="produkDetail">Pilih transaksi untuk melihat detail produk</span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Pilih Produk</label>
                            <select class="form-select" id="selectProduk" name="produk_id" required disabled>
                                <option value="">-- Pilih Produk --</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Jumlah Retur</label>
                            <input type="number" class="form-control" id="qtyRetur" name="qty_retur" 
                                   min="1" max="1" required disabled>
                            <div class="form-text">Maksimal: <span id="maxQty">0</span> unit</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Alasan Retur</label>
                            <select class="form-select" name="alasan" required>
                                <option value="">-- Pilih Alasan --</option>
                                <option value="Rusak">Produk Rusak</option>
                                <option value="Tidak Sesuai">Tidak Sesuai Pesanan</option>
                                <option value="Pengembalian">Pengembalian Barang</option>
                                <option value="Lainnya">Lainnya</option>
                            </select>
                        </div>

                        <div class="mb-3" id="keteranganContainer" style="display: none;">
                            <label class="form-label">Keterangan Lainnya</label>
                            <textarea class="form-control" name="keterangan" rows="2" placeholder="Jelaskan alasan retur..."></textarea>
                        </div>

                        <input type="hidden" name="transaksi_id" id="transaksiId">

                        <div class="d-grid gap-2">
                            <button type="submit" name="proses_retur" class="btn btn-warning btn-retur">
                                <i class="fa fa-undo me-2"></i>Proses Retur
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Riwayat Retur -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fa fa-history me-2"></i>Riwayat Retur Terbaru</h5>
                </div>
                <div class="card-body">
                    <div class="search-container">
                        <i class="fa fa-search"></i>
                        <input type="text" id="searchRetur" class="form-control" placeholder="Cari riwayat retur...">
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover" id="tabelRetur">
                            <thead>
                                <tr>
                                    <th width="80">ID</th>
                                    <th>Tanggal</th>
                                    <th>Produk</th>
                                    <th width="80">Qty</th>
                                    <th width="100">Status</th>
                                    <th width="80">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($riwayat_retur) > 0): ?>
                                    <?php while($retur = mysqli_fetch_assoc($riwayat_retur)): ?>
                                    <tr>
                                        <td><strong>#<?= $retur['transaksi_id']; ?></strong></td>
                                        <td>
                                            <div>
                                                <div class="fw-semibold"><?= date('d/m/Y', strtotime($retur['tanggal'])); ?></div>
                                                <small class="text-muted"><?= date('H:i', strtotime($retur['tanggal'])); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-start">
                                                <div class="fw-semibold"><?= htmlspecialchars($retur['nama_produk']); ?></div>
                                                <small class="text-muted"><?= $retur['kode']; ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger"><?= $retur['qty']; ?> unit</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success badge-status">
                                                <?= ucfirst($retur['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-outline-primary btn-sm" 
                                                    onclick="showDetailRetur(<?= $retur['id'] ?>)"
                                                    data-bs-toggle="tooltip" 
                                                    title="Lihat Detail">
                                                <i class="fa fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6">
                                            <div class="empty-state">
                                                <i class="fa fa-history"></i>
                                                <h5>Belum Ada Retur</h5>
                                                <p>Belum ada riwayat retur produk</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Panduan Retur -->
            <div class="card mt-4">
                <div class="card-header bg-warning text-dark">
                    <h5><i class="fa fa-info-circle me-2"></i>Panduan Retur</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning alert-retur">
                        <h6><i class="fa fa-exclamation-triangle me-2"></i>Perhatian!</h6>
                        <ul class="mb-0 mt-2">
                            <li>Pastikan produk dalam kondisi sesuai dengan alasan retur</li>
                            <li>Stok produk akan otomatis bertambah setelah retur diproses</li>
                            <li>Retur hanya dapat dilakukan untuk transaksi yang statusnya "Selesai"</li>
                            <li>Jumlah retur tidak boleh melebihi jumlah pembelian</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<footer id="footer">
    &copy; <?= date('Y'); ?> Kasir Computer — Manajemen Retur | Kasir: <?= htmlspecialchars($_SESSION['username']); ?>
</footer>

<!-- Modal Detail Retur -->
<div class="modal fade" id="detailReturModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Detail Retur #<span id="modalReturId"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="detailReturContent">
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
    
    // Event listener untuk select transaksi
    document.getElementById('selectTransaksi').addEventListener('change', function() {
        loadProdukFromTransaksi(this.value);
    });
    
    // Event listener untuk select alasan
    document.querySelector('select[name="alasan"]').addEventListener('change', function() {
        if(this.value === 'Lainnya') {
            document.getElementById('keteranganContainer').style.display = 'block';
        } else {
            document.getElementById('keteranganContainer').style.display = 'none';
        }
    });
    
    // Search functionality
    document.getElementById('searchRetur').addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll('#tabelRetur tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });
});

// Load produk dari transaksi yang dipilih
function loadProdukFromTransaksi(transaksiId) {
    if(!transaksiId) {
        document.getElementById('produkContainer').style.display = 'none';
        document.getElementById('selectProduk').disabled = true;
        document.getElementById('qtyRetur').disabled = true;
        return;
    }
    
    // Set transaksi ID
    document.getElementById('transaksiId').value = transaksiId;
    
    // Show loading
    document.getElementById('produkContainer').style.display = 'block';
    document.getElementById('produkDetail').innerHTML = '<i class="fa fa-spinner fa-spin"></i> Memuat produk...';
    
    // Simulasi loading data produk
    setTimeout(() => {
        const selectedOption = document.querySelector('#selectTransaksi option:checked');
        const produkDetail = selectedOption.getAttribute('data-detail');
        
        document.getElementById('produkDetail').textContent = produkDetail || 'Tidak ada detail produk';
        
        // Enable produk select
        document.getElementById('selectProduk').disabled = false;
        document.getElementById('selectProduk').innerHTML = '<option value="">-- Pilih Produk --</option>';
        
        // Simulasi data produk (dalam implementasi real, ini akan diambil via AJAX)
        const produkList = produkDetail.split(', ');
        produkList.forEach((produk, index) => {
            const option = document.createElement('option');
            option.value = index + 1; // Simulasi ID produk
            option.textContent = produk;
            option.setAttribute('data-max-qty', Math.floor(Math.random() * 5) + 1); // Simulasi max qty
            document.getElementById('selectProduk').appendChild(option);
        });
        
    }, 1000);
}

// Event listener untuk select produk
document.getElementById('selectProduk').addEventListener('change', function() {
    if(!this.value) {
        document.getElementById('qtyRetur').disabled = true;
        return;
    }
    
    const selectedOption = this.options[this.selectedIndex];
    const maxQty = parseInt(selectedOption.getAttribute('data-max-qty'));
    
    document.getElementById('qtyRetur').disabled = false;
    document.getElementById('qtyRetur').max = maxQty;
    document.getElementById('qtyRetur').value = 1;
    document.getElementById('maxQty').textContent = maxQty;
});

// Show detail retur
function showDetailRetur(returId) {
    document.getElementById('modalReturId').textContent = returId;
    document.getElementById('detailReturContent').innerHTML = `
        <div class="text-center py-4">
            <i class="fa fa-spinner fa-spin fa-2x text-primary"></i>
            <p class="mt-2">Memuat detail retur...</p>
        </div>
    `;
    
    const modal = new bootstrap.Modal(document.getElementById('detailReturModal'));
    modal.show();
    
    // Simulasi loading data detail retur
    setTimeout(() => {
        document.getElementById('detailReturContent').innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Informasi Retur</h6>
                    <table class="table table-sm">
                        <tr><td>ID Retur</td><td>#${returId}</td></tr>
                        <tr><td>Tanggal</td><td>${new Date().toLocaleDateString('id-ID')}</td></tr>
                        <tr><td>Status</td><td><span class="badge bg-success">Selesai</span></td></tr>
                        <tr><td>Diproses Oleh</td><td><?= htmlspecialchars($_SESSION['username']); ?></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Detail Produk</h6>
                    <table class="table table-sm">
                        <tr><td>Nama Produk</td><td>Produk Contoh</td></tr>
                        <tr><td>Kode</td><td>PRD001</td></tr>
                        <tr><td>Jumlah Retur</td><td class="fw-bold text-danger">2 unit</td></tr>
                        <tr><td>Alasan</td><td>Produk Rusak</td></tr>
                    </table>
                </div>
            </div>
            <div class="alert alert-info mt-3">
                <i class="fa fa-info-circle me-2"></i>
                Stok produk telah berhasil ditambahkan ke sistem.
            </div>
        `;
    }, 1000);
}

// Form validation
document.getElementById('formRetur').addEventListener('submit', function(e) {
    const qtyRetur = document.getElementById('qtyRetur').value;
    const maxQty = document.getElementById('qtyRetur').max;
    
    if(parseInt(qtyRetur) > parseInt(maxQty)) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Jumlah Retur Melebihi Batas',
            text: `Jumlah retur tidak boleh melebihi ${maxQty} unit`,
            confirmButtonColor: '#dc3545'
        });
        return;
    }
    
    // Show loading
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i>Memproses...';
    submitBtn.disabled = true;
});

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