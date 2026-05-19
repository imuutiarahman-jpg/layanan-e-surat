<?php
include 'koneksi.php';
if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$id = $_GET['id'];
$query = mysqli_query($koneksi, "SELECT p.*, u.nama_lengkap, u.nomor_induk, u.prodi FROM permohonan_surat p JOIN users u ON p.user_id = u.id WHERE p.id = '$id'");
$data = mysqli_fetch_assoc($query);

if (isset($_POST['upload_replace']) && $_SESSION['role'] == 'admin') {
    if (!empty($_FILES['replace_file']['name'])) {
        $filename = "REPLACE_" . time() . "_" . str_replace(' ', '_', $_FILES['replace_file']['name']);
        if (move_uploaded_file($_FILES['replace_file']['tmp_name'], 'uploads/' . $filename)) {
            mysqli_query($koneksi, "UPDATE permohonan_surat SET surat_jadi = '$filename' WHERE id = '$id'");
            echo "<script>alert('Berhasil! Surat otomatis telah diganti dengan file manual.'); window.location='admin_arsip.php';</script>";
            exit;
        }
    }
}

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

// Ambil template yang sesuai
$jenis = $data['jenis_surat'];
$tmpl_query = mysqli_query($koneksi, "SELECT isi_template FROM templates WHERE jenis_surat = '$jenis'");
$tmpl = mysqli_fetch_assoc($tmpl_query);

$vars = [];
$isi_surat = $tmpl ? $tmpl['isi_template'] : "Template belum diatur oleh admin untuk jenis ini.";

// Proses penggantian variabel dinamis
if (!empty($data['data_surat'])) {
    $vars = json_decode($data['data_surat'], true);
    foreach ($vars as $key => $val) {
        $isi_surat = str_replace("[" . strtoupper($key) . "]", "<b>" . htmlspecialchars($val) . "</b>", $isi_surat);
    }
}

// Tambahan placeholder khusus SK
$isi_surat = str_replace("[NOMOR_SURAT]", "<b>" . ($vars['nomor_surat'] ?? '...') . "</b>", $isi_surat);
$isi_surat = str_replace("[TA]", "<b>" . ($vars['ta'] ?? '...') . "</b>", $isi_surat);
$isi_surat = str_replace("[PRODI]", "<b>" . strtoupper($data['prodi'] ?? 'Sistem Informasi') . "</b>", $isi_surat);

// Fallback placeholders standar
$isi_surat = str_replace("[NAMA]", "<b>" . $data['nama_lengkap'] . "</b>", $isi_surat);
$isi_surat = str_replace("[TANGGAL_TTD]", date('d F Y'), $isi_surat);

$is_sk = (strpos(strtolower($data['jenis_surat']), 'sk') !== false);
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
        <button onclick="window.close()" style="padding: 10px 20px; cursor:pointer;">Tutup</button>
    </div>
    <?php if($_SESSION['role'] == 'admin'): ?>
        <div class="no-print" style="background: #fff3cd; border: 1px solid #ffeeba; padding: 15px; text-align: center;">
            <form method="POST" enctype="multipart/form-data" style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                <span style="font-size: 13px; font-weight: bold; color: #856404;">Ganti dengan File Manual (Word/PDF):</span>
                <input type="file" name="replace_file" accept=".pdf,.doc,.docx" required>
                <button type="submit" name="upload_replace" style="padding: 5px 15px; background: #856404; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">Upload & Ganti</button>
            </form>
        </div>
    <?php endif; ?>
    <div class="page">
    <div class="kop-surat">
        <h2 style="margin:0;">UNIVERSITAS MUHAMMADIYAH GORONTALO</h2>
        <h3 style="margin:0;">FAKULTAS SAINS DAN ILMU KOMPUTER</h3>
        <p style="margin:0; font-size: 12px;">Jl. Prof. Dr. Mansoer Pateda No. 247, Pentadio Timur, Gorontalo | Email: fsik@umgo.ac.id</p>
    </div>

    <div class="content">
        <?php if($is_sk): ?>
            <p style="text-align: center; font-weight: bold; margin-bottom: 0;">SURAT KEPUTUSAN</p>
            <p style="text-align: center; font-weight: bold; margin-top: 0;">DEKAN FAKULTAS SAINS DAN ILMU KOMPUTER <br> Nomor : <?= $vars['nomor_surat'] ?? '...'; ?></p>
            <p style="text-align: center; font-weight: bold; margin-top: 20px;">TENTANG</p>
            <p style="text-align: center; font-weight: bold;">TIM PENGUJI UJIAN SEMINAR PROPOSAL <br> PROGRAM STUDI <?= strtoupper($data['prodi'] ?? 'Sistem Informasi'); ?> <br> FAKULTAS SAINS DAN ILMU KOMPUTER UNIVERSITAS MUHAMMADIYAH GORONTALO <br> SEMESTER GENAP T.A <?= $vars['ta'] ?? '...'; ?></p>
        <?php else: ?>
        <p style="text-align: center; font-weight: bold; text-decoration: underline; font-size: 18px;">
            SURAT KETERANGAN <?= strtoupper($data['jenis_surat']); ?>
        </p>
        <?php endif; ?>
        
        <div style="margin-top: 40px;">
            <?= $isi_surat; ?>
        </div>
    </div>

    <div class="footer">
        <p>Gorontalo, <?= isset($vars['tanggal_surat']) ? htmlspecialchars($vars['tanggal_surat']) : date('d F Y'); ?></p>
        <p>Dekan,</p>
        <br><br><br><br>
        <p><b>Dr. Mohamad Ervandi, S.Pt., MP</b></p>
        <p style="margin-top:-15px;">NBM : 1 2 3 2 6 9 0</p>
        <div style="font-size: 10px;">ID Validasi: <?= md5($data['id']); ?></div>
    </div>
    <div style="clear: both;"></div>

    <?php if($is_sk): ?>
        <div class="lampiran-page">
            <p>Lampiran : Surat Keputusan Dekan Fakultas Sains dan Ilmu Komputer</p>
            <p style="margin-top:-10px;">Nomor : <?= $vars['nomor_surat'] ?? '...'; ?></p>
            <p style="margin-top:-10px;">Tanggal : <?= isset($vars['tanggal_surat']) ? htmlspecialchars($vars['tanggal_surat']) : date('d F Y'); ?></p>
            <p style="margin-top:-10px;">Tentang : Penetapan Tim Penguji Ujian Seminar Proposal</p>
            
            <table class="table-lampiran">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nama/NIM</th>
                        <th>Judul Seminar Proposal</th>
                        <th>Pembimbing</th>
                        <th>Penguji</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1.</td>
                        <td><?= $data['nama_lengkap']; ?> / <?= $data['nomor_induk']; ?></td>
                        <td><?= $vars['judul'] ?? '-'; ?></td>
                        <td>1. <?= $vars['p1'] ?? '-'; ?><br>2. <?= $vars['p2'] ?? '-'; ?></td>
                        <td><?= $vars['penguji'] ?? '-'; ?></td>
                    </tr>
                </tbody>
            </table>

            <div class="footer">
                <p>Mengetahui,</p>
                <p>Dekan,</p>
                <br><br><br>
                <p><b>Dr. Mohamad Ervandi, S.Pt., MP</b></p>
                <p style="margin-top:-15px;">NBM : 1 2 3 2 6 9 0</p>
            </div>
        </div>
    <?php endif; ?>
    </div>
</body>
</html>