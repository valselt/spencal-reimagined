<?php
session_start();
require '../config.php'; // Naik satu level untuk ambil config

if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php"); // Jika sudah login, lempar ke dashboard
    exit();
}

// --- PROSES LOGIN ---
if (isset($_POST['login'])) {
    $user_input = $conn->real_escape_string($_POST['user_input']);
    $password = $_POST['password'];
    
    $result = $conn->query("SELECT * FROM users WHERE username='$user_input' OR email='$user_input'");
    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['email'] = $row['email'];
            header("Location: ../index.php"); // Redirect ke Dashboard (Root)
            exit();
        }
    }
    $error_login = "Username/Email atau password salah!";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - Spencal</title>
    <link rel="stylesheet" href="../style.css"> </head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-brand">spencal<span>.</span></div>
            <h4 class="auth-title">Masuk ke akun Anda</h4>
            
            <?php if(isset($error_login)) echo "<p class='text-danger' style='margin-bottom:15px;'>$error_login</p>"; ?>

            <form method="POST" style="text-align:left;">
                <div class="form-group">
                    <label class="form-label">Username atau Email</label>
                    <input type="text" name="user_input" class="form-control" required placeholder="contoh@email.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required placeholder="******">
                </div>
                <button type="submit" name="login" class="btn btn-primary">Masuk Sekarang</button>
            </form>

            <div style="margin-top: 20px; font-size: 0.9rem; color: var(--text-muted);">
                Belum punya akun? <a href="../register/register.php" style="color: var(--primary); font-weight: 600;">Daftar disini</a>
            </div>
        </div>
    </div>
</body>
</html>