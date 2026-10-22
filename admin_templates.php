<?php
include 'koneksi.php';
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'admin' || (($_SESSION['login_via'] ?? '') !== 'panel-admin')) {
    header("Location: panel-admin.php");
    exit;
}

$template_dir = 'templates/';
if (!is_dir($template_dir)) {
    mkdir($template_dir, 0777, true);
}

// Ambil daftar template langsung dari database agar dinamis
$managed_templates = [];
$db_templates = mysqli_query($koneksi, "SELECT nama_surat as jenis, file_template as file FROM master_surat_jenis");
while($row_t = mysqli_fetch_assoc($db_templates)) {
    $managed_templates[] = $row_t;
}

// Proses Unggah Ganti Template (Langkah 3) — hardened tanpa ubah tampilan
if (isset($_POST['upload_template'])) {
    // File target harus cocok dengan daftar master di DB (cegah overwrite sembarang)
    $req_file = basename($_POST['filename'] ?? '');
    $valid = false;
    $chk = mysqli_prepare($koneksi, "SELECT id FROM master_surat_jenis WHERE file_template = ? LIMIT 1");
    if ($chk) {
        mysqli_stmt_bind_param($chk, "s", $req_file);
        mysqli_stmt_execute($chk);
        $rchk = mysqli_stmt_get_result($chk);
        if ($rchk && mysqli_fetch_assoc($rchk)) { $valid = true; }
    }
    if (!$valid) {
        $error_msg = "Nama template tidak terdaftar.";
    } else {
        $target_file = $template_dir . $req_file;
        $file_type = strtolower(pathinfo($_FILES["template_docx"]["name"] ?? '', PATHINFO_EXTENSION));
        $size_ok = (($_FILES["template_docx"]["size"] ?? 0) <= 10 * 1024 * 1024);
        $err_ok = (($_FILES["template_docx"]["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK);

        if (!$err_ok) {
            $error_msg = "File gagal diunggah.";
        } elseif ($file_type != "docx" || !$size_ok) {
            $error_msg = "Maaf, hanya file .docx maksimal 10MB yang diizinkan.";
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $_FILES["template_docx"]["tmp_name"]) : '';
            if ($finfo) finfo_close($finfo);
            if (!in_array($mime, ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'], true)) {
                $error_msg = "Isi file bukan dokumen Word yang valid.";
            } elseif (move_uploaded_file($_FILES["template_docx"]["tmp_name"], $target_file)) {
                $success_msg = "Template untuk '" . htmlspecialchars($_POST['jenis_surat'] ?? '', ENT_QUOTES, 'UTF-8') . "' berhasil diperbarui!";
            } else {
                $error_msg = "Terjadi kesalahan saat mengunggah file.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelola Template Surat | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7f6; margin: 0; color: #333; }
        .header { background: #2c3e50; color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .header a { color: #ecf0f1; text-decoration: none; background: #3498db; padding: 8px 15px; border-radius: 5px; font-size: 14px; }
        .content { padding: 40px; max-width: 900px; margin: auto; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        h3 { color: #2c3e50; margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; border-bottom: 2px solid #edf2f7; color: #718096; font-size: 13px; text-transform: uppercase; }
        td { padding: 12px; border-bottom: 1px solid #edf2f7; font-size: 14px; }
        .btn { padding: 8px 12px; border-radius: 5px; text-decoration: none; font-size: 13px; font-weight: 600; cursor: pointer; border: none; display: inline-block; }
        .btn-download { background: #2ecc71; color: white; }
        .btn-upload { background: #f39c12; color: white; }
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .upload-form { margin-top: 10px; background: #f9f9f9; padding: 15px; border-radius: 8px; display: none; border: 1px dashed #ddd; text-align: left; }
    </style>
    <link rel="stylesheet" href="responsive.css">
</head>
<body class="admin-list">
    <div class="header">
        <h2>Kelola Master Template (.docx)</h2>
        <a href="dashboard_admin.php">Kembali ke Dashboard</a>
    </div>

    <div class="content">
        <?php if(isset($success_msg)): ?>
            <div class="alert alert-success"><?= $success_msg ?></div>
        <?php endif; ?>
        <?php if(isset($error_msg)): ?>
            <div class="alert alert-error"><?= $error_msg ?></div>
        <?php endif; ?>

        <div class="card">
            <h3>Daftar Template Tersedia</h3>
            <p style="font-size: 13px; color: #7f8c8d; margin-bottom: 20px;">
                Gunakan placeholder seperti <b>${nama}</b>, <b>${nim}</b>, atau <b>${judul}</b> di dalam file Word Anda agar data terisi otomatis.
            </p>
            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Jenis Surat</th>
                        <th>Nama File Master</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($managed_templates as $tmpl): ?>
                    <tr>
                        <td><b><?= $tmpl['jenis'] ?></b></td>
                        <td><code><?= $tmpl['file'] ?></code></td>
                        <td style="text-align: right;">
                            <!-- Langkah 1: Download -->
                            <a href="<?= $template_dir . $tmpl['file'] ?>" class="btn btn-download" download>⬇ Lihat/Download</a>
                            
                            <!-- Langkah 3: Munculkan Form Ganti -->
                            <button class="btn btn-upload" onclick="document.getElementById('form-<?= md5($tmpl['file']) ?>').style.display='block'">🔄 Ganti Template</button>

                            <div id="form-<?= md5($tmpl['file']) ?>" class="upload-form">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="filename" value="<?= $tmpl['file'] ?>">
                                    <input type="hidden" name="jenis_surat" value="<?= $tmpl['jenis'] ?>">
                                    <label style="display:block; margin-bottom:10px; font-weight:bold;">Pilih File Baru (.docx):</label>
                                    <input type="file" name="template_docx" accept=".docx" required style="margin-bottom:10px;">
                                    <br>
                                    <button type="submit" name="upload_template" class="btn btn-download">Unggah & Simpan</button>
                                    <button type="button" class="btn" style="background:#95a5a6; color:white;" onclick="this.parentElement.parentElement.style.display='none'">Batal</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</body>
</html>