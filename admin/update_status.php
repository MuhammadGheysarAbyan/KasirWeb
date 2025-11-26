<?php
session_start();
if(!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../auth/login.php");
    exit();
}

include("../config/db.php");

if(isset($_GET['id']) && isset($_GET['status'])){
    $id = (int)$_GET['id'];
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    
    // Validasi status
    $allowed_status = ['selesai', 'batal'];
    if(in_array($status, $allowed_status)){
        mysqli_query($conn, "UPDATE transaksi SET status='$status' WHERE id=$id");
        header("Location: transaksi.php?success=status_updated");
    } else {
        header("Location: transaksi.php?error=invalid_status");
    }
    exit();
} else {
    header("Location: transaksi.php");
    exit();
}
?>