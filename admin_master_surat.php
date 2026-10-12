<?php
include 'koneksi.php';
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'admin' || (($_SESSION['login_via'] ?? '') !== 'panel-admin')) {
    header("Location: panel-admin.php");
    exit;
}

// Fungsi pembantu untuk format waktu "time ago"
function time_ago($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;
    
    $minutes = round($seconds / 60);
    $hours   = round($seconds / 3600);
    $days    = round($seconds / 86400);
    $months  = round($seconds / 2629440);
    $years   = round($seconds / 31553280);

    if($seconds <= 60) return "baru saja";
    else if($minutes <= 60) return "$minutes menit lalu";
    else if($hours <= 24) return "$hours jam lalu";
    else if($days <= 30) return "$days hari lalu";
    else if($months <= 12) return "$months bulan lalu";
    else return "$years tahun lalu";
}

$view = isset($_GET['view']) ? $_GET['view'] : 'mahasiswa';
if (!in_array($view, ['mahasiswa', 'dosen'], true)) { $view = 'mahasiswa'; }
$prodi_active = isset($_GET['prodi']) ? $_GET['prodi'] : 'Umum';
$list_prodi = ['Umum', 'Sistem Informasi', 'Geografi', 'Ilmu Komputer', 'Bisnis Digital', 'Teknik Sipil', 'Akuakultur', 'Agribisnis', 'Peternakan'];
$full_list_prodi = array_merge($list_prodi, ['Dosen']);
if ($view === 'mahasiswa' && $prodi_active !== 'Semua' && !in_array($prodi_active, $list_prodi, true)) { $prodi_active = 'Umum'; }

$query = false;
if ($view == 'dosen') {
    $stmt_q = mysqli_prepare($koneksi, "SELECT * FROM master_surat_jenis WHERE prodi = 'Dosen' ORDER BY id ASC");
    if ($stmt_q) { mysqli_stmt_execute($stmt_q); $query = mysqli_stmt_get_result($stmt_q); }
} else {
    if ($prodi_active != 'Semua') {
        $stmt_q = mysqli_prepare($koneksi, "SELECT * FROM master_surat_jenis WHERE prodi = ? ORDER BY id ASC");
        if ($stmt_q) { mysqli_stmt_bind_param($stmt_q, "s", $prodi_active); mysqli_stmt_execute($stmt_q); $query = mysqli_stmt_get_result($stmt_q); }
    } else {
        $stmt_q = mysqli_prepare($koneksi, "SELECT * FROM master_surat_jenis WHERE prodi != 'Dosen' ORDER BY id ASC");
        if ($stmt_q) { mysqli_stmt_execute($stmt_q); $query = mysqli_stmt_get_result($stmt_q); }
    }
}

