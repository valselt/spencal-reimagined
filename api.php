<?php
// --- api.php (VERSION 6 - LOGO ADDED & SCREEN MESSAGE REMOVED) ---

error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'config.php';

// Helper Function
function format_rupiah_singkat($angka) {
    if ($angka >= 1000000000) return round($angka / 1000000000, 1) . 'M';
    if ($angka >= 1000000) return round($angka / 1000000, 1) . 'jt';
    if ($angka >= 1000) return round($angka / 1000, 0) . 'rb';
    return strval($angka);
}

function format_full($angka) {
    // Menghasilkan format: Rp 67.135
    return "Rp " . number_format($angka, 0, ',', '.');
}

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");

if (!isset($_GET['key']) || empty($_GET['key'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "API Key required."]);
    exit;
}

$api_key = $_GET['key'];

// 1. Validasi API & Ambil User ID
$stmt = $conn->prepare("SELECT user_id FROM api_user WHERE api = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database Error."]);
    exit;
}
$stmt->bind_param("s", $api_key);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid API Key."]);
    exit;
}

$row_api = $result->fetch_assoc();
$user_id = $row_api['user_id'];

// 2. Ambil Profil User
$stmt_user = $conn_valselt->prepare("SELECT username, profile_pic FROM users WHERE id = ? LIMIT 1");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$res_user = $stmt_user->get_result();
$user_data = $res_user->fetch_assoc();

$username_display = $user_data['username'] ?? 'User';
$avatar_display = $user_data['profile_pic'] ?? '';

// 3. Setup Waktu
$today = date('Y-m-d');
$now_time = date('H:i:s');
$cur_month = date('m');
$cur_year = date('Y');
$total_hari_bulan_ini = date('t'); 
$hari_ini_angka = date('j'); 
$sisa_hari = $total_hari_bulan_ini - $hari_ini_angka + 1;

// 4. Query Aggregates

// A. Harian
$q_daily_out = $conn->query("SELECT SUM(amount) as total FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id='$user_id' AND c.type='pengeluaran' AND t.date = '$today'");
$daily_out = $q_daily_out->fetch_assoc()['total'] ?? 0;

$q_daily_in = $conn->query("SELECT SUM(amount) as total FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id='$user_id' AND c.type='pemasukan' AND t.date = '$today'");
$daily_in = $q_daily_in->fetch_assoc()['total'] ?? 0;

// B. Bulanan
$q_month_in = $conn->query("SELECT SUM(amount) as total FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id='$user_id' AND c.type='pemasukan' AND MONTH(t.date)='$cur_month' AND YEAR(t.date)='$cur_year'");
$month_in = $q_month_in->fetch_assoc()['total'] ?? 0;

$q_month_out = $conn->query("SELECT SUM(amount) as total FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id='$user_id' AND c.type='pengeluaran' AND MONTH(t.date)='$cur_month' AND YEAR(t.date)='$cur_year'");
$month_out = $q_month_out->fetch_assoc()['total'] ?? 0;

// C. Savings
$q_savings = $conn->query("SELECT SUM(amount) as total FROM savings WHERE user_id='$user_id'");
$total_savings = $q_savings->fetch_assoc()['total'] ?? 0;

