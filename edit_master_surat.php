<?php
include 'koneksi.php';
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'admin' || (($_SESSION['login_via'] ?? '') !== 'panel-admin')) {
    header("Location: panel-admin.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { die("ID tidak valid."); }
$stmt = mysqli_prepare($koneksi, "SELECT * FROM master_surat_jenis WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($res);

if (!$data) {
    die("Data tidak ditemukan.");
}

$list_prodi = ['Umum', 'Dosen', 'Sistem Informasi', 'Geografi', 'Ilmu Komputer', 'Bisnis Digital', 'Teknik Sipil', 'Akuakultur', 'Agribisnis', 'Peternakan'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Jenis Surat | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }
        .container { max-width: 600px; margin-top: 50px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    </style>
    <link rel="stylesheet" href="responsive.css">
</head>
<body>
<div class="container">
    <div class="card p-4">
        <h4 class="mb-4">Edit Jenis Surat</h4>
        <form action="proses_master_surat.php" method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="edit_jenis">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            
            <div class="mb-3">
                <label class="form-label fw-bold">Nama Jenis Surat</label>
                <input type="text" name="nama_surat" class="form-control" value="<?= htmlspecialchars($data['nama_surat']) ?>" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-bold">Kategori / Prodi</label>
                <select name="prodi" class="form-select" required>
                    <?php foreach($list_prodi as $p): ?>
                        <option value="<?= $p ?>" <?= ($data['prodi'] == $p) ? 'selected' : '' ?>><?= $p ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Berkas Pendukung</label>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="require_berkas" value="1" id="editReqBerkas" <?= $data['require_berkas'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="editReqBerkas">Aktifkan Unggah Berkas</label>
                </div>
                <input type="text" name="info_berkas" class="form-control" value="<?= htmlspecialchars($data['info_berkas']) ?>" placeholder="Instruksi berkas...">
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">Variabel Tambahan</label>
                <input type="text" name="variabel_tambahan" class="form-control" value="<?= htmlspecialchars($data['variabel_tambahan']) ?>">
            </div>
            
            <div class="d-flex justify-content-between">
                <a href="admin_master_surat.php" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>