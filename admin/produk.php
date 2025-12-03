<?php
session_start();
if(!isset($_SESSION['id']) || $_SESSION['role'] != 'admin'){
    header("Location: ../auth/login.php");
    exit();
}
include("../config/db.php");

// === CRUD KATEGORI ===
if(isset($_POST['tambah_kategori'])){
    $nama_kategori = trim($_POST['nama_kategori']);
    if(!empty($nama_kategori)){
        // Generate kode kategori otomatis
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $nama_kategori), 0, 3));
        if(strlen($prefix) < 3) {
            $prefix = str_pad($prefix, 3, 'X', STR_PAD_RIGHT);
        }
        
        // Cari nomor urut terakhir untuk prefix ini
        $last_code = mysqli_query($conn, "SELECT kode FROM kategori WHERE kode LIKE '$prefix%' ORDER BY id DESC LIMIT 1");
        $next_number = 1;
        
        if(mysqli_num_rows($last_code) > 0) {
            $last_row = mysqli_fetch_assoc($last_code);
            $last_code_value = $last_row['kode'];
            // Extract number from last code (e.g., LAP-001 -> 1)
            preg_match('/-?(\d+)$/', $last_code_value, $matches);
            if(!empty($matches)) {
                $next_number = intval($matches[1]) + 1;
            }
        }
        
        $kode = $prefix . '-' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
        
        // Insert kategori dengan kode
        mysqli_query($conn, "INSERT INTO kategori (nama_kategori, kode) VALUES ('$nama_kategori', '$kode')");
        header("Location: produk.php?success=kategori_tambah");
        exit();
    }
}

if(isset($_POST['edit_kategori'])){
    $id = $_POST['id_kategori'];
    $nama_kategori = trim($_POST['nama_kategori']);
    if(!empty($nama_kategori)){
        mysqli_query($conn, "UPDATE kategori SET nama_kategori='$nama_kategori' WHERE id=$id");
        header("Location: produk.php?success=kategori_edit");
        exit();
    }
}

if(isset($_GET['hapus_kategori'])){
    $id = $_GET['hapus_kategori'];
    mysqli_query($conn, "DELETE FROM kategori WHERE id=$id");
    header("Location: produk.php?success=kategori_hapus");
    exit();
}

// === CRUD PRODUK ===
if(isset($_POST['tambah'])){
    $nama_produk = mysqli_real_escape_string($conn, $_POST['nama_produk']);
    $harga = $_POST['harga'];
    $stok = $_POST['stok'];
    $kategori_id = $_POST['kategori_id'] ?? 1;
    
    // Ambil informasi kategori
    $kategori_result = mysqli_query($conn, "SELECT nama_kategori FROM kategori WHERE id=$kategori_id");
    $kategori_row = mysqli_fetch_assoc($kategori_result);
    $nama_kategori = $kategori_row['nama_kategori'] ?? 'Umum';
    
    // Generate kode otomatis berdasarkan kategori
    $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $nama_kategori), 0, 3));
    if(strlen($prefix) < 3) {
        $prefix = str_pad($prefix, 3, 'X', STR_PAD_RIGHT);
    }
    
    // Cari nomor urut terakhir untuk kategori ini
    $last_code_query = mysqli_query($conn, "
        SELECT kode FROM produk 
        WHERE kategori_id = $kategori_id 
        AND kode LIKE '$prefix%'
        ORDER BY id DESC LIMIT 1
    ");
    
    $next_number = 1;
    
    if(mysqli_num_rows($last_code_query) > 0) {
        $last_code = mysqli_fetch_assoc($last_code_query)['kode'];
        // Extract number from last code (e.g., LAP-001 -> 1)
        preg_match('/-?(\d+)$/', $last_code, $matches);
        if(!empty($matches)) {
            $last_number = intval($matches[1]);
            $next_number = $last_number + 1;
        }
    }
    
    // Format kode: PREFIX-NOMOR (3 digit)
    $kode = $prefix . '-' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
    
    // Cek apakah kode sudah ada
    $cek_kode = mysqli_query($conn, "SELECT id FROM produk WHERE kode='$kode'");
    if(mysqli_num_rows($cek_kode) > 0){
        $kode = $prefix . '-' . str_pad($next_number, 3, '0', STR_PAD_LEFT) . rand(1, 9);
    }

    $foto = 'default-product.jpg';
    if(!empty($_FILES['foto']['name'])){
        $foto = mysqli_real_escape_string($conn, $_FILES['foto']['name']);
        $tmp = $_FILES['foto']['tmp_name'];
        $path = "../assets/img/produk/" . $foto;
        
        if(!is_dir("../assets/img/produk/")) {
            mkdir("../assets/img/produk/", 0777, true);
        }
        move_uploaded_file($tmp, $path);
    }

    // Query aman berdasarkan kolom yang ada
    $columns = [];
    $result = mysqli_query($conn, "DESCRIBE produk");
    while($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row['Field'];
    }

    if(in_array('kategori_id', $columns) && in_array('kode', $columns) && in_array('foto', $columns)) {
        mysqli_query($conn, "INSERT INTO produk (nama_produk, harga, stok, foto, kode, kategori_id) VALUES ('$nama_produk','$harga','$stok','$foto','$kode','$kategori_id')");
    } elseif(in_array('kategori', $columns) && in_array('kode', $columns) && in_array('foto', $columns)) {
        mysqli_query($conn, "INSERT INTO produk (nama_produk, harga, stok, foto, kategori, kode) VALUES ('$nama_produk','$harga','$stok','$foto','$nama_kategori','$kode')");
    } else {
        mysqli_query($conn, "INSERT INTO produk (nama_produk, harga, stok) VALUES ('$nama_produk','$harga','$stok')");
    }
    
    header("Location: produk.php?success=tambah");
    exit();
}

