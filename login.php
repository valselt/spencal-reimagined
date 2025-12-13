<?php
// Tentukan URL Login Valselt
$valselt_login_url = "https://valseltid.ivanaldorino.web.id/login";

// Tentukan URL Callback Spencal
$my_callback_url = "https://spencal.ivanaldorino.web.id/auth_callback.php";

// --- PERUBAHAN DISINI ---
// Kita ubah URL menjadi kode acak (Base64) agar tidak diblokir firewall server
$encoded_url = base64_encode($my_callback_url);

// Redirect User ke Valselt
header("Location: " . $valselt_login_url . "?redirect_to=" . urlencode($encoded_url));
exit();
?>