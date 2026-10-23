<?php
include 'koneksi.php';

$error_msg = '';

// === Rate-limit login (anti brute-force) ===
$LOCK_MAX = 5;
$LOCK_WINDOW = 900; // 15 menit

if (!isset($_SESSION['login_fail'])) {
    $_SESSION['login_fail'] = ['count' => 0, 'first' => time(), 'locked_until' => 0];
}
$fail = &$_SESSION['login_fail'];

// Jangan reset saat masih terkunci; reset hanya bila jendela waktu sudah lewat.
if (time() >= (int)($fail['locked_until'] ?? 0) && (time() - (int)$fail['first'] > $LOCK_WINDOW)) {
    $fail = ['count' => 0, 'first' => time(), 'locked_until' => 0];
}

if (isset($_POST['login'])) {
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        $error_msg = "Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.";
    } elseif (time() < (int)($fail['locked_until'] ?? 0)) {
        $sisa = (int)(($fail['locked_until'] - time()) / 60) + 1;
        $error_msg = "Terlalu banyak percobaan gagal. Coba lagi dalam ±{$sisa} menit.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = isset($_POST['role']) ? $_POST['role'] : '';

        $valid = false;

        // Login publik hanya untuk mahasiswa/dosen. Admin wajib via panel-admin.php
        if (!in_array($role, ['mahasiswa', 'dosen'], true)) {
            $error_msg = "Pilih role Mahasiswa atau Dosen. Akun admin wajib masuk lewat Panel Admin.";
        } else {
            $stmt = mysqli_prepare($koneksi, "SELECT * FROM users WHERE username=? AND role=?");
            if (!$stmt) {
                $error_msg = "Sistem login sedang bermasalah. Silakan coba lagi nanti.";
            } else {
                mysqli_stmt_bind_param($stmt, "ss", $username, $role);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);

                if ($result && ($row = mysqli_fetch_assoc($result))) {
                    if (password_verify($password, $row['password'])) {
                        $valid = true;

                        $_SESSION['login_fail'] = ['count' => 0, 'first' => time(), 'locked_until' => 0];
                        unset($fail);

                        session_regenerate_id(true);
                        $_SESSION['login'] = true;
                        $_SESSION['user_id'] = (int)$row['id'];
                        $_SESSION['nama'] = $row['nama_lengkap'];
                        $_SESSION['role'] = $row['role'];
                        $_SESSION['last_activity'] = time();
                        $_SESSION['last_regen'] = time();

                        if ($row['role'] == 'mahasiswa') {
                            header("Location: dashboard_mhs.php");
                        } else {
                            header("Location: dashboard_dosen.php");
                        }
                        exit;
                    }
                }
                if ($error_msg === '') {
                    $error_msg = "Username, password, atau role tidak cocok. Periksa kembali role yang dipilih.";
                }
            }
        }

        if (!$valid) {
            // Perlambat percobaan otomatis
            usleep(random_int(300000, 700000));
            $fail['count']++;
            if ($fail['count'] === 1) {
                $fail['first'] = time();
            }
            if ($fail['count'] >= $LOCK_MAX) {
                $fail['locked_until'] = time() + $LOCK_WINDOW;
                $fail['first'] = time();
                $error_msg = "Terlalu banyak percobaan gagal. Akun dikunci sementara ±15 menit.";
            }
        }
    }
}
unset($fail);
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | E-Surat</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: flex-end; /* Menggeser konten ke kanan */
            align-items: center;
            height: 100vh;
            margin: 0;
            color: #1c1e21;
            position: relative; /* Diperlukan untuk z-index pada pseudo-element */
        }
        body::before {
            content: '';
            position: fixed; /* Mengisi seluruh viewport */
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('gambar/background.png'); /* Mengambil gambar dari folder gambar */
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            z-index: -1; /* Memastikan latar belakang berada di belakang konten */
        }
        .login-box {
            background: white; /* Warna putih solid */
            backdrop-filter: blur(60px); /* Efek blur pada kotak ditingkatkan lagi */
            -webkit-backdrop-filter: blur(60px); /* Dukungan untuk Safari */
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            width: 100%;
            max-width: 360px;
            margin-right: 80px; /* Memberi jarak dari pinggir kanan */
        }
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
    <link rel="stylesheet" href="responsive.css">
</head>
<body class="auth-page">
<div class="login-box">
    <div style="text-align: center; margin-bottom: 20px;">
        <img src="logo/logo.png" alt="Logo" style="max-height: 160px;">
    </div>
    <h3 style="margin-bottom: 0;">Layanan E-Surat</h3>
    <h3 style="margin-top: 5px;">Fakultas Sains dan Ilmu Komputer</h3>
    <?php if($error_msg !== '') echo "<div class='error-msg'>" . htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8') . "</div>"; ?>
    <form method="POST" action="">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="text" name="username" placeholder="Username" required autocomplete="username">
        <input type="password" name="password" placeholder="Password" required autocomplete="current-password">
        <select name="role" required>
            <option value="" disabled selected>Pilih Role...</option>
            <option value="mahasiswa">Mahasiswa</option>
            <option value="dosen">Dosen</option>
        </select>
        <button type="submit" name="login">Masuk ke Sistem</button>
    </form>
    <div class="footer-links">
        Belum punya akun? <a href="register.php">Daftar Sekarang</a>
    </div>
    <div class="footer-links" style="margin-top: 12px;">
        <span style="font-size: 13px;">Admin? <a href="panel-admin.php">Masuk lewat Panel Admin</a></span>
    </div>
    
</div>
</body>
</html>