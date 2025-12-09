<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); exit();
}

$user_id = $_SESSION['user_id'];

// --- LOGIC 1: UPDATE PROFILE (MODIFIKASI UNTUK BASE64) ---
if (isset($_POST['update_profile'])) {
    $new_username = htmlspecialchars($_POST['username']);
    $new_email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $new_pass = $_POST['password'];
    
    // Upload Foto Logic
    if (!empty($_POST['cropped_image'])) {
        $data = $_POST['cropped_image'];
        list($type, $data) = explode(';', $data);
        list(, $data)      = explode(',', $data);
        $data = base64_decode($data);

        $image = imagecreatefromstring($data);
        
        if ($image !== false) {
            ob_start();
            imagewebp($image, null, 80);
            $webp_data = ob_get_contents();
            ob_end_clean();
            imagedestroy($image);

            $timestamp = date('Y-m-d_H-i-s');
            
            // PERBAIKAN DI SINI: Hapus 'spencal/' di depan agar tidak double folder
            $s3_key = "photoprofile/{$timestamp}_{$user_id}.webp";

            try {
                $result = $s3->putObject([
                    'Bucket' => $minio_bucket,
                    'Key'    => $s3_key,
                    'Body'   => $webp_data,
                    'ContentType' => 'image/webp',
                    'ACL'    => 'public-read'
                ]);
                $foto_url = $result['ObjectURL'];
                $conn->query("UPDATE users SET profile_pic='$foto_url' WHERE id='$user_id'");
            } catch (AwsException $e) {
                $_SESSION['popup_status'] = 'error';
                $_SESSION['popup_message'] = "Upload Gagal: " . $e->getMessage();
            }
        }
    }

    $conn->query("UPDATE users SET username='$new_username', email='$new_email' WHERE id='$user_id'");

    if (!empty($new_pass)) {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password='$hash' WHERE id='$user_id'");
    }
    
    $_SESSION['username'] = $new_username;
    $_SESSION['popup_status'] = 'success';
    $_SESSION['popup_message'] = 'Profil berhasil diperbarui!';

    header("Location: profile.php");
    exit();
}

// --- LOGIC 2: HAPUS AKUN ---
if (isset($_POST['delete_account'])) {
    $conn->query("DELETE FROM transactions WHERE user_id='$user_id'");
    $conn->query("DELETE FROM categories WHERE user_id='$user_id'");
    $conn->query("DELETE FROM users WHERE id='$user_id'");
    session_destroy();
    session_start();
    $_SESSION['popup_status'] = 'success';
    $_SESSION['popup_message'] = 'Akun Anda berhasil dihapus permanen.';
    header("Location: login.php"); exit();
}

// --- LOGIC 3: CRUD KATEGORI ---
$active_type = isset($_GET['type']) ? $_GET['type'] : 'pengeluaran';

// A. LOGIC EDIT KATEGORI (UPDATE)
if (isset($_POST['edit_cat'])) {
    $id_cat = $_POST['edit_id'];
    $new_name = htmlspecialchars($_POST['edit_name']);
    $type_redirect = $_POST['edit_type'];
    
    // Tangkap data baru
    $icon = $_POST['edit_icon'];
    $is_shortcut = isset($_POST['edit_shortcut']) ? 1 : 0;

    $stmt = $conn->prepare("UPDATE categories SET name = ?, icon = ?, is_shortcut = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ssiii", $new_name, $icon, $is_shortcut, $id_cat, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['popup_status'] = 'success'; $_SESSION['popup_message'] = 'Kategori berhasil diperbarui!';
    } else {
        $_SESSION['popup_status'] = 'error'; $_SESSION['popup_message'] = 'Gagal mengubah kategori.';
    }
    header("Location: profile.php?tab=kategori&type=$type_redirect"); exit();
}

// B. LOGIC TAMBAH KATEGORI (UPDATE)
if (isset($_POST['add_cat'])) {
    $type = $_POST['cat_type']; 
    $name = htmlspecialchars($_POST['cat_name']);
    $def_icon = 'category'; 
    $def_short = 0;

    $conn->query("INSERT INTO categories (user_id, type, name, icon, is_shortcut) VALUES ('$user_id', '$type', '$name', '$def_icon', '$def_short')");
    $_SESSION['popup_status'] = 'success'; $_SESSION['popup_message'] = 'Kategori berhasil ditambahkan!';
    header("Location: profile.php?tab=kategori&type=$type"); exit();
}

// C. LOGIC HAPUS KATEGORI (DENGAN PROTEKSI)
if (isset($_GET['del_cat'])) {
    $id_cat = $conn->real_escape_string($_GET['del_cat']); // Sanitasi input

    // 1. Ambil info kategori dulu (untuk redirect type)
    $q_cek = $conn->query("SELECT type, name FROM categories WHERE id='$id_cat' AND user_id='$user_id'");
    
    if ($row = $q_cek->fetch_assoc()) {
        $type_saat_hapus = $row['type'];
        $nama_kategori = htmlspecialchars($row['name']);

        // 2. CEK PENGGUNAAN DI TABEL TRANSAKSI
        // Hitung berapa kali kategori ini muncul di transaksi user ini
        $cek_transaksi = $conn->query("SELECT COUNT(*) AS total FROM transactions WHERE category_id='$id_cat' AND user_id='$user_id'");
        $data_transaksi = $cek_transaksi->fetch_assoc();
        $jumlah_terpakai = $data_transaksi['total'];

        if ($jumlah_terpakai > 0) {
            // SKENARIO GAGAL: Kategori sedang dipakai
            $_SESSION['popup_status'] = 'error';
            $_SESSION['popup_message'] = "<b>Gagal Hapus!</b><br>Kategori '{$nama_kategori}' sedang digunakan di <b>{$jumlah_terpakai} transaksi</b>.<br><br>Mohon edit transaksi tersebut ke kategori lain terlebih dahulu melalui menu Riwayat Transaksi.";
        } else {
            // SKENARIO SUKSES: Tidak ada transaksi yang pakai, aman dihapus
            $del = $conn->query("DELETE FROM categories WHERE id='$id_cat' AND user_id='$user_id'");
            if ($del) {
                $_SESSION['popup_status'] = 'success';
                $_SESSION['popup_message'] = 'Kategori berhasil dihapus!';
            } else {
                $_SESSION['popup_status'] = 'error';
                $_SESSION['popup_message'] = 'Terjadi kesalahan sistem saat menghapus.';
            }
        }

        // Redirect kembali ke tab kategori
        header("Location: profile.php?tab=kategori&type=$type_saat_hapus");
        exit();
    }
}

$u_res = $conn_valselt->query("SELECT * FROM users WHERE id='$user_id'");
$user_data = $u_res->fetch_assoc();

