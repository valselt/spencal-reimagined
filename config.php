<?php
date_default_timezone_set('Asia/Jakarta');
// Load Composer Autoload (Pastikan path ini benar di Docker)
require 'vendor/autoload.php'; 

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use PHPMailer\PHPMailer\PHPMailer;

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

// --- KONFIGURASI EMAIL (SMTP GMAIL) ---
$mail_host = 'smtp.gmail.com';
$mail_port = 587; // TLS
$mail_user = 'valseltalt@gmail.com'; // Ganti Email Anda
$mail_pass = 'cryw pkpa chai pefm';  // Ganti App Password (16 digit)
$mail_from_name = 'Spencal by Valselt';

// --- FUNGSI KIRIM EMAIL ---
function sendOTPEmail($toEmail, $otp) {
    global $mail_host, $mail_port, $mail_user, $mail_pass, $mail_from_name;
    
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $mail_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $mail_user;
        $mail->Password   = $mail_pass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $mail_port;

        $mail->setFrom($mail_user, $mail_from_name);
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = "Kode Verifikasi OTP Spencal";
        $mail->Body    = "
            <h3>Halo!</h3>
            <p>Terima kasih telah mendaftar di Spencal.</p>
            <p>Kode OTP Anda adalah: <b style='font-size: 20px; color: #4f46e5;'>$otp</b></p>
            <p>Kode ini berlaku selama 10 menit.</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}



?>