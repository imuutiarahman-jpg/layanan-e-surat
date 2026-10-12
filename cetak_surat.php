<?php
include 'koneksi.php';
require 'vendor/autoload.php';

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = mysqli_prepare($koneksi, "SELECT p.*, u.nama_lengkap, u.nomor_induk, u.prodi, u.role FROM permohonan_surat p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    die("Data tidak ditemukan.");
}

// Validasi akses
if ($_SESSION['role'] !== 'admin' && $data['user_id'] != $_SESSION['user_id']) {
    die("Anda tidak memiliki akses ke surat ini.");
}

// Validasi status
if ($data['status'] !== 'Selesai' && !($_SESSION['role'] === 'admin' && $data['status'] === 'Diproses')) {
    die("Surat belum selesai diproses.");
}

$is_admin = ($_SESSION['role'] === 'admin');

// Logika Tolak permohonan oleh Admin (tanpa mengubah tampilan daftar, hanya tambah aksi di preview)
if (isset($_POST['tolak_permohonan']) && $is_admin) {
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        die("Token keamanan tidak valid.");
    }
    $alasan = trim($_POST['alasan_tolak'] ?? '');
    if ($alasan === '') { $alasan = 'Permohonan ditolak oleh admin.'; }
    $upd = mysqli_prepare($koneksi, "UPDATE permohonan_surat SET status='Ditolak' WHERE id=?");
    if ($upd) {
        mysqli_stmt_bind_param($upd, "i", $id);
        mysqli_stmt_execute($upd);
        $pesan_notif = "Permohonan '" . $data['jenis_surat'] . "' DITOLAK. Alasan: " . $alasan;
        $stmt_n = mysqli_prepare($koneksi, "INSERT INTO notifikasi (user_id, pesan) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt_n, "is", $data['user_id'], $pesan_notif);
        mysqli_stmt_execute($stmt_n);
        echo "<script>alert('Permohonan ditolak dan user telah dinotifikasi.'); window.location='dashboard_admin.php';</script>";
        exit;
    }
}

// Logika Konfirmasi oleh Admin untuk mengirim surat ke User
if (isset($_POST['konfirmasi_kirim']) && $is_admin) {
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        die("Token keamanan tidak valid.");
    }
    if (isset($_FILES['file_ttd']) && $_FILES['file_ttd']['error'] == 0) {
        $berkas = $_FILES['file_ttd']['name'];
        $tmp_name = $_FILES['file_ttd']['tmp_name'];
        $ext = strtolower(pathinfo($berkas, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

        if (!in_array($ext, $allowed)) {
            echo "<script>alert('Format file tidak diizinkan! (PDF/DOCX/JPG/PNG)');</script>";
        } elseif ($_FILES['file_ttd']['size'] > 10 * 1024 * 1024) {
            echo "<script>alert('Ukuran file maksimal 10MB.');</script>";
        } else {
            // Validasi MIME agar file TTD tidak bisa dipalsukan
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $tmp_name) : '';
            if ($finfo) finfo_close($finfo);
            $allowed_mime = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/msword', 'image/jpeg', 'image/png'];
            if (!in_array($mime, $allowed_mime, true)) {
                echo "<script>alert('Isi file tidak valid.');</script>";
            } else {
            $berkas_baru = "SIGNED_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
            if (!is_dir('uploads')) mkdir('uploads', 0755, true);

            if (move_uploaded_file($tmp_name, 'uploads/' . $berkas_baru)) {
                $update_stmt = mysqli_prepare($koneksi, "UPDATE permohonan_surat SET status='Selesai', surat_jadi=? WHERE id=?");
                mysqli_stmt_bind_param($update_stmt, "si", $berkas_baru, $id);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    $pesan_notif = "Surat/SK '" . $data['jenis_surat'] . "' yang telah ditandatangani kini tersedia di dashboard Anda.";
                    $stmt_n = mysqli_prepare($koneksi, "INSERT INTO notifikasi (user_id, pesan) VALUES (?, ?)");
                    mysqli_stmt_bind_param($stmt_n, "is", $data['user_id'], $pesan_notif);
                    mysqli_stmt_execute($stmt_n);

                    echo "<script>alert('Surat berhasil diunggah dan dikirim ke User!'); window.location='dashboard_admin.php';</script>";
                    exit;
                }
            }
            } // tutup else MIME valid
        } // tutup else ext/size valid
    } else {
        echo "<script>alert('Gagal mengunggah berkas tanda tangan. Pastikan file terpilih.');</script>";
    }
}

