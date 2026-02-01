<?php

if (isset($_SERVER['HTTP_CF_VISITOR']) || isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    if (
        (isset($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false) ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
    ) {
        $_SERVER['HTTPS'] = 'on';
    }
}
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

require 'vendor/autoload.php'; // Pastikan Autoload Composer dimuat
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// 1. Database Credentials
$db_host = getEnvVar('DB_HOST', 'mariadb');
$db_port = getEnvVar('DB_PORT', 3306); // Port default jika env kosong
$db_user = getEnvVar('DB_USER', 'root');
$db_pass = getEnvVar('DB_PASS'); // Password tidak ada default demi keamanan

// DB NAMES
$db_spencal = getEnvVar('DB_NAME_SPENCAL', 'spencal_reimagined');
$db_valselt = getEnvVar('DB_NAME_VALSELT', 'valselt_id');

// ==========================================
// KONEKSI S3 MINIO
// ==========================================
$s3_endpoint = getEnvVar('S3_ENDPOINT', 'https://cdn.ivanaldorino.web.id');
$s3_key      = getEnvVar('S3_ACCESS_KEY', 'admin');
$s3_secret   = getEnvVar('S3_SECRET_KEY', 'aldorino04');
$s3_bucket   = getEnvVar('S3_BUCKET', 'spencal');
$s3_region   = getEnvVar('S3_REGION', 'us-east-1');

try {
    $s3 = new S3Client([
        'version' => 'latest',
        'region'  => $s3_region,
        'endpoint' => $s3_endpoint,
        'use_path_style_endpoint' => true, // Wajib true untuk MinIO
        'credentials' => [
            'key'    => $s3_key,
            'secret' => $s3_secret,
        ],
    ]);
} catch (Exception $e) {
    // S3 Error (Optional: Log error)
}

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