<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login/login.php"); exit();
}

$user_id = $_SESSION['user_id'];
$msg = "";
$msg_type = ""; 

// --- LOGIC 1: UPDATE PROFILE ---
if (isset($_POST['update_profile'])) {
    $new_username = htmlspecialchars($_POST['username']);
    $new_email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $new_pass = $_POST['password'];
    
    // Upload Foto Logic
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] == 0) {
        $file_tmp = $_FILES['foto_profil']['tmp_name'];
        $file_name = $_FILES['foto_profil']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed = ['jpg', 'jpeg', 'png'];
        if (in_array($file_ext, $allowed)) {
            if ($file_ext == 'png') $image = imagecreatefrompng($file_tmp);
            else $image = imagecreatefromjpeg($file_tmp);

            ob_start();
            imagewebp($image, null, 80);
            $webp_data = ob_get_contents();
            ob_end_clean();
            imagedestroy($image);

            $timestamp = date('Y-m-d_H-i-s');
            $clean_name = pathinfo($file_name, PATHINFO_FILENAME);
            $s3_key = "spencal/photoprofile/{$timestamp}+{$clean_name}.webp";

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
                $msg = "Upload Gagal: " . $e->getMessage();
                $msg_type = "error";
            }
        }
    }

    $conn->query("UPDATE users SET username='$new_username', email='$new_email' WHERE id='$user_id'");

    if (!empty($new_pass)) {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password='$hash' WHERE id='$user_id'");
    }
    
    $_SESSION['username'] = $new_username;
    if(empty($msg)) {
        $msg = "Perubahan berhasil disimpan!";
        $msg_type = "success";
    }
}

// --- LOGIC 2: HAPUS AKUN ---
if (isset($_POST['delete_account'])) {
    $conn->query("DELETE FROM transactions WHERE user_id='$user_id'");
    $conn->query("DELETE FROM categories WHERE user_id='$user_id'");
    $conn->query("DELETE FROM users WHERE id='$user_id'");
    session_destroy();
    header("Location: index.php"); exit();
}

// --- LOGIC 3: CRUD KATEGORI ---
$active_type = isset($_GET['type']) ? $_GET['type'] : 'pengeluaran';

if (isset($_POST['add_cat'])) {
    $type = $_POST['cat_type']; 
    $name = htmlspecialchars($_POST['cat_name']);
    $conn->query("INSERT INTO categories (user_id, type, name) VALUES ('$user_id', '$type', '$name')");
    
    // Redirect tetap membawa parameter agar user tetap di tab kategori
    header("Location: profile.php?tab=kategori&type=$type"); 
    exit();
}

if (isset($_GET['del_cat'])) {
    $id_cat = $_GET['del_cat'];
    $q_cek = $conn->query("SELECT type FROM categories WHERE id='$id_cat' AND user_id='$user_id'");
    if($row = $q_cek->fetch_assoc()){
        $type_saat_hapus = $row['type'];
        $conn->query("DELETE FROM categories WHERE id='$id_cat' AND user_id='$user_id'");
        header("Location: profile.php?tab=kategori&type=$type_saat_hapus");
        exit();
    }
}

