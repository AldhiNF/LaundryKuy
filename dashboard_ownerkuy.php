<?php
/**
 * File: dashboard_ownerkuy.php
 * Deskripsi: Dashboard Owner — Grafik bulanan, stat card dinamis, analisis performa.
 */

session_start();
$halaman_aktif = 'dashboard';
include 'connectkuy.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header("Location: loginkuy.php");
    exit();
}

$pesan_sukses_pw = "";
$pesan_error_pw  = "";

// ── GANTI PASSWORD OWNER ──────────────────────────────────────────────
if (isset($_POST['btn_ganti_pw_owner'])) {
    $pw_lama = $_POST['pw_lama']; $pw_baru = $_POST['pw_baru_owner']; $pw_konfirm = $_POST['pw_konfirm_owner'];
    $stmt = mysqli_prepare($koneksi, "SELECT password FROM t_user WHERE id_user = ?");
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['id_user']); mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
    if (!password_verify($pw_lama, $row['password']))       { $pesan_error_pw  = "Password lama salah!"; }
    elseif ($pw_baru !== $pw_konfirm)                       { $pesan_error_pw  = "Konfirmasi password tidak cocok!"; }
    elseif (strlen($pw_baru) < 6)                           { $pesan_error_pw  = "Password minimal 6 karakter!"; }
    else {
        $hash = password_hash($pw_baru, PASSWORD_BCRYPT);
        $stmt = mysqli_prepare($koneksi, "UPDATE t_user SET password=? WHERE id_user=?");
        mysqli_stmt_bind_param($stmt, "si", $hash, $_SESSION['id_user']);
        $pesan_sukses_pw = mysqli_stmt_execute($stmt) ? "Password berhasil diubah!" : "Gagal mengubah password.";
        mysqli_stmt_close($stmt);
    }
}

// ── FILTER BULAN ──────────────────────────────────────────────────────
$f_bulan = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)date('m');
$f_tahun = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)date('Y');
if ($f_bulan < 1 || $f_bulan > 12) $f_bulan = (int)date('m');
if ($f_tahun < 2020 || $f_tahun > 2099) $f_tahun = (int)date('Y');

$awal_bulan  = sprintf('%04d-%02d-01', $f_tahun, $f_bulan);
$akhir_bulan = date('Y-m-t', strtotime($awal_bulan));
$label_bln   = date('F Y', strtotime($awal_bulan));

// Periode bulan lalu untuk perbandingan
$bln_lalu_ts    = strtotime('-1 month', strtotime($awal_bulan));
$bln_lalu_awal  = date('Y-m-01', $bln_lalu_ts);
$bln_lalu_akhir = date('Y-m-t',  $bln_lalu_ts);

// ── STAT CARDS ────────────────────────────────────────────────────────
// Pendapatan bulan ini
$q = mysqli_prepare($koneksi, "SELECT COALESCE(SUM(total),0) as v FROM t_transaksi WHERE st_bayar='lunas' AND DATE(tgl) BETWEEN ? AND ?");
mysqli_stmt_bind_param($q,"ss",$awal_bulan,$akhir_bulan); mysqli_stmt_execute($q);
$pendapatan_bln = (float)mysqli_fetch_assoc(mysqli_stmt_get_result($q))['v']; mysqli_stmt_close($q);

// Pendapatan bulan lalu
$q = mysqli_prepare($koneksi, "SELECT COALESCE(SUM(total),0) as v FROM t_transaksi WHERE st_bayar='lunas' AND DATE(tgl) BETWEEN ? AND ?");
mysqli_stmt_bind_param($q,"ss",$bln_lalu_awal,$bln_lalu_akhir); mysqli_stmt_execute($q);
$pendapatan_lalu = (float)mysqli_fetch_assoc(mysqli_stmt_get_result($q))['v']; mysqli_stmt_close($q);

// Pengeluaran bulan ini
$q = mysqli_prepare($koneksi, "SELECT COALESCE(SUM(jumlah),0) as v FROM t_biaya_op WHERE DATE(tgl) BETWEEN ? AND ?");
mysqli_stmt_bind_param($q,"ss",$awal_bulan,$akhir_bulan); mysqli_stmt_execute($q);
$pengeluaran_bln = (float)mysqli_fetch_assoc(mysqli_stmt_get_result($q))['v']; mysqli_stmt_close($q);

