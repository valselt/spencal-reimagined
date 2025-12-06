<?php
session_start();
session_destroy(); // Hapus sesi Spencal

// Redirect ke login Valselt lagi
header("Location: https://valseltid.ivanaldorino.web.id/logout.php");
exit();
?>