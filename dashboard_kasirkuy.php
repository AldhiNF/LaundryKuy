<?php
/**
 * =================================================================================
 * FILE: dashboard_kasirkuy.php
 * DESKRIPSI: Halaman Dashboard / Panel Operasional khusus untuk Kasir.
 * FUNGSI: Menampilkan ringkasan kerja harian (order masuk, cucian diproses,
 *         cucian siap diambil) serta menu pintasan ke fitur Kasir dan Riwayat.
 * HAK AKSES: KHUSUS KASIR (Owner tidak bisa membuka halaman ini, akan ditendang
 *            ke loginkuy.php oleh blok keamanan di bawah).
 * =================================================================================
 */

// 1. MEMULAI SESSION & PROTEKSI HALAMAN
// session_start() wajib dipanggil paling atas sebelum ada output HTML apapun.
// Fungsinya agar server mengingat identitas user (id_user, username, role)
// yang sedang login, supaya halaman ini tahu siapa yang sedang membukanya.
session_start();

// Variabel ini dipakai oleh sidebarkuy.php untuk menandai menu mana yang
// sedang aktif (di-highlight) di sidebar saat ini, yaitu menu "Dashboard".
$halaman_aktif = 'dashboard';

// Memanggil file koneksi agar halaman ini bisa "berbicara" dengan database 'db_laundry'
include 'connectkuy.php';

// 2. SISTEM KEAMANAN (PROTEKSI AKSES KHUSUS KASIR)
// Jika variabel $_SESSION['role'] belum dibuat (artinya belum login sama sekali)
// ATAU yang login BUKAN kasir (misalnya owner mencoba masuk lewat URL langsung), maka:
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'kasir') {
    header("Location: loginkuy.php"); // Tendang kembali ke halaman login
    exit(); // Hentikan semua proses PHP di bawah baris ini agar aman
}

// =================================================================================
// 3. PENGAMBILAN DATA RINGKASAN UNTUK KARTU STATISTIK
// Ketiga query di bawah ini dijalankan otomatis setiap kali halaman dibuka,
// sehingga angka pada kartu statistik selalu menampilkan kondisi terbaru
// (real-time) dari database tanpa perlu input manual dari kasir.
// =================================================================================

// A. Hitung total transaksi (nota) yang dibuat HARI INI
// date('Y-m-d') mengambil tanggal hari ini sesuai jam server, lalu
// dicocokkan dengan kolom tgl di t_transaksi menggunakan fungsi DATE()
// agar bagian jam:menit:detik diabaikan (hanya tanggalnya saja yang dibandingkan).
// [FIX BUG F] Ganti mysqli_query + string interpolasi menjadi prepared statement.
// $hari_ini berasal dari date() sehingga aman, tapi pola prepared statement
// harus konsisten di seluruh aplikasi agar tidak membuka kebiasaan buruk.
$hari_ini = date('Y-m-d');

// A. Total transaksi hari ini
$q = mysqli_prepare($koneksi, "SELECT COUNT(*) AS total FROM t_transaksi WHERE DATE(tgl) = ?");
mysqli_stmt_bind_param($q, "s", $hari_ini);
mysqli_stmt_execute($q);
$r_hari_ini = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
mysqli_stmt_close($q);

// B. Cucian masih diterima atau diproses
$q = mysqli_prepare($koneksi, "SELECT COUNT(*) AS total FROM t_transaksi WHERE st_cuci IN ('diterima','proses')");
mysqli_stmt_execute($q);
$r_proses = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
mysqli_stmt_close($q);

