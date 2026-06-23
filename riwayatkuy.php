<?php
/**
 * =================================================================================
 * FILE: riwayatkuy.php
 * DESKRIPSI: Halaman Riwayat & Laporan Transaksi Laundry.
 * FUNGSI: Menampilkan daftar nota cucian, serta memungkinkan admin mengubah status.
 * HAK AKSES: Admin (Bisa edit status) & Owner (Hanya bisa memantau/Read Only).
 * =================================================================================
 */

// 1. MEMULAI SESSION
// Wajib dipanggil di baris paling awal agar sistem tahu siapa pengguna yang sedang login.
session_start();

$halaman_aktif = 'riwayat';

// 2. KONEKSI DATABASE
// Menghubungkan halaman ini dengan database 'db_laundry' melalui file koneksi.
include 'connectkuy.php';

// 3. SISTEM KEAMANAN (PROTEKSI AKSES GANDA)
// Halaman ini SPESIAL karena bisa diakses oleh 2 role berbeda.
// Jika belum login, ATAU role-nya BUKAN admin DAN BUKAN owner, tendang ke halaman login.
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'kasir' && $_SESSION['role'] !== 'owner')) {
    header("Location: loginkuy.php");
    exit();
}

// 4. PENGATURAN TAMPILAN DINAMIS BERDASARKAN ROLE
// Kita gunakan "Ternary Operator" (Kondisi ? Benar : Salah) untuk mengubah UI secara otomatis.
$link_dashboard = ($_SESSION['role'] == 'kasir') ? 'dashboard_kasirkuy.php' : 'dashboard_ownerkuy.php';
$teks_role      = ($_SESSION['role'] == 'kasir') ? 'Kasir' : 'Owner';
$tema_navbar    = ($_SESSION['role'] == 'kasir') ? 'bg-primary' : 'navbar-custom'; // Biru untuk Admin, Hitam elegan untuk Owner

// Variabel untuk menampung pesan pop-up SweetAlert (Awalnya kosong)
$pesan_sukses = "";
$pesan_error = "";
$pesan_sukses_pw = "";
$pesan_error_pw  = "";

// =================================================================================
// GANTI PASSWORD OWNER
// Dijalankan jika owner submit form ganti password dari modal di navbar
// =================================================================================
if (isset($_POST['btn_ganti_pw_owner'])) {
    $pw_lama     = $_POST['pw_lama'];
    $pw_baru     = $_POST['pw_baru_owner'];
    $pw_konfirm  = $_POST['pw_konfirm_owner'];

    // Ambil password hash owner dari database
    $stmt_cek = mysqli_prepare($koneksi, "SELECT password FROM t_user WHERE id_user = ?");
    mysqli_stmt_bind_param($stmt_cek, "i", $_SESSION['id_user']);
    mysqli_stmt_execute($stmt_cek);
    $res = mysqli_stmt_get_result($stmt_cek);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt_cek);

    if (!password_verify($pw_lama, $row['password'])) {
        $pesan_error_pw = "Password lama salah!";
    } elseif ($pw_baru !== $pw_konfirm) {
        $pesan_error_pw = "Password baru dan konfirmasi tidak cocok!";
    } elseif (strlen($pw_baru) < 6) {
        $pesan_error_pw = "Password baru minimal 6 karakter!";
    } else {
        $pw_hash = password_hash($pw_baru, PASSWORD_BCRYPT);
        $stmt_up = mysqli_prepare($koneksi, "UPDATE t_user SET password = ? WHERE id_user = ?");
        mysqli_stmt_bind_param($stmt_up, "si", $pw_hash, $_SESSION['id_user']);
        if (mysqli_stmt_execute($stmt_up)) {
            $pesan_sukses_pw = "Password berhasil diubah!";
        } else {
            $pesan_error_pw = "Gagal mengubah password.";
        }
        mysqli_stmt_close($stmt_up);
    }
}

// =================================================================================
// LOGIKA BACKEND: UPDATE STATUS CUCIAN (PROSES -> SELESAI -> DIAMBIL)
// Logika ini hanya akan dijalankan jika form "Selesai/Ambil" di-submit oleh kasir.
// =================================================================================
if (isset($_POST['btn_update_cuci'])) {
    $id_transaksi = (int) $_POST['id_transaksi'];
    // Whitelist status yang boleh — cegah inject nilai sembarangan
    $allowed = ['diterima', 'proses', 'selesai', 'dikirim'];
    $status_baru = in_array($_POST['status_cuci_baru'], $allowed) ? $_POST['status_cuci_baru'] : '';

    if ($status_baru === '') {
        $pesan_error = "Status tidak valid!";
    } else {
        $stmt = mysqli_prepare($koneksi, "UPDATE t_transaksi SET st_cuci = ? WHERE id_trans = ?");
        mysqli_stmt_bind_param($stmt, "si", $status_baru, $id_transaksi);
        if (mysqli_stmt_execute($stmt)) {
            $pesan_sukses = "Status cucian berhasil diperbarui menjadi: " . strtoupper($status_baru);
        } else {
            $pesan_error = "Gagal memperbarui status cucian.";
        }
        mysqli_stmt_close($stmt);
    }
}

