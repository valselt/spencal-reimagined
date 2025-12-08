<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    // TAMPILKAN HALAMAN LANDING SEDERHANA
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selamat Datang di Spencal</title>
    <link rel="icon" href="https://cdn.ivanaldorino.web.id/spencal/spencal_favicon.png" type="image/png">
    
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/airbnb.css">
    <style>
        body { display: flex; align-items: center; justify-content: center; height: 100vh; background: #f1f5f9; font-family: 'DM Sans', sans-serif; }
        .welcome-card { background: white; padding: 40px; border-radius: 16px; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 400px; width: 90%; }
        .logo { max-width: 150px; margin-bottom: 20px; }
        .btn-login-valselt {
            background: #4f46e5; color: white; padding: 12px 20px; border-radius: 8px; 
            text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;
            transition: 0.2s;
        }
        .btn-login-valselt:hover { background: #4338ca; }
        .flatpickr-current-month .flatpickr-monthDropdown-months {
             font-weight: 700; /* Bulan lebih tebal */
        }
        .flatpickr-today-btn {
            background: #4f46e5; 
            color: white; 
            padding: 12px; 
            text-align: center; 
            font-weight: 600; 
            cursor: pointer;
            border-top: 1px solid #e2e8f0;
            transition: 0.2s;
        }
        .flatpickr-today-btn:hover {
            background: #4338ca;
        }
        /* Agar sudut bawah membulat mengikuti tema */
        .flatpickr-calendar { overflow: hidden; }
    </style>
</head>
<body>
    <div class="welcome-card">
        <img src="http://cdn.ivanaldorino.web.id/spencal/spencal_logo.png" alt="Spencal" class="logo">
        <h2 style="margin-bottom: 10px; color: #1e293b;">Kelola Keuanganmu</h2>
        <p style="color: #64748b; margin-bottom: 30px;">Catat pengeluaran dan pemasukan dengan mudah.</p>
        
        <a href="login/login.php" class="btn-login-valselt">
            <i class='bx bx-log-in-circle'></i> Login dengan Valselt ID
        </a>
    </div>
</body>
</html>
<?php
    exit(); 
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

// --- PROSES INPUT TRANSAKSI ---
if (isset($_POST['submit_transaksi'])) {
    $tgl = $_POST['tanggal'];
    $cat_id = $_POST['sub_jenis'];
    $note = htmlspecialchars($_POST['catatan']);
    $amount = str_replace('.', '', $_POST['total']); 

    $stmt = $conn->prepare("INSERT INTO transactions (user_id, category_id, date, note, amount) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iissd", $user_id, $cat_id, $tgl, $note, $amount);
    
    if($stmt->execute()){
        $_SESSION['popup_status'] = 'success';
        $_SESSION['popup_message'] = 'Transaksi berhasil disimpan!';
    } else {
        $_SESSION['popup_status'] = 'error';
        $_SESSION['popup_message'] = 'Gagal menyimpan transaksi.';
    }
    header("Location: index.php"); 
    exit(); 
}

// --- QUERY DATA STATISTIK HARIAN (TOTAL) ---
$q_daily_out = $conn->query("SELECT SUM(amount) as total FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id='$user_id' AND c.type='pengeluaran' AND t.date = '$today'");
$daily_out = $q_daily_out->fetch_assoc()['total'] ?? 0;

$q_daily_in = $conn->query("SELECT SUM(amount) as total FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id='$user_id' AND c.type='pemasukan' AND t.date = '$today'");
$daily_in = $q_daily_in->fetch_assoc()['total'] ?? 0;

// --- QUERY DATA RINCIAN HARIAN (LIST TRANSAKSI) ---
$q_list_out = $conn->query("SELECT t.amount, t.note, c.name as category_name FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id='$user_id' AND c.type='pengeluaran' AND t.date = '$today' ORDER BY t.id DESC");
$q_list_in = $conn->query("SELECT t.amount, t.note, c.name as category_name FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id='$user_id' AND c.type='pemasukan' AND t.date = '$today' ORDER BY t.id DESC");

// --- QUERY DATA STATISTIK BULANAN ---
$q_month_in = $conn->query("SELECT SUM(amount) as total FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id='$user_id' AND c.type='pemasukan' AND MONTH(t.date)='$cur_month' AND YEAR(t.date)='$cur_year'");
$month_in = $q_month_in->fetch_assoc()['total'] ?? 0;

$q_month_out = $conn->query("SELECT SUM(amount) as total FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id='$user_id' AND c.type='pengeluaran' AND MONTH(t.date)='$cur_month' AND YEAR(t.date)='$cur_year'");
$month_out = $q_month_out->fetch_assoc()['total'] ?? 0;

$saldo_bulan_ini = $month_in - $month_out;
$saldo_awal_hari_ini = $saldo_bulan_ini + $daily_out;

if ($sisa_hari > 0) {
    $jatah_hari_ini = $saldo_awal_hari_ini / $sisa_hari;
} else {
    $jatah_hari_ini = 0;
}
$sisa_bisa_pakai_hari_ini = $jatah_hari_ini - $daily_out;


// --- CHART DATA ---
$query_pie_in = $conn->query("SELECT c.name, SUM(t.amount) as total FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id='$user_id' AND c.type='pemasukan' AND MONTH(t.date)='$cur_month' AND YEAR(t.date)='$cur_year' GROUP BY c.name ORDER BY total DESC");
$label_pie_in = []; $data_pie_in = [];
while($row = $query_pie_in->fetch_assoc()){ $label_pie_in[] = $row['name']; $data_pie_in[] = $row['total']; }

$query_pie_out = $conn->query("SELECT c.name, SUM(t.amount) as total FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id='$user_id' AND c.type='pengeluaran' AND MONTH(t.date)='$cur_month' AND YEAR(t.date)='$cur_year' GROUP BY c.name ORDER BY total DESC");
$label_pie_out = []; $data_pie_out = [];
while($row = $query_pie_out->fetch_assoc()){ $label_pie_out[] = $row['name']; $data_pie_out[] = $row['total']; }

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
    
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://npmcdn.com/flatpickr/dist/themes/airbnb.css">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .input-section { margin-bottom: 20px; }
        .charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .monthly-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card-monthly { background: var(--card-bg); border-radius: var(--radius-md); padding: 20px; box-shadow: var(--shadow); border: 1px solid #e2e8f0; border-left: 4px solid var(--primary); }
        .stat-card-monthly h4 { margin: 0 0 10px 0; color: var(--text-muted); font-size: 0.9rem; font-weight: 500;}
        .stat-card-monthly .val { font-size: 1.5rem; font-weight: 700; }
        
        .chart-wrapper { position: relative; height: 300px; display: flex; justify-content: center; }

        .daily-details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .detail-list { list-style: none; padding: 0; margin: 0; }
        .detail-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
        .detail-item:last-child { border-bottom: none; }
        .detail-info { display: flex; flex-direction: column; }
        .detail-cat { font-weight: 600; color: var(--text-dark); }
        .detail-note { font-size: 0.8rem; color: var(--text-muted); }
        .detail-amount { font-weight: 700; }

        .section-title { margin: 30px 0 15px 0; font-size: 1.2rem; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px; }

        @media (max-width: 968px) { 
            .charts-grid, .daily-details-grid { grid-template-columns: 1fr; } 
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
            $q_user = $conn_valselt->query("SELECT profile_pic, username, email FROM users WHERE id='$user_id'");
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
            <div id="live-clock" class="clock-simple">
                <div class="date-small" id="date-text">Memuat...</div>
                <div class="time-large" id="time-text">00:00:00</div>
            </div>
        </header>

        <div class="stats-grid">
            <div class="card stat-card stat-saldo">
                <h3>Sisa Saldo Hari Ini</h3>
                <div class="value">
                    Rp <?php echo number_format($sisa_bisa_pakai_hari_ini, 0, ',', '.'); ?>
                    <span style="font-size: 0.9rem; font-weight: 500; color: rgba(255,255,255,0.8);">
                        dari Rp <?php echo number_format($jatah_hari_ini, 0, ',', '.'); ?>
                    </span>
                </div>
                <small style="font-size:0.75rem; margin-top:5px; display:block;">
                    (Jatah Harian untuk <?php echo $sisa_hari; ?> hari tersisa)
                </small>
                <div class="stat-icon"><i class='bx bx-wallet'></i></div>
            </div>
            
            <div class="card stat-card stat-out">
                <h3>Total Pengeluaran Hari Ini</h3>
                <div class="value">Rp <?php echo number_format($daily_out, 0, ',', '.'); ?></div>
                <div class="stat-icon"><i class='bx bx-trending-down'></i></div>
            </div>
            
            <div class="card stat-card stat-in">
                <h3>Total Pemasukan Hari Ini</h3>
                <div class="value">Rp <?php echo number_format($daily_in, 0, ',', '.'); ?></div>
                <div class="stat-icon"><i class='bx bx-trending-up'></i></div>
            </div>
        </div>

        <div class="input-section">
            <div class="card">
                <h2 class="card-title"><i class='bx bx-plus-circle'></i> Input Transaksi</h2>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Tanggal</label>
                        <input type="text" name="tanggal" class="form-control" required placeholder="Pilih Tanggal...">
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

        <h2 class="section-title"><i class='bx bx-list-check'></i> Rincian Hari Ini</h2>
        
        <div class="daily-details-grid">
            <div class="card">
                <h4 style="margin-bottom: 15px; color: var(--text-muted); display:flex; align-items:center; gap:5px;">
                    <i class='bx bx-down-arrow-circle text-danger'></i> Rincian Pengeluaran
                </h4>
                <ul class="detail-list">
                    <?php if ($q_list_out->num_rows > 0): ?>
                        <?php while($row = $q_list_out->fetch_assoc()): ?>
                            <li class="detail-item">
                                <div class="detail-info">
                                    <span class="detail-cat"><?php echo htmlspecialchars($row['category_name']); ?></span>
                                    <span class="detail-note"><?php echo htmlspecialchars($row['note']); ?></span>
                                </div>
                                <span class="detail-amount text-danger">- Rp <?php echo number_format($row['amount'], 0, ',', '.'); ?></span>
                            </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li style="text-align:center; color:#cbd5e1; padding:10px;">Belum ada pengeluaran hari ini.</li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="card">
                <h4 style="margin-bottom: 15px; color: var(--text-muted); display:flex; align-items:center; gap:5px;">
                    <i class='bx bx-up-arrow-circle text-success'></i> Rincian Pemasukan
                </h4>
                <ul class="detail-list">
                    <?php if ($q_list_in->num_rows > 0): ?>
                        <?php while($row = $q_list_in->fetch_assoc()): ?>
                            <li class="detail-item">
                                <div class="detail-info">
                                    <span class="detail-cat"><?php echo htmlspecialchars($row['category_name']); ?></span>
                                    <span class="detail-note"><?php echo htmlspecialchars($row['note']); ?></span>
                                </div>
                                <span class="detail-amount text-success">+ Rp <?php echo number_format($row['amount'], 0, ',', '.'); ?></span>
                            </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li style="text-align:center; color:#cbd5e1; padding:10px;">Belum ada pemasukan hari ini.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <h2 class="section-title"><i class='bx bx-calendar'></i> Rincian Bulan Ini</h2>

        <div class="monthly-stats-grid">
            <div class="stat-card-monthly" style="border-left-color: #22c55e;">
                <h4>Pemasukan Bulan Ini</h4>
                <div class="val text-success">Rp <?php echo number_format($month_in, 0, ',', '.'); ?></div>
            </div>
            <div class="stat-card-monthly" style="border-left-color: #ef4444;">
                <h4>Pengeluaran Bulan Ini</h4>
                <div class="val text-danger">Rp <?php echo number_format($month_out, 0, ',', '.'); ?></div>
            </div>
            <div class="stat-card-monthly" style="border-left-color: #4f46e5;">
                <h4>Total Uang Bulan Ini</h4>
                <div class="val <?php echo ($saldo_bulan_ini < 0) ? 'text-danger' : 'text-primary'; ?>">
                    Rp <?php echo number_format($saldo_bulan_ini, 0, ',', '.'); ?>
                </div>
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
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/id.js"></script>

<script>
    function startLiveClock() {
        const timeDisplay = document.getElementById('time-text');
        const dateDisplay = document.getElementById('date-text');
        function update() {
            const now = new Date();
            const dateOptions = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
            dateDisplay.innerText = now.toLocaleDateString('id-ID', dateOptions);
            const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };
            timeDisplay.innerText = now.toLocaleTimeString('id-ID', timeOptions).replace(/\./g, ':');
        }
        setInterval(update, 1000); update();
    }
    startLiveClock();

    function formatRupiah(element) {
        let value = element.value.replace(/[^,\d]/g, '').toString();
        let split = value.split(',');
        let sisa = split[0].length % 3;
        let rupiah = split[0].substr(0, sisa);
        let ribuan = split[0].substr(sisa).match(/\d{3}/gi);
        if (ribuan) { let separator = sisa ? '.' : ''; rupiah += separator + ribuan.join('.'); }
        rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
        element.value = rupiah;
    }

    // --- MODERN CHART COLORS ---
    function generateGreenShades(count) {
        let colors = [];
        for (let i = 0; i < count; i++) {
            // Gradasi Hijau Teal-ish
            let lightness = 40 + (i * (40 / Math.max(count, 1)));
            colors.push(`hsl(160, 80%, ${lightness}%)`);
        }
        return colors;
    }

    function generateRedShades(count) {
        let colors = [];
        for (let i = 0; i < count; i++) {
            // Gradasi Merah Rose/Pink-ish
            let lightness = 50 + (i * (40 / Math.max(count, 1)));
            colors.push(`hsl(340, 90%, ${lightness}%)`);
        }
        return colors;
    }

    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '75%', // Bikin jadi Doughnut (Bolong Tengah)
        plugins: {
            legend: { 
                position: 'bottom', 
                labels: { 
                    usePointStyle: true, // Pake buletan bukan kotak
                    padding: 20,
                    font: {
                        family: "'DM Sans', sans-serif",
                        size: 11
                    }
                } 
            }
        },
        elements: {
            arc: {
                borderRadius: 15, // Rounded Corners
                borderWidth: 2
            }
        },
        layout: {
            padding: 10
        }
    };

    <?php if(!empty($data_pie_in)): ?>
    const ctxIn = document.getElementById('incomeChart').getContext('2d');
    new Chart(ctxIn, {
        type: 'doughnut', // Ganti jadi Doughnut
        data: { 
            labels: <?php echo json_encode($label_pie_in); ?>, 
            datasets: [{ 
                data: <?php echo json_encode($data_pie_in); ?>, 
                backgroundColor: generateGreenShades(<?php echo count($data_pie_in); ?>), 
                borderWidth: 2, 
                borderColor: '#ffffff',
                hoverOffset: 10 // Efek Pop-out saat hover
            }] 
        },
        options: commonOptions
    });
    <?php endif; ?>

    <?php if(!empty($data_pie_out)): ?>
    const ctxOut = document.getElementById('expenseChart').getContext('2d');
    new Chart(ctxOut, {
        type: 'doughnut', // Ganti jadi Doughnut
        data: { 
            labels: <?php echo json_encode($label_pie_out); ?>, 
            datasets: [{ 
                data: <?php echo json_encode($data_pie_out); ?>, 
                backgroundColor: generateRedShades(<?php echo count($data_pie_out); ?>), 
                borderWidth: 2, 
                borderColor: '#ffffff',
                hoverOffset: 10
            }] 
        },
        options: commonOptions
    });
    <?php endif; ?>

    const katPengeluaran = [<?php while($r = $cats_pengeluaran->fetch_assoc()){ echo "{id:{$r['id']}, name:'{$r['name']}'},"; } ?>];
    const katPemasukan = [<?php while($r = $cats_pemasukan->fetch_assoc()){ echo "{id:{$r['id']}, name:'{$r['name']}'},"; } ?>];

    function updateSubJenis() {
        const jenis = document.getElementById('jenis_transaksi').value;
        const subSelect = document.getElementById('sub_jenis');
        subSelect.innerHTML = '<option value="">Pilih Kategori...</option>';
        let data = (jenis === 'pengeluaran') ? katPengeluaran : katPemasukan;
        data.forEach(item => { let option = document.createElement('option'); option.value = item.id; option.text = item.name; subSelect.add(option); });
    }
    updateSubJenis();

    let lastDate = new Date().toLocaleDateString('id-ID');

    setInterval(() => {
        let now = new Date().toLocaleDateString('id-ID');
        
        // Jika tanggal berubah → ambil ulang data dari server
        if (now !== lastDate) {
            fetch('api_refresh_daily.php')
                .then(res => res.json())
                .then(data => {
                    document.querySelector('.stat-saldo .value').innerHTML =
                        `Rp ${new Intl.NumberFormat('id-ID').format(data.sisa)} 
                        <span>dari Rp ${new Intl.NumberFormat('id-ID').format(data.jatah)}</span>`;

                    document.querySelector('.stat-out .value').innerHTML =
                        `Rp ${new Intl.NumberFormat('id-ID').format(data.daily_out)}`;

                    document.querySelector('.stat-in .value').innerHTML =
                        `Rp ${new Intl.NumberFormat('id-ID').format(data.daily_in)}`;

                    lastDate = now;
                });
        }
    }, 1000 * 60); // cek setiap 1 menit

    flatpickr("input[name='tanggal']", {
        dateFormat: "Y-m-d",        // Format ke Database (tetap angka)
        altInput: true,             // Tampilkan format berbeda ke user
        altFormat: "l, j F Y",      // Format Modern: "Senin, 8 Desember 2025"
        locale: "id",               // Bahasa Indonesia
        defaultDate: "today",       // Default hari ini
        animate: true,              // Animasi buka/tutup
        disableMobile: "true",      // PENTING: Paksa tema modern muncul di HP (bukan kalender native HP)
        
        // FUNGSI MEMBUAT TOMBOL "HARI INI"
        onReady: function(selectedDates, dateStr, instance) {
            // 1. Bikin elemen tombol
            const todayBtn = document.createElement("div");
            todayBtn.innerHTML = "Pilih Hari Ini";
            todayBtn.className = "flatpickr-today-btn";
            
            // 2. Aksi saat tombol diklik
            todayBtn.onclick = function() {
                instance.setDate(new Date()); // Set ke sekarang
                instance.close();             // Tutup kalender
            };

            // 3. Masukkan tombol ke dalam kalender
            instance.calendarContainer.appendChild(todayBtn);
        }
    });
</script>

<?php include 'popupcustom.php'; ?>
</body>
</html>