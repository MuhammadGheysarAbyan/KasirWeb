<?php
include("../config/db.php");
$result = mysqli_query($conn, "SELECT id, nama_produk, foto FROM produk LIMIT 10");
while($row = mysqli_fetch_assoc($result)) {
    echo "ID: " . $row['id'] . " | Nama: " . $row['nama_produk'] . " | Foto: " . $row['foto'] . "\n";
}
?>
