<?php
include 'koneksi.php';
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'mahasiswa') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Proses Pengajuan Surat
if (isset($_POST['ajukan'])) {
    $jenis_surat = $_POST['jenis_surat'];
    $catatan = $_POST['catatan_user'];
    
    $berkas = $_FILES['berkas']['name'];
    $tmp_name = $_FILES['berkas']['tmp_name'];
    $berkas_baru = time() . "_" . $berkas; // Hindari nama file kembar
    
    if (!is_dir('uploads')) {
        mkdir('uploads', 0777, true);
    }
    
    if (move_uploaded_file($tmp_name, 'uploads/' . $berkas_baru)) {
        $stmt = mysqli_prepare($koneksi, "INSERT INTO permohonan_surat (user_id, jenis_surat, catatan_user, berkas_pendukung) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isss", $user_id, $jenis_surat, $catatan, $berkas_baru);
        mysqli_stmt_execute($stmt);
        
        echo "<script>alert('Permohonan berhasil dikirim!'); window.location='dashboard_mhs.php';</script>";
    }
}

// Ambil Notifikasi yang belum dibaca
$stmt_notif = mysqli_prepare($koneksi, "SELECT * FROM notifikasi WHERE user_id=? AND is_read=0 ORDER BY created_at DESC");
$notif_res = null;
if ($stmt_notif) {
    mysqli_stmt_bind_param($stmt_notif, "i", $user_id);
    mysqli_stmt_execute($stmt_notif);
    $notif_res = mysqli_stmt_get_result($stmt_notif);
}

$stmt_tampil = mysqli_prepare($koneksi, "SELECT * FROM permohonan_surat WHERE user_id=? ORDER BY id DESC");
mysqli_stmt_bind_param($stmt_tampil, "i", $user_id);
mysqli_stmt_execute($stmt_tampil);
$tampil = mysqli_stmt_get_result($stmt_tampil);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard Mahasiswa | E-Surat</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8f9fc; margin: 0; color: #333; }
        .navbar { background: #4e73df; padding: 15px 30px; color: white; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .navbar a { color: rgba(255,255,255,0.8); text-decoration: none; font-weight: 600; }
        .container { max-width: 1000px; margin: 30px auto; padding: 0 20px; }
        .card { background: white; border-radius: 10px; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); margin-bottom: 24px; padding: 25px; border: 1px solid #e3e6f0; }
        h2, h3 { color: #4e73df; margin-top: 0; }
        label { font-weight: 600; display: block; margin-bottom: 8px; font-size: 14px; }
        input, textarea, select { width: 100%; padding: 10px; border: 1px solid #d1d3e2; border-radius: 5px; margin-bottom: 15px; box-sizing: border-box; }
        button { background: #1cc88a; color: white; border: none; padding: 12px 20px; border-radius: 5px; cursor: pointer; font-weight: 600; width: 100%; }
        button:hover { background: #17a673; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #f8f9fc; color: #4e73df; text-align: left; padding: 12px; border-bottom: 2px solid #e3e6f0; }
        td { padding: 12px; border-bottom: 1px solid #e3e6f0; font-size: 14px; }
        .badge { padding: 4px 10px; border-radius: 50px; color: white; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .bg-pending { background: #f6c23e; color: #fff; }
        .bg-proses { background: #36b9cc; }
        .bg-selesai { background: #1cc88a; }
        .bg-ditolak { background: #e74a3b; }
        .btn-download { color: #4e73df; text-decoration: none; border: 1px solid #4e73df; padding: 4px 8px; border-radius: 4px; font-size: 12px; }
        .alert-notif { background: #d1e7dd; color: #0f5132; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid #198754; font-size: 14px; }
    </style>
</head>
<body>
    <div class="navbar">
        <span><b>E-Surat</b> | Portal Mahasiswa</span>
        <a href="logout.php">Keluar</a>
    </div>

    <div class="container">
        <h2>Halo, <?= htmlspecialchars($_SESSION['nama']); ?> 👋</h2>

        <?php if($notif_res) while($n = mysqli_fetch_assoc($notif_res)) { ?>
            <div class="alert-notif">
                <b>Pemberitahuan:</b> <?= htmlspecialchars($n['pesan']); ?>
            </div>
        <?php } ?>

        <div class="card">
            <h3>Formulir Pengajuan Baru</h3>
            <form method="POST" action="" enctype="multipart/form-data">
                <label>Jenis Surat</label>
                <select name="jenis_surat" required>
            <option value="Surat permintaan data awal penelitian">Surat permintaan data awal penelitian</option>
            <option value="Surat permintaan data hasil penelitian">Surat permintaan data hasil penelitian</option>
            <option value="Permohonan SK pembimbing">Permohonan SK pembimbing</option>
            <option value="Permohonan SK ujian proposal / hasil / skripsi">Permohonan SK ujian proposal / hasil / skripsi</option>
            <option value="Persetujuan waktu ujian">Persetujuan waktu ujian</option>
            <option value="Undangan ujian">Undangan ujian</option>
            <option value="Transkrip nilai">Transkrip nilai</option>
            <option value="Cek Turnitin">Cek Turnitin</option>
        </select>
                <label>Catatan Tambahan</label>
                <textarea name="catatan_user" placeholder="Jelaskan kebutuhan Anda secara singkat..."></textarea>
                <label>Unggah Berkas Pendukung (PDF/Image)</label>
                <input type="file" name="berkas" required>
                <button type="submit" name="ajukan">Kirim Permohonan</button>
    </form>
        </div>

        <div class="card">
            <h3>Riwayat Pengajuan</h3>
    <table>
        <tr>
                    <th>Layanan</th>
                    <th>Tanggal</th>
            <th>Status</th>
                    <th>Aksi</th>
        </tr>
        <?php $no=1; while($row = mysqli_fetch_assoc($tampil)) { ?>
        <tr>
            <td><?= htmlspecialchars($row['jenis_surat']); ?></td>
            <td><?= htmlspecialchars($row['tgl_pengajuan']); ?></td>
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
                <?php if($row['status'] == 'Selesai' && !empty($row['surat_jadi'])) { ?>
                    <?php if($row['surat_jadi'] == 'SYSTEM_GENERATED') { ?>
                        <a href="cetak_surat.php?id=<?= $row['id']; ?>" target="_blank" class="btn-download">Lihat / Cetak</a>
                    <?php } else { ?>
                        <a href="uploads/<?= htmlspecialchars($row['surat_jadi']); ?>" download class="btn-download">Unduh File</a>
                    <?php } ?>
                <?php } else { echo "Belum tersedia"; } ?>
            </td>
        </tr>
        <?php } ?>
    </table>
        </div>
    </div>
</body>
</html>