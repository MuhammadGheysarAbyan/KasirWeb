<?php
session_start();
if(!isset($_SESSION['id']) || $_SESSION['role'] != 'admin'){
    header("Location: ../auth/login.php");
    exit();
}

include("../config/db.php");

if(isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    
    $query = "SELECT p.*, k.nama_kategori 
              FROM produk p 
              LEFT JOIN kategori k ON p.kategori_id = k.id 
              WHERE p.id = $product_id";
    
    $result = mysqli_query($conn, $query);
    
    if(mysqli_num_rows($result) > 0) {
        $product = mysqli_fetch_assoc($result);
        header('Content-Type: application/json');
        echo json_encode($product);
    } else {
        echo json_encode(null);
    }
} else {
    echo json_encode(null);
}
?>