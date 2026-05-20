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
if ($data['status'] !== 'Selesai') {
    die("Surat belum selesai diproses.");
}

// === JIKA SURAT BERUPA FILE UPLOAD ===
if ($data['surat_jadi'] !== 'SYSTEM_GENERATED') {
    $file_path = "uploads/" . $data['surat_jadi'];
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

    if ($extension == 'pdf') { ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <title>Pratinjau Surat</title>
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

// === SURAT SYSTEM_GENERATED: Generate PDF dari template RTF ===
$vars = [];
if (!empty($data['data_surat'])) {
    $vars = json_decode($data['data_surat'], true) ?: [];
}

// Mapping jenis surat → file RTF template
$rtf_map = [
    'Permohonan SK ujian proposal' => 'rtf/sk_proposal_si.rtf',
];

// Mapping key JSON → placeholder di template RTF
$placeholder_map = [
    'Permohonan SK ujian proposal' => [
        '%nomor surat%'              => 'nomor_surat',
        '%hari tanggal%'             => 'waktu',
        '%Nama dan Nim%'             => ['nama', 'id_number'],
        '%Judul%'                    => 'judul',
        '%Nama Pembimbing 1 dan 2 %' => ['p1', 'p2'],
        '%Nama Penguju%'             => 'penguji',
    ],
];

$rtf_file = $rtf_map[$data['jenis_surat']] ?? null;
$has_rtf = $rtf_file && file_exists($rtf_file);

function resolvePlaceholder($mapping, $vars) {
    if (is_array($mapping)) {
        $parts = [];
        foreach ($mapping as $key) {
            if (!empty($vars[$key])) {
                $parts[] = strip_tags($vars[$key]);
            }
        }
        return implode("\n", $parts);
    }
    return !empty($vars[$mapping]) ? strip_tags($vars[$mapping]) : '';
}

// Generate atau ambil PDF dari cache
$pdf_dir = 'uploads/pdf_cache';
if (!is_dir($pdf_dir)) {
    mkdir($pdf_dir, 0777, true);
}

$pdf_filename = 'surat_' . $id . '_' . md5($data['data_surat'] ?? '') . '.pdf';
$pdf_path = $pdf_dir . '/' . $pdf_filename;

// Serve PDF jika parameter ?serve=pdf
if (isset($_GET['serve']) && $_GET['serve'] === 'pdf') {
    if (!file_exists($pdf_path)) {
        // Generate PDF
        if ($has_rtf) {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($rtf_file, 'RTF');
            \PhpOffice\PhpWord\Settings::setPdfRendererName('DomPDF');
            \PhpOffice\PhpWord\Settings::setPdfRendererPath(realpath('vendor/dompdf/dompdf'));
            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'PDF');

            // Replace placeholder di PhpWord object tidak bisa langsung,
            // jadi kita generate HTML dulu, replace, lalu convert ke PDF via DomPDF
            $htmlWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'HTML');
            ob_start();
            $htmlWriter->save('php://output');
            $html_content = ob_get_clean();

            $mappings = $placeholder_map[$data['jenis_surat']] ?? [];
            foreach ($mappings as $placeholder => $field) {
                $value = resolvePlaceholder($field, $vars);
                $value_html = nl2br(htmlspecialchars($value));
                $html_content = str_replace(htmlspecialchars($placeholder), $value_html, $html_content);
                $html_content = str_replace($placeholder, $value_html, $html_content);
            }

            // Generate PDF via DomPDF
            $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
            $dompdf->loadHtml($html_content);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            file_put_contents($pdf_path, $dompdf->output());
        } else {
            // Fallback: generate PDF sederhana untuk jenis surat tanpa template RTF
            $html = buildFallbackHtml($data, $vars);
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            file_put_contents($pdf_path, $dompdf->output());
        }
    }

    // Serve PDF inline (tampil di browser)
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $pdf_filename . '"');
    header('Content-Length: ' . filesize($pdf_path));
    readfile($pdf_path);
    exit;
}

// === TAMPILAN UTAMA: PDF viewer dalam iframe ===
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pratinjau Surat - <?= htmlspecialchars($data['jenis_surat']) ?></title>
    <style>
        body, html { margin: 0; padding: 0; height: 100%; overflow: hidden; background: #525659; }
        iframe { width: 100%; height: calc(100% - 50px); border: none; }
        .toolbar { background: #2c3e50; padding: 10px 20px; display: flex; align-items: center; justify-content: space-between; height: 30px; }
        .toolbar .title { color: #ecf0f1; font-family: sans-serif; font-size: 13px; }
        .toolbar .actions a, .toolbar .actions button { padding: 6px 14px; border: none; border-radius: 4px; font-size: 12px; font-family: sans-serif; font-weight: 600; cursor: pointer; text-decoration: none; margin-left: 8px; display: inline-block; }
        .btn-back { background: #95a5a6; color: white; }
        .btn-download { background: #3498db; color: white; }
    </style>
</head>
<body>
    <div class="toolbar">
        <span class="title"><?= htmlspecialchars($data['jenis_surat']) ?></span>
        <div class="actions">
            <a href="cetak_surat.php?id=<?= $id ?>&serve=pdf" download="<?= $pdf_filename ?>" class="btn-download">⬇ Unduh PDF</a>
            <a href="javascript:history.back()" class="btn-back">← Kembali</a>
        </div>
    </div>
    <iframe src="cetak_surat.php?id=<?= $id ?>&serve=pdf#toolbar=1"></iframe>
</body>
</html>
<?php
exit;

// Fallback HTML untuk jenis surat tanpa template RTF
function buildFallbackHtml($data, $vars) {
    $nama = htmlspecialchars($vars['nama'] ?? $data['nama_lengkap']);
    $rows = '';
    foreach ($vars as $key => $val) {
        $label = ucfirst(str_replace('_', ' ', htmlspecialchars($key)));
        $rows .= "<tr><td style='width:150px;vertical-align:top;'><b>$label</b></td><td>: $val</td></tr>";
    }
    $tanggal = htmlspecialchars($vars['tanggal_surat'] ?? date('d F Y'));
    return "<!DOCTYPE html><html><head><meta charset='UTF-8'>
    <style>body{font-family:'Times New Roman',serif;line-height:1.8;padding:20mm 25mm;}
    .kop{text-align:center;border-bottom:3px double #000;padding-bottom:10px;margin-bottom:30px;}
    .kop h2,.kop h3,.kop p{margin:0;}table td{border:none;padding:3px 8px;}</style></head><body>
    <div class='kop'><h2>UNIVERSITAS MUHAMMADIYAH GORONTALO</h2><h3>FAKULTAS SAINS DAN ILMU KOMPUTER</h3>
    <p style='font-size:11px;'>Jl. Prof. Dr. Mansoer Pateda, Pentadio Timur, Gorontalo</p></div>
    <h3 style='text-align:center;'>" . strtoupper(htmlspecialchars($data['jenis_surat'])) . "</h3>
    <table>$rows</table>
    <div style='margin-top:60px;float:right;text-align:center;width:250px;'>
    <p>Gorontalo, $tanggal</p><p>Dekan,</p><br><br><br>
    <p><b><u>Dr. Mohamad Ervandi, S.Pt., MP</u></b></p></div></body></html>";
}
