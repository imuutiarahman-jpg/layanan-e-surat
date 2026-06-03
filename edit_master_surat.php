<?php
include 'koneksi.php';
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$id = mysqli_real_escape_string($koneksi, $_GET['id']);
$query = mysqli_query($koneksi, "SELECT * FROM master_surat_jenis WHERE id = '$id'");
$data = mysqli_fetch_assoc($query);

if (!$data) {
    die("Data tidak ditemukan.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Jenis Surat | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Inter', sans-serif; }
        .container { max-width: 600px; margin-top: 50px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
<div class="container">
    <div class="card p-4">
        <h4 class="mb-4">Edit Jenis Surat</h4>
        <form action="proses_master_surat.php" method="POST">
            <input type="hidden" name="action" value="edit_jenis">
            <input type="hidden" name="id" value="<?= $data['id'] ?>">
            
            <div class="mb-3">
                <label class="form-label fw-bold">Nama Jenis Surat</label>
                <input type="text" name="nama_surat" class="form-control" value="<?= htmlspecialchars($data['nama_surat']) ?>" required>
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