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

// Proses retur
if(isset($_POST['proses_retur'])){
    $transaksi_id = $_POST['transaksi_id'];
    $produk_id = $_POST['produk_id'];
    $qty_retur = $_POST['qty_retur'];
    $alasan = mysqli_real_escape_string($conn, $_POST['alasan']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan'] ?? '');
    
    // Ambil data transaksi dan produk
    $transaksi = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT dt.qty, dt.harga, p.nama_produk, p.stok 
        FROM detail_transaksi dt
        JOIN produk p ON dt.produk_id = p.id
        WHERE dt.transaksi_id = '$transaksi_id' AND dt.produk_id = '$produk_id'
    "));
    
    if($transaksi){
        if($qty_retur > $transaksi['qty']){
            $error = "Jumlah retur tidak boleh melebihi jumlah pembelian!";
        } else {
            mysqli_begin_transaction($conn);
            
            try {
                // Update stok produk
                mysqli_query($conn, "UPDATE produk SET stok = stok + $qty_retur WHERE id = '$produk_id'");
                
                // Generate kode retur
                $last_retur = mysqli_query($conn, "SELECT kode_retur FROM retur ORDER BY id DESC LIMIT 1");
                $next_number = 1;
                
                if(mysqli_num_rows($last_retur) > 0) {
                    $last_code = mysqli_fetch_assoc($last_retur)['kode_retur'];
                    preg_match('/\d+$/', $last_code, $matches);
                    if(!empty($matches)) {
                        $next_number = intval($matches[0]) + 1;
                    }
                }
                
                $kode_retur = 'RET' . str_pad($next_number, 4, '0', STR_PAD_LEFT);
                
                // Insert data retur
                mysqli_query($conn, "
                    INSERT INTO retur (kode_retur, transaksi_id, produk_id, kasir_id, qty, alasan, keterangan, tanggal, status) 
                    VALUES ('$kode_retur', '$transaksi_id', '$produk_id', '".$_SESSION['id']."', '$qty_retur', '$alasan', '$keterangan', NOW(), 'selesai')
                ");
                
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

// Hapus retur
if(isset($_GET['hapus_retur'])){
    $retur_id = $_GET['hapus_retur'];
    
    mysqli_begin_transaction($conn);
    try {
        // Ambil data retur untuk mengembalikan stok
        $retur_data = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT r.*, p.stok 
            FROM retur r 
            JOIN produk p ON r.produk_id = p.id 
            WHERE r.id = '$retur_id'
        "));
        
        if($retur_data){
            // Kembalikan stok ke semula
            mysqli_query($conn, "
                UPDATE produk SET stok = stok - {$retur_data['qty']} 
                WHERE id = '{$retur_data['produk_id']}'
            ");
            
            // Hapus data retur
            mysqli_query($conn, "DELETE FROM retur WHERE id = '$retur_id'");
            
            mysqli_commit($conn);
            $success = "Data retur berhasil dihapus! Stok produk telah dikembalikan.";
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = "Terjadi kesalahan: " . $e->getMessage();
    }
}

// Ambil data transaksi untuk retur - SESUAIKAN DENGAN DATABASE
$transaksi_query = mysqli_query($conn, "
    SELECT t.id, t.kode_transaksi, t.tanggal, t.total, u.username as kasir,
           COUNT(dt.id) as jumlah_item,
           GROUP_CONCAT(CONCAT(p.nama_produk, ' (', dt.qty, 'x)') SEPARATOR ', ') as produk_detail
    FROM transaksi t
    JOIN users u ON t.kasir_id = u.id
    LEFT JOIN detail_transaksi dt ON t.id = dt.transaksi_id
    LEFT JOIN produk p ON dt.produk_id = p.id
    WHERE t.status = 'selesai'
    GROUP BY t.id, t.kode_transaksi, t.tanggal, t.total, u.username
    ORDER BY t.tanggal DESC
    LIMIT 50
");

// Ambil semua data retur untuk admin - SESUAIKAN DENGAN DATABASE
$riwayat_retur = mysqli_query($conn, "
    SELECT r.*, t.kode_transaksi, p.nama_produk, p.kode, u.username as kasir
    FROM retur r
    JOIN transaksi t ON r.transaksi_id = t.id
    JOIN produk p ON r.produk_id = p.id
    JOIN users u ON r.kasir_id = u.id
    ORDER BY r.tanggal DESC
");

// Hitung statistik retur
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

$total_nilai_retur = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COALESCE(SUM(r.qty * dt.harga), 0) as total 
    FROM retur r
    JOIN detail_transaksi dt ON r.transaksi_id = dt.transaksi_id AND r.produk_id = dt.produk_id
    WHERE DATE(r.tanggal) = CURDATE()
"));
$total_nilai_retur = $total_nilai_retur['total'];

// Get produk dari transaksi untuk AJAX
if(isset($_GET['get_produk']) && isset($_GET['transaksi_id'])){
    $transaksi_id = mysqli_real_escape_string($conn, $_GET['transaksi_id']);
    $produk_query = mysqli_query($conn, "
        SELECT dt.produk_id, p.nama_produk, p.kode, dt.qty, dt.harga
        FROM detail_transaksi dt
        JOIN produk p ON dt.produk_id = p.id
        WHERE dt.transaksi_id = '$transaksi_id'
    ");
    
    $produk_data = [];
    while($row = mysqli_fetch_assoc($produk_query)){
        $produk_data[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode($produk_data);
    exit();
}

// Get detail retur untuk modal
if(isset($_GET['get_detail_retur'])){
    $retur_id = mysqli_real_escape_string($conn, $_GET['get_detail_retur']);
    $retur_detail = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT r.*, t.kode_transaksi, p.nama_produk, p.kode, u.username as kasir, dt.harga
        FROM retur r
        JOIN transaksi t ON r.transaksi_id = t.id
        JOIN produk p ON r.produk_id = p.id
        JOIN users u ON r.kasir_id = u.id
        JOIN detail_transaksi dt ON r.transaksi_id = dt.transaksi_id AND r.produk_id = dt.produk_id
        WHERE r.id = '$retur_id'
    "));
    
    if($retur_detail){
        header('Content-Type: application/json');
        echo json_encode($retur_detail);
    } else {
        echo json_encode(null);
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Retur Barang - Admin</title>
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
    color: #1e293b;
    margin-bottom: 8px;
}

/* Alert Styles */
.alert-retur {
    border-left: 4px solid #f59e0b;
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

footer {
    margin-left: 250px;
    text-align: center;
    padding: 20px 0;
    color: #6b7280;
    font-size: 14px;
    border-top: 1px solid #e5e7eb;
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
    
    <a href="retur.php" class="active">
        <i class="fa fa-undo"></i>
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
        <div class="title">
            Retur Barang
        </div>
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
        <h2>Manajemen Retur Barang</h2>
        <div class="date-info">
            <i class="fa fa-calendar me-2"></i><?= date('d F Y'); ?>
        </div>
    </div>

    <!-- Statistik Retur -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card">
                <i class="fa fa-exchange-alt text-primary"></i>
                <h5>Total Retur</h5>
                <h3><?= $total_retur; ?></h3>
                <div class="card-subtitle">Semua waktu</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <i class="fa fa-undo text-warning"></i>
                <h5>Retur Hari Ini</h5>
                <h3><?= $retur_hari_ini; ?></h3>
                <div class="card-subtitle">Tanggal <?= date('d/m/Y'); ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <i class="fa fa-boxes text-danger"></i>
                <h5>Produk Dikembalikan</h5>
                <h3><?= $total_produk_retur; ?></h3>
                <div class="card-subtitle">Unit hari ini</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <i class="fa fa-money-bill-wave text-success"></i>
                <h5>Nilai Retur</h5>
                <h3>Rp <?= number_format($total_nilai_retur, 0, ',', '.'); ?></h3>
                <div class="card-subtitle">Hari ini</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Form Retur -->
        <div class="col-lg-6">
            <div class="form-container">
                <h4 class="mb-4"><i class="fa fa-undo me-2 text-primary"></i>Form Retur Produk</h4>
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

            <!-- Panduan Retur -->
            <div class="form-container mt-4">
                <h5 class="text-warning"><i class="fa fa-info-circle me-2"></i>Panduan Retur</h5>
                <div class="alert alert-warning alert-retur">
                    <ul class="mb-0">
                        <li>Pastikan produk dalam kondisi sesuai dengan alasan retur</li>
                        <li>Stok produk akan otomatis bertambah setelah retur diproses</li>
                        <li>Retur hanya dapat dilakukan untuk transaksi yang statusnya "Selesai"</li>
                        <li>Jumlah retur tidak boleh melebihi jumlah pembelian</li>
                    </ul>
                </div>
            </div>
        </div>
<!-- Riwayat Retur -->
<div class="col-lg-6">
    <div class="summary-card">
        <h4>
            <span><i class="fa fa-history me-2 text-primary"></i>Riwayat Retur</span>
            <span class="badge bg-primary"><?= $total_retur; ?> Data</span>
        </h4>
        
        <div class="table-responsive">
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>Kode Retur</th>
                        <th>Tanggal</th>
                        <th>Transaksi</th>
                        <th>Produk</th>
                        <th>Qty</th>
                        <th>Kasir</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Reset pointer untuk loop kedua
                    mysqli_data_seek($riwayat_retur, 0);
                    if(mysqli_num_rows($riwayat_retur) > 0): ?>
                        <?php while($retur = mysqli_fetch_assoc($riwayat_retur)): ?>
                        <tr>
                            <td>
                                <strong><?= $retur['kode_retur']; ?></strong>
                            </td>
                            <td>
                                <div>
                                    <div class="fw-semibold"><?= date('d/m/Y', strtotime($retur['tanggal'])); ?></div>
                                    <small class="text-muted"><?= date('H:i', strtotime($retur['tanggal'])); ?></small>
                                </div>
                            </td>
                            <td>
                                <strong><?= $retur['kode_transaksi']; ?></strong>
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
                                <?= htmlspecialchars($retur['kasir']); ?>
                            </td>
                            <td>
                                <button class="btn btn-outline-primary btn-sm" 
                                        onclick="showDetailRetur(<?= $retur['id'] ?>)"
                                        data-bs-toggle="tooltip" 
                                        title="Lihat Detail">
                                    <i class="fa fa-eye"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-sm" 
                                        onclick="hapusRetur(<?= $retur['id'] ?>)"
                                        data-bs-toggle="tooltip" 
                                        title="Hapus Retur">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <i class="fa fa-history fa-2x mb-3"></i>
                                <p>Belum ada riwayat retur</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<footer id="footer">
    &copy; <?= date('Y'); ?> Kasir Computer — Manajemen Retur | Admin: <?= htmlspecialchars($_SESSION['username']); ?>
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
    
    // Load data produk via AJAX
    fetch(`retur.php?get_produk=1&transaksi_id=${transaksiId}`)
        .then(response => response.json())
        .then(produkList => {
            const selectedOption = document.querySelector('#selectTransaksi option:checked');
            const produkText = selectedOption.textContent;
            
            document.getElementById('produkDetail').textContent = produkText;
            
            // Enable produk select
            document.getElementById('selectProduk').disabled = false;
            document.getElementById('selectProduk').innerHTML = '<option value="">-- Pilih Produk --</option>';
            
            // Isi dropdown produk
            produkList.forEach(produk => {
                const option = document.createElement('option');
                option.value = produk.produk_id;
                option.textContent = `${produk.nama_produk} (${produk.qty} unit) - Rp ${produk.harga.toLocaleString('id-ID')}`;
                option.setAttribute('data-max-qty', produk.qty);
                document.getElementById('selectProduk').appendChild(option);
            });
            
        })
        .catch(error => {
            document.getElementById('produkDetail').textContent = 'Error memuat produk';
            console.error('Error:', error);
        });
}

// Show detail retur
function showDetailRetur(returId) {
    // Load data detail retur via AJAX
    fetch(`retur.php?get_detail_retur=${returId}`)
        .then(response => response.json())
        .then(returData => {
            if(!returData) {
                Swal.fire({
                    icon: 'error',
                    title: 'Data Tidak Ditemukan',
                    text: 'Data retur tidak ditemukan',
                    confirmButtonColor: '#dc3545'
                });
                return;
            }
            
            const subtotal = returData.qty * returData.harga;
            
            Swal.fire({
                title: `Detail Retur - ${returData.kode_retur}`,
                html: `
                    <div class="text-start">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary">Informasi Retur</h6>
                                <table class="table table-sm table-borderless">
                                    <tr><td width="100"><strong>Kode Retur</strong></td><td>${returData.kode_retur}</td></tr>
                                    <tr><td><strong>Tanggal</strong></td><td>${new Date(returData.tanggal).toLocaleDateString('id-ID')}</td></tr>
                                    <tr><td><strong>Waktu</strong></td><td>${new Date(returData.tanggal).toLocaleTimeString('id-ID')}</td></tr>
                                    <tr><td><strong>Status</strong></td><td><span class="badge bg-success">${returData.status}</span></td></tr>
                                    <tr><td><strong>Kasir</strong></td><td>${returData.kasir}</td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-success">Detail Transaksi</h6>
                                <table class="table table-sm table-borderless">
                                    <tr><td width="100"><strong>Transaksi</strong></td><td>${returData.kode_transaksi}</td></tr>
                                    <tr><td><strong>Produk</strong></td><td>${returData.nama_produk}</td></tr>
                                    <tr><td><strong>Kode</strong></td><td>${returData.kode}</td></tr>
                                    <tr><td><strong>Qty Retur</strong></td><td class="fw-bold text-danger">${returData.qty} unit</td></tr>
                                    <tr><td><strong>Harga</strong></td><td>Rp ${returData.harga.toLocaleString('id-ID')}</td></tr>
                                    <tr><td><strong>Subtotal</strong></td><td class="fw-bold">Rp ${subtotal.toLocaleString('id-ID')}</td></tr>
                                </table>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6 class="text-info">Alasan Retur</h6>
                                <div class="alert alert-info p-2">
                                    <strong class="d-block">${returData.alasan}</strong>
                                    ${returData.keterangan ? `<small class="mt-1">${returData.keterangan}</small>` : ''}
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-success mt-2 p-2">
                            <i class="fa fa-check-circle me-1"></i>
                            Stok produk <strong>${returData.nama_produk}</strong> telah ditambahkan ${returData.qty} unit
                        </div>
                    </div>
                `,
                width: 700,
                showCloseButton: true,
                showConfirmButton: false,
                customClass: {
                    popup: 'border-radius-15'
                }
            });
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Gagal Memuat',
                text: 'Terjadi kesalahan saat memuat detail retur',
                confirmButtonColor: '#dc3545'
            });
        });
}

// Hapus retur dengan konfirmasi
function hapusRetur(returId) {
    // Ambil kode retur untuk ditampilkan di konfirmasi
    fetch(`retur.php?get_detail_retur=${returId}`)
        .then(response => response.json())
        .then(returData => {
            const kodeRetur = returData ? returData.kode_retur : 'Retur';
            
            Swal.fire({
                title: 'Hapus Data Retur?',
                html: `
                    <div class="text-start">
                        <p>Anda akan menghapus data retur:</p>
                        <div class="alert alert-warning">
                            <strong>${kodeRetur}</strong><br>
                            ${returData ? `${returData.nama_produk} - ${returData.qty} unit` : ''}
                        </div>
                        <p class="text-danger"><strong>Stok produk akan dikurangi kembali!</strong></p>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                width: 500
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'retur.php?hapus_retur=' + returId;
                }
            });
        })
        .catch(error => {
            Swal.fire({
                title: 'Hapus Data Retur?',
                text: "Data retur akan dihapus dan stok produk akan dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'retur.php?hapus_retur=' + returId;
                }
            });
        });
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
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin me-2"></i>Memproses...';
    submitBtn.disabled = true;
    
    // Reset button setelah 3 detik jika form tidak submit
    setTimeout(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }, 3000);
});

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