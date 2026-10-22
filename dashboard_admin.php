<?php
include 'koneksi.php';
if (!isset($_SESSION['login']) || $_SESSION['role'] != 'admin' || ($_SESSION['login_via'] ?? '') !== 'panel-admin') {
    header("Location: panel-admin.php");
    exit;
}

// Ambil semua permohonan dari database gabung dengan data pengaju (user)
// Hanya menampilkan antrean aktif (Pending dan Diproses)
$query = mysqli_query($koneksi, "SELECT permohonan_surat.*, users.nama_lengkap, users.role 
                                 FROM permohonan_surat 
                                 JOIN users ON permohonan_surat.user_id = users.id 
                                 WHERE permohonan_surat.status IN ('Pending', 'Diproses')
                                 ORDER BY permohonan_surat.id DESC");

// Ambil Statistik Pengguna
$total_users = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as count FROM users WHERE role != 'admin'"))['count'];
$dosen_count = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as count FROM users WHERE role = 'dosen'"))['count'];
$prodi_counts_query = mysqli_query($koneksi, "SELECT prodi, COUNT(*) as count FROM users WHERE role = 'mahasiswa' GROUP BY prodi ORDER BY prodi ASC");
$prodi_stats = [];
while($ps = mysqli_fetch_assoc($prodi_counts_query)) {
    $prodi_stats[] = $ps;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Admin | E-Surat</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js Library -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #4e73df;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --dark: #2c3e50;
            --light: #f8f9fc;
        }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; margin: 0; color: #444; }
        .navbar { background: white; padding: 15px 40px; color: var(--primary); display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 1000; }
        .navbar .brand { font-size: 20px; font-weight: 700; color: var(--dark); text-decoration: none; display: flex; align-items: center; }
        .navbar .nav-links { display: flex; gap: 20px; align-items: center; }
        .navbar .nav-btn { text-decoration: none; font-weight: 600; font-size: 13px; padding: 8px 16px; border-radius: 6px; transition: 0.3s; }
        .btn-master { background: #f3e8ff; color: #7e22ce; }
        .btn-template { background: #fef3c7; color: #b45309; }
        .btn-arsip { background: #dcfce7; color: #15803d; }
        .btn-logout { background: #fee2e2; color: #b91c1c; margin-left: 15px; } /* Memberi jarak extra untuk tombol Keluar */
        
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .welcome-section { margin-bottom: 30px; }
        .welcome-section h2 { margin: 0; font-weight: 700; color: var(--dark); }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 12px; border-left: 4px solid var(--primary); box-shadow: 0 4px 6px rgba(0,0,0,0.04); display: flex; align-items: center; justify-content: space-between; }
        .stat-card .label { font-size: 12px; font-weight: 800; color: var(--primary); text-transform: uppercase; margin-bottom: 5px; }
        .stat-card .value { font-size: 24px; font-weight: 700; color: #5a5c69; }
        .stat-card i { font-size: 32px; color: #dddfeb; }

        .card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.04); padding: 25px; border: 1px solid #eef0f7; }
        .card h3 { color: var(--dark); margin-top: 0; font-size: 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px 12px; border-bottom: 2px solid #eef0f7; color: #858796; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 15px 12px; border-bottom: 1px solid #eef0f7; font-size: 14px; color: #5a5c69; }
        
        .badge { padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .bg-pending { background: #fff4e5; color: #b7791f; }
        .bg-proses { background: #e0f2fe; color: #0369a1; }
        
        .btn-action { background: var(--primary); color: white; text-decoration: none; padding: 8px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; transition: 0.3s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-action:hover { background: #2e59d9; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(78, 115, 223, 0.2); }
        .btn-view-doc { color: var(--primary); text-decoration: none; font-weight: 600; }

        /* Modal Detail Pengguna */
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); animation: slideIn 0.3s ease-out; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 15px; }
        .modal-header h4 { margin: 0; color: var(--dark); font-weight: 700; }
        .close-modal { cursor: pointer; font-size: 20px; color: #888; transition: 0.2s; }
        .close-modal:hover { color: var(--danger); }
        .user-list-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f8f9fc; font-size: 14px; }
        .user-list-item:last-child { border-bottom: none; }
        .user-list-item .count-badge { background: #f0f2f5; padding: 2px 10px; border-radius: 10px; font-weight: 700; color: var(--primary); font-size: 12px; }
        @keyframes slideIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    </style>
    <link rel="stylesheet" href="responsive.css">
</head>
<body class="dash-page">
    <div class="navbar">
        <a href="dashboard_admin.php" class="brand">
            <img src="logo/logo.png" alt="Logo" style="height: 60px; margin-right: 15px;">
            <span>E-Surat <small style="font-weight: 300; color: #858796;">Panel Admin</small></span>
        </a>
        <div class="nav-links">
            <a href="admin_master_surat.php" class="nav-btn btn-master"><i class="fa-solid fa-file-shield"></i> Kelola Jenis Surat</a>
            <a href="admin_templates.php" class="nav-btn btn-template"><i class="fa-solid fa-file-word"></i> Template Surat</a>
            <a href="admin_arsip.php" class="nav-btn btn-arsip"><i class="fa-solid fa-box-archive"></i> Arsip</a>
            <a href="logout.php" class="nav-btn btn-logout"><i class="fa-solid fa-power-off"></i> Keluar</a>
        </div>
    </div>

    <div class="container">
        <div class="welcome-section">
            <h2>Selamat Datang, <?= htmlspecialchars($_SESSION['nama']); ?> 🔑</h2>
        </div>

        <div class="stats-grid">
            <?php
                $total_req = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as count FROM permohonan_surat"))['count'];
                $pending_req = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as count FROM permohonan_surat WHERE status='Pending'"))['count'];
                $process_req = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as count FROM permohonan_surat WHERE status='Diproses'"))['count'];
                $selesai_req = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) as count FROM permohonan_surat WHERE status='Selesai'"))['count'];
            ?>
            <div class="stat-card">
                <div>
                    <div class="label">Total Pengajuan</div>
                    <div class="value"><?= $total_req; ?></div>
                </div>
                <i class="fa-solid fa-file-invoice"></i>
            </div>
            <div class="stat-card" style="border-left-color: var(--warning);">
                <div>
                    <div class="label" style="color: var(--warning);">Menunggu</div>
                    <div class="value"><?= $pending_req; ?></div>
                </div>
                <i class="fa-solid fa-hourglass-start"></i>
            </div>
            <div class="stat-card" style="border-left-color: var(--info);">
                <div>
                    <div class="label" style="color: var(--info);">Diproses</div>
                    <div class="value"><?= $process_req; ?></div>
                </div>
                <i class="fa-solid fa-spinner fa-spin-pulse"></i>
            </div>
            <div class="stat-card" style="border-left-color: var(--success);">
                <div>
                    <div class="label" style="color: var(--success);">Selesai</div>
                    <div class="value"><?= $selesai_req; ?></div>
                </div>
                <i class="fa-solid fa-check-double"></i>
            </div>
            <div class="stat-card" style="border-left-color: var(--dark); cursor: pointer;" onclick="openUserModal()">
                <div>
                    <div class="label" style="color: var(--dark);">Total Pengguna</div>
                    <div class="value"><?= $total_users; ?></div>
                </div>
                <i class="fa-solid fa-users"></i>
            </div>
        </div>

        <div class="card">
            <h3><i class="fa-solid fa-list-check" style="color: var(--primary);"></i> Antrean Permohonan Aktif</h3>
            <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Pengaju</th>
                        <th>Jenis Surat</th>
                        <th>Catatan</th>
                        <th>Dokumen</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(mysqli_num_rows($query) == 0): ?>
                    <tr><td colspan="6" style="text-align:center; padding:40px; color:#aaa;">Tidak ada antrean permohonan saat ini.</td></tr>
                <?php endif; ?>
                <?php while($row = mysqli_fetch_assoc($query)) { ?>
                <tr>
                    <td>
                        <span style="font-weight: 600; color: var(--dark);"><?= htmlspecialchars($row['nama_lengkap']); ?></span><br>
                        <small style="color: #858796; text-transform: capitalize;"><?= $row['role']; ?></small>
                    </td>
                    <td><span style="font-weight: 500;"><?= htmlspecialchars($row['jenis_surat']); ?></span></td>
                    <td style="max-width: 200px;"><small><?= htmlspecialchars($row['catatan_user']); ?></small></td>
                    <td><?php if(!empty($row['berkas_pendukung'])) { ?>
                            <a href="uploads/<?= htmlspecialchars(basename($row['berkas_pendukung']), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="btn-view-doc"><i class="fa-solid fa-file-arrow-up"></i> Lihat</a>
                        <?php } else { ?>
                            <small style="color: #a0aec0;">Tidak ada berkas</small>
                        <?php } ?>
                    </td>
                    <td>
                        <?php 
                            $class = '';
                            if($row['status'] == 'Pending') $class = 'bg-pending';
                            elseif($row['status'] == 'Diproses') $class = 'bg-proses';
                            elseif($row['status'] == 'Selesai') $class = 'bg-selesai';
                            elseif($row['status'] == 'Ditolak') $class = 'bg-ditolak';
                        ?>
                        <span class="badge <?= $class; ?>"><?= $row['status']; ?></span>
                    </td>
                    <td>
                        <a href="admin_kirim_surat.php?id_permohonan=<?= (int)$row['id']; ?>&user_id=<?= (int)$row['user_id']; ?>&role=<?= urlencode($row['role']); ?>&jenis=<?= urlencode($row['jenis_surat']); ?>" class="btn-action">
                            <i class="fa-solid fa-pen-to-square"></i> Proses
                        </a>
                    </td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <!-- Modal Detail Pengguna -->
    <div id="userStatsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4><i class="fa-solid fa-users-viewfinder"></i> Rincian Pengguna</h4>
                <span class="close-modal" onclick="closeUserModal()">&times;</span>
            </div>

            <!-- Chart Section -->
            <div style="margin-bottom: 25px; position: relative; height: 260px;">
                <canvas id="userChart"></canvas>
            </div>

            <!-- List Section -->
            <div style="max-height: 200px; overflow-y: auto; padding-right: 5px;">
                <div class="user-list-item" style="border-bottom: 2px solid #eee; margin-bottom: 5px; font-weight: 600;">
                    <span>Dosen (Total)</span>
                    <span class="count-badge" style="background: var(--primary); color: white;"><?= $dosen_count; ?></span>
                </div>
                <?php foreach($prodi_stats as $stat): ?>
                <div class="user-list-item">
                    <span>Mahasiswa - <?= htmlspecialchars($stat['prodi'] ?: 'Tanpa Prodi'); ?></span>
                    <span class="count-badge"><?= $stat['count']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        let userChartInstance = null;

        function openUserModal() { 
            document.getElementById('userStatsModal').style.display = 'flex';
            
            const ctx = document.getElementById('userChart').getContext('2d');
            
            // Menyiapkan data dari PHP ke JavaScript
            const labels = ['Dosen', <?php foreach($prodi_stats as $s) echo '"'.addslashes($s['prodi']?:'Tanpa Prodi').'",'; ?>];
            const counts = [<?= $dosen_count ?>, <?php foreach($prodi_stats as $s) echo (int)$s['count'].","; ?>];

            // Hancurkan instance bagan lama jika ada untuk menghindari tumpang tindih
            if (userChartInstance) {
                userChartInstance.destroy();
            }

            userChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: counts,
                        backgroundColor: [
                            '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', 
                            '#5a5c69', '#2c3e50', '#858796', '#2e59d9', '#17a673'
                        ],
                        hoverOffset: 8,
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                padding: 15,
                                font: { size: 11, family: "'Inter', sans-serif" }
                            }
                        }
                    },
                    cutout: '65%'
                }
            });
        }

        function closeUserModal() { document.getElementById('userStatsModal').style.display = 'none'; }
        window.onclick = function(event) { if (event.target == document.getElementById('userStatsModal')) closeUserModal(); }
    </script>
</body>
</html>