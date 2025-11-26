<?php
include("../config/db.php");
if(isset($_POST['submit'])){
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    mysqli_query($conn,"INSERT INTO users (username,password,role) VALUES ('$username','$password','$role')");
    header("Location: users.php");
    exit();
}
?>

<h2>Tambah User</h2>
<form method="POST">
<input type="text" name="username" placeholder="Username" required><br>
<input type="password" name="password" placeholder="Password" required><br>
<select name="role" required>
<option value="admin">Admin</option>
<option value="kasir">Kasir</option>
</select><br>
<button type="submit" name="submit">Simpan</button>
</form>
