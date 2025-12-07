<?php
session_start();
// Hancurkan Session Spencal saja
session_destroy(); 

// Redirect kembali ke halaman utama Spencal
header("Location: index.php");
exit();
?>