<?php
include("../config/db.php");
$id = $_GET['id'];
$data = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM produk WHERE id='$id'"));

if(isset($_POST['submit'])){
    $nama = $_POST['nama_produk'];
    $kategori = $_POST['kategori'];
    $harga = $_POST['harga'];
    $stok = $_POST['stok'];

    mysqli_query($conn,"UPDATE produk SET nama_produk='$nama', kategori='$kategori', harga='$harga', stok='$stok' WHERE id='$id'");
    header("Location: produk.php");
    exit();
}
?>

<h2>Edit Produk</h2>
<form method="POST">
<input type="text" name="nama_produk" value="<?= $data['nama_produk'] ?>" required><br>
<input type="text" name="kategori" value="<?= $data['kategori'] ?>" required><br>
<input type="number" name="harga" value="<?= $data['harga'] ?>" required><br>
<input type="number" name="stok" value="<?= $data['stok'] ?>" required><br>
<button type="submit" name="submit">Update</button>
</form>
