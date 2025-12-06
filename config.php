<?php
// Load Composer Autoload (Pastikan path ini benar di Docker)
require 'vendor/autoload.php'; 

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// ==========================================
// KONSENTRASI DATA RAHASIA
// ==========================================

// 1. Database
$db_host = '100.115.160.110';
$db_port = 3306;
$db_name = 'spencal_reimagined';
$db_user = 'root';
$db_pass = 'aldorino04';

// 2. MinIO Object Storage
$minio_endpoint = 'https://cdn.ivanaldorino.web.id/';
$minio_key      = 'admin';
$minio_secret   = 'aldorino04';
$minio_bucket   = 'spencal'; // Pastikan bucket ini sudah dibuat di MinIO Anda

// 3. Google reCAPTCHA
$recaptcha_site_key   = '6LdEEyMsAAAAAPK75it3V-_wxwWESVqQebrdNzKF'; 
$recaptcha_secret_key = '6LdEEyMsAAAAADK5A1RXPIHpHTi2lwx5CdnORfwB';

// ==========================================
// KONEKSI & HELPER
// ==========================================

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
if ($conn->connect_error) die("Koneksi Database Gagal.");

// Inisialisasi S3 Client (MinIO)
try {
    $s3 = new S3Client([
        'version' => 'latest',
        'region'  => 'us-east-1', // MinIO mengabaikan region, tapi SDK butuh ini
        'endpoint' => $minio_endpoint,
        'use_path_style_endpoint' => true,
        'credentials' => [
            'key'    => $minio_key,
            'secret' => $minio_secret,
        ],
    ]);
} catch (Exception $e) {
    die("Gagal inisialisasi MinIO: " . $e->getMessage());
}

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