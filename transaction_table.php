<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// --- LOGIC TAHUN ---
// Ambil tahun dari URL jika ada, jika tidak pakai tahun sekarang
$selected_year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Ambil daftar tahun yang tersedia di database untuk dropdown
$years_query = $conn->query("SELECT DISTINCT YEAR(date) as year FROM transactions WHERE user_id='$user_id' ORDER BY year DESC");
$available_years = [];
while($r = $years_query->fetch_assoc()) {
    $available_years[] = $r['year'];
}
// Jika belum ada data, minimal tampilkan tahun sekarang
if(empty($available_years)) {
    $available_years[] = date('Y');
}

// --- 1. AMBIL SEMUA KATEGORI ---
$cats_in = [];
$cats_out = [];
$q_cat = $conn->query("SELECT * FROM categories WHERE user_id='$user_id' ORDER BY name ASC");
while($c = $q_cat->fetch_assoc()) {
    if($c['type'] == 'pemasukan') $cats_in[$c['id']] = $c['name'];
    else $cats_out[$c['id']] = $c['name'];
}

// --- 2. AMBIL TRANSAKSI TAHUN TERPILIH DI-GROUP BY BULAN & KATEGORI ---
$matrix = [];
$month_totals_in = array_fill(1, 12, 0);  
$month_totals_out = array_fill(1, 12, 0); 

$query = "SELECT category_id, MONTH(date) as month, SUM(amount) as total 
          FROM transactions 
          WHERE user_id='$user_id' AND YEAR(date)='$selected_year' 
          GROUP BY category_id, MONTH(date)";
$res = $conn->query($query);

while($row = $res->fetch_assoc()) {
    $cat_id = $row['category_id'];
    $month = $row['month'];
    $val = $row['total'];
    
    $matrix[$cat_id][$month] = $val;

    if (array_key_exists($cat_id, $cats_in)) {
        $month_totals_in[$month] += $val;
    } elseif (array_key_exists($cat_id, $cats_out)) {
        $month_totals_out[$month] += $val;
    }
}