// =================================================================================
// LOGIKA BACKEND: UPDATE STATUS PEMBAYARAN (BELUM LUNAS -> LUNAS)
// Akan dijalankan jika kasir menekan tombol "Bayar Lunas"
// =================================================================================
if (isset($_POST['btn_lunas'])) {
    $id_transaksi = (int) $_POST['id_transaksi'];

    $stmt = mysqli_prepare($koneksi, "UPDATE t_transaksi SET st_bayar = 'lunas' WHERE id_trans = ?");
    mysqli_stmt_bind_param($stmt, "i", $id_transaksi);
    if (mysqli_stmt_execute($stmt)) {
        $pesan_sukses = "Pembayaran untuk nota ini berhasil dilunasi!";
    } else {
        $pesan_error = "Gagal memproses pembayaran.";
    }
    mysqli_stmt_close($stmt);
}

// =================================================================================
// LOGIKA FILTER & SEARCH
// Ambil nilai filter dari GET
// =================================================================================
$f_cari      = isset($_GET['cari'])   ? trim($_GET['cari'])   : '';
// [FIX] Pakai filter Bulan & Tahun, lebih mudah dari pertanggal
$f_bulan     = isset($_GET['bulan'])  && (int)$_GET['bulan']  > 0     ? (int)$_GET['bulan']  : 0;
$f_tahun     = isset($_GET['tahun'])  && (int)$_GET['tahun']  > 2000  ? (int)$_GET['tahun']  : (int)date('Y');
$f_tgl_dari   = '';
$f_tgl_sampai = '';
$f_st_bayar  = isset($_GET['st_bayar'])   ? trim($_GET['st_bayar'])   : '';
$f_st_cuci   = isset($_GET['st_cuci'])    ? trim($_GET['st_cuci'])    : '';

// Bangun klausa WHERE secara dinamis berdasarkan filter yang diisi
$where_parts = [];
$bind_types  = "";
$bind_values = [];

// Filter pencarian nama pelanggan atau nomor nota
if ($f_cari !== '') {
    // Cek apakah inputan mengandung angka saja (kemungkinan cari nomor nota)
    if (is_numeric($f_cari)) {
        $where_parts[] = "t.id_trans = ?";
        $bind_types   .= "i";
        $bind_values[] = (int)$f_cari;
    } else {
        $where_parts[] = "p.nama LIKE ?";
        $bind_types   .= "s";
        $bind_values[] = "%$f_cari%";
    }
}

// Filter tanggal dari
// [FIX] Filter bulan & tahun
if ($f_bulan > 0) {
    $where_parts[] = "MONTH(t.tgl) = ?";
    $bind_types   .= "i";
    $bind_values[] = $f_bulan;
}
if ($f_tahun > 0) {
    $where_parts[] = "YEAR(t.tgl) = ?";
    $bind_types   .= "i";
    $bind_values[] = $f_tahun;
}

// Filter status bayar
if ($f_st_bayar !== '') {
    $where_parts[] = "t.st_bayar = ?";
    $bind_types   .= "s";
    $bind_values[] = $f_st_bayar;
}

// Filter status cucian
if ($f_st_cuci !== '') {
    $where_parts[] = "t.st_cuci = ?";
    $bind_types   .= "s";
    $bind_values[] = $f_st_cuci;
}

// Gabungkan semua kondisi filter menjadi satu klausa WHERE
$where_sql = count($where_parts) > 0 ? "WHERE " . implode(" AND ", $where_parts) : "";

// Query utama dengan filter dinamis menggunakan Prepared Statement
$sql_riwayat = "SELECT t.*, p.nama, p.hp, l.nama_paket 
                FROM t_transaksi t 
                JOIN t_pelanggan p ON t.id_pel = p.id_pel 
                LEFT JOIN t_layanan l ON t.id_layanan = l.id_layanan
                $where_sql
                ORDER BY t.id_trans DESC 
                LIMIT 200";

$stmt_riwayat = mysqli_prepare($koneksi, $sql_riwayat);

// Bind parameter hanya jika ada filter yang aktif
if (count($bind_values) > 0) {
    mysqli_stmt_bind_param($stmt_riwayat, $bind_types, ...$bind_values);
}

mysqli_stmt_execute($stmt_riwayat);
$hasil_riwayat = mysqli_stmt_get_result($stmt_riwayat);
$total_hasil   = mysqli_num_rows($hasil_riwayat);
?>

