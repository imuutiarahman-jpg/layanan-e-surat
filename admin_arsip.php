<?php
include 'koneksi.php';
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'admin' || (($_SESSION['login_via'] ?? '') !== 'panel-admin')) {
    header("Location: panel-admin.php");
    exit;
}

$allowed_prodi = ['Sistem Informasi', 'Geografi', 'Ilmu Komputer', 'Bisnis Digital', 'Teknik Sipil', 'Akuakultur', 'Agribisnis', 'Peternakan'];
$list_prodi = $allowed_prodi;

// Handle Delete Request — wajib POST + CSRF (cegah hapus via link/prefetch)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        die("Token keamanan tidak valid. Silakan kembali dan muat ulang halaman.");
    }
    $delete_id = (int)$_POST['delete_id'];
    if ($delete_id <= 0) { die("ID tidak valid."); }

    $view_r = in_array($_POST['view'] ?? '', ['mahasiswa', 'dosen'], true) ? $_POST['view'] : 'mahasiswa';
    $prodi_r = in_array($_POST['prodi'] ?? '', $allowed_prodi, true) ? $_POST['prodi'] : '';
    $search_r = mb_substr(trim($_POST['search'] ?? ''), 0, 100);

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
    $deleted = false;
    $delete_stmt = mysqli_prepare($koneksi, "DELETE FROM permohonan_surat WHERE id = ?");
    if ($delete_stmt) {
        mysqli_stmt_bind_param($delete_stmt, "i", $delete_id);
        $deleted = mysqli_stmt_execute($delete_stmt);
    }

    $params = ['view' => $view_r];
    if ($prodi_r !== '') { $params['prodi'] = $prodi_r; }
    if ($search_r !== '') { $params['search'] = $search_r; }
    $params[$deleted ? 'deleted' : 'delete_error'] = 1;

    header("Location: admin_arsip.php?" . http_build_query($params));
    exit;
}

// Filter Logika (whitelist agar tampilan tetap, query aman)
$view = isset($_GET['view']) ? $_GET['view'] : 'mahasiswa';
if (!in_array($view, ['mahasiswa', 'dosen'], true)) { $view = 'mahasiswa'; }
$prodi_filter = isset($_GET['prodi']) ? $_GET['prodi'] : '';
if ($prodi_filter !== '' && !in_array($prodi_filter, $allowed_prodi, true)) { $prodi_filter = ''; }
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search = mb_substr($search, 0, 100);
$role_target = ($view == 'dosen') ? 'dosen' : 'mahasiswa';

$sql = "SELECT permohonan_surat.*, users.nama_lengkap, users.role, users.prodi 
        FROM permohonan_surat 
        JOIN users ON permohonan_surat.user_id = users.id 
        WHERE permohonan_surat.status IN ('Selesai', 'Ditolak') AND users.role = ?";

if (!empty($prodi_filter)) {
    $sql .= " AND users.prodi = ?";
}

if (!empty($search)) {
    $sql .= " AND (users.nama_lengkap LIKE ? OR permohonan_surat.jenis_surat LIKE ? OR users.nomor_induk LIKE ?)";
}

$sql .= " ORDER BY permohonan_surat.id DESC";

$stmt_arsip = mysqli_prepare($koneksi, $sql);
if (!$stmt_arsip) {
    die("Gagal menyiapkan data arsip. Silakan coba lagi.");
}

if (!empty($prodi_filter) && !empty($search)) {
    $s_term = "%$search%";
    mysqli_stmt_bind_param($stmt_arsip, "sssss", $role_target, $prodi_filter, $s_term, $s_term, $s_term);
} elseif (!empty($prodi_filter)) {
    mysqli_stmt_bind_param($stmt_arsip, "ss", $role_target, $prodi_filter);
} elseif (!empty($search)) {
    $s_term = "%$search%";
    mysqli_stmt_bind_param($stmt_arsip, "ssss", $role_target, $s_term, $s_term, $s_term);
} else {
    mysqli_stmt_bind_param($stmt_arsip, "s", $role_target);
}

mysqli_stmt_execute($stmt_arsip);
$query = mysqli_stmt_get_result($stmt_arsip);

if (!$query) {
    die("Gagal mengambil data arsip. Silakan coba lagi.");
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
        .alert { padding: 14px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
    <link rel="stylesheet" href="responsive.css">
</head>
<body class="admin-list">
    <div class="header">
        <div style="display: flex; align-items: center;">
            <img src="logo/logo.png" alt="Logo" style="height: 90px; margin-right: 15px;">
            <h2 style="margin: 0;">Arsip Surat</h2>
        </div>
        <div>
            <a href="dashboard_admin.php" style="background: #3498db; margin-right: 20px;">Kembali ke Dashboard</a>
            <a href="logout.php" style="background: #e74c3c;">Sign Out</a>
        </div>
    </div>

    <div class="content">
        <div class="card">
            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-success">Data surat berhasil dihapus.</div>
            <?php elseif (isset($_GET['delete_error'])): ?>
                <div class="alert alert-error">Gagal menghapus data. Silakan coba lagi.</div>
            <?php endif; ?>
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
                <?= !empty($prodi_filter) ? " - Prodi " . htmlspecialchars($prodi_filter, ENT_QUOTES, 'UTF-8') : "" ?>
            </h3>

            <div class="table-wrap">
            <table>
                <tr>
                    <th>User & Info</th>
                    <th>No. Surat</th>
                    <th>Jenis Surat</th>
                    <th>Tgl Selesai/Kirim</th>
                    <th>Status</th>
                    <th>Hasil Akhir</th>
                    <th>Aksi</th>
                </tr>
                <?php while($row = mysqli_fetch_assoc($query)) { 
                    $vars = !empty($row['data_surat']) ? (json_decode($row['data_surat'], true) ?: []) : [];
                    $nomor_full = isset($vars['nomor_surat']) ? $vars['nomor_surat'] : '-';
                    $nomor_surat = explode('/', $nomor_full)[0];
                ?>
                <tr>
                    <td>
                        <b><?= htmlspecialchars($row['nama_lengkap']); ?></b><br>
                        <small style="color:#718096"><?= $row['prodi'] ? htmlspecialchars($row['prodi']) : ucfirst($row['role']); ?></small>
                    </td>
                    <td><?= htmlspecialchars($nomor_full); ?></td>
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
                            <!-- Tombol Lihat: Menampilkan halaman review variabel atau pratinjau inline -->
                            <a href="cetak_surat.php?id=<?= (int)$row['id']; ?>" target="_blank" class="btn-view" style="color: #3498db;">👁 Lihat</a>
                            
                            <!-- Tombol Cetak: Memaksa unduhan file -->
                            <a href="cetak_surat.php?id=<?= (int)$row['id']; ?>&download=1" class="btn-view" style="color: #2ecc71; margin-left:10px;">📥 Cetak</a>
                        <?php } else { ?>
                            <small style="color: #a0aec0;">-</small>
                        <?php } ?>
                    </td>
                    <td>
                        <form method="POST" action="admin_arsip.php" style="margin: 0;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data arsip ini?');">
                            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="delete_id" value="<?= (int)$row['id'] ?>">
                            <input type="hidden" name="view" value="<?= htmlspecialchars($view, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="prodi" value="<?= htmlspecialchars($prodi_filter, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="btn-delete">Hapus</button>
                        </form>
                    </td>
                </tr>
                <?php } ?>
                <?php if(mysqli_num_rows($query) == 0) { ?>
                    <tr><td colspan="7" style="text-align:center;">Belum ada arsip surat.</td></tr>
                <?php } ?>
            </table>
            </div>
        </div>
    </div>
</body>
</html>