if(isset($_POST['edit'])){
    $id = $_POST['id'];
    $nama_produk = mysqli_real_escape_string($conn, $_POST['nama_produk']);
    $harga = $_POST['harga'];
    $stok = $_POST['stok'];
    $kategori_id = $_POST['kategori_id'] ?? 1;
    $kode = mysqli_real_escape_string($conn, $_POST['kode']);

    $columns = [];
    $result = mysqli_query($conn, "DESCRIBE produk");
    while($row = mysqli_fetch_assoc($result)) {
        $columns[] = $row['Field'];
    }

    $update_fields = ["nama_produk='$nama_produk'", "harga='$harga'", "stok='$stok'"];
    
    if(in_array('kode', $columns)) {
        $update_fields[] = "kode='$kode'";
    }
    
    if(in_array('kategori_id', $columns)) {
        $update_fields[] = "kategori_id='$kategori_id'";
    } elseif(in_array('kategori', $columns)) {
        $kategori_result = mysqli_query($conn, "SELECT nama_kategori FROM kategori WHERE id=$kategori_id");
        $kategori_row = mysqli_fetch_assoc($kategori_result);
        $nama_kategori = $kategori_row['nama_kategori'] ?? 'Umum';
        $update_fields[] = "kategori='$nama_kategori'";
    }

    if(!empty($_FILES['foto']['name']) && in_array('foto', $columns)){
        $foto = mysqli_real_escape_string($conn, $_FILES['foto']['name']);
        $tmp = $_FILES['foto']['tmp_name'];
        $path = "../assets/img/produk/" . $foto;
        move_uploaded_file($tmp, $path);
        $update_fields[] = "foto='$foto'";
    }

    $update_query = "UPDATE produk SET " . implode(', ', $update_fields) . " WHERE id=$id";
    mysqli_query($conn, $update_query);

    header("Location: produk.php?success=edit");
    exit();
}

if(isset($_GET['hapus'])){
    $id = $_GET['hapus'];
    mysqli_query($conn, "DELETE FROM produk WHERE id=$id");
    header("Location: produk.php?success=hapus");
    exit();
}

// === PAGINATION ===
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// === Ambil data produk ===
$search = $_GET['search'] ?? '';
$kategori_filter = $_GET['kategori'] ?? '';
$stok_filter = $_GET['stok'] ?? '';

// Query untuk mengambil data produk dengan pagination
// AMAN: Cek dulu apakah kolom kode ada di tabel kategori
$table_check = mysqli_query($conn, "SHOW COLUMNS FROM kategori LIKE 'kode'");
$has_kode_column = (mysqli_num_rows($table_check) > 0);

if($has_kode_column) {
    $query = "SELECT p.*, k.nama_kategori, k.kode as kode_kategori 
              FROM produk p 
              LEFT JOIN kategori k ON p.kategori_id = k.id 
              WHERE 1=1";
    $count_query = "SELECT COUNT(*) as total 
                    FROM produk p 
                    LEFT JOIN kategori k ON p.kategori_id = k.id 
                    WHERE 1=1";
} else {
    $query = "SELECT p.*, k.nama_kategori 
              FROM produk p 
              LEFT JOIN kategori k ON p.kategori_id = k.id 
              WHERE 1=1";
    $count_query = "SELECT COUNT(*) as total 
                    FROM produk p 
                    LEFT JOIN kategori k ON p.kategori_id = k.id 
                    WHERE 1=1";
}

