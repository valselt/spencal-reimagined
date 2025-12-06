<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login/login.php"); exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$email_user = isset($_SESSION['email']) ? $_SESSION['email'] : $username . '@spencal.com';

// --- DATA WAKTU ---
$today = date('Y-m-d');
$cur_month = date('m');
$cur_year = date('Y');

// Variabel untuk Rumus Hari
$total_hari_bulan_ini = date('t'); // Total hari (misal 30 atau 31)
$hari_ini_angka = date('j'); // Tanggal hari ini (1-31)
$sisa_hari = $total_hari_bulan_ini - $hari_ini_angka + 1; // Sisa hari termasuk hari ini

// --- PROSES INPUT TRANSAKSI ---
if (isset($_POST['submit_transaksi'])) {
    $tgl = $_POST['tanggal'];
    $cat_id = $_POST['sub_jenis'];
    $note = htmlspecialchars($_POST['catatan']);
    $amount = $_POST['total'];

    $stmt = $conn->prepare("INSERT INTO transactions (user_id, category_id, date, note, amount) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iissd", $user_id, $cat_id, $tgl, $note, $amount);
    $stmt->execute();
}

// --- QUERY DATA ---

// 1. Pengeluaran Hari Ini
$q_daily_out = $conn->query("SELECT SUM(amount) as total FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id='$user_id' AND c.type='pengeluaran' AND t.date = '$today'");
$daily_out = $q_daily_out->fetch_assoc()['total'] ?? 0;

// 2. Pemasukan Hari Ini
$q_daily_in = $conn->query("SELECT SUM(amount) as total FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id='$user_id' AND c.type='pemasukan' AND t.date = '$today'");
$daily_in = $q_daily_in->fetch_assoc()['total'] ?? 0;

// 3. Pemasukan Bulan Ini
$q_month_in = $conn->query("SELECT SUM(amount) as total FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id='$user_id' AND c.type='pemasukan' AND MONTH(t.date)='$cur_month' AND YEAR(t.date)='$cur_year'");
$month_in = $q_month_in->fetch_assoc()['total'] ?? 0;

// 4. Pengeluaran Bulan Ini
$q_month_out = $conn->query("SELECT SUM(amount) as total FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id='$user_id' AND c.type='pengeluaran' AND MONTH(t.date)='$cur_month' AND YEAR(t.date)='$cur_year'");
$month_out = $q_month_out->fetch_assoc()['total'] ?? 0;


// --- LOGIKA HITUNG SISA SALDO HARI INI ---

// A. Saldo Real Saat Ini (Uang yang ada di tangan)
$saldo_bulan_ini = $month_in - $month_out;

// B. Saldo Awal Hari Ini (Sebelum jajan hari ini)
// Kita kembalikan pengeluaran hari ini untuk menghitung jatah awal hari ini
$saldo_awal_hari_ini = $saldo_bulan_ini + $daily_out;

// C. Sisa Saldo Untuk Hari Ini (JATAH / TARGET)
// Rumus: (Saldo Awal Hari Ini) / (Sisa Hari)
if ($sisa_hari > 0) {
    $jatah_hari_ini = $saldo_awal_hari_ini / $sisa_hari;
} else {
    $jatah_hari_ini = 0;
}

// D. Sisa Saldo Yang Masih Bisa Dipakai Hari Ini (REALITA)
// Rumus: Jatah - Pengeluaran Hari Ini
$sisa_bisa_pakai_hari_ini = $jatah_hari_ini - $daily_out;


// Data Chart
$chart_data = [$month_in, $month_out];
$chart_labels = ['Pemasukan Bln Ini', 'Pengeluaran Bln Ini'];

