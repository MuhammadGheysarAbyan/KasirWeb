<?php
session_start();
if(!isset($_SESSION['id']) || $_SESSION['role'] != 'kasir'){
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

// Ambil data transaksi (tanpa filter kasir_id agar bisa print semua transaksi kasir ini)
$transaksi_query = mysqli_query($conn, "
    SELECT t.*, u.username as kasir 
    FROM transaksi t 
    LEFT JOIN users u ON t.kasir_id = u.id 
    WHERE t.id = $transaksi_id
");

if(!$transaksi_query || mysqli_num_rows($transaksi_query) == 0) {
    echo json_encode(['success' => false, 'message' => 'Transaksi tidak ditemukan (ID: ' . $transaksi_id . ')']);
    exit();
}

$transaksi = mysqli_fetch_assoc($transaksi_query);

// Ambil detail transaksi dengan subtotal
$detail_query = mysqli_query($conn, "
    SELECT dt.*, p.nama_produk, (dt.qty * dt.harga) as subtotal
    FROM detail_transaksi dt 
    JOIN produk p ON dt.produk_id = p.id 
    WHERE dt.transaksi_id = $transaksi_id
");

$items = [];
if($detail_query) {
    while($row = mysqli_fetch_assoc($detail_query)) {
        $items[] = [
            'nama_produk' => $row['nama_produk'],
            'qty' => (int)$row['qty'],
            'harga' => (int)$row['harga'],
            'subtotal' => (int)$row['subtotal']
        ];
    }
}

// Jika tidak ada items, coba query alternatif
if(empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Detail transaksi kosong untuk ID: ' . $transaksi_id]);
    exit();
}

// Karena database mungkin tidak menyimpan uang bayar, kita simulasikan
$uang_bayar = $transaksi['total'];
$kembalian = 0;

echo json_encode([
    'success' => true,
    'struk' => [
        'kode_transaksi' => $transaksi['kode_transaksi'] ?? 'TRX-' . $transaksi_id,
        'kasir' => $transaksi['kasir'] ?? 'Kasir',
        'tanggal' => $transaksi['tanggal'] ?? date('Y-m-d'),
        'waktu' => isset($transaksi['waktu']) ? date('H:i', strtotime($transaksi['waktu'])) : date('H:i'),
        'items' => $items,
        'total' => (int)$transaksi['total'],
        'uang_bayar' => (int)$uang_bayar,
        'kembalian' => (int)$kembalian
    ]
]);
?>
