<?php
include 'koneksi.php';
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'dosen') {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Ambil data profil dosen untuk pre-fill formulir (prepared)
$u_data = ['nama_lengkap' => '', 'nomor_induk' => '', 'prodi' => ''];
$stmt_u = mysqli_prepare($koneksi, "SELECT nama_lengkap, nomor_induk, prodi FROM users WHERE id = ?");
if ($stmt_u) {
    mysqli_stmt_bind_param($stmt_u, "i", $user_id);
    mysqli_stmt_execute($stmt_u);
    $res_u = mysqli_stmt_get_result($stmt_u);
    if ($r = mysqli_fetch_assoc($res_u)) { $u_data = $r; }
}

if (isset($_POST['ajukan'])) {
    $jenis_surat = $_POST['jenis_surat'];
    $catatan = $_POST['catatan_user'];
    $data_surat = isset($_POST['var']) ? json_encode($_POST['var']) : null;

    $berkas_baru = null;
    $upload_ok = true;

    if (isset($_FILES['berkas']) && !empty($_FILES['berkas']['name'])) {
        $berkas = $_FILES['berkas']['name'];
        $tmp_name = $_FILES['berkas']['tmp_name'];
        $ekstensi = strtolower(pathinfo($berkas, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];

        if ($_FILES['berkas']['error'] !== UPLOAD_ERR_OK) {
            echo "<script>alert('Gagal mengunggah berkas.');</script>";
            $upload_ok = false;
        } elseif (!in_array($ekstensi, $allowed)) {
            echo "<script>alert('Format file tidak diizinkan! (Hanya PDF/JPG/PNG)');</script>";
            $upload_ok = false;
        } elseif ($_FILES['berkas']['size'] > 5 * 1024 * 1024) {
            echo "<script>alert('Ukuran file maksimal 5MB.');</script>";
            $upload_ok = false;
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $tmp_name) : '';
            if ($finfo) finfo_close($finfo);
            if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'], true)) {
                echo "<script>alert('Isi file tidak sesuai format yang diizinkan.');</script>";
                $upload_ok = false;
            } else {
            $berkas_baru = time() . "_" . bin2hex(random_bytes(4)) . "." . $ekstensi;
            if (!is_dir('uploads')) mkdir('uploads', 0755, true);
            if (!move_uploaded_file($tmp_name, 'uploads/' . $berkas_baru)) {
                echo "<script>alert('Gagal mengunggah berkas.');</script>";
                $upload_ok = false;
            }
            }
        }
    }
    
    if ($upload_ok) {
        $stmt = mysqli_prepare($koneksi, "INSERT INTO permohonan_surat (user_id, jenis_surat, catatan_user, berkas_pendukung, data_surat) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "issss", $user_id, $jenis_surat, $catatan, $berkas_baru, $data_surat);
            if (mysqli_stmt_execute($stmt)) {
                echo "<script>alert('Permohonan berhasil dikirim!'); window.location='dashboard_dosen.php';</script>";
                exit;
            } else {
                echo "<script>alert('Gagal mengirim permohonan. Silakan coba lagi.');</script>";
            }
        } else {
            echo "<script>alert('Gagal menyiapkan sistem. Silakan coba lagi.');</script>";
        }
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
$tampil = null;
if ($stmt_tampil) {
    mysqli_stmt_bind_param($stmt_tampil, "i", $user_id);
    mysqli_stmt_execute($stmt_tampil);
    $tampil = mysqli_stmt_get_result($stmt_tampil);
}

// Ambil daftar jenis surat dari master untuk dropdown
$master_surat_query = mysqli_query($koneksi, "SELECT nama_surat, variabel_tambahan, prodi, require_berkas, info_berkas FROM master_surat_jenis ORDER BY nama_surat ASC");
$surat_options = [];
if ($master_surat_query) {
    while($opt = mysqli_fetch_assoc($master_surat_query)) { $surat_options[] = $opt; }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Dosen | E-Surat</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6610f2;
            --success: #1cc88a;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --light: #f8f9fc;
        }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; margin: 0; color: #444; }
        .navbar { background: white; padding: 15px 40px; color: var(--primary); display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 1000; }
        .navbar .brand { font-size: 20px; font-weight: 700; color: var(--primary); text-decoration: none; }
        .navbar .logout-btn { color: var(--danger); text-decoration: none; font-weight: 600; font-size: 14px; border: 1px solid var(--danger); padding: 6px 15px; border-radius: 6px; transition: 0.3s; }
        .navbar .logout-btn:hover { background: var(--danger); color: white; }
        
        .container { max-width: 1100px; margin: 30px auto; padding: 0 20px; }
        .welcome-section { margin-bottom: 30px; }
        .welcome-section h2 { margin: 0; font-weight: 700; color: #2e3b4e; }
        .welcome-section p { color: #858796; margin: 5px 0 0; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; border-left: 4px solid var(--primary); box-shadow: 0 4px 6px rgba(0,0,0,0.04); display: flex; align-items: center; justify-content: space-between; }
        .stat-card .label { font-size: 12px; font-weight: 800; color: var(--primary); text-transform: uppercase; margin-bottom: 5px; }
        .stat-card .value { font-size: 22px; font-weight: 700; color: #5a5c69; }
        .stat-card i { font-size: 28px; color: #dddfeb; }

        .main-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 25px; align-items: start; }
        @media (max-width: 850px) { .main-grid { grid-template-columns: 1fr; } }

        .card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.04); margin-bottom: 24px; padding: 25px; border: 1px solid #eef0f7; }
        .card h3 { color: #2e3b4e; margin-top: 0; font-size: 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        
        label { font-weight: 600; display: block; margin-bottom: 8px; font-size: 14px; }
        input, textarea, select { width: 100%; padding: 12px; border: 1px solid #d1d3e2; border-radius: 8px; margin-bottom: 18px; box-sizing: border-box; font-family: inherit; font-size: 14px; transition: 0.2s; }
        input:focus, select:focus, textarea:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(102, 16, 242, 0.1); }
        
        button { background: var(--primary); color: white; border: none; padding: 14px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; width: 100%; font-size: 15px; transition: 0.3s; }
        button:hover { background: #520dc2; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(102, 16, 242, 0.2); }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { text-align: left; padding: 15px 12px; border-bottom: 2px solid #eef0f7; color: #858796; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 15px 12px; border-bottom: 1px solid #eef0f7; font-size: 14px; color: #5a5c69; }
        
        .badge { padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .bg-pending { background: #fff4e5; color: #b7791f; }
        .bg-proses { background: #e0f2fe; color: #0369a1; }
        .bg-selesai { background: #dcfce7; color: #166534; }
        .bg-ditolak { background: #fee2e2; color: #991b1b; }
        
        .btn-download { color: var(--primary); text-decoration: none; border: 1.5px solid var(--primary); padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; transition: 0.3s; }
        .btn-download:hover { background: var(--primary); color: white; }
        
        .dynamic-fields { background: #f0f2f5; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #6610f2; display: none; }
    </style>
    <link rel="stylesheet" href="responsive.css">
</head>
<body class="dash-page">
    <div class="navbar">
        <a href="dashboard_dosen.php" class="brand">
            <img src="logo/logo.png" alt="Logo" style="height: 85px; margin-right: 10px; vertical-align: middle;">
            E-Surat <span style="font-weight:300; color:#858796">Portal Dosen</span>
        </a>
        <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
    </div>

    <div class="container">
        <div class="welcome-section">
            <h2>Halo, Dr/Bpk/Ibu <?= htmlspecialchars($_SESSION['nama']); ?> 👋</h2>
            <p>Selamat datang di dashboard administrasi dosen.</p>
        </div>

        <?php 
        if ($notif_res && mysqli_num_rows($notif_res) > 0) {
            $msgs = [];
            $ids_to_update = [];
            while ($n = mysqli_fetch_assoc($notif_res)) {
                $msgs[] = "- " . $n['pesan'];
                $ids_to_update[] = (int)$n['id'];
            }
            if (!empty($ids_to_update)) {
                $id_list = implode(',', $ids_to_update);
                mysqli_query($koneksi, "UPDATE notifikasi SET is_read = 1 WHERE user_id = $user_id AND id IN ($id_list)");
            }
            $msg_text = "PEMBERITAHUAN BARU:\n\n" . implode("\n", $msgs);
            echo "<script>alert(" . json_encode($msg_text) . ");</script>";
        }
        ?>
        
        <div class="stats-grid">
            <?php
                $s_total = $s_pending = $s_selesai = 0;
                $st = mysqli_prepare($koneksi, "SELECT status, COUNT(*) c FROM permohonan_surat WHERE user_id=? GROUP BY status");
                if ($st) {
                    mysqli_stmt_bind_param($st, "i", $user_id);
                    mysqli_stmt_execute($st);
                    $rs = mysqli_stmt_get_result($st);
                    $map = [];
                    while ($r = mysqli_fetch_assoc($rs)) { $map[$r['status']] = (int)$r['c']; }
                    foreach ($map as $v) { $s_total += $v; }
                    $s_pending = $map['Pending'] ?? 0;
                    $s_selesai = $map['Selesai'] ?? 0;
                }
            ?>
            <div class="stat-card">
                <div>
                    <div class="label">Total Pengajuan</div>
                    <div class="value"><?= $s_total; ?></div>
                </div>
                <i class="fa-solid fa-folder-open"></i>
            </div>
            <div class="stat-card" style="border-left-color: var(--warning);">
                <div>
                    <div class="label" style="color: var(--warning);">Menunggu Verifikasi</div>
                    <div class="value"><?= $s_pending; ?></div>
                </div>
                <i class="fa-solid fa-hourglass-half"></i>
            </div>
            <div class="stat-card" style="border-left-color: var(--success);">
                <div>
                    <div class="label" style="color: var(--success);">Selesai</div>
                    <div class="value"><?= $s_selesai; ?></div>
                </div>
                <i class="fa-solid fa-file-signature"></i>
            </div>
        </div>

        <div class="main-grid">
            <div class="card">
                <h3><i class="fa-solid fa-file-pen" style="color:var(--primary)"></i> Formulir Baru</h3>
                <form method="POST" action="" enctype="multipart/form-data">
                    <label>Filter Kategori</label>
                    <select id="filter_prodi" onchange="filterLetters()" required>
                        <option value="Dosen">Khusus Dosen</option>
                        <option value="Umum">Umum / Lainnya</option>
                    </select>

                    <label>Jenis Administrasi</label>
                    <select name="jenis_surat" required id="jenis_surat" onchange="showFormFields()" disabled>
                        <option value="" disabled selected>-- Pilih Jenis Surat --</option>
                        <?php foreach($surat_options as $opt): ?>
                            <option value="<?= htmlspecialchars($opt['nama_surat']) ?>" 
                                    data-prodi="<?= htmlspecialchars($opt['prodi']) ?>" 
                                    data-vars="<?= htmlspecialchars($opt['variabel_tambahan']) ?>"
                                    data-require-berkas="<?= $opt['require_berkas'] ?>"
                                    data-info-berkas="<?= htmlspecialchars($opt['info_berkas']) ?>">
                                <?= htmlspecialchars($opt['nama_surat']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div id="dynamicContainer" class="dynamic-fields"></div>

                    <label>Deskripsi Kebutuhan</label>
                    <textarea name="catatan_user" rows="4" placeholder="Jelaskan detail permohonan Anda..."></textarea>
                    <button type="submit" name="ajukan">Kirim Permohonan</button>
                </form>
            </div>

            <div class="card">
                <h3><i class="fa-solid fa-list-check" style="color:var(--primary)"></i> Status Pengajuan</h3>
                <div style="overflow-x: auto;">
                    <table>
                        <tr>
                            <th>Layanan</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                        <?php if(mysqli_num_rows($tampil) == 0): ?>
                            <tr><td colspan="4" style="text-align:center; padding:30px; color:#aaa;">Belum ada riwayat pengajuan.</td></tr>
                        <?php endif; ?>
                        <?php while($row = mysqli_fetch_assoc($tampil)) { ?>
                        <tr>
                            <td><span style="font-weight:600; color:#2e3b4e;"><?= htmlspecialchars($row['jenis_surat']); ?></span></td>
                            <td><?= date('d/m/Y', strtotime($row['tgl_pengajuan'])); ?></td>
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
                                    <a href="cetak_surat.php?id=<?= $row['id']; ?>" target="_blank" class="btn-download"><i class="fa-solid fa-download"></i> Unduh</a>
                                <?php } else { echo "<small style='color:#ccc'>Verifikasi</small>"; } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    function filterLetters() {
        const prodi = document.getElementById('filter_prodi').value;
        const jenisSelect = document.getElementById('jenis_surat');
        const options = jenisSelect.options;
        const container = document.getElementById('dynamicContainer');

        jenisSelect.disabled = (prodi === "");
        jenisSelect.value = "";
        container.style.display = "none";
        container.innerHTML = "";

        for (let i = 1; i < options.length; i++) {
            const optionProdi = options[i].getAttribute('data-prodi');
            if (optionProdi === prodi) {
                options[i].style.display = 'block';
            } else {
                options[i].style.display = 'none';
            }
        }
    }
    window.onload = filterLetters;

    function showFormFields() {
        const select = document.getElementById('jenis_surat');
        const template = select.value;
        const container = document.getElementById('dynamicContainer');
        const selectedOption = select.options[select.selectedIndex];
        const extraVars = selectedOption.getAttribute('data-vars') || "";
        
        container.innerHTML = "";
        container.style.display = "none";
        if(template === "") return;

        let fields = `
            <label>Nama Lengkap</label><input type="text" name="var[nama]" value="<?= htmlspecialchars($u_data['nama_lengkap']) ?>" required>
            <label>NIP / NIDN</label><input type="text" name="var[id_number]" value="<?= htmlspecialchars($u_data['nomor_induk']) ?>" required>
        `;

        if (extraVars.trim() !== "") {
            extraVars.split(',').forEach(v => {
                let cleanVar = v.replace('${', '').replace('}', '').trim();
                if (!['nama', 'nim', 'id_number', 'nama_lengkap'].includes(cleanVar)) {
                    let label = cleanVar.replace(/_/g, ' ').toUpperCase();
                    fields += `<label>${label}</label><input type="text" name="var[${cleanVar}]" placeholder="Masukkan ${label}..." required>`;
                }
            });
        }

        const requireBerkas = selectedOption.getAttribute('data-require-berkas') === '1';
        if (requireBerkas) {
            const labelText = selectedOption.getAttribute('data-info-berkas') || 'Unggah Berkas Pendukung (PDF/Image)';
            fields += `<label>${labelText}</label><input type="file" name="berkas" style="border:none; padding:10px 0;" required>`;
        }

        container.innerHTML = "<h4 style='margin-top:0; color:#6610f2;'>Lengkapi Data Surat</h4>" + fields;
        container.style.display = "block";
    }
    </script>
</body>
</html>