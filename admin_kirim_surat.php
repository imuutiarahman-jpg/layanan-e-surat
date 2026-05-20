<?php
include 'koneksi.php';
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// Proses Pengiriman Surat
if (isset($_POST['kirim_surat'])) {
    $user_id = $_POST['recipient_id'];
    $jenis = $_POST['jenis_surat'];
    $variables = $_POST['var']; // Ambil semua input variabel
    $data_json = json_encode($variables);
    $id_permohonan = $_POST['id_permohonan'];

    $surat_final = 'SYSTEM_GENERATED';

    if (!empty($id_permohonan)) {
        // Jika memproses permohonan yang sudah ada (UPDATE)
        $stmt = mysqli_prepare($koneksi, "UPDATE permohonan_surat SET jenis_surat=?, status='Selesai', surat_jadi=?, data_surat=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, "sssi", $jenis, $surat_final, $data_json, $id_permohonan);
    } else {
        // Jika membuat surat baru tanpa permohonan sebelumnya (INSERT)
        $stmt = mysqli_prepare($koneksi, "INSERT INTO permohonan_surat (user_id, jenis_surat, status, surat_jadi, data_surat) VALUES (?, ?, 'Selesai', ?, ?)");
        mysqli_stmt_bind_param($stmt, "isss", $user_id, $jenis, $surat_final, $data_json);
    }
    
    if ($stmt) {
        if (mysqli_stmt_execute($stmt)) {
            $pesan_notif = "Surat/SK baru '$jenis' telah tersedia untuk Anda.";
            $stmt_n = mysqli_prepare($koneksi, "INSERT INTO notifikasi (user_id, pesan) VALUES (?, ?)");
            if ($stmt_n) {
                mysqli_stmt_bind_param($stmt_n, "is", $user_id, $pesan_notif);
                mysqli_stmt_execute($stmt_n);
            }
            
            echo "<script>alert('Surat berhasil dikirim ke User!'); window.location='dashboard_admin.php';</script>";
        }
    } else {
        die("Gagal menyiapkan query (mysqli_prepare). Pastikan kolom 'data_surat' sudah ada di tabel 'permohonan_surat'. Error: " . mysqli_error($koneksi));
    }
}

// Ambil semua user non-admin
$users_res = mysqli_query($koneksi, "SELECT id, nama_lengkap, role, nomor_induk FROM users WHERE role != 'admin' ORDER BY nama_lengkap ASC"); // Line 29
$all_users = [];
// Check if the query was successful
if ($users_res) {
    while($u = mysqli_fetch_assoc($users_res)) { // Line 31
        $all_users[] = $u;
    }
} else {
    // Handle the error gracefully
    echo "<script>alert('Terjadi kesalahan saat mengambil data pengguna dari database. Pastikan kolom nomor_induk ada di tabel users. Error: " . mysqli_error($koneksi) . "'); window.location='dashboard_admin.php';</script>";
    exit;
}

// Ambil data permohonan jika ada ID untuk pre-fill field (Insightful addition)
$existing_data = [];
if (isset($_GET['id_permohonan'])) {
    $id_perm = mysqli_real_escape_string($koneksi, $_GET['id_permohonan']);
    $p_query = mysqli_query($koneksi, "SELECT data_surat FROM permohonan_surat WHERE id = '$id_perm'");
    $p_res = mysqli_fetch_assoc($p_query);
    if ($p_res && $p_res['data_surat']) {
        $existing_data = json_decode($p_res['data_surat'], true);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
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
</head>
<body>
    <div class="container">
        <a href="dashboard_admin.php" class="back-link">← Kembali ke Dashboard</a>

        <h2>Buat & Kirim Surat </h2>
        
        <form method="POST" id="mainForm">
            <input type="hidden" name="id_permohonan" value="<?= isset($_GET['id_permohonan']) ? $_GET['id_permohonan'] : ''; ?>">
            
            <!-- Langkah 1: Pilih Kategori -->
            <div class="form-group">
                <label>Pilih Kategori Penerima</label>
                <select id="kategori_peran" name="kategori" onchange="updateUserAndTemplate()" required>
                    <option value="">-- Pilih Kategori --</option>
                    <option value="mahasiswa" <?= (isset($_GET['role']) && $_GET['role'] == 'mahasiswa') ? 'selected' : ''; ?>>Mahasiswa (Kategori A)</option>
                    <option value="dosen" <?= (isset($_GET['role']) && $_GET['role'] == 'dosen') ? 'selected' : ''; ?>>Dosen (Kategori B)</option>
                </select>
            </div>

            <!-- Langkah 2: Pilih Nama (Muncul setelah kategori dipilih) -->
            <div class="form-group">
                <label>Pilih Nama Penerima</label>
                <select name="recipient_id" id="recipient" required onchange="showFields()" disabled>
                    <option value="">-- Pilih Nama --</option>
                </select>
            </div>

            <!-- Langkah 3: Pilih Jenis Surat -->
            <div class="form-group">
                <label>Pilih Jenis Surat</label>
                <select name="jenis_surat" id="templateSelect" required onchange="showFields()" disabled>
                    <option value="">-- Pilih Penerima Terlebih Dahulu --</option>
                    <!-- Optgroups will be filtered by JS -->
                    <optgroup label="Kategori A: Mahasiswa" id="optMhs" style="display:none;">
                        <option value="Surat permintaan data awal penelitian">1. Data Awal Penelitian</option>
                        <option value="Surat permintaan data hasil penelitian">2. Data Hasil Penelitian</option>
                        <option value="Permohonan SK pembimbing">3. Permohonan SK Pembimbing</option>
                        <option value="Permohonan SK ujian proposal">4. Permohonan SK Ujian Proposal</option>
                        <option value="Permohonan SK ujian seminar hasil">5. Permohonan SK Ujian Seminar Hasil</option>
                        <option value="Permohonan SK ujian skripsi">6. Permohonan SK Ujian Skripsi</option>
                        <option value="Persetujuan waktu ujian">7. Persetujuan Waktu Ujian</option>
                        <option value="Undangan ujian">8. Undangan Ujian</option>
                        <option value="Transkrip nilai">9. Transkrip Nilai</option>
                        <option value="Cek Turnitin">10. Cek Turnitin</option>
                    </optgroup>
                    <optgroup label="Kategori B: Dosen" id="optDosen" style="display:none;">
                        <option value="Permohonan surat tugas">1. Surat Tugas</option>
                        <option value="Surat rekomendasi penelitian">2. Surat Rekomendasi Penelitian</option>
                        <option value="Permintaan data penelitian">3. Permintaan Data Penelitian</option>
                    </optgroup>
                </select>
            </div>

            <div id="dynamicContainer" class="dynamic-fields">
                <h4 style="margin-top:0">Isi Variabel Surat</h4>
                <div id="fieldsBody"></div>
            </div>

            <button type="submit" name="kirim_surat">Kirim Surat ke Dashboard User</button>
        </form>
    </div>

    <script>
        const usersData = <?= json_encode($all_users); ?>;
        const existingVars = <?= json_encode($existing_data); ?>;
        
        // Jalankan otomatis jika datang dari tombol "Proses Surat"
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('id_permohonan')) {
                updateUserAndTemplate();
                document.getElementById('recipient').value = urlParams.get('user_id');
                document.getElementById('templateSelect').value = urlParams.get('jenis');
                showFields();
            }
        };

        function updateUserAndTemplate() {
            const kategori = document.getElementById('kategori_peran').value;
            const recipientSelect = document.getElementById('recipient');
            const templateSelect = document.getElementById('templateSelect');
            const optMhs = document.getElementById('optMhs');
            const optDosen = document.getElementById('optDosen');

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
                    recipientSelect.add(opt);
                });
            } else {
                recipientSelect.disabled = true;
            }

            // Filter Template
            templateSelect.disabled = true;
            templateSelect.selectedIndex = 0;
            optMhs.style.display = 'none';
            optDosen.style.display = 'none';

            if (kategori === 'mahasiswa') {
                optMhs.style.display = 'block';
                templateSelect.disabled = false;
                templateSelect.options[0].text = "-- Pilih Jenis Surat --";
            } else if (kategori === 'dosen') {
                optDosen.style.display = 'block';
                templateSelect.disabled = false;
                templateSelect.options[0].text = "-- Pilih Jenis Surat --";
            }

            document.getElementById('dynamicContainer').style.display = "none";
        }

        function showFields() {
            const template = document.getElementById('templateSelect').value;
            const container = document.getElementById('dynamicContainer');
            const body = document.getElementById('fieldsBody');
            const userOpt = document.getElementById('recipient').selectedOptions[0];
            
            if(!template) { container.style.display = "none"; return; }
            container.style.display = "block";
            body.innerHTML = "";

            // Auto-fill Data
            const today = new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });

            // Fungsi pembantu untuk mengambil nilai lama jika ada
            const getVal = (key, def = '') => (existingVars && existingVars[key]) ? existingVars[key] : def;

            let commonFields = `
                <label>Nama Lengkap</label><input type="text" name="var[nama]" value="${getVal('nama', userOpt.dataset.nama)}" required>
                <label>NIM / NIDN</label><input type="text" name="var[id_number]" value="${getVal('nim', userOpt.dataset.id || '')}" required>
                <label>Nomor Surat SK</label><input type="text" name="var[nomor_surat]" value="${getVal('nomor_surat')}" placeholder="Contoh: 153/FSIK-UMGO/KEP/V/2026" required>
                <label>Tahun Akademik</label><input type="text" name="var[ta]" value="${getVal('ta')}" placeholder="Contoh: 2025/2026" required>
                <label>Tanggal Surat (Muncul di Footer)</label><input type="text" name="var[tanggal_surat]" value="${getVal('tanggal_surat', today)}" required>
            `;

            let specificFields = "";
            if(template === "Surat permintaan data awal penelitian") {
                specificFields = `
                    <label>Lokasi Penelitian</label><input type="text" name="var[lokasi]" value="${getVal('lokasi')}" required>
                    <label>Data yang Diminta</label><textarea name="var[data]" class="summernote" required>${getVal('data')}</textarea>`;
            } else if(template === "Surat permintaan data hasil penelitian") {
                specificFields = `
                    <label>Lokasi Penelitian</label><input type="text" name="var[lokasi]" value="${getVal('lokasi')}" required>
                    <label>Instansi Tujuan</label><input type="text" name="var[instansi]" value="${getVal('instansi')}" required>`;
            } else if(template.includes("SK pembimbing")) {
                specificFields = `
                    <label>Judul Skripsi</label><textarea name="var[judul]" class="summernote" required>${getVal('judul')}</textarea>
                    <label>Nama Pembimbing 1</label><input type="text" name="var[p1]" value="${getVal('p1')}" required>
                    <label>Nama Pembimbing 2</label><input type="text" name="var[p2]" value="${getVal('p2')}" required>`;
            } else if(template === "Permohonan SK ujian proposal") {
                specificFields = `
                    <label>Judul Proposal</label><textarea name="var[judul]" class="summernote" required>${getVal('judul')}</textarea>
                    <label>Hari / Tanggal / Waktu</label><input type="text" name="var[waktu]" value="${getVal('waktu')}" placeholder="Contoh: Senin, 20 Mei 2024 / 09:00 WIB" required>
                    <label>Nama Penguji</label><textarea name="var[penguji]" class="summernote" required>${getVal('penguji')}</textarea>`;
            } else if(template === "Permohonan SK ujian seminar hasil") {
                specificFields = `
                    <label>Judul Skripsi</label><textarea name="var[judul]" class="summernote" required>${getVal('judul')}</textarea>
                    <label>Catatan Kelayakan</label><textarea name="var[catatan]" class="summernote" required>${getVal('catatan')}</textarea>`;
            } else if(template === "Permohonan SK ujian skripsi") {
                specificFields = `
                    <label>Judul Skripsi</label><textarea name="var[judul]" class="summernote" required>${getVal('judul')}</textarea>
                    <label>Tanggal Kelulusan Syarat</label><input type="date" name="var[tgl_lulus]" value="${getVal('tgl_lulus')}" required>`;
            } else if(template === "Persetujuan waktu ujian") {
                specificFields = `
                    <label>Hari / Tanggal</label><input type="text" name="var[tanggal]" value="${getVal('tanggal')}" required>
                    <label>Jam</label><input type="text" name="var[jam]" value="${getVal('jam')}" required>
                    <label>Ruangan</label><input type="text" name="var[ruangan]" value="${getVal('ruangan')}" required>`;
            } else if(template === "Undangan ujian") {
                specificFields = `
                    <label>Judul Skripsi / Tugas Akhir</label><textarea name="var[judul]" class="summernote" required>${getVal('judul')}</textarea>`;
            } else if(template.includes("surat tugas")) {
                specificFields = `
                    <label>Nama Kegiatan</label><input type="text" name="var[kegiatan]" value="${getVal('kegiatan')}" required>
                    <label>Lokasi</label><input type="text" name="var[lokasi]" value="${getVal('lokasi')}" required>
                    <label>Durasi Tugas</label><input type="text" name="var[durasi]" value="${getVal('durasi')}" required>`;
            } else if(template === "Surat rekomendasi penelitian") {
                specificFields = `
                    <label>Judul Penelitian</label><textarea name="var[judul]" class="summernote" required>${getVal('judul')}</textarea>
                    <label>Pihak Pemberi Rekomendasi</label><input type="text" name="var[pemberi]" value="${getVal('pemberi')}" required>`;
            } else if(template === "Permintaan data penelitian") {
                specificFields = `
                    <label>Topik Riset</label><input type="text" name="var[topik]" value="${getVal('topik')}" required>
                    <label>Sumber Data yang Dituju</label><input type="text" name="var[sumber]" value="${getVal('sumber')}" required>`;
            } else {
                // Default fallback fields
                specificFields = `<label>Catatan/Isi Spesifik</label><textarea name="var[catatan]" class="summernote">${getVal('catatan')}</textarea>`;
            }

            body.innerHTML = commonFields + specificFields;

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