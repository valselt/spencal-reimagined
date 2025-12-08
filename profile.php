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
                                <?php 
                                    $iconName = $c['icon'] ?? 'bx-category';
                                    // Cek apakah ikon dimulai dengan 'bx-' (Boxicons) atau tidak (Google Icons)
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

<script>
    const availableIcons = {

    // ===========================
    // KEUANGAN & BELANJA
    // ===========================
    finance: [
        'payments','account_balance_wallet','credit_card','attach_money','monetization_on',
        'currency_bitcoin','receipt_long','calculate','savings','paid','store','local_offer',
        'redeem','shopping_cart','shopping_bag','shopping_basket','price_change',
        'price_check','receipt','point_of_sale','add_card','atm','barcode_reader','wallet',
        'card_membership','card_giftcard','loyalty','request_quote','trending_up','trending_down',
        'trending_flat','account_balance','account_tree','analytics','bar_chart','leaderboard',
        'account_balance','balance','insights','stacked_line_chart','query_stats'
    ],

    // ===========================
    // MAKANAN & MINUMAN
    // ===========================
    food: [
        'restaurant','grocery','local_dining','lunch_dining','fastfood','bakery_dining','local_cafe',
        'local_bar','local_pizza','icecream','ramen_dining','kitchen','egg','water_drop',
        'local_drink','set_meal','dinner_dining','brunch_dining','rice_bowl','flatware','fork_spoon','hanami_dango','coffee','wine_bar',
        'liquor','tapas','outdoor_grill','cookie','nutrition','emoji_food_beverage'
    ],

    // ===========================
    // TRANSPORTASI
    // ===========================
    transportation: [
        'directions_car','directions_bus','train','tram','subway','directions_railway',
        'local_taxi','pedal_bike','directions_walk','flight','airport_shuttle','hotel',
        'commute','map','navigation','local_gas_station','ev_station','scooter','sailing',
        'directions_boat','rv_hookup','car_rental','car_crash','bus_alert','airline_seat_recline_normal',
        'airline_seat_individual_suite','airplane_ticket','connecting_airports'
    ],

    // ===========================
    // TEKNOLOGI & GADGET
    // ===========================
    tech: [
        'smartphone','phone_iphone','phone_android','laptop','desktop_windows','desktop_mac',
        'headphones','camera_alt','videocam','tv','keyboard','mouse','router','bluetooth',
        'wifi','memory','sd_card','print','speaker','watch','devices','cast','monitor',
        'security','usb','mouse','keyboard_alt','gamepad','mic','mic_none','mic_off',
        'headphones_battery','battery_full','battery_5_bar','battery_3_bar','battery_saver',
        'brightness_auto','dark_mode','light_mode','contrast','data_usage','signal_cellular_alt','cloud',
        'cloud_sync','cloud_download','cloud_upload','cloud_off','dns','qr_code','qr_code_scanner',
        'barcode','storage','hub','device_unknown','headset_mic'
    ],

    // ===========================
    // RUMAH & KEBUTUHAN
    // ===========================
    home: [
        'home','chair','bed','bathtub','lightbulb','chair_alt','house_siding','cottage',
        'villa','weekend','window','door_front','yard','vacuum','cleaning_services','build',
        'plumbing','electrical_services','carpenter','handyman','roofing','grass','fireplace',
        'ac_unit','air_purifier','heat_pump','iron','local_laundry_service','dry','kitchen',
        'oven_gen','cooking','dishwasher','microwave','heat','oven','oven_gen','skillet','skillet_cooktop','stockpot','range_hood'
    ],

    // ===========================
    // KESEHATAN & OLAHRAGA
    // ===========================
    health: [
        'favorite','favorite_border','monitor_heart','medical_services','medication','medication_liquid',
        'healing','health_and_safety','vaccines','coronavirus','masks','thermostat','bloodtype',
        'fitness_center','sports_soccer','sports_basketball','sports_tennis','sports_volleyball',
        'sports_mma','sports_kabaddi','sports_esports','pool','directions_run','directions_bike',
        'pedal_bike','person_add','self_improvement','emoji_people'
    ],

    // ===========================
    // PENDIDIKAN & PERKANTORAN
    // ===========================
    office: [
        'menu_book','edit','description','folder','folder_open','create_new_folder','drive_file_move',
        'calendar_month','schedule','alarm','notifications','person','groups','badge','print','work',
        'school','auto_stories','library_books','library_add','fact_check','rule','task_alt','checklist',
        'edit_note','note','sticky_note_2','assignment','draft','approval','bookmark','book','meeting_room',
        'event','event_available','event_note','note_add','workspaces','workspace_premium'
    ],

    // ===========================
    // SOSIAL, KOMUNIKASI & MEDIA
    // ===========================
    social: [
        'chat','chat_bubble','sms','call','call_end','call_made','call_received','call_missed',
        'contacts','contact_page','email','mark_email_unread','forum','groups','group_add','share',
        'share_location','public','language','person_add','person_remove','person_pin','face','face_retouching_natural',
        'mood','mood_bad','emoji_emotions','emoji_events','emoji_objects','thumb_up','thumb_down',
        'handshake','diversity_1','diversity_2','diversity_3','sentiment_satisfied','sentiment_dissatisfied',
        'sentiment_very_satisfied','sentiment_very_dissatisfied','support','live_help'
    ],

    // ===========================
    // AKSI / ACTION ICONS
    // ===========================
    action: [
        'add','remove','close','done','check','check_circle','done_all','refresh','autorenew','settings',
        'settings_suggest','settings_accessibility','settings_backup_restore','logout','login','swap_vert',
        'swap_horiz','open_in_new','open_with','home_app_logo','info','warning','error','help','tips_and_updates',
        'star','star_half','grade','visibility','visibility_off','lock','lock_open','manage_accounts','verified',
        'priority_high','lightbulb_circle','ads_click','bolt','extension','history','hourglass_empty',
        'hourglass_full','build_circle','reorder','update','upgrade','fingerprint','translate'
    ],

    // ===========================
    // KONTEN / EDITOR / UTILITAS
    // ===========================
    content: [
        'content_copy','content_copy','cut','content_cut','content_paste','save','cloud_done','cloud_upload',
        'cloud_download','delete','delete_forever','backspace','undo','redo','text_format','font_download',
        'format_bold','format_italic','format_underlined','format_align_left','format_align_center',
        'format_align_right','format_align_justify','format_list_bulleted','format_list_numbered','insert_photo',
        'insert_chart','insert_link','insert_emoticon','table_chart','border_color','border_all','brush','palette',
        'draw','image','crop','crop_free','crop_square','filter','filter_alt','tune','adjust','style','color_lens',
        'wallpaper','animation','burst_mode','hdr_auto','panorama','slideshow'
    ],

    // ===========================
    // NAVIGASI
    // ===========================
    navigation: [
        'menu','apps','more_vert','more_horiz','arrow_back','arrow_forward','arrow_upward','arrow_downward',
        'chevron_left','chevron_right','expand_more','expand_less','fullscreen','fullscreen_exit','unfold_more',
        'unfold_less','refresh','first_page','last_page','subdirectory_arrow_right','subdirectory_arrow_left',
        'home_work','explore','explore_off'
    ],

    // ===========================
    // MAPS & LOKASI
    // ===========================
    maps: [
        'map','location_on','location_off','location_searching','location_disabled','near_me','place',
        'local_hospital','local_police','local_fire_department','local_post_office','local_florist',
        'local_library','local_mall','local_movies','local_laundry_service','local_offer','zoom_in_map',
        'zoom_out_map','directions','directions_run','directions_walk','directions_bus','directions_car',
        'local_shipping','delivery_dining','run_circle','emergency','terrain','satellite'
    ],

    // ===========================
    // MISC & LAINNYA
    // ===========================
    miscellaneous: [
        'pets','child_care','child_friendly','toys','music_note','music_video','movie','theaters',
        'book_online','local_activity','celebration','cake','party_mode','stars','auto_awesome',
        'auto_awesome_mosaic','auto_fix_high','auto_fix_normal','auto_fix_off','magic_button',
        'bubble_chart','insights','psychology','science','sports','downhill_skiing','surfing',
        'kayaking','paragliding','skateboarding','snowboarding','snowshoeing','smoking_rooms','smoke_free','vaping_rooms'
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

        if (activeCategory === 'all') {
            // Gabungkan semua array menjadi satu array flat
            iconsToRender = Object.values(availableIcons).flat();
        } else {
            // Ambil array dari kategori spesifik
            iconsToRender = availableIcons[activeCategory] || [];
        }

        // Filter berdasarkan teks search
        const filteredIcons = iconsToRender.filter(icon => 
            icon.toLowerCase().includes(filterText.toLowerCase())
        );

        if (filteredIcons.length === 0) {
            grid.innerHTML = '<div style="grid-column: 1/-1; text-align:center; color:#94a3b8; font-size:0.8rem; padding:10px;">Ikon tidak ditemukan</div>';
            return;
        }

        // Batasi jumlah render jika terlalu banyak (opsional, untuk performa)
        // const limit = 200; 
        // const finalIcons = filteredIcons.slice(0, limit);

        filteredIcons.forEach(icon => {
            const div = document.createElement('div');
            const isSelected = (icon === selectedIcon); 
            div.className = `icon-option ${isSelected ? 'selected' : ''}`;
            
            // Render Google Icons
            div.innerHTML = `<span class="material-symbols-rounded" style="font-size: 24px;">${icon}</span>`;
            div.title = icon;
            
            div.onclick = function() {
                document.querySelectorAll('.icon-option').forEach(el => el.classList.remove('selected'));
                div.classList.add('selected');
                input.value = icon;
            };
            grid.appendChild(div);
        });
    }

    function filterIcons() {
        const searchText = document.getElementById('iconSearchInput').value;
        const currentSelectedIcon = document.getElementById('edit_cat_icon_input').value;
        if(searchText.length > 0) activeCategory = 'all'; renderCategoryChips();
        
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