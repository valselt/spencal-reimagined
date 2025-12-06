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
if (isset($_POST['add_cat'])) {
    $type = $_POST['cat_type']; $name = htmlspecialchars($_POST['cat_name']);
    $conn->query("INSERT INTO categories (user_id, type, name) VALUES ('$user_id', '$type', '$name')");
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

$u_res = $conn->query("SELECT * FROM users WHERE id='$user_id'");
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
                <form action="profile.php" method="POST" id="profileForm">
                    <input type="hidden" name="cropped_image" id="cropped_image_data">
                    
                    <div class="profile-flex-container">
                        <div class="profile-avatar-section">
                            <?php if($user_data['profile_pic']): ?>
                                <img src="<?php echo $user_data['profile_pic']; ?>" id="main-preview" class="profile-pic-preview" style="width:100px; height:100px; border-radius:50%; object-fit:cover;">
                            <?php else: ?>
                                <div id="main-preview-placeholder" class="profile-pic-preview" style="background:var(--primary); width:100px; height:100px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-size:2rem; margin:0 auto;">
                                    <?php echo strtoupper(substr($user_data['username'], 0, 2)); ?>
                                </div>
                                <img src="" id="main-preview" style="display:none; width:100px; height:100px; border-radius:50%; object-fit:cover; margin: 0 auto;">
                            <?php endif; ?>
                            
                            <input type="file" id="hidden-file-input" accept="image/png, image/jpeg, image/jpg">
                            
                            <div style="text-align:center;">
                                <button type="button" class="btn-change-photo" onclick="document.getElementById('hidden-file-input').click()">
                                    <i class='bx bx-pencil'></i> Ganti
                                </button>
                            </div>
                        </div>

                        <div class="profile-form-section">
                            <div class="form-group">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Password Baru</label>
                                <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah password">
                            </div>

                            <button type="submit" name="update_profile" class="btn btn-primary" style="width:auto; padding: 12px 25px;"><i class='bx bx-save'></i> Simpan Perubahan</button>
                        </div>
                    </div>
                </form>

                <div class="danger-zone">
                    <div class="danger-title"><i class='bx bx-error-circle'></i> Zona Berbahaya</div>
                    <p class="danger-desc">Menghapus akun akan menghilangkan semua data transaksi...</p>
                    <form method="POST" id="deleteForm">
                        <button type="button" onclick="showDeleteConfirm()" class="btn-danger"><i class='bx bx-trash'></i> Hapus Akun Saya Permanen</button>
                        <input type="hidden" name="delete_account" value="1">
                    </form>
                </div>
            </div>

            <div id="AturKategori" class="tab-content" style="display: none;">
                <h3 style="margin-bottom:20px;">Kelola Kategori Transaksi</h3>
                <form method="POST" style="display:flex; gap:10px; margin-bottom:20px; align-items:flex-end;">
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
                            <td style="padding:15px;">
                                <span class="badge-tipe <?php echo ($c['type']=='pemasukan')?'badge-in':'badge-out'; ?>">
                                    <?php echo ucfirst($c['type']); ?>
                                </span>
                            </td>
                            <td style="padding:15px; font-weight:500;"><?php echo htmlspecialchars($c['name']); ?></td>
                            <td style="padding:15px; text-align:right;">
                                <a href="?del_cat=<?php echo $c['id']; ?>&tab=kategori&type=<?php echo $c['type']; ?>" class="text-danger" onclick="return confirm('Hapus kategori ini?')">
                                    <i class='bx bx-trash'></i>
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