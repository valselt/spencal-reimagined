<?php
date_default_timezone_set('Asia/Jakarta');
// Load Composer Autoload (Pastikan path ini benar di Docker)
require __DIR__ . '/vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

// ==========================================
// KONSENTRASI DATA RAHASIA
// ==========================================

// 1. Database
$db_host = '100.115.160.110';
$db_port = 3306;
$db_user = 'root';
$db_pass = 'aldorino04';

// DB NAME
$db_spencal = 'spencal_reimagined';
$db_valselt = 'valselt_id';

// ==========================================
// KONEKSI & HELPER
// ==========================================

// KONEKSI 1: DATA TRANSAKSI
$conn = new mysqli($db_host, $db_user, $db_pass, $db_spencal, $db_port);

// KONEKSI 2: DATA USER (READ ONLY UNTUK JOIN)
$conn_valselt = new mysqli($db_host, $db_user, $db_pass, $db_valselt, $db_port);

function seedCategories($userId, $conn) {
    // ... (kode lama sama)
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