// C. Cucian selesai, belum dikirim
$q = mysqli_prepare($koneksi, "SELECT COUNT(*) AS total FROM t_transaksi WHERE st_cuci = 'selesai'");
mysqli_stmt_execute($q);
$r_selesai = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
mysqli_stmt_close($q);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir Dashboard - LaundryKuy</title>

    <!-- Icon Tab Browser -->
    <link rel="icon" type="image/png" href="assets/icontab.png">

    <!-- Memanggil framework CSS Bootstrap untuk desain rapi dan responsif -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Memanggil ikon dari Bootstrap (untuk ikon speedometer, receipt, jam pasir, dll) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- ===============================================================================
         CSS KHUSUS HALAMAN INI
         Mengatur efek hover pada kartu "Aktivitas Cepat" (Kasir & Update Status)
         agar warna teks dan ikon tetap terbaca jelas saat kursor diarahkan ke kartu.
         =============================================================================== -->
    <style>

        /* Efek hover umum: kartu sedikit terangkat ke atas dengan bayangan lebih tebal */
        .card-action {
            transition: all 0.3s ease;
            background-color: #ffffff; /* !important dihapus agar background bisa berubah saat di-hover */
        }
        .card-action:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 25px rgba(0,0,0,0.15) !important;
        }

        /* --- PERBAIKAN WARNA TEKS & ICON SAAT DI-HOVER --- */

        /* 1. Kotak Kasir / Buat Nota (Warna Primary/Biru) */
        .btn-outline-primary {
            color: #212529; /* Warna teks default tetap gelap saat kartu belum disentuh */
        }
        .btn-outline-primary:hover h4,
        .btn-outline-primary:hover p {
            color: #ffffff !important; /* Teks judul & deskripsi berubah jadi putih saat hover */
        }
        .btn-outline-primary:hover .bg-primary {
            background-color: #ffffff !important; /* Lingkaran ikon berbalik jadi putih */
            color: #0d6efd !important; /* Warna ikon di dalamnya berubah jadi biru */
        }

        /* 2. Kotak Update Status Cucian (Warna Info/Biru Muda) */
        .btn-outline-info {
            color: #212529; /* Warna teks default tetap gelap saat kartu belum disentuh */
        }
        .btn-outline-info:hover h4,
        .btn-outline-info:hover p {
            color: #000000 !important; /* Teks judul & deskripsi jadi hitam pekat saat hover (kontras dengan bg biru muda) */
        }
        .btn-outline-info:hover .bg-info {
            background-color: #000000 !important; /* Lingkaran ikon jadi hitam */
            color: #0dcaf0 !important; /* Warna ikon di dalamnya jadi biru muda */
        }
        .btn-outline-info:hover .text-white {
            color: #0dcaf0 !important; /* Memperbaiki warna ikon kaca pembesar agar tetap senada saat hover */
        }
    </style>
</head>
<body>

<!-- ===============================================================================
     STRUKTUR LAYOUT UTAMA (SIDEBAR + KONTEN)
     Layout dibagi menjadi dua bagian besar:
     1. Sidebar (sidebarkuy.php) -> Menu navigasi di sisi kiri, bisa collapse.
     2. Main Content (kuy-main)  -> Topbar + area konten dashboard.
     =============================================================================== -->