$laba_bln = $pendapatan_bln - $pengeluaran_bln;
$growth   = $pendapatan_lalu > 0 ? round((($pendapatan_bln - $pendapatan_lalu) / $pendapatan_lalu) * 100, 1) : 0;

// Jumlah transaksi bulan ini & lalu
$q = mysqli_prepare($koneksi, "SELECT COUNT(*) as v FROM t_transaksi WHERE DATE(tgl) BETWEEN ? AND ?");
mysqli_stmt_bind_param($q,"ss",$awal_bulan,$akhir_bulan); mysqli_stmt_execute($q);
$trx_bln = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($q))['v']; mysqli_stmt_close($q);

$q = mysqli_prepare($koneksi, "SELECT COUNT(*) as v FROM t_transaksi WHERE DATE(tgl) BETWEEN ? AND ?");
mysqli_stmt_bind_param($q,"ss",$bln_lalu_awal,$bln_lalu_akhir); mysqli_stmt_execute($q);
$trx_lalu = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($q))['v']; mysqli_stmt_close($q);

$trx_growth = $trx_lalu > 0 ? round((($trx_bln - $trx_lalu) / $trx_lalu) * 100, 1) : 0;

// Pelanggan baru bulan ini — pakai id_trans pertama pelanggan di bulan ini
$q = mysqli_prepare($koneksi,
    "SELECT COUNT(DISTINCT t.id_pel) as v FROM t_transaksi t
     WHERE DATE(t.tgl) BETWEEN ? AND ?
     AND NOT EXISTS (
         SELECT 1 FROM t_transaksi t2
         WHERE t2.id_pel = t.id_pel AND DATE(t2.tgl) < ?
     )");
mysqli_stmt_bind_param($q, "sss", $awal_bulan, $akhir_bulan, $awal_bulan);
mysqli_stmt_execute($q);
$r = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
$pel_baru = (int)($r['v'] ?? 0);
mysqli_stmt_close($q);

// Antrian proses
$q = mysqli_query($koneksi, "SELECT COUNT(*) as v FROM t_transaksi WHERE st_cuci='proses'");
$antrian = (int)mysqli_fetch_assoc($q)['v'];

// ── GRAFIK 1: PENDAPATAN 12 BULAN TERAKHIR ───────────────────────────
$g1_labels = []; $g1_data = [];
for ($i = 11; $i >= 0; $i--) {
    $ts   = strtotime("-$i months", mktime(0,0,0,$f_bulan,1,$f_tahun));
    $tgl1 = date('Y-m-01', $ts);
    $tgl2 = date('Y-m-t',  $ts);
    $g1_labels[] = date('M Y', $ts);
    $q = mysqli_prepare($koneksi, "SELECT COALESCE(SUM(total),0) as v FROM t_transaksi WHERE st_bayar='lunas' AND DATE(tgl) BETWEEN ? AND ?");
    mysqli_stmt_bind_param($q,"ss",$tgl1,$tgl2); mysqli_stmt_execute($q);
    $g1_data[] = (float)mysqli_fetch_assoc(mysqli_stmt_get_result($q))['v']; mysqli_stmt_close($q);
}

// ── GRAFIK 2: PENDAPATAN vs PENGELUARAN 12 BULAN ─────────────────────
$g2_pendapatan = []; $g2_pengeluaran = [];
for ($i = 11; $i >= 0; $i--) {
    $ts   = strtotime("-$i months", mktime(0,0,0,$f_bulan,1,$f_tahun));
    $tgl1 = date('Y-m-01', $ts);
    $tgl2 = date('Y-m-t',  $ts);
    // Pendapatan
    $q = mysqli_prepare($koneksi, "SELECT COALESCE(SUM(total),0) as v FROM t_transaksi WHERE st_bayar='lunas' AND DATE(tgl) BETWEEN ? AND ?");
    mysqli_stmt_bind_param($q,"ss",$tgl1,$tgl2); mysqli_stmt_execute($q);
    $g2_pendapatan[] = (float)mysqli_fetch_assoc(mysqli_stmt_get_result($q))['v']; mysqli_stmt_close($q);
    // Pengeluaran
    $q = mysqli_prepare($koneksi, "SELECT COALESCE(SUM(jumlah),0) as v FROM t_biaya_op WHERE DATE(tgl) BETWEEN ? AND ?");
    mysqli_stmt_bind_param($q,"ss",$tgl1,$tgl2); mysqli_stmt_execute($q);
    $g2_pengeluaran[] = (float)mysqli_fetch_assoc(mysqli_stmt_get_result($q))['v']; mysqli_stmt_close($q);
}

// ── GRAFIK 3: PAKET TERLARIS BULAN INI ───────────────────────────────
$q = mysqli_prepare($koneksi,
    "SELECT l.nama_paket, COUNT(t.id_trans) as jumlah, COALESCE(SUM(t.total),0) as omset
     FROM t_transaksi t LEFT JOIN t_layanan l ON t.id_layanan = l.id_layanan
     WHERE DATE(t.tgl) BETWEEN ? AND ?
     GROUP BY t.id_layanan, l.nama_paket ORDER BY jumlah DESC LIMIT 6");
mysqli_stmt_bind_param($q,"ss",$awal_bulan,$akhir_bulan); mysqli_stmt_execute($q);
$hasil_paket = mysqli_stmt_get_result($q);
$g3_labels = []; $g3_data = []; $g3_omset = [];
while ($r = mysqli_fetch_assoc($hasil_paket)) {
    $g3_labels[] = ucwords($r['nama_paket'] ?? 'Lainnya');
    $g3_data[]   = (int)$r['jumlah'];
    $g3_omset[]  = (float)$r['omset'];
}
mysqli_stmt_close($q);

// ── 5 TRANSAKSI TERAKHIR ──────────────────────────────────────────────
$q_recent = mysqli_query($koneksi,
    "SELECT t.id_trans, p.nama, t.st_bayar, t.st_cuci, t.total, t.tgl
     FROM t_transaksi t JOIN t_pelanggan p ON t.id_pel = p.id_pel
     ORDER BY t.id_trans DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Owner - LaundryKuy</title>
    <link rel="icon" type="image/png" href="assets/icontab.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .chart-wrap  { position: relative; height: 260px; }
        .chart-wrap-sm { position: relative; height: 220px; }
        .kuy-stat-badge {
            display: inline-flex; align-items: center; gap: 3px;
            font-size: 11px; font-weight: 700; padding: 2px 8px;
            border-radius: 20px;
        }
        .badge-up   { background: #dcfce7; color: #166534; }
        .badge-down { background: #fee2e2; color: #991b1b; }
        .badge-flat { background: var(--cream-100,#f5edd8); color: var(--text-mid,#4a6178); }
    </style>
</head>
<body>
<div class="kuy-layout">
<?php include 'sidebarkuy.php'; ?>
<main class="kuy-main" id="kuyMain">
    <div class="kuy-topbar">
        <span class="kuy-topbar-title">Dashboard</span>
        <div class="kuy-topbar-right">
            <!-- Filter Bulan -->
            <form method="GET" action="" class="d-flex gap-2 align-items-center">
                <select name="bulan" class="form-select form-select-sm border-2 fw-semibold" style="width:130px; font-size:12px;">
                    <?php
                    $nm_bln = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
                    for ($i=1;$i<=12;$i++) echo "<option value='$i'" . ($i==$f_bulan?' selected':'') . ">{$nm_bln[$i-1]}</option>";
                    ?>
                </select>
                <select name="tahun" class="form-select form-select-sm border-2 fw-semibold" style="width:90px; font-size:12px;">
                    <?php for ($y=2024;$y<=2030;$y++) echo "<option value='$y'" . ($y==$f_tahun?' selected':'') . ">$y</option>"; ?>
                </select>
                <button type="submit" class="btn btn-sm fw-bold px-3"
                    style="background:var(--navy-800,#1e2d40);color:var(--cream-200,#e8d5b7);border:none;border-radius:8px;font-size:12px;">
                    <i class="bi bi-funnel-fill"></i>
                </button>
            </form>
            <span class="kuy-topbar-user d-none d-md-flex">
                <i class="bi bi-person-circle me-1"></i><?php echo ucwords($_SESSION['username']); ?>
            </span>
        </div>
    </div>
    <div class="kuy-content">

        <!-- Judul -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1" style="color:var(--text-dark,#1e2d40);">
                    <i class="bi bi-graph-up-arrow me-2" style="color:var(--gold,#c9a96e);"></i>Ringkasan Bisnis
                </h4>
                <p class="text-muted small mb-0">Periode: <strong><?php echo $label_bln; ?></strong></p>
            </div>
            <a href="laporankuy.php?bulan=<?php echo $f_bulan; ?>&tahun=<?php echo $f_tahun; ?>"
               class="kuy-btn-primary text-decoration-none" style="font-size:12px;">
                <i class="bi bi-file-earmark-bar-graph-fill"></i> Laporan Lengkap
            </a>
        </div>

        <!-- ── STAT CARDS ── -->
        <div class="row g-3 mb-4">

            <!-- Pendapatan Bulan Ini -->
            <div class="col-6 col-md-3">
                <div class="kuy-stat">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="kuy-stat-label">Pendapatan</div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                            style="width:36px;height:36px;background:rgba(30,45,64,0.08);">
                            <i class="bi bi-cash-coin" style="color:var(--navy-800,#1e2d40);font-size:17px;"></i>
                        </div>
                    </div>
                    <div class="kuy-stat-value">Rp <?php echo number_format($pendapatan_bln,0,',','.'); ?></div>
                    <div class="d-flex align-items-center gap-2 mt-2">
                        <?php
                        $cls = $growth > 0 ? 'badge-up' : ($growth < 0 ? 'badge-down' : 'badge-flat');
                        $ico = $growth > 0 ? 'bi-arrow-up-right' : ($growth < 0 ? 'bi-arrow-down-right' : 'bi-dash');
                        $tanda = $growth > 0 ? '+' : '';
                        ?>
                        <span class="kuy-stat-badge <?php echo $cls; ?>">
                            <i class="bi <?php echo $ico; ?>"></i><?php echo $tanda.$growth; ?>%
                        </span>
                        <span class="kuy-stat-sub">vs bln lalu</span>
                    </div>
                </div>
            </div>

            <!-- Pengeluaran Bulan Ini -->
            <div class="col-6 col-md-3">
                <div class="kuy-stat">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="kuy-stat-label">Pengeluaran</div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                            style="width:36px;height:36px;background:rgba(220,53,69,0.08);">
                            <i class="bi bi-wallet2" style="color:#dc3545;font-size:17px;"></i>
                        </div>
                    </div>
                    <div class="kuy-stat-value" style="color:#dc3545;">Rp <?php echo number_format($pengeluaran_bln,0,',','.'); ?></div>
                    <div class="kuy-stat-sub mt-2">
                        Laba: <strong style="color:<?php echo $laba_bln >= 0 ? '#166534' : '#991b1b'; ?>;">
                            Rp <?php echo number_format(abs($laba_bln),0,',','.'); ?>
                        </strong>
                    </div>
                </div>
            </div>

            <!-- Transaksi Bulan Ini -->
            <div class="col-6 col-md-3">
                <div class="kuy-stat">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="kuy-stat-label">Transaksi</div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                            style="width:36px;height:36px;background:rgba(201,169,110,0.12);">
                            <i class="bi bi-receipt" style="color:var(--gold,#c9a96e);font-size:17px;"></i>
                        </div>
                    </div>
                    <div class="kuy-stat-value"><?php echo $trx_bln; ?> <span style="font-size:1rem;font-weight:500;color:#7a92a8;">nota</span></div>
                    <div class="d-flex align-items-center gap-2 mt-2">
                        <?php
                        $cls2 = $trx_growth > 0 ? 'badge-up' : ($trx_growth < 0 ? 'badge-down' : 'badge-flat');
                        $ico2 = $trx_growth > 0 ? 'bi-arrow-up-right' : ($trx_growth < 0 ? 'bi-arrow-down-right' : 'bi-dash');
                        $t2   = $trx_growth > 0 ? '+' : '';
                        ?>
                        <span class="kuy-stat-badge <?php echo $cls2; ?>">
                            <i class="bi <?php echo $ico2; ?>"></i><?php echo $t2.$trx_growth; ?>%
                        </span>
                        <span class="kuy-stat-sub">vs bln lalu</span>
                    </div>
                </div>
            </div>

            <!-- Pelanggan Baru -->
            <div class="col-6 col-md-3">
                <div class="kuy-stat">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="kuy-stat-label">Pelanggan Baru</div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center"
                            style="width:36px;height:36px;background:rgba(16,185,129,0.08);">
                            <i class="bi bi-person-plus-fill" style="color:#10b981;font-size:17px;"></i>
                        </div>
                    </div>
                    <div class="kuy-stat-value" style="color:#10b981;"><?php echo $pel_baru; ?></div>
                    <div class="kuy-stat-sub mt-2">Antrian: <strong><?php echo $antrian; ?></strong> proses</div>
                </div>
            </div>

        </div>

        <!-- ── GRAFIK BARIS 1: Pendapatan 12 Bulan + Paket Terlaris ── -->
        <div class="row g-3 mb-3">

            <!-- Grafik Pendapatan 12 Bulan -->
            <div class="col-lg-8">
                <div class="kuy-card p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="fw-bold mb-0" style="color:var(--text-dark,#1e2d40);">
                                <i class="bi bi-bar-chart-line-fill me-2" style="color:var(--gold,#c9a96e);"></i>Tren Pendapatan 12 Bulan
                            </h6>
                            <p class="text-muted small mb-0">Pendapatan lunas per bulan</p>
                        </div>
                    </div>
                    <div class="chart-wrap">
                        <canvas id="chartPendapatan"></canvas>
                    </div>
                </div>
            </div>

            <!-- Grafik Paket Terlaris -->
            <div class="col-lg-4">
                <div class="kuy-card p-4 h-100">
                    <div class="mb-3">
                        <h6 class="fw-bold mb-0" style="color:var(--text-dark,#1e2d40);">
                            <i class="bi bi-trophy-fill me-2" style="color:var(--gold,#c9a96e);"></i>Paket Terlaris
                        </h6>
                        <p class="text-muted small mb-0"><?php echo $label_bln; ?></p>
                    </div>
                    <?php if (count($g3_labels) > 0): ?>
                    <div class="chart-wrap-sm">
                        <canvas id="chartPaket"></canvas>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                        <small>Belum ada transaksi bulan ini</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- ── GRAFIK BARIS 2: Pendapatan vs Pengeluaran + Transaksi Terakhir ── -->
        <div class="row g-3 mb-3">

            <!-- Grafik Pendapatan vs Pengeluaran -->
            <div class="col-lg-7">
                <div class="kuy-card p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h6 class="fw-bold mb-0" style="color:var(--text-dark,#1e2d40);">
                                <i class="bi bi-bar-chart-steps me-2" style="color:var(--gold,#c9a96e);"></i>Pendapatan vs Pengeluaran
                            </h6>
                            <p class="text-muted small mb-0">Perbandingan 12 bulan terakhir</p>
                        </div>
                    </div>
                    <div class="chart-wrap">
                        <canvas id="chartVsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- 5 Transaksi Terakhir -->
            <div class="col-lg-5">
                <div class="kuy-card p-4 h-100 d-flex flex-column">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="fw-bold mb-0" style="color:var(--text-dark,#1e2d40);">
                            <i class="bi bi-clock-history me-2" style="color:var(--gold,#c9a96e);"></i>Transaksi Terakhir
                        </h6>
                        <a href="riwayatkuy.php" class="text-decoration-none small fw-semibold"
                            style="color:var(--navy-600,#2e4d6e);">Lihat semua →</a>
                    </div>
                    <div class="flex-grow-1">
                        <?php if (mysqli_num_rows($q_recent) > 0): ?>
                        <div class="d-flex flex-column gap-2">
                            <?php while ($r = mysqli_fetch_assoc($q_recent)): ?>
                            <div class="d-flex align-items-center justify-content-between p-2 rounded-3"
                                style="background:var(--cream-50,#fdf8f0); border:1px solid var(--cream-100,#f5edd8);">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="fw-bold small" style="color:var(--navy-600,#2e4d6e); min-width:52px;">
                                        KUY-<?php echo sprintf("%02d",$r['id_trans']); ?>
                                    </div>
                                    <div>
                                        <div class="fw-semibold small" style="color:var(--text-dark,#1e2d40); line-height:1.2;">
                                            <?php echo htmlspecialchars($r['nama']); ?>
                                        </div>
                                        <div style="font-size:10px; color:#7a92a8;">
                                            <?php echo date('d M Y', strtotime($r['tgl'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold small" style="color:var(--text-dark,#1e2d40);">
                                        Rp <?php echo number_format($r['total'],0,',','.'); ?>
                                    </div>
                                    <?php if ($r['st_bayar'] == 'lunas'): ?>
                                        <span class="kuy-badge kuy-badge-success" style="font-size:9px;">Lunas</span>
                                    <?php else: ?>
                                        <span class="kuy-badge kuy-badge-danger" style="font-size:9px;">Belum</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            <small>Belum ada transaksi</small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

    </div><!-- end kuy-content -->
</main>
</div><!-- end kuy-layout -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const FMT = v => 'Rp ' + new Intl.NumberFormat('id-ID').format(v);
const NAVY  = '#1e2d40';
const GOLD  = '#c9a96e';
const CREAM = '#f5edd8';

// ── Chart 1: Pendapatan 12 Bulan ───────────────────────────────────────
new Chart(document.getElementById('chartPendapatan'), {
    type: 'line', // [FIX] Ganti bar -> line agar tren lebih jelas
    data: {
        labels: <?php echo json_encode($g1_labels); ?>,
        datasets: [{
            label: 'Pendapatan',
            data: <?php echo json_encode($g1_data); ?>,
            borderColor: NAVY,
            backgroundColor: 'rgba(30,45,64,0.07)',
            fill: true,
            tension: 0.4,
            borderWidth: 2.5,
            pointBackgroundColor: (ctx) => ctx.dataIndex === 11 ? GOLD : NAVY,
            pointBorderColor: '#fff',
            pointRadius: (ctx) => ctx.dataIndex === 11 ? 7 : 4,
            pointBorderWidth: 2,
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => FMT(ctx.raw) } }
        },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 10 } } },
            y: { beginAtZero: true, grid: { color: CREAM },
                ticks: { callback: v => FMT(v), font: { size: 10 } }
            }
        }
    }
});

// ── Chart 2: Paket Terlaris (Doughnut) ────────────────────────────────
<?php if (count($g3_labels) > 0): ?>
new Chart(document.getElementById('chartPaket'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($g3_labels); ?>,
        datasets: [{
            data: <?php echo json_encode($g3_data); ?>,
            backgroundColor: [GOLD,'#1e2d40','#2e4d6e','#c9a96e88','#3d6491','#e8d5b7'],
            borderWidth: 2,
            borderColor: '#000000'
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 10 }, padding: 8,
                generateLabels: chart => {
                    const data = chart.data;
                    const total = data.datasets[0].data.reduce((a,b)=>a+b,0);
                    return data.labels.map((lbl,i) => ({
                        text: lbl + ' (' + Math.round(data.datasets[0].data[i]/total*100) + '%)',
                        fillStyle: data.datasets[0].backgroundColor[i],
                        index: i
                    }));
                }
            }},
            tooltip: {
                callbacks: {
                    label: ctx => {
                        const total = ctx.dataset.data.reduce((a,b)=>a+b,0);
                        const pct   = Math.round(ctx.raw/total*100);
                        return ctx.label + ': ' + ctx.raw + ' order (' + pct + '%)';
                    }
                }
            },
            // [FIX] Tampilkan % di tengah setiap slice
            datalabels: false
        },
        cutout: '55%'
    },
    plugins: [{
        id: 'pieLabels',
        afterDatasetDraw(chart) {
            const {ctx, data} = chart;
            const total = data.datasets[0].data.reduce((a,b)=>a+b,0);
            chart.getDatasetMeta(0).data.forEach((arc, i) => {
                const pct = Math.round(data.datasets[0].data[i] / total * 100);
                if (pct < 4) return; // jangan tampil jika terlalu kecil
                const {x, y} = arc.tooltipPosition();
                ctx.save();
                ctx.font = 'bold 11px Plus Jakarta Sans, Arial';
                ctx.fillStyle = '#fff';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(pct + '%', x, y);
                ctx.restore();
            });
        }
    }]
});
<?php endif; ?>

// ── Chart 3: Pendapatan vs Pengeluaran (Line) ─────────────────────────
new Chart(document.getElementById('chartVsChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($g1_labels); ?>,
        datasets: [
            {
                label: 'Pendapatan',
                data: <?php echo json_encode($g2_pendapatan); ?>,
                borderColor: NAVY,
                backgroundColor: 'rgba(30,45,64,0.08)',
                fill: true,
                tension: 0.4,
                borderWidth: 2,
                pointBackgroundColor: NAVY,
                pointRadius: 4,
            },
            {
                label: 'Pengeluaran',
                data: <?php echo json_encode($g2_pengeluaran); ?>,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220,53,69,0.06)',
                fill: true,
                tension: 0.4,
                borderWidth: 2,
                pointBackgroundColor: '#dc3545',
                pointRadius: 4,
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top', labels: { font: { size: 11 }, padding: 12 } },
            tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ' + FMT(ctx.raw) } }
        },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 10 } } },
            y: { beginAtZero: true, grid: { color: CREAM },
                ticks: { callback: v => FMT(v), font: { size: 10 } }
            }
        }
    }
});
</script>

<!-- Modal Ganti Password Owner -->
<div class="modal fade" id="modalGantiPwOwner" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header text-white rounded-top-4" style="background:linear-gradient(to right,#1e2d40,#2e4d6e);">
                <h5 class="modal-title fw-bold"><i class="bi bi-key-fill me-2" style="color:var(--gold,#c9a96e);"></i>Ganti Password</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="formGantiPwOwner">
                <div class="modal-body p-4">
                    <?php foreach(['pw_lama'=>'Password Lama','pw_baru_owner'=>'Password Baru','pw_konfirm_owner'=>'Konfirmasi Password'] as $nm=>$lb): ?>
                    <div class="mb-3">
                        <label class="form-label fw-bold small"><?php echo $lb; ?></label>
                        <div class="position-relative">
                            <input type="password" class="form-control pe-5" name="<?php echo $nm; ?>"
                                id="<?php echo $nm; ?>" placeholder="<?php echo $lb; ?>" required
                                <?php echo strpos($nm,'lama')===false ? 'minlength="6"' : ''; ?>>
                            <i class="bi bi-eye position-absolute top-50 end-0 translate-middle-y me-3"
                                style="cursor:pointer;color:#b0c0cc;"
                                onclick="togglePwOwner('<?php echo $nm; ?>',this)"></i>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="modal-footer bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary btn-sm fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-sm fw-bold px-4"
                        style="background:var(--navy-800,#1e2d40);color:var(--cream-200,#e8d5b7);border:none;border-radius:8px;"
                        onclick="konfirmasiGantiPw()">
                        <i class="bi bi-save me-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function togglePwOwner(id,el){const i=document.getElementById(id);if(i.type==='password'){i.type='text';el.classList.replace('bi-eye','bi-eye-slash');}else{i.type='password';el.classList.replace('bi-eye-slash','bi-eye');}}
function konfirmasiGantiPw(){const f=document.getElementById('formGantiPwOwner');if(!f.checkValidity()){f.reportValidity();return;}Swal.fire({title:'Ganti Password?',text:'Password baru akan aktif langsung.',icon:'question',showCancelButton:true,confirmButtonColor:'#1e2d40',cancelButtonColor:'#6c757d',confirmButtonText:'Ya, Simpan!',cancelButtonText:'Batal'}).then(r=>{if(r.isConfirmed){let i=document.createElement('input');i.type='hidden';i.name='btn_ganti_pw_owner';i.value='1';f.appendChild(i);f.submit();}});}
</script>
<?php if (!empty($pesan_sukses_pw)): ?>
<script>Swal.fire({icon:'success',title:'Berhasil!',text:'<?php echo $pesan_sukses_pw; ?>',showConfirmButton:false,timer:2000});</script>
<?php endif; ?>
<?php if (!empty($pesan_error_pw)): ?>
<script>Swal.fire({icon:'error',title:'Gagal!',text:'<?php echo addslashes($pesan_error_pw); ?>'});</script>
<?php endif; ?>
</body>
</html>