$force_download = (isset($_GET['download']) && $_GET['download'] == '1');

// === JIKA SURAT BERUPA FILE UPLOAD ===
if ($data['surat_jadi'] !== 'SYSTEM_GENERATED') {
    $safe_name = basename((string)$data['surat_jadi']);
    // Tolak traversal / nama aneh
    if ($safe_name === '' || $safe_name !== $data['surat_jadi'] || strpos($safe_name, '..') !== false) {
        die("Nama file tidak valid.");
    }
    $file_path = "uploads/" . $safe_name;
    if (!is_file($file_path)) {
        die("File surat tidak ditemukan. Hubungi admin.");
    }
    // Pastikan file benar-benar di dalam folder uploads (anti traversal via symlink)
    $real_base = realpath('uploads');
    $real_file = realpath($file_path);
    if ($real_base === false || $real_file === false || strpos($real_file, $real_base) !== 0) {
        die("Akses file ditolak.");
    }
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    
    // Mapping content type sederhana
    $ctype = "application/octet-stream";
    if($ext == 'pdf') $ctype = "application/pdf";
    if(in_array($ext, ['jpg','jpeg','png'])) $ctype = "image/".$ext;
    if(in_array($ext, ['doc','docx'])) $ctype = "application/vnd.openxmlformats-officedocument.wordprocessingml.document";

    if (ob_get_contents()) ob_end_clean();
    header("Content-Type: $ctype");
    header("Content-Length: " . filesize($file_path));
    // Jika download=1 atau filenya word (.docx/.doc), paksa unduh. Selain itu (PDF/Gambar) tampilkan inline.
    $disposition = ($force_download || strpos($ext, 'doc') !== false) ? 'attachment' : 'inline';
    header("Content-Disposition: $disposition; filename=\"" . $safe_name . "\"");
    
    readfile($file_path);
    exit;
}

// === SURAT SYSTEM_GENERATED: Generate & Download Langsung ===
$vars = [];
if (!empty($data['data_surat'])) {
    $vars = json_decode($data['data_surat'], true) ?: [];
}

$base_filename = 'Surat_' . preg_replace('/[^A-Za-z0-9]/', '_', $data['nama_lengkap']) . '.docx';
$docx_template = '';

// Ambil template dari database berdasarkan jenis surat (Gunakan LIKE untuk toleransi spasi/karakter)
$nama_surat_clean = trim($data['jenis_surat']);
$stmt_tmpl = mysqli_prepare($koneksi, "SELECT file_template FROM master_surat_jenis WHERE nama_surat = ? OR nama_surat LIKE ? LIMIT 1");
$search_param = "%" . $nama_surat_clean . "%";
mysqli_stmt_bind_param($stmt_tmpl, "ss", $nama_surat_clean, $search_param);
mysqli_stmt_execute($stmt_tmpl);
$res_tmpl = mysqli_stmt_get_result($stmt_tmpl);
if ($row_tmpl = mysqli_fetch_assoc($res_tmpl)) {
    $docx_template = 'templates/' . basename($row_tmpl['file_template']);
}

