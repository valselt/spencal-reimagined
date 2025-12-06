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

$total_hari_bulan_ini = date('t'); 
$hari_ini_angka = date('j'); 
$sisa_hari = $total_hari_bulan_ini - $hari_ini_angka + 1; 

// --- PROSES INPUT TRANSAKSI (DENGAN PRG PATTERN) ---
if (isset($_POST['submit_transaksi'])) {
    $tgl = $_POST['tanggal'];
    $cat_id = $_POST['sub_jenis'];
    $note = htmlspecialchars($_POST['catatan']);
    $amount = str_replace('.', '', $_POST['total']); 

    $stmt = $conn->prepare("INSERT INTO transactions (user_id, category_id, date, note, amount) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iissd", $user_id, $cat_id, $tgl, $note, $amount);
    $stmt->execute();
    
    // --- SOLUSI: REDIRECT SETELAH POST ---
    header("Location: index.php"); 
    exit(); 
}

// --- QUERY DATA STATISTIK HARIAN ---
$q_daily_out = $conn->query("SELECT SUM(amount) as total FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id='$user_id' AND c.type='pengeluaran' AND t.date = '$today'");
$daily_out = $q_daily_out->fetch_assoc()['total'] ?? 0;

$q_daily_in = $conn->query("SELECT SUM(amount) as total FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id='$user_id' AND c.type='pemasukan' AND t.date = '$today'");
$daily_in = $q_daily_in->fetch_assoc()['total'] ?? 0;

// --- QUERY DATA STATISTIK BULANAN ---
$q_month_in = $conn->query("SELECT SUM(amount) as total FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id='$user_id' AND c.type='pemasukan' AND MONTH(t.date)='$cur_month' AND YEAR(t.date)='$cur_year'");
$month_in = $q_month_in->fetch_assoc()['total'] ?? 0;

$q_month_out = $conn->query("SELECT SUM(amount) as total FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id='$user_id' AND c.type='pengeluaran' AND MONTH(t.date)='$cur_month' AND YEAR(t.date)='$cur_year'");
$month_out = $q_month_out->fetch_assoc()['total'] ?? 0;

// --- LOGIKA HITUNG SISA SALDO ---
$saldo_bulan_ini = $month_in - $month_out;
$saldo_awal_hari_ini = $saldo_bulan_ini + $daily_out;

if ($sisa_hari > 0) {
    $jatah_hari_ini = $saldo_awal_hari_ini / $sisa_hari;
} else {
    $jatah_hari_ini = 0;
}
$sisa_bisa_pakai_hari_ini = $jatah_hari_ini - $daily_out;


// --- QUERY DATA PIE CHART ---
// A. Pemasukan
$query_pie_in = $conn->query("SELECT c.name, SUM(t.amount) as total FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id='$user_id' AND c.type='pemasukan' AND MONTH(t.date)='$cur_month' AND YEAR(t.date)='$cur_year' GROUP BY c.name ORDER BY total DESC");
$label_pie_in = [];
$data_pie_in = [];
while($row = $query_pie_in->fetch_assoc()){
    $label_pie_in[] = $row['name'];
    $data_pie_in[] = $row['total'];
}