if(!empty($search)) {
    $search_condition = " AND (p.nama_produk LIKE '%$search%' OR p.kode LIKE '%$search%')";
    $query .= $search_condition;
    $count_query .= $search_condition;
}
if(!empty($kategori_filter)) {
    $kategori_condition = " AND p.kategori_id = '$kategori_filter'";
    $query .= $kategori_condition;
    $count_query .= $kategori_condition;
}
if($stok_filter == 'menipis') {
    $stok_condition = " AND p.stok <= 10 AND p.stok > 0";
    $query .= $stok_condition;
    $count_query .= $stok_condition;
} elseif($stok_filter == 'habis') {
    $stok_condition = " AND p.stok = 0";
    $query .= $stok_condition;
    $count_query .= $stok_condition;
}

$query .= " ORDER BY p.id DESC LIMIT $limit OFFSET $offset";
$produk = mysqli_query($conn, $query);

$total_result = mysqli_fetch_assoc(mysqli_query($conn, $count_query));
$total_produk_all = $total_result['total'];
$total_pages = ceil($total_produk_all / $limit);

// === Ambil kategori ===
$kategori_query = mysqli_query($conn, "SELECT * FROM kategori ORDER BY nama_kategori");
$kategori_list = [];
while($row = mysqli_fetch_assoc($kategori_query)) {
    $kategori_list[] = $row;
}

// Query untuk filter kategori - AMAN
if($has_kode_column) {
    $kategori_filter_query = mysqli_query($conn, "SELECT DISTINCT k.id, k.nama_kategori, k.kode FROM kategori k JOIN produk p ON k.id = p.kategori_id ORDER BY k.nama_kategori");
} else {
    $kategori_filter_query = mysqli_query($conn, "SELECT DISTINCT k.id, k.nama_kategori FROM kategori k JOIN produk p ON k.id = p.kategori_id ORDER BY k.nama_kategori");
}
$kategori_filter_list = [];
while($row = mysqli_fetch_assoc($kategori_filter_query)) {
    $kategori_filter_list[] = $row;
}

// === Statistik produk ===
$total_produk = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM produk"))['total'];
$stok_menipis = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM produk WHERE stok <= 10 AND stok > 0"))['total'];
$stok_habis = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM produk WHERE stok = 0"))['total'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Produk</title>
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

/* Search box styling */
.input-group-text {
    border-radius: 8px 0 0 8px !important;
    background-color: #f8f9fa !important;
}
.form-control.border-start-0 {
    border-radius: 0 8px 8px 0 !important;
}

/* Pastikan tab aktif tetap terlihat */
.nav-tabs .nav-link.active {
    background: #1e293b !important;
    color: #fff !important;
    border-color: #1e293b !important;
}

