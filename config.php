<?php
// Ambil timezone dari env, default ke Asia/Jakarta jika tidak ada
$timezone = getenv('APP_TIMEZONE') ?: 'Asia/Jakarta';
date_default_timezone_set($timezone);

// ==========================================
// KONSENTRASI DATA RAHASIA (VIA ENV)
// ==========================================

// Helper function agar kode lebih bersih
function getEnvVar($key, $default = '') {
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

// 1. Database Credentials
$db_host = getEnvVar('DB_HOST', 'mariadb');
$db_port = getEnvVar('DB_PORT', 3306); // Port default jika env kosong
$db_user = getEnvVar('DB_USER', 'root');
$db_pass = getEnvVar('DB_PASS'); // Password tidak ada default demi keamanan

// DB NAMES
$db_spencal = getEnvVar('DB_NAME_SPENCAL', 'spencal_reimagined');
$db_valselt = getEnvVar('DB_NAME_VALSELT', 'valselt_id');

// ==========================================
// KONEKSI & HELPER
// ==========================================

// KONEKSI 1: DATA TRANSAKSI
// Menggunakan try-catch untuk menangani error koneksi dengan lebih rapi
try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_spencal, $db_port);
    if ($conn->connect_error) {
        throw new Exception("Koneksi Spencal Gagal: " . $conn->connect_error);
    }

    // KONEKSI 2: DATA USER (READ ONLY UNTUK JOIN)
    $conn_valselt = new mysqli($db_host, $db_user, $db_pass, $db_valselt, $db_port);
    if ($conn_valselt->connect_error) {
        throw new Exception("Koneksi Valselt Gagal: " . $conn_valselt->connect_error);
    }
} catch (Exception $e) {
    // Di production, jangan echo error raw ke user, log saja.
    die("Database Connection Error. Check logs.");
}

function seedCategories($userId, $conn) {
    $defaults = [
        'pengeluaran' => ['Makan', 'Jajan', 'Bumbu Masak', 'Kebersihan Diri', 'Kesehatan', 'Bensin', 'Jalan-Jalan'],
        'pemasukan' => ['Uang Gaji', 'Bonus', 'Bunga']
    ];
    foreach ($defaults as $type => $names) {
        foreach ($names as $name) {
            // Gunakan prepared statement untuk keamanan ekstra (optional tapi recommended)
            $stmt = $conn->prepare("INSERT INTO categories (user_id, type, name) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $userId, $type, $name);
            $stmt->execute();
        }
    }
}
?>