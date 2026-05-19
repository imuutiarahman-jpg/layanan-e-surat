<?php
include 'koneksi.php';

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    $stmt = mysqli_prepare($koneksi, "SELECT * FROM users WHERE username=? AND role=?");
    mysqli_stmt_bind_param($stmt, "ss", $username, $role);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['login'] = true;
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['nama'] = $row['nama_lengkap'];
            $_SESSION['role'] = $row['role'];

            if ($row['role'] == 'admin') {
                header("Location: dashboard_admin.php");
            } elseif ($row['role'] == 'mahasiswa') {
                header("Location: dashboard_mhs.php");
            } elseif ($row['role'] == 'dosen') {
                header("Location: dashboard_dosen.php");
            }
            exit;
        }
    }
    $error = true;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login | E-Surat</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; color: #1c1e21; }
        .login-box { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); width: 100%; max-width: 360px; }
        h3 { margin-bottom: 24px; font-weight: 600; color: #1c3faa; text-align: center; }
        input, select { width: 100%; padding: 12px; margin: 8px 0; border: 1px solid #dddfe2; border-radius: 6px; box-sizing: border-box; font-size: 14px; transition: border 0.2s; }
        input:focus { border-color: #1c3faa; outline: none; box-shadow: 0 0 0 2px rgba(28, 63, 170, 0.1); }
        button { width: 100%; padding: 12px; margin-top: 16px; background: #1c3faa; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; transition: background 0.3s; }
        button:hover { background: #153084; }
        .error-msg { background: #ffebe9; color: #d93025; padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 16px; border: 1px solid rgba(217, 48, 37, 0.2); text-align: center; }
        .footer-links { text-align: center; margin-top: 24px; font-size: 14px; color: #606770; }
        .footer-links a { color: #1c3faa; text-decoration: none; font-weight: 600; }
        .demo-hint { font-size: 11px; text-align: center; color: #8a8d91; margin-top: 20px; border-top: 1px solid #ebedf0; padding-top: 15px; }
    </style>
</head>
<body>
<div class="login-box">
    <h3>Layanan E-Surat<h3>
    <h3>Fakultas Sains dan Ilmu Komputer</h3>
    <?php if(isset($error)) echo "<div class='error-msg'>Username atau Password salah!</div>"; ?>
    <form method="POST" action="">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <select name="role" required>
            <option value="" disabled selected>Pilih Role...</option>
            <option value="mahasiswa">Mahasiswa</option>
            <option value="dosen">Dosen</option>
            <option value="admin">Admin</option>
        </select>
        <button type="submit" name="login">Masuk ke Sistem</button>
    </form>
    <div class="footer-links">
        Belum punya akun? <a href="register.php">Daftar Sekarang</a>
    </div>
    
</div>
</body>
</html>