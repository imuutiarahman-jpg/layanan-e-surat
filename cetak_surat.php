<?php
include 'koneksi.php';
require 'vendor/autoload.php';

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = mysqli_prepare($koneksi, "SELECT p.*, u.nama_lengkap, u.nomor_induk, u.prodi FROM permohonan_surat p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
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

// Logika Konfirmasi oleh Admin untuk mengirim surat ke User
if (isset($_POST['konfirmasi_kirim']) && $is_admin) {
    $update_stmt = mysqli_prepare($koneksi, "UPDATE permohonan_surat SET status='Selesai' WHERE id=?");
    mysqli_stmt_bind_param($update_stmt, "i", $id);
    
    if (mysqli_stmt_execute($update_stmt)) {
        $pesan_notif = "Surat/SK '" . $data['jenis_surat'] . "' telah tersedia dan dapat diunduh di dashboard Anda.";
        $stmt_n = mysqli_prepare($koneksi, "INSERT INTO notifikasi (user_id, pesan) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt_n, "is", $data['user_id'], $pesan_notif);
        mysqli_stmt_execute($stmt_n);

        echo "<script>alert('Surat berhasil dikonfirmasi dan dikirim ke User!'); window.location='dashboard_admin.php';</script>";
        exit;
    }
}

$force_download = (isset($_GET['download']) && $_GET['download'] == '1');

// === JIKA SURAT BERUPA FILE UPLOAD ===
if ($data['surat_jadi'] !== 'SYSTEM_GENERATED') {
    $file_path = "uploads/" . $data['surat_jadi'];
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    
    // Mapping content type sederhana
    $ctype = "application/octet-stream";
    if($ext == 'pdf') $ctype = "application/pdf";
    if(in_array($ext, ['jpg','jpeg','png'])) $ctype = "image/".$ext;

    header("Content-Type: $ctype");
    // Jika download=1 atau filenya word (.docx/.doc), paksa unduh. Selain itu (PDF/Gambar) tampilkan inline.
    $disposition = ($force_download || strpos($ext, 'doc') !== false) ? 'attachment' : 'inline';
    header("Content-Disposition: $disposition; filename=\"" . $data['surat_jadi'] . "\"");
    
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
$search_param = "%$nama_surat_clean%";
mysqli_stmt_bind_param($stmt_tmpl, "ss", $nama_surat_clean, $search_param);
mysqli_stmt_execute($stmt_tmpl);
$res_tmpl = mysqli_stmt_get_result($stmt_tmpl);
if ($row_tmpl = mysqli_fetch_assoc($res_tmpl)) {
    $docx_template = 'templates/' . $row_tmpl['file_template'];
}

if (!empty($docx_template) && file_exists($docx_template)) {
    // --- CASE 1: MENGGUNAKAN TEMPLATE DOCX ---
    $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($docx_template);

    // Isi variabel template secara otomatis dari data_surat
    foreach ($vars as $key => $val) {
        // Khusus untuk NIM/ID Number agar kompatibel dengan berbagai key
        if ($key == 'id_number' || $key == 'nim') {
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
        if (!is_dir($temp_dir)) mkdir($temp_dir, 0777, true);
        
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
                <p>Surat telah berhasil digenerate. Silakan periksa dan unduh.</p>
                <a href="<?= htmlspecialchars($generated_file_path) ?>" class="btn-action btn-download" download="<?= htmlspecialchars($base_filename) ?>">⬇ Unduh DOCX</a>
                
                <form method="POST" style="display:inline;">
                    <button type="submit" name="konfirmasi_kirim" class="btn-action btn-download" style="border:none; cursor:pointer; background:#2ecc71;">✅ Konfirmasi & Kirim ke User</button>
                </form>

                <!-- Perbaikan: Mengubah $data['role'] menjadi $_SESSION['role'] -->
                <a href="admin_kirim_surat.php?id_permohonan=<?= $id ?>&user_id=<?= $data['user_id']; ?>&role=<?= $_SESSION['role']; ?>&jenis=<?= urlencode($data['jenis_surat']); ?>" class="btn-action btn-edit">✎ Edit Variabel</a>
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