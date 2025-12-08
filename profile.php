<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login/login.php"); exit();
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
    header("Location: login/login.php"); exit();
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
    $def_icon = 'bx-category'; 
    $def_short = 0;

    $conn->query("INSERT INTO categories (user_id, type, name, icon, is_shortcut) VALUES ('$user_id', '$type', '$name', '$def_icon', '$def_short')");
    $_SESSION['popup_status'] = 'success'; $_SESSION['popup_message'] = 'Kategori berhasil ditambahkan!';
    header("Location: profile.php?tab=kategori&type=$type"); exit();
}

if (isset($_GET['del_cat'])) {
    $id_cat = $_GET['del_cat'];
    $q_cek = $conn->query("SELECT type FROM categories WHERE id='$id_cat' AND user_id='$user_id'");
    if($row = $q_cek->fetch_assoc()){
        $type_saat_hapus = $row['type'];
        $conn->query("DELETE FROM categories WHERE id='$id_cat' AND user_id='$user_id'");
        $_SESSION['popup_status'] = 'success'; $_SESSION['popup_message'] = 'Kategori berhasil dihapus!';
        header("Location: profile.php?tab=kategori&type=$type_saat_hapus"); exit();
    }
}

$u_res = $conn_valselt->query("SELECT * FROM users WHERE id='$user_id'");
$user_data = $u_res->fetch_assoc();
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
                <button class="tab-btn active" onclick="openTab(event, 'EditProfil')"><i class='bx bx-user'></i> Edit Profil</button>
                <button class="tab-btn" onclick="openTab(event, 'AturKategori')"><i class='bx bx-layer'></i> Atur Kategori</button>
            </div>

            <div id="EditProfil" class="tab-content" style="display: block;">
                <a href="https://valseltid.ivanaldorino.web.id/index.php" id="btn-valselt" class="btn btn-primary" target="_blank">
                    Edit Profil & Ganti Foto di Valselt ID <i class='bx bx-link-external'></i>
                </a>
            </div>

            <div id="AturKategori" class="tab-content" style="display: none;">
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
                        $cats = $conn->query("SELECT * FROM categories WHERE user_id='$user_id' ORDER BY type DESC, name ASC");
                        while($c = $cats->fetch_assoc()): 
                        ?>
                        <tr class="kategori-row" data-type="<?php echo $c['type']; ?>" style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:15px; width: 50px;">
                                <i class='bx <?php echo $c['icon'] ?? 'bx-category'; ?>' style="font-size: 1.5rem; color: #64748b;"></i>
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
                                <a href="?del_cat=<?php echo $c['id']; ?>&tab=kategori&type=<?php echo $c['type']; ?>" class="text-danger" onclick="return confirm('Hapus kategori ini?')">
                                    <i class='bx bx-trash' style="font-size:1.2rem;"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </table>
                    <div id="empty-msg" style="padding:20px; text-align:center; display:none; color:#64748b;">Belum ada kategori.</div>
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
        <h3 class="popup-title" style="margin-bottom: 20px;">Edit Kategori</h3>
        
        <form method="POST">
            <input type="hidden" name="edit_id" id="edit_cat_id">
            <input type="hidden" name="edit_type" id="edit_cat_type">
            <input type="hidden" name="edit_icon" id="edit_cat_icon_input"> 
            
            <div class="form-group">
                <label class="form-label">Nama Kategori</label>
                <input type="text" name="edit_name" id="edit_cat_name" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">Pilih Ikon</label>
                <div class="icon-search-wrapper">
                    <i class='bx bx-search'></i>
                    <input type="text" id="iconSearchInput" class="icon-search-input" placeholder="Cari ikon..." onkeyup="filterIcons()">
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

