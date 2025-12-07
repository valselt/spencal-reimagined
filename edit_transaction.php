<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login/login.php"); exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

if (!isset($_GET['id'])) { header("Location: transactions.php"); exit(); }
$trx_id = $_GET['id'];

$stmt = $conn->prepare("SELECT t.*, c.type as cat_type FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.id = ? AND t.user_id = ?");
$stmt->bind_param("ii", $trx_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) { header("Location: transactions.php?msg=error"); exit(); }

if (isset($_POST['update_transaksi'])) {
    $tgl = $_POST['tanggal'];
    $cat_id = $_POST['sub_jenis'];
    $note = htmlspecialchars($_POST['catatan']);
    $amount = str_replace('.', '', $_POST['total']); 

    $update_stmt = $conn->prepare("UPDATE transactions SET date=?, category_id=?, note=?, amount=? WHERE id=? AND user_id=?");
    $update_stmt->bind_param("sissii", $tgl, $cat_id, $note, $amount, $trx_id, $user_id);
    
    if ($update_stmt->execute()) {
        header("Location: transactions.php?msg=updated");
        exit();
    }
}

$cats_pemasukan = $conn->query("SELECT * FROM categories WHERE user_id='$user_id' AND type='pemasukan'");
$cats_pengeluaran = $conn->query("SELECT * FROM categories WHERE user_id='$user_id' AND type='pengeluaran'");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Transaksi - Spencal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="https://cdn.ivanaldorino.web.id/spencal/spencal_favicon.png" type="image/png">
    
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
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
                <li><a href="transactions.php" class="menu-item active"><i class='bx bx-list-ul'></i> Riwayat Transaksi</a></li>
                <li>
                    <a href="transaction_table.php" class="menu-item">
                        <i class='bx bx-table'></i> Tabel Transaksi
                    </a>
                </li>
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
            <a href="transactions.php" style="color:var(--text-muted); font-size:0.9rem; margin-bottom:10px; display:inline-block;"><i class='bx bx-arrow-back'></i> Kembali ke Riwayat</a>
            <h1>Edit Transaksi</h1>
        </header>

        <div class="card" style="max-width: 600px;">
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Tanggal</label>
                    <input type="date" name="tanggal" class="form-control" required value="<?php echo $data['date']; ?>">
                </div>

                <input type="hidden" id="initial_cat_id" value="<?php echo $data['category_id']; ?>">
                <input type="hidden" id="initial_type" value="<?php echo $data['cat_type']; ?>">

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
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Nominal (Rp)</label>
                    <input type="text" name="total" class="form-control" required 
                           value="<?php echo number_format($data['amount'], 0, ',', '.'); ?>" 
                           onkeyup="formatRupiah(this)">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Catatan</label>
                    <input type="text" name="catatan" class="form-control" value="<?php echo htmlspecialchars($data['note']); ?>">
                </div>

                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button type="submit" name="update_transaksi" class="btn btn-primary">Simpan Perubahan</button>
                    <a href="transactions.php" class="btn" style="background:#f1f5f9; color:#64748b; text-align:center;">Batal</a>
                </div>
            </form>
        </div>
    </main>
</div>

<script>
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

    const katPengeluaran = [<?php while($r = $cats_pengeluaran->fetch_assoc()){ echo "{id:{$r['id']}, name:'{$r['name']}'},"; } ?>];
    const katPemasukan = [<?php while($r = $cats_pemasukan->fetch_assoc()){ echo "{id:{$r['id']}, name:'{$r['name']}'},"; } ?>];

    function updateSubJenis(selectedValue = null) {
        const jenis = document.getElementById('jenis_transaksi').value;
        const subSelect = document.getElementById('sub_jenis');
        subSelect.innerHTML = '<option value="">Pilih Kategori...</option>';

        let data = (jenis === 'pengeluaran') ? katPengeluaran : katPemasukan;
        data.forEach(item => {
            let option = document.createElement('option');
            option.value = item.id;
            option.text = item.name;
            if(selectedValue && item.id == selectedValue) { option.selected = true; }
            subSelect.add(option);
        });
    }

    document.addEventListener("DOMContentLoaded", function() {
        const initialType = document.getElementById('initial_type').value;
        const initialCatId = document.getElementById('initial_cat_id').value;
        document.getElementById('jenis_transaksi').value = initialType;
        updateSubJenis(initialCatId);
    });
</script>

</body>
</html>