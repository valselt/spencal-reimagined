<?php
// ==========================================
// KONSENTRASI DATA RAHASIA (CONFIGURATION)
// ==========================================

// 1. Database Credentials
$db_host = '100.115.160.110';
$db_port = 3306;
$db_name = 'spencal_reimagined';
$db_user = 'root';
$db_pass = 'aldorino04';

// 2. Google reCAPTCHA Keys
// Ganti dengan kunci asli dari Google Admin Console
$recaptcha_site_key   = '6LdEEyMsAAAAAPK75it3V-_wxwWESVqQebrdNzKF'; 
$recaptcha_secret_key = '6LdEEyMsAAAAADK5A1RXPIHpHTi2lwx5CdnORfwB';

// ==========================================
// LOGIKA KONEKSI (JANGAN DIUBAH)
// ==========================================

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

if ($conn->connect_error) {
    die("Koneksi Database Gagal: " . $conn->connect_error);
}

// Helper function: Insert kategori default saat user baru register
function seedCategories($userId, $conn) {
    $defaults = [
        'pengeluaran' => ['Makan', 'Jajan', 'Bumbu Masak', 'Kebersihan Diri', 'Kesehatan', 'Bensin', 'Jalan-Jalan'],
        'pemasukan' => ['Uang Gaji', 'Bonus', 'Bunga']
    ];

    foreach ($defaults as $type => $names) {
        foreach ($names as $name) {
            $conn->query("INSERT INTO categories (user_id, type, name) VALUES ('$userId', '$type', '$name')");
        }
    }
}
?>