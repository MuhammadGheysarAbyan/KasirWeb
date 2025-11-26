<?php
session_start();
if(!isset($_SESSION['id']) || $_SESSION['role'] != 'kasir'){
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include("../config/db.php");

if(isset($_GET['id'])) {
    $transaksi_id = $_GET['id'];
    
    // Get transaction data
    $transaksi_query = mysqli_query($conn, "
        SELECT t.*, COUNT(dt.id) as jumlah_item
        FROM transaksi t
        LEFT JOIN detail_transaksi dt ON t.id = dt.transaksi_id
        WHERE t.id = $transaksi_id AND t.kasir_id = {$_SESSION['id']}
        GROUP BY t.id
    ");
    
    if(mysqli_num_rows($transaksi_query) > 0) {
        $transaksi = mysqli_fetch_assoc($transaksi_query);
        
        // Get transaction details
        $detail_query = mysqli_query($conn, "
            SELECT dt.*, p.nama_produk
            FROM detail_transaksi dt
            JOIN produk p ON dt.produk_id = p.id
            WHERE dt.transaksi_id = $transaksi_id
        ");
        
        $detail = [];
        while($row = mysqli_fetch_assoc($detail_query)) {
            $detail[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'transaksi' => $transaksi,
            'detail' => $detail
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Transaksi tidak ditemukan']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID transaksi tidak valid']);
}
?>