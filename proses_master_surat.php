<?php
include 'koneksi.php';
require 'vendor/autoload.php';

// Mengaktifkan error reporting untuk mempermudah debugging jika terjadi kendala lagi
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Pastikan hanya admin yang bisa mengakses proses ini
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
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
    $action = $_POST['action'];

    // 1. PENANGANAN AKSI TAMBAH DATA
    if ($action == 'tambah_jenis') {
        $nama_surat = mysqli_real_escape_string($koneksi, $_POST['nama_surat']);
        $prodi = mysqli_real_escape_string($koneksi, $_POST['prodi']);
        $require_berkas = isset($_POST['require_berkas']) ? 1 : 0;
        $info_berkas = mysqli_real_escape_string($koneksi, $_POST['info_berkas']);
        $updated_by = $_SESSION['nama'] ?? 'Admin'; 

        // Logika upload file template .docx
        $file_name = $_FILES['file_template']['name'];
        $file_tmp = $_FILES['file_template']['tmp_name'];
        
        // Pastikan folder "templates" ada, jika belum otomatis dibuat
        if (!is_dir('templates')) {
            mkdir('templates', 0777, true);
        }

        // Pindahkan ke folder templates
        if (move_uploaded_file($file_tmp, "templates/" . $file_name)) {
            // OTOMATIS deteksi variabel dari file yang baru diupload
            $path = "templates/" . $file_name;
            $variabel_tambahan = detect_extra_vars($path);

            if (strpos($variabel_tambahan, 'Error:') === 0) {
                echo $variabel_tambahan;
                exit;
            }

            // Jalankan Query Insert ke Database
            $query = "INSERT INTO master_surat_jenis (nama_surat, prodi, variabel_tambahan, file_template, require_berkas, info_berkas, updated_by, updated_at) 
                      VALUES ('$nama_surat', '$prodi', '$variabel_tambahan', '$file_name', '$require_berkas', '$info_berkas', '$updated_by', NOW())";
            
            if (mysqli_query($koneksi, $query)) {
                // Berhasil, kembalikan ke halaman utama
                header("Location: " . $halaman_utama . "?status=sukses_tambah");
                exit;
            } else {
                echo "Gagal menyimpan data ke database: " . mysqli_error($koneksi);
            }
        } else {
            echo "Gagal mengunggah file template. Pastikan folder 'templates' sudah ada dan memiliki izin akses write.";
        }
    }

    // 2. PENANGANAN AKSI GANTI TEMPLATE
    elseif ($action == 'upload_template') {
        $id_jenis = mysqli_real_escape_string($koneksi, $_POST['id_jenis']);
        $updated_by = $_SESSION['nama'] ?? 'Admin';

        // Logika upload file template baru (menggunakan name="file_rtf" sesuai dengan modal HTML Anda)
        $file_name = $_FILES['file_rtf']['name'];
        $file_tmp = $_FILES['file_rtf']['tmp_name'];

        // Pastikan folder templates ada
        if (!is_dir('templates')) {
            mkdir('templates', 0777, true);
        }

        if (move_uploaded_file($file_tmp, "templates/" . $file_name)) {
            // OTOMATIS deteksi variabel dari file template baru
            $path = "templates/" . $file_name;
            $variabel_tambahan = detect_extra_vars($path);

            if (strpos($variabel_tambahan, 'Error:') === 0) {
                echo $variabel_tambahan;
                exit;
            }

            // Jalankan Query Update berdasarkan ID Jenis Surat
            $query = "UPDATE master_surat_jenis 
                      SET file_template = '$file_name', variabel_tambahan = '$variabel_tambahan', updated_by = '$updated_by', updated_at = NOW() 
                      WHERE id = '$id_jenis'";
            
            if (mysqli_query($koneksi, $query)) {
                // Berhasil, kembalikan ke halaman utama
                header("Location: " . $halaman_utama . "?status=sukses_ganti");
                exit;
            } else {
                echo "Gagal memperbarui template di database: " . mysqli_error($koneksi);
            }
        } else {
            echo "Gagal mengunggah file template baru.";
        }
    }

    // 3. PENANGANAN AKSI EDIT DATA (Nama & Format)
    elseif ($action == 'edit_jenis') {
        $id = mysqli_real_escape_string($koneksi, $_POST['id']);
        $nama_surat = mysqli_real_escape_string($koneksi, $_POST['nama_surat']);
        $prodi = mysqli_real_escape_string($koneksi, $_POST['prodi']);
        $require_berkas = isset($_POST['require_berkas']) ? 1 : 0;
        $info_berkas = mysqli_real_escape_string($koneksi, $_POST['info_berkas']);
        $variabel_tambahan = mysqli_real_escape_string($koneksi, $_POST['variabel_tambahan']);
        $updated_by = $_SESSION['nama'] ?? 'Admin';

        $query = "UPDATE master_surat_jenis SET 
                  nama_surat = '$nama_surat', 
                  prodi = '$prodi',
                  require_berkas = '$require_berkas',
                  info_berkas = '$info_berkas',
                  variabel_tambahan = '$variabel_tambahan', 
                  updated_by = '$updated_by', 
                  updated_at = NOW() 
                  WHERE id = '$id'";

        if (mysqli_query($koneksi, $query)) {
            header("Location: " . $halaman_utama . "?status=sukses_edit");
            exit;
        } else {
            echo "Gagal memperbarui data: " . mysqli_error($koneksi);
        }
    }

    // 4. PENANGANAN AKSI HAPUS DATA
    elseif ($action == 'hapus_jenis') {
        $id = mysqli_real_escape_string($koneksi, $_POST['id']);
        
        $query = "DELETE FROM master_surat_jenis WHERE id = '$id'";

        if (mysqli_query($koneksi, $query)) {
            header("Location: " . $halaman_utama . "?status=sukses_hapus");
            exit;
        } else {
            echo "Gagal menghapus data: " . mysqli_error($koneksi);
        }
    }
}