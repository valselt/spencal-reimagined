<?php
// Setting Session
$durasi_session = 90 * 24 * 60 * 60; 
ini_set('session.gc_maxlifetime', $durasi_session);
session_set_cookie_params($durasi_session);

require 'config.php'; // DB Lokal
require 'Valselt.php'; // SDK

// Konfigurasi (Redirect URI diarahkan ke file ini sendiri)
$client_id     = "spencal-874325-valselt-id"; 
$client_secret = "0b5e1a54ff70d7b0b8a4a50a34672eba954973fc0e009219e47cbdf4913dcfa0"; 
$redirect_uri  = "https://spencal.ivanaldorino.web.id/login.php"; // PENTING: Arahkan ke diri sendiri

$sso = new Valselt($client_id, $client_secret, $redirect_uri);

// SDK Pintar:
// 1. Jika ada ?code=... di URL, dia akan memproses login & return data user.
// 2. Jika TIDAK ada code, dia akan redirect user ke Valselt (exit di sini).
$user = $sso->getUser();

// --- KODE DI BAWAH INI HANYA JALAN JIKA LOGIN SUKSES (Callback) ---

// Pindahkan logika seed & session dari auth_callback.php ke sini
$uid      = $user['id'];
$username = $user['username'];
$email    = $user['email'];

// Logika Seed Kategori
$cek_cat = $conn->query("SELECT id FROM categories WHERE user_id='$uid' LIMIT 1");
if ($cek_cat->num_rows == 0) {
    if(function_exists('seedCategories')) {
        seedCategories($uid, $conn);
    }
}

// Set Session
$_SESSION['user_id']  = $uid;
$_SESSION['username'] = $username;
$_SESSION['email']    = $email;

header("Location: index.php");
exit();
?>