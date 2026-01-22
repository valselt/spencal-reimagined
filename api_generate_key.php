<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Fungsi Generate Random String
function generateRandomString($length = 16) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}

// --- LOGIKA BARU: Generate & Cek Unik ---
$new_api_code = '';
$is_unique = false;
$attempt = 0;
$max_attempts = 5; // Batas percobaan agar tidak infinite loop

do {
    $new_api_code = generateRandomString(16);
    
    // Cek apakah kode ini sudah dipakai orang lain
    $check_stmt = $conn->prepare("SELECT id FROM api_user WHERE api = ?");
    $check_stmt->bind_param("s", $new_api_code);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows == 0) {
        $is_unique = true; // Kode aman, belum ada yang pakai
    }
    
    $check_stmt->close();
    $attempt++;

} while (!$is_unique && $attempt < $max_attempts);

if (!$is_unique) {
    echo json_encode(['status' => 'error', 'message' => 'Gagal generate kode unik. Silakan coba lagi.']);
    exit;
}

// 2. Simpan ke Database
// Menggunakan ON DUPLICATE KEY UPDATE untuk user_id yang sama
$stmt = $conn->prepare("INSERT INTO api_user (user_id, api) VALUES (?, ?) ON DUPLICATE KEY UPDATE api = ?");
$stmt->bind_param("iss", $user_id, $new_api_code, $new_api_code);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'success', 
        'api_code' => $new_api_code
    ]);
} else {
    echo json_encode([
        'status' => 'error', 
        'message' => 'Gagal menyimpan ke database.'
    ]);
}
?>