<?php
// Penyaji file terproteksi: berkas pendukung (admin) & pratinjau DOCX sementara (admin).
// Akses langsung ke folder uploads/ ditutup lewat uploads/.htaccess.
include 'koneksi.php';

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$is_admin = (($_SESSION['role'] ?? '') === 'admin' && (($_SESSION['login_via'] ?? '') === 'panel-admin'));
if (!$is_admin) {
    http_response_code(403);
    die("Akses ditolak.");
}

$type = $_GET['type'] ?? '';
$id   = (int)($_GET['id'] ?? 0);
$path = '';
$download_name = '';

if ($type === 'berkas') {
    if ($id <= 0) { http_response_code(400); die("ID tidak valid."); }

    $stmt = mysqli_prepare($koneksi, "SELECT berkas_pendukung FROM permohonan_surat WHERE id = ? LIMIT 1");
    if (!$stmt) { http_response_code(500); die("Gagal memuat data."); }
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;

    $name = basename((string)($row['berkas_pendukung'] ?? ''));
    if ($name === '' || $name === '.' || $name === '..') {
        http_response_code(404);
        die("Berkas tidak ditemukan.");
    }
    $path = 'uploads/' . $name;
    $download_name = $name;
} elseif ($type === 'temp') {
    $name = basename((string)($_GET['f'] ?? ''));
    // Hanya izinkan nama pratinjau yang dibuat oleh cetak_surat.php
    if (!preg_match('/^docx_[a-f0-9]{32}_[A-Za-z0-9_]+\.docx$/', $name)) {
        http_response_code(400);
        die("Nama file tidak valid.");
    }
    $path = 'uploads/temp_admin/' . $name;
    $download_name = $name;
} else {
    http_response_code(400);
    die("Permintaan tidak valid.");
}

if (!is_file($path)) {
    http_response_code(404);
    die("File tidak ditemukan.");
}

// Pastikan file benar-benar berada di dalam folder uploads (anti path traversal)
$real_base = realpath('uploads');
$real_file = realpath($path);
if ($real_base === false || $real_file === false || strpos($real_file, $real_base) !== 0) {
    http_response_code(403);
    die("Akses file ditolak.");
}

$ext = strtolower(pathinfo($real_file, PATHINFO_EXTENSION));
$allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
if (!in_array($ext, $allowed, true)) {
    http_response_code(403);
    die("Tipe file tidak diizinkan.");
}

$ctype = 'application/octet-stream';
if ($ext === 'pdf') { $ctype = 'application/pdf'; }
elseif (in_array($ext, ['jpg', 'jpeg', 'png'], true)) { $ctype = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext); }
elseif (in_array($ext, ['doc', 'docx'], true)) { $ctype = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'; }

$disposition = in_array($ext, ['doc', 'docx'], true) ? 'attachment' : 'inline';
$safe_download_name = str_replace(['"', "\r", "\n"], '', $download_name);

if (ob_get_contents()) { ob_end_clean(); }
header('Content-Type: ' . $ctype);
header('Content-Length: ' . filesize($real_file));
header('Content-Disposition: ' . $disposition . '; filename="' . $safe_download_name . '"');
header('X-Content-Type-Options: nosniff');
readfile($real_file);
exit;
