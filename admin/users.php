<?php
session_start();
if(!isset($_SESSION['id']) || $_SESSION['role'] != 'admin'){
    header("Location: ../auth/login.php");
    exit();
}
include("../config/db.php");

// Tambah user
if(isset($_POST['tambah'])){
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $nama = mysqli_real_escape_string($conn, $_POST['nama'] ?? '');
    $no_telp = mysqli_real_escape_string($conn, $_POST['no_telp'] ?? '');
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat'] ?? '');
    $shift = mysqli_real_escape_string($conn, $_POST['shift'] ?? '');
    
    // Generate user code
    $prefix = $role == 'admin' ? 'ADM' : 'KSR';
    $last_user = mysqli_query($conn, "SELECT user_code FROM users WHERE user_code LIKE '$prefix%' ORDER BY id DESC LIMIT 1");
    $next_number = 1;
    
    if(mysqli_num_rows($last_user) > 0) {
        $last_code = mysqli_fetch_assoc($last_user)['user_code'];
        preg_match('/\d+$/', $last_code, $matches);
        if(!empty($matches)) {
            $next_number = intval($matches[0]) + 1;
        }
    }
    
    $user_code = $prefix . str_pad($next_number, 3, '0', STR_PAD_LEFT);
    
    mysqli_query($conn, "INSERT INTO users (user_code, username, password, role, email, nama, no_telp, alamat, shift) 
                        VALUES ('$user_code','$username','$password','$role','$email','$nama','$no_telp','$alamat','$shift')");
    header("Location: users.php?success=tambah");
    exit();
}

// Edit user
if(isset($_POST['edit'])){
    $id = $_POST['id'];
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $role = $_POST['role'];
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $nama = mysqli_real_escape_string($conn, $_POST['nama'] ?? '');
    $no_telp = mysqli_real_escape_string($conn, $_POST['no_telp'] ?? '');
    $alamat = mysqli_real_escape_string($conn, $_POST['alamat'] ?? '');
    $shift = mysqli_real_escape_string($conn, $_POST['shift'] ?? '');
    
    // Jika password diisi, update password juga
    if(!empty($_POST['password'])){
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        mysqli_query($conn, "UPDATE users SET username='$username', password='$password', role='$role', email='$email', 
                        nama='$nama', no_telp='$no_telp', alamat='$alamat', shift='$shift' WHERE id=$id");
    } else {
        mysqli_query($conn, "UPDATE users SET username='$username', role='$role', email='$email', 
                        nama='$nama', no_telp='$no_telp', alamat='$alamat', shift='$shift' WHERE id=$id");
    }
    
    header("Location: users.php?success=edit");
    exit();
}

// Reset password
if(isset($_POST['reset_password'])){
    $id = $_POST['id'];
    $default_password = password_hash('123456', PASSWORD_DEFAULT); // Password default
    mysqli_query($conn, "UPDATE users SET password='$default_password' WHERE id=$id");
    header("Location: users.php?success=reset");
    exit();
}

// Hapus user
if(isset($_GET['hapus'])){
    $id = $_GET['hapus'];
    // Cek apakah user sedang login
    if($id == $_SESSION['id']){
        header("Location: users.php?error=self_delete");
        exit();
    }
    mysqli_query($conn, "DELETE FROM users WHERE id=$id");
    header("Location: users.php?success=hapus");
    exit();
}

// Ambil data users dengan statistik
$users = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");
$total_users = mysqli_num_rows($users);
$admin_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role='admin'"))['count'];
$kasir_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role='kasir'"))['count'];

// Get active kasir count
$active_kasir = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(DISTINCT kasir_id) as count FROM transaksi WHERE DATE(tanggal) = CURDATE()"))['count'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola User</title>
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

/* Stats Container - SAMA PERSIS */
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

/* Card Styling - SAMA PERSIS */
.card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    margin-bottom: 25px;
}

/* Search Filter Container - SAMA DENGAN LAINNYA */
.search-container {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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

/* Action Buttons - SAMA PERSIS */
.action-buttons {
    display: flex;
    gap: 8px;
    justify-content: center;
}

/* User Avatar */
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

/* Modal Styling - SAMA PERSIS */
.modal-header {
    border-bottom: 2px solid #e5e7eb;
}
.modal-footer {
    border-top: 2px solid #e5e7eb;
}

/* User Details */
.last-login {
    font-size: 12px;
    color: #6b7280;
}

.user-info-row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.user-details {
    flex: 1;
}

.shift-badge {
    font-size: 11px;
    padding: 4px 8px;
    border-radius: 15px;
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
    
    <a href="transaksi.php">
        <i class="fa fa-exchange-alt"></i>
        <span class="nav-text">Data Transaksi</span>
    </a>
    
    <a href="users.php" class="active">
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
        <div class="title">Kelola User</div>
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
    <!-- Statistik User -->
    <div class="stats-container">
        <div class="stat-card">
            <i class="fa fa-users text-primary"></i>
            <div class="stat-number"><?= $total_users ?></div>
            <div class="stat-label">Total User</div>
        </div>
        <div class="stat-card">
            <i class="fa fa-user-shield text-success"></i>
            <div class="stat-number"><?= $admin_count ?></div>
            <div class="stat-label">Admin</div>
        </div>
        <div class="stat-card">
            <i class="fa fa-cash-register text-info"></i>
            <div class="stat-number"><?= $kasir_count ?></div>
            <div class="stat-label">Kasir</div>
        </div>
        <div class="stat-card">
            <i class="fa fa-user-check text-warning"></i>
            <div class="stat-number"><?= $active_kasir ?></div>
            <div class="stat-label">Kasir Aktif Hari Ini</div>
        </div>
    </div>

    <!-- Search dan Action -->
    <div class="search-container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <input type="text" id="searchInput" class="form-control" placeholder="Cari user...">
                    <button class="btn btn-outline-secondary" type="button">
                        <i class="fa fa-search"></i>
                    </button>
                </div>
            </div>
            <div class="col-md-6 text-end">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
                    <i class="fa fa-plus"></i> Tambah User Baru
                </button>
            </div>
        </div>
    </div>
    
    <!-- Tabel User - DIUBAH MENJADI SAMA DENGAN TRANSAKSI.PHP -->
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0"><i class="fa fa-users me-2 text-primary"></i>Daftar User</h4>
                <span class="text-muted">Total: <strong><?= $total_users; ?></strong> user</span>
            </div>
            
            <table class="table table-hover align-middle" id="usersTable">
                <thead>
                    <tr>
                        <th width="100">User Code</th>
                        <th width="60">Avatar</th>
                        <th width="200">User Details</th>
                        <th width="80">Role</th>
                        <th width="80">Shift</th>
                        <th width="100">Status</th>
                        <th width="150" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $users_data = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");
                    while($row = mysqli_fetch_assoc($users_data)): 
                        $is_current_user = $row['id'] == $_SESSION['id'];
                        $role_badge = $row['role'] == 'admin' ? 'bg-success' : 'bg-info';
                        $status_badge = $is_current_user ? 'bg-primary' : 'bg-secondary';
                        $status_text = $is_current_user ? 'Online' : 'Offline';
                        $shift_badge = $row['shift'] ? 'bg-warning' : 'bg-secondary';
                        $shift_text = $row['shift'] ? ucfirst($row['shift']) : 'Belum diatur';
                    ?>
                    <tr>
                        <td>
                            <strong><?= $row['user_code']; ?></strong>
                        </td>
                        <td>
                            <div class="user-avatar" style="width: 35px; height: 35px; font-size: 14px;">
                                <?= strtoupper(substr($row['username'], 0, 1)) ?>
                            </div>
                        </td>
                        <td>
                            <div class="user-details">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div style="min-width: 0; flex: 1;">
                                        <strong class="text-truncate d-block" style="max-width: 120px;" title="<?= htmlspecialchars($row['username']) ?>">
                                            <?= htmlspecialchars($row['username']); ?>
                                        </strong>
                                        <?php if(!empty($row['nama'])): ?>
                                            <div class="text-muted small text-truncate" style="max-width: 120px;" title="<?= htmlspecialchars($row['nama']) ?>">
                                                <?= htmlspecialchars($row['nama']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if(!empty($row['email'])): ?>
                                    <div class="text-muted small mt-1 text-truncate" style="max-width: 180px;" title="<?= htmlspecialchars($row['email']) ?>">
                                        <i class="fa fa-envelope"></i> <?= htmlspecialchars($row['email']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if(!empty($row['no_telp'])): ?>
                                    <div class="text-muted small text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($row['no_telp']) ?>">
                                        <i class="fa fa-phone"></i> <?= htmlspecialchars($row['no_telp']) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="last-login mt-1 text-truncate" style="max-width: 180px;">
                                    <i class="fa fa-clock"></i> Terdaftar: <?= date('d M Y', strtotime($row['created_at'] ?? 'now')) ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge <?= $role_badge ?>" style="font-size: 11px;">
                                <i class="fa fa-<?= $row['role'] == 'admin' ? 'user-shield' : 'cash-register' ?> me-1"></i>
                                <?= ucfirst($row['role']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $shift_badge ?> shift-badge" style="font-size: 11px;">
                                <i class="fa fa-clock me-1"></i>
                                <?= $shift_text ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $status_badge ?>" style="font-size: 11px;">
                                <i class="fa fa-<?= $is_current_user ? 'circle' : 'circle' ?> me-1"></i>
                                <?= $status_text ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <!-- Tombol Edit -->
                                <button class="btn btn-warning btn-sm" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#modalEdit<?= $row['id']; ?>"
                                        title="Edit User">
                                    <i class="fa fa-edit"></i>
                                </button>
                                
                                <!-- Tombol Reset Password -->
                                <button class="btn btn-info btn-sm"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#modalReset<?= $row['id']; ?>"
                                        title="Reset Password">
                                    <i class="fa fa-key"></i>
                                </button>
                                
                                <!-- Tombol Hapus -->
                                <button onclick="hapusUser(<?= $row['id']; ?>, <?= $is_current_user ? 'true' : 'false' ?>)" 
                                        class="btn btn-danger btn-sm"
                                        title="Hapus User"
                                        <?= $is_current_user ? 'disabled' : '' ?>>
                                    <i class="fa fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>

                    <!-- Modal Edit -->
                    <div class="modal fade" id="modalEdit<?= $row['id']; ?>" tabindex="-1">
                      <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                          <form method="POST">
                            <div class="modal-header bg-warning text-white">
                              <h5 class="modal-title">Edit User</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Username *</label>
                                            <input type="text" name="username" class="form-control" value="<?= $row['username']; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Role *</label>
                                            <select name="role" class="form-select" required>
                                                <option value="admin" <?= $row['role']=='admin'?'selected':''; ?>>Admin</option>
                                                <option value="kasir" <?= $row['role']=='kasir'?'selected':''; ?>>Kasir</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Nama Lengkap</label>
                                            <input type="text" name="nama" class="form-control" value="<?= $row['nama'] ?? '' ?>" placeholder="Nama lengkap...">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" name="email" class="form-control" value="<?= $row['email'] ?? '' ?>" placeholder="email@example.com">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">No. Telepon</label>
                                            <input type="text" name="no_telp" class="form-control" value="<?= $row['no_telp'] ?? '' ?>" placeholder="08...">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Shift</label>
                                            <select name="shift" class="form-select">
                                                <option value="">Pilih Shift</option>
                                                <option value="pagi" <?= ($row['shift'] ?? '') == 'pagi' ? 'selected' : '' ?>>Pagi</option>
                                                <option value="siang" <?= ($row['shift'] ?? '') == 'siang' ? 'selected' : '' ?>>Siang</option>
                                                <option value="malam" <?= ($row['shift'] ?? '') == 'malam' ? 'selected' : '' ?>>Malam</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Alamat</label>
                                    <textarea name="alamat" class="form-control" rows="2" placeholder="Alamat lengkap..."><?= $row['alamat'] ?? '' ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Password Baru (opsional)</label>
                                    <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah">
                                    <div class="form-text">Minimal 6 karakter</div>
                                </div>
                                <div class="alert alert-info">
                                    <i class="fa fa-info-circle"></i> 
                                    User Code: <strong><?= $row['user_code'] ?></strong> (Tidak dapat diubah)
                                </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                              <button type="submit" name="edit" class="btn btn-warning">Update User</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>

                    <!-- Modal Reset Password -->
                    <div class="modal fade" id="modalReset<?= $row['id']; ?>" tabindex="-1">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <form method="POST">
                            <div class="modal-header bg-info text-white">
                              <h5 class="modal-title">Reset Password</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="id" value="<?= $row['id']; ?>">
                                <div class="text-center mb-4">
                                    <i class="fa fa-key fa-3x text-info mb-3"></i>
                                    <h5>Reset Password User</h5>
                                    <p class="text-muted">Password akan direset ke: <strong>123456</strong></p>
                                    <div class="alert alert-warning">
                                        <i class="fa fa-exclamation-triangle"></i>
                                        User harus mengganti password setelah login pertama kali
                                    </div>
                                </div>
                                <div class="user-info text-center">
                                    <div class="user-avatar d-inline-flex mb-2" style="width: 40px; height: 40px; font-size: 16px;">
                                        <?= strtoupper(substr($row['username'], 0, 1)) ?>
                                    </div>
                                    <h6><?= htmlspecialchars($row['username']) ?></h6>
                                    <span class="badge <?= $role_badge ?>"><?= ucfirst($row['role']) ?></span>
                                    <div class="text-muted small mt-1"><?= $row['user_code'] ?></div>
                                </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                              <button type="submit" name="reset_password" class="btn btn-info">Reset Password</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                    <?php endwhile; ?>
                    
                    <?php if($total_users == 0): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <i class="fa fa-users fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Belum ada user terdaftar</h5>
                            <p class="text-muted">Mulai dengan menambahkan user pertama</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
                                <i class="fa fa-plus"></i> Tambah User Pertama
                            </button>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Tambah -->
    <div class="modal fade" id="modalTambah" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <form method="POST">
            <div class="modal-header bg-primary text-white">
              <h5 class="modal-title">Tambah User Baru</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" name="username" class="form-control" placeholder="Masukkan username" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Role *</label>
                            <select name="role" class="form-select" required>
                                <option value="">Pilih Role</option>
                                <option value="admin">Admin</option>
                                <option value="kasir">Kasir</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama" class="form-control" placeholder="Nama lengkap user">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="email@example.com">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">No. Telepon</label>
                            <input type="text" name="no_telp" class="form-control" placeholder="08...">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Shift</label>
                            <select name="shift" class="form-select">
                                <option value="">Pilih Shift</option>
                                <option value="pagi">Pagi</option>
                                <option value="siang">Siang</option>
                                <option value="malam">Malam</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Alamat</label>
                    <textarea name="alamat" class="form-control" rows="2" placeholder="Alamat lengkap..."></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required minlength="6">
                </div>
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i> 
                    User Code akan digenerate otomatis oleh sistem
                </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
              <button type="submit" name="tambah" class="btn btn-primary">Tambah User</button>
            </div>
          </form>
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
// === Search Functionality ===
document.getElementById('searchInput').addEventListener('keyup', function() {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#usersTable tbody tr');
    
    rows.forEach(row => {
        const username = row.cells[2].textContent.toLowerCase();
        const email = row.cells[2].querySelector('.fa-envelope') ? 
                     row.cells[2].querySelector('.fa-envelope').parentNode.textContent.toLowerCase() : '';
        const namaLengkap = row.cells[2].querySelector('.text-muted.small') ? 
                          row.cells[2].querySelector('.text-muted.small').textContent.toLowerCase() : '';
        
        if (username.includes(filter) || email.includes(filter) || namaLengkap.includes(filter)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// === Hapus User ===
function hapusUser(id, isCurrentUser) {
    if (isCurrentUser) {
        Swal.fire({
            title: 'Tidak Dapat Dihapus',
            text: "Anda tidak dapat menghapus akun sendiri!",
            icon: 'error',
            confirmButtonColor: '#d33',
            confirmButtonText: 'Mengerti'
        });
        return;
    }
    
    Swal.fire({
        title: 'Yakin ingin menghapus?',
        text: "User akan dihapus permanen dari sistem!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location = "users.php?hapus=" + id;
        }
    });
}

// === Notifikasi Sukses ===
<?php if(isset($_GET['success'])): 
    $message = '';
    $icon = 'success';
    switch($_GET['success']) {
        case 'tambah':
            $message = 'User berhasil ditambahkan!';
            break;
        case 'edit':
            $message = 'User berhasil diperbarui!';
            break;
        case 'hapus':
            $message = 'User berhasil dihapus!';
            break;
        case 'reset':
            $message = 'Password berhasil direset!';
            break;
    }
?>
Swal.fire({
    icon: '<?= $icon ?>',
    title: 'Berhasil!',
    text: '<?= $message ?>',
    timer: 3000,
    showConfirmButton: false
}).then(() => {
    // Remove success parameter from URL
    window.history.replaceState({}, document.title, window.location.pathname + window.location.search.replace(/[?&]success=[^&]+/, '').replace(/^&/, '?'));
});
<?php endif; ?>

<?php if(isset($_GET['error']) && $_GET['error'] == 'self_delete'): ?>
Swal.fire({
    icon: 'error',
    title: 'Gagal!',
    text: 'Tidak dapat menghapus akun sendiri',
    confirmButtonColor: '#d33'
});
<?php endif; ?>

// === Mobile sidebar toggle ===
function toggleMobileSidebar() {
    document.getElementById('sidebar').classList.toggle('mobile-open');
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