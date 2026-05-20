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

// Validasi akses: mahasiswa/dosen hanya bisa lihat surat miliknya sendiri
if ($_SESSION['role'] !== 'admin' && $data['user_id'] != $_SESSION['user_id']) {
    die("Anda tidak memiliki akses ke surat ini.");
}

// Validasi status: hanya surat yang sudah selesai yang bisa dicetak
if ($data['status'] !== 'Selesai') {
    die("Surat belum selesai diproses.");
}

// === JIKA SURAT BERUPA FILE UPLOAD (bukan SYSTEM_GENERATED) ===
if ($data['surat_jadi'] !== 'SYSTEM_GENERATED') {
    $file_path = "uploads/" . $data['surat_jadi'];
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

    if ($extension == 'pdf') { ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <title>Pratinjau Surat PDF</title>
            <style>
                body, html { margin: 0; padding: 0; height: 100%; overflow: hidden; background: #525659; }
                iframe { width: 100%; height: 100%; border: none; }
                .back-btn { position: fixed; top: 10px; left: 10px; z-index: 999; background: #3498db; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; font-family: sans-serif; font-size: 12px; }
            </style>
        </head>
        <body>
            <a href="javascript:history.back()" class="back-btn">← Kembali</a>
            <iframe src="<?= htmlspecialchars($file_path) ?>#toolbar=1"></iframe>
        </body>
        </html>
    <?php exit;
    } else { ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <title>Unduh Dokumen</title>
            <style>
                body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
                .container { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); text-align: center; max-width: 400px; }
                .btn-download { background: #2ecc71; color: white; text-decoration: none; padding: 12px 25px; border-radius: 6px; font-weight: 600; display: inline-block; }
            </style>
        </head>
        <body>
            <div class="container">
                <h3>Dokumen .<?= strtoupper($extension) ?></h3>
                <p>Silakan unduh file untuk melihat isinya.</p>
                <a href="<?= htmlspecialchars($file_path) ?>" class="btn-download" download>Unduh Sekarang</a>
                <br><br>
                <a href="javascript:history.back()" style="color: #95a5a6; font-size: 12px;">← Kembali</a>
            </div>
        </body>
        </html>
    <?php exit;
    }
}

// === SURAT SYSTEM_GENERATED: Render dari template RTF ===
$vars = [];
if (!empty($data['data_surat'])) {
    $vars = json_decode($data['data_surat'], true) ?: [];
}

// Mapping jenis surat → file RTF template
$rtf_map = [
    'Permohonan SK ujian proposal' => 'rtf/sk_proposal_si.rtf',
    // Tambahkan mapping lain saat file RTF tersedia:
    // 'Permohonan SK pembimbing' => 'rtf/sk_pembimbing_si.rtf',
];

// Mapping key JSON dari admin_kirim_surat → placeholder di template RTF
$placeholder_map = [
    'Permohonan SK ujian proposal' => [
        '%nomor surat%'             => 'nomor_surat',
        '%hari tanggal%'            => 'waktu',
        '%Nama dan Nim%'            => ['nama', 'id_number'], // gabungan
        '%Judul%'                   => 'judul',
        '%Nama Pembimbing 1 dan 2 %' => ['p1', 'p2'],        // gabungan
        '%Nama Penguju%'            => 'penguji',
    ],
];

$rtf_file = isset($rtf_map[$data['jenis_surat']]) ? $rtf_map[$data['jenis_surat']] : null;
$has_rtf = $rtf_file && file_exists($rtf_file);

// Fungsi untuk resolve placeholder value dari vars
function resolvePlaceholder($mapping, $vars, $data) {
    if (is_array($mapping)) {
        // Gabungkan beberapa field
        $parts = [];
        foreach ($mapping as $key) {
            if (isset($vars[$key]) && $vars[$key] !== '') {
                $parts[] = strip_tags($vars[$key]);
            }
        }
        return implode("\n", $parts);
    }
    // Single field
    if (isset($vars[$mapping]) && $vars[$mapping] !== '') {
        return strip_tags($vars[$mapping]);
    }
    return '';
}

// Download RTF jika diminta
if (isset($_GET['download']) && $_GET['download'] === 'rtf' && $has_rtf) {
    $rtf_content = file_get_contents($rtf_file);
    $mappings = $placeholder_map[$data['jenis_surat']] ?? [];
    foreach ($mappings as $placeholder => $field) {
        $value = resolvePlaceholder($field, $vars, $data);
        $rtf_content = str_replace($placeholder, $value, $rtf_content);
    }
    header("Content-type: application/rtf");
    header("Content-Disposition: attachment; filename=Surat_" . str_replace(' ', '_', $data['jenis_surat']) . ".rtf");
    echo $rtf_content;
    exit;
}

// === PREVIEW HTML: Convert RTF → HTML via PHPWord ===
if ($has_rtf) {
    // Load RTF dan convert ke HTML
    $phpWord = \PhpOffice\PhpWord\IOFactory::load($rtf_file, 'RTF');
    $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
    ob_start();
    $writer->save('php://output');
    $html_content = ob_get_clean();

    // Replace placeholder di HTML output
    $mappings = $placeholder_map[$data['jenis_surat']] ?? [];
    foreach ($mappings as $placeholder => $field) {
        $value = resolvePlaceholder($field, $vars, $data);
        // Untuk HTML, bisa mengandung newline → convert ke <br>
        $value_html = nl2br(htmlspecialchars($value));
        $html_content = str_replace(htmlspecialchars($placeholder), $value_html, $html_content);
        $html_content = str_replace($placeholder, $value_html, $html_content);
    }

    // Inject toolbar dan styling tambahan
    $toolbar = '
    <div style="background:#2c3e50; padding:12px; text-align:center; position:sticky; top:0; z-index:100; font-family:sans-serif;">
        <button onclick="window.print()" style="padding:8px 16px; border:none; border-radius:4px; background:#3498db; color:white; font-weight:600; cursor:pointer; margin:0 5px;">🖨️ Cetak / Simpan PDF</button>
        <a href="cetak_surat.php?id=' . $id . '&download=rtf" style="padding:8px 16px; border:none; border-radius:4px; background:#2ecc71; color:white; font-weight:600; text-decoration:none; margin:0 5px; display:inline-block;">📄 Unduh RTF</a>
        <button onclick="history.back()" style="padding:8px 16px; border:none; border-radius:4px; background:#95a5a6; color:white; font-weight:600; cursor:pointer; margin:0 5px;">← Kembali</button>
    </div>';

    $print_style = '<style>@media print { body > div:first-child { display: none !important; } }</style>';

    // Inject toolbar setelah <body>
    $html_content = str_replace('<body>', '<body>' . $toolbar . $print_style, $html_content);

    echo $html_content;
    exit;
}

// === FALLBACK: Jika tidak ada template RTF, tampilkan data dalam format sederhana ===
$nama = htmlspecialchars($vars['nama'] ?? $data['nama_lengkap']);
$nim = htmlspecialchars($vars['id_number'] ?? $vars['nim'] ?? $data['nomor_induk']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Preview Surat - <?= htmlspecialchars($data['jenis_surat']) ?></title>
    <style>
        @page { size: A4; margin: 20mm; }
        body { font-family: 'Times New Roman', serif; line-height: 1.8; background: #f0f0f0; margin: 0; }
        .no-print { background: #2c3e50; padding: 12px; text-align: center; position: sticky; top: 0; z-index: 100; }
        .no-print button, .no-print a { padding: 8px 16px; border: none; border-radius: 4px; font-size: 13px; font-family: sans-serif; font-weight: 600; cursor: pointer; margin: 0 5px; text-decoration: none; display: inline-block; color: white; }
        .page { width: 210mm; min-height: 297mm; padding: 20mm 25mm; margin: 10mm auto; background: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); box-sizing: border-box; }
        .kop { text-align: center; border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 30px; }
        .kop h2, .kop h3, .kop p { margin: 0; }
        table.info td { border: none; padding: 3px 8px; vertical-align: top; }
        @media print { body { background: none; } .page { margin: 0; box-shadow: none; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="background:#3498db;">🖨️ Cetak / Simpan PDF</button>
        <button onclick="history.back()" style="background:#95a5a6;">← Kembali</button>
    </div>
    <div class="page">
        <div class="kop">
            <h2>UNIVERSITAS MUHAMMADIYAH GORONTALO</h2>
            <h3>FAKULTAS SAINS DAN ILMU KOMPUTER</h3>
            <p style="font-size:11px;">Jl. Prof. Dr. Mansoer Pateda, Pentadio Timur, Gorontalo</p>
        </div>
        <h3 style="text-align:center;"><?= strtoupper(htmlspecialchars($data['jenis_surat'])) ?></h3>
        <table class="info">
            <?php foreach ($vars as $key => $val): ?>
            <tr>
                <td style="width:150px;"><b><?= ucfirst(str_replace('_', ' ', htmlspecialchars($key))) ?></b></td>
                <td>: <?= $val ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <div style="margin-top:60px; float:right; text-align:center; width:250px;">
            <p>Gorontalo, <?= htmlspecialchars($vars['tanggal_surat'] ?? date('d F Y')) ?></p>
            <p>Dekan,</p><br><br><br>
            <p><b><u>Dr. Mohamad Ervandi, S.Pt., MP</u></b></p>
        </div>
    </div>
</body>
</html>
