<?php
include 'koneksi.php';
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'mahasiswa') {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Ambil data profil mahasiswa untuk pre-fill formulir (prepared, cegah SQLi)
$u_data = ['nama_lengkap' => '', 'nomor_induk' => '', 'prodi' => ''];
$stmt_u = mysqli_prepare($koneksi, "SELECT nama_lengkap, nomor_induk, prodi FROM users WHERE id = ?");
if ($stmt_u) {
    mysqli_stmt_bind_param($stmt_u, "i", $user_id);
    mysqli_stmt_execute($stmt_u);
    $res_u = mysqli_stmt_get_result($stmt_u);
    if ($r = mysqli_fetch_assoc($res_u)) { $u_data = $r; }
}

// Proses Pengajuan Surat
if (isset($_POST['ajukan'])) {
    $jenis_surat = $_POST['jenis_surat'];
    $catatan = $_POST['catatan_user'];
    $data_surat = isset($_POST['var']) ? json_encode($_POST['var']) : null;

    $berkas_baru = null;
    $upload_ok = true;

    if (!empty($_FILES['berkas']['name'])) {
        $berkas = $_FILES['berkas']['name'];
        $tmp_name = $_FILES['berkas']['tmp_name'];
        $ekstensi = strtolower(pathinfo($berkas, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];

        if ($_FILES['berkas']['error'] !== UPLOAD_ERR_OK) {
            echo "<script>alert('Gagal mengunggah berkas (kode error upload).');</script>";
            $upload_ok = false;
        } elseif (!in_array($ekstensi, $allowed)) {
            echo "<script>alert('Format file tidak diizinkan! (Hanya PDF/JPG/PNG)');</script>";
            $upload_ok = false;
        } elseif ($_FILES['berkas']['size'] > 5 * 1024 * 1024) {
            echo "<script>alert('Ukuran file maksimal 5MB.');</script>";
            $upload_ok = false;
        } else {
            // Validasi MIME asli agar ekstensi tidak bisa dipalsukan
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $tmp_name) : '';
            if ($finfo) finfo_close($finfo);
            $allowed_mime = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
            if (!in_array($mime, $allowed_mime, true)) {
                echo "<script>alert('Isi file tidak sesuai format yang diizinkan.');</script>";
                $upload_ok = false;
            } else {
            $berkas_baru = time() . "_" . bin2hex(random_bytes(4)) . "." . $ekstensi;
            if (!is_dir('uploads')) mkdir('uploads', 0755, true);
            if (!move_uploaded_file($tmp_name, 'uploads/' . $berkas_baru)) {
                echo "<script>alert('Gagal mengunggah berkas.');</script>";
                $upload_ok = false;
            }
            } // tutup else MIME valid
        } // tutup else ekstensi/size valid
    } // tutup if ada berkas

    if ($upload_ok) {
        $stmt = mysqli_prepare($koneksi, "INSERT INTO permohonan_surat (user_id, jenis_surat, catatan_user, berkas_pendukung, data_surat) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "issss", $user_id, $jenis_surat, $catatan, $berkas_baru, $data_surat);
            if (mysqli_stmt_execute($stmt)) {
                echo "<script>alert('Permohonan berhasil dikirim!'); window.location='dashboard_mhs.php';</script>";
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
    <title>Dashboard Mahasiswa | E-Surat</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4e73df;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --dark: #5a5c69;
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
        input:focus, select:focus, textarea:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(78, 115, 223, 0.1); }
        
        button { background: var(--success); color: white; border: none; padding: 14px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; width: 100%; font-size: 15px; transition: 0.3s; }
        button:hover { background: #17a673; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(28, 200, 138, 0.2); }
        
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
        
        .dynamic-fields { background: #f0f2f5; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #4e73df; display: none; }
    </style>
</head>
<body>
    <div class="navbar">
        <a href="dashboard_mhs.php" class="brand">
            <img src="logo/logo.png" alt="Logo" style="height: 85px; margin-right: 10px; vertical-align: middle;">
            E-Surat <span style="font-weight:300; color:#858796">Portal Mahasiswa</span>
        </a>
        <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
    </div>

    <div class="container">
        <div class="welcome-section">
            <h2>Halo, <?= htmlspecialchars($_SESSION['nama']); ?> 👋</h2>
            <p>Selamat datang di dashboard layanan persuratan elektronik.</p>
        </div>

        <?php 
        if ($notif_res && mysqli_num_rows($notif_res) > 0) {
            $msgs = [];
            $ids_to_update = [];
            while ($n = mysqli_fetch_assoc($notif_res)) {
                $msgs[] = "- " . $n['pesan'];
                $ids_to_update[] = (int)$n['id'];
            }
            if(!empty($ids_to_update)) {
                $id_list = implode(',', $ids_to_update);
                // IDs sudah di-cast ke int, aman dari SQLi
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
                    foreach ($map as $k => $v) { $s_total += $v; }
                    $s_pending = $map['Pending'] ?? 0;
                    $s_selesai = $map['Selesai'] ?? 0;
                }
            ?>
            <div class="stat-card">
                <div>
                    <div class="label">Total Pengajuan</div>
                    <div class="value"><?= $s_total; ?></div>
                </div>
                <i class="fa-solid fa-clipboard-list"></i>
            </div>
            <div class="stat-card" style="border-left-color: var(--warning);">
                <div>
                    <div class="label" style="color: var(--warning);">Menunggu</div>
                    <div class="value"><?= $s_pending; ?></div>
                </div>
                <i class="fa-solid fa-clock"></i>
            </div>
            <div class="stat-card" style="border-left-color: var(--success);">
                <div>
                    <div class="label" style="color: var(--success);">Selesai</div>
                    <div class="value"><?= $s_selesai; ?></div>
                </div>
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>

        <div class="main-grid">
            <div class="card">
                <h3><i class="fa-solid fa-file-circle-plus" style="color:var(--primary)"></i> Pengajuan Baru</h3>
                <form method="POST" action="" enctype="multipart/form-data">
                    <label>Pilih Program Studi</label>
                    <select id="filter_prodi" onchange="filterLetters()" required>
                        <option value="Umum">Umum</option>
                        <option value="Sistem Informasi">Sistem Informasi</option>
                        <option value="Geografi">Geografi</option>
                        <option value="Ilmu Komputer">Ilmu Komputer</option>
                        <option value="Bisnis Digital">Bisnis Digital</option>
                        <option value="Teknik Sipil">Teknik Sipil</option>
                        <option value="Akuakultur">Akuakultur</option>
                        <option value="Agribisnis">Agribisnis</option>
                        <option value="Peternakan">Peternakan</option>
                    </select>

                    <label>Jenis Surat</label>
                    <select name="jenis_surat" id="jenis_surat" required onchange="showFormFields()" disabled>
                        <option value="" disabled selected>-- Pilih Jenis Surat --</option>
                        <?php foreach($surat_options as $opt): ?>
                            <option value="<?= htmlspecialchars($opt['nama_surat']) ?>" 
                                    data-prodi="<?= htmlspecialchars($opt['prodi']) ?>" 
                                    data-vars="<?= htmlspecialchars($opt['variabel_tambahan']) ?>"
                                    data-require-berkas="<?= $opt['require_berkas'] ?>"
                                    data-info-berkas="<?= htmlspecialchars($opt['info_berkas']) ?>"><?= htmlspecialchars($opt['nama_surat']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <div id="dynamicContainer" class="dynamic-fields"></div>

                    <label>Catatan Tambahan</label>
                    <textarea name="catatan_user" rows="3" placeholder="Jelaskan kebutuhan Anda secara singkat..."></textarea>
                    <button type="submit" name="ajukan">Kirim Permohonan</button>
                </form>
            </div>

            <div class="card">
                <h3><i class="fa-solid fa-clock-rotate-left" style="color:var(--primary)"></i> Riwayat Pengajuan</h3>
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
                                    <a href="cetak_surat.php?id=<?= $row['id']; ?>" target="_blank" class="btn-download"><i class="fa-solid fa-print"></i> Cetak</a>
                                <?php } else { echo "<span style='color:#ccc; font-size:12px;'>Belum tersedia</span>"; } ?>
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

        let hasVisible = false;
        for (let i = 1; i < options.length; i++) {
            const optionProdi = options[i].getAttribute('data-prodi');
            if (optionProdi === prodi) {
                options[i].style.display = 'block';
                hasVisible = true;
            } else {
                options[i].style.display = 'none';
            }
        }
        
        if(!hasVisible && prodi !== "") alert("Tidak ada jenis surat untuk prodi ini.");
    }

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
            <label>NIM</label><input type="text" name="var[nim]" value="<?= htmlspecialchars($u_data['nomor_induk']) ?>" required>
        `;

        if (extraVars.trim() !== "") {
            extraVars.split(',').forEach(v => {
                let cleanVar = v.replace('${', '').replace('}', '').trim();
                if (!['nama', 'nim', 'nama_lengkap'].includes(cleanVar)) {
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

        container.innerHTML = "<h4 style='margin-top:0; color:#4e73df;'>Lengkapi Data Surat</h4>" + fields;
        container.style.display = "block";
    }

    window.onload = function() {
        const userProdi = "<?= $u_data['prodi'] ?>";
        if(userProdi) {
            document.getElementById('filter_prodi').value = userProdi;
            filterLetters();
        }
    };
    </script>
</body>
</html>