<?php
session_start();
require 'config.php'; // Memuat semua variabel rahasia ($recaptcha_site_key, dll)

// --- PROSES REGISTRASI ---
if (isset($_POST['register'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $username = htmlspecialchars($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $recaptcha_response = $_POST['g-recaptcha-response'];

    // Validasi reCAPTCHA ke Google menggunakan Secret Key dari config.php
    $captcha_success = false;
    
    if(!empty($recaptcha_secret_key) && $recaptcha_secret_key != 'GANTI_DENGAN_SECRET_KEY_ANDA') {
        $verify_url = "https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_secret_key}&response={$recaptcha_response}";
        $verify_response = file_get_contents($verify_url);
        $response_data = json_decode($verify_response);
        $captcha_success = $response_data->success;
    } else {
        // BYPASS jika key belum disetting (Hanya untuk dev)
        $captcha_success = true; 
    }

    if (!$captcha_success) {
         echo "<script>alert('Verifikasi Robot Gagal! Silakan coba lagi.');</script>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
         echo "<script>alert('Format email tidak valid!');</script>";
    } else {
        // Cek Email atau Username kembar
        $cek = $conn->query("SELECT id FROM users WHERE username='$username' OR email='$email'");
        if($cek->num_rows > 0){
             echo "<script>alert('Username atau Email sudah terdaftar!');</script>";
        } else {
             $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
             $stmt->bind_param("sss", $username, $email, $password);
             
             if ($stmt->execute()) {
                 $last_id = $conn->insert_id;
                 seedCategories($last_id, $conn);
                 echo "<script>alert('Registrasi Berhasil! Silakan Login.'); window.location.href='index.php';</script>";
             } else {
                 echo "<script>alert('Terjadi kesalahan sistem.');</script>";
             }
        }
    }
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
            header("Location: dashboard.php");
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
    <title>Masuk / Daftar - Spencal</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-brand">spencal<span>.</span></div>
            <h4 class="auth-title">Kelola keuanganmu dengan cerdas.</h4>
            
            <?php if(isset($error_login)) echo "<p class='text-danger' style='margin-bottom:15px;'>$error_login</p>"; ?>

            <div class="auth-toggle">
                <button class="toggle-btn active" onclick="switchForm('login')">Masuk</button>
                <button class="toggle-btn" onclick="switchForm('register')">Daftar Akun</button>
            </div>

            <form id="loginForm" method="POST" style="text-align:left;">
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

            <form id="registerForm" method="POST" style="display:none; text-align:left;">
                 <div class="form-group">
                    <label class="form-label">Alamat Email</label>
                    <input type="email" name="email" class="form-control" required placeholder="nama@email.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required placeholder="Username unik">
                </div>
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required placeholder="Minimal 6 karakter">
                </div>

                <div class="g-recaptcha" data-sitekey="<?php echo $recaptcha_site_key; ?>"></div>
                
                <button type="submit" name="register" class="btn btn-primary" style="margin-top:15px;">Daftar Akun Baru</button>
            </form>
        </div>
    </div>

    <script>
        function switchForm(type) {
            const loginBtn = document.querySelector('.toggle-btn:nth-child(1)');
            const regBtn = document.querySelector('.toggle-btn:nth-child(2)');
            const loginForm = document.getElementById('loginForm');
            const regForm = document.getElementById('registerForm');

            if(type === 'login') {
                loginForm.style.display = 'block';
                regForm.style.display = 'none';
                loginBtn.classList.add('active');
                regBtn.classList.remove('active');
            } else {
                loginForm.style.display = 'none';
                regForm.style.display = 'block';
                loginBtn.classList.remove('active');
                regBtn.classList.add('active');
            }
        }
    </script>
</body>
</html>