function rp($angka) {
    if($angka == 0) return '-';
    return number_format($angka, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tabel Transaksi - Spencal</title>
    <link rel="icon" href="https://cdn.ivanaldorino.web.id/spencal/spencal_favicon.png" type="image/png">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* Override style khusus untuk halaman ini agar mirip transaction.php */
        .header-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .year-selector {
            padding: 8px 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: white;
            font-family: 'DM Sans', sans-serif;
            font-weight: 600;
            cursor: pointer;
            outline: none;
        }
        .year-selector:focus {
            border-color: var(--primary);
        }
    </style>
</head>
<body>

<div class="admin-wrapper">
    <aside class="sidebar">
        <div>
            <div class="brand-logo">
                <img src="http://cdn.ivanaldorino.web.id/spencal/spencal_logo.png" alt="Spencal" class="brand-logo-img">
            </div>
            <ul class="sidebar-menu">
                <li><a href="index.php" class="menu-item"><i class='bx bxs-dashboard'></i> Dashboard</a></li>
                <li><a href="transactions.php" class="menu-item"><i class='bx bx-list-ul'></i> Riwayat Transaksi</a></li>
                <li><a href="transaction_table.php" class="menu-item active"><i class='bx bx-table'></i> Tabel Transaksi</a></li>
            </ul>
        </div>

        <?php 
            $q_user = $conn_valselt->query("SELECT profile_pic FROM users WHERE id='$user_id'");
            $u_data = $q_user->fetch_assoc();
            $pic_url = $u_data['profile_pic'];
        ?>
        <div class="sidebar-profile">
            <a href="profile.php" class="profile-info-link" title="Edit Profil">
                <div class="user-info">
                    <?php if(isset($pic_url) && $pic_url): ?>
                        <img src="<?php echo $pic_url; ?>" class="user-avatar" style="object-fit:cover; width:40px; height:40px; border-radius:50%; margin-right:10px;">
                    <?php elseif(isset($user_data['profile_pic']) && $user_data['profile_pic']): ?>
                         <img src="<?php echo $user_data['profile_pic']; ?>" class="user-avatar" style="object-fit:cover; width:40px; height:40px; border-radius:50%; margin-right:10px;">
                    <?php else: ?>
                        <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 2)); ?></div>
                    <?php endif; ?>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($username); ?></h4>
                        <small>Edit Profil</small>
                    </div>
                </div>
            </a>
            <a href="logout.php" class="logout-btn" title="Keluar / Logout"><i class='bx bx-log-out-circle'></i></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="header-controls">
            <div>
                <header class="content-header" style="margin-bottom:0;">
                    <h1>Tabel Transaksi</h1>
                    <p>Rekapitulasi lengkap bulanan.</p>
                </header>
            </div>
            
            <div>
                <form method="GET">
                    <select name="year" class="year-selector" onchange="this.form.submit()">
                        <?php foreach($available_years as $y): ?>
                            <option value="<?php echo $y; ?>" <?php echo ($y == $selected_year) ? 'selected' : ''; ?>>
                                Tahun <?php echo $y; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <div class="excel-container">
            <table class="excel-table">
                <thead>
                    <tr class="row-header-top">
                        <th style="width: 200px;">Tahun Anggaran <?php echo $selected_year; ?></th>
                        <?php 
                        $grand_total_net = 0;
                        for($m=1; $m<=12; $m++): 
                            $net = $month_totals_in[$m] - $month_totals_out[$m];
                            $grand_total_net += $net;
                            $cls = ($net > 0) ? 'net-pos' : (($net < 0) ? 'net-neg' : 'net-zero');
                        ?>
                            <th class="<?php echo $cls; ?>" style="color: inherit;"><?php echo rp($net); ?></th>
                        <?php endfor; ?>
                        <th class="<?php echo ($grand_total_net >= 0) ? 'net-pos' : 'net-neg'; ?>" style="color: inherit; border-left: 3px solid #000;">
                            <?php echo rp($grand_total_net); ?>
                        </th>
                    </tr>
                    
                    <tr class="row-months">
                        <th>Kategori</th>
                        <th>Jan</th><th>Feb</th><th>Mar</th><th>Apr</th><th>Mei</th><th>Jun</th>
                        <th>Jul</th><th>Agu</th><th>Sep</th><th>Okt</th><th>Nov</th><th>Des</th>
                        <th style="border-left: 3px solid #000;">TOTAL</th>
                    </tr>
                </thead>

                <tbody>
                    <tr class="header-income"><th colspan="14">PEMASUKAN</th></tr>
                    <?php 
                    $grand_total_in_year = 0;
                    foreach($cats_in as $cid => $cname): 
                        $row_total = 0;
                    ?>
                    <tr>
                        <td><?php echo $cname; ?></td>
                        <?php for($m=1; $m<=12; $m++): 
                            $val = $matrix[$cid][$m] ?? 0;
                            $row_total += $val;
                        ?>
                            <td><?php echo rp($val); ?></td>
                        <?php endfor; 
                        $grand_total_in_year += $row_total;
                        ?>
                        <td style="font-weight:bold; background:#f0fdf4; border-left: 3px solid #000;"><?php echo rp($row_total); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <tr class="row-total">
                        <td>TOTAL PEMASUKAN</td>
                        <?php for($m=1; $m<=12; $m++): ?>
                            <td><?php echo rp($month_totals_in[$m]); ?></td>
                        <?php endfor; ?>
                        <td style="border-left: 3px solid #000;"><?php echo rp($grand_total_in_year); ?></td>
                    </tr>
                </tbody>

                <tbody>
                    <tr class="header-expense"><th colspan="14">PENGELUARAN</th></tr>
                    <?php 
                    $grand_total_out_year = 0;
                    foreach($cats_out as $cid => $cname): 
                        $row_total = 0;
                    ?>
                    <tr>
                        <td><?php echo $cname; ?></td>
                        <?php for($m=1; $m<=12; $m++): 
                            $val = $matrix[$cid][$m] ?? 0;
                            $row_total += $val;
                        ?>
                            <td><?php echo rp($val); ?></td>
                        <?php endfor; 
                        $grand_total_out_year += $row_total;
                        ?>
                        <td style="font-weight:bold; background:#fef2f2; border-left: 3px solid #000;"><?php echo rp($row_total); ?></td>
                    </tr>
                    <?php endforeach; ?>

                    <tr class="row-total">
                        <td>TOTAL PENGELUARAN</td>
                        <?php for($m=1; $m<=12; $m++): ?>
                            <td><?php echo rp($month_totals_out[$m]); ?></td>
                        <?php endfor; ?>
                        <td style="border-left: 3px solid #000;"><?php echo rp($grand_total_out_year); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </main>
</div>

</body>
</html>