<script>
    const availableIcons = [
        // === KEUANGAN, BISNIS & BELANJA ===
        'bx-money', 'bx-wallet', 'bx-wallet-alt', 'bx-credit-card', 'bx-credit-card-alt', 
        'bx-dollar', 'bx-dollar-circle', 'bx-bitcoin', 'bx-euro', 'bx-yen', 'bx-pound', 'bx-ruble', 'bx-rupee',
        'bx-cart', 'bx-cart-alt', 'bx-shopping-bag', 'bx-basket', 'bx-store', 'bx-store-alt', 'bx-shop',
        'bx-purchase-tag', 'bx-purchase-tag-alt', 'bx-barcode', 'bx-receipt', 'bx-calculator', 
        'bx-bank', 'bx-building-house', 'bx-buildings', 'bx-home', 'bx-home-circle',
        'bx-briefcase', 'bx-briefcase-alt', 'bx-archive', 'bx-box', 'bx-package', 'bx-gift',
        'bx-line-chart', 'bx-bar-chart', 'bx-bar-chart-alt', 'bx-bar-chart-square', 'bx-pie-chart', 
        'bx-pie-chart-alt', 'bx-trending-up', 'bx-trending-down', 'bx-coin', 'bx-coin-stack', 
        'bx-diamond', 'bx-crown', 'bx-award', 'bx-badge', 'bx-star', 'bx-trophy', 'bx-medal',

        // === MAKANAN & MINUMAN ===
        'bx-food-menu', 'bx-restaurant', 'bx-coffee', 'bx-coffee-togo', 'bx-beer', 'bx-drink', 'bx-water', 
        'bx-wine', 'bx-martini', 'bx-bowl-rice', 'bx-bowl-hot', 'bx-cookie', 'bx-cake', 
        'bx-baguette', 'bx-dish', 'bx-fridge', 'bx-cheese', 'bx-pizza', 'bx-popsicle',

        // === TRANSPORTASI & PERJALANAN ===
        'bx-car', 'bx-bus', 'bx-bus-school', 'bx-taxi', 'bx-train', 'bx-train-subway', 'bx-cycling', 
        'bx-walk', 'bx-run', 'bx-gas-pump', 'bx-charging-station', 'bx-map', 'bx-map-pin', 'bx-map-alt',
        'bx-navigation', 'bx-compass', 'bx-rocket', 'bx-plane', 'bx-plane-alt', 'bx-plane-take-off', 
        'bx-plane-land', 'bx-anchor', 'bx-ship', 'bx-traffic-cone', 'bx-hotel', 'bx-trip', 'bx-world', 
        'bx-globe', 'bx-planet', 'bx-flag',

        // === TEKNOLOGI & GADGET ===
        'bx-mobile', 'bx-mobile-alt', 'bx-mobile-vibration', 'bx-phone', 'bx-phone-call', 'bx-phone-incoming',
        'bx-laptop', 'bx-desktop', 'bx-mouse', 'bx-mouse-alt', 'bx-keyboard', 'bx-headphone', 'bx-speaker',
        'bx-camera', 'bx-camera-movie', 'bx-video', 'bx-video-recording', 'bx-webcam', 'bx-microphone',
        'bx-wifi', 'bx-wifi-off', 'bx-bluetooth', 'bx-cast', 'bx-hdd', 'bx-memory-card', 'bx-microchip', 'bx-chip',
        'bx-usb', 'bx-plug', 'bx-server', 'bx-data', 'bx-cloud', 'bx-cloud-upload', 'bx-cloud-download',
        'bx-tv', 'bx-broadcast', 'bx-radar', 'bx-station', 'bx-satellite', 'bx-game', 'bx-joystick', 'bx-joystick-alt',

        // === RUMAH, UTILITAS & KELUARGA ===
        'bx-home-heart', 'bx-bed', 'bx-bath', 'bx-chair', 'bx-cabinet', 'bx-door-open', 'bx-window',
        'bx-bulb', 'bx-trash', 'bx-trash-alt', 'bx-wrench', 'bx-hammer', 'bx-paint', 'bx-brush', 'bx-spray-can',
        'bx-palette', 'bx-cut', 'bx-ruler', 'bx-pencil', 'bx-pen', 'bx-edit', 'bx-edit-alt',
        'bx-key', 'bx-lock', 'bx-lock-open', 'bx-lock-alt', 'bx-shield', 'bx-shield-alt-2', 'bx-cctv',
        'bx-baby-carriage', 'bx-male', 'bx-female', 'bx-user', 'bx-user-circle', 'bx-group', 
        'bx-face', 'bx-happy', 'bx-happy-alt', 'bx-sad', 'bx-sleepy', 'bx-shocked', 'bx-cool',

        // === KESEHATAN & OLAHRAGA ===
        'bx-heart', 'bx-heart-circle', 'bx-pulse', 'bx-plus-medical', 'bx-first-aid', 'bx-health', 
        'bx-capsule', 'bx-injection', 'bx-dna', 'bx-virus', 'bx-bacteria', 'bx-bandage',
        'bx-dumbbell', 'bx-football', 'bx-basketball', 'bx-tennis-ball', 'bx-bowling-ball', 'bx-baseball', 
        'bx-swim', 'bx-body', 'bx-brain', 'bx-spa', 'bx-timer', 'bx-stopwatch',

        // === PENDIDIKAN & KANTOR ===
        'bx-book', 'bx-book-open', 'bx-book-heart', 'bx-book-bookmark', 'bx-bookmarks', 'bx-library',
        'bx-notepad', 'bx-note', 'bx-paste', 'bx-copy', 'bx-file', 'bx-file-blank', 'bx-folder', 'bx-folder-open',
        'bx-envelope', 'bx-envelope-open', 'bx-send', 'bx-mail-send', 'bx-message', 'bx-message-dots',
        'bx-calendar', 'bx-calendar-check', 'bx-calendar-event', 'bx-time', 'bx-time-five', 'bx-alarm', 'bx-bell',
        'bx-paperclip', 'bx-pin', 'bx-link', 'bx-link-external', 'bx-printer', 'bx-id-card', 'bx-search', 'bx-search-alt',

        // === ALAM & CUACA ===
        'bx-sun', 'bx-moon', 'bx-cloud-rain', 'bx-cloud-lightning', 'bx-cloud-snow', 'bx-wind', 
        'bx-leaf', 'bx-tree', 'bx-flower', 'bx-landscape', 'bx-bug', 'bx-dog', 'bx-cat', 'bx-bone',
        'bx-fire', 'bx-water',

        // === MEDIA & SOSIAL ===
        'bx-music', 'bx-play', 'bx-play-circle', 'bx-pause', 'bx-stop', 'bx-rewind', 'bx-fast-forward',
        'bx-volume-full', 'bx-volume-mute', 'bx-like', 'bx-dislike', 'bx-comment', 'bx-share', 'bx-share-alt',
        'bx-image', 'bx-images', 'bx-slideshow', 'bx-film', 'bx-movie-play',

        // === SIMBOL & LAINNYA ===
        'bx-check', 'bx-check-circle', 'bx-check-double', 'bx-x', 'bx-x-circle', 
        'bx-plus', 'bx-plus-circle', 'bx-minus', 'bx-minus-circle', 'bx-question-mark', 'bx-info-circle',
        'bx-error', 'bx-error-circle', 'bx-warning', 'bx-notification', 'bx-power-off', 'bx-log-out', 'bx-log-in',
        'bx-grid-alt', 'bx-list-ul', 'bx-list-ol', 'bx-menu', 'bx-dots-horizontal', 'bx-dots-vertical',
        'bx-chevron-up', 'bx-chevron-down', 'bx-chevron-left', 'bx-chevron-right',
        'bx-sort', 'bx-filter', 'bx-filter-alt', 'bx-slider', 'bx-slider-alt',
        'bx-toggle-left', 'bx-toggle-right', 'bx-radio-circle', 'bx-checkbox-checked'
    ];


    function renderIcons(selectedIcon, filterText = '') {
        const grid = document.getElementById('iconGrid');
        const input = document.getElementById('edit_cat_icon_input');
        grid.innerHTML = ''; // Reset grid

        // Filter array ikon berdasarkan teks pencarian
        const filteredIcons = availableIcons.filter(icon => 
            icon.toLowerCase().includes(filterText.toLowerCase())
        );

        if (filteredIcons.length === 0) {
            grid.innerHTML = '<div style="grid-column: 1/-1; text-align:center; color:#94a3b8; font-size:0.8rem; padding:10px;">Ikon tidak ditemukan</div>';
            return;
        }

        filteredIcons.forEach(icon => {
            const div = document.createElement('div');
            // Cek apakah ikon ini adalah yang sedang dipilih
            const isSelected = (icon === selectedIcon); 
            div.className = `icon-option ${isSelected ? 'selected' : ''}`;
            div.innerHTML = `<i class='bx ${icon}'></i>`;
            
            div.onclick = function() {
                // Hapus class selected dari item lain di DOM saat ini
                document.querySelectorAll('.icon-option').forEach(el => el.classList.remove('selected'));
                // Tambah class ke item ini
                div.classList.add('selected');
                // Update hidden input
                input.value = icon;
                
                // Simpan ikon terpilih ke variabel global (agar persist saat search)
                // (Opsional, tergantung UX yang diinginkan)
            };
            grid.appendChild(div);
        });
    }

    function filterIcons() {
        const searchText = document.getElementById('iconSearchInput').value;
        const currentSelectedIcon = document.getElementById('edit_cat_icon_input').value;
        renderIcons(currentSelectedIcon, searchText);
    }
    
    // Update fungsi openEditCatModal agar mereset search bar
    function openEditCatModal(id, name, type, icon, isShortcut) {
        document.getElementById('edit_cat_id').value = id;
        document.getElementById('edit_cat_name').value = name;
        document.getElementById('edit_cat_type').value = type;
        document.getElementById('edit_cat_icon_input').value = icon;
        
        // Reset Search Bar
        document.getElementById('iconSearchInput').value = '';
        
        // Set Checkbox
        document.getElementById('edit_cat_shortcut').checked = (isShortcut == 1);

        // Render Semua Ikon (Tanpa Filter Awal)
        renderIcons(icon);

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

    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        if(urlParams.get('tab') === 'kategori'){
            const tabBtn = document.querySelector("button[onclick*='AturKategori']");
            if(tabBtn) { tabBtn.click(); }
            setTimeout(filterKategori, 100); 
        }
    });

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
</script>

<?php include 'popupcustom.php'; ?>
</body>
</html>