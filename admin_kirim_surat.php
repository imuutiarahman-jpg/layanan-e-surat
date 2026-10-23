<?php
include 'koneksi.php';
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'admin' || (($_SESSION['login_via'] ?? '') !== 'panel-admin')) {
    header("Location: panel-admin.php");
    exit;
}

// Proses Pengiriman Surat
if (isset($_POST['kirim_surat'])) {
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        die("Token keamanan tidak valid. Silakan muat ulang halaman.");
    }
    $user_id = (int)($_POST['recipient_id'] ?? 0);
    $jenis = trim($_POST['jenis_surat'] ?? '');
    $variables = isset($_POST['var']) && is_array($_POST['var']) ? $_POST['var'] : [];
    // Batasi panjang tiap variabel agar tidak membebani DOCX/DB
    foreach ($variables as $k => $v) {
        if (!preg_match('/^[A-Za-z0-9_\/]+$/', (string)$k)) { unset($variables[$k]); continue; }
        $variables[$k] = mb_substr(trim(strip_tags((string)$v)), 0, 5000);
    }
    $data_json = json_encode($variables, JSON_UNESCAPED_UNICODE);
    $id_permohonan = (int)($_POST['id_permohonan'] ?? 0);

    // Validasi penerima (harus user non-admin yang benar-benar ada)
    $recipient_ok = false;
    $stmt_u = mysqli_prepare($koneksi, "SELECT id FROM users WHERE id = ? AND role IN ('mahasiswa','dosen') LIMIT 1");
    if ($stmt_u) {
        mysqli_stmt_bind_param($stmt_u, "i", $user_id);
        mysqli_stmt_execute($stmt_u);
        $res_u = mysqli_stmt_get_result($stmt_u);
        $recipient_ok = ($res_u && mysqli_fetch_assoc($res_u));
    }

    // Validasi jenis surat terhadap master
    $jenis_ok = false;
    $stmt_j = mysqli_prepare($koneksi, "SELECT id FROM master_surat_jenis WHERE nama_surat = ? LIMIT 1");
    if ($stmt_j) {
        mysqli_stmt_bind_param($stmt_j, "s", $jenis);
        mysqli_stmt_execute($stmt_j);
        $res_j = mysqli_stmt_get_result($stmt_j);
        $jenis_ok = ($res_j && mysqli_fetch_assoc($res_j));
    }

    // Bila memproses permohonan, pastikan ID-nya benar-benar ada
    $permohonan_ok = true;
    if ($id_permohonan > 0) {
        $permohonan_ok = false;
        $stmt_pm = mysqli_prepare($koneksi, "SELECT id FROM permohonan_surat WHERE id = ? LIMIT 1");
        if ($stmt_pm) {
            mysqli_stmt_bind_param($stmt_pm, "i", $id_permohonan);
            mysqli_stmt_execute($stmt_pm);
            $res_pm = mysqli_stmt_get_result($stmt_pm);
            $permohonan_ok = ($res_pm && mysqli_fetch_assoc($res_pm));
        }
    }

    if ($user_id <= 0 || $jenis === '' || !$recipient_ok || !$jenis_ok || !$permohonan_ok) {
        die("Data penerima / jenis surat tidak valid.");
    }

    $surat_final = 'SYSTEM_GENERATED';

    if ($id_permohonan > 0) {
        // Jika memproses permohonan yang sudah ada (UPDATE)
        $stmt = mysqli_prepare($koneksi, "UPDATE permohonan_surat SET jenis_surat=?, status='Diproses', surat_jadi=?, data_surat=? WHERE id=?");
        if (!$stmt) {
            die("Gagal menyiapkan query. Silakan coba lagi.");
        }
        mysqli_stmt_bind_param($stmt, "sssi", $jenis, $surat_final, $data_json, $id_permohonan);
    } else {
        // Jika membuat surat baru tanpa permohonan sebelumnya (INSERT)
        $stmt = mysqli_prepare($koneksi, "INSERT INTO permohonan_surat (user_id, jenis_surat, status, surat_jadi, data_surat) VALUES (?, ?, 'Diproses', ?, ?)");
        if (!$stmt) {
            die("Gagal menyiapkan query. Silakan coba lagi.");
        }
        mysqli_stmt_bind_param($stmt, "isss", $user_id, $jenis, $surat_final, $data_json);
    }

    if (mysqli_stmt_execute($stmt)) {
        $id_final = $id_permohonan > 0 ? $id_permohonan : mysqli_insert_id($koneksi);
        header("Location: cetak_surat.php?id=" . (int)$id_final);
        exit;
    } else {
        die("Gagal menyimpan surat. Silakan coba lagi.");
    }
}