if (!$query) {
    die("Kesalahan Database. Jika tabel hilang, silakan jalankan script SQL untuk tabel 'master_surat_jenis'.");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Manajemen Jenis Surat | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .table thead th { background-color: #f1f4f8; color: #495057; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; border: none; }
        .badge-variable { font-size: 0.7rem; font-weight: 500; margin-right: 3px; }
        .btn-stack .btn { display: block; width: 100%; margin-bottom: 5px; font-size: 0.8rem; font-weight: 600; text-align: left; }
        .nav-tabs { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #edf2f7; padding-bottom: 10px; }
        .tab-links { display: flex; gap: 10px; }
        .tab-links a { padding: 10px 20px; text-decoration: none; color: #718096; border-radius: 8px; font-weight: 600; transition: 0.3s; font-size: 14px; }
        .tab-links a.active { background: #667eea; color: white; }
        .prodi-filter { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 25px; }
        .prodi-filter a { font-size: 12px; padding: 6px 12px; background: #ebedf0; color: #4a5568; text-decoration: none; border-radius: 5px; }
        .prodi-filter a.active { background: #4a5568; color: white; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container">
        <a href="dashboard_admin.php" class="navbar-brand d-flex align-items-center">
            <img src="logo/logo.png" alt="Logo" width="80" height="80" class="d-inline-block me-2">
            E-Surat Admin
        </a>
        <a href="dashboard_admin.php" class="btn btn-outline-light btn-sm">Kembali</a>
    </div>
</nav>

<div class="container pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Manajemen Jenis Surat</h4>
        <!-- PERBAIKAN: Menambahkan trigger modal Bootstrap -->
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#tambahModal">+ Tambah Jenis Surat</button>
    </div>

    <div class="nav-tabs">
        <div class="tab-links">
            <a href="admin_master_surat.php?view=mahasiswa" class="<?= $view=='mahasiswa'?'active':'' ?>">Manajemen Mahasiswa</a>
            <a href="admin_master_surat.php?view=dosen" class="<?= $view=='dosen'?'active':'' ?>">Manajemen Dosen</a>
        </div>
    </div>

    <?php if($view == 'mahasiswa'): ?>
    <div class="prodi-filter">
        <a href="admin_master_surat.php?view=mahasiswa&prodi=Semua" class="<?= $prodi_active=='Semua'?'active':'' ?>">Semua Prodi</a>
        <?php foreach($list_prodi as $p): ?>
            <a href="admin_master_surat.php?view=mahasiswa&prodi=<?= urlencode($p) ?>" class="<?= $prodi_active==$p?'active':'' ?>"><?= $p ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="card p-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Jenis</th>
                        <th style="width: 30%;">Field</th>
                        <th>Diperbaharui</th>
                        <th style="width: 180px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no=1; if ($query) while($row = mysqli_fetch_assoc($query)): ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($row['nama_surat']) ?></div>
                        </td>
                        <td>
                            <?php if(!empty($row['variabel_tambahan']) || $row['require_berkas']): ?>
                                <?php foreach(explode(',', $row['variabel_tambahan']) as $vt): ?>
                                    <span class="badge bg-primary badge-variable"><?= trim($vt) ?></span>
                                <?php endforeach; ?>
                                <?php if($row['require_berkas']): ?>
                                    <span class="badge bg-warning text-dark badge-variable" title="<?= htmlspecialchars($row['info_berkas']) ?>"><i class="fa-solid fa-paperclip"></i> Berkas Wajib</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <small class="text-muted">-</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small class="d-block text-dark"><?= htmlspecialchars($row['updated_by']) ?></small>
                            <small class="text-muted"><?= time_ago($row['updated_at']) ?></small>
                        </td>
                        <td class="btn-stack">
                            <!-- PERBAIKAN: Mengarahkan link edit ke halaman edit dengan membawa parameter ID -->
                            <a href="edit_master_surat.php?id=<?= (int)$row['id'] ?>" class="btn btn-success">✎ Edit</a>
                            <a href="templates/<?= htmlspecialchars(basename($row['file_template']), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-info text-white" download>⬇ Download DOCX</a>
                            <button class="btn btn-warning text-dark" 
                                    onclick="openUploadModal(<?= (int)$row['id'] ?>, '<?= htmlspecialchars($row['nama_surat'], ENT_QUOTES, 'UTF-8') ?>')">
                                🔄 Ganti Template
                            </button>
                            <form action="proses_master_surat.php" method="POST" onsubmit="return confirm('Yakin ingin menghapus jenis surat ini?');" style="display:inline;">
                                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="hapus_jenis">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm mt-1" style="width: 100%; text-align: left;">🗑 Hapus Jenis</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL BARU: Tambah Jenis Surat             -->
<!-- ========================================== -->
<div class="modal fade" id="tambahModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="proses_master_surat.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Jenis Surat Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="tambah_jenis">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama Jenis Surat</label>
                        <input type="text" name="nama_surat" class="form-control" required placeholder="Contoh: Surat Keterangan Aktif Kuliah">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Kategori / Prodi</label>
                        <select name="prodi" class="form-select" required>
                            <?php foreach($full_list_prodi as $p): ?>
                                <option value="<?= $p ?>" <?= ($view == 'dosen' && $p == 'Dosen') || ($prodi_active == $p) ? 'selected' : '' ?>><?= $p ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Variabel Tambahan (Opsional)</label>
                        <input type="text" name="variabel_tambahan" class="form-control" placeholder="Contoh: ${instansi}, ${keperluan}">
                        <small class="text-muted mt-1 d-block" style="font-size: 0.75rem;">Gunakan format seperti bawaan, pisahkan dengan tanda koma jika lebih dari satu.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Berkas Pendukung</label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="require_berkas" value="1" id="reqBerkas">
                            <label class="form-check-label" for="reqBerkas">Aktifkan Unggah Berkas</label>
                        </div>
                        <input type="text" name="info_berkas" class="form-control form-control-sm" placeholder="Instruksi berkas (Contoh: Lampirkan KTP/KTM)">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">File Template (.docx)</label>
                        <input type="file" name="file_template" class="form-control" accept=".docx" required>
                        <small class="text-danger mt-1 d-block" style="font-size: 0.75rem;">* File template wajib berformat Word (.docx)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Jenis Surat</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Upload Template -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="proses_master_surat.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ganti Template DOCX</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="upload_template">
                    <input type="hidden" name="id_jenis" id="modal_id_jenis">
                    
                    <div class="mb-3">
                        <label class="form-label">Jenis Surat</label>
                        <input type="text" id="modal_nama_surat" class="form-control" readonly disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">File Template Baru (.docx)</label>
                        <input type="file" name="file_rtf" class="form-control" accept=".docx" required>
                        <small class="text-danger mt-1 d-block">* Pastikan format file adalah .docx</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning">Unggah & Ganti</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openUploadModal(id, nama) {
    document.getElementById('modal_id_jenis').value = id;
    document.getElementById('modal_nama_surat').value = nama;
    var myModal = new bootstrap.Modal(document.getElementById('uploadModal'));
    myModal.show();
}
</script>
</body>
</html>