<?php
session_start();
require 'config.php'; 

if (isset($_GET['token'])) {
    $token = $conn_valselt->real_escape_string($_GET['token']);
    
    // Cek Token Valid di DB Valselt
    $result = $conn_valselt->query("SELECT * FROM users WHERE auth_token='$token'");
    
    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $uid = $user['id'];
        
        // --- SEED KATEGORI ---
        // Karena DB sudah bersih, kode ini akan berjalan lancar sekarang
        $cek_cat = $conn->query("SELECT id FROM categories WHERE user_id='$uid' LIMIT 1");
        if ($cek_cat->num_rows == 0) {
            seedCategories($uid, $conn);
        }

        // Buat Session Lokal
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        
        // Hapus Token (PENTING: Ini yang membuat token jadi sekali pakai)
        $conn_valselt->query("UPDATE users SET auth_token=NULL WHERE id='$uid'");
        
        header("Location: index.php");
        exit();
    }
}

// JIKA TOKEN SALAH/KADALUARSA/REFRESH:
// Jangan die(), tapi lempar balik ke login biar user bisa coba lagi
header("Location: login/login.php");
exit();
?>