<div class="kuy-layout">

    <!-- Memanggil sidebar terpusat. $halaman_aktif='dashboard' membuat menu
         "Dashboard" otomatis ter-highlight (warna aktif) di sidebar. -->
    <?php $halaman_aktif = 'dashboard'; include 'sidebarkuy.php'; ?>

    <main class="kuy-main" id="kuyMain">

        <!-- ===============================================================================
             TOPBAR (Bilah Atas)
             Menampilkan judul halaman saat ini dan info singkat user yang login.
             =============================================================================== -->
        <div class="kuy-topbar">
            <span class="kuy-topbar-title">Dashboard Kasir</span>
            <div class="kuy-topbar-right">
                <!-- ucwords() membuat huruf awal tiap kata menjadi kapital, contoh: "budi santoso" -> "Budi Santoso" -->
                <span class="kuy-topbar-user d-none d-md-flex"><i class="bi bi-person-circle me-1"></i><?php echo ucwords($_SESSION['username']); ?></span>
            </div>
        </div>

        <!-- ===============================================================================
             KONTEN UTAMA HALAMAN
             =============================================================================== -->
        <div class="kuy-content">

        <div class="container-fluid">

            <!-- Header Judul Halaman -->
            <div class="row mb-4">
                <div class="col">
                    <h4 class="fw-bold mb-1" style="color:var(--text-dark,#1e2d40);">
                        <i class="bi bi-speedometer2 me-2" style="color:var(--gold,#c9a96e);"></i>Panel Operasional Kasir
                    </h4>
                    <p class="text-muted small mb-0">Pantau cucian hari ini dan layani pelanggan dengan cepat.</p>
                </div>
            </div>

            <!-- ===============================================================================
                 WIDGET STATISTIK (3 KARTU RINGKASAN)
                 Setiap kartu diberi garis warna tebal di sisi kiri (border-start border-5)
                 sebagai kode warna visual: biru = order masuk, kuning = sedang dikerjakan,
                 hijau = siap diserahkan ke pelanggan.
                 =============================================================================== -->
            <div class="row g-4 mb-5">

                <!-- Kartu 1: Order Hari Ini (garis biru tebal di kiri) -->
                <div class="col-md-4">
                    <div class="card border-0 border-start border-5 border-primary shadow-lg rounded-4 bg-white p-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted text-uppercase small fw-bold mb-2">Order Hari Ini</h6>
                                <!-- Menampilkan jumlah nota yang dibuat hari ini, hasil dari $q_hari_ini -->
                                <h2 class="fw-bolder mb-0 text-dark"><?php echo $r_hari_ini['total']; ?> <span class="fs-5 text-muted fw-normal">Nota</span></h2>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded-4 text-primary">
                                <i class="bi bi-receipt fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kartu 2: Diterima & Diproses (garis kuning tebal di kiri) -->
                <div class="col-md-4">
                    <div class="card border-0 border-start border-5 border-warning shadow-lg rounded-4 bg-white p-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted text-uppercase small fw-bold mb-2">Diterima & Diproses</h6>
                                <!-- Gabungan status 'diterima' dan 'proses', hasil dari $q_proses -->
                                <h2 class="fw-bolder mb-0 text-dark"><?php echo $r_proses['total']; ?> <span class="fs-5 text-muted fw-normal">Cucian</span></h2>
                            </div>
                            <div class="bg-warning bg-opacity-10 p-3 rounded-4 text-warning">
                                <i class="bi bi-hourglass-split fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kartu 3: Siap Diambil (garis hijau tebal di kiri) -->
                <div class="col-md-4">
                    <div class="card border-0 border-start border-5 border-success shadow-lg rounded-4 bg-white p-4">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <h6 class="text-muted text-uppercase small fw-bold mb-2">Siap Diambil</h6>
                                <!-- Status 'selesai' artinya cucian sudah bersih, menunggu diambil/dikirim, hasil dari $q_selesai -->
                                <h2 class="fw-bolder mb-0 text-dark"><?php echo $r_selesai['total']; ?> <span class="fs-5 text-muted fw-normal">Pack</span></h2>
                            </div>
                            <div class="bg-success bg-opacity-10 p-3 rounded-4 text-success">
                                <i class="bi bi-check2-circle fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===============================================================================
                 KARTU "AKTIVITAS CEPAT"
                 Berisi 2 menu pintasan utama yang paling sering dipakai kasir sehari-hari:
                 1. Membuat nota transaksi baru (Kasir)
                 2. Mencari nota & mengubah status cucian (Riwayat)
                 =============================================================================== -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5 bg-white">
                        <h4 class="fw-bolder mb-4 text-dark border-bottom border-2 pb-3"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Aktivitas Cepat</h4>

                        <div class="row g-4 mt-1">
                            <!-- Menu 1: Kasir / Buat Nota -->
                            <div class="col-md-6">
                                <a href="transaksikuy.php" class="btn btn-outline-primary w-100 p-4 text-start rounded-4 card-action h-100 border-2">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-primary text-white rounded-circle me-3 d-flex justify-content-center align-items-center" style="width: 64px; height: 64px; flex-shrink: 0;">
                                            <i class="bi bi-cart-plus-fill fs-3"></i>
                                        </div>
                                        <h4 class="fw-bolder mb-0 text-dark">Kasir / Buat Nota</h4>
                                    </div>
                                    <p class="text-secondary fw-semibold mb-0">Cari nama pelanggan, tambah pelanggan baru otomatis, input cucian, dan hitung harga dalam satu halaman.</p>
                                </a>
                            </div>

                            <!-- Menu 2: Update Status Cucian -->
                            <div class="col-md-6">
                                <a href="riwayatkuy.php" class="btn btn-outline-info w-100 p-4 text-start rounded-4 card-action h-100 border-2">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-info text-white rounded-circle me-3 d-flex justify-content-center align-items-center" style="width: 64px; height: 64px; flex-shrink: 0;">
                                            <i class="bi bi-search fs-3 text-white"></i>
                                        </div>
                                        <h4 class="fw-bolder text-dark mb-0">Update Status Cucian</h4>
                                    </div>
                                    <p class="text-secondary fw-semibold mb-0">Cari riwayat nota pelanggan, ubah status cucian menjadi <strong>Selesai</strong> atau ambil pakaian.</p>
                                </a>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>

        <!-- Panggil File Utama Javascript Bootstrap (dropdown, modal, collapse, dll) -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

        </div><!-- end kuy-content -->
    </main><!-- end kuy-main -->
</div><!-- end kuy-layout -->
</body>
</html>