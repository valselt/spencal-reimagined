<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); exit();
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

// --- LOGIC MENANGKAP PESAN EDIT ---
if(isset($_GET['msg']) && $_GET['msg'] == 'updated'){
    $_SESSION['popup_status'] = 'success';
    $_SESSION['popup_message'] = 'Transaksi berhasil diperbarui.';
    header("Location: transactions.php"); 
    exit();
}

// --- LOGIC AMBIL DATA TRANSAKSI ---
$sort_option = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$order_sql = "";

switch ($sort_option) {
    case 'oldest': $order_sql = "t.date ASC, t.id ASC"; break;
    case 'amount_high': $order_sql = "t.amount DESC"; break;
    case 'amount_low': $order_sql = "t.amount ASC"; break;
    case 'newest': default: $order_sql = "t.date DESC, t.id DESC"; break;
}

$query = "SELECT t.*, c.name as category_name, c.type 
          FROM transactions t 
          JOIN categories c ON t.category_id = c.id 
          WHERE t.user_id = '$user_id' 
          ORDER BY $order_sql";
          
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Riwayat Transaksi - Spencal</title>
    <link rel="icon" href="https://cdn.ivanaldorino.web.id/spencal/spencal_favicon.png" type="image/png">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* --- CSS RESET KHUSUS MOBILE --- */
        @media (max-width: 768px) {
            /* 1. Paksa Body & HTML tidak melebihi layar */
            html, body {
                overflow-x: hidden;
                width: 100vw;
                margin: 0;
            }

            /* 2. Reset Main Content */
            .main-content {
                width: 100% !important;
                max-width: 100vw !important;
                padding: 15px !important;
                box-sizing: border-box;
                overflow-x: hidden;
            }

            /* 3. Reset Table Container & Wrapper */
            .table-container {
                background: transparent !important;
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                width: 100% !important;
            }

            .table-responsive {
                overflow: visible !important;
            }

            /* 4. RESET TABEL (Ini kuncinya: hapus min-width 600px dari style.css) */
            .custom-table {
                display: block;
                width: 100% !important;
                min-width: 0 !important; /* HAPUS MIN-WIDTH BAWAAN */
            }

            .custom-table thead { display: none; }

            /* --- STYLE KARTU TRANSAKSI --- */
            .custom-table tbody tr {
                display: flex;
                flex-direction: column;
                background: #fff;
                border-radius: 12px;
                padding: 15px;
                margin-bottom: 15px;
                border: 1px solid #e2e8f0;
                box-shadow: 0 4px 6px rgba(0,0,0,0.02);
                width: 100% !important;
                box-sizing: border-box;
                position: relative;
            }

            /* Reset Style TD */
            .custom-table td {
                display: block;
                padding: 0;
                border: none;
                width: 100%;
                text-align: left;
            }

            /* --- URUTAN TAMPILAN (ORDER) --- */

            /* 1. TANGGAL (Paling Atas) */
            .td-date {
                order: 1;
                font-size: 0.75rem;
                color: #94a3b8;
                margin-bottom: 5px;
                display: flex; align-items: center; gap: 5px;
            }

            /* 2. TIPE (Badge di Kanan Atas Absolute) */
            .td-type {
                position: absolute;
                top: 15px;
                right: 15px;
                width: auto !important;
                padding: 0;
            }
            .badge { 
                font-size: 0.7rem; 
                padding: 4px 8px; 
            }

            /* 3. KATEGORI (Judul Besar) */
            .td-category {
                order: 2;
                font-size: 1.1rem;
                font-weight: 700;
                color: var(--text-dark);
                margin-bottom: 5px;
                line-height: 1.2;
            }

            /* 4. CATATAN (Deskripsi) - Di bawah Judul */
            .td-note {
                order: 3;
                font-size: 0.9rem;
                color: #64748b;
                font-style: italic;
                margin-bottom: 12px; /* Jarak ke harga */
                line-height: 1.4;
                /* Pastikan teks panjang turun ke bawah, bukan melebar */
                white-space: normal !important; 
                word-wrap: break-word !important;
                display: block !important;
            }

            /* 5. NOMINAL (Harga) - Di bawah Deskripsi */
            .td-amount {
                order: 4;
                font-size: 1.2rem;
                font-weight: 800;
                padding-top: 10px;
                border-top: 1px dashed #e2e8f0 !important;
                margin-top: 5px;
                display: block;
            }

            /* 6. TOMBOL AKSI (Paling Bawah Sejajar Kanan) */
            .td-action {
                order: 5;
                margin-top: 12px;
                display: flex !important;
                flex-direction: row !important; /* Paksa Sejajar */
                justify-content: flex-end; /* Rata Kanan */
                gap: 8px;
            }

            /* Perbesar tombol sedikit agar mudah disentuh */
            .td-action .btn-action {
                width: 38px;
                height: 38px;
                display: flex; align-items: center; justify-content: center;
                border-radius: 8px;
                border: 1px solid #e2e8f0;
                background: #f8fafc;
                margin: 0;
            }
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
            <div class="header-between">
                <div>
                    <h1>Riwayat Transaksi</h1>
                    <p>Daftar lengkap pemasukan dan pengeluaran Anda.</p>
                </div>
                
                <div class="sort-wrapper">
                    <form method="GET">
                        <div class="select-wrapper">
                            <i class='bx bx-sort-alt-2'></i>
                            <select name="sort" class="sort-select" onchange="this.form.submit()">
                                <option value="newest" <?php echo ($sort_option == 'newest') ? 'selected' : ''; ?>>Terbaru</option>
                                <option value="oldest" <?php echo ($sort_option == 'oldest') ? 'selected' : ''; ?>>Terlama</option>
                                <option value="amount_high" <?php echo ($sort_option == 'amount_high') ? 'selected' : ''; ?>>Nominal Terbesar</option>
                                <option value="amount_low" <?php echo ($sort_option == 'amount_low') ? 'selected' : ''; ?>>Nominal Terkecil</option>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
        </header>

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
                                    <td class="td-date">
                                        <i class='bx bx-calendar'></i> <?php echo date('d M Y', strtotime($row['date'])); ?>
                                    </td>
                                    
                                    <td class="td-type">
                                        <span class="badge <?php echo $row['type']; ?>">
                                            <?php echo ucfirst($row['type']); ?>
                                        </span>
                                    </td>
                                    
                                    <td class="td-category">
                                        <?php echo htmlspecialchars($row['category_name']); ?>
                                    </td>
                                    
                                    <td class="td-note">
                                        <?php echo (!empty($row['note'])) ? htmlspecialchars($row['note']) : '<i>Tidak ada catatan</i>'; ?>
                                    </td>
                                    
                                    <td class="td-amount <?php echo ($row['type'] == 'pengeluaran') ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo ($row['type'] == 'pengeluaran' ? '-' : '+'); ?> Rp <?php echo number_format($row['amount'], 0, ',', '.'); ?>
                                    </td>
                                    
                                    <td class="td-action">
                                        <?php if(!empty($row['photo_url'])): ?>
                                            <a href="<?php echo htmlspecialchars($row['photo_url']); ?>" target="_blank" class="btn-action" style="color: #6366f1;" title="Lihat Foto">
                                                <i class='bx bx-image'></i>
                                            </a>
                                        <?php endif; ?>

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