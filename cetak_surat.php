<?php
include 'koneksi.php';
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

// Validasi akses: mahasiswa/dosen hanya bisa lihat surat miliknya
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

// === SURAT SYSTEM_GENERATED: Preview HTML dari data_surat JSON ===
$vars = [];
if (!empty($data['data_surat'])) {
    $vars = json_decode($data['data_surat'], true) ?: [];
}

// Mapping jenis surat ke file RTF (untuk fitur download RTF)
$rtf_map = [
    'Permohonan SK ujian proposal' => 'sk_proposal_si.rtf',
    // Tambahkan mapping lain di sini saat file RTF tersedia
];

$rtf_file = isset($rtf_map[$data['jenis_surat']]) ? 'rtf/' . $rtf_map[$data['jenis_surat']] : null;
$has_rtf = $rtf_file && file_exists($rtf_file);

// Download RTF jika diminta
if (isset($_GET['download']) && $_GET['download'] === 'rtf' && $has_rtf) {
    $rtf_content = file_get_contents($rtf_file);
    foreach ($vars as $key => $val) {
        $rtf_content = str_replace("%" . $key . "%", strip_tags($val), $rtf_content);
    }
    header("Content-type: application/rtf");
    header("Content-Disposition: attachment; filename=Surat_" . str_replace(' ', '_', $data['jenis_surat']) . ".rtf");
    echo $rtf_content;
    exit;
}

// Helper: ambil nilai variabel dengan fallback
function v($vars, $key, $default = '-') {
    return isset($vars[$key]) && $vars[$key] !== '' ? $vars[$key] : $default;
}