// D. Top Category
$q_top_cat = $conn->query("
    SELECT c.name, SUM(t.amount) as total 
    FROM transactions t 
    JOIN categories c ON t.category_id = c.id 
    WHERE t.user_id='$user_id' AND c.type='pengeluaran' AND MONTH(t.date)='$cur_month' AND YEAR(t.date)='$cur_year'
    GROUP BY c.id 
    ORDER BY total DESC 
    LIMIT 1
");
$top_cat_data = $q_top_cat->fetch_assoc();
$top_cat_name = $top_cat_data['name'] ?? '-';
$top_cat_amount = $top_cat_data['total'] ?? 0;

// 5. Kalkulasi Logic Spencal
$saldo_bulan_ini = $month_in - $month_out;
$saldo_awal_hari_ini = $saldo_bulan_ini + $daily_out;
$jatah_hari_ini = ($sisa_hari > 0) ? ($saldo_awal_hari_ini / $sisa_hari) : 0;
$sisa_bisa_pakai_hari_ini = $jatah_hari_ini - $daily_out;

// Persentase & Status Logic
$persen_terpakai = 0;
if ($jatah_hari_ini > 0) {
    $persen_terpakai = ($daily_out / $jatah_hari_ini) * 100;
}
$persen_terpakai = round($persen_terpakai, 1);

// --- [LOGIKA KHUSUS ESP32 / HARDWARE] ---
$status_color_hex = "#22c55e"; // Default Hijau (Green)
$status_text = "Aman";
$alert_level = 0; // 0=Aman, 1=Warning, 2=Bahaya
// Logika screen_message dihapus di sini

if ($sisa_bisa_pakai_hari_ini < 0) {
    // KONDISI BAHAYA (Minus)
    $status_color_hex = "#ef4444"; // Merah
    $status_text = "Over!";
    $alert_level = 2; // Trigger alarm/blink cepat

} elseif ($persen_terpakai > 90) {
    // KONDISI KRITIS (90% ke atas)
    $status_color_hex = "#f97316"; // Oranye Tua
    $status_text = "Kritis";
    $alert_level = 1; // Trigger warning beep

} elseif ($persen_terpakai > 75) {
    // KONDISI WARNING (75% ke atas)
    $status_color_hex = "#eab308"; // Kuning
    $status_text = "Siaga";
    $alert_level = 0;
} else {
    // AMAN
}

// 6. Recent Transactions
$recent_trx = [];
$q_recent = $conn->query("
    SELECT t.amount, t.note, c.name as category, c.type 
    FROM transactions t 
    JOIN categories c ON t.category_id = c.id 
    WHERE t.user_id='$user_id' 
    ORDER BY t.date DESC, t.id DESC 
    LIMIT 3
");

if($q_recent) {
    while($row = $q_recent->fetch_assoc()) {
        $recent_trx[] = [
            "category" => $row['category'],
            "note" => $row['note'],
            "amount" => (float)$row['amount'],
            "formatted" => format_full($row['amount']),
            "type" => $row['type']
        ];
    }
}

// 7. Format Output JSON
$response = [
    "status" => "success",
    // [BARU] Menambahkan Logo di root JSON
    "logo" => "https://cdn.ivanaldorino.web.id/spencal/spencal_favicon.png",
    "user" => [
        "username" => $username_display,
        "avatar" => $avatar_display
    ],
    // Bagian ini KHUSUS untuk Hardware Control
    "device_ux" => [
        "led_hex" => $status_color_hex,
        "alert_level" => $alert_level,
        // "screen_message" => DIHAPUS
        "is_negative" => ($sisa_bisa_pakai_hari_ini < 0)
    ],
    "highlight" => [
        "sisa_hari_ini" => (float)$sisa_bisa_pakai_hari_ini,
        "sisa_hari_ini_fmt" => format_full($sisa_bisa_pakai_hari_ini),
        "status_text" => $status_text,
        "persen_hari_ini" => $persen_terpakai
    ],
    "today_detail" => [
        "limit_harian" => (float)$jatah_hari_ini,
        "pengeluaran" => (float)$daily_out,
        "pemasukan" => (float)$daily_in,
        "limit_fmt" => format_full($jatah_hari_ini), 
        "keluar_fmt" => format_full($daily_out)      
    ],
    "month_detail" => [
        "total_masuk" => (float)$month_in,
        "total_keluar" => (float)$month_out,
        "cashflow_bersih" => (float)$saldo_bulan_ini,
        "top_expense_category" => $top_cat_name,
        "top_expense_amount" => format_rupiah_singkat($top_cat_amount)
    ],
    "assets" => [
        "total_savings" => (float)$total_savings,
        "total_savings_fmt" => format_full($total_savings)
    ],
    "recent_trx" => $recent_trx,
    "meta" => [
        "date_pulled" => $today,
        "time_pulled" => $now_time,
        "api_ver" => "v6"
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>