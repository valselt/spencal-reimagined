<?php
session_start();
require 'config.php';

if(!isset($_SESSION['user_id'])){
    echo json_encode(["error" => "not_login"]);
    exit;
}

$user_id = $_SESSION['user_id'];

$today = date('Y-m-d');
$cur_month = date('m');
$cur_year = date('Y');

$total_hari_bulan_ini = date('t'); 
$hari_ini_angka = date('j'); 
$sisa_hari = $total_hari_bulan_ini - $hari_ini_angka + 1;

// Query ulang
$q_out = $conn->query("SELECT SUM(amount) as total FROM transactions t JOIN categories c ON t.category_id=c.id WHERE t.user_id='$user_id' AND c.type='pengeluaran' AND t.date='$today'");
$daily_out = $q_out->fetch_assoc()['total'] ?? 0;

$q_in = $conn->query("SELECT SUM(amount) as total FROM transactions t JOIN categories c ON t.category_id=c.id WHERE t.user_id='$user_id' AND c.type='pemasukan' AND t.date='$today'");
$daily_in = $q_in->fetch_assoc()['total'] ?? 0;

$q_month_in = $conn->query("SELECT SUM(amount) as total FROM transactions t JOIN categories c ON t.category_id=c.id WHERE t.user_id='$user_id' AND c.type='pemasukan' AND MONTH(t.date)='$cur_month' AND YEAR(t.date)='$cur_year'");
$month_in = $q_month_in->fetch_assoc()['total'] ?? 0;

$q_month_out = $conn->query("SELECT SUM(amount) as total FROM transactions t JOIN categories c ON t.category_id=c.id WHERE t.user_id='$user_id' AND c.type='pengeluaran' AND MONTH(t.date)='$cur_month' AND YEAR(t.date)='$cur_year'");
$month_out = $q_month_out->fetch_assoc()['total'] ?? 0;

$saldo_bulan_ini = $month_in - $month_out;
$saldo_awal_hari_ini = $saldo_bulan_ini + $daily_out;

$jatah_hari_ini = ($sisa_hari > 0) ? $saldo_awal_hari_ini / $sisa_hari : 0;
$sisa_bisa_pakai_hari_ini = $jatah_hari_ini - $daily_out;

echo json_encode([
    "jatah" => $jatah_hari_ini,
    "sisa" => $sisa_bisa_pakai_hari_ini,
    "daily_in" => $daily_in,
    "daily_out" => $daily_out,
    "today" => $today
]);
?>
