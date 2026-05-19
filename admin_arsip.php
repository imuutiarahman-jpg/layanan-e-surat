<?php
include 'koneksi.php';
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// Handle Delete Request
if (isset($_GET['delete_id'])) {
    $delete_id = mysqli_real_escape_string($koneksi, $_GET['delete_id']);

    // Optional: Delete associated files if 'surat_jadi' is not 'SYSTEM_GENERATED'
    // This part is commented out for now, but can be uncommented if file deletion is desired.
    /*
    $get_file_query = mysqli_query($koneksi, "SELECT surat_jadi FROM permohonan_surat WHERE id = '$delete_id'");
    $file_data = mysqli_fetch_assoc($get_file_query);
    if ($file_data && $file_data['surat_jadi'] != 'SYSTEM_GENERATED' && !empty($file_data['surat_jadi'])) {
        $file_path = 'uploads/' . $file_data['surat_jadi'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    */

    // Delete the permohonan_surat entry
    $delete_stmt = mysqli_prepare($koneksi, "DELETE FROM permohonan_surat WHERE id = ?");
    if ($delete_stmt) {
        mysqli_stmt_bind_param($delete_stmt, "i", $delete_id);
        mysqli_stmt_execute($delete_stmt);
        echo "<script>alert('Data surat berhasil dihapus.'); window.location='admin_arsip.php?view=" . htmlspecialchars($_GET['view']) . "&prodi=" . urlencode($_GET['prodi']) . "&search=" . urlencode($_GET['search']) . "';</script>";
    } else {
        echo "<script>alert('Gagal menghapus data surat: " . mysqli_error($koneksi) . "'); window.location='admin_arsip.php?view=" . htmlspecialchars($_GET['view']) . "&prodi=" . urlencode($_GET['prodi']) . "&search=" . urlencode($_GET['search']) . "';</script>";
    }
    exit;
}

// Filter Logika
$view = isset($_GET['view']) ? $_GET['view'] : 'mahasiswa';
$prodi_filter = isset($_GET['prodi']) ? $_GET['prodi'] : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($koneksi, $_GET['search']) : '';

$sql = "SELECT permohonan_surat.*, users.nama_lengkap, users.role, users.prodi 
        FROM permohonan_surat 
        JOIN users ON permohonan_surat.user_id = users.id 
        WHERE permohonan_surat.status IN ('Selesai', 'Ditolak') 
        AND users.role = '$view'";

if ($view == 'mahasiswa' && !empty($prodi_filter)) {
    $sql .= " AND users.prodi = '$prodi_filter'";
}

if (!empty($search)) {
    $sql .= " AND (users.nama_lengkap LIKE '%$search%' OR permohonan_surat.jenis_surat LIKE '%$search%' OR users.nomor_induk LIKE '%$search%')";
}

$sql .= " ORDER BY permohonan_surat.id DESC";
$query = mysqli_query($koneksi, $sql);

// Cek apakah kueri berhasil dijalankan
if (!$query) {
    die("Gagal mengambil data arsip. Pastikan kolom 'prodi' sudah ada di tabel 'users'. Error: " . mysqli_error($koneksi));
}

