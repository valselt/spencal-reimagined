<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

// Debug: laporkan DB yang sedang terhubung (ke error_log)
if (isset($conn) && $conn instanceof mysqli) {
    $dbName = $conn->query("SELECT DATABASE()")->fetch_row()[0] ?? 'unknown';
    error_log("API SAVE ORDER - using DB: " . $dbName);
} else {
    error_log("API SAVE ORDER - \$conn not available");
}

// Cek login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Ambil data JSON
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || !isset($input['order']) || !is_array($input['order'])) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak valid atau kosong']);
    exit();
}

$orderArray = $input['order'];
if (count($orderArray) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Tidak ada item untuk disimpan']);
    exit();
}

// Siapkan statement (update per id). Kita pertahankan user_id di WHERE demi keamanan.
$stmt = $conn->prepare("UPDATE categories SET shortcut_order = ? WHERE id = ? AND user_id = ?");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
    exit();
}

// Bind sekali (variabel referensi akan berubah tiap loop)
$order_idx = 0;
$cat_id_val = 0;
$user_id_val = $user_id;
$stmt->bind_param("iii", $order_idx, $cat_id_val, $user_id_val);

$successCount = 0;
$failures = [];

foreach ($orderArray as $index => $cat_id_raw) {
    $order_idx = (int)$index + 1;         // gunakan 1-based index jika mau
    $cat_id_val = (int)$cat_id_raw;

    if ($cat_id_val <= 0) {
        $failures[] = ['id' => $cat_id_raw, 'error' => 'invalid id'];
        continue;
    }

    if (!$stmt->execute()) {
        $err = $stmt->error;
        error_log("Failed to update category_id={$cat_id_val} : {$err}");
        $failures[] = ['id' => $cat_id_val, 'error' => $err];
    } else {
        $successCount++;
    }
}

$stmt->close();

echo json_encode([
    'status' => 'success',
    'message' => 'Selesai memproses urutan',
    'updated_items' => $successCount,
    'failures' => $failures
]);