<!DOCTYPE html>
<html lang="id">
<head>
        <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/icontab.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Judul Tab Browser juga dibuat dinamis mengikuti role pengguna -->
    <title><?php echo ($_SESSION['role'] == 'kasir') ? 'Riwayat Order' : 'Laporan Transaksi'; ?> - LaundryKuy</title>
    
    <!-- Framework CSS Bootstrap 5 -->
    <!-- Library SweetAlert2 untuk Notifikasi Pop-up -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* Desain Background Web: Menggunakan gambar yang digelapkan dengan gradasi CSS */
        
        /* CSS Khusus untuk Warna Navbar Owner (Gradasi Hitam ke Biru Dongker) */
        
        /* Efek nyala tipis (highlight) saat kursor mouse melewati baris di dalam tabel */
        .table-hover tbody tr:hover { background-color: rgba(13, 110, 253, 0.05); transition: 0.3s; }
        
        /* Modifikasi ukuran badge (label status) agar lebih rapi */
        .badge-custom { font-size: 0.85rem; padding: 0.4em 0.8em; }

        /* Form filter: border biru saat focus */
        .form-control:focus, .form-select:focus {
            border-color: #0d6efd !important;
            box-shadow: 0 0 0 0.2rem rgba(13,110,253,0.15);
        }

        /* =====================================================================
           CSS MODAL STRUK CETAK
           ===================================================================== */
        .struk-wrapper {
            font-family: 'Courier New', Courier, monospace;
            width: 100%;
            max-width: 360px;
            margin: 0 auto;
            background: #fff;
            padding: 20px 24px;
            border: 1px dashed #ccc;
            border-radius: 8px;
            font-size: 13px;
            color: #1a1a1a;
        }
        .struk-wrapper .struk-logo {
            font-size: 20px;
            font-weight: 900;
            letter-spacing: 1px;
            text-align: center;
            margin-bottom: 2px;
        }
        .struk-wrapper .struk-sub {
            text-align: center;
            font-size: 11px;
            color: #555;
            margin-bottom: 10px;
        }
        .struk-wrapper .struk-divider {
            border: none;
            border-top: 1px dashed #aaa;
            margin: 8px 0;
        }
        .struk-wrapper .struk-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }
        .struk-wrapper .struk-row .label { color: #555; }
        .struk-wrapper .struk-row .value { font-weight: 700; text-align: right; max-width: 55%; }
        .struk-wrapper .struk-total-box {
            background: #0d6efd;
            color: #fff;
            border-radius: 6px;
            padding: 8px 12px;
            margin: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 15px;
            font-weight: 900;
        }
        .struk-wrapper .struk-footer {
            text-align: center;
            font-size: 11px;
            color: #777;
            margin-top: 10px;
            line-height: 1.6;
        }
        .struk-wrapper .struk-badge-lunas {
            display: inline-block;
            background: #198754;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 20px;
            letter-spacing: 1px;
        }
        .struk-wrapper .struk-badge-belum {
            display: inline-block;
            background: #dc3545;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 20px;
            letter-spacing: 1px;
        }

        /* CSS khusus saat jendela cetak dibuka — semua disembunyikan kecuali area struk */
        /* [FIX] Saat body.mode-cetak-struk: tampilkan hanya #print-struk-area */
        body.mode-cetak-struk > *:not(#print-struk-area) {
            display: none !important;
        }
        #print-struk-area {
            display: none;
        }
        body.mode-cetak-struk #print-struk-area {
            display: block !important;
        }
        @media print {
            body.mode-cetak-struk > *:not(#print-struk-area) {
                display: none !important;
            }
            body.mode-cetak-struk #print-struk-area {
                display: block !important;
                width: 76mm !important;
                margin: 0 auto !important;
                padding: 0 !important;
            }
            @page {
                size: 80mm auto;
                margin: 4mm;
            }
        }
    </style>
