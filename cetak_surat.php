<?php
include 'koneksi.php';
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$id = isset($_GET['id']) ? $_GET['id'] : '';
$stmt = mysqli_prepare($koneksi, "SELECT p.*, u.nama_lengkap, u.nomor_induk, u.prodi FROM permohonan_surat p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    die("Data tidak ditemukan.");
}

if ($data['surat_jadi'] != 'SYSTEM_GENERATED') {
    $file_path = "uploads/" . $data['surat_jadi'];
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

    if ($extension == 'pdf') {
        // Gunakan struktur HTML lengkap untuk pratinjau PDF agar tidak terunduh
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <title>Pratinjau Surat PDF</title>
            <style>
                body, html { margin: 0; padding: 0; height: 100%; overflow: hidden; background: #525659; }
                iframe { width: 100%; height: 100%; border: none; }
                .back-btn { position: fixed; top: 10px; left: 10px; z-index: 999; background: #3498db; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; font-family: sans-serif; font-size: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
            </style>
        </head>
        <body>
            <a href="javascript:history.back()" class="back-btn">← Kembali</a>
            <iframe src="<?= $file_path ?>#toolbar=1"></iframe>
        </body>
        </html>
        <?php
        exit;
    } else {
        // Jika Word (.doc/.docx), tampilkan halaman unduh daripada langsung terunduh otomatis
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <title>Unduh Dokumen</title>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                .container { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); text-align: center; max-width: 400px; }
                .icon { font-size: 60px; margin-bottom: 20px; }
                h3 { color: #2c3e50; margin-bottom: 10px; }
                p { color: #7f8c8d; font-size: 14px; margin-bottom: 25px; line-height: 1.5; }
                .btn-download { background: #2ecc71; color: white; text-decoration: none; padding: 12px 25px; border-radius: 6px; font-weight: 600; display: inline-block; transition: 0.3s; }
                .btn-download:hover { background: #27ae60; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="icon">📄</div>
                <h3>Dokumen .<?= strtoupper($extension) ?></h3>
                <p>Browser tidak mendukung pratinjau langsung untuk file Word/Dokumen.<br>Silakan unduh file untuk melihat isinya.</p>
                <a href="<?= $file_path ?>" class="btn-download" download>Unduh Sekarang</a>
                <br><br>
                <a href="javascript:history.back()" style="color: #95a5a6; font-size: 12px; text-decoration: none;">← Kembali</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

$vars = [];
$isi_surat = "Isi surat belum ditentukan."; // Default fallback

// Cek apakah ada Template Master RTF untuk jenis surat ini (Pendekatan 1)
// Gunakan MD5 dari jenis surat agar nama file dinamis sesuai jenisnya
$rtf_master_path = "rtf/" . md5($data['jenis_surat']) . ".rtf";
$is_rtf_mode = file_exists($rtf_master_path);

// LOGIKA UNDUH: Hanya dijalankan jika ada parameter ?download=rtf
if (isset($_GET['download']) && $_GET['download'] == 'rtf' && $is_rtf_mode) {
    if (!empty($data['data_surat'])) {
        $vars = json_decode($data['data_surat'], true);
        $rtf_content = file_get_contents($rtf_master_path);
        foreach ($vars as $key => $val) {
            $rtf_content = str_replace("%" . $key . "%", $val, $rtf_content);
        }
        header("Content-type: application/rtf");
        header("Content-Disposition: attachment; filename=Surat_" . str_replace(' ', '_', $data['jenis_surat']) . ".rtf");
        echo $rtf_content;
        exit;
    }
}

// LOGIKA PRATINJAU: Menyiapkan variabel untuk tampilan HTML
if (!empty($data['data_surat'])) {
    $vars = json_decode($data['data_surat'], true);

    /* // Bagian ini dihapus agar tidak langsung download saat halaman dibuka
        $rtf_content = file_get_contents($rtf_master_path);
        foreach ($vars as $key => $val) {
            // Ganti %nama%, %id_number%, %nomor_surat%, dll sesuai atribut database (JSON)
            $rtf_content = str_replace("%" . $key . "%", $val, $rtf_content);
        }
        
        // Kirim header untuk download file RTF (Rich Text Format)
        header("Content-type: application/rtf");
        header("Content-Disposition: attachment; filename=Surat_Selesai_" . time() . ".rtf");
        echo $rtf_content;
        exit;
    */

    $main_content_key = (isset($vars['data'])) ? 'data' : ((isset($vars['judul'])) ? 'judul' : 'catatan');
    if (isset($vars[$main_content_key])) $isi_surat = $vars[$main_content_key];

    foreach ($vars as $key => $val) {
        if ($key !== $main_content_key) {
            $final_val = (strlen($val) < 100 && !str_contains($val, '<')) ? "<b>$val</b>" : $val;
            // Gunakan format %variabel% agar sama dengan template RTF
            $isi_surat = str_replace("%" . $key . "%", $final_val, $isi_surat);
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Cetak Surat Otomatis</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }
        body {
            font-family: 'Times New Roman', serif;
            line-height: 1.8;
            color: #000;
            background-color: #f0f0f0;
            margin: 0;
            padding: 0;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm 25mm;
            margin: 10mm auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            box-sizing: border-box;
        }
        .kop-surat { text-align: center; border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 40px; }
        .content { text-align: justify; min-height: 300px; }
        /* Styling Konten Dinamis dari Word */
        .content img { max-width: 100%; height: auto; display: block; margin: 10px auto; }
        .content table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .content table td, .content table th { border: 1px solid #333; padding: 8px; vertical-align: top; }
        .content p { margin-bottom: 10px; }
        .footer { margin-top: 40px; float: right; text-align: center; width: 300px; }
        .qr-code { margin-top: 10px; border: 1px solid #eee; padding: 5px; width: 80px; }
        .no-print { background: #f4f4f4; padding: 15px; border-bottom: 1px solid #ccc; text-align: center; }
        .lampiran-page { page-break-before: always; margin-top: 50px; }
        .table-lampiran { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table-lampiran th, .table-lampiran td { border: 1px solid black; padding: 8px; text-align: left; font-size: 12px; }
        .legal-box { display: flex; margin-bottom: 10px; }
        .legal-label { width: 120px; font-weight: bold; }
        @media print {
            body { background: none; }
            .page { margin: 0; box-shadow: none; width: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="padding: 10px 20px; cursor:pointer;">Cetak Surat / Simpan PDF</button>
        <?php if ($is_rtf_mode): ?>
            <a href="cetak_surat.php?id=<?= $id ?>&download=rtf" style="padding: 10px 20px; text-decoration: none; background: #2ecc71; color: white; border-radius: 4px; font-size: 13px; font-family: sans-serif; display: inline-block; font-weight: bold;">Unduh File Word (.rtf)</a>
        <?php endif; ?>
        <button onclick="window.close()" style="padding: 10px 20px; cursor:pointer;">Tutup</button>
    </div>
    <div class="page">
    <div class="kop-surat">
        <h2 style="margin:0;">UNIVERSITAS MUHAMMADIYAH GORONTALO</h2>
        <h3 style="margin:0;">FAKULTAS SAINS DAN ILMU KOMPUTER</h3>
        <p style="margin:0; font-size: 12px;">Jl. Prof. Dr. Mansoer Pateda No. 247, Pentadio Timur, Gorontalo | Email: fsik@umgo.ac.id</p>
    </div>

    <div class="content">
        <!-- Konten surat sepenuhnya diambil dari input Rich Text Editor Admin -->
        <div style="margin-top: 20px;">
            <?= $isi_surat; ?>
        </div>
    </div>

    <div class="footer">
        <p>Gorontalo, <?= date('d F Y'); ?></p>
        <p>Dekan,</p>
        <br><br><br><br>
        <p><b>Dr. Mohamad Ervandi, S.Pt., MP</b></p>
        <p style="margin-top:-15px;">NBM : 1 2 3 2 6 9 0</p>
        <div style="font-size: 10px;">ID Validasi: <?= md5($data['id']); ?></div>
    </div>
    <div style="clear: both;"></div>
    </div>
</body>
</html>