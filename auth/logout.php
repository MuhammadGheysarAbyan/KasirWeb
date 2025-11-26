<?php
session_start();

// Hapus semua variabel session
$_SESSION = [];

// Hapus cookie session kalau ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hancurkan session di server
session_destroy();

// Redirect ke login dengan pesan sukses
header("Location: ../auth/login.php?logout=success");
exit();
?>