</head>
<body>
<div id="print-struk-area"></div>
<div class="kuy-layout">
<?php $halaman_aktif = 'riwayat'; include 'sidebarkuy.php'; ?>
<main class="kuy-main" id="kuyMain">
    <div class="kuy-topbar no-print">
        <span class="kuy-topbar-title">Riwayat Transaksi</span>
        <div class="kuy-topbar-right">
            <span class="kuy-topbar-user">
                <i class="bi bi-person-circle me-1"></i>
                <?php echo ucwords($_SESSION['username']); ?>
            </span>
        </div>
    </div>
    <div class="kuy-content">


    <!-- ===============================================================================
         NAVBAR (MENU ATAS)
         Tampilan warna dan urutan menu akan berubah tergantung siapa yang login
         =============================================================================== -->
    <!-- KONTEN UTAMA: TABEL TRANSAKSI -->
    <div class="container-fluid">

        <!-- Judul Halaman -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <?php if ($_SESSION['role'] == 'kasir'): ?>
                <h4 class="fw-bold mb-1" style="color:var(--text-dark);">
                    <i class="bi bi-card-list me-2" style="color:var(--gold);"></i>Riwayat & Status Cucian
                </h4>
                <p class="text-muted small mb-0">Pantau dan update status pengerjaan laundry pelanggan.</p>
                <?php else: ?>
                <h4 class="fw-bold mb-1" style="color:var(--text-dark);">
                    <i class="bi bi-journal-text me-2" style="color:var(--gold);"></i>Laporan Transaksi / Audit
                </h4>
                <p class="text-muted small mb-0">Pantau seluruh pergerakan nota transaksi oleh kasir.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="kuy-card p-4">

                    <!-- ================================================================
                         FORM FILTER & SEARCH
                         Menggunakan method GET agar hasil filter bisa di-share/bookmark
                         ================================================================ -->
                    <form method="GET" action="" id="formFilter">
                        <div class="row g-2 align-items-end mb-3">

                            <!-- Kolom 1: Cari nama / nomor nota -->
                            <div class="col-md-3">
                                <label class="form-label fw-bold small text-muted mb-1">
                                    <i class="bi bi-search me-1"></i>Cari Pelanggan / Nota
                                </label>
                                <input type="text" class="form-control border-2" name="cari"
                                    placeholder="Nama atau nomor nota..."
                                    value="<?php echo htmlspecialchars($f_cari); ?>">
                            </div>

                            <!-- [FIX] Kolom 2: Pilih Bulan (lebih mudah dari pertanggal) -->
                            <div class="col-md-2">
                                <label class="form-label fw-bold small text-muted mb-1">
                                    <i class="bi bi-calendar3 me-1"></i>Bulan
                                </label>
                                <select class="form-select border-2" name="bulan">
                                    <option value="0">Semua Bulan</option>
                                    <?php
                                    $nama_bulan = ['','Januari','Februari','Maret','April','Mei','Juni',
                                                   'Juli','Agustus','September','Oktober','November','Desember'];
                                    for ($i=1; $i<=12; $i++):
                                    ?>
                                    <option value="<?php echo $i; ?>" <?php echo $f_bulan==$i?'selected':''; ?>>
                                        <?php echo $nama_bulan[$i]; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <!-- Kolom 3: Pilih Tahun -->
                            <div class="col-md-2">
                                <label class="form-label fw-bold small text-muted mb-1">
                                    <i class="bi bi-calendar-range me-1"></i>Tahun
                                </label>
                                <select class="form-select border-2" name="tahun">
                                    <option value="0">Semua Tahun</option>
                                    <?php for ($y=date('Y'); $y>=2023; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $f_tahun==$y?'selected':''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <!-- Kolom 4: Status bayar -->
                            <div class="col-md-2">
                                <label class="form-label fw-bold small text-muted mb-1">
                                    <i class="bi bi-cash me-1"></i>Status Bayar
                                </label>
                                <select class="form-select border-2" name="st_bayar">
                                    <option value="">Semua</option>
                                    <option value="lunas"       <?php echo $f_st_bayar == 'lunas'        ? 'selected' : ''; ?>>Lunas</option>
                                    <option value="belum lunas" <?php echo $f_st_bayar == 'belum lunas'  ? 'selected' : ''; ?>>Belum Lunas</option>
                                </select>
                            </div>

                            <!-- Kolom 5: Status cucian -->
                            <div class="col-md-2">
                                <label class="form-label fw-bold small text-muted mb-1">
                                    <i class="bi bi-basket me-1"></i>Status Cucian
                                </label>
                                <select class="form-select border-2" name="st_cuci">
                                    <option value="">Semua</option>
                                    <option value="diterima" <?php echo $f_st_cuci == 'diterima' ? 'selected' : ''; ?>>Diterima</option>
                                    <option value="proses"   <?php echo $f_st_cuci == 'proses'   ? 'selected' : ''; ?>>Diproses</option>
                                    <option value="selesai"  <?php echo $f_st_cuci == 'selesai'  ? 'selected' : ''; ?>>Selesai</option>
                                    <option value="dikirim"  <?php echo $f_st_cuci == 'dikirim'  ? 'selected' : ''; ?>>Dikirim</option>
                                </select>
                            </div>

                            <!-- Kolom 6: Tombol aksi filter -->
                            <div class="col-md-1">
                                <div class="d-flex gap-1">
                                    <button type="submit" class="btn btn-primary fw-bold w-100" title="Terapkan Filter">
                                        <i class="bi bi-funnel-fill"></i>
                                    </button>
                                    <a href="riwayatkuy.php" class="btn btn-outline-secondary fw-bold" title="Reset Filter">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </a>
                                </div>
                            </div>

                        </div>
                    </form>

                    <!-- Info hasil filter -->
                    <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">
                        <div class="small fw-semibold text-muted">
                            <?php
                            $ada_filter = ($f_cari || $f_tgl_dari || $f_tgl_sampai || $f_st_bayar || $f_st_cuci);
                            if ($ada_filter): ?>
                                <i class="bi bi-funnel-fill text-primary me-1"></i>
                                Menampilkan <strong class="text-dark"><?php echo $total_hasil; ?></strong> hasil
                                <?php if ($f_cari): ?>
                                    untuk "<strong><?php echo htmlspecialchars($f_cari); ?></strong>"
                                <?php endif; ?>
                                <?php if ($f_tgl_dari || $f_tgl_sampai): ?>
                                    periode
                                    <strong><?php echo $f_tgl_dari ? date('d M Y', strtotime($f_tgl_dari)) : '...'; ?></strong>
                                    —
                                    <strong><?php echo $f_tgl_sampai ? date('d M Y', strtotime($f_tgl_sampai)) : '...'; ?></strong>
                                <?php endif; ?>
                            <?php else: ?>
                                <i class="bi bi-list-ul text-secondary me-1"></i>
                                Menampilkan <strong class="text-dark"><?php echo $total_hasil; ?></strong> transaksi terbaru
                            <?php endif; ?>
                        </div>
                        <?php if ($ada_filter): ?>
                        <a href="riwayatkuy.php" class="btn btn-sm btn-outline-danger rounded-pill fw-bold">
                            <i class="bi bi-x-circle me-1"></i>Hapus Filter
                        </a>
                        <?php endif; ?>
                    </div>

                    <!-- Area Tabel Responsif (Bisa digeser kanan-kiri di layar kecil/HP) -->
                    <div class="table-responsive mt-2">
                        <table class="table table-hover align-middle border">
                            <!-- Kolom Judul Tabel (Warna Gelap) -->
                            <thead class="table-dark">
                                <tr>
                                    <th class="py-3 px-3 text-center">No. Nota</th>
                                    <th class="py-3 px-3">Tanggal</th>
                                    <th class="py-3 px-3">Pelanggan</th>
                                    <th class="py-3 px-3">Paket Layanan</th>
                                    <th class="py-3 px-3">Berat (Kg)</th>
                                    <th class="py-3 px-3">Total Tagihan</th>
                                    <th class="py-3 px-3 text-center">Estimasi</th>
                                    <th class="py-3 px-3 text-center">Status Bayar</th>
                                    <th class="py-3 px-3 text-center">Status Cucian</th>
                                    <th class="py-3 px-3 text-center">Aksi Cepat</th>
                                </tr>
                            </thead>
                            <!-- Isi Data Tabel -->
                            <tbody>
                                <?php
                                // Hasil query sudah disiapkan di atas dengan filter dinamis
                                if ($total_hasil > 0) {
                                    while ($r = mysqli_fetch_assoc($hasil_riwayat)) {
                                ?>
                                <tr>
                                    <!-- Fungsi sprintf("%02d", angka) digunakan untuk membuat nomor jadi 2 digit (1 jadi 01) -->
                                    <td class="text-center fw-bold text-primary">KUY - <?php echo sprintf("%02d", $r['id_trans']); ?></td>
                                    
                                    <!-- Mengubah format tanggal MySQL (Y-m-d) menjadi format manusiawi (Contoh: 24 May 2026) -->
                                    <td><?php echo date('d M Y', strtotime($r['tgl'])); ?></td>
                                    
                                    <!-- Kolom Pelanggan menampilkan Nama (tebal) dan Nomor HP (kecil di bawahnya) -->
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo $r['nama']; ?></div>
                                        <div class="small text-muted"><i class="bi bi-telephone-fill me-1"></i><?php echo $r['hp']; ?></div>
                                    </td>
                                    
                                    <!-- Kolom Paket Layanan (Jika paket sudah dihapus dari sistem, muncul teks 'Paket Terhapus') -->
                                    <td>
                                        <span class="badge bg-light text-dark border rounded-pill px-3 py-2 fw-semibold shadow-sm">
                                            <i class="bi bi-tag-fill text-primary me-1"></i> 
                                            <?php echo $r['nama_paket'] ? ucwords($r['nama_paket']) : 'Paket Terhapus'; ?>
                                        </span>
                                    </td>
                                    
                                    <td class="fw-semibold"><?php echo $r['berat']; ?> Kg</td>
                                    
                                    <!-- Format angka jadi Rupiah dengan fungsi number_format -->
                                    <td class="fw-bolder text-dark">
                                        Rp <?php echo number_format($r['total'], 0, ',', '.'); ?>
                                        <?php if (!empty($r['diskon']) && $r['diskon'] > 0): ?>
                                        <div class="small text-success fw-normal">
                                            <i class="bi bi-ticket-perforated me-1"></i>
                                            -Rp <?php echo number_format($r['diskon'], 0, ',', '.'); ?>
                                        </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Estimasi Selesai -->
                                    <td class="text-center">
                                        <?php if (!empty($r['estimasi_selesai'])): ?>
                                            <?php
                                            $est_ts  = strtotime($r['estimasi_selesai']);
                                            $now_ts  = time();
                                            $lewat   = $est_ts < $now_ts;
                                            $sudah   = ($r['st_cuci'] == 'selesai' || $r['st_cuci'] == 'dikirim');
                                            ?>
                                            <?php if ($sudah): ?>
                                                <span class="badge bg-success rounded-pill" style="font-size:0.75rem;">
                                                    <i class="bi bi-check2"></i> Selesai
                                                </span>
                                            <?php elseif ($lewat): ?>
                                                <span class="badge bg-danger rounded-pill" style="font-size:0.75rem;" title="<?php echo date('d M Y H:i', $est_ts); ?>">
                                                    <i class="bi bi-exclamation-triangle"></i> Terlambat
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-info text-dark rounded-pill" style="font-size:0.75rem;">
                                                    <?php echo date('d M, H:i', $est_ts); ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- LOGIKA TAMPILAN BADGE STATUS BAYAR -->
                                    <td class="text-center">
                                        <?php if ($r['st_bayar'] == 'lunas') { ?>
                                            <span class="badge bg-success rounded-pill badge-custom"><i class="bi bi-check2-all me-1"></i>Lunas</span>
                                        <?php } else { ?>
                                            <span class="badge bg-danger rounded-pill badge-custom"><i class="bi bi-x-circle me-1"></i>Belum</span>
                                        <?php } ?>
                                    </td>

                                    <!-- LOGIKA TAMPILAN BADGE STATUS CUCIAN -->
                                    <td class="text-center">
                                        <?php 
                                        if ($r['st_cuci'] == 'diterima') {
                                            echo '<span class="badge bg-primary rounded-pill badge-custom"><i class="bi bi-inbox me-1"></i>Diterima</span>';
                                        } elseif ($r['st_cuci'] == 'proses') {
                                            echo '<span class="badge bg-warning text-dark rounded-pill badge-custom"><i class="bi bi-hourglass-split me-1"></i>Diproses</span>';
                                        } elseif ($r['st_cuci'] == 'selesai') {
                                            echo '<span class="badge bg-info text-dark rounded-pill badge-custom"><i class="bi bi-check-circle me-1"></i>Selesai (Siap)</span>';
                                        } else {
                                            echo '<span class="badge bg-success rounded-pill badge-custom"><i class="bi bi-send-fill me-1"></i>Dikirim</span>';
                                        }
                                        ?>
                                    </td>

                                    <!-- ===============================================================================
                                         KOLOM AKSI CEPAT (SISTEM ANTI-FRAUD OWNER)
                                         Bagian ini memisahkan hak antara kasir (bisa klik) dan pemilik (hanya melihat)
                                         =============================================================================== -->
                                    <td class="text-center">
                                        <?php
                                        // Sekarang KASIR dan OWNER sama-sama bisa melakukan aksi
                                        $bisa_aksi = ($_SESSION['role'] == 'kasir' || $_SESSION['role'] == 'owner');
                                        if ($bisa_aksi):
                                        ?>
                                            <div class="d-flex justify-content-center gap-1 flex-wrap">

                                                <!-- DITERIMA: muncul saat status masih 'diterima' -->
                                                <?php if ($r['st_cuci'] == 'diterima'): ?>
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="id_transaksi" value="<?php echo $r['id_trans']; ?>">
                                                        <input type="hidden" name="status_cuci_baru" value="proses">
                                                        <button type="button" class="btn btn-sm btn-primary fw-bold shadow-sm"
                                                            title="Mulai Proses Cuci"
                                                            onclick="konfirmasiAksi(this.form, 'proses')">
                                                            <i class="bi bi-play-fill"></i> Proses
                                                        </button>
                                                    </form>

                                                <!-- SELESAI: muncul saat status 'proses' -->
                                                <?php elseif ($r['st_cuci'] == 'proses'): ?>
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="id_transaksi" value="<?php echo $r['id_trans']; ?>">
                                                        <input type="hidden" name="status_cuci_baru" value="selesai">
                                                        <button type="button" class="btn btn-sm btn-info fw-bold shadow-sm text-dark"
                                                            title="Tandai Selesai"
                                                            onclick="konfirmasiAksi(this.form, 'selesai')">
                                                            <i class="bi bi-check-lg"></i> Selesai
                                                        </button>
                                                    </form>

                                                <!-- KIRIM: muncul saat status 'selesai' -->
                                                <?php elseif ($r['st_cuci'] == 'selesai'): ?>
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="id_transaksi" value="<?php echo $r['id_trans']; ?>">
                                                        <input type="hidden" name="status_cuci_baru" value="dikirim">
                                                        <button type="button" class="btn btn-sm btn-success fw-bold shadow-sm"
                                                            title="Kirim / Serahkan ke Pelanggan"
                                                            onclick="konfirmasiAksi(this.form, 'kirim')">
                                                            <i class="bi bi-send-fill"></i> Kirim
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <!-- BAYAR: muncul jika belum lunas -->
                                                <?php if ($r['st_bayar'] == 'belum lunas' || $r['st_bayar'] == 'belum'): ?>
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="id_transaksi" value="<?php echo $r['id_trans']; ?>">
                                                        <button type="button" class="btn btn-sm btn-warning fw-bold shadow-sm text-dark"
                                                            title="Bayar Lunas"
                                                            onclick="konfirmasiAksi(this.form, 'lunas')">
                                                            <i class="bi bi-cash"></i> Bayar
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <!-- STRUK: muncul jika sudah selesai atau dikirim -->
                                                <?php if ($r['st_cuci'] == 'selesai' || $r['st_cuci'] == 'dikirim'): ?>
                                                    <button type="button"
                                                        class="btn btn-sm btn-dark fw-bold shadow-sm"
                                                        title="Cetak Struk"
                                                        onclick="cetakStruk(
                                                            '<?php echo sprintf("%02d", $r['id_trans']); ?>',
                                                            '<?php echo date('d M Y', strtotime($r['tgl'])); ?>',
                                                            '<?php echo addslashes($r['nama']); ?>',
                                                            '<?php echo $r['hp']; ?>',
                                                            '<?php echo addslashes($r['nama_paket'] ? ucwords($r['nama_paket']) : 'Paket Terhapus'); ?>',
                                                            '<?php echo $r['berat']; ?>',
                                                            '<?php echo $r['harga_saat_transaksi']; ?>',
                                                            '<?php echo $r['total']; ?>',
                                                            '<?php echo $r['st_bayar']; ?>'
                                                        )">
                                                        <i class="bi bi-printer-fill"></i> Struk
                                                    </button>
                                                <?php endif; ?>

                                            </div>
                                        <?php else: ?>
                                            <span class="badge bg-light border text-secondary">
                                                <i class="bi bi-lock-fill me-1"></i>Hanya Baca
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php 
                                    } // Akhir dari perulangan baris tabel
                                } else {
                                    // Tampilan Empty State
                                ?>
                                    <tr>
                                        <td colspan="10" class="text-center py-5 text-muted fw-bold">
                                            <?php if ($ada_filter): ?>
                                                <i class="bi bi-search fs-2 d-block mb-2 text-secondary"></i>
                                                Tidak ada transaksi yang cocok dengan filter yang dipilih.<br>
                                                <a href="riwayatkuy.php" class="btn btn-sm btn-outline-primary mt-3 rounded-pill fw-bold">
                                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Filter
                                                </a>
                                            <?php else: ?>
                                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                                Belum ada data transaksi laundry.
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- ===============================================================================
         MODAL STRUK CETAK TRANSAKSI
         Dipanggil oleh fungsi cetakStruk() melalui Javascript
         =============================================================================== -->
    <div class="modal fade" id="modalStruk" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <!-- Header Modal -->
                <div class="modal-header bg-dark text-white rounded-top-4">
                    <h5 class="modal-title fw-bold"><i class="bi bi-printer-fill me-2"></i>Struk Transaksi Laundry</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <!-- Body Modal: Area Struk -->
                <div class="modal-body p-4">
                    <!-- ID areaCetak dipakai oleh CSS @media print agar hanya bagian ini yang dicetak -->
                    <div id="areaCetak">
                        <div class="struk-wrapper">

                            <!-- HEADER STRUK -->
                            <div class="struk-logo">🧺 LaundryKuy</div>
                            <div class="struk-sub">Jasa Laundry Terpercaya<br>Terima Kasih Telah Mempercayakan Cucian Anda</div>

                            <hr class="struk-divider">

                            <!-- INFO NOTA -->
                            <div class="struk-row">
                                <span class="label">No. Nota</span>
                                <span class="value" id="struk-nota"></span>
                            </div>
                            <div class="struk-row">
                                <span class="label">Tanggal</span>
                                <span class="value" id="struk-tgl"></span>
                            </div>

                            <hr class="struk-divider">

                            <!-- INFO PELANGGAN -->
                            <div class="struk-row">
                                <span class="label">Pelanggan</span>
                                <span class="value" id="struk-nama"></span>
                            </div>
                            <div class="struk-row">
                                <span class="label">No. HP</span>
                                <span class="value" id="struk-hp"></span>
                            </div>

                            <hr class="struk-divider">

                            <!-- DETAIL CUCIAN -->
                            <div class="struk-row">
                                <span class="label">Paket</span>
                                <span class="value" id="struk-paket"></span>
                            </div>
                            <div class="struk-row">
                                <span class="label">Berat</span>
                                <span class="value" id="struk-berat"></span>
                            </div>
                            <div class="struk-row">
                                <span class="label">Tarif / Satuan</span>
                                <span class="value" id="struk-harga"></span>
                            </div>

                            <hr class="struk-divider">

                            <!-- TOTAL TAGIHAN -->
                            <div class="struk-total-box">
                                <span>TOTAL</span>
                                <span id="struk-total"></span>
                            </div>

                            <!-- STATUS PEMBAYARAN -->
                            <div class="text-center my-2">
                                <span id="struk-status-bayar"></span>
                            </div>

                            <hr class="struk-divider">

                            <!-- FOOTER STRUK -->
                            <div class="struk-footer">
                                Barang yang tidak diambil lebih dari 7 hari<br>
                                tidak menjadi tanggung jawab kami.<br>
                                <strong>— Terima Kasih & Sampai Jumpa 🙏 —</strong>
                            </div>

                        </div><!-- end .struk-wrapper -->
                    </div><!-- end #areaCetak -->
                </div>
                <!-- Footer Modal: Tombol Aksi -->
                <div class="modal-footer bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i> Tutup
                    </button>
                    <button type="button" class="btn btn-dark fw-bold px-4" id="btnCetakStruk">
                        <i class="bi bi-printer-fill me-2"></i> Cetak Sekarang
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- END MODAL STRUK -->

    <!-- Panggil File Utama Javascript Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- ===============================================================================
         KUMPULAN LOGIKA JAVASCRIPT CUSTOM (Pop-up & Form Handling)
         =============================================================================== -->
    <script>
        // 1. MENCEGAH BUG DOUBLE SUBMIT (Saat browser di-back atau di-refresh)
        // Baris ini akan menghapus riwayat POST agar notifikasi tidak muncul berulang-ulang
        if ( window.history.replaceState ) { window.history.replaceState( null, null, window.location.href ); }

        // 2. FUNGSI CETAK STRUK
        // Dipanggil tombol Struk, mengisi data ke dalam Modal lalu membukanya.
        function cetakStruk(noNota, tgl, nama, hp, paket, berat, hargaSatuan, total, stBayar) {
            // Format angka ke Rupiah pakai Intl.NumberFormat standar Indonesia
            const fmt = (n) => 'Rp ' + new Intl.NumberFormat('id-ID').format(n);

            // Isi setiap elemen di dalam modal struk dengan data dari parameter
            document.getElementById('struk-nota').textContent   = 'KUY-' + noNota;
            document.getElementById('struk-tgl').textContent    = tgl;
            document.getElementById('struk-nama').textContent   = nama;
            document.getElementById('struk-hp').textContent     = hp;
            document.getElementById('struk-paket').textContent  = paket;
            document.getElementById('struk-berat').textContent  = berat + ' Kg';
            document.getElementById('struk-harga').textContent  = fmt(hargaSatuan);
            document.getElementById('struk-total').textContent  = fmt(total);

            // Render badge status bayar (hijau = lunas, merah = belum)
            const elStatus = document.getElementById('struk-status-bayar');
            if (stBayar === 'lunas') {
                elStatus.innerHTML = '<span class="struk-badge-lunas">✔ LUNAS</span>';
            } else {
                elStatus.innerHTML = '<span class="struk-badge-belum">✘ BELUM LUNAS</span>';
            }

            // Buka Modal Bootstrap
            const modal = new bootstrap.Modal(document.getElementById('modalStruk'));
            modal.show();
        }

        // [FIX] Cetak struk: clone ke div kosong, tambah class body, print, bersihkan
        document.getElementById('btnCetakStruk').addEventListener('click', function() {
            var strukHtml = document.getElementById('areaCetak').innerHTML;
            var printDiv  = document.getElementById('print-struk-area');
            printDiv.innerHTML = strukHtml;
            document.body.classList.add('mode-cetak-struk');
            setTimeout(function() {
                window.print();
                setTimeout(function() {
                    document.body.classList.remove('mode-cetak-struk');
                    printDiv.innerHTML = '';
                }, 800);
            }, 150);
        });

        // 2. FUNGSI SWEET-ALERT — alur 4 status: diterima > proses > selesai > dikirim
        function konfirmasiAksi(form, aksi) {
            let titleText = ""; let textMsg = ""; let confirmBtn = ""; let colorBtn = ""; let inputName = "";

            if (aksi === 'proses') {
                titleText = "Mulai Proses Cuci?";
                textMsg = "Cucian ini akan mulai diproses.";
                confirmBtn = "Ya, Proses!"; colorBtn = "#0d6efd"; inputName = "btn_update_cuci";
            } else if (aksi === 'selesai') {
                titleText = "Tandai Selesai?";
                textMsg = "Apakah cucian ini sudah selesai diproses?";
                confirmBtn = "Ya, Selesai!"; colorBtn = "#0dcaf0"; inputName = "btn_update_cuci";
            } else if (aksi === 'kirim') {
                titleText = "Kirim ke Pelanggan?";
                textMsg = "Cucian ini akan dikirim atau diserahkan ke pelanggan.";
                confirmBtn = "Ya, Kirim!"; colorBtn = "#198754"; inputName = "btn_update_cuci";
            } else if (aksi === 'lunas') {
                titleText = "Pelunasan Tagihan";
                textMsg = "Yakin pelanggan ini sudah membayar tagihannya secara Lunas?";
                confirmBtn = "Ya, Sudah Lunas!"; colorBtn = "#198754"; inputName = "btn_lunas";
            }

            Swal.fire({
                title: titleText, text: textMsg, icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: colorBtn, cancelButtonColor: '#d33',
                cancelButtonText: 'Batal', confirmButtonText: confirmBtn
            }).then((result) => {
                if (result.isConfirmed) {
                    let hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = inputName;
                    hiddenInput.value = '1';
                    form.appendChild(hiddenInput);
                    form.submit();
                }
            });
        }
    </script>

    <!-- ===============================================================================
         MENAMPILKAN NOTIFIKASI BERHASIL / GAGAL DARI PHP (via SweetAlert2)
         Jika variabel PHP terisi, otomatis script ini dicetak ke HTML dan pop-up muncul
         =============================================================================== -->
    <?php if ($pesan_sukses != "") { ?>
    <script>Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?php echo $pesan_sukses; ?>', showConfirmButton: false, timer: 2000 });</script>
    <?php } ?>
    
    <?php if ($pesan_error != "") { ?>
    <script>Swal.fire({ icon: 'error', title: 'Oops...', text: '<?php echo $pesan_error; ?>' });</script>
    <?php } ?>
    <!-- ===============================================================================
         MODAL GANTI PASSWORD OWNER
         Bisa diakses dari tombol kunci di navbar semua halaman owner
         =============================================================================== -->
    <!-- MODAL GANTI PASSWORD OWNER -->
    <div class="modal fade" id="modalGantiPwOwner" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header text-white rounded-top-4" style="background: linear-gradient(to right, #0f172a, #1e293b);">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-key-fill me-2 text-warning"></i>Ganti Password Saya
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" id="formGantiPwOwner">
                    <div class="modal-body p-4">

                        <div class="alert alert-light border rounded-3 small mb-4">
                            <i class="bi bi-shield-lock-fill text-primary me-1"></i>
                            Perubahan password berlaku langsung. Pastikan kamu ingat password baru sebelum menyimpan.
                        </div>

                        <!-- Password Lama -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Password Lama</label>
                            <div class="position-relative">
                                <input type="password" class="form-control form-control-lg pe-5"
                                    name="pw_lama" id="pw_lama_owner"
                                    placeholder="Masukkan password saat ini" required>
                                <i class="bi bi-eye position-absolute top-50 end-0 translate-middle-y me-3"
                                    style="cursor:pointer; color:#6c757d;"
                                    onclick="togglePwOwner('pw_lama_owner', this)"></i>
                            </div>
                        </div>

                        <!-- Password Baru -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Password Baru</label>
                            <div class="position-relative">
                                <input type="password" class="form-control form-control-lg pe-5"
                                    name="pw_baru_owner" id="pw_baru_owner"
                                    placeholder="Minimal 6 karakter" required minlength="6">
                                <i class="bi bi-eye position-absolute top-50 end-0 translate-middle-y me-3"
                                    style="cursor:pointer; color:#6c757d;"
                                    onclick="togglePwOwner('pw_baru_owner', this)"></i>
                            </div>
                        </div>

                        <!-- Konfirmasi Password Baru -->
                        <div class="mb-1">
                            <label class="form-label fw-bold">Konfirmasi Password Baru</label>
                            <div class="position-relative">
                                <input type="password" class="form-control form-control-lg pe-5"
                                    name="pw_konfirm_owner" id="pw_konfirm_owner"
                                    placeholder="Ulangi password baru" required>
                                <i class="bi bi-eye position-absolute top-50 end-0 translate-middle-y me-3"
                                    style="cursor:pointer; color:#6c757d;"
                                    onclick="togglePwOwner('pw_konfirm_owner', this)"></i>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer bg-light rounded-bottom-4">
                        <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-dark fw-bold px-4"
                            onclick="konfirmasiGantiPw()">
                            <i class="bi bi-save me-1"></i> Simpan Password Baru
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- END MODAL GANTI PASSWORD OWNER -->

    <script>
        // Toggle show/hide password di modal owner
        function togglePwOwner(inputId, iconEl) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                iconEl.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                input.type = 'password';
                iconEl.classList.replace('bi-eye-slash', 'bi-eye');
            }
        }

        // Konfirmasi SweetAlert sebelum submit ganti password
        function konfirmasiGantiPw() {
            const form = document.getElementById('formGantiPwOwner');
            if (!form.checkValidity()) { form.reportValidity(); return; }

            Swal.fire({
                title: 'Ganti Password?',
                text: 'Password baru akan aktif langsung setelah disimpan.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#0f172a',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Simpan!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    let input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'btn_ganti_pw_owner';
                    input.value = '1';
                    form.appendChild(input);
                    form.submit();
                }
            });
        }
    </script>

    <?php if (!empty($pesan_sukses_pw)): ?>
    <script>Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?php echo $pesan_sukses_pw; ?>', showConfirmButton: false, timer: 2000 });</script>
    <?php endif; ?>
    <?php if (!empty($pesan_error_pw)): ?>
    <script>Swal.fire({ icon: 'error', title: 'Gagal!', text: '<?php echo addslashes($pesan_error_pw); ?>' });</script>
    <?php endif; ?>

    </div><!-- end kuy-content -->
</main><!-- end kuy-main -->
</div><!-- end kuy-layout -->
</body>
</html>