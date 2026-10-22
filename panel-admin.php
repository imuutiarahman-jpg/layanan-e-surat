<?php
include 'koneksi.php';

// === Login khusus Admin (jalur terpisah, lebih aman) ===
// - Tanpa pilihan role (fixed role='admin'), tanpa link register
// - Rate-limit + lockout sementara untuk tahan brute-force
// - CSRF token + session_regenerate + pesan error generik

$LOCK_MAX = 5;        // maks gagal
$LOCK_WINDOW = 900;   // 15 menit

if (!isset($_SESSION['adm_fail'])) {
    $_SESSION['adm_fail'] = ['count' => 0, 'first' => time(), 'locked_until' => 0];
}
$fail = &$_SESSION['adm_fail'];

// Reset window jika sudah lewat
if (time() - $fail['first'] > $LOCK_WINDOW) {
    $fail = ['count' => 0, 'first' => time(), 'locked_until' => 0];
}

if (isset($_POST['login'])) {
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        $error = "Sesi kedaluwarsa. Muat ulang halaman.";
    } elseif (time() < ($fail['locked_until'] ?? 0)) {
        $sisa = (int)(($fail['locked_until'] - time()) / 60) + 1;
        $error = "Terlalu banyak percobaan gagal. Coba lagi dalam ±{$sisa} menit.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $ok = false;
        $stmt = mysqli_prepare($koneksi, "SELECT * FROM users WHERE username=? AND role='admin' LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                if (password_verify($password, $row['password'])) {
                    $ok = true;
                    // Reset counter gagal
                    $_SESSION['adm_fail'] = ['count' => 0, 'first' => time(), 'locked_until' => 0];
                    session_regenerate_id(true);
                    $_SESSION['login'] = true;
                    $_SESSION['login_via'] = 'panel-admin';
                    $_SESSION['user_id'] = (int)$row['id'];
                    $_SESSION['nama'] = $row['nama_lengkap'];
                    $_SESSION['role'] = 'admin';
                    header("Location: dashboard_admin.php");
                    exit;
                }
            }
        }
        if (!$ok) {
            // Perlambat brute-force tanpa mengubah tampilan (delay kecil acak)
            usleep(rand(400000, 900000));
            $fail['count']++;
            if ($fail['count'] == 1) { $fail['first'] = time(); }
            if ($fail['count'] >= $LOCK_MAX) {
                $fail['locked_until'] = time() + $LOCK_WINDOW;
                $error = "Terlalu banyak percobaan gagal. Akun dikunci sementara ±15 menit.";
            } else {
                $error = "Username atau Password salah!";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel Admin | E-Surat</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            height: 100vh;
            margin: 0;
            color: #1c1e21;
            position: relative;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(rgba(0, 0, 0, 0.55), rgba(0, 0, 0, 0.55)), url('gambar/background.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            z-index: -1;
        }
        .login-box {
            background: white;
            backdrop-filter: blur(60px);
            -webkit-backdrop-filter: blur(60px);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            width: 100%;
            max-width: 360px;
            margin-right: 80px;
        }
        h3 { margin-bottom: 24px; font-weight: 600; color: #1c3faa; text-align: center; }
        input { width: 100%; padding: 12px; margin: 8px 0; border: 1px solid #dddfe2; border-radius: 6px; box-sizing: border-box; font-size: 14px; transition: border 0.2s; }
        input:focus { border-color: #1c3faa; outline: none; box-shadow: 0 0 0 2px rgba(28, 63, 170, 0.1); }
        button { width: 100%; padding: 12px; margin-top: 16px; background: #111827; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; transition: background 0.3s; }
        button:hover { background: #000000; }
        .error-msg { background: #ffebe9; color: #d93025; padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 16px; border: 1px solid rgba(217, 48, 37, 0.2); text-align: center; }
        .footer-links { text-align: center; margin-top: 24px; font-size: 14px; color: #606770; }
        .footer-links a { color: #1c3faa; text-decoration: none; font-weight: 600; }
        .badge-admin { display: inline-block; background: #111827; color: #fff; font-size: 11px; font-weight: 700; letter-spacing: 1px; padding: 4px 12px; border-radius: 20px; margin-bottom: 12px; }
    </style>
    <link rel="stylesheet" href="responsive.css">
</head>
<body class="auth-page">
<div class="login-box">
    <div style="text-align: center; margin-bottom: 20px;">
        <img src="logo/logo.png" alt="Logo" style="max-height: 160px;">
    </div>
    <div style="text-align:center;"><span class="badge-admin">PANEL ADMIN</span></div>
    <h3 style="margin-bottom: 0;">Layanan E-Surat</h3>
    <h3 style="margin-top: 5px;">Fakultas Sains dan Ilmu Komputer</h3>
    <?php if(isset($error)) echo "<div class='error-msg'>" . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "</div>"; ?>
    <form method="POST" action="">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="text" name="username" placeholder="Username Admin" required autocomplete="username">
        <input type="password" name="password" placeholder="Password" required autocomplete="current-password">
        <button type="submit" name="login">Masuk Panel Admin</button>
    </form>
    <div class="footer-links">
        Bukan admin? <a href="login.php">Login Pengguna</a>
    </div>
</div>
</body>
</html>
