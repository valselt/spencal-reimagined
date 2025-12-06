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
$db_name_spencal = 'spencal_reimagined';
$db_name_valselt = 'valselt_id';
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

// --- KONEKSI 1: APLIKASI SPENCAL ---
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name_spencal, $db_port);
if ($conn->connect_error) die("Koneksi Spencal Gagal: " . $conn->connect_error);

// --- KONEKSI 2: AKUN VALSELT (GLOBAL) ---
$conn_valselt = new mysqli($db_host, $db_user, $db_pass, $db_name_valselt, $db_port);
if ($conn_valselt->connect_error) die("Koneksi Valselt ID Gagal: " . $conn_valselt->connect_error);

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
$mail_port = 587; 
$mail_user = 'valseltalt@gmail.com'; 
$mail_pass = 'cryw pkpa chai pefm';  
$mail_from_name = 'Spencal by Valselt';

// --- FUNGSI KIRIM EMAIL ---
function sendOTPEmail($toEmail, $otp) {
    global $mail_host, $mail_port, $mail_user, $mail_pass, $mail_from_name;
    
    $mail = new PHPMailer(true);
    try {
        // --- DEBUGGING (HAPUS JIKA SUDAH BERHASIL) ---
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Uncomment baris ini jika masih gagal untuk melihat log lengkap
        // $mail->Debugoutput = 'html';

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
            <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                <h2 style='color: #4f46e5;'>Halo!</h2>
                <p>Terima kasih telah mendaftar di Spencal.</p>
                <p>Kode OTP Anda adalah:</p>
                <h1 style='color: #4f46e5; letter-spacing: 5px;'>$otp</h1>
                <p>Kode ini berlaku selama 10 menit.</p>
                <hr>
                <small>Jika Anda tidak merasa mendaftar, abaikan email ini.</small>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        // --- PERBAIKAN 2: Tampilkan Error Asli di Popup ---
        // Kita tidak bisa melihat error di F12 karena ini PHP (Server Side)
        // Kita simpan error ke Session agar muncul di Popup Browser
        if(session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['popup_status'] = 'error';
        $_SESSION['popup_message'] = "Mailer Error: " . $mail->ErrorInfo; 
        return false;
    }
}
?>