if (!empty($row_tmpl['file_template']) && is_file($docx_template)) {
    // --- CASE 1: MENGGUNAKAN TEMPLATE DOCX ---
    $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($docx_template);

    // Isi variabel template secara otomatis dari data_surat
    foreach ($vars as $key => $val) {
        // Khusus untuk NIM/ID Number agar kompatibel dengan berbagai key
        if ($key === 'id_number' || $key === 'nim') {
            $templateProcessor->setValue('nim', htmlspecialchars(strip_tags($val)));
            $templateProcessor->setValue('id_number', htmlspecialchars(strip_tags($val)));
        }
        $templateProcessor->setValue($key, htmlspecialchars(strip_tags($val)));
    }
    
    // Fallback variabel umum jika belum ada
    $templateProcessor->setValue('nama', htmlspecialchars(strip_tags($vars['nama'] ?? $data['nama_lengkap'])));
    $templateProcessor->setValue('tanggal_surat', htmlspecialchars(strip_tags($vars['tanggal_surat'] ?? date('d F Y'))));

    if ($is_admin && !$force_download) {
        // Untuk admin: simpan sementara dan tampilkan halaman pratinjau
        $temp_dir = 'uploads/temp_admin';
        if (!is_dir($temp_dir)) mkdir($temp_dir, 0755, true);
        
        $generated_file_path = $temp_dir . '/' . uniqid('docx_') . '_' . $base_filename;
        $templateProcessor->saveAs($generated_file_path);

        // Tampilkan halaman pratinjau untuk admin
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <title>Pratinjau Surat (Admin)</title>
            <style>
                body { font-family: 'Inter', sans-serif; background: #f4f7f6; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
                .container { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); text-align: center; max-width: 500px; }
                h3 { color: #2c3e50; margin-bottom: 20px; }
                .btn-action { background: #3498db; color: white; text-decoration: none; padding: 12px 25px; border-radius: 6px; font-weight: 600; display: inline-block; margin: 10px; }
                .btn-edit { background: #f39c12; }
                .btn-download { background: #2ecc71; }
            </style>
        </head>
        <body>
            <div class="container">
                <h3>Pratinjau Surat: <?= htmlspecialchars($data['jenis_surat']) ?></h3>
                <p>Surat draf berhasil digenerate. Silakan unduh untuk ditandatangani, lalu unggah kembali versi finalnya.</p>
                <a href="<?= htmlspecialchars($generated_file_path) ?>" class="btn-action btn-download" download="<?= htmlspecialchars($base_filename) ?>">⬇ Unduh DOCX</a>
                
                <form method="POST" enctype="multipart/form-data" style="margin-top: 20px; border: 1px dashed #ccc; padding: 20px; border-radius: 8px;">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <label style="display:block; margin-bottom:10px; font-weight:bold;">Unggah Surat Final (Tanda Tangan/PDF):</label>
                    <input type="file" name="file_ttd" accept=".pdf,.docx,.jpg,.png" required style="margin-bottom:15px; border: 1px solid #ddd; padding: 8px; width: 100%; box-sizing: border-box;">
                    <button type="submit" name="konfirmasi_kirim" class="btn-action btn-download" style="border:none; cursor:pointer; background:#2ecc71; width: 100%;">✅ Unggah & Kirim ke User</button>
                </form>

                <form method="POST" onsubmit="return confirm('Yakin tolak permohonan ini? User akan dinotifikasi.');" style="margin-top: 10px; border: 1px dashed #e74c3c; padding: 20px; border-radius: 8px;">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <label style="display:block; margin-bottom:10px; font-weight:bold; color:#c0392b;">Tolak Permohonan:</label>
                    <input type="text" name="alasan_tolak" placeholder="Alasan penolakan..." required style="margin-bottom:15px; border: 1px solid #ddd; padding: 8px; width: 100%; box-sizing: border-box;">
                    <button type="submit" name="tolak_permohonan" class="btn-action" style="border:none; cursor:pointer; background:#e74c3c; width: 100%;">❌ Tolak & Notifikasi User</button>
                </form>

                <a href="admin_kirim_surat.php?id_permohonan=<?= $id ?>&user_id=<?= $data['user_id']; ?>&role=<?= $data['role']; ?>&jenis=<?= urlencode($data['jenis_surat']); ?>" class="btn-action btn-edit">✎ Edit Variabel</a>
                <br><br>
                <a href="javascript:history.back()" style="color: #95a5a6; font-size: 12px;">← Kembali</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    } else {
        // Perbaikan: Bersihkan output buffer agar file word tidak korup
        if (ob_get_contents()) ob_end_clean(); 
        
        // Untuk user (Mahasiswa/Dosen) atau Admin yang klik unduh: Langsung kirim file Word
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $base_filename . '"');
        $templateProcessor->saveAs('php://output');
        exit;
    }
} else {
    // Jika template tidak ditemukan, jangan gunakan layout fallback.
    // Informasikan admin agar segera mengunggah template yang benar.
    die("Kesalahan: Template dokumen (.docx) untuk jenis surat '" . htmlspecialchars($data['jenis_surat']) . "' tidak ditemukan di folder templates/. Silakan unggah template di Manajemen Jenis Surat.");
}
// Fallback jika terjadi kesalahan logika
die("Terjadi kesalahan saat memproses surat.");
exit;