// Render konten surat berdasarkan jenis
function renderSuratContent($jenis, $vars, $data) {
    $nama = htmlspecialchars(v($vars, 'nama', $data['nama_lengkap']));
    $nim = htmlspecialchars(v($vars, 'id_number', v($vars, 'nim', $data['nomor_induk'])));
    $prodi = htmlspecialchars($data['prodi'] ?? 'Sistem Informasi');
    $nomor_surat = htmlspecialchars(v($vars, 'nomor_surat'));
    $ta = htmlspecialchars(v($vars, 'ta'));

    $html = '<p style="text-align:center; font-weight:bold; text-decoration:underline; font-size:14pt; margin-bottom:5px;">';
    
    switch ($jenis) {
        case 'Permohonan SK pembimbing':
            $html .= 'SURAT KEPUTUSAN<br>PENETAPAN PEMBIMBING SKRIPSI</p>';
            $html .= "<p style='text-align:center;'>Nomor: $nomor_surat</p>";
            $html .= '<p>Dekan Fakultas Sains dan Ilmu Komputer Universitas Muhammadiyah Gorontalo, dengan ini menetapkan:</p>';
            $html .= "<table style='margin:15px 0; border:none;'>
                <tr><td style='border:none; width:150px;'>Nama</td><td style='border:none;'>: $nama</td></tr>
                <tr><td style='border:none;'>NIM</td><td style='border:none;'>: $nim</td></tr>
                <tr><td style='border:none;'>Program Studi</td><td style='border:none;'>: $prodi</td></tr>
                <tr><td style='border:none;'>Tahun Akademik</td><td style='border:none;'>: $ta</td></tr>
                <tr><td style='border:none; vertical-align:top;'>Judul Skripsi</td><td style='border:none;'>: " . v($vars, 'judul') . "</td></tr>
            </table>";
            $html .= "<p>Dengan susunan pembimbing sebagai berikut:</p>";
            $html .= "<ol><li>Pembimbing I: <b>" . htmlspecialchars(v($vars, 'p1')) . "</b></li>";
            $html .= "<li>Pembimbing II: <b>" . htmlspecialchars(v($vars, 'p2')) . "</b></li></ol>";
            break;

        case 'Permohonan SK ujian proposal':
            $html .= 'SURAT KEPUTUSAN<br>PELAKSANAAN UJIAN SEMINAR PROPOSAL</p>';
            $html .= "<p style='text-align:center;'>Nomor: $nomor_surat</p>";
            $html .= '<p>Dekan Fakultas Sains dan Ilmu Komputer Universitas Muhammadiyah Gorontalo, dengan ini menetapkan:</p>';
            $html .= "<table style='margin:15px 0; border:none;'>
                <tr><td style='border:none; width:150px;'>Nama</td><td style='border:none;'>: $nama</td></tr>
                <tr><td style='border:none;'>NIM</td><td style='border:none;'>: $nim</td></tr>
                <tr><td style='border:none;'>Program Studi</td><td style='border:none;'>: $prodi</td></tr>
                <tr><td style='border:none;'>Tahun Akademik</td><td style='border:none;'>: $ta</td></tr>
                <tr><td style='border:none; vertical-align:top;'>Judul Proposal</td><td style='border:none;'>: " . v($vars, 'judul') . "</td></tr>
                <tr><td style='border:none;'>Waktu Ujian</td><td style='border:none;'>: " . htmlspecialchars(v($vars, 'waktu')) . "</td></tr>
            </table>";
            if (!empty($vars['penguji'])) {
                $html .= "<p><b>Susunan Penguji:</b></p>" . $vars['penguji'];
            }
            break;

        case 'Permohonan SK ujian seminar hasil':
            $html .= 'SURAT KEPUTUSAN<br>PELAKSANAAN UJIAN SEMINAR HASIL</p>';
            $html .= "<p style='text-align:center;'>Nomor: $nomor_surat</p>";
            $html .= '<p>Dekan Fakultas Sains dan Ilmu Komputer Universitas Muhammadiyah Gorontalo, dengan ini menetapkan:</p>';
            $html .= "<table style='margin:15px 0; border:none;'>
                <tr><td style='border:none; width:150px;'>Nama</td><td style='border:none;'>: $nama</td></tr>
                <tr><td style='border:none;'>NIM</td><td style='border:none;'>: $nim</td></tr>
                <tr><td style='border:none;'>Program Studi</td><td style='border:none;'>: $prodi</td></tr>
                <tr><td style='border:none; vertical-align:top;'>Judul Skripsi</td><td style='border:none;'>: " . v($vars, 'judul') . "</td></tr>
            </table>";
            if (!empty($vars['catatan'])) {
                $html .= "<p><b>Catatan:</b></p>" . $vars['catatan'];
            }
            break;

        case 'Permohonan SK ujian skripsi':
            $html .= 'SURAT KEPUTUSAN<br>PELAKSANAAN UJIAN SKRIPSI</p>';
            $html .= "<p style='text-align:center;'>Nomor: $nomor_surat</p>";
            $html .= '<p>Dekan Fakultas Sains dan Ilmu Komputer Universitas Muhammadiyah Gorontalo, dengan ini menetapkan:</p>';
            $html .= "<table style='margin:15px 0; border:none;'>
                <tr><td style='border:none; width:150px;'>Nama</td><td style='border:none;'>: $nama</td></tr>
                <tr><td style='border:none;'>NIM</td><td style='border:none;'>: $nim</td></tr>
                <tr><td style='border:none;'>Program Studi</td><td style='border:none;'>: $prodi</td></tr>
                <tr><td style='border:none; vertical-align:top;'>Judul Skripsi</td><td style='border:none;'>: " . v($vars, 'judul') . "</td></tr>
                <tr><td style='border:none;'>Tgl. Kelulusan</td><td style='border:none;'>: " . htmlspecialchars(v($vars, 'tgl_lulus')) . "</td></tr>
            </table>";
            break;

        case 'Surat permintaan data awal penelitian':
            $html .= 'SURAT PENGANTAR PENELITIAN</p>';
            $html .= "<p style='text-align:center;'>Nomor: $nomor_surat</p>";
            $html .= "<p>Yang bertanda tangan di bawah ini, Dekan Fakultas Sains dan Ilmu Komputer Universitas Muhammadiyah Gorontalo, menerangkan bahwa:</p>";
            $html .= "<table style='margin:15px 0; border:none;'>
                <tr><td style='border:none; width:150px;'>Nama</td><td style='border:none;'>: $nama</td></tr>
                <tr><td style='border:none;'>NIM</td><td style='border:none;'>: $nim</td></tr>
                <tr><td style='border:none;'>Program Studi</td><td style='border:none;'>: $prodi</td></tr>
                <tr><td style='border:none;'>Lokasi Penelitian</td><td style='border:none;'>: " . htmlspecialchars(v($vars, 'lokasi')) . "</td></tr>
            </table>";
            $html .= "<p>Adalah benar mahasiswa pada fakultas kami yang sedang melaksanakan penelitian untuk pengambilan data awal. Adapun data yang diminta:</p>";
            $html .= "<div style='margin-left:20px;'>" . v($vars, 'data') . "</div>";
            break;

        case 'Surat permintaan data hasil penelitian':
            $html .= 'SURAT PENGANTAR PENELITIAN (HASIL)</p>';
            $html .= "<p style='text-align:center;'>Nomor: $nomor_surat</p>";
            $html .= "<p>Yang bertanda tangan di bawah ini menerangkan bahwa:</p>";
            $html .= "<table style='margin:15px 0; border:none;'>
                <tr><td style='border:none; width:150px;'>Nama</td><td style='border:none;'>: $nama</td></tr>
                <tr><td style='border:none;'>NIM</td><td style='border:none;'>: $nim</td></tr>
                <tr><td style='border:none;'>Program Studi</td><td style='border:none;'>: $prodi</td></tr>
                <tr><td style='border:none;'>Lokasi Penelitian</td><td style='border:none;'>: " . htmlspecialchars(v($vars, 'lokasi')) . "</td></tr>
                <tr><td style='border:none;'>Instansi Tujuan</td><td style='border:none;'>: " . htmlspecialchars(v($vars, 'instansi')) . "</td></tr>
            </table>";
            $html .= "<p>Adalah benar mahasiswa pada fakultas kami yang memerlukan data hasil penelitian. Mohon kiranya dapat diberikan bantuan.</p>";
            break;

        case 'Persetujuan waktu ujian':
            $html .= 'SURAT PERSETUJUAN WAKTU UJIAN</p>';
            $html .= "<p style='text-align:center;'>Nomor: $nomor_surat</p>";
            $html .= "<p>Dengan ini disampaikan bahwa ujian untuk:</p>";
            $html .= "<table style='margin:15px 0; border:none;'>
                <tr><td style='border:none; width:150px;'>Nama</td><td style='border:none;'>: $nama</td></tr>
                <tr><td style='border:none;'>NIM</td><td style='border:none;'>: $nim</td></tr>
                <tr><td style='border:none;'>Hari/Tanggal</td><td style='border:none;'>: " . htmlspecialchars(v($vars, 'tanggal')) . "</td></tr>
                <tr><td style='border:none;'>Jam</td><td style='border:none;'>: " . htmlspecialchars(v($vars, 'jam')) . "</td></tr>
                <tr><td style='border:none;'>Ruangan</td><td style='border:none;'>: " . htmlspecialchars(v($vars, 'ruangan')) . "</td></tr>
            </table>";
            $html .= "<p>Telah disetujui untuk dilaksanakan sesuai jadwal di atas.</p>";
            break;

        case 'Undangan ujian':
            $html .= 'UNDANGAN UJIAN</p>';
            $html .= "<p style='text-align:center;'>Nomor: $nomor_surat</p>";
            $html .= "<p>Dengan hormat, mengundang Bapak/Ibu untuk hadir sebagai penguji pada ujian:</p>";
            $html .= "<table style='margin:15px 0; border:none;'>
                <tr><td style='border:none; width:150px;'>Nama Mahasiswa</td><td style='border:none;'>: $nama</td></tr>
                <tr><td style='border:none;'>NIM</td><td style='border:none;'>: $nim</td></tr>
                <tr><td style='border:none; vertical-align:top;'>Judul</td><td style='border:none;'>: " . v($vars, 'judul') . "</td></tr>
            </table>";
            break;

        default:
            // Fallback: tampilkan semua variabel dalam format tabel
            $html .= strtoupper($jenis) . '</p>';
            $html .= "<p style='text-align:center;'>Nomor: $nomor_surat</p>";
            $html .= "<table style='margin:15px 0; border:none;'>";
            foreach ($vars as $key => $val) {
                $label = ucfirst(str_replace('_', ' ', $key));
                $html .= "<tr><td style='border:none; width:150px; vertical-align:top;'>$label</td><td style='border:none;'>: $val</td></tr>";
            }
            $html .= "</table>";
            break;
    }

    return $html;
}

