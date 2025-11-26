<?php
include("../config/db.php");
$id = $_GET['id'];
$data = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM users WHERE id='$id'"));

if(isset($_POST['submit'])){
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    mysqli_query($conn,"UPDATE users SET username='$username', password='$password', role='$role' WHERE id='$id'");
    header("Location: users.php");
    exit();
}
?>

<h2>Edit User</h2>
<form method="POST">
<input type="text" name="username" value="<?= $data['username'] ?>" required><br>
<input type="password" name="password" value="<?= $data['password'] ?>" required><br>
<select name="role" required>
<option value="admin" <?= $data['role']=='admin'?'selected':'' ?>>Admin</option>
<option value="kasir" <?= $data['role']=='kasir'?'selected':'' ?>>Kasir</option>
</select><br>
<button type="submit" name="submit">Update</button>
</form>