// Ambil semua user non-admin
$users_res = mysqli_query($koneksi, "SELECT id, nama_lengkap, role, nomor_induk, prodi FROM users WHERE role != 'admin' ORDER BY nama_lengkap ASC");
$all_users = [];
// Check if the query was successful
if ($users_res) {
    while($u = mysqli_fetch_assoc($users_res)) { // Line 31
        $all_users[] = $u;
    }
} else {
    // Handle the error gracefully (tanpa membocorkan detail DB)
    echo "<script>alert('Terjadi kesalahan saat mengambil data pengguna. Silakan coba lagi.'); window.location='dashboard_admin.php';</script>";
    exit;
}

// Ambil daftar jenis surat dari master untuk dropdown
$master_surat_query = mysqli_query($koneksi, "SELECT nama_surat, variabel_tambahan, prodi FROM master_surat_jenis ORDER BY nama_surat ASC");
$master_surat_list = [];
if ($master_surat_query) {
    while ($ms = mysqli_fetch_assoc($master_surat_query)) {
        $master_surat_list[] = $ms;
    }
}

// Ambil data permohonan jika ada ID untuk pre-fill field (Insightful addition)
$existing_data = [];
if (isset($_GET['id_permohonan'])) {
    $id_perm = (int)$_GET['id_permohonan'];
    if ($id_perm > 0) {
        $stmt_p = mysqli_prepare($koneksi, "SELECT data_surat FROM permohonan_surat WHERE id = ?");
        if ($stmt_p) {
            mysqli_stmt_bind_param($stmt_p, "i", $id_perm);
            mysqli_stmt_execute($stmt_p);
            $res_p = mysqli_stmt_get_result($stmt_p);
            if ($res_p && ($p_res = mysqli_fetch_assoc($res_p))) {
                if (!empty($p_res['data_surat'])) {
                    $decoded = json_decode($p_res['data_surat'], true);
                    if (is_array($decoded)) { $existing_data = $decoded; }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kirim Surat Baru | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <!-- Load jQuery dan Summernote untuk fitur Rich Text (RTF) -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f7f6; padding: 40px; }
        .container { max-width: 800px; margin: auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        h2 { color: #2c3e50; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; }
        select, input, textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        .dynamic-fields { background: #f9f9f9; padding: 20px; border-radius: 8px; border-left: 4px solid #3498db; margin-top: 20px; display: none; }
        button { background: #2ecc71; color: white; border: none; padding: 15px; width: 100%; border-radius: 6px; font-weight: 600; cursor: pointer; margin-top: 20px; }
        .back-link { display: inline-block; margin-bottom: 20px; text-decoration: none; color: #7f8c8d; font-size: 14px; }
    </style>
    <link rel="stylesheet" href="responsive.css">
</head>
<body class="form-page">
    <div class="container">
        <a href="dashboard_admin.php" class="back-link">← Kembali ke Dashboard</a>

        <h2>Buat & Kirim Surat </h2>
        
        <form method="POST" id="mainForm">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="id_permohonan" value="<?= isset($_GET['id_permohonan']) ? (int)$_GET['id_permohonan'] : ''; ?>">
            
            <!-- Langkah 1: Pilih Kategori -->
            <div class="form-group">
                <label>Pilih Kategori Penerima</label>
                <select id="kategori_peran" name="kategori" onchange="updateUserAndTemplate()" required>
                    <option value="">-- Pilih Kategori --</option>
                    <option value="mahasiswa" <?= (isset($_GET['role']) && $_GET['role'] == 'mahasiswa') ? 'selected' : ''; ?>>Mahasiswa (Kategori A)</option>
                    <option value="dosen" <?= (isset($_GET['role']) && $_GET['role'] == 'dosen') ? 'selected' : ''; ?>>Dosen (Kategori B)</option>
                </select>
            </div>

            <div class="form-group" id="prodi_filter_group" style="display:none;">
                <label>Filter Berdasarkan Program Studi / Kategori</label>
                <select id="filter_prodi" onchange="filterTemplates()">
                    <option value="">-- Semua Prodi --</option>
                    <option value="Umum">Umum</option>
                    <option value="Dosen">Dosen</option>
                    <option value="Sistem Informasi">Sistem Informasi</option>
                    <option value="Geografi">Geografi</option>
                    <option value="Ilmu Komputer">Ilmu Komputer</option>
                    <option value="Bisnis Digital">Bisnis Digital</option>
                    <option value="Teknik Sipil">Teknik Sipil</option>
                    <option value="Akuakultur">Akuakultur</option>
                    <option value="Agribisnis">Agribisnis</option>
                    <option value="Peternakan">Peternakan</option>
                </select>
            </div>

            <!-- Langkah 2: Pilih Nama (Muncul setelah kategori dipilih) -->
            <div class="form-group">
                <label>Pilih Nama Penerima</label>
                <select name="recipient_id" id="recipient" required onchange="handleRecipientChange()" disabled>
                    <option value="">-- Pilih Nama --</option>
                </select>
            </div>

            <!-- Langkah 3: Pilih Jenis Surat -->
            <div class="form-group">
                <label>Pilih Jenis Surat</label>
                <select name="jenis_surat" id="templateSelect" required onchange="showFields()" disabled>
                    <option value="">-- Pilih Penerima Terlebih Dahulu --</option>
                    <?php foreach($master_surat_list as $ms): ?>
                        <option value="<?= htmlspecialchars($ms['nama_surat'], ENT_QUOTES, 'UTF-8') ?>" data-prodi="<?= htmlspecialchars($ms['prodi'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-vars="<?= htmlspecialchars($ms['variabel_tambahan'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($ms['nama_surat'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="dynamicContainer" class="dynamic-fields">
                <h4 style="margin-top:0">Isi Variabel Surat</h4>
                <div id="fieldsBody"></div>
            </div>

            <button type="submit" name="kirim_surat">Generate & Review Surat</button>
        </form>
    </div>

    <script>
        const usersData = <?= json_encode($all_users, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const existingVars = <?= json_encode($existing_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        // Escape nilai sebelum dimasukkan ke HTML (cegah XSS dari nama user / data lama)
        function escAttr(s) {
            return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }
        
        // Jalankan otomatis jika datang dari tombol "Proses Surat"
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('id_permohonan')) {
                updateUserAndTemplate();
                const userId = urlParams.get('user_id');
                document.getElementById('recipient').value = userId;
                
                // Otomatis filter prodi berdasarkan data user yang sedang diproses
                const user = usersData.find(u => u.id == userId);
                if (user && user.prodi) {
                    document.getElementById('filter_prodi').value = user.prodi;
                    filterTemplates();
                }

                document.getElementById('templateSelect').value = urlParams.get('jenis');
                showFields();
            }
        };

        function updateUserAndTemplate() {
            const kategori = document.getElementById('kategori_peran').value;
            const recipientSelect = document.getElementById('recipient');
            const templateSelect = document.getElementById('templateSelect');
            const prodiGroup = document.getElementById('prodi_filter_group');
            const prodiFilter = document.getElementById('filter_prodi');

            prodiGroup.style.display = kategori ? 'block' : 'none';
            prodiFilter.value = kategori === 'dosen' ? 'Dosen' : '';

            // Reset & Filter Penerima (Mahasiswa/Dosen)
            recipientSelect.innerHTML = '<option value="">-- Pilih Nama --</option>';
            if (kategori) {
                recipientSelect.disabled = false;
                usersData.filter(u => u.role === kategori).forEach(u => {
                    let opt = document.createElement('option');
                    opt.value = u.id;
                    opt.text = u.nama_lengkap + (u.nomor_induk ? " (" + u.nomor_induk + ")" : "");
                    opt.dataset.nama = u.nama_lengkap;
                    opt.dataset.id = u.nomor_induk;
                    opt.dataset.prodi = u.prodi || "";
                    recipientSelect.add(opt);
                });
            } else {
                recipientSelect.disabled = true;
            }

            // Filter Template
            templateSelect.disabled = !kategori;
            filterTemplates();
        }

        function handleRecipientChange() {
            const recipientSelect = document.getElementById('recipient');
            const selectedOpt = recipientSelect.selectedOptions[0];
            const filterProdi = document.getElementById('filter_prodi');
            
            // Jika user memiliki data prodi (biasanya mahasiswa), otomatis arahkan filter prodi
            if (selectedOpt && selectedOpt.dataset.prodi) {
                filterProdi.value = selectedOpt.dataset.prodi;
                filterTemplates();
            }
            showFields();
        }

        function filterTemplates() {
            const prodi = document.getElementById('filter_prodi').value;
            const templateSelect = document.getElementById('templateSelect');
            const options = templateSelect.options;
            const kategori = document.getElementById('kategori_peran').value;

            templateSelect.value = "";
            
            for (let i = 1; i < options.length; i++) {
                const itemProdi = options[i].getAttribute('data-prodi');
                
                let isMatch = false;
                if (!prodi) {
                    // Jika filter prodi kosong, tampilkan sesuai kategori
                    isMatch = (kategori === 'dosen') ? (itemProdi === 'Dosen') : (itemProdi !== 'Dosen');
                } else {
                    isMatch = (itemProdi === prodi);
                }
                
                options[i].style.display = isMatch ? 'block' : 'none';
            }
            document.getElementById('dynamicContainer').style.display = "none";
        }

        function showFields() {
            const select = document.getElementById('templateSelect');
            const template = select.value;
            const container = document.getElementById('dynamicContainer');
            const body = document.getElementById('fieldsBody');
            const userOpt = document.getElementById('recipient').selectedOptions[0];
            const extraVars = select.options[select.selectedIndex].getAttribute('data-vars') || "";
            
            if(!template) { container.style.display = "none"; return; }
            container.style.display = "block";
            body.innerHTML = "";

            const today = new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
            const getVal = (key, def = '') => (existingVars && existingVars[key]) ? existingVars[key] : def;
            const recNama = userOpt ? (userOpt.dataset.nama || '') : '';
            const recId = userOpt ? (userOpt.dataset.id || '') : '';

            let fields = `
                <label>Nama Lengkap</label><input type="text" name="var[nama]" value="${escAttr(getVal('nama', recNama))}" required>
                <label>NIM / NIDN</label><input type="text" name="var[id_number]" value="${escAttr(getVal('nim', recId))}" required>
                <label>Nomor Surat SK</label><input type="text" name="var[nomor_surat]" value="${escAttr(getVal('nomor_surat'))}" placeholder="Contoh: 153/FSIK-UMGO/KEP/V/2026" required>
                <label>Tanggal Surat (Muncul di Footer)</label><input type="text" name="var[tanggal_surat]" value="${escAttr(getVal('tanggal_surat', today))}" required>
            `;

            if (extraVars.trim() !== "") {
                extraVars.split(',').forEach(v => {
                    let cleanVar = v.replace('${', '').replace('}', '').trim().replace(/[^A-Za-z0-9_\/]/g, '');
                    const skip = ['nama', 'nim', 'id_number', 'nama_lengkap', 'nomor_surat', 'tanggal_surat'];
                    if (cleanVar !== '' && !skip.includes(cleanVar)) {
                        let label = cleanVar.replace(/_/g, ' ').toUpperCase();
                        // Gunakan textarea jika variabel mengandung kata 'judul', 'catatan', atau 'data'
                        if (['judul', 'catatan', 'data', 'penguji'].some(k => cleanVar.includes(k))) {
                            fields += `<label>${escAttr(label)}</label><textarea name="var[${cleanVar}]" class="summernote" required>${escAttr(getVal(cleanVar))}</textarea>`;
                        } else {
                            fields += `<label>${escAttr(label)}</label><input type="text" name="var[${cleanVar}]" value="${escAttr(getVal(cleanVar))}" placeholder="Masukkan ${escAttr(label)}..." required>`;
                        }
                    }
                });
            }

            body.innerHTML = fields;

            // Inisialisasi Summernote pada semua textarea dengan class .summernote
            $('.summernote').summernote({
                placeholder: 'Gunakan toolbar di atas untuk memformat teks...',
                tabsize: 2,
                height: 150,
                toolbar: [
                    ['style', ['bold', 'italic', 'underline', 'clear']],
                    ['font', ['strikethrough']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['view', ['codeview']]
                ]
            });
        }
    </script>
</body>
</html>