$tab_active = isset($_GET['tab']) ? $_GET['tab'] : 'profil';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Akun - Spencal</title>
    <link rel="icon" href="https://cdn.ivanaldorino.web.id/spencal/spencal_favicon.png" type="image/png">
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,1,0" />
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* Style Tambahan untuk Pilihan Icon */
        .icon-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(40px, 1fr)); /* Fit otomatis */
            gap: 10px; 
            margin-top: 10px; 
            max-height: 200px; /* Tinggi maksimal scroll */
            overflow-y: auto;  /* Scroll vertikal saja */
            overflow-x: hidden; /* Matikan scroll horizontal */
            padding-right: 5px; /* Spasi untuk scrollbar */
        }
        
        /* Style Search Bar Icon */
        .icon-search-wrapper {
            position: relative;
            margin-bottom: 10px;
        }
        .icon-search-wrapper i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        .icon-search-input {
            width: 100%;
            padding: 8px 10px 8px 35px; /* Padding kiri untuk ikon */
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            outline: none;
        }
        .icon-search-input:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
        }

        .icon-option { 
            display: flex; align-items: center; justify-content: center; 
            padding: 8px; border: 1px solid #e2e8f0; border-radius: 8px; 
            cursor: pointer; transition: 0.2s; font-size: 1.4rem; color: #64748b;
        }
        .icon-option:hover { background: #f1f5f9; }
        .icon-option.selected { background: #4f46e5; color: white; border-color: #4f46e5; }
        .shortcut-badge { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; margin-left: 5px; }

        .chip-btn {
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #64748b;
            padding: 4px 10px;       /* Padding Dikecilkan */
            border-radius: 20px;
            font-size: 0.7rem;       /* Font Dikecilkan */
            cursor: pointer;
            transition: 0.2s;
            white-space: nowrap;
            font-family: 'DM Sans', sans-serif;
            font-weight: 600;
        }
        .chip-btn:hover { background: #f1f5f9; }
        .chip-btn.active {
            background: #4f46e5;
            color: white;
            border-color: #4f46e5;
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
                <li><a href="transaction_table.php" class="menu-item"><i class='bx bx-table'></i> Tabel Transaksi</a></li>
            </ul>
        </div>
        <div class="sidebar-profile">
            <a href="profile.php" class="profile-info-link" title="Edit Profil">
                <div class="user-info">
                    <?php if(isset($user_data['profile_pic']) && $user_data['profile_pic']): ?>
                        <img src="<?php echo $user_data['profile_pic']; ?>" class="user-avatar" style="object-fit:cover; width:40px; height:40px; border-radius:50%; margin-right:10px;">
                    <?php else: ?>
                        <div class="user-avatar"><?php echo strtoupper(substr($user_data['username'], 0, 2)); ?></div>
                    <?php endif; ?>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($user_data['username']); ?></h4>
                        <small>Edit Profil</small>
                    </div>
                </div>
            </a>
            <a href="logout.php" class="logout-btn" title="Keluar / Logout"><i class='bx bx-log-out-circle'></i></a>
        </div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <h1>Pengaturan Akun</h1>
        </header>

        <div class="card">
            <div class="tab-nav">
                <button class="tab-btn <?php echo ($tab_active !== 'kategori') ? 'active' : ''; ?>" onclick="openTab(event, 'EditProfil')">
                    <i class='bx bx-user'></i> Edit Profil
                </button>
                
                <button class="tab-btn <?php echo ($tab_active === 'kategori') ? 'active' : ''; ?>" onclick="openTab(event, 'AturKategori')">
                    <i class='bx bx-layer'></i> Atur Kategori
                </button>
            </div>

            <div id="EditProfil" class="tab-content" style="display: <?php echo ($tab_active !== 'kategori') ? 'block' : 'none'; ?>;">
                <a href="https://valseltid.ivanaldorino.web.id/index.php" id="btn-valselt" class="btn btn-primary" target="_blank">
                    Edit Profil & Ganti Foto di Valselt ID <i class='bx bx-link-external'></i>
                </a>
                
                </div>

            <div id="AturKategori" class="tab-content" style="display: <?php echo ($tab_active === 'kategori') ? 'block' : 'none'; ?>;">
                <h3 style="margin-bottom:20px;">Kelola Kategori Transaksi</h3>
                
                <form method="POST" id="form-kategori" style="display:flex; gap:10px; margin-bottom:20px; align-items:flex-end;">
                    <div style="flex:1;">
                        <label class="form-label">Tipe</label>
                        <select name="cat_type" id="filterTipe" class="form-control" onchange="filterKategori()">
                            <option value="pengeluaran" <?php echo ($active_type == 'pengeluaran') ? 'selected' : ''; ?>>Pengeluaran</option>
                            <option value="pemasukan" <?php echo ($active_type == 'pemasukan') ? 'selected' : ''; ?>>Pemasukan</option>
                        </select>
                    </div>
                    <div style="flex:2;">
                        <label class="form-label">Nama Kategori</label>
                        <input type="text" name="cat_name" class="form-control" placeholder="Misal: Investasi" required>
                    </div>
                    <button type="submit" name="add_cat" class="btn btn-primary" style="width:auto;"><i class='bx bx-plus'></i> Tambah</button>
                </form>

                <div style="max-height: 400px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:8px;">
                    <table style="width:100%; border-collapse:collapse;">
                        <?php 
                        // Query ambil semua kategori
                        $cats = $conn->query("SELECT * FROM categories WHERE user_id='$user_id' ORDER BY type DESC, name ASC");
                        
                        // Hitung jumlah baris yang tampil untuk logic empty state
                        $visible_count = 0; 

                        while($c = $cats->fetch_assoc()): 
                            // LOGIKA UTAMA: Cek apakah tipe kategori ini sama dengan dropdown yang aktif?
                            $is_match = ($c['type'] == $active_type);
                            
                            // Jika cocok, tampilkan. Jika beda, sembunyikan pakai CSS (display:none)
                            $row_style = $is_match ? '' : 'display:none;';
                            
                            if ($is_match) $visible_count++;
                        ?>
                        <tr class="kategori-row" data-type="<?php echo $c['type']; ?>" style="border-bottom:1px solid #f1f5f9; <?php echo $row_style; ?>">
                            <td style="padding:15px; width: 50px;">
                                <?php 
                                    $iconName = $c['icon'] ?? 'bx-category';
                                    if (strpos($iconName, 'bx-') === 0) {
                                        echo "<i class='bx $iconName' style='font-size: 1.5rem; color: #64748b;'></i>";
                                    } else {
                                        echo "<span class='material-symbols-rounded' style='font-size: 1.5rem; color: #64748b;'>$iconName</span>";
                                    }
                                ?>
                            </td>
                            <td style="padding:15px;">
                                <span class="badge-tipe <?php echo ($c['type']=='pemasukan')?'badge-in':'badge-out'; ?>">
                                    <?php echo ucfirst($c['type']); ?>
                                </span>
                                <?php if($c['is_shortcut']): ?>
                                    <span class="shortcut-badge"><i class='bx bxs-star'></i> Shortcut</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:15px; font-weight:500;"><?php echo htmlspecialchars($c['name']); ?></td>
                            <td style="padding:15px; text-align:right;">
                                <button type="button" 
                                        onclick="openEditCatModal(
                                            '<?php echo $c['id']; ?>', 
                                            '<?php echo htmlspecialchars($c['name'], ENT_QUOTES); ?>', 
                                            '<?php echo $c['type']; ?>',
                                            '<?php echo $c['icon'] ?? 'bx-category'; ?>',
                                            '<?php echo $c['is_shortcut']; ?>'
                                        )" 
                                        style="background:none; border:none; cursor:pointer; margin-right:10px;" class="text-primary">
                                    <i class='bx bx-pencil' style="font-size:1.2rem;"></i>
                                </button>
                                <button type="button" class="text-danger" 
                                        style="background:none; border:none; cursor:pointer;" 
                                        onclick="openDeleteCatModal('?del_cat=<?php echo $c['id']; ?>&tab=kategori&type=<?php echo $c['type']; ?>')">
                                    <i class='bx bx-trash' style="font-size:1.2rem;"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </table>
                    
                    <div id="empty-msg" style="padding:20px; text-align:center; color:#64748b; display: <?php echo ($visible_count == 0) ? 'block' : 'none'; ?>;">
                        Belum ada kategori.
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<div class="popup-overlay" id="cropModal" style="display:none; opacity:0; transition: opacity 0.3s;">
    <div class="popup-box" style="width: 500px; max-width: 95%;">
        <h3 class="popup-title">Sesuaikan Foto</h3>
        <p class="popup-message" style="margin-bottom:15px;">Geser dan sesuaikan area yang ingin diambil.</p>
        <div class="crop-container">
            <img id="image-to-crop" style="max-width: 100%; display: block;">
        </div>
        <div style="display:flex; gap:10px;">
            <button type="button" onclick="closeCropModal()" class="popup-btn" style="background:#f1f5f9; color:#333;">Batal</button>
            <button type="button" onclick="cropImage()" class="popup-btn success">Selesai</button>
        </div>
    </div>
</div>

<div class="popup-overlay" id="deleteConfirmModal" style="display:none; opacity:0; transition: opacity 0.3s;">
    <div class="popup-box">
        <div class="popup-icon-box error"><i class='bx bx-error-circle'></i></div>
        <h3 class="popup-title">Yakin Hapus Akun?</h3>
        <p class="popup-message">Tindakan ini <b>tidak bisa dibatalkan</b>.</p>
        <div style="display:flex; gap:10px;">
            <button onclick="closeDeleteConfirm()" class="popup-btn" style="background:#f1f5f9; color:#333;">Batal</button>
            <button onclick="document.getElementById('deleteForm').submit()" class="popup-btn error">Ya, Hapus Permanen</button>
        </div>
    </div>
</div>

<div class="popup-overlay" id="editCatModal" style="display:none; opacity:0; transition: opacity 0.3s;">
    <div class="popup-box" style="width:450px;">
        <h3 class="popup-title" style="margin-bottom: 20px; text-align: left; display: flex; align-items: center; gap: 10px;">
            <i class='bx bx-pencil'></i> Edit Kategori
        </h3>
        
        <form method="POST">
            <input type="hidden" name="edit_id" id="edit_cat_id">
            <input type="hidden" name="edit_type" id="edit_cat_type">
            <input type="hidden" name="edit_icon" id="edit_cat_icon_input"> 
            
            <div class="form-group">
                <label class="form-label" style="text-align: left;">Nama Kategori</label>
                <input type="text" name="edit_name" id="edit_cat_name" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label" style="text-align: left;">Pilih Ikon</label>
                <div class="icon-search-wrapper">
                    <i class='bx bx-search'></i>
                    <input type="text" id="iconSearchInput" class="icon-search-input" placeholder="Cari ikon..." onkeyup="filterIcons()">
                </div>
                <div id="categoryChips" style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:10px;">
                    </div>
                <div class="icon-grid" id="iconGrid">
                    </div>
            </div>

            <div class="form-group" style="display:flex; align-items:center; gap:10px; margin-top:15px; padding: 10px; background: #f8fafc; border-radius: 8px;">
                <input type="checkbox" name="edit_shortcut" id="edit_cat_shortcut" style="width: 20px; height: 20px; cursor: pointer;">
                <label for="edit_cat_shortcut" style="margin:0; font-weight:500; cursor: pointer;">Tampilkan Shortcut di Dashboard</label>
            </div>

            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="button" onclick="closeEditCatModal()" class="popup-btn" style="background:#f1f5f9; color:#333;">Batal</button>
                <button type="submit" name="edit_cat" class="popup-btn success">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<div class="popup-overlay" id="unsavedChangesModal" style="display:none; opacity:0; transition: opacity 0.3s;">
    <div class="popup-box">
        <div class="popup-icon-box error"><i class='bx bx-error'></i></div>
        <h3 class="popup-title">Perubahan Belum Disimpan</h3>
        <p class="popup-message">Anda memiliki perubahan yang belum disimpan pada profil. Apakah Anda yakin ingin keluar?</p>
        <div style="display:flex; gap:10px;">
            <button onclick="stayOnPage()" class="popup-btn" style="background:#f1f5f9; color:#333;">Batal</button>
            <button onclick="leavePage()" class="popup-btn error">Ya, Keluar</button>
        </div>
    </div>
</div>

<div class="popup-overlay" id="deleteCatModal" style="display:none; opacity:0; transition: opacity 0.3s;">
    <div class="popup-box">
        <div class="popup-icon-box error"><i class='bx bx-trash'></i></div>
        <h3 class="popup-title">Hapus Kategori?</h3>
        <p class="popup-message">Kategori ini akan dihapus permanen.</p>
        <div style="display:flex; gap:10px;">
            <button onclick="closeDeleteCatModal()" class="popup-btn" style="background:#f1f5f9; color:#333;">Batal</button>
            <a id="btn-confirm-delete-cat" href="#" class="popup-btn error" style="text-decoration:none; display:inline-block; text-align:center; line-height: normal; padding-top: 10px;">
                Ya, Hapus
            </a>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

<script>
    const availableIcons = {
        finance: [
            { name: 'payments', tags: 'pembayaran payment pay transaksi transfer uang' },
            { name: 'account_balance_wallet', tags: 'dompet wallet saldo uang finance' },
            { name: 'credit_card', tags: 'kartu kredit pembayaran belanja card transaction' },
            { name: 'attach_money', tags: 'uang money cash finansial bayar' },
            { name: 'monetization_on', tags: 'monetisasi income pendapatan profit money' },
            { name: 'currency_bitcoin', tags: 'bitcoin crypto kripto currency digital coin' },
            { name: 'receipt_long', tags: 'struk panjang receipt belanja bukti transaksi' },
            { name: 'calculate', tags: 'kalkulator hitung calculate finance angka' },
            { name: 'savings', tags: 'tabungan savings deposit uang bank' },
            { name: 'paid', tags: 'dibayar paid lunas selesai transaksi' },
            { name: 'store', tags: 'toko store shop marketplace belanja' },
            { name: 'local_offer', tags: 'diskon promo offer tag harga' },
            { name: 'redeem', tags: 'redeem tukar voucher claim hadiah' },
            { name: 'shopping_cart', tags: 'keranjang belanja shopping cart ecommerce' },
            { name: 'shopping_bag', tags: 'tas belanja bag shopping mall' },
            { name: 'shopping_basket', tags: 'keranjang basket belanja supermarket' },
            { name: 'price_change', tags: 'perubahan harga price change naik turun' },
            { name: 'price_check', tags: 'cek harga price check tag harga' },
            { name: 'receipt', tags: 'struk receipt bukti transaksi belanja' },
            { name: 'point_of_sale', tags: 'pos kasir point of sale pembayaran' },
            { name: 'add_card', tags: 'tambah kartu add card payment' },
            { name: 'atm', tags: 'atm mesin atm tarik tunai bank' },
            { name: 'barcode_reader', tags: 'barcode scanner reader scan kode' },
            { name: 'wallet', tags: 'wallet dompet digital uang' },
            { name: 'card_membership', tags: 'kartu member membership loyalty card' },
            { name: 'card_giftcard', tags: 'gift card kartu hadiah voucher gift' },
            { name: 'loyalty', tags: 'loyalty poin pelanggan reward' },
            { name: 'request_quote', tags: 'minta harga quote estimate penawaran' },
            { name: 'trending_up', tags: 'tren naik trending up grafik investasi' },
            { name: 'trending_down', tags: 'tren turun trending down grafik rugi' },
            { name: 'trending_flat', tags: 'tren datar flat grafik stabil' },
            { name: 'account_balance', tags: 'saldo akun balance uang bank' },
            { name: 'account_tree', tags: 'struktur akun tree finance hierarchy' },
            { name: 'analytics', tags: 'analitik analytics data finance insight' },
            { name: 'bar_chart', tags: 'grafik batang bar chart statistik data' },
            { name: 'leaderboard', tags: 'papan peringkat leaderboard ranking' },
            { name: 'balance', tags: 'keseimbangan balance stabil financial' },
            { name: 'insights', tags: 'insight analisis data analitik finance' },
            { name: 'stacked_line_chart', tags: 'grafik garis stacked chart tren data' },
            { name: 'query_stats', tags: 'statistik query stats analitik data' }
        ],
        food: [
            { name: 'restaurant', tags: 'restoran restaurant makan dining tempat makan' },
            { name: 'grocery', tags: 'grocery belanja bahan makanan pasar minimarket' },
            { name: 'local_dining', tags: 'makan lokal dining food kuliner' },
            { name: 'lunch_dining', tags: 'makan siang lunch dining food jajan' },
            { name: 'fastfood', tags: 'fast food cepat saji burger fries makan jajan' },
            { name: 'bakery_dining', tags: 'bakery roti kue pastry toko roti' },
            { name: 'local_cafe', tags: 'kafe cafe coffee shop minuman' },
            { name: 'local_bar', tags: 'bar minuman alcohol pub drink' },
            { name: 'local_pizza', tags: 'pizza restoran pizza italian food' },
            { name: 'icecream', tags: 'es krim ice cream dessert manis' },
            { name: 'ramen_dining', tags: 'ramen mie jepang japanese food' },
            { name: 'kitchen', tags: 'dapur kitchen cooking peralatan memasak' },
            { name: 'egg', tags: 'telur egg protein makanan' },
            { name: 'water_drop', tags: 'air water hydration minum' },
            { name: 'local_drink', tags: 'minuman drink beverage juice soda' },
            { name: 'set_meal', tags: 'paket makan set meal combo' },
            { name: 'dinner_dining', tags: 'makan malam dinner dining food' },
            { name: 'brunch_dining', tags: 'brunch sarapan siang dining' },
            { name: 'rice_bowl', tags: 'mangkuk nasi rice bowl asia food beras' },
            { name: 'flatware', tags: 'peralatan makan flatware sendok garpu' },
            { name: 'fork_spoon', tags: 'sendok garpu utensil makan' },
            { name: 'hanami_dango', tags: 'dango mochi japanese snack dessert jajan' },
            { name: 'coffee', tags: 'kopi coffee caffeine minuman panas' },
            { name: 'wine_bar', tags: 'wine anggur bar alcohol drink' },
            { name: 'liquor', tags: 'liquor alkohol minuman keras spirit' },
            { name: 'tapas', tags: 'tapas spanish food snack appetizer' },
            { name: 'outdoor_grill', tags: 'grill barbeque bbq panggang outdoor' },
            { name: 'cookie', tags: 'kue cookie dessert snack manis' },
            { name: 'nutrition', tags: 'nutrisi nutrition health food diet' },
            { name: 'emoji_food_beverage', tags: 'makanan minuman food beverage emoji teh cangkir tea' }
        ],
        transportation: [
            { name: 'directions_car', tags: 'mobil car kendaraan transportasi drive' },
            { name: 'directions_bus', tags: 'bus transportasi umum busway public transit' },
            { name: 'train', tags: 'kereta train railway transportasi' },
            { name: 'tram', tags: 'tram trem transportasi kota rail' },
            { name: 'subway', tags: 'subway MRT metro kereta bawah tanah' },
            { name: 'directions_railway', tags: 'rel kereta railway track transportasi' },
            { name: 'local_taxi', tags: 'taksi taxi ride transportasi' },
            { name: 'pedal_bike', tags: 'sepeda bike pedal cycling' },
            { name: 'directions_walk', tags: 'jalan walk pejalan kaki' },
            { name: 'flight', tags: 'pesawat flight airplane penerbangan' },
            { name: 'airport_shuttle', tags: 'shuttle bandara airport transport bus' },
            { name: 'hotel', tags: 'hotel penginapan lodging travel' },
            { name: 'commute', tags: 'perjalanan commute kerja transport' },
            { name: 'map', tags: 'peta map lokasi navigasi' },
            { name: 'navigation', tags: 'navigasi navigation arah compass' },
            { name: 'local_gas_station', tags: 'pom bensin gas station fuel' },
            { name: 'ev_station', tags: 'stasiun ev charging listrik electric vehicle' },
            { name: 'scooter', tags: 'skuter scooter kendaraan kecil' },
            { name: 'sailing', tags: 'perahu layar sailing boat laut' },
            { name: 'directions_boat', tags: 'kapal boat ferry laut' },
            { name: 'rv_hookup', tags: 'rv camper hookup camping mobil' },
            { name: 'car_rental', tags: 'sewa mobil car rental travel' },
            { name: 'car_crash', tags: 'kecelakaan crash mobil emergency' },
            { name: 'bus_alert', tags: 'alert bus peringatan jadwal transport' },
            { name: 'airline_seat_recline_normal', tags: 'kursi pesawat seat recline travel' },
            { name: 'airline_seat_individual_suite', tags: 'suite pesawat seat premium first class' },
            { name: 'airplane_ticket', tags: 'tiket pesawat airplane ticket travel' },
            { name: 'connecting_airports', tags: 'bandara transit connecting flights travel' }
        ],
        tech: [
            { name: 'smartphone', tags: 'smartphone hp ponsel mobile phone gadget' },
            { name: 'phone_iphone', tags: 'iphone ios smartphone apple mobile' },
            { name: 'phone_android', tags: 'android phone smartphone google device' },
            { name: 'laptop', tags: 'laptop komputer notebook pc portable' },
            { name: 'desktop_windows', tags: 'desktop windows pc komputer' },
            { name: 'desktop_mac', tags: 'mac desktop apple macintosh komputer' },
            { name: 'headphones', tags: 'headphone audio musik earphone device' },
            { name: 'camera_alt', tags: 'kamera camera foto photography' },
            { name: 'videocam', tags: 'kamera video camcorder filming' },
            { name: 'tv', tags: 'televisi tv layar monitor media' },
            { name: 'keyboard', tags: 'keyboard papan ketik komputer input' },
            { name: 'mouse', tags: 'mouse komputer input device pointer' },
            { name: 'router', tags: 'router wifi jaringan internet device' },
            { name: 'bluetooth', tags: 'bluetooth koneksi wireless pairing' },
            { name: 'wifi', tags: 'wifi internet jaringan sinyal' },
            { name: 'memory', tags: 'memori memory ram storage hardware' },
            { name: 'sd_card', tags: 'sd card kartu memori penyimpanan' },
            { name: 'print', tags: 'printer print cetak dokumen' },
            { name: 'speaker', tags: 'speaker audio suara sound device' },
            { name: 'watch', tags: 'jam watch smartwatch wearable' },
            { name: 'devices', tags: 'perangkat devices gadget electronics' },
            { name: 'cast', tags: 'cast screen mirroring display' },
            { name: 'monitor', tags: 'monitor layar komputer display' },
            { name: 'security', tags: 'keamanan security protection cyber' },
            { name: 'usb', tags: 'usb port konektor device' },
            { name: 'keyboard_alt', tags: 'keyboard alternatif input device' },
            { name: 'gamepad', tags: 'gamepad kontroler gaming joystick' },
            { name: 'mic', tags: 'mikrofon mic audio recording' },
            { name: 'mic_none', tags: 'mic none mikrofon off muted' },
            { name: 'mic_off', tags: 'mic off mute suara mati' },
            { name: 'headphones_battery', tags: 'headphone battery daya audio' },
            { name: 'battery_full', tags: 'baterai penuh battery full charge' },
            { name: 'battery_5_bar', tags: 'baterai 5 bar battery medium charge' },
            { name: 'battery_3_bar', tags: 'baterai 3 bar battery low charge' },
            { name: 'battery_saver', tags: 'penghemat baterai battery saver mode' },
            { name: 'brightness_auto', tags: 'kecerahan otomatis brightness auto' },
            { name: 'dark_mode', tags: 'dark mode tema gelap display' },
            { name: 'light_mode', tags: 'light mode tema terang display' },
            { name: 'contrast', tags: 'kontras contrast display visual' },
            { name: 'data_usage', tags: 'penggunaan data usage internet' },
            { name: 'signal_cellular_alt', tags: 'sinyal seluler cellular signal network' },
            { name: 'cloud', tags: 'cloud awan storage online' },
            { name: 'cloud_sync', tags: 'sinkronisasi cloud sync online' },
            { name: 'cloud_download', tags: 'cloud download unduh storage' },
            { name: 'cloud_upload', tags: 'cloud upload unggah storage' },
            { name: 'cloud_off', tags: 'cloud off offline disconnected' },
            { name: 'dns', tags: 'dns server jaringan internet' },
            { name: 'qr_code', tags: 'qr code scan barcode digital' },
            { name: 'qr_code_scanner', tags: 'scanner qr code scan device' },
            { name: 'barcode', tags: 'barcode scan kode produk' },
            { name: 'storage', tags: 'penyimpanan storage device memory' },
            { name: 'hub', tags: 'hub port konektor usb device' },
            { name: 'device_unknown', tags: 'perangkat tidak dikenal unknown device error' },
            { name: 'headset_mic', tags: 'headset mic audio komunikasi' }
        ],
        home: [
            { name: 'home', tags: 'rumah home tempat tinggal' },
            { name: 'chair', tags: 'kursi chair furniture duduk' },
            { name: 'bed', tags: 'tempat tidur bed kamar' },
            { name: 'bathtub', tags: 'bathtub bak mandi bathroom' },
            { name: 'lightbulb', tags: 'lampu lightbulb pencahayaan ideas' },
            { name: 'chair_alt', tags: 'kursi alternatif chair furniture' },
            { name: 'house_siding', tags: 'dinding rumah siding exterior' },
            { name: 'cottage', tags: 'pondok cottage rumah kecil' },
            { name: 'villa', tags: 'villa rumah mewah holiday house' },
            { name: 'weekend', tags: 'liburan weekend rumah santai' },
            { name: 'window', tags: 'jendela window rumah kaca' },
            { name: 'door_front', tags: 'pintu depan front door entry' },
            { name: 'yard', tags: 'halaman yard outdoor taman' },
            { name: 'vacuum', tags: 'vacuum penyedot debu cleaning' },
            { name: 'cleaning_services', tags: 'jasa bersih cleaning services rumah' },
            { name: 'build', tags: 'bangun build konstruksi rumah' },
            { name: 'plumbing', tags: 'pipa plumbing instalasi air' },
            { name: 'electrical_services', tags: 'listrik electrical instalasi listrik' },
            { name: 'carpenter', tags: 'tukang kayu carpenter woodworking' },
            { name: 'handyman', tags: 'tukang handyman perbaikan service' },
            { name: 'roofing', tags: 'atap roofing perbaikan roof' },
            { name: 'grass', tags: 'rumput grass taman outdoor' },
            { name: 'fireplace', tags: 'perapian fireplace panas' },
            { name: 'ac_unit', tags: 'ac unit pendingin udara air conditioner' },
            { name: 'air_purifier', tags: 'air purifier penyaring udara rumah' },
            { name: 'heat_pump', tags: 'heat pump pemanas pendingin' },
            { name: 'iron', tags: 'setrika iron pakaian laundry' },
            { name: 'local_laundry_service', tags: 'laundry cucian jasa mencuci mesin cuci washing' },
            { name: 'dry', tags: 'keringkan dry clothes laundry' },
            { name: 'kitchen', tags: 'dapur kitchen rumah memasak' },
            { name: 'oven_gen', tags: 'oven general cooking baking' },
            { name: 'cooking', tags: 'memasak cooking food kitchen' },
            { name: 'dishwasher', tags: 'pencuci piring dishwasher kitchen' },
            { name: 'microwave', tags: 'microwave oven cepat panas' },
            { name: 'heat', tags: 'panas heat suhu cooking' },
            { name: 'oven', tags: 'oven baking roasting memasak' },
            { name: 'skillet', tags: 'wajan skillet frying pan' },
            { name: 'skillet_cooktop', tags: 'kompor skillet cooktop wajan' },
            { name: 'stockpot', tags: 'panci besar stockpot cooking' },
            { name: 'range_hood', tags: 'penghisap asap range hood dapur' }
        ],
        health: [
            { name: 'favorite', tags: 'favorit love health wellbeing suka' },
            { name: 'favorite_border', tags: 'favorit kosong love outline like' },
            { name: 'monitor_heart', tags: 'detak jantung monitor heart kesehatan cardiology' },
            { name: 'medical_services', tags: 'layanan medis medical services kesehatan' },
            { name: 'medication', tags: 'obat medication pharmacy kesehatan' },
            { name: 'medication_liquid', tags: 'obat cair liquid medicine syrup' },
            { name: 'healing', tags: 'penyembuhan healing recovery health' },
            { name: 'health_and_safety', tags: 'kesehatan dan keselamatan health safety' },
            { name: 'vaccines', tags: 'vaksin vaccines imunisasi kesehatan' },
            { name: 'coronavirus', tags: 'corona covid virus pandemi health' },
            { name: 'masks', tags: 'masker masks kesehatan perlindungan' },
            { name: 'thermostat', tags: 'termometer thermostat suhu temperature' },
            { name: 'bloodtype', tags: 'golongan darah blood type kesehatan' },
            { name: 'fitness_center', tags: 'fitness gym olahraga workout' },
            { name: 'sports_soccer', tags: 'sepak bola soccer sports football' },
            { name: 'sports_basketball', tags: 'bola basket basketball sports' },
            { name: 'sports_tennis', tags: 'tenis tennis sports raket' },
            { name: 'sports_volleyball', tags: 'voli volleyball sports permainan' },
            { name: 'sports_mma', tags: 'mma bela diri mixed martial arts fight' },
            { name: 'sports_kabaddi', tags: 'kabaddi olahraga india sports game' },
            { name: 'sports_esports', tags: 'e-sports esports gaming kompetitif' },
            { name: 'pool', tags: 'kolam renang pool swimming olahraga' },
            { name: 'directions_run', tags: 'lari run jogging workout' },
            { name: 'directions_bike', tags: 'bersepeda bike cycling olahraga' },
            { name: 'pedal_bike', tags: 'sepeda pedal bike cycling' },
            { name: 'person_add', tags: 'tambah orang person add community' },
            { name: 'self_improvement', tags: 'pengembangan diri self improvement meditasi' },
            { name: 'emoji_people', tags: 'orang people emoji human' }
        ],
        office: [
            { name: 'menu_book', tags: 'buku menu book membaca referensi' },
            { name: 'edit', tags: 'edit ubah modify pencil' },
            { name: 'description', tags: 'deskripsi description dokumen file' },
            { name: 'folder', tags: 'folder direktori penyimpanan file' },
            { name: 'folder_open', tags: 'folder terbuka open directory' },
            { name: 'create_new_folder', tags: 'buat folder baru create folder new' },
            { name: 'drive_file_move', tags: 'pindah file move drive storage' },
            { name: 'calendar_month', tags: 'kalender bulan calendar schedule date' },
            { name: 'schedule', tags: 'jadwal schedule waktu planning' },
            { name: 'alarm', tags: 'alarm pengingat waktu reminder' },
            { name: 'notifications', tags: 'notifikasi notifications alert pemberitahuan' },
            { name: 'person', tags: 'orang person user profile' },
            { name: 'groups', tags: 'grup groups team komunitas' },
            { name: 'badge', tags: 'lencana badge id card identitas' },
            { name: 'print', tags: 'cetak print dokumen printer' },
            { name: 'work', tags: 'kerja work job kantor' },
            { name: 'school', tags: 'sekolah school pendidikan lesson' },
            { name: 'auto_stories', tags: 'story cerita auto stories buku' },
            { name: 'library_books', tags: 'perpustakaan library books koleksi' },
            { name: 'library_add', tags: 'tambah ke library add koleksi' },
            { name: 'fact_check', tags: 'cek fakta fact check validasi' },
            { name: 'rule', tags: 'aturan rule garis penggaris' },
            { name: 'task_alt', tags: 'tugas selesai task checklist done' },
            { name: 'checklist', tags: 'daftar tugas checklist to-do list' },
            { name: 'edit_note', tags: 'edit catatan edit note modify' },
            { name: 'note', tags: 'catatan note memo tulisan' },
            { name: 'sticky_note_2', tags: 'sticky note memo tempel kertas' },
            { name: 'assignment', tags: 'tugas assignment file kerja sekolah' },
            { name: 'draft', tags: 'draft konsep draf dokumen' },
            { name: 'approval', tags: 'persetujuan approval acc otorisasi' },
            { name: 'bookmark', tags: 'penanda bookmark simpan halaman' },
            { name: 'book', tags: 'buku book membaca literatur' },
            { name: 'meeting_room', tags: 'ruang rapat meeting room kantor' },
            { name: 'event', tags: 'acara event kegiatan kalender' },
            { name: 'event_available', tags: 'event tersedia available jadwal' },
            { name: 'event_note', tags: 'catatan event note jadwal' },
            { name: 'note_add', tags: 'tambah catatan note add' },
            { name: 'workspaces', tags: 'ruang kerja workspaces team collaboration' },
            { name: 'workspace_premium', tags: 'ruang kerja premium workspace vip office' }
        ],
        social: [
            { name: 'chat', tags: 'chat obrolan pesan message talk' },
            { name: 'chat_bubble', tags: 'chat bubble pesan percakapan message' },
            { name: 'sms', tags: 'sms pesan teks message text' },
            { name: 'call', tags: 'panggilan call telepon voice' },
            { name: 'call_end', tags: 'akhiri panggilan call end hang up' },
            { name: 'call_made', tags: 'panggilan keluar call made' },
            { name: 'call_received', tags: 'panggilan masuk received call' },
            { name: 'call_missed', tags: 'panggilan tak terjawab missed call' },
            { name: 'contacts', tags: 'kontak contacts daftar nomor' },
            { name: 'contact_page', tags: 'halaman kontak contact page profile' },
            { name: 'email', tags: 'email surat elektronik mail inbox' },
            { name: 'mark_email_unread', tags: 'email belum dibaca unread mail' },
            { name: 'forum', tags: 'forum diskusi chat komunitas' },
            { name: 'groups', tags: 'grup groups komunitas team' },
            { name: 'group_add', tags: 'tambah grup add group community' },
            { name: 'share', tags: 'bagikan share sosial media' },
            { name: 'share_location', tags: 'bagikan lokasi share location gps' },
            { name: 'public', tags: 'publik public global dunia' },
            { name: 'language', tags: 'bahasa language translate global' },
            { name: 'person_add', tags: 'tambah orang add person follow' },
            { name: 'person_remove', tags: 'hapus orang remove person unfollow' },
            { name: 'person_pin', tags: 'pin lokasi orang person pin map' },
            { name: 'face', tags: 'wajah face human profile' },
            { name: 'face_retouching_natural', tags: 'retouch wajah edit natural beauty' },
            { name: 'mood', tags: 'mood bahagia happy smile' },
            { name: 'mood_bad', tags: 'mood buruk sad bad unhappy' },
            { name: 'emoji_emotions', tags: 'emoji emosi emotions smiley' },
            { name: 'emoji_events', tags: 'emoji acara events celebration' },
            { name: 'emoji_objects', tags: 'emoji objek objects stuff' },
            { name: 'thumb_up', tags: 'like thumbs up suka setuju' },
            { name: 'thumb_down', tags: 'dislike thumbs down tidak setuju' },
            { name: 'handshake', tags: 'jabat tangan handshake agreement deal' },
            { name: 'diversity_1', tags: 'keberagaman diversity people group' },
            { name: 'diversity_2', tags: 'diversity kelompok komunitas inklusif' },
            { name: 'diversity_3', tags: 'diversity variasi people community' },
            { name: 'sentiment_satisfied', tags: 'senang satisfied happy emotion' },
            { name: 'sentiment_dissatisfied', tags: 'tidak puas dissatisfied sad emotion' },
            { name: 'sentiment_very_satisfied', tags: 'sangat puas very satisfied happy' },
            { name: 'sentiment_very_dissatisfied', tags: 'sangat tidak puas very dissatisfied upset' },
            { name: 'support', tags: 'dukungan support bantuan help' },
            { name: 'live_help', tags: 'live help bantuan chat support' }
        ],
        action: [
            { name: 'add', tags: 'tambah add plus new' },
            { name: 'remove', tags: 'hapus remove delete minus' },
            { name: 'close', tags: 'tutup close exit cancel' },
            { name: 'done', tags: 'selesai done complete check' },
            { name: 'check', tags: 'cek check benar correct' },
            { name: 'check_circle', tags: 'cek lingkaran check circle verified' },
            { name: 'done_all', tags: 'selesai semua done all checklist' },
            { name: 'refresh', tags: 'refresh ulang reload update' },
            { name: 'autorenew', tags: 'pembaruan otomatis autorenew repeat' },
            { name: 'settings', tags: 'pengaturan settings konfigurasi' },
            { name: 'settings_suggest', tags: 'saran pengaturan settings suggest' },
            { name: 'settings_accessibility', tags: 'aksesibilitas accessibility settings' },
            { name: 'settings_backup_restore', tags: 'backup restore settings data' },
            { name: 'logout', tags: 'keluar logout sign out' },
            { name: 'login', tags: 'masuk login sign in' },
            { name: 'swap_vert', tags: 'tukar vertikal swap vertical exchange' },
            { name: 'swap_horiz', tags: 'tukar horizontal swap horizontal exchange' },
            { name: 'open_in_new', tags: 'buka di tab baru open in new external' },
            { name: 'open_with', tags: 'buka dengan open with choose app' },
            { name: 'home_app_logo', tags: 'logo aplikasi home app icon' },
            { name: 'info', tags: 'informasi info detail' },
            { name: 'warning', tags: 'peringatan warning caution alert' },
            { name: 'error', tags: 'error kesalahan danger alert' },
            { name: 'help', tags: 'bantuan help support question' },
            { name: 'tips_and_updates', tags: 'tips dan pembaruan tips updates idea' },
            { name: 'star', tags: 'bintang star favorit rating' },
            { name: 'star_half', tags: 'bintang setengah star half rating' },
            { name: 'grade', tags: 'nilai grade rating score' },
            { name: 'visibility', tags: 'lihat visibility show view' },
            { name: 'visibility_off', tags: 'sembunyikan visibility off hide' },
            { name: 'lock', tags: 'kunci lock secure keamanan' },
            { name: 'lock_open', tags: 'kunci terbuka lock open unlock' },
            { name: 'manage_accounts', tags: 'kelola akun manage accounts profile' },
            { name: 'verified', tags: 'terverifikasi verified check badge' },
            { name: 'priority_high', tags: 'prioritas tinggi priority high alert' },
            { name: 'lightbulb_circle', tags: 'ide lightbulb circle inspiration' },
            { name: 'ads_click', tags: 'klik iklan ads click marketing' },
            { name: 'bolt', tags: 'petir bolt energi fast power' },
            { name: 'extension', tags: 'ekstensi extension plugin add-on' },
            { name: 'history', tags: 'riwayat history log waktu' },
            { name: 'hourglass_empty', tags: 'jam pasir kosong hourglass empty waiting' },
            { name: 'hourglass_full', tags: 'jam pasir penuh hourglass full time' },
            { name: 'build_circle', tags: 'bangun build circle konstruksi settings' },
            { name: 'reorder', tags: 'susun ulang reorder list sort' },
            { name: 'update', tags: 'pembaruan update refresh system' },
            { name: 'upgrade', tags: 'tingkatkan upgrade improvement level up' },
            { name: 'fingerprint', tags: 'sidik jari fingerprint security unlock' },
            { name: 'translate', tags: 'terjemahkan translate language bahasa' }
        ],
        content: [
            { name: 'content_copy', tags: 'salin copy duplikasi content' },
            { name: 'cut', tags: 'potong cut trim edit' },
            { name: 'content_cut', tags: 'potong cut konten edit' },
            { name: 'content_paste', tags: 'tempel paste content insert' },
            { name: 'save', tags: 'simpan save file document' },
            { name: 'cloud_done', tags: 'cloud selesai cloud done synced' },
            { name: 'cloud_upload', tags: 'unggah upload cloud kirim' },
            { name: 'cloud_download', tags: 'unduh download cloud file' },
            { name: 'delete', tags: 'hapus delete remove trash' },
            { name: 'delete_forever', tags: 'hapus permanen delete forever remove' },
            { name: 'backspace', tags: 'hapus karakter backspace undo typing' },
            { name: 'undo', tags: 'kembali undo revert' },
            { name: 'redo', tags: 'ulang redo repeat' },
            { name: 'text_format', tags: 'format teks text formatting style' },
            { name: 'font_download', tags: 'unduh font download typeface typography' },
            { name: 'format_bold', tags: 'tebal bold format text' },
            { name: 'format_italic', tags: 'miring italic format text' },
            { name: 'format_underlined', tags: 'garis bawah underline text formatting' },
            { name: 'format_align_left', tags: 'rata kiri align left text' },
            { name: 'format_align_center', tags: 'rata tengah align center text' },
            { name: 'format_align_right', tags: 'rata kanan align right text' },
            { name: 'format_align_justify', tags: 'justify rata penuh text formatting' },
            { name: 'format_list_bulleted', tags: 'daftar poin bullets list' },
            { name: 'format_list_numbered', tags: 'daftar nomor numbered list' },
            { name: 'insert_photo', tags: 'sisip foto insert photo image' },
            { name: 'insert_chart', tags: 'sisip grafik insert chart diagram' },
            { name: 'insert_link', tags: 'sisip tautan insert link url' },
            { name: 'insert_emoticon', tags: 'sisip emoticon emoji smiley' },
            { name: 'table_chart', tags: 'tabel chart data spreadsheet' },
            { name: 'border_color', tags: 'warna border garis color edit' },
            { name: 'border_all', tags: 'border semua grid table layout' },
            { name: 'brush', tags: 'kuas brush editing drawing' },
            { name: 'palette', tags: 'palet warna palette color scheme' },
            { name: 'draw', tags: 'gambar draw sketsa doodle' },
            { name: 'image', tags: 'gambar image foto media' },
            { name: 'crop', tags: 'potong crop gambar edit' },
            { name: 'crop_free', tags: 'crop bebas free crop resize' },
            { name: 'crop_square', tags: 'crop persegi square image' },
            { name: 'filter', tags: 'filter efek edit photo' },
            { name: 'filter_alt', tags: 'filter alternatif alt selective' },
            { name: 'tune', tags: 'atur tune adjust setting' },
            { name: 'adjust', tags: 'atur adjust brightness contrast' },
            { name: 'style', tags: 'gaya style desain theme' },
            { name: 'color_lens', tags: 'lensa warna color lens palette' },
            { name: 'wallpaper', tags: 'wallpaper latar belakang background' },
            { name: 'animation', tags: 'animasi animation motion' },
            { name: 'burst_mode', tags: 'burst mode foto cepat kamera' },
            { name: 'hdr_auto', tags: 'hdr otomatis hdr auto camera' },
            { name: 'panorama', tags: 'panorama wide photo landscape' },
            { name: 'slideshow', tags: 'slideshow tayangan presentasi gambar' }
        ],
        navigation: [
            { name: 'menu', tags: 'menu navigasi daftar options' },
            { name: 'apps', tags: 'aplikasi apps grid launcher' },
            { name: 'more_vert', tags: 'lebih banyak more vert menu options' },
            { name: 'more_horiz', tags: 'lebih banyak more horiz menu options' },
            { name: 'arrow_back', tags: 'panah kembali arrow back previous' },
            { name: 'arrow_forward', tags: 'panah maju arrow forward next' },
            { name: 'arrow_upward', tags: 'panah atas arrow up scroll top' },
            { name: 'arrow_downward', tags: 'panah bawah arrow down scroll' },
            { name: 'chevron_left', tags: 'chevron kiri left navigation' },
            { name: 'chevron_right', tags: 'chevron kanan right navigation' },
            { name: 'expand_more', tags: 'lebih banyak expand more dropdown' },
            { name: 'expand_less', tags: 'lebih sedikit expand less collapse' },
            { name: 'fullscreen', tags: 'layar penuh fullscreen expand view' },
            { name: 'fullscreen_exit', tags: 'keluar fullscreen exit minimize' },
            { name: 'unfold_more', tags: 'buka lebih unfold more expand' },
            { name: 'unfold_less', tags: 'buka sedikit unfold less collapse' },
            { name: 'refresh', tags: 'segarkan refresh reload page' },
            { name: 'first_page', tags: 'halaman pertama first page start' },
            { name: 'last_page', tags: 'halaman terakhir last page end' },
            { name: 'subdirectory_arrow_right', tags: 'submenu kanan subdirectory arrow right' },
            { name: 'subdirectory_arrow_left', tags: 'submenu kiri subdirectory arrow left' },
            { name: 'home_work', tags: 'kantor rumah home work building' },
            { name: 'explore', tags: 'jelajah explore compass discover' },
            { name: 'explore_off', tags: 'jelajah off explore disabled compass' }
        ],
        maps: [
            { name: 'map', tags: 'peta map navigasi lokasi' },
            { name: 'location_on', tags: 'lokasi aktif location on marker pin' },
            { name: 'location_off', tags: 'lokasi mati location off disabled' },
            { name: 'location_searching', tags: 'mencari lokasi location searching gps' },
            { name: 'location_disabled', tags: 'lokasi dinonaktifkan location disabled gps off' },
            { name: 'near_me', tags: 'di dekat saya near me lokasi sekitar' },
            { name: 'local_parking', tags: 'parking parkir mobil motor jalan' },
            { name: 'place', tags: 'tempat place lokasi pin' },
            { name: 'local_hospital', tags: 'rumah sakit hospital medis emergency' },
            { name: 'local_police', tags: 'polisi kantor polisi local police keamanan' },
            { name: 'local_fire_department', tags: 'pemadam kebakaran fire department emergency' },
            { name: 'local_post_office', tags: 'kantor pos post office mail' },
            { name: 'local_florist', tags: 'toko bunga florist flower shop' },
            { name: 'local_library', tags: 'perpustakaan library education books' },
            { name: 'local_mall', tags: 'mall pusat perbelanjaan lokal mall' },
            { name: 'local_movies', tags: 'bioskop local movies cinema film' },
            { name: 'local_laundry_service', tags: 'jasa laundry local laundry service cleaning' },
            { name: 'local_offer', tags: 'penawaran lokal local offer promo' },
            { name: 'zoom_in_map', tags: 'zoom masuk zoom in map enlarge' },
            { name: 'zoom_out_map', tags: 'zoom keluar zoom out map shrink' },
            { name: 'directions', tags: 'petunjuk arah directions navigasi' },
            { name: 'directions_run', tags: 'arah lari directions run jogging' },
            { name: 'directions_walk', tags: 'arah jalan directions walk foot' },
            { name: 'directions_bus', tags: 'arah bus directions bus transport' },
            { name: 'directions_car', tags: 'arah mobil directions car drive' },
            { name: 'local_shipping', tags: 'pengiriman lokal shipping delivery cargo' },
            { name: 'delivery_dining', tags: 'antar makanan delivery dining food' },
            { name: 'run_circle', tags: 'lari lingkaran run circle activity' },
            { name: 'emergency', tags: 'darurat emergency bantuan' },
            { name: 'terrain', tags: 'medan terrain peta kontur landscape' },
            { name: 'satellite', tags: 'satelit satellite citra map' }
            
        ],
        miscellaneous: [
            { name: 'pets', tags: 'hewan peliharaan pets animals' },
            { name: 'child_care', tags: 'pengasuhan anak child care baby' },
            { name: 'child_friendly', tags: 'ramah anak child friendly kids' },
            { name: 'toys', tags: 'mainan toys kids play' },
            { name: 'music_note', tags: 'nada musik music note sound' },
            { name: 'music_video', tags: 'video musik music video clip' },
            { name: 'movie', tags: 'film movie cinema' },
            { name: 'theaters', tags: 'teater theaters bioskop movie' },
            { name: 'book_online', tags: 'pesan online book online ticket' },
            { name: 'local_activity', tags: 'aktivitas lokal local activity events' },
            { name: 'celebration', tags: 'perayaan celebration party event' },
            { name: 'cake', tags: 'kue cake dessert sweet' },
            { name: 'party_mode', tags: 'mode pesta party mode fun' },
            { name: 'stars', tags: 'bintang stars rating night' },
            { name: 'auto_awesome', tags: 'keren auto awesome magic effect' },
            { name: 'auto_awesome_mosaic', tags: 'mozaik mosaic auto awesome pattern' },
            { name: 'auto_fix_high', tags: 'perbaikan tinggi auto fix high edit' },
            { name: 'auto_fix_normal', tags: 'perbaikan normal auto fix normal edit' },
            { name: 'auto_fix_off', tags: 'perbaikan mati auto fix off disabled' },
            { name: 'magic_button', tags: 'tombol ajaib magic button special' },
            { name: 'bubble_chart', tags: 'diagram gelembung bubble chart data' },
            { name: 'insights', tags: 'wawasan insights analisis data' },
            { name: 'psychology', tags: 'psikologi psychology mind behavior' },
            { name: 'science', tags: 'sains science eksperimen lab' },
            { name: 'sports', tags: 'olahraga sports activity fitness' },
            { name: 'downhill_skiing', tags: 'ski turun downhill skiing snow sport' },
            { name: 'surfing', tags: 'selancar surfing wave ocean' },
            { name: 'kayaking', tags: 'kayak kayaking boat paddle' },
            { name: 'paragliding', tags: 'paralayang paragliding fly adventure' },
            { name: 'skateboarding', tags: 'skateboard skateboarding trick sport' },
            { name: 'snowboarding', tags: 'snowboard snowboarding winter sport' },
            { name: 'snowshoeing', tags: 'jalan salju snowshoeing winter walk' },
            { name: 'smoking_rooms', tags: 'ruang merokok smoking rooms allowed' },
            { name: 'smoke_free', tags: 'bebas rokok smoke free no smoking' },
            { name: 'vaping_rooms', tags: 'ruang vaping vaping rooms allowed' }
        ]
    };

    const categoryLabels = {
        'finance': 'Keuangan', 'food': 'Makanan', 'transportation': 'Transportasi',
        'tech': 'Gadget', 'home': 'Rumah', 'health': 'Kesehatan', 'office': 'Kantor',
        'social': 'Sosial', 'action': 'Aksi', 'content': 'Konten', 'navigation': 'Navigasi',
        'maps': 'Peta', 'miscellaneous': 'Lainnya'
    };

    let activeCategory = 'all';

    function renderCategoryChips() {
        const chipsContainer = document.getElementById('categoryChips');
        chipsContainer.innerHTML = '';

        // Chip "Semua"
        const allBtn = document.createElement('button');
        allBtn.type = 'button';
        allBtn.className = `chip-btn ${activeCategory === 'all' ? 'active' : ''}`;
        allBtn.innerText = 'Semua';
        allBtn.onclick = () => { setActiveCategory('all'); };
        chipsContainer.appendChild(allBtn);

        // Chip Kategori Lainnya
        for (const [key, icons] of Object.entries(availableIcons)) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `chip-btn ${activeCategory === key ? 'active' : ''}`;
            btn.innerText = categoryLabels[key] || key; // Pakai label mapping atau key asli
            btn.onclick = () => { setActiveCategory(key); };
            chipsContainer.appendChild(btn);
        }
    }

    function setActiveCategory(cat) {
        activeCategory = cat;
        renderCategoryChips(); // Re-render chips untuk update class active
        
        // Reset search bar saat ganti kategori
        document.getElementById('iconSearchInput').value = '';
        
        // Ambil input hidden value ikon yang terpilih saat ini
        const currentSelected = document.getElementById('edit_cat_icon_input').value;
        renderIcons(currentSelected); // Render ulang grid
    }



    function renderIcons(selectedIcon, filterText = '') {
        const grid = document.getElementById('iconGrid');
        const input = document.getElementById('edit_cat_icon_input');
        grid.innerHTML = ''; 

        let iconsToRender = [];

        // Gabungkan array jika kategori 'all'
        if (activeCategory === 'all') {
            iconsToRender = Object.values(availableIcons).flat();
        } else {
            iconsToRender = availableIcons[activeCategory] || [];
        }

        // --- FILTER PINTAR (SMART SEARCH) ---
        // Mencari teks di dalam 'name' DAN 'tags'
        const lowerFilter = filterText.toLowerCase();
        
        const filteredIcons = iconsToRender.filter(item => {
            // Jika item masih string lama (backward compatibility), ubah jadi object sementara
            const iconName = typeof item === 'string' ? item : item.name;
            const iconTags = typeof item === 'string' ? item : (item.tags || '');

            return iconName.toLowerCase().includes(lowerFilter) || 
                   iconTags.toLowerCase().includes(lowerFilter);
        });

        if (filteredIcons.length === 0) {
            grid.innerHTML = '<div style="grid-column: 1/-1; text-align:center; color:#94a3b8; font-size:0.8rem; padding:10px;">Ikon tidak ditemukan. Coba kata kunci lain (misal: "makan", "gaji").</div>';
            return;
        }

        filteredIcons.forEach(item => {
            // Handle jika data masih ada yang string
            const iconName = typeof item === 'string' ? item : item.name;
            const div = document.createElement('div');
            
            const isSelected = (iconName === selectedIcon); 
            div.className = `icon-option ${isSelected ? 'selected' : ''}`;
            
            // Render Google Icons
            div.innerHTML = `<span class="material-symbols-rounded" style="font-size: 24px;">${iconName}</span>`;
            
            // Tampilkan tooltip nama saat hover (opsional)
            div.title = iconName;
            
            div.onclick = function() {
                document.querySelectorAll('.icon-option').forEach(el => el.classList.remove('selected'));
                div.classList.add('selected');
                input.value = iconName; // Simpan nama ikonnya saja ke database
            };
            grid.appendChild(div);
        });
    }

    function filterIcons() {
        const searchText = document.getElementById('iconSearchInput').value;
        const currentSelectedIcon = document.getElementById('edit_cat_icon_input').value;
        
        // Jika sedang mencari, otomatis pindah ke kategori 'Semua' agar pencarian lebih luas
        if(searchText.length > 0 && activeCategory !== 'all') {
            activeCategory = 'all'; 
            renderCategoryChips();
        }
        
        renderIcons(currentSelectedIcon, searchText);
    }
    
    function openEditCatModal(id, name, type, icon, isShortcut) {
        document.getElementById('edit_cat_id').value = id;
        document.getElementById('edit_cat_name').value = name;
        document.getElementById('edit_cat_type').value = type;
        document.getElementById('edit_cat_icon_input').value = icon;
        
        // Reset UI
        document.getElementById('iconSearchInput').value = '';
        activeCategory = 'all'; // Default balik ke semua atau bisa di deteksi dari icon kategori
        
        // Set Checkbox
        const scCheckbox = document.getElementById('edit_cat_shortcut');
        if(scCheckbox) scCheckbox.checked = (isShortcut == 1);

        renderCategoryChips(); // Render Chips
        renderIcons(icon);     // Render Grid

        const modal = document.getElementById('editCatModal');
        modal.style.display = 'flex';
        setTimeout(() => modal.style.opacity = '1', 10);
    }

    function closeEditCatModal() {
        const modal = document.getElementById('editCatModal');
        modal.style.opacity = '0';
        setTimeout(() => modal.style.display = 'none', 300);
    }

    // --- 1. DETEKSI UNSAVED CHANGES & INTERCEPT LINK ---
    let formChanged = false;
    let targetUrl = ''; 
    const form = document.getElementById('profileForm');
    
    form.addEventListener('change', () => formChanged = true);
    form.addEventListener('input', () => formChanged = true);

    document.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', function(e) {
            if (formChanged) {
                const href = this.getAttribute('href');
                if (!href || href.startsWith('#') || href.startsWith('javascript')) return;

                e.preventDefault(); 
                targetUrl = href;   
                showUnsavedModal(); 
            }
        });
    });

    window.addEventListener('beforeunload', function (e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = ''; 
        }
    });

    form.addEventListener('submit', () => formChanged = false);

    function showUnsavedModal() {
        const modal = document.getElementById('unsavedChangesModal');
        modal.style.display = 'flex';
        setTimeout(() => modal.style.opacity = '1', 10);
    }

    function stayOnPage() {
        const modal = document.getElementById('unsavedChangesModal');
        modal.style.opacity = '0';
        setTimeout(() => modal.style.display = 'none', 300);
    }

    function leavePage() {
        formChanged = false; 
        window.location.href = targetUrl;
    }


    // --- 2. LOGIC CROP IMAGE ---
    let cropper;
    const fileInput = document.getElementById('hidden-file-input');
    const imageToCrop = document.getElementById('image-to-crop');
    const cropModal = document.getElementById('cropModal');

    fileInput.addEventListener('change', function(e) {
        const files = e.target.files;
        if (files && files.length > 0) {
            const file = files[0];
            const reader = new FileReader();
            reader.onload = function(e) {
                imageToCrop.src = e.target.result;
                cropModal.style.display = 'flex';
                setTimeout(() => cropModal.style.opacity = '1', 10);
                if(cropper) cropper.destroy();
                cropper = new Cropper(imageToCrop, { aspectRatio: 1, viewMode: 1, autoCropArea: 1 });
            };
            reader.readAsDataURL(file);
        }
        this.value = null;
    });

    function cropImage() {
        const canvas = cropper.getCroppedCanvas({ width: 300, height: 300 });
        const base64Image = canvas.toDataURL("image/webp");
        const mainPreview = document.getElementById('main-preview');
        const placeholder = document.getElementById('main-preview-placeholder');
        
        mainPreview.src = base64Image;
        mainPreview.style.display = 'block';
        if(placeholder) placeholder.style.display = 'none';

        document.getElementById('cropped_image_data').value = base64Image;
        formChanged = true; 
        closeCropModal();
    }

    function closeCropModal() {
        cropModal.style.opacity = '0';
        setTimeout(() => {
            cropModal.style.display = 'none';
            if(cropper) cropper.destroy();
        }, 300);
    }

    // --- 3. LOGIC LAINNYA ---
    function openTab(evt, tabName) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) tabcontent[i].style.display = "none";
        tablinks = document.getElementsByClassName("tab-btn");
        for (i = 0; i < tablinks.length; i++) tablinks[i].className = tablinks[i].className.replace(" active", "");
        document.getElementById(tabName).style.display = "block";
        if (evt) evt.currentTarget.className += " active";

        const newUrl = new URL(window.location);
        if(tabName === 'AturKategori') {
            newUrl.searchParams.set('tab', 'kategori');
            filterKategori();
        } else {
            newUrl.searchParams.delete('tab');
            newUrl.searchParams.delete('type');
        }
        window.history.pushState({}, '', newUrl);
    }

    function filterKategori() {
        const selectedType = document.getElementById('filterTipe').value;
        const rows = document.querySelectorAll('.kategori-row');
        let visibleCount = 0;
        rows.forEach(row => {
            const rowType = row.getAttribute('data-type');
            if (rowType === selectedType) { row.style.display = ''; visibleCount++; }
            else { row.style.display = 'none'; }
        });
        document.getElementById('empty-msg').style.display = (visibleCount === 0) ? 'block' : 'none';
    }

    function showDeleteConfirm() {
        const modal = document.getElementById('deleteConfirmModal');
        modal.style.display = 'flex';
        setTimeout(() => modal.style.opacity = '1', 10);
    }

    function closeDeleteConfirm() {
        const modal = document.getElementById('deleteConfirmModal');
        modal.style.opacity = '0';
        setTimeout(() => modal.style.display = 'none', 300);
    }

    function openDeleteCatModal(deleteUrl) {
        // Set link tombol "Ya, Hapus" sesuai URL delete kategori yang diklik
        document.getElementById('btn-confirm-delete-cat').href = deleteUrl;
        
        const modal = document.getElementById('deleteCatModal');
        modal.style.display = 'flex';
        setTimeout(() => modal.style.opacity = '1', 10);
    }

    function closeDeleteCatModal() {
        const modal = document.getElementById('deleteCatModal');
        modal.style.opacity = '0';
        setTimeout(() => modal.style.display = 'none', 300);
    }
</script>

<?php include 'popupcustom.php'; ?>
</body>
</html>