$list_prodi = ['Sistem Informasi', 'Geografi', 'Ilmu Komputer', 'Bisnis Digital', 'Teknik Sipil', 'Akuakultur', 'Agribisnis', 'Peternakan'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Arsip Surat | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7f6; margin: 0; color: #333; }
        .header { background: #2c3e50; color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .header a { color: #ecf0f1; text-decoration: none; padding: 8px 15px; border-radius: 5px; font-size: 14px; }
        .content { padding: 40px; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; border-bottom: 2px solid #edf2f7; color: #718096; font-size: 13px; text-transform: uppercase; }
        td { padding: 15px; border-bottom: 1px solid #edf2f7; font-size: 14px; }
        .badge { padding: 5px 12px; border-radius: 20px; color: white; font-size: 11px; font-weight: 600; }
        .bg-selesai { background: #48bb78; }
        .bg-ditolak { background: #f56565; }
        .btn-delete { background: #f56565; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600; text-decoration: none; }
        .btn-view { color: #667eea; text-decoration: none; font-size: 13px; font-weight: 600; margin-right: 10px; }
        .nav-tabs { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #edf2f7; padding-bottom: 10px; }
        .tab-links { display: flex; gap: 10px; }
        .tab-links a { padding: 10px 20px; text-decoration: none; color: #718096; border-radius: 8px; font-weight: 600; transition: 0.3s; }
        .tab-links a.active { background: #667eea; color: white; }
        .prodi-filter { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 25px; }
        .prodi-filter a { font-size: 12px; padding: 6px 12px; background: #ebedf0; color: #4a5568; text-decoration: none; border-radius: 5px; }
        .prodi-filter a.active { background: #4a5568; color: white; }
        .search-box { display: flex; gap: 5px; }
        .search-box input { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px; width: 250px; outline: none; }
        .search-box button { background: #667eea; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Arsip Surat</h2>
        <div>
            <a href="dashboard_admin.php" style="background: #3498db; margin-right: 10px;">Kembali ke Dashboard</a>
            <a href="logout.php" style="background: #e74c3c;">Sign Out</a>
        </div>
    </div>

    <div class="content">
        <div class="card">
            <div class="nav-tabs">
                <div class="tab-links">
                    <a href="admin_arsip.php?view=mahasiswa" class="<?= $view=='mahasiswa'?'active':'' ?>">Arsip Mahasiswa</a>
                    <a href="admin_arsip.php?view=dosen" class="<?= $view=='dosen'?'active':'' ?>">Arsip Dosen</a>
                </div>
                <form method="GET" action="admin_arsip.php" class="search-box">
                    <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
                    <?php if(!empty($prodi_filter)): ?>
                        <input type="hidden" name="prodi" value="<?= htmlspecialchars($prodi_filter) ?>">
                    <?php endif; ?>
                    <input type="text" name="search" placeholder="Cari nama atau jenis surat..." 
                           value="<?= htmlspecialchars($search) ?>">
                    <button type="submit">Cari</button>
                    <?php if(!empty($search)): ?>
                        <a href="admin_arsip.php?view=<?= $view ?>&prodi=<?= urlencode($prodi_filter) ?>" style="font-size: 12px; align-self: center; margin-left: 5px; color: #f56565; text-decoration: none;">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if($view == 'mahasiswa'): ?>
            <div class="prodi-filter">
                <a href="admin_arsip.php?view=mahasiswa" class="<?= empty($prodi_filter)?'active':'' ?>">Semua Prodi</a>
                <?php foreach($list_prodi as $p): ?>
                    <a href="admin_arsip.php?view=mahasiswa&prodi=<?= urlencode($p) ?>" class="<?= $prodi_filter==$p?'active':'' ?>"><?= $p ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <h3 style="margin-bottom: 25px;">
                Data Arsip: <?= ucfirst($view) ?> 
                <?= !empty($prodi_filter) ? " - Prodi $prodi_filter" : "" ?>
            </h3>

            <table>
                <tr>
                    <th>User & Info</th>
                    <th>Layanan</th>
                    <th>Tgl Selesai/Kirim</th>
                    <th>Status</th>
                    <th>Hasil Akhir</th>
                    <th>Aksi</th>
                </tr>
                <?php while($row = mysqli_fetch_assoc($query)) { ?>
                <tr>
                    <td>
                        <b><?= htmlspecialchars($row['nama_lengkap']); ?></b><br>
                        <small style="color:#718096"><?= $row['prodi'] ? htmlspecialchars($row['prodi']) : ucfirst($row['role']); ?></small>
                    </td>
                    <td><?= htmlspecialchars($row['jenis_surat']); ?></td>
                    <td><?= htmlspecialchars($row['tgl_pengajuan']); ?></td>
                    <td>
                        <?php 
                            $class = ($row['status'] == 'Selesai') ? 'bg-selesai' : 'bg-ditolak';
                        ?>
                        <span class="badge <?= $class; ?>"><?= htmlspecialchars($row['status']); ?></span>
                    </td>
                    <td>
                        <?php if($row['status'] == 'Selesai' && !empty($row['surat_jadi'])) { ?>
                            <?php if($row['surat_jadi'] == 'SYSTEM_GENERATED') { ?>
                                <a href="cetak_surat.php?id=<?= $row['id']; ?>" target="_blank" class="btn-view">Lihat / Cetak</a>
                            <?php } else { ?>
                                <a href="uploads/<?= htmlspecialchars($row['surat_jadi']); ?>" target="_blank" class="btn-view">Buka File</a>
                            <?php } ?>
                        <?php } else { ?>
                            <small style="color: #a0aec0;">-</small>
                        <?php } ?>
                    </td>
                    <td>
                        <a href="admin_arsip.php?delete_id=<?= $row['id'] ?>&view=<?= htmlspecialchars($view) ?>&prodi=<?= urlencode($prodi_filter) ?>&search=<?= urlencode($search) ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus data arsip ini?');" class="btn-delete">Hapus</a>
                    </td>
                </tr>
                <?php } ?>
                <?php if(mysqli_num_rows($query) == 0) { ?>
                    <tr><td colspan="5" style="text-align:center;">Belum ada arsip surat.</td></tr>
                <?php } ?>
            </table>
        </div>
    </div>
</body>
</html>