$tanggal_surat = v($vars, 'tanggal_surat', date('d F Y'));
$surat_content = renderSuratContent($data['jenis_surat'], $vars, $data);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Surat - <?= htmlspecialchars($data['jenis_surat']) ?></title>
    <style>
        @page { size: A4; margin: 0; }
        body { font-family: 'Times New Roman', serif; line-height: 1.8; color: #000; background: #f0f0f0; margin: 0; padding: 0; }
        .page { width: 210mm; min-height: 297mm; padding: 20mm 25mm; margin: 10mm auto; background: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); box-sizing: border-box; }
        .kop-surat { text-align: center; border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 30px; }
        .kop-surat h2, .kop-surat h3, .kop-surat p { margin: 0; }
        .content { text-align: justify; min-height: 300px; font-size: 12pt; }
        .content table { border-collapse: collapse; }
        .content table td { padding: 3px 8px; }
        .footer { margin-top: 40px; float: right; text-align: center; width: 300px; }
        .no-print { background: #2c3e50; padding: 12px; text-align: center; position: sticky; top: 0; z-index: 100; }
        .no-print button, .no-print a { padding: 8px 16px; cursor: pointer; border: none; border-radius: 4px; font-size: 13px; font-family: sans-serif; font-weight: 600; margin: 0 5px; text-decoration: none; display: inline-block; }
        .btn-print { background: #3498db; color: white; }
        .btn-download { background: #2ecc71; color: white; }
        .btn-back { background: #95a5a6; color: white; }
        @media print {
            body { background: none; }
            .page { margin: 0; box-shadow: none; width: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
        <?php if ($has_rtf): ?>
            <a href="cetak_surat.php?id=<?= $id ?>&download=rtf" class="btn-download">📄 Unduh RTF</a>
        <?php endif; ?>
        <button class="btn-back" onclick="history.back()">← Kembali</button>
    </div>

    <div class="page">
        <div class="kop-surat">
            <h2>UNIVERSITAS MUHAMMADIYAH GORONTALO</h2>
            <h3>FAKULTAS SAINS DAN ILMU KOMPUTER</h3>
            <p style="font-size: 11px;">Jl. Prof. Dr. Mansoer Pateda No. 247, Pentadio Timur, Gorontalo | Email: fsik@umgo.ac.id</p>
        </div>

        <div class="content">
            <?= $surat_content ?>
        </div>

        <div class="footer">
            <p>Gorontalo, <?= htmlspecialchars($tanggal_surat) ?></p>
            <p>Dekan,</p>
            <br><br><br>
            <p><b><u>Dr. Mohamad Ervandi, S.Pt., MP</u></b></p>
            <p style="margin-top:-10px; font-size:10pt;">NBM: 1 2 3 2 6 9 0</p>
        </div>
        <div style="clear:both;"></div>

        <div style="position:absolute; bottom:25mm; left:25mm; font-size:8pt; color:#999;">
            ID Validasi: <?= md5($data['id'] . $data['tgl_pengajuan']) ?> | Dicetak: <?= date('d/m/Y H:i') ?>
        </div>
    </div>
</body>
</html>
