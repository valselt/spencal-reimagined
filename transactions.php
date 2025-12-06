<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login/login.php"); exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// --- LOGIC HAPUS TRANSAKSI ---
if (isset($_GET['delete_id'])) {
    $del_id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $del_id, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['popup_status'] = 'success';
        $_SESSION['popup_message'] = 'Transaksi berhasil dihapus.';
    } else {
        $_SESSION['popup_status'] = 'error';
        $_SESSION['popup_message'] = 'Gagal menghapus transaksi.';
    }
    header("Location: transactions.php");
    exit();
}

// --- LOGIC MENANGKAP PESAN EDIT (Dari edit_transaction.php) ---
// Jika edit_transaction.php melempar ?msg=updated, kita ubah jadi Session Popup
if(isset($_GET['msg']) && $_GET['msg'] == 'updated'){
    $_SESSION['popup_status'] = 'success';
    $_SESSION['popup_message'] = 'Transaksi berhasil diperbarui.';
    header("Location: transactions.php"); // Refresh lagi untuk bersihkan URL
    exit();
}

// --- LOGIC AMBIL DATA TRANSAKSI ---
$query = "SELECT t.*, c.name as category_name, c.type 
          FROM transactions t 
          JOIN categories c ON t.category_id = c.id 
          WHERE t.user_id = '$user_id' 
          ORDER BY t.date DESC, t.id DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi - Spencal</title>
    <link rel="icon" href="https://cdn.ivanaldorino.web.id/spencal/spencal_favicon.png" type="image/png">
    <link rel="stylesheet" href="style.css">
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
            <h1>Riwayat Transaksi</h1>
            <p>Daftar lengkap pemasukan dan pengeluaran Anda.</p>
        </header>

        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
            <div class="card" style="padding: 15px; background: #dcfce7; color: #166534; margin-bottom: 20px;">
                Transaksi berhasil dihapus.
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'updated'): ?>
            <div class="card" style="padding: 15px; background: #dbeafe; color: #1e40af; margin-bottom: 20px;">
                Transaksi berhasil diperbarui.
            </div>
        <?php endif; ?>

        <div class="table-container">
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Tipe</th>
                            <th>Kategori</th>
                            <th>Catatan</th>
                            <th>Nominal</th>
                            <th style="text-align:center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d M Y', strtotime($row['date'])); ?></td>
                                    <td>
                                        <span class="badge <?php echo $row['type']; ?>">
                                            <?php echo ucfirst($row['type']); ?>
                                        </span>
                                    </td>
                                    <td style="font-weight: 500;"><?php echo htmlspecialchars($row['category_name']); ?></td>
                                    <td style="color: #64748b;"><?php echo htmlspecialchars($row['note']); ?></td>
                                    <td style="font-weight: 700; font-family: monospace; font-size: 1rem;">
                                        Rp <?php echo number_format($row['amount'], 0, ',', '.'); ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <a href="edit_transaction.php?id=<?php echo $row['id']; ?>" class="btn-action btn-edit" title="Edit">
                                            <i class='bx bx-pencil'></i>
                                        </a>
                                        <a href="transactions.php?delete_id=<?php echo $row['id']; ?>" class="btn-action btn-delete" title="Hapus" onclick="return confirm('Yakin ingin menghapus transaksi ini? Data akan hilang permanen.')">
                                            <i class='bx bx-trash'></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding: 40px; color: #94a3b8;">
                                    Belum ada transaksi. Silakan input di Dashboard.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php include 'popupcustom.php'; ?>
</body>
</html>