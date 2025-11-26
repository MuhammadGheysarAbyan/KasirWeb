<?php
include("../config/db.php");
if(isset($_POST['submit'])){
    $nama = $_POST['nama_produk'];
    $kategori = $_POST['kategori'];
    $harga = $_POST['harga'];
    $stok = $_POST['stok'];

    mysqli_query($conn,"INSERT INTO produk (nama_produk,kategori,harga,stok) VALUES ('$nama','$kategori','$harga','$stok')");
    header("Location: produk.php");
    exit();
}
?>

<h2>Tambah Produk</h2>
<form method="POST">
<input type="text" name="nama_produk" placeholder="Nama Produk" required><br>
<input type="text" name="kategori" placeholder="Kategori" required><br>
<input type="number" name="harga" placeholder="Harga" required><br>
<input type="number" name="stok" placeholder="Stok" required><br>
<button type="submit" name="submit">Simpan</button>
</form>