// Dropdown Data
$cats_pemasukan = $conn->query("SELECT * FROM categories WHERE user_id='$user_id' AND type='pemasukan'");
$cats_pengeluaran = $conn->query("SELECT * FROM categories WHERE user_id='$user_id' AND type='pengeluaran'");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Keuangan - Spencal</title>
    <link rel="stylesheet" href="style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .monthly-summary {
            background: #f8fafc;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .m-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.9rem;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 5px;
        }
        .m-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .m-label { color: var(--text-muted); font-weight: 500; }
        .m-val { font-weight: 700; }
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
                <li>
                    <a href="index.php" class="menu-item active">
                        <i class='bx bxs-dashboard'></i> Dashboard
                    </a>
                </li>
            </ul>
        </div>

        <?php 
            $q_user = $conn->query("SELECT profile_pic FROM users WHERE id='$user_id'");
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
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($username, 0, 2)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($username); ?></h4>
                        <small>Edit Profil</small>
                    </div>
                </div>
            </a>
            
            <a href="logout.php" class="logout-btn" title="Keluar / Logout">
                <i class='bx bx-log-out-circle'></i>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <h1>Dashboard Keuangan</h1>
            <p>Selamat datang kembali, <strong><?php echo htmlspecialchars($email_user); ?></strong></p>
        </header>

        <div class="stats-grid">
            
            <div class="card stat-card">
                <h3>Sisa Saldo Hari Ini</h3>
                <div class="value <?php echo ($sisa_bisa_pakai_hari_ini > 0) ? 'text-primary' : 'text-danger'; ?>" style="font-size: 1.4rem;">
                    Rp <?php echo number_format($sisa_bisa_pakai_hari_ini, 0, ',', '.'); ?>
                    <span style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">
                        dari Rp <?php echo number_format($jatah_hari_ini, 0, ',', '.'); ?>
                    </span>
                </div>
                <small style="font-size:0.75rem; color:#94a3b8; margin-top:5px; display:block;">
                    (Jatah Harian untuk <?php echo $sisa_hari; ?> hari tersisa)
                </small>
            </div>

            <div class="card stat-card">
                <h3>Pengeluaran Hari Ini</h3>
                <div class="value text-danger">
                    Rp <?php echo number_format($daily_out, 0, ',', '.'); ?>
                </div>
            </div>
            <div class="card stat-card">
                <h3>Pemasukan Hari Ini</h3>
                <div class="value text-success">
                    Rp <?php echo number_format($daily_in, 0, ',', '.'); ?>
                </div>
            </div>
        </div>

        <div class="dashboard-layout-grid">
            <div class="card">
                <h2 class="card-title"><i class='bx bx-plus-circle'></i> Input Transaksi</h2>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Tanggal</label>
                        <input type="date" name="tanggal" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tipe</label>
                        <select id="jenis_transaksi" class="form-control" onchange="updateSubJenis()">
                            <option value="pengeluaran">Pengeluaran</option>
                            <option value="pemasukan">Pemasukan</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Kategori</label>
                        <select name="sub_jenis" id="sub_jenis" class="form-control" required>
                            <option value="">Pilih...</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nominal (Rp)</label>
                        <input type="number" name="total" class="form-control" required placeholder="0">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Catatan</label>
                        <input type="text" name="catatan" class="form-control" placeholder="Contoh: Makan Siang">
                    </div>

                    <button type="submit" name="submit_transaksi" class="btn btn-primary">Simpan Transaksi</button>
                </form>
            </div>

            <div class="card">
                <h2 class="card-title">Proporsi Bulan Ini</h2>
                
                <div class="monthly-summary">
                    <div class="m-item">
                        <span class="m-label">Pemasukan</span>
                        <span class="m-val text-success">Rp <?php echo number_format($month_in, 0, ',', '.'); ?></span>
                    </div>
                    <div class="m-item">
                        <span class="m-label">Pengeluaran</span>
                        <span class="m-val text-danger">Rp <?php echo number_format($month_out, 0, ',', '.'); ?></span>
                    </div>
                    <div class="m-item" style="border-top: 1px dashed #cbd5e1; margin-top:5px; padding-top:5px;">
                        <span class="m-label">Sisa Saldo</span>
                        <span class="m-val <?php echo ($saldo_bulan_ini < 0) ? 'text-danger' : 'text-primary'; ?>">
                            Rp <?php echo number_format($saldo_bulan_ini, 0, ',', '.'); ?>
                        </span>
                    </div>
                </div>

                <div class="chart-box">
                    <canvas id="proporsiChart"></canvas>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    const ctx = document.getElementById('proporsiChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($chart_data); ?>,
                backgroundColor: ['#22c55e', '#ef4444'],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { font: { family: "'Inter Tight', sans-serif" }, padding: 20, usePointStyle: true } }
            },
            cutout: '65%'
        }
    });

    const katPengeluaran = [<?php while($r = $cats_pengeluaran->fetch_assoc()){ echo "{id:{$r['id']}, name:'{$r['name']}'},"; } ?>];
    const katPemasukan = [<?php while($r = $cats_pemasukan->fetch_assoc()){ echo "{id:{$r['id']}, name:'{$r['name']}'},"; } ?>];

    function updateSubJenis() {
        const jenis = document.getElementById('jenis_transaksi').value;
        const subSelect = document.getElementById('sub_jenis');
        subSelect.innerHTML = '<option value="">Pilih Kategori...</option>';

        let data = (jenis === 'pengeluaran') ? katPengeluaran : katPemasukan;
        data.forEach(item => {
            let option = document.createElement('option');
            option.value = item.id;
            option.text = item.name;
            subSelect.add(option);
        });
    }
    updateSubJenis();
</script>

</body>
</html>