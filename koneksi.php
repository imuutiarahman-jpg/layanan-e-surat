<?php
ob_start();
session_start();
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_layanan_surat";

// Disable exceptions to use procedural error handling (die)
mysqli_report(MYSQLI_REPORT_OFF);

$koneksi = mysqli_connect($host, $user, $pass, $db);
if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>