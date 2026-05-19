<?php
include 'koneksi.php';

if (isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    // Validasi apakah username sudah ada
    $stmt_cek = mysqli_prepare($koneksi, "SELECT id FROM users WHERE username = ?");
    mysqli_stmt_bind_param($stmt_cek, "s", $username);
    mysqli_stmt_execute($stmt_cek);
    mysqli_stmt_store_result($stmt_cek);

    if (mysqli_stmt_num_rows($stmt_cek) > 0) {
        $error = "Username sudah terdaftar! Gunakan username lain.";
    } else {
        // Insert user baru
        $nomor_induk = trim($_POST['nomor_induk']);
        // Pastikan prodi hanya diisi jika role adalah mahasiswa
        $prodi_val = ($role == 'mahasiswa') ? $_POST['prodi'] : NULL;
        $stmt_ins = mysqli_prepare($koneksi, "INSERT INTO users (username, password, nama_lengkap, role, nomor_induk, prodi) VALUES (?, ?, ?, ?, ?, ?)");
        
        if ($stmt_ins) {
            mysqli_stmt_bind_param($stmt_ins, "ssssss", $username, $password, $nama_lengkap, $role, $nomor_induk, $prodi_val);
            
            if (mysqli_stmt_execute($stmt_ins)) {
                echo "<script>alert('Akun berhasil dibuat! Silakan login.'); window.location='login.php';</script>";
                exit;
            } else {
                $error = "Gagal menyimpan data: " . mysqli_stmt_error($stmt_ins);
            }
        } else {
            $error = "Kesalahan pada sistem database: " . mysqli_error($koneksi);
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Daftar Akun Baru</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .register-box { background: white; padding: 30px; border-radius: 8px; box-shadow: 0px 0px 10px rgba(0,0,0,0.1); width: 350px; }
        input, select, button { width: 100%; padding: 10px; margin: 10px 0; box-sizing: border-box; }
        button { background: #28a745; color: white; border: none; cursor: pointer; font-weight: bold; }
        button:hover { background: #218838; }
    </style>
</head>
<body>
<div class="register-box">
    <h3 style="text-align:center;">Daftar Akun Baru</h3>
    <?php if(isset($error)) echo "<p style='color:red; font-size:13px;'>$error</p>"; ?>
    <form method="POST" action="" id="regForm">
        <input type="text" name="username" placeholder="Username" required>
        <input type="text" name="nama_lengkap" placeholder="Nama Lengkap" required>
        <input type="text" name="nomor_induk" id="nomor_induk" placeholder="NIM / NIDN" required>
        <input type="password" name="password" placeholder="Password" required>
        <label style="font-size: 14px;">Mendaftar sebagai:</label>
        <select name="role" id="roleSelect" required onchange="toggleProdi()">
            <option value="mahasiswa">Mahasiswa</option>
            <option value="dosen">Dosen</option>
            <option value="admin">Admin</option>
        </select>
        
        <div id="prodiSection">
            <label style="font-size: 14px;">Program Studi:</label>
            <select name="prodi" id="prodiSelect">
                <option value="" disabled selected>-- Pilih Program Studi --</option>
                <option value="Sistem Informasi">Sistem Informasi</option>
                <option value="Geografi">Geografi</option>
                <option value="Ilmu Komputer">Ilmu Komputer</option>
                <option value="Bisnis Digital">Bisnis Digital</option>
                <option value="Teknik Sipil">Teknik Sipil</option>
                <option value="Akuakultur">Akuakultur</option>
                <option value="Agribisnis">Agribisnis</option>
                <option value="Peternakan">Peternakan</option>
            </select>
        </div>

        <button type="submit" name="register">Daftar Sekarang</button>
    </form>
    <p style="text-align:center; font-size:14px;">Sudah punya akun? <a href="login.php">Login di sini</a></p>
</div>

<script>
function toggleProdi() {
    const role = document.getElementById('roleSelect').value;
    const prodiSection = document.getElementById('prodiSection');
    const prodiSelect = document.getElementById('prodiSelect');
    const niInput = document.getElementById('nomor_induk');

    prodiSection.style.display = (role === 'mahasiswa') ? 'block' : 'none';
    prodiSelect.required = (role === 'mahasiswa');
    niInput.placeholder = (role === 'mahasiswa') ? 'NIM (Nomor Induk Mahasiswa)' : (role === 'dosen' ? 'NIDN (Nomor Induk Dosen)' : 'Nomor Induk');
}
toggleProdi();
</script>
</body>
</html>