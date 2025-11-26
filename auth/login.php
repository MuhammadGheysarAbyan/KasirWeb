<?php
session_start();
include("../config/db.php");

$error = '';

if(isset($_POST['login'])){
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    $query = mysqli_query($conn, "SELECT * FROM users WHERE username='$username' AND password='$password' AND role='$role'");
    if(mysqli_num_rows($query) == 1){
        $user = mysqli_fetch_assoc($query);
        $_SESSION['id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        if($user['role'] == 'admin'){
            header("Location: ../admin/dashboard.php");
        } elseif($user['role'] == 'kasir'){
            header("Location: ../kasir/dashboard.php");
        } else {
            $error = "Role tidak dikenali!";
        }
        exit();
    } else {
        $error = "Username, password, atau role salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Kasir</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body {
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #1e293b 0%, #3b82f6 100%);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
}
.container-login {
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.25);
    overflow: hidden;
    display: flex;
    max-width: 850px;
    width: 100%;
}
.left-side {
    background: #f1f5f9;
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 40px;
}
.left-side img {
    width: 100%;
    max-width: 340px;
}
.right-side {
    flex: 1;
    padding: 50px 40px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.right-side h2 {
    text-align: center;
    font-weight: 700;
    margin-bottom: 25px;
    color: #1e293b;
}
.form-control, .form-select {
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 12px;
    border: 1px solid #cbd5e1;
    transition: 0.3s;
}
.form-control:focus, .form-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 8px rgba(59,130,246,0.4);
}
.password-wrapper {
    position: relative;
}
.password-wrapper i {
    position: absolute;
    top: 50%;
    right: 12px;
    transform: translateY(-50%);
    cursor: pointer;
    color: #94a3b8;
}
.btn-login {
    background: #3b82f6;
    color: #fff;
    font-weight: 600;
    border-radius: 12px;
    width: 100%;
    padding: 12px;
    transition: 0.3s;
}
.btn-login:hover {
    background: #2563eb;
    box-shadow: 0 4px 12px rgba(59,130,246,0.4);
}
.error-msg {
    font-size: 14px;
    margin-bottom: 12px;
    color: #e11d48;
    font-weight: 500;
}
.footer-text {
    text-align: center;
    margin-top: 20px;
    font-size: 13px;
    color: #64748b;
}
.footer-text a {
    color: #ef4444;
    text-decoration: none;
}
.footer-text a:hover {
    text-decoration: underline;
}
@media (max-width: 768px) {
    .container-login {
        flex-direction: column;
    }
    .left-side {
        display: none;
    }
}
</style>
</head>
<body>

<div class="container-login">
    <div class="left-side">
        <img src="../assets/img/loginpagebg.png" alt="Login Illustration">
    </div>

    <div class="right-side">
        <div style="text-align:center;">
            <img src="../assets/img/Abyan (10) Kasir Computer.jpg" alt="KasirApp Logo" style="width:100px; height:auto; margin-bottom:10px;">
        </div>
        <h2>KasirApp</h2>

        <form id="loginForm" method="POST">
            <input type="text" name="username" class="form-control" placeholder="Username" required>

            <div class="password-wrapper">
                <input type="password" name="password" class="form-control" placeholder="Password" required id="passwordField">
                <i class="fa fa-eye" id="togglePassword"></i>
            </div>

            <select name="role" class="form-select" required>
                <option value="" selected disabled>Pilih Role</option>
                <option value="admin">Admin</option>
                <option value="kasir">Kasir</option>
            </select>

            <?php if($error != ''): ?>
                <div class="error-msg"><?= $error ?></div>
            <?php endif; ?>

            <button type="submit" name="login" class="btn btn-login">Login</button>

            <div class="footer-text">
                Copyright © 2025 - <a href="#">KasirApp</a>
            </div>
        </form>
    </div>
</div>

<script>
const togglePassword = document.querySelector('#togglePassword');
const passwordField = document.querySelector('#passwordField');

togglePassword.addEventListener('click', () => {
    const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordField.setAttribute('type', type);
    togglePassword.classList.toggle('fa-eye-slash');
});
</script>

</body>
</html>
