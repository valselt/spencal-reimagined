<?php
// Cek apakah ada pesan popup di session
if (isset($_SESSION['popup_status']) && isset($_SESSION['popup_message'])) {
    $status = $_SESSION['popup_status']; // 'success' atau 'error'
    $message = $_SESSION['popup_message'];
    
    // Tentukan Ikon & Judul
    if ($status == 'success') {
        $icon = "<i class='bx bx-check'></i>";
        $title = "Berhasil!";
        $btn_text = "Lanjutkan";
        $btn_class = "success";
    } else {
        $icon = "<i class='bx bx-x'></i>";
        $title = "Gagal!";
        $btn_text = "Coba Lagi";
        $btn_class = "error";
    }
?>
    <div class="popup-overlay" id="customPopup">
        <div class="popup-box">
            <div class="popup-icon-box <?php echo $status; ?>">
                <?php echo $icon; ?>
            </div>
            <h3 class="popup-title"><?php echo $title; ?></h3>
            <p class="popup-message"><?php echo $message; ?></p>
            <button onclick="closePopup()" class="popup-btn <?php echo $btn_class; ?>"><?php echo $btn_text; ?></button>
        </div>
    </div>

    <script>
        function closePopup() {
            const popup = document.getElementById('customPopup');
            popup.style.opacity = '0';
            setTimeout(() => {
                popup.remove();
            }, 300);
        }
    </script>
<?php
    // Hapus session agar popup tidak muncul lagi saat refresh
    unset($_SESSION['popup_status']);
    unset($_SESSION['popup_message']);
}
?>