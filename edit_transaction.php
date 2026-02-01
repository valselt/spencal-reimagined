<?php
session_start();
require 'config.php';

// Load AWS Exception untuk menangani error upload
use Aws\Exception\AwsException;

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

if (!isset($_GET['id'])) { header("Location: transactions.php"); exit(); }
$trx_id = $_GET['id'];

// Ambil data transaksi lama
$stmt = $conn->prepare("SELECT t.*, c.type as cat_type FROM transactions t JOIN categories c ON t.category_id = c.id WHERE t.id = ? AND t.user_id = ?");
$stmt->bind_param("ii", $trx_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) { header("Location: transactions.php?msg=error"); exit(); }

// --- LOGIC UPDATE TRANSAKSI ---
if (isset($_POST['update_transaksi'])) {
    $tgl = $_POST['tanggal'];
    $cat_id = $_POST['sub_jenis'];
    $note = htmlspecialchars($_POST['catatan']);
    $amount = str_replace('.', '', $_POST['total']); 
    
    // Status apakah foto lama dihapus user? (0 = tidak, 1 = ya)
    $is_photo_deleted = $_POST['is_photo_deleted'] ?? 0;

    // Ambil URL foto lama dari database sebagai default
    $final_photo_url = $data['photo_url'];

    // 1. Jika user klik hapus foto di UI, set URL jadi NULL
    if ($is_photo_deleted == '1') {
        $final_photo_url = null;
    }

    // 2. Cek apakah ada file BARU yang diupload (Akan menimpa foto lama/null)
    if (isset($_FILES['bukti_foto']) && $_FILES['bukti_foto']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['bukti_foto']['tmp_name'];
        $fileName    = $_FILES['bukti_foto']['name'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        $allowedfileExtensions = array('jpg', 'jpeg', 'png');
        
        if (in_array($fileExtension, $allowedfileExtensions)) {
            // --- KONVERSI KE WEBP ---
            $imageResource = null;
            if ($fileExtension === 'jpg' || $fileExtension === 'jpeg') {
                $imageResource = @imagecreatefromjpeg($fileTmpPath);
            } elseif ($fileExtension === 'png') {
                $imageResource = @imagecreatefrompng($fileTmpPath);
                imagepalettetotruecolor($imageResource);
                imagealphablending($imageResource, true);
                imagesavealpha($imageResource, true);
            }

            if ($imageResource) {
                ob_start();
                imagewebp($imageResource, null, 80); 
                $webpData = ob_get_contents();
                ob_end_clean();
                imagedestroy($imageResource);

                // Upload ke S3
                $newFileName = "photos/{$user_id}_" . time() . "_" . bin2hex(random_bytes(4)) . ".webp";
                try {
                    $result = $s3->putObject([
                        'Bucket' => $s3_bucket,
                        'Key'    => $newFileName,
                        'Body'   => $webpData,
                        'ACL'    => 'public-read',
                        'ContentType' => 'image/webp'
                    ]);
                    $final_photo_url = $result['ObjectURL']; // Update dengan URL baru
                } catch (AwsException $e) {
                    // Error handle (opsional)
                }
            }
        }
    }

    // 3. Update Database (Termasuk kolom photo_url)
    // PERBAIKAN DI SINI: Tipe data diubah menjadi "sisssii"
    // s (date string), i (cat_id), s (note), s (amount string), s (photo_url string), i (id), i (user_id)
    $update_stmt = $conn->prepare("UPDATE transactions SET date=?, category_id=?, note=?, amount=?, photo_url=? WHERE id=? AND user_id=?");
    $update_stmt->bind_param("sisssii", $tgl, $cat_id, $note, $amount, $final_photo_url, $trx_id, $user_id);
    
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
    
    <style>
        /* --- COPY STYLE DARI INDEX.PHP UNTUK UPLOAD --- */
        .upload-area {
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            padding: 30px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8fafc;
            position: relative;
            overflow: hidden;
        }
        .upload-area:hover, .upload-area.dragover { border-color: #4f46e5; background: #eef2ff; }
        .upload-placeholder { display: flex; flex-direction: column; align-items: center; color: #64748b; }
        .upload-icon { font-size: 2.5rem; color: #4f46e5; margin-bottom: 10px; }
        .upload-text { font-size: 0.9rem; font-weight: 500; }
        .upload-limit { font-size: 0.75rem; color: #94a3b8; margin-top: 5px; }

        .preview-container { display: none; position: relative; width: 100%; height: 100%; }
        .preview-image { width: 100%; height: 200px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0; }
        .file-info { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; background: white; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .file-name { font-size: 0.85rem; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px; }
        .btn-remove-file { background: #fee2e2; color: #ef4444; border: none; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 600; transition: 0.2s; }
        .btn-remove-file:hover { background: #fecaca; }
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
            <form method="POST" enctype="multipart/form-data">
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

                <div class="form-group">
                    <label class="form-label">Edit Bukti Foto</label>
                    
                    <input type="hidden" name="is_photo_deleted" id="is_photo_deleted" value="0">
                    
                    <input type="file" name="bukti_foto" id="bukti_foto" class="hidden-input" accept="image/*" style="display: none;" onchange="handleFileSelect(this)">
                    
                    <div class="upload-area" id="uploadArea" onclick="document.getElementById('bukti_foto').click()">
                        
                        <div class="upload-placeholder" id="uploadPlaceholder">
                            <i class='bx bx-cloud-upload upload-icon'></i>
                            <span class="upload-text">Klik untuk ganti foto</span>
                            <span class="upload-limit">JPG / PNG Only (Auto Convert WebP)</span>
                        </div>

                        <div class="preview-container" id="previewContainer">
                            <img id="imgPreview" class="preview-image" src="">
                            <div class="file-info" onclick="event.stopPropagation()">
                                <span class="file-name" id="fileName">Foto Saat Ini</span>
                                <button type="button" class="btn-remove-file" onclick="removeFile()">
                                    <i class='bx bx-trash'></i> Hapus
                                </button>
                            </div>
                        </div>
                    </div>
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

    // --- LOGIC MODERN UPLOAD (ADAPTASI DARI INDEX.PHP) ---
    const existingPhotoUrl = "<?php echo $data['photo_url']; ?>";

    document.addEventListener("DOMContentLoaded", function() {
        const initialType = document.getElementById('initial_type').value;
        const initialCatId = document.getElementById('initial_cat_id').value;
        document.getElementById('jenis_transaksi').value = initialType;
        updateSubJenis(initialCatId);

        // Logic tampilkan foto lama jika ada
        if (existingPhotoUrl) {
            showPreview(existingPhotoUrl, "Foto Tersimpan");
        }
    });

    function handleFileSelect(input) {
        const file = input.files[0];
        if (file) {
            const validTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            if (!validTypes.includes(file.type)) {
                alert("Format file tidak didukung! Harap upload JPG atau PNG.");
                removeFile(); // Reset
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                // Set flag delete jadi 0 karena user upload baru
                document.getElementById('is_photo_deleted').value = "0";
                showPreview(e.target.result, file.name + " (Baru)");
            };
            reader.readAsDataURL(file);
        }
    }

    function showPreview(src, name) {
        const placeholder = document.getElementById('uploadPlaceholder');
        const previewContainer = document.getElementById('previewContainer');
        const imgPreview = document.getElementById('imgPreview');
        const fileName = document.getElementById('fileName');
        const uploadArea = document.getElementById('uploadArea');

        imgPreview.src = src;
        fileName.innerText = name;
        
        placeholder.style.display = 'none';
        previewContainer.style.display = 'block';
        
        uploadArea.style.borderStyle = 'solid';
        uploadArea.style.borderColor = '#e2e8f0';
        uploadArea.style.background = '#ffffff';
        uploadArea.style.padding = '10px';
    }

    function removeFile() {
        const input = document.getElementById('bukti_foto');
        const placeholder = document.getElementById('uploadPlaceholder');
        const previewContainer = document.getElementById('previewContainer');
        const uploadArea = document.getElementById('uploadArea');

        // Reset Input File
        input.value = '';
        
        // Set Flag Delete = 1 (Artinya user ingin menghapus foto lama juga)
        document.getElementById('is_photo_deleted').value = "1";

        // Reset UI ke Placeholder
        placeholder.style.display = 'flex';
        previewContainer.style.display = 'none';
        
        uploadArea.style.borderStyle = 'dashed';
        uploadArea.style.borderColor = '#cbd5e1';
        uploadArea.style.background = '#f8fafc';
        uploadArea.style.padding = '30px 20px';
    }

    // Drag and Drop (Sama seperti index.php)
    const dropArea = document.getElementById('uploadArea');
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, function(e) { e.preventDefault(); e.stopPropagation(); }, false);
    });
    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, () => dropArea.classList.add('dragover'), false);
    });
    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, () => dropArea.classList.remove('dragover'), false);
    });
    dropArea.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        document.getElementById('bukti_foto').files = files;
        handleFileSelect(document.getElementById('bukti_foto'));
    }, false);
</script>

</body>
</html>