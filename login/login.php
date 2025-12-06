<?php
// Tentukan URL Login Valselt (Port 9000)
$valselt_login_url = "https://valseltid.ivanaldorino.web.id/login.php";

// Tentukan URL Callback Spencal (Port 1649)
// Ini adalah alamat di mana Valselt harus mengembalikan user setelah login sukses
$my_callback_url = "http://localhost:1649/auth_callback.php";

// Redirect User ke Valselt
header("Location: " . $valselt_login_url . "?redirect_to=" . urlencode($my_callback_url));
exit();
?>