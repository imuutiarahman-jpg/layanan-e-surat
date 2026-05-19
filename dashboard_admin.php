<?php
include 'koneksi.php';
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// Ambil semua permohonan dari database gabung dengan data pengaju (user)
// Hanya menampilkan antrean aktif (Pending dan Diproses)
$query = mysqli_query($koneksi, "SELECT permohonan_surat.*, users.nama_lengkap, users.role 
                                 FROM permohonan_surat 
                                 JOIN users ON permohonan_surat.user_id = users.id 
                                 WHERE permohonan_surat.status IN ('Pending', 'Diproses')
                                 ORDER BY permohonan_surat.id DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel | E-Surat</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7f6; margin: 0; color: #333; }
        .header { background: #2c3e50; color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .header a { color: #ecf0f1; text-decoration: none; background: #e74c3c; padding: 8px 15px; border-radius: 5px; font-size: 14px; }
        .content { padding: 40px; }
        .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; border-bottom: 2px solid #edf2f7; color: #718096; font-size: 13px; text-transform: uppercase; }
        td { padding: 15px; border-bottom: 1px solid #edf2f7; font-size: 14px; }
        .badge { padding: 5px 12px; border-radius: 20px; color: white; font-size: 11px; font-weight: 600; }
        .bg-pending { background: #f6ad55; }
        .bg-proses { background: #4299e1; }
        .bg-selesai { background: #48bb78; }
        .bg-ditolak { background: #f56565; }
        .action-form { display: flex; flex-direction: column; gap: 5px; min-width: 150px; }
        select, input[type="file"] { font-size: 12px; padding: 5px; border: 1px solid #e2e8f0; border-radius: 4px; }
        .btn-update { background: #667eea; color: white; border: none; padding: 8px; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: 600; }
        .btn-view { color: #667eea; text-decoration: none; font-size: 13px; font-weight: 600; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Admin Dashboard</h2>
        <div>
            <a href="admin_kirim_surat.php" style="background: #3498db; margin-right: 10px;">+ Kirim Surat / SK</a>
            <a href="admin_arsip.php" style="background: #27ae60; margin-right: 10px;">Arsip Surat</a>
            <a href="logout.php" style="background: #e74c3c;">Sign Out</a>
        </div>
    </div>

    <div class="content">
        <div class="card">
            <h3 style="margin-bottom: 25px;">Manajemen Antrean Permohonan (Aktif)</h3>
    <table>
        <tr>
                    <th>User & Peran</th>
                    <th>Detail Layanan</th>
            <th>Catatan</th>
                    <th>Dokumen User</th>
                    <th>Status</th>
                    <th>Aksi</th>
        </tr>
        <?php while($row = mysqli_fetch_assoc($query)) { ?>
        <tr>
            <td><b><?= htmlspecialchars($row['nama_lengkap']); ?></b> (<?= ucfirst($row['role']); ?>)</td>
            <td><?= htmlspecialchars($row['jenis_surat']); ?></td>
            <td><?= htmlspecialchars($row['catatan_user']); ?></td>
            <td><?php if(!empty($row['berkas_pendukung'])) { ?>
                    <a href="uploads/<?= htmlspecialchars($row['berkas_pendukung']); ?>" target="_blank" class="btn-view">Buka Berkas</a>
                <?php } else { ?>
                    <small style="color: #a0aec0;">Tidak ada berkas</small>
                <?php } ?>
            </td>
            <td>
                <?php 
                    $class = '';
                    if($row['status'] == 'Pending') $class = 'bg-pending';
                    elseif($row['status'] == 'Diproses') $class = 'bg-proses';
                    elseif($row['status'] == 'Selesai') $class = 'bg-selesai';
                    elseif($row['status'] == 'Ditolak') $class = 'bg-ditolak';
                ?>
                <span class="badge <?= $class; ?>"><?= htmlspecialchars($row['status']); ?></span>
            </td>
            <td>
                <a href="admin_kirim_surat.php?id_permohonan=<?= $row['id']; ?>&user_id=<?= $row['user_id']; ?>&role=<?= $row['role']; ?>&jenis=<?= urlencode($row['jenis_surat']); ?>" 
                   class="btn-view" style="background: #667eea; color: white; padding: 5px 10px; border-radius: 4px;">Proses Surat</a>
            </td>
        </tr>
        <?php } ?>
    </table>
        </div>
    </div>
</body>
</html>