// Ambil Data User
$u_res = $conn->query("SELECT * FROM users WHERE id='$user_id'");
$user_data = $u_res->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Akun - Spencal</title>
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
                <li><a href="dashboard.php" class="menu-item"><i class='bx bxs-dashboard'></i> Dashboard</a></li>
            </ul>
        </div>
        
        <div class="sidebar-profile">
            <a href="profile.php" class="profile-info-link" title="Edit Profil">
                <div class="user-info">
                    <?php if(isset($user_data['profile_pic']) && $user_data['profile_pic']): ?>
                        <img src="<?php echo $user_data['profile_pic']; ?>" class="user-avatar" style="object-fit:cover; width:40px; height:40px; border-radius:50%; margin-right:10px;">
                    <?php else: ?>
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user_data['username'], 0, 2)); ?>
                        </div>
                    <?php endif; ?>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($user_data['username']); ?></h4>
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
            <h1>Pengaturan Akun</h1>
        </header>

        <?php if($msg): ?>
            <div class="card" style="margin-bottom:20px; background:<?php echo ($msg_type=='error')?'#fee2e2':'#dcfce7'; ?>; color:<?php echo ($msg_type=='error')?'#991b1b':'#166534'; ?>; padding:15px;">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="tab-nav">
                <button class="tab-btn active" onclick="openTab(event, 'EditProfil')"><i class='bx bx-user'></i> Edit Profil</button>
                <button class="tab-btn" onclick="openTab(event, 'AturKategori')"><i class='bx bx-layer'></i> Atur Kategori</button>
            </div>

            <div id="EditProfil" class="tab-content" style="display: block;">
                <form action="profile.php" method="POST" enctype="multipart/form-data">
                    <div class="profile-flex-container">
                        <div class="profile-avatar-section">
                            <?php if($user_data['profile_pic']): ?>
                                <img src="<?php echo $user_data['profile_pic']; ?>" class="profile-pic-preview" style="width:100px; height:100px; border-radius:50%; object-fit:cover;">
                            <?php else: ?>
                                <div class="profile-pic-preview" style="background:var(--primary); width:100px; height:100px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-size:2rem; margin:0 auto;">
                                    <?php echo strtoupper(substr($user_data['username'], 0, 2)); ?>
                                </div>
                            <?php endif; ?>
                            <small>Preview Foto</small>
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
                                <label class="form-label">Ganti Foto Profil (Upload File)</label>
                                <input type="file" name="foto_profil" class="form-control" accept="image/png, image/jpeg">
                                <small style="color:var(--text-muted);">Format: JPG/PNG. Otomatis convert ke WebP.</small>
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
                    <p class="danger-desc">Menghapus akun akan menghilangkan semua data transaksi dan kategori Anda secara permanen dan tidak bisa dikembalikan.</p>
                    <form method="POST" onsubmit="return confirm('Yakin ingin menghapus akun PERMANEN? Data tidak bisa kembali!');">
                        <button type="submit" name="delete_account" class="btn-danger"><i class='bx bx-trash'></i> Hapus Akun Saya Permanen</button>
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
                    
                    <div id="empty-msg" style="padding:20px; text-align:center; display:none; color:#64748b;">
                        Belum ada kategori untuk tipe ini.
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
    // --- 1. Fungsi Switch Tab ---
    function openTab(evt, tabName) {
        var i, tabcontent, tablinks;
        
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }
        
        tablinks = document.getElementsByClassName("tab-btn");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" active", "");
        }
        
        document.getElementById(tabName).style.display = "block";
        if (evt) {
            evt.currentTarget.className += " active";
        }

        // Logic Modern: Bersihkan/Update URL saat ganti tab agar tidak nyangkut
        const newUrl = new URL(window.location);
        if(tabName === 'AturKategori') {
            newUrl.searchParams.set('tab', 'kategori');
            filterKategori();
        } else {
            // Jika klik Edit Profil, hapus parameter tab & type agar URL bersih
            newUrl.searchParams.delete('tab');
            newUrl.searchParams.delete('type');
        }
        window.history.pushState({}, '', newUrl);
    }

    // --- 2. Fungsi Filter Kategori ---
    function filterKategori() {
        const selectedType = document.getElementById('filterTipe').value;
        const rows = document.querySelectorAll('.kategori-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const rowType = row.getAttribute('data-type');
            if (rowType === selectedType) {
                row.style.display = ''; 
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        const emptyMsg = document.getElementById('empty-msg');
        if(emptyMsg) {
            emptyMsg.style.display = (visibleCount === 0) ? 'block' : 'none';
        }
    }

    // --- 3. Inisialisasi saat Halaman Load ---
    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        
        if(urlParams.get('tab') === 'kategori'){
            const tabBtn = document.querySelector("button[onclick*='AturKategori']");
            if(tabBtn) {
                tabBtn.click();
            }
            setTimeout(filterKategori, 100); 
        }
    });
</script>

</body>
</html>