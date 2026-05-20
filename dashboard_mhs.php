<?php
include 'koneksi.php';
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'mahasiswa') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Ambil data profil mahasiswa untuk pre-fill formulir
$u_query = mysqli_query($koneksi, "SELECT nama_lengkap, nomor_induk FROM users WHERE id = '$user_id'");
$u_data = mysqli_fetch_assoc($u_query);

// Proses Pengajuan Surat
if (isset($_POST['ajukan'])) {
    $jenis_surat = $_POST['jenis_surat'];
    $catatan = $_POST['catatan_user'];
    $data_surat = isset($_POST['var']) ? json_encode($_POST['var']) : null;
    
    $berkas = $_FILES['berkas']['name'];
    $tmp_name = $_FILES['berkas']['tmp_name'];
    $berkas_baru = time() . "_" . $berkas; // Hindari nama file kembar
    
    if (!is_dir('uploads')) {
        mkdir('uploads', 0777, true);
    }
    
    if (move_uploaded_file($tmp_name, 'uploads/' . $berkas_baru)) {
        $stmt = mysqli_prepare($koneksi, "INSERT INTO permohonan_surat (user_id, jenis_surat, catatan_user, berkas_pendukung, data_surat) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "issss", $user_id, $jenis_surat, $catatan, $berkas_baru, $data_surat);
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
        .dynamic-fields { background: #f0f2f5; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #4e73df; display: none; }
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

        <?php 
        if ($notif_res && mysqli_num_rows($notif_res) > 0) {
            $msgs = [];
            while ($n = mysqli_fetch_assoc($notif_res)) {
                $msgs[] = "- " . $n['pesan'];
                $nid = $n['id'];
                // Tandai sudah dibaca agar tidak muncul lagi saat refresh
                mysqli_query($koneksi, "UPDATE notifikasi SET is_read = 1 WHERE id = $nid");
            }
            $msg_text = "PEMBERITAHUAN BARU:\n\n" . implode("\n", $msgs);
            echo "<script>alert(" . json_encode($msg_text) . ");</script>";
        }
        ?>

        <div class="card">
            <h3>Formulir Pengajuan Baru</h3>
            <form method="POST" action="" enctype="multipart/form-data">
                <label>Jenis Surat</label>
                <select name="jenis_surat" id="jenis_surat" required onchange="showFormFields()">
                    <option value="" disabled selected>-- Pilih Jenis Surat --</option>
                    <option value="Surat permintaan data awal penelitian">Surat permintaan data awal penelitian</option>
                    <option value="Surat permintaan data hasil penelitian">Surat permintaan data hasil penelitian</option>
                    <option value="Permohonan SK pembimbing">Permohonan SK pembimbing</option>
                    <option value="Permohonan SK ujian proposal">Permohonan SK Ujian Seminar Proposal</option>
                    <option value="Permohonan SK ujian seminar hasil">Permohonan SK Ujian Seminar Hasil</option>
                    <option value="Permohonan SK ujian skripsi">Permohonan SK Ujian Skripsi</option>
                    <option value="Persetujuan waktu ujian">Persetujuan waktu ujian</option>
                    <option value="Undangan ujian">Undangan ujian</option>
                    <option value="Transkrip nilai">Transkrip nilai</option>
                    <option value="Cek Turnitin">Cek Turnitin</option>
                </select>

                <div id="dynamicContainer" class="dynamic-fields"></div>

                <label>Catatan Tambahan</label>
                <textarea name="catatan_user" rows="3" placeholder="Jelaskan kebutuhan Anda secara singkat..."></textarea>
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
                    <a href="cetak_surat.php?id=<?= $row['id']; ?>" target="_blank" class="btn-download">Lihat / Cetak</a>
                <?php } else { echo "Belum tersedia"; } ?>
            </td>
        </tr>
        <?php } ?>
    </table>
        </div>
    </div>

    <script>
    function showFormFields() {
        const template = document.getElementById('jenis_surat').value;
        const container = document.getElementById('dynamicContainer');
        
        container.innerHTML = "";
        container.style.display = "none";

        // Menambahkan Nama dan NIM sebagai field utama yang muncul untuk semua jenis surat
        let fields = `
            <label>Nama Lengkap</label><input type="text" name="var[nama]" value="<?= htmlspecialchars($u_data['nama_lengkap']) ?>" required>
            <label>NIM</label><input type="text" name="var[nim]" value="<?= htmlspecialchars($u_data['nomor_induk']) ?>" required>
        `;

        if(template === "Surat permintaan data awal penelitian") {
            fields += `
                <label>Lokasi Penelitian</label><input type="text" name="var[lokasi]" placeholder="Contoh: Kantor Kelurahan..." required>
                <label>Data yang Diminta</label><textarea name="var[data]" placeholder="Sebutkan data apa saja yang dibutuhkan..." required></textarea>`;
        } else if(template === "Surat permintaan data hasil penelitian") {
            fields += `
                <label>Lokasi Penelitian</label><input type="text" name="var[lokasi]" required>
                <label>Instansi Tujuan</label><input type="text" name="var[instansi]" placeholder="Contoh: PT. Maju Bersama" required>`;
        } else if(template === "Permohonan SK pembimbing") {
            fields += `
                <label>Judul Skripsi (Tentatif)</label><textarea name="var[judul]" required></textarea>
                <label>Calon Pembimbing 1</label><input type="text" name="var[p1]" required>
                <label>Calon Pembimbing 2</label><input type="text" name="var[p2]" required>`;
        } else if(template === "Permohonan SK ujian proposal") {
            fields += `
                <label>Judul Proposal</label><textarea name="var[judul]" required></textarea>
                <label>Rencana Waktu Ujian</label><input type="text" name="var[waktu]" placeholder="Contoh: Senin, 20 Mei 2024 / 09:00 WIB" required>`;
        } else if(template === "Permohonan SK ujian seminar hasil") {
            fields += `
                <label>Judul Skripsi</label><textarea name="var[judul]" required></textarea>
                <label>Waktu Seminar</label><input type="text" name="var[waktu]" required>`;
        } else if(template === "Permohonan SK ujian skripsi") {
            fields += `
                <label>Judul Skripsi</label><textarea name="var[judul]" required></textarea>
                <label>Tanggal Kelulusan Seminar Hasil</label><input type="date" name="var[tgl_lulus]" required>`;
        } else if(template === "Persetujuan waktu ujian") {
            fields += `
                <label>Hari / Tanggal</label><input type="text" name="var[tanggal]" required>
                <label>Jam</label><input type="text" name="var[jam]" required>
                <label>Ruangan / Platform (Jika Online)</label><input type="text" name="var[ruangan]" required>`;
        } else if(template === "Undangan ujian") {
            fields += `<label>Judul Skripsi / Tugas Akhir</label><textarea name="var[judul]" required></textarea>`;
        } else if(template === "Transkrip nilai") {
            fields += `<label>Semester Terakhir</label><input type="number" name="var[semester]" required>`;
        } else if(template === "Cek Turnitin") {
            fields += `<label>Judul Dokumen</label><textarea name="var[judul]" required></textarea>`;
        }

        if(template !== "") {
            container.innerHTML = "<h4 style='margin-top:0; color:#4e73df;'>Lengkapi Data Surat</h4>" + fields;
            container.style.display = "block";
        }
    }
    </script>
</body>
</html>