<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login/login.php"); exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
// Ambil email dari session atau query ulang jika perlu
$email_user = isset($_SESSION['email']) ? $_SESSION['email'] : $username . '@spencal.com';

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

// --- AMBIL DATA (DIGUNAKAN UNTUK CHART DAN DROPDOWN) ---
// Kategori untuk dropdown
$cats_pemasukan = $conn->query("SELECT * FROM categories WHERE user_id='$user_id' AND type='pemasukan'");
$cats_pengeluaran = $conn->query("SELECT * FROM categories WHERE user_id='$user_id' AND type='pengeluaran'");

// Hitung Total Pemasukan & Pengeluaran
$q_in = $conn->query("SELECT SUM(amount) as total FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id='$user_id' AND c.type='pemasukan'");
$total_masuk = $q_in->fetch_assoc()['total'] ?? 0;

$q_out = $conn->query("SELECT SUM(amount) as total FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.user_id='$user_id' AND c.type='pengeluaran'");
$total_keluar = $q_out->fetch_assoc()['total'] ?? 0;

// Hitung Saldo
$sisa_saldo = $total_masuk - $total_keluar;

// Data Chart Proporsi (Hanya transaksi)
$chart_data = [$total_masuk, $total_keluar];
$chart_labels = ['Pemasukan', 'Pengeluaran'];
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
                    <a href="dashboard.php" class="menu-item active">
                        <i class='bx bxs-dashboard'></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="#" class="menu-item" style="opacity:0.5; cursor:not-allowed;">
                        <i class='bx bxs-wallet-alt'></i> Data Tabungan
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
                            <?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($_SESSION['username']); ?></h4>
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
                <h3>Sisa Saldo</h3>
                <div class="value <?php echo ($sisa_saldo > 0) ? 'text-success' : (($sisa_saldo < 0) ? 'text-danger' : 'text-primary'); ?>">
                    Rp <?php echo number_format($sisa_saldo, 0, ',', '.'); ?>
                </div>
            </div>
            <div class="card stat-card">
                <h3>Total Pengeluaran</h3>
                <div class="value text-danger">
                    Rp <?php echo number_format($total_keluar, 0, ',', '.'); ?>
                </div>
            </div>
            <div class="card stat-card">
                <h3>Total Pemasukan</h3>
                <div class="value text-success">
                    Rp <?php echo number_format($total_masuk, 0, ',', '.'); ?>
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
                <h2 class="card-title">Proporsi Pemasukan vs Pengeluaran</h2>
                <div class="chart-box">
                    <canvas id="proporsiChart"></canvas>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    // --- LOGIC 1: CHART JS (Doughnut Modern) ---
    const ctx = document.getElementById('proporsiChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($chart_data); ?>,
                backgroundColor: ['#22c55e', '#ef4444'], // Hijau Pemasukan, Merah Pengeluaran
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
            cutout: '65%' // Membuat donat lebih tipis dan modern
        }
    });

    // --- LOGIC 2: DYNAMIC DROPDOWN ---
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
    // Init saat load
    updateSubJenis();
</script>

</body>
</html>