.content {
    margin-left: 250px;
    padding: 30px;
    min-height: 100vh;
}

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
img.preview { 
    width: 60px; 
    height: 60px;
    border-radius: 8px; 
    object-fit: cover;
    border: 2px solid #e5e7eb;
}
.badge-stok {
    font-size: 11px;
    padding: 5px 10px;
    border-radius: 20px;
    cursor: help;
    min-width: 80px;
    display: inline-block;
    text-align: center;
}
.bg-success { background-color: #10b981 !important; }
.bg-warning { background-color: #f59e0b !important; color: #000 !important; }
.bg-danger { background-color: #ef4444 !important; }
.table-container {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.action-buttons {
    display: flex;
    gap: 8px;
    justify-content: center;
}
.form-control, .form-select {
    border-radius: 8px;
    border: 1px solid #d1d5db;
    padding: 8px 12px;
}
.form-control:focus, .form-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
}
.modal-header {
    border-bottom: 2px solid #e5e7eb;
}
.modal-footer {
    border-top: 2px solid #e5e7eb;
}
.product-image-large {
    max-height: 300px;
    width: 100%;
    object-fit: contain;
    border-radius: 12px;
}
.detail-section {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
}
.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    padding: 15px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.tab-content {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-top: 20px;
}
.nav-tabs .nav-link.active {
    background: #1e293b;
    color: #fff;
    border-color: #1e293b;
}
.search-filter-container {
    background: #fff;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
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
    
    <a href="produk.php" class="active">
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
        <div class="title">Kelola Produk & Kategori</div>
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
    <!-- Statistik Produk -->
    <div class="stats-container">
        <div class="stat-card">
            <i class="fa fa-boxes text-primary"></i>
            <div class="stat-number"><?= $total_produk ?></div>
            <div class="stat-label">Total Produk</div>
        </div>
        <div class="stat-card">
            <i class="fa fa-exclamation-triangle text-warning"></i>
            <div class="stat-number"><?= $stok_menipis ?></div>
            <div class="stat-label">Stok Menipis</div>
        </div>
        <div class="stat-card">
            <i class="fa fa-times-circle text-danger"></i>
            <div class="stat-number"><?= $stok_habis ?></div>
            <div class="stat-label">Stok Habis</div>
        </div>
    </div>

    <!-- Tabs untuk Produk dan Kategori -->
    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="produk-tab" data-bs-toggle="tab" data-bs-target="#produk" type="button" role="tab">
                <i class="fa fa-box me-2"></i>Kelola Produk
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="kategori-tab" data-bs-toggle="tab" data-bs-target="#kategori" type="button" role="tab">
                <i class="fa fa-tags me-2"></i>Kelola Kategori
            </button>
        </li>
    </ul>

    <div class="tab-content" id="myTabContent">
        <!-- Tab Produk -->
        <div class="tab-pane fade show active" id="produk" role="tabpanel">
            <!-- Search dan Filter -->
            <div class="search-filter-container">
                <div class="row g-3 align-items-center">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="fa fa-search text-muted"></i>
                            </span>
                            <input type="text" name="search" class="form-control border-start-0" placeholder="Cari produk atau kode..." value="<?= htmlspecialchars($search) ?>" id="searchInput">
                            <?php if(!empty($search)): ?>
                            <button type="button" class="btn btn-outline-secondary" onclick="clearSearch()" title="Clear search">
                                <i class="fa fa-times"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="kategori" class="form-select" id="kategoriSelect">
                            <option value="">Semua Kategori</option>
                            <?php foreach($kategori_filter_list as $kat): ?>
                                <option value="<?= $kat['id'] ?>" <?= $kategori_filter == $kat['id'] ? 'selected' : '' ?>>
                                    <?= $kat['nama_kategori'] ?>
                                    <?php if(isset($kat['kode'])): ?>
                                        (<?= $kat['kode'] ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="stok" class="form-select" id="stokSelect">
                            <option value="">Semua Stok</option>
                            <option value="menipis" <?= $stok_filter == 'menipis' ? 'selected' : '' ?>>Stok Menipis</option>
                            <option value="habis" <?= $stok_filter == 'habis' ? 'selected' : '' ?>>Stok Habis</option>
                        </select>
                    </div>
                    <div class="col-md-2 text-end">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
                            <i class="fa fa-plus"></i> Tambah Produk
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tabel Produk -->
            <div class="table-container">
                <table class="table table-hover align-middle" id="produkTable">
                    <thead>
                        <tr>
                            <th width="80">Foto</th>
                            <th>Nama Produk</th>
                            <th>Kode</th>
                            <th>Kategori</th>
                            <th width="120">Harga</th>
                            <th width="100">Stok</th>
                            <th width="180" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $products_data = [];
                        mysqli_data_seek($produk, 0);
                        if(mysqli_num_rows($produk) > 0) {
                            while($row = mysqli_fetch_assoc($produk)): 
                                $products_data[] = $row;
                                $stok_class = '';
                                $stok_text = $row['stok'] . ' unit';
                                
                                if($row['stok'] == 0) {
                                    $stok_class = 'bg-danger';
                                    $stok_text = 'Stok Habis';
                                } elseif($row['stok'] <= 10) {
                                    $stok_class = 'bg-warning';
                                    $stok_text = $row['stok'] . ' unit (Menipis)';
                                } else {
                                    $stok_class = 'bg-success';
                                    $stok_text = $row['stok'] . ' unit';
                                }
                        ?>
                        <tr>
                            <td>
                                <img src="../assets/img/produk/<?= $row['foto']; ?>" 
                                     class="preview" 
                                     data-bs-toggle="tooltip" 
                                     data-bs-title="<?= htmlspecialchars($row['nama_produk']) ?>"
                                     onerror="this.src='../assets/img/default-product.jpg'">
                            </td>
                            <td>
                                <div>
                                    <strong><?= htmlspecialchars($row['nama_produk']); ?></strong>
                                </div>
                            </td>
                            <td>
                                <code class="fw-bold"><?= $row['kode']; ?></code>
                            </td>
                            <td>
                                <span class="badge bg-secondary">
                                    <?= $row['nama_kategori'] ?? 'Umum' ?>
                                    <?php if(isset($row['kode_kategori']) && !empty($row['kode_kategori'])): ?>
                                        <br><small><?= $row['kode_kategori']; ?></small>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <strong class="text-success">Rp<?= number_format($row['harga'],0,',','.'); ?></strong>
                            </td>
                            <td>
                                <span class="badge <?= $stok_class ?> badge-stok" data-bs-toggle="tooltip" data-bs-title="Stok: <?= $row['stok'] ?> unit">
                                    <?= $stok_text ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-warning btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalEdit<?= $row['id']; ?>"
                                            title="Edit Produk">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                    <button onclick="hapusProduk(<?= $row['id']; ?>)" 
                                            class="btn btn-danger btn-sm"
                                            title="Hapus Produk">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                    <button class="btn btn-info btn-sm"
                                            onclick="showProductDetail(<?= $row['id']; ?>)"
                                            title="Detail Produk">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Modal Edit Produk -->
                        <div class="modal fade" id="modalEdit<?= $row['id']; ?>" tabindex="-1">
                          <div class="modal-dialog">
                            <div class="modal-content">
                              <form method="POST" enctype="multipart/form-data">
                                <div class="modal-header bg-warning text-white">
                                  <h5 class="modal-title">Edit Produk</h5>
                                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Nama Produk</label>
                                        <input type="text" name="nama_produk" class="form-control" value="<?= $row['nama_produk']; ?>" required>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Kode Produk</label>
                                                <input type="text" name="kode" class="form-control" value="<?= $row['kode']; ?>" readonly style="background-color: #f8f9fa;">
                                                <div class="form-text text-muted"><small><i class="fa fa-lock"></i> Kode tidak dapat diubah</small></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Kategori</label>
                                                <select name="kategori_id" class="form-select" required>
                                                    <?php foreach($kategori_list as $kat): ?>
                                                        <option value="<?= $kat['id'] ?>" <?= $row['kategori_id'] == $kat['id'] ? 'selected' : '' ?>>
                                                            <?= $kat['nama_kategori'] ?>
                                                            <?php if(isset($kat['kode'])): ?>
                                                                (<?= $kat['kode'] ?>)
                                                            <?php endif; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Harga</label>
                                                <input type="number" name="harga" class="form-control" value="<?= $row['harga']; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Stok</label>
                                                <input type="number" name="stok" class="form-control" value="<?= $row['stok']; ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Foto (opsional)</label>
                                        <input type="file" name="foto" class="form-control" accept="image/*">
                                        <div class="form-text">Kosongkan jika tidak ingin mengubah foto</div>
                                    </div>
                                    <div class="text-center">
                                        <img src="../assets/img/produk/<?= $row['foto']; ?>" 
                                             class="img-thumbnail" 
                                             style="max-height: 100px;"
                                             onerror="this.src='../assets/img/default-product.jpg'">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                  <button type="submit" name="edit" class="btn btn-warning">Update Produk</button>
                                </div>
                              </form>
                            </div>
                          </div>
                        </div>
                        <?php 
                            endwhile;
                        } else {
                        ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <i class="fa fa-box fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Tidak ada produk ditemukan</h5>
                                <p class="text-muted">Coba ubah filter pencarian atau tambah produk baru</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
                                    <i class="fa fa-plus"></i> Tambah Produk Pertama
                                </button>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="pagination-container">
                <div class="text-muted">
                    Menampilkan <?= count($products_data) ?> dari <?= $total_produk_all ?> produk
                </div>
                <nav>
                    <ul class="pagination mb-0">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&kategori=<?= $kategori_filter ?>&stok=<?= $stok_filter ?>">
                                <i class="fa fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&kategori=<?= $kategori_filter ?>&stok=<?= $stok_filter ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&kategori=<?= $kategori_filter ?>&stok=<?= $stok_filter ?>">
                                <i class="fa fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>

        <!-- Tab Kategori -->
        <div class="tab-pane fade" id="kategori" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">Kelola Kategori Produk</h4>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahKategori">
                    <i class="fa fa-plus"></i> Tambah Kategori
                </button>
            </div>

            <div class="table-container">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th width="100">Kode Kategori</th>
                            <th>Nama Kategori</th>
                            <th width="120" class="text-center">Jumlah Produk</th>
                            <th width="150" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $kategori_display = mysqli_query($conn, "SELECT k.*, COUNT(p.id) as jumlah_produk 
                                                                FROM kategori k 
                                                                LEFT JOIN produk p ON k.id = p.kategori_id 
                                                                GROUP BY k.id 
                                                                ORDER BY k.nama_kategori");
                        if(mysqli_num_rows($kategori_display) > 0) {
                            while($kat = mysqli_fetch_assoc($kategori_display)): 
                        ?>
                        <tr>
                            <td>
                                <?php if(isset($kat['kode']) && !empty($kat['kode'])): ?>
                                    <code class="fw-bold text-primary"><?= $kat['kode']; ?></code>
                                <?php else: ?>
                                    <!-- Generate kode jika kosong -->
                                    <code class="fw-bold text-primary">
                                        <?= strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $kat['nama_kategori']), 0, 3)) . '-' . str_pad($kat['id'], 3, '0', STR_PAD_LEFT); ?>
                                    </code>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-primary fs-6"><?= $kat['nama_kategori']; ?></span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-secondary"><?= $kat['jumlah_produk']; ?> produk</span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-warning btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#modalEditKategori<?= $kat['id']; ?>"
                                            title="Edit Kategori">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                    <button onclick="hapusKategori(<?= $kat['id']; ?>, '<?= $kat['nama_kategori']; ?>')" 
                                            class="btn btn-danger btn-sm"
                                            title="Hapus Kategori"
                                            <?= $kat['jumlah_produk'] > 0 ? 'disabled' : '' ?>>
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Modal Edit Kategori -->
                        <div class="modal fade" id="modalEditKategori<?= $kat['id']; ?>" tabindex="-1">
                          <div class="modal-dialog">
                            <div class="modal-content">
                              <form method="POST">
                                <div class="modal-header bg-warning text-white">
                                  <h5 class="modal-title">Edit Kategori</h5>
                                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="id_kategori" value="<?= $kat['id']; ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Kode Kategori</label>
                                        <input type="text" class="form-control" value="<?= $kat['kode'] ?? ''; ?>" readonly style="background-color: #f8f9fa;">
                                        <div class="form-text text-muted"><small>Kode tidak dapat diubah</small></div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Nama Kategori</label>
                                        <input type="text" name="nama_kategori" class="form-control" value="<?= $kat['nama_kategori']; ?>" required>
                                    </div>
                                    <div class="alert alert-info">
                                        <i class="fa fa-info-circle"></i> 
                                        Kategori ini digunakan oleh <?= $kat['jumlah_produk']; ?> produk
                                    </div>
                                </div>
                                <div class="modal-footer">
                                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                  <button type="submit" name="edit_kategori" class="btn btn-warning">Update Kategori</button>
                                </div>
                              </form>
                            </div>
                          </div>
                        </div>
                        <?php 
                            endwhile;
                        } else {
                        ?>
                        <tr>
                            <td colspan="4" class="text-center py-4">
                                <i class="fa fa-tags fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Belum ada kategori</h5>
                                <p class="text-muted">Tambahkan kategori pertama Anda</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahKategori">
                                    <i class="fa fa-plus"></i> Tambah Kategori
                                </button>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detail Produk -->
<div class="modal fade" id="modalDetail" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title">Detail Produk</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="detailContent">
        <!-- Content akan diisi oleh JavaScript -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Tambah Produk -->
<div class="modal fade" id="modalTambah" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">Tambah Produk Baru</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Nama Produk</label>
                <input type="text" name="nama_produk" class="form-control" placeholder="Masukkan nama produk" required>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Kode Produk</label>
                        <input type="text" name="kode" class="form-control" placeholder="Kode otomatis" id="kodeAuto" readonly style="background-color: #f8f9fa;">
                        <div class="form-text text-muted"><small>Kode akan di-generate otomatis berdasarkan kategori</small></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Kategori</label>
                        <select name="kategori_id" class="form-select" required id="kategoriSelectTambah">
                            <?php foreach($kategori_list as $kat): ?>
                                <option value="<?= $kat['id'] ?>">
                                    <?= $kat['nama_kategori'] ?>
                                    <?php if(isset($kat['kode'])): ?>
                                        (<?= $kat['kode'] ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Harga</label>
                        <input type="number" name="harga" class="form-control" placeholder="0" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Stok</label>
                        <input type="number" name="stok" class="form-control" placeholder="0" required>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Foto Produk</label>
                <input type="file" name="foto" class="form-control" accept="image/*">
                <div class="form-text">Upload foto produk (JPG, PNG, maksimal 2MB). Kosongkan untuk menggunakan foto default.</div>
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" name="tambah" class="btn btn-primary">Simpan Produk</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Tambah Kategori -->
<div class="modal fade" id="modalTambahKategori" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">Tambah Kategori Baru</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">Nama Kategori</label>
                <input type="text" name="nama_kategori" class="form-control" placeholder="Masukkan nama kategori" required>
            </div>
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> 
                Kode kategori akan di-generate otomatis dari 3 huruf pertama nama kategori
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" name="tambah_kategori" class="btn btn-primary">Simpan Kategori</button>
        </div>
      </form>
    </div>
  </div>
</div>

<footer id="footer">
    &copy; <?= date('Y'); ?> Kasir Computer — Developed by Abyan
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// === Inisialisasi tooltip ===
const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

// === Generate kode otomatis berdasarkan kategori ===
function generateKode(kategoriId, kategoriNama) {
    // Ambil prefix dari 3 huruf pertama kategori (hanya huruf)
    let prefix = kategoriNama.replace(/[^A-Za-z]/g, '').substring(0, 3).toUpperCase();
    
    // Jika kurang dari 3 karakter, tambah X
    if (prefix.length < 3) {
        prefix = (prefix + 'XXX').substring(0, 3);
    }
    
    // Kirim AJAX request untuk mendapatkan nomor urut berikutnya
    return new Promise((resolve) => {
        fetch(`get_next_kode.php?kategori_id=${kategoriId}&prefix=${prefix}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resolve(data.kode);
                } else {
                    // Fallback: gunakan timestamp
                    const timestamp = Date.now().toString().slice(-3);
                    resolve(`${prefix}-${timestamp}`);
                }
            })
            .catch(() => {
                // Fallback jika AJAX gagal
                const timestamp = Date.now().toString().slice(-3);
                resolve(`${prefix}-${timestamp}`);
            });
    });
}

// === Setup kode otomatis di modal tambah ===
async function initKodeAutoGenerate() {
    const kodeInputTambah = document.querySelector('#modalTambah input[name="kode"]');
    const kategoriSelectTambah = document.querySelector('#modalTambah select[name="kategori_id"]');
    
    if (kodeInputTambah && kategoriSelectTambah) {
        // Set default value untuk pertama kali
        const firstOption = kategoriSelectTambah.options[0];
        if (firstOption) {
            const optionText = firstOption.textContent;
            // Ambil nama kategori tanpa kode dalam kurung
            const kategoriNama = optionText.split(' (')[0].trim();
            const generatedKode = await generateKode(firstOption.value, kategoriNama);
            kodeInputTambah.value = generatedKode;
        }
        
        // Event listener untuk perubahan kategori
        kategoriSelectTambah.addEventListener('change', async function() {
            const selectedOption = this.options[this.selectedIndex];
            const optionText = selectedOption.textContent;
            // Ambil nama kategori tanpa kode dalam kurung
            const kategoriNama = optionText.split(' (')[0].trim();
            const generatedKode = await generateKode(this.value, kategoriNama);
            kodeInputTambah.value = generatedKode;
        });
    }
}

// === Real-time Filter ===
function setupRealTimeFilter() {
    const searchInput = document.getElementById('searchInput');
    const kategoriSelect = document.getElementById('kategoriSelect');
    const stokSelect = document.getElementById('stokSelect');
    
    let filterTimeout;
    
    function applyFilters() {
        const search = searchInput ? searchInput.value : '';
        const kategori = kategoriSelect ? kategoriSelect.value : '';
        const stok = stokSelect ? stokSelect.value : '';
        
        const params = new URLSearchParams();
        if (search) params.append('search', search);
        if (kategori) params.append('kategori', kategori);
        if (stok) params.append('stok', stok);
        
        window.location.href = 'produk.php?' + params.toString();
    }
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(filterTimeout);
            filterTimeout = setTimeout(applyFilters, 800); // Delay 800ms
        });
        
        // Tambahkan event untuk Enter key
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
    }
    
    if (kategoriSelect) {
        kategoriSelect.addEventListener('change', applyFilters);
    }
    
    if (stokSelect) {
        stokSelect.addEventListener('change', applyFilters);
    }
}

// === Clear Search ===
function clearSearch() {
    window.location.href = 'produk.php';
}

// === Hapus Produk ===
function hapusProduk(id) {
    Swal.fire({
        title: 'Yakin ingin menghapus?',
        text: "Produk akan dihapus permanen dari sistem!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location = "produk.php?hapus=" + id;
        }
    });
}

// === Hapus Kategori ===
function hapusKategori(id, nama) {
    Swal.fire({
        title: 'Yakin ingin menghapus?',
        text: `Kategori "${nama}" akan dihapus permanen!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location = "produk.php?hapus_kategori=" + id;
        }
    });
}

// === Tampilkan Detail Produk ===
function showProductDetail(productId) {
    fetch(`get_product_detail.php?id=${productId}`)
        .then(response => response.json())
        .then(product => {
            if (!product) {
                Swal.fire('Error', 'Produk tidak ditemukan', 'error');
                return;
            }
            
            const stokClass = product.stok == 0 ? 'bg-danger' : (product.stok <= 10 ? 'bg-warning' : 'bg-success');
            const stokText = product.stok == 0 ? 'Stok Habis' : (product.stok <= 10 ? `${product.stok} unit (Menipis)` : `${product.stok} unit`);
            
            document.getElementById('detailContent').innerHTML = `
                <div class="row">
                    <div class="col-md-6 text-center">
                        <img src="../assets/img/produk/${product.foto}" 
                             class="product-image-large"
                             onerror="this.src='../assets/img/default-product.jpg'">
                    </div>
                    <div class="col-md-6">
                        <div class="detail-section">
                            <h4 class="text-primary">${product.nama_produk}</h4>
                            <span class="badge bg-secondary">${product.nama_kategori || 'Umum'}</span>
                        </div>
                        
                        <div class="detail-section">
                            <h6><i class="fa fa-tag text-success"></i> Informasi Harga</h6>
                            <p class="h4 text-success mb-0">Rp${formatNumber(product.harga)}</p>
                        </div>
                        
                        <div class="detail-section">
                            <h6><i class="fa fa-box text-info"></i> Status Stok</h6>
                            <span class="badge ${stokClass} fs-6">${stokText}</span>
                            ${product.stok == 0 ? 
                                '<p class="text-danger small mt-1"><i class="fa fa-exclamation-circle"></i> Stok habis, segera restok</p>' : 
                                (product.stok <= 10 ? 
                                    '<p class="text-warning small mt-1"><i class="fa fa-exclamation-triangle"></i> Stok menipis, perlu restok</p>' : 
                                    '<p class="text-success small mt-1"><i class="fa fa-check-circle"></i> Stok aman</p>')
                            }
                        </div>
                        
                        <div class="detail-section">
                            <h6><i class="fa fa-info-circle text-primary"></i> Informasi Produk</h6>
                            <p class="mb-1"><strong>Kode:</strong> <code class="fw-bold">${product.kode}</code></p>
                            <p class="mb-1"><strong>Kode Kategori:</strong> <code>${product.kode_kategori || 'N/A'}</code></p>
                            <p class="mb-0 text-muted"><small><i class="fa fa-lock"></i> Kode produk tidak dapat diubah</small></p>
                        </div>
                    </div>
                </div>
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('modalDetail'));
            modal.show();
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'Terjadi kesalahan saat memuat detail produk', 'error');
        });
}

// === Format number dengan separator ===
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

// === Notifikasi Sukses dengan Tombol OK ===
<?php if(isset($_GET['success'])): 
    $message = '';
    $icon = 'success';
    $title = 'Berhasil!';
    
    switch($_GET['success']) {
        case 'tambah':
            $message = 'Produk berhasil ditambahkan ke sistem!';
            break;
        case 'edit':
            $message = 'Data produk berhasil diperbarui!';
            break;
        case 'hapus':
            $message = 'Produk berhasil dihapus dari sistem!';
            break;
        case 'kategori_tambah':
            $message = 'Kategori baru berhasil ditambahkan!';
            break;
        case 'kategori_edit':
            $message = 'Kategori berhasil diperbarui!';
            break;
        case 'kategori_hapus':
            $message = 'Kategori berhasil dihapus!';
            break;
    }
?>
Swal.fire({
    icon: '<?= $icon ?>',
    title: '<?= $title ?>',
    text: '<?= $message ?>',
    confirmButtonText: 'OK',
    confirmButtonColor: '#3b82f6',
    timer: 5000,
    timerProgressBar: true,
    willClose: () => {
        const url = new URL(window.location);
        url.searchParams.delete('success');
        window.history.replaceState({}, '', url);
    }
});
<?php endif; ?>

// === Preview image sebelum upload ===
document.addEventListener('DOMContentLoaded', function() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 2 * 1024 * 1024) {
                    Swal.fire({
                        icon: 'error',
                        title: 'File terlalu besar',
                        text: 'Ukuran file maksimal 2MB',
                    });
                    this.value = '';
                    return;
                }
                
                if (!file.type.startsWith('image/')) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Format tidak didukung',
                        text: 'Hanya file gambar yang diizinkan',
                    });
                    this.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.createElement('img');
                    preview.src = e.target.result;
                    preview.className = 'img-thumbnail mt-2';
                    preview.style.maxHeight = '100px';
                    
                    const existingPreview = input.parentNode.querySelector('.image-preview');
                    if (existingPreview) {
                        existingPreview.remove();
                    }
                    
                    preview.className += ' image-preview';
                    input.parentNode.appendChild(preview);
                }
                reader.readAsDataURL(file);
            }
        });
    });
});

// === Mobile sidebar toggle ===
function toggleMobileSidebar() {
    document.getElementById('sidebar').classList.toggle('mobile-open');
}

// === Initialize functions ===
document.addEventListener('DOMContentLoaded', async function() {
    // Inisialisasi kode otomatis
    await initKodeAutoGenerate();
    
    // Setup real-time filter
    setupRealTimeFilter();
    
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