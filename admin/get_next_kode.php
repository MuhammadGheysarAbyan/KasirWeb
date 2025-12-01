<?php
session_start();
if(!isset($_SESSION['id']) || $_SESSION['role'] != 'admin'){
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include("../config/db.php");

header('Content-Type: application/json');

$kategori_id = isset($_GET['kategori_id']) ? (int)$_GET['kategori_id'] : 0;
$prefix = isset($_GET['prefix']) ? strtoupper($_GET['prefix']) : 'PROD';

if($kategori_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid category']);
    exit();
}

// Cek apakah kolom kode ada di tabel kategori
$table_check = mysqli_query($conn, "SHOW COLUMNS FROM kategori LIKE 'kode'");
$has_kode_column = (mysqli_num_rows($table_check) > 0);

// Ambil nama kategori
$kategori_result = mysqli_query($conn, "SELECT nama_kategori FROM kategori WHERE id = $kategori_id");
if(mysqli_num_rows($kategori_result) == 0) {
    echo json_encode(['success' => false, 'message' => 'Category not found']);
    exit();
}

$kategori_row = mysqli_fetch_assoc($kategori_result);
$nama_kategori = $kategori_row['nama_kategori'];

// Generate prefix dari nama kategori (3 huruf pertama)
$prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $nama_kategori), 0, 3));
if(strlen($prefix) < 3) {
    $prefix = str_pad($prefix, 3, 'X', STR_PAD_RIGHT);
}

// Hitung berapa banyak produk dalam kategori ini
$count_query = "SELECT COUNT(*) as total FROM produk WHERE kategori_id = $kategori_id";
$count_result = mysqli_query($conn, $count_query);
$count_row = mysqli_fetch_assoc($count_result);
$total_produk_kategori = $count_row['total'];

// Cari kode terakhir untuk kategori ini
$last_code_query = mysqli_query($conn, "
    SELECT kode FROM produk 
    WHERE kategori_id = $kategori_id 
    AND kode LIKE '$prefix%'
    ORDER BY id DESC 
    LIMIT 1
");

$next_number = $total_produk_kategori + 1;

if(mysqli_num_rows($last_code_query) > 0) {
    $last_code = mysqli_fetch_assoc($last_code_query)['kode'];
    // Extract number from last code (format: PREFIX-001)
    preg_match('/-?(\d+)$/', $last_code, $matches);
    if(!empty($matches)) {
        $last_number = intval($matches[1]);
        $next_number = $last_number + 1;
    }
}

// Format kode: PREFIX-NOMOR (3 digit)
$kode = $prefix . '-' . str_pad($next_number, 3, '0', STR_PAD_LEFT);

// Double check kode tidak duplikat
$cek_kode = mysqli_query($conn, "SELECT id FROM produk WHERE kode='$kode'");
if(mysqli_num_rows($cek_kode) > 0) {
    $kode = $prefix . '-' . str_pad($next_number, 3, '0', STR_PAD_LEFT) . rand(1, 9);
}

echo json_encode([
    'success' => true,
    'kode' => $kode,
    'prefix' => $prefix,
    'next_number' => $next_number
]);
?>