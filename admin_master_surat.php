<?php
include 'koneksi.php';
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
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

$query = mysqli_query($koneksi, "SELECT * FROM master_surat_jenis ORDER BY id ASC");
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
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container">
        <span class="navbar-brand">E-Surat Admin</span>
        <a href="dashboard_admin.php" class="btn btn-outline-light btn-sm">Kembali</a>
    </div>
</nav>

<div class="container pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Manajemen Jenis Surat</h4>
        <!-- PERBAIKAN: Menambahkan trigger modal Bootstrap -->
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#tambahModal">+ Tambah Jenis Surat</button>
    </div>

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
                    <?php $no=1; while($row = mysqli_fetch_assoc($query)): ?>
                    <tr>
                        <td><?= $no++; ?></td>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($row['nama_surat']) ?></div>
                        </td>
                        <td>
                            <?php if(!empty($row['variabel_tambahan'])): ?>
                                <?php foreach(explode(',', $row['variabel_tambahan']) as $vt): ?>
                                    <span class="badge bg-primary badge-variable"><?= trim($vt) ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <small class="text-muted">Ganti template untuk deteksi variabel</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small class="d-block text-dark"><?= htmlspecialchars($row['updated_by']) ?></small>
                            <small class="text-muted"><?= time_ago($row['updated_at']) ?></small>
                        </td>
                        <td class="btn-stack">
                            <!-- PERBAIKAN: Mengarahkan link edit ke halaman edit dengan membawa parameter ID -->
                            <a href="edit_master_surat.php?id=<?= $row['id'] ?>" class="btn btn-success">✎ Edit</a>
                            <a href="templates/<?= $row['file_template'] ?>" class="btn btn-info text-white" download>⬇ Download DOCX</a>
                            <button class="btn btn-warning text-dark" 
                                    onclick="openUploadModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['nama_surat']) ?>')">
                                🔄 Ganti Template
                            </button>
                            <form action="proses_master_surat.php" method="POST" onsubmit="return confirm('Yakin ingin menghapus jenis surat ini?');" style="display:inline;">
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
                        <label class="form-label fw-bold">Variabel Tambahan (Opsional)</label>
                        <input type="text" name="variabel_tambahan" class="form-control" placeholder="Contoh: ${instansi}, ${keperluan}">
                        <small class="text-muted mt-1 d-block" style="font-size: 0.75rem;">Gunakan format seperti bawaan, pisahkan dengan tanda koma jika lebih dari satu.</small>
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