// B. Pengeluaran
$query_pie_out = $conn->query("SELECT c.name, SUM(t.amount) as total FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id='$user_id' AND c.type='pengeluaran' AND MONTH(t.date)='$cur_month' AND YEAR(t.date)='$cur_year' GROUP BY c.name ORDER BY total DESC");
$label_pie_out = [];
$data_pie_out = [];
while($row = $query_pie_out->fetch_assoc()){
    $label_pie_out[] = $row['name'];
    $data_pie_out[] = $row['total'];
}

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
    <link rel="icon" href="https://cdn.ivanaldorino.web.id/spencal/spencal_favicon.png" type="image/png">
    <link rel="stylesheet" href="style.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .input-section { margin-bottom: 20px; }
        
        .charts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .monthly-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px; 
        }

        .stat-card-monthly {
            background: var(--card-bg);
            border-radius: var(--radius-md);
            padding: 20px;
            box-shadow: var(--shadow);
            border: 1px solid #e2e8f0;
            border-left: 4px solid var(--primary); 
        }
        .stat-card-monthly h4 { margin: 0 0 10px 0; color: var(--text-muted); font-size: 0.9rem; font-weight: 500;}
        .stat-card-monthly .val { font-size: 1.5rem; font-weight: 700; }

        @media (max-width: 768px) {
            .charts-grid { grid-template-columns: 1fr; }
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
            display: flex;
            justify-content: center;
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
                <li><a href="index.php" class="menu-item active"><i class='bx bxs-dashboard'></i> Dashboard</a></li>
                <li><a href="transactions.php" class="menu-item"><i class='bx bx-list-ul'></i> Riwayat Transaksi</a></li>
                <li>
                    <a href="transaction_table.php" class="menu-item">
                        <i class='bx bx-table'></i> Tabel Transaksi
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
                <h3>Pemasukan Hari Ini</h3>
                <div class="value text-success">Rp <?php echo number_format($daily_in, 0, ',', '.'); ?></div>
            </div>
            <div class="card stat-card">
                <h3>Pengeluaran Hari Ini</h3>
                <div class="value text-danger">Rp <?php echo number_format($daily_out, 0, ',', '.'); ?></div>
            </div>
            
        </div>

        <div class="input-section">
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
                        <input type="text" name="total" id="rupiah_input" class="form-control" required placeholder="0" onkeyup="formatRupiah(this)">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Catatan</label>
                        <input type="text" name="catatan" class="form-control" placeholder="Contoh: Makan Siang">
                    </div>

                    <button type="submit" name="submit_transaksi" class="btn btn-primary">Simpan Transaksi</button>
                </form>
            </div>
        </div>

        <div class="monthly-stats-grid">
            <div class="stat-card-monthly" style="border-left-color: #4f46e5;">
                <h4>Total Uang Bulan Ini</h4>
                <div class="val <?php echo ($saldo_bulan_ini < 0) ? 'text-danger' : 'text-primary'; ?>">
                    Rp <?php echo number_format($saldo_bulan_ini, 0, ',', '.'); ?>
                </div>
            </div>
            <div class="stat-card-monthly" style="border-left-color: #22c55e;">
                <h4>Pemasukan Bulan Ini</h4>
                <div class="val text-success">Rp <?php echo number_format($month_in, 0, ',', '.'); ?></div>
            </div>
            <div class="stat-card-monthly" style="border-left-color: #ef4444;">
                <h4>Pengeluaran Bulan Ini</h4>
                <div class="val text-danger">Rp <?php echo number_format($month_out, 0, ',', '.'); ?></div>
            </div>
            
        </div>

        <div class="charts-grid">
            <div class="card">
                <h2 class="card-title text-success"><i class='bx bxs-up-arrow-circle'></i> Pemasukan Bulan Ini</h2>
                <div class="chart-wrapper">
                    <?php if(empty($data_pie_in)): ?>
                        <p style="text-align:center; margin-top:100px; color:#cbd5e1;">Belum ada data pemasukan.</p>
                    <?php else: ?>
                        <canvas id="incomeChart"></canvas>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <h2 class="card-title text-danger"><i class='bx bxs-down-arrow-circle'></i> Pengeluaran Bulan Ini</h2>
                <div class="chart-wrapper">
                    <?php if(empty($data_pie_out)): ?>
                        <p style="text-align:center; margin-top:100px; color:#cbd5e1;">Belum ada data pengeluaran.</p>
                    <?php else: ?>
                        <canvas id="expenseChart"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </main>
</div>

<script>
    // --- 1. JS FORMAT RUPIAH ---
    function formatRupiah(element) {
        let value = element.value.replace(/[^,\d]/g, '').toString();
        let split = value.split(',');
        let sisa = split[0].length % 3;
        let rupiah = split[0].substr(0, sisa);
        let ribuan = split[0].substr(sisa).match(/\d{3}/gi);

        if (ribuan) {
            let separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }

        rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
        element.value = rupiah;
    }

    // --- 2. JS COLOR GENERATOR (SHADES) ---
    function generateGreenShades(count) {
        let colors = [];
        for (let i = 0; i < count; i++) {
            let lightness = 35 + (i * (50 / Math.max(count, 1))); 
            colors.push(`hsl(132, 60%, ${lightness}%)`);
        }
        return colors;
    }

    function generateRedShades(count) {
        let colors = [];
        for (let i = 0; i < count; i++) {
            let lightness = 45 + (i * (45 / Math.max(count, 1))); 
            colors.push(`hsl(350, 75%, ${lightness}%)`);
        }
        return colors;
    }

    // --- 3. CONFIG CHART PEMASUKAN ---
    <?php if(!empty($data_pie_in)): ?>
    const ctxIn = document.getElementById('incomeChart').getContext('2d');
    new Chart(ctxIn, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($label_pie_in); ?>,
            datasets: [{
                data: <?php echo json_encode($data_pie_in); ?>,
                backgroundColor: generateGreenShades(<?php echo count($data_pie_in); ?>),
                borderWidth: 1,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 20 } }
            }
        }
    });
    <?php endif; ?>

    // --- 4. CONFIG CHART PENGELUARAN ---
    <?php if(!empty($data_pie_out)): ?>
    const ctxOut = document.getElementById('expenseChart').getContext('2d');
    new Chart(ctxOut, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode($label_pie_out); ?>,
            datasets: [{
                data: <?php echo json_encode($data_pie_out); ?>,
                backgroundColor: generateRedShades(<?php echo count($data_pie_out); ?>),
                borderWidth: 1,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 20 } }
            }
        }
    });
    <?php endif; ?>

    // --- 5. LOGIC DROPDOWN ---
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