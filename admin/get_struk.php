<?php
session_start();
if(!isset($_SESSION['id'])){
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include("../config/db.php");

header('Content-Type: application/json');

$transaksi_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($transaksi_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid transaction ID']);
    exit();
}

// Ambil data transaksi
$transaksi_query = mysqli_query($conn, "
    SELECT t.*, u.username as kasir 
    FROM transaksi t 
    JOIN users u ON t.kasir_id = u.id 
    WHERE t.id = $transaksi_id
");

if(mysqli_num_rows($transaksi_query) == 0) {
    echo json_encode(['success' => false, 'message' => 'Transaction not found']);
    exit();
}

$transaksi = mysqli_fetch_assoc($transaksi_query);

// Ambil detail transaksi
$detail_query = mysqli_query($conn, "
    SELECT dt.*, p.nama_produk 
    FROM detail_transaksi dt 
    JOIN produk p ON dt.produk_id = p.id 
    WHERE dt.transaksi_id = $transaksi_id
");

$items = [];
while($row = mysqli_fetch_assoc($detail_query)) {
    $items[] = $row;
}

// Simulasikan uang bayar dan kembalian (dalam real app, ini disimpan di database)
$uang_bayar = $transaksi['total'] + rand(0, 50000); // Contoh: uang bayar lebih besar dari total
$kembalian = $uang_bayar - $transaksi['total'];

echo json_encode([
    'success' => true,
    'struk' => [
        'kode_transaksi' => $transaksi['kode_transaksi'],
        'kasir' => $transaksi['kasir'],
        'tanggal' => $transaksi['tanggal'],
        'waktu' => date('H:i', strtotime($transaksi['waktu'])),
        'items' => $items,
        'total' => $transaksi['total'],
        'uang_bayar' => $uang_bayar,
        'kembalian' => $kembalian
    ]
]);
?>