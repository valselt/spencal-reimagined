<?php
session_start();
// Gunakan config Spencal yang punya 2 koneksi ($conn dan $conn_valselt)
require 'config.php'; 

if (isset($_GET['token'])) {
    $token = $conn_valselt->real_escape_string($_GET['token']);
    
    // 1. Cek Token di Database Valselt
    $result = $conn_valselt->query("SELECT * FROM users WHERE auth_token='$token'");
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // 2. Token Valid! Buat Session di Spencal
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        
        // 3. Hapus Token agar tidak bisa dipakai ulang (Security)
        $uid = $user['id'];
        $conn_valselt->query("UPDATE users SET auth_token=NULL WHERE id='$uid'");
        
        // 4. Masuk ke Dashboard
        header("Location: index.php");
        exit();
    } else {
        echo "Token Login Tidak Valid atau Kadaluarsa.";
        exit();
    }
} else {
    header("Location: login/login.php");
    exit();
}
?>