<?php
include 'koneksi.php';
require 'vendor/autoload.php';

// Jangan tampilkan error ke browser di production; catat ke log saja
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Pastikan hanya admin yang bisa mengakses proses ini (wajib via panel-admin)
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'admin' || (($_SESSION['login_via'] ?? '') !== 'panel-admin')) {
    header("Location: panel-admin.php");
    exit;
}

// TENTUKAN NAMA FILE UTAMA ANDA DI SINI
// Ganti 'index.php' atau 'master_surat.php' sesuai nama file tempat tabel jenis surat Anda berada
$halaman_utama = "admin_master_surat.php"; 

/**
 * Fungsi untuk mendeteksi semua variabel di file .docx
 */
function detect_extra_vars($file_path) {
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

    // Jika file RTF, kita bisa baca manual tanpa Zip
    if ($extension === 'rtf') {
        $content = file_get_contents($file_path);
        // Regex untuk mencari pola ${...}
        preg_match_all('/\$\{[^}]+\}/', $content, $matches);
        return implode(',', array_unique($matches[0]));
    }

    // Jika file DOCX, tetap butuh ZipArchive
    if ($extension === 'docx') {
        if (!class_exists('ZipArchive')) {
            return "Error: Ekstensi PHP Zip tidak aktif. Gunakan format .rtf sebagai alternatif.";
        }

    try {
        $templateProcessor = new \PhpOffice\PhpWord\TemplateProcessor($file_path);
        $all_vars = $templateProcessor->getVariables(); // Mengambil semua ${var}
        
        // Format menjadi ${nama} dan gabungkan dengan koma
        $formatted = array_map(function($v) { return '${' . $v . '}'; }, $all_vars);
        return implode(',', $formatted);
    } catch (Exception $e) {
        return '';
    }
    }
    return '';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF: tolak jika token tidak valid (form sudah ditambah hidden input, tidak mengubah tampilan)
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        die("Token keamanan tidak valid. Silakan muat ulang halaman.");
    }
    $action = $_POST['action'] ?? '';
    $allowed_prodi = ['Umum','Dosen','Sistem Informasi','Geografi','Ilmu Komputer','Bisnis Digital','Teknik Sipil','Akuakultur','Agribisnis','Peternakan'];

    // Helper lokal: sanitasi + validasi file docx tanpa mengubah tampilan
    $validate_docx = function($file_arr) {
        if (!isset($file_arr) || ($file_arr['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !is_uploaded_file($file_arr['tmp_name'] ?? '')) {
            return [false, 'File belum dipilih / gagal diunggah.'];
        }
        if (($file_arr['size'] ?? 0) > 10 * 1024 * 1024) {
            return [false, 'Ukuran template maksimal 10MB.'];
        }
        $ext = strtolower(pathinfo($file_arr['name'], PATHINFO_EXTENSION));
        if ($ext !== 'docx') {
            return [false, 'Hanya file .docx yang diizinkan.'];
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file_arr['tmp_name']) : '';
        if ($finfo) finfo_close($finfo);
        // docx = zip
        if (!in_array($mime, ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'], true)) {
            return [false, 'Isi file bukan dokumen Word yang valid.'];
        }
        return [true, ''];
    };
    $safe_filename = function($orig) {
        $base = basename($orig);
        // Hanya huruf, angka, spasi, _ - . ; selain itu jadi _
        $base = preg_replace('/[^A-Za-z0-9 _\-\.]/', '_', $base);
        $base = preg_replace('/_+/', '_', $base);
        if (strtolower(substr($base, -5)) !== '.docx') { $base .= '.docx'; }
        // Prefix waktu agar tidak overwrite template lain
        return time() . '_' . $base;
    };

    // 1. PENANGANAN AKSI TAMBAH DATA
    if ($action == 'tambah_jenis') {
        $nama_surat = mb_substr(trim(strip_tags($_POST['nama_surat'] ?? '')), 0, 200);
        $prodi = $_POST['prodi'] ?? 'Umum';
        if (!in_array($prodi, $allowed_prodi, true)) { $prodi = 'Umum'; }
        $require_berkas = isset($_POST['require_berkas']) ? 1 : 0;
        $info_berkas = mb_substr(trim(strip_tags($_POST['info_berkas'] ?? '')), 0, 255);
        $updated_by = mb_substr((string)($_SESSION['nama'] ?? 'Admin'), 0, 100);
        if ($nama_surat === '') { die("Nama jenis surat wajib diisi."); }

        [$ok, $msg] = $validate_docx($_FILES['file_template'] ?? null);
        if (!$ok) { die(esc($msg)); }
        $file_name = $safe_filename($_FILES['file_template']['name']);
        $file_tmp = $_FILES['file_template']['tmp_name'];
        
        // Pastikan folder "templates" ada, jika belum otomatis dibuat
        if (!is_dir('templates')) {
            mkdir('templates', 0755, true);
        }

        // Pindahkan ke folder templates
        if (move_uploaded_file($file_tmp, "templates/" . $file_name)) {
            // OTOMATIS deteksi variabel dari file yang baru diupload
            $path = "templates/" . $file_name;
            $variabel_tambahan = detect_extra_vars($path);

            if (strpos($variabel_tambahan, 'Error:') === 0) {
                @unlink($path);
                die(esc($variabel_tambahan));
            }

            // Jalankan Query Insert ke Database (prepared)
            // format_nomor wajib (NOT NULL) di skema, isi string kosong agar tidak gagal di MySQL strict mode
            $stmt = mysqli_prepare($koneksi, "INSERT INTO master_surat_jenis (nama_surat, prodi, variabel_tambahan, file_template, format_nomor, require_berkas, info_berkas, updated_by, updated_at) VALUES (?, ?, ?, ?, '', ?, ?, ?, NOW())");
            if (!$stmt) {
                @unlink($path);
                die("Gagal menyiapkan data. Silakan coba lagi.");
            }
            mysqli_stmt_bind_param($stmt, "ssssiss", $nama_surat, $prodi, $variabel_tambahan, $file_name, $require_berkas, $info_berkas, $updated_by);
            
            if (mysqli_stmt_execute($stmt)) {
                // Berhasil, kembalikan ke halaman utama
                header("Location: " . $halaman_utama . "?status=sukses_tambah");
                exit;
            } else {
                @unlink($path);
                die("Gagal menyimpan data. Silakan coba lagi.");
            }
        } else {
            die("Gagal mengunggah file template. Pastikan folder 'templates' sudah ada dan memiliki izin akses write.");
        }
    }

    // 2. PENANGANAN AKSI GANTI TEMPLATE
    elseif ($action == 'upload_template') {
        $id_jenis = (int)($_POST['id_jenis'] ?? 0);
        $updated_by = mb_substr((string)($_SESSION['nama'] ?? 'Admin'), 0, 100);
        if ($id_jenis <= 0) { die("ID tidak valid."); }

        [$ok, $msg] = $validate_docx($_FILES['file_rtf'] ?? null);
        if (!$ok) { die(esc($msg)); }
        $file_name = $safe_filename($_FILES['file_rtf']['name']);
        $file_tmp = $_FILES['file_rtf']['tmp_name'];

        // Pastikan folder templates ada
        if (!is_dir('templates')) {
            mkdir('templates', 0755, true);
        }

        if (move_uploaded_file($file_tmp, "templates/" . $file_name)) {
            // OTOMATIS deteksi variabel dari file template baru
            $path = "templates/" . $file_name;
            $variabel_tambahan = detect_extra_vars($path);

            if (strpos($variabel_tambahan, 'Error:') === 0) {
                @unlink($path);
                die(esc($variabel_tambahan));
            }

            // Jalankan Query Update berdasarkan ID Jenis Surat (prepared)
            $stmt = mysqli_prepare($koneksi, "UPDATE master_surat_jenis SET file_template = ?, variabel_tambahan = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
            if (!$stmt) {
                @unlink($path);
                die("Gagal menyiapkan data. Silakan coba lagi.");
            }
            mysqli_stmt_bind_param($stmt, "sssi", $file_name, $variabel_tambahan, $updated_by, $id_jenis);
            
            if (mysqli_stmt_execute($stmt)) {
                // Berhasil, kembalikan ke halaman utama
                header("Location: " . $halaman_utama . "?status=sukses_ganti");
                exit;
            } else {
                @unlink($path);
                die("Gagal memperbarui template. Silakan coba lagi.");
            }
        } else {
            die("Gagal mengunggah file template baru.");
        }
    }

    // 3. PENANGANAN AKSI EDIT DATA (Nama & Format)
    elseif ($action == 'edit_jenis') {
        $id = (int)($_POST['id'] ?? 0);
        $nama_surat = mb_substr(trim(strip_tags($_POST['nama_surat'] ?? '')), 0, 200);
        $prodi = $_POST['prodi'] ?? 'Umum';
        if (!in_array($prodi, $allowed_prodi, true)) { $prodi = 'Umum'; }
        $require_berkas = isset($_POST['require_berkas']) ? 1 : 0;
        $info_berkas = mb_substr(trim(strip_tags($_POST['info_berkas'] ?? '')), 0, 255);
        $variabel_tambahan = mb_substr(trim(strip_tags($_POST['variabel_tambahan'] ?? '')), 0, 1000);
        $updated_by = mb_substr((string)($_SESSION['nama'] ?? 'Admin'), 0, 100);
        if ($id <= 0 || $nama_surat === '') { die("Data tidak valid."); }

        $stmt = mysqli_prepare($koneksi, "UPDATE master_surat_jenis SET nama_surat=?, prodi=?, require_berkas=?, info_berkas=?, variabel_tambahan=?, updated_by=?, updated_at=NOW() WHERE id=?");
        if (!$stmt) {
            die("Gagal menyiapkan data. Silakan coba lagi.");
        }
        mysqli_stmt_bind_param($stmt, "ssisssi", $nama_surat, $prodi, $require_berkas, $info_berkas, $variabel_tambahan, $updated_by, $id);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: " . $halaman_utama . "?status=sukses_edit");
            exit;
        } else {
            die("Gagal memperbarui data. Silakan coba lagi.");
        }
    }

    // 4. PENANGANAN AKSI HAPUS DATA
    elseif ($action == 'hapus_jenis') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { die("ID tidak valid."); }
        
        $stmt = mysqli_prepare($koneksi, "DELETE FROM master_surat_jenis WHERE id = ?");
        if (!$stmt) {
            die("Gagal menyiapkan data. Silakan coba lagi.");
        }
        mysqli_stmt_bind_param($stmt, "i", $id);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: " . $halaman_utama . "?status=sukses_hapus");
            exit;
        } else {
            die("Gagal menghapus data. Silakan coba lagi.");
        }
    }
}