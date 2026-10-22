CREATE DATABASE IF NOT EXISTS db_layanan_surat;
USE db_layanan_surat;

-- Table for user management
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL,
    role ENUM('admin', 'mahasiswa', 'dosen') NOT NULL,
    nomor_induk VARCHAR(50) DEFAULT NULL,
    prodi VARCHAR(100) DEFAULT NULL
);

-- Table for letter applications
CREATE TABLE IF NOT EXISTS permohonan_surat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    jenis_surat VARCHAR(255) NOT NULL,
    catatan_user TEXT,
    berkas_pendukung VARCHAR(255),
    status ENUM('Pending', 'Diproses', 'Selesai', 'Ditolak') DEFAULT 'Pending',
    tgl_pengajuan TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    surat_jadi VARCHAR(255),
    data_surat TEXT DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table for notifications
CREATE TABLE IF NOT EXISTS notifikasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    pesan TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table for letter templates
CREATE TABLE IF NOT EXISTS templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jenis_surat VARCHAR(255) NOT NULL UNIQUE,
    isi_template TEXT NOT NULL
);

-- Default users (Password for all is '123')
-- Hash di bawah ini sudah diverifikasi cocok dengan password '123' (bcrypt).
-- Admin hanya bisa login lewat panel-admin.php, mahasiswa/dosen lewat login.php.
INSERT INTO users (username, password, nama_lengkap, role) VALUES
('admin', '$2y$10$wl2yol7IdVzL7MrRPOSSeuybKROzmA54d.PcMWQdON0qbMIRQ1Zsa', 'Administrator', 'admin'),
('mahasiswa', '$2y$10$wl2yol7IdVzL7MrRPOSSeuybKROzmA54d.PcMWQdON0qbMIRQ1Zsa', 'Mahasiswa User', 'mahasiswa'),
('dosen', '$2y$10$wl2yol7IdVzL7MrRPOSSeuybKROzmA54d.PcMWQdON0qbMIRQ1Zsa', 'Dosen User', 'dosen');

-- Tabel Manajemen Jenis Surat & Template
CREATE TABLE IF NOT EXISTS master_surat_jenis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_surat VARCHAR(255) NOT NULL,
    prodi VARCHAR(100) DEFAULT 'Umum',
    format_nomor VARCHAR(255) NOT NULL,
    file_template VARCHAR(255) NOT NULL,
    variabel_tambahan TEXT,
    require_berkas TINYINT(1) DEFAULT 0,
    info_berkas VARCHAR(255) DEFAULT NULL,
    updated_by VARCHAR(100),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Data Awal Contoh (Pastikan file .docx tersedia di folder templates/)
INSERT INTO master_surat_jenis (nama_surat, format_nomor, file_template, variabel_tambahan, updated_by) 
VALUES ('Permohonan SK ujian proposal', '{no}/FPSI-UMGO/SK-PROP/{yyyy}', 'template_permohonan_sk_ujian_proposal.docx', '${waktu},${penguji}', 'admin'),
       ('Keterangan Aktif Kuliah', '{no}/FPSI-UMGO/KET.AK/{yyyy}', 'template_keterangan_aktif_kuliah.docx', '${ta}', 'admin');