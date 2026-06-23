<?php
/**
 * =================================================================================
 * FILE: transaksikuy.php
 * DESKRIPSI: Halaman Kasir (Point of Sales) khusus untuk Admin.
 * FUNGSI: Mendaftarkan pelanggan baru/lama, memilih paket cuci, menghitung total 
 * harga secara otomatis dari database, dan mencetak nota transaksi.
 * HAK AKSES: KHUSUS ADMIN (Owner dilarang masuk agar tidak merusak data operasional).
 * =================================================================================
 */

// 1. MEMULAI SESSION
// Wajib dipanggil di baris paling awal agar sistem tahu siapa Admin yang sedang melayani.
session_start();

$halaman_aktif = 'transaksi';

// 2. KONEKSI DATABASE
// Menghubungkan halaman ini dengan database 'db_laundry' melalui file koneksi.
include 'connectkuy.php';

// 3. SISTEM KEAMANAN (PROTEKSI AKSES KHUSUS ADMIN)
// Jika belum login, ATAU role-nya BUKAN admin, tendang ke halaman login.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'kasir') {
    header("Location: loginkuy.php");
    exit();
}

// Variabel untuk menampung pesan pop-up SweetAlert (Awalnya kosong)
$pesan_sukses = "";
$pesan_error = "";

// =================================================================================
// LOGIKA BACKEND: PROSES SIMPAN TRANSAKSI (BUAT NOTA)
// Akan dijalankan KETIKA kasir menekan tombol "SIMPAN TRANSAKSI & BUAT NOTA" di form
// =================================================================================
if (isset($_POST['btn_simpan'])) {
    
    $jenis_pelanggan = $_POST['jenis_pelanggan'];
    $berat           = (float) $_POST['berat'];
    $id_layanan      = $_POST['id_layanan'];
    $tgl_transaksi   = date('Y-m-d H:i:s');
    $id_user         = $_SESSION['id_user'];
    $id_pel          = "";
    $kode_voucher    = strtoupper(trim($_POST['kode_voucher'] ?? ''));

    // ── AMBIL HARGA + ESTIMASI PAKET DARI DATABASE ──────────────────
    $q_layanan = mysqli_prepare($koneksi, "SELECT harga_fix, estimasi_jam FROM t_layanan WHERE id_layanan=?");
    mysqli_stmt_bind_param($q_layanan, "i", $id_layanan);
    mysqli_stmt_execute($q_layanan);
    $d_layanan = mysqli_fetch_assoc(mysqli_stmt_get_result($q_layanan));
    mysqli_stmt_close($q_layanan);

    $total_sebelum_diskon = 0;
    if ($d_layanan) {
        $harga_saat_transaksi = (float)$d_layanan['harga_fix'];
        $estimasi_jam         = (int)($d_layanan['estimasi_jam'] ?? 24);
        $total_sebelum_diskon = $berat * $harga_saat_transaksi;
        $estimasi_selesai     = date('Y-m-d H:i:s', strtotime("+{$estimasi_jam} hours"));
    } else {
        $pesan_error = "Pilihan paket layanan tidak valid!";
    }

    // ── VALIDASI & HITUNG VOUCHER ────────────────────────────────────
    $diskon     = 0;
    $id_voucher = null;
    $total_bayar = $total_sebelum_diskon ?? 0;

    if ($kode_voucher !== '' && $pesan_error == "") {
        $today = date('Y-m-d');
        $stmt_v = mysqli_prepare($koneksi,
            "SELECT * FROM t_voucher WHERE kode=? AND aktif=1
             AND (tgl_mulai IS NULL OR tgl_mulai <= ?)
             AND (tgl_selesai IS NULL OR tgl_selesai >= ?)");
        mysqli_stmt_bind_param($stmt_v, "sss", $kode_voucher, $today, $today);
        mysqli_stmt_execute($stmt_v);
        $voucher = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_v));
        mysqli_stmt_close($stmt_v);

        if (!$voucher) {
            $pesan_error = "Kode voucher '$kode_voucher' tidak valid, sudah kadaluarsa, atau tidak aktif!";
        } elseif ($voucher['kuota'] > 0 && $voucher['terpakai'] >= $voucher['kuota']) {
            $pesan_error = "Voucher '$kode_voucher' sudah habis kuotanya!";
        } elseif ($voucher['min_transaksi'] > 0 && $total_sebelum_diskon < $voucher['min_transaksi']) {
            $pesan_error = "Total transaksi minimal Rp " . number_format($voucher['min_transaksi'],0,',','.') . " untuk menggunakan voucher ini!";
        } else {
            // Hitung diskon
            if ($voucher['tipe'] === 'persen') {
                $diskon = $total_sebelum_diskon * ($voucher['nilai'] / 100);
                // Terapkan batas maksimum diskon jika ada
                if ($voucher['max_diskon'] > 0 && $diskon > $voucher['max_diskon']) {
                    $diskon = $voucher['max_diskon'];
                }
            } else {
                $diskon = $voucher['nilai'];
            }
            // Pastikan diskon tidak melebihi total
            $diskon      = min($diskon, $total_sebelum_diskon);
            $total_bayar = $total_sebelum_diskon - $diskon;
            $id_voucher  = $voucher['id_voucher'];
        }
    } elseif ($pesan_error == "") {
        $total_bayar = $total_sebelum_diskon;
    }

    // ── PROSES PELANGGAN ─────────────────────────────────────────────
    if ($jenis_pelanggan == 'baru' && $pesan_error == "") {
        $nama_baru   = mysqli_real_escape_string($koneksi, $_POST['nama_baru']);
        $hp_raw      = preg_replace('/[^0-9]/', '', $_POST['hp_baru']);
        $alamat_baru = mysqli_real_escape_string($koneksi, $_POST['alamat_baru'] ?? '');

        // Validasi & normalisasi HP Indonesia
        if (!preg_match('/^(08|628|62)[0-9]{7,12}$/', $hp_raw)) {
            $pesan_error = "Nomor HP tidak valid! Gunakan format: 08xx (minimal 10 digit).";
        } else {
            if (substr($hp_raw, 0, 3) === '628') $hp_raw = '0' . substr($hp_raw, 2);
            $hp_baru = mysqli_real_escape_string($koneksi, $hp_raw);
            $stmt_pel = mysqli_prepare($koneksi, "INSERT INTO t_pelanggan (nama, hp, alamat) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt_pel, "sss", $nama_baru, $hp_baru, $alamat_baru);
        $q = mysqli_stmt_execute($stmt_pel);
        if ($q) { mysqli_stmt_close($stmt_pel); }
            if ($q) { $id_pel = mysqli_insert_id($koneksi); }
            else     { $pesan_error = "Gagal mendaftarkan pelanggan baru: " . mysqli_error($koneksi); }
        } // end validasi HP
    } elseif ($jenis_pelanggan == 'lama' && $pesan_error == "") {
        if (isset($_POST['id_pelanggan_lama'])) { $id_pel = $_POST['id_pelanggan_lama']; }
        else { $pesan_error = "Silakan pilih nama pelanggan dari daftar!"; }
    }

    // ── SIMPAN TRANSAKSI ─────────────────────────────────────────────
    if ($id_pel != "" && $pesan_error == "") {
        $stmt_trx = mysqli_prepare($koneksi,
            "INSERT INTO t_transaksi
             (id_pel, id_layanan, id_user, tgl, estimasi_selesai, berat, harga_saat_transaksi,
              total_sebelum_diskon, id_voucher, diskon, total, st_bayar, st_cuci)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,'belum lunas','proses')");
        mysqli_stmt_bind_param($stmt_trx, "iiissdddidd",
            $id_pel, $id_layanan, $id_user, $tgl_transaksi, $estimasi_selesai,
            $berat, $harga_saat_transaksi, $total_sebelum_diskon,
            $id_voucher, $diskon, $total_bayar);

        if (mysqli_stmt_execute($stmt_trx)) {
            // Update kuota voucher terpakai
            if ($id_voucher) {
                $stmt_v = mysqli_prepare($koneksi, "UPDATE t_voucher SET terpakai=terpakai+1 WHERE id_voucher=?");
                mysqli_stmt_bind_param($stmt_v, "i", $id_voucher);
                mysqli_stmt_execute($stmt_v);
                mysqli_stmt_close($stmt_v);
            }
            // [FIX] Hapus tag <strong> — SweetAlert text: tidak render HTML.
            // Tag <strong> muncul mentah di layar (tampil &lt;strong&gt;) jika dipakai di text:
            $pesan_sukses = "Nota berhasil dibuat!"
                . ($diskon > 0 ? " Diskon: Rp " . number_format($diskon,0,',','.') . "." : "")
                . " Total: Rp " . number_format($total_bayar,0,',','.')
                . " | Estimasi selesai: " . date('d M Y, H:i', strtotime($estimasi_selesai));
        } else {
            $pesan_error = "Gagal membuat transaksi: " . mysqli_error($koneksi);
        }
        mysqli_stmt_close($stmt_trx);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
        <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/icontab.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <title>Kasir (Nota Baru) - LaundryKuy</title>
    
    <!-- Framework CSS Bootstrap 5 -->
    <!-- Library SweetAlert2 untuk Notifikasi Pop-up -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* Desain Background Web: Menggunakan gambar yang digelapkan dengan gradasi CSS */
        
        /* Mempercantik border biru saat form input sedang di-klik (focus) oleh admin */
        .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        
        /* Modifikasi label form agar warnanya gelap dan lebih tegas dibaca */
        .form-kasir-label { font-weight: 700; color: #343a40; margin-bottom: 8px; }
        
        /* Modifikasi Switch Button (Tombol geser Pelanggan Baru/Lama) agar ukurannya lebih besar */
        .form-switch .form-check-input { width: 3em; height: 1.5em; cursor: pointer; }
        .form-switch .form-check-label { padding-top: 5px; margin-left: 10px; cursor: pointer; }
    </style>
</head>
<body>
<div class="kuy-layout">
<?php $halaman_aktif = 'transaksi'; include 'sidebarkuy.php'; ?>
<main class="kuy-main" id="kuyMain">
    <div class="kuy-topbar no-print">
        <span class="kuy-topbar-title">Kasir — Buat Nota</span>
        <div class="kuy-topbar-right">
            <span class="kuy-topbar-user">
                <i class="bi bi-person-circle me-1"></i>
                <?php echo ucwords($_SESSION['username']); ?>
            </span>
        </div>
    </div>
    <div class="kuy-content">


    <!-- ===============================================================================
         NAVBAR (MENU ATAS KHUSUS ADMIN)
         Sudah disinkronkan. Terdiri dari 4 menu utama dengan ikon yang konsisten.
         Menu "Kasir" menyala (active fw-bold) karena ini adalah halamannya.
         =============================================================================== -->
    <!-- ===============================================================================
         KONTEN UTAMA: LAYAR KASIR (POINT OF SALES)
         =============================================================================== -->
    <div class="container my-5">
        
        <!-- Header Judul Halaman -->
        <div class="row mb-4">
            <div class="col">
                <a href="dashboard_kasirkuy.php" class="btn btn-outline-light mb-3 rounded-pill fw-bold border-2">
                    <i class="bi bi-arrow-left me-2"></i>Kembali
                </a>
                <h4 class="fw-bold mb-1" style="color:var(--text-dark);"><i class="bi bi-cart-plus-fill me-2" style="color:var(--gold);"></i>Buat Nota Baru</h4>
                <p class="text-light fw-semibold">Sistem Kasir Cerdas: Tambah pelanggan & hitung harga otomatis berdasarkan layanan.</p>
            </div>
        </div>

        <div class="row">
            
            <!-- BAGIAN KIRI: FORM INPUT KASIR (Lebar 8 Kolom) -->
            <div class="col-lg-8 mb-4">
                <div class="card border-0 shadow-lg rounded-4 p-4 p-md-5 bg-white h-100">

                    <!-- Form ini dilengkapi ID 'formTransaksi' agar bisa dicegat oleh Javascript SweetAlert saat disubmit -->
                    <form id="formTransaksi" method="POST" action="">
                        
                        <!-- ========================= BLOK 1: DATA PELANGGAN ========================= -->
                        <h4 class="fw-bolder text-primary border-bottom border-2 pb-2 mb-4">1. Data Pelanggan</h4>
                        
                        <!-- Switch Toggle: Menentukan apakah ini Pelanggan Lama atau Baru -->
                        <div class="form-check form-switch mb-4 p-3 bg-light rounded-3 border">
                            <!-- onChange memanggil fungsi Javascript togglePelanggan() -->
                            <input class="form-check-input" type="checkbox" id="switchPelanggan" onchange="togglePelanggan()">
                            <label class="form-check-label fw-bold text-dark fs-5" for="switchPelanggan">Daftarkan Pelanggan Baru</label>
                            <small class="d-block text-muted ms-5 mt-1">Geser ke kanan jika pelanggan belum pernah mencuci di sini.</small>
                        </div>

                        <!-- Input tersembunyi (hidden) untuk memberi tahu PHP status pelanggan saat form dikirim -->
                        <input type="hidden" name="jenis_pelanggan" id="inputJenisPelanggan" value="lama">

                        <!-- [A] TAMPILAN JIKA PELANGGAN LAMA (Default Muncul di awal) -->
                        <div id="blokPelangganLama" class="mb-4">
                            <label class="form-kasir-label">Pilih Nama Pelanggan</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-white"><i class="bi bi-person-lines-fill text-primary"></i></span>
                                <select class="form-select" name="id_pelanggan_lama" id="selectPelanggan">
                                    <option value="" disabled selected>-- Klik untuk cari nama --</option>
                                    <?php
                                    // Mengambil daftar pelanggan dari database secara urut abjad
                                    $q_plg = mysqli_prepare($koneksi, "SELECT id_pel, nama, hp FROM t_pelanggan ORDER BY nama ASC");
                                    mysqli_stmt_execute($q_plg);
                                    $res_plg = mysqli_stmt_get_result($q_plg);
                                    while ($r_plg = mysqli_fetch_assoc($res_plg)) {
                                        echo "<option value='".$r_plg['id_pel']."'>".$r_plg['nama']." - ".$r_plg['hp']."</option>";
                                    }
                                    mysqli_stmt_close($q_plg);
                                    ?>
                                </select>
                            </div>
                        </div>

                        <!-- [B] TAMPILAN JIKA PELANGGAN BARU (Default Sembunyi / d-none) -->
                        <div id="blokPelangganBaru" class="mb-4 d-none p-4 border border-2 border-primary rounded-4 bg-primary bg-opacity-10">
                            <h5 class="fw-bold text-primary mb-3"><i class="bi bi-person-plus-fill me-2"></i>Form Pelanggan Baru</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-kasir-label">Nama Lengkap</label>
                                    <input type="text" class="form-control form-control-lg" name="nama_baru" id="namaBaru" placeholder="Contoh: Budi Santoso">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-kasir-label">No. WhatsApp / HP</label>
                                    <div class="input-group input-group-lg">
                                        <span class="input-group-text bg-light">
                                            <i class="bi bi-whatsapp text-success"></i>
                                        </span>
                                        <input type="tel" class="form-control" name="hp_baru" id="telpBaru"
                                            placeholder="08xxxxxxxxxx"
                                            oninput="validasiHP(this)">
                                    </div>
                                    <small id="infoHP" class="text-muted mt-1 d-block">
                                        Format: 08xx, minimal 10 digit
                                    </small>
                                </div>
                                <div class="col-12">
                                    <label class="form-kasir-label">
                                        Alamat <span class="text-muted fw-normal">(isi jika ada jasa pengiriman)</span>
                                    </label>
                                    <textarea class="form-control" name="alamat_baru" id="alamatBaru" rows="2"
                                        placeholder="Isi alamat lengkap jika cucian akan dikirim ke rumah pelanggan..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- ========================= BLOK 2: DATA CUCIAN ========================= -->
                        <h4 class="fw-bolder text-primary border-bottom border-2 pb-2 mb-4 mt-5">2. Data Cucian</h4>
                        
                        <div class="row g-3">
                            <!-- Pilihan Layanan / Paket Cuci dari Database -->
                            <div class="col-md-6">
                                <label class="form-kasir-label">Pilih Layanan / Paket</label>
                                <!-- onchange="hitungTotal()" memicu JS untuk merubah harga di layar kanan secara realtime -->
                                <select class="form-select border-primary border-2 form-control-lg" name="id_layanan" id="selectLayanan" required onchange="hitungTotal()">
                                    <option value="" disabled selected>-- Pilih Layanan --</option>
                                    <?php
                                    // Mengambil daftar layanan/paket dari database
                                    // Harga FIX disimpan diam-diam di atribut HTML 'data-harga' agar bisa dibaca oleh Javascript Kalkulator
                                    $q_lyn = mysqli_prepare($koneksi, "SELECT id_layanan, nama_paket, harga_fix, estimasi_jam FROM t_layanan ORDER BY nama_paket ASC");
                                    mysqli_stmt_execute($q_lyn);
                                    $res_lyn = mysqli_stmt_get_result($q_lyn);
                                    while ($r_lyn = mysqli_fetch_assoc($res_lyn)) {
                                        echo "<option value='".$r_lyn['id_layanan']."' data-harga='".$r_lyn['harga_fix']."' data-estimasi='".(int)($r_lyn['estimasi_jam'] ?? 24)."'>".ucwords($r_lyn['nama_paket'])."</option>";
                                    }
                                    mysqli_stmt_close($q_lyn);
                                    ?>
                                </select>
                            </div>
                            
                            <!-- Input Berat Pakaian -->
                            <div class="col-md-6">
                                <label class="form-kasir-label">Berat Pakaian (Kg)</label>
                                <div class="input-group input-group-lg">
                                    <!-- oninput="hitungTotal()" memastikan setiap angka yang diketik langsung dikalikan harganya -->
                                    <input type="number" step="0.1" min="0.1" class="form-control border-primary border-2" name="berat" id="inputBerat" placeholder="Contoh: 2.5" required oninput="hitungTotal()">
                                    <span class="input-group-text bg-primary text-white fw-bold border-primary border-2">Kg</span>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4 border-2">

                        <!-- ========================= BLOK 3: VOUCHER DISKON ========================= -->
                        <h4 class="fw-bolder text-primary border-bottom border-2 pb-2 mb-4">3. Voucher Diskon <span class="text-muted fs-6 fw-normal">(Opsional)</span></h4>

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-kasir-label">Kode Voucher</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-light"><i class="bi bi-ticket-perforated-fill text-warning"></i></span>
                                    <input type="text" class="form-control text-uppercase"
                                        name="kode_voucher" id="inputVoucher"
                                        placeholder="Masukkan kode voucher..."
                                        style="font-family:monospace; letter-spacing:1px; font-size:15px;"
                                        oninput="this.value=this.value.toUpperCase(); cekVoucher()">
                                </div>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <div id="infoVoucher" class="w-100 p-3 rounded-3 text-center"
                                    style="background:#f8f9fa; border:1.5px dashed #dee2e6; font-size:13px; color:#6c757d; min-height:56px; display:flex; align-items:center; justify-content:center;">
                                    <i class="bi bi-ticket me-1"></i> Belum ada voucher
                                </div>
                            </div>
                        </div>

                        <hr class="my-4 border-2">
                        
                        <!-- TOMBOL SUBMIT -->
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-success btn-lg py-3 rounded-pill fw-bolder shadow">
                                <i class="bi bi-printer-fill me-2"></i>SIMPAN TRANSAKSI & BUAT NOTA
                            </button>
                        </div>

                    </form>
                </div>
            </div>

            <!-- BAGIAN KANAN: LAYAR TOTAL TAGIHAN KALKULATOR (Lebar 4 Kolom) -->
            <div class="col-lg-4 mb-4">
                <!-- class 'sticky-top' membuat kotak hitam ini ikut melayang turun saat layar di-scroll ke bawah -->
                <div class="card border-0 shadow-lg rounded-4 p-4 bg-dark text-white sticky-top" style="top: 100px;">
                    <div class="text-center">
                        <i class="bi bi-calculator text-warning" style="font-size: 3rem;"></i>
                        <h5 class="fw-bold mt-2 text-light">RINGKASAN TAGIHAN</h5>
                        
                        <!-- Tarif Per Kilo -->
                        <p class="small text-muted border-bottom border-secondary pb-3" id="teksTarif">Tarif: Pilih layanan dulu</p>

                        <!-- Subtotal sebelum diskon -->
                        <div class="d-flex justify-content-between align-items-center px-2 mb-1">
                            <span class="small text-secondary">Subtotal</span>
                            <span class="small" id="layarSubtotal">Rp 0</span>
                        </div>

                        <!-- Baris diskon (tersembunyi dulu) -->
                        <div class="d-flex justify-content-between align-items-center px-2 mb-1" id="barisDiskon" style="display:none !important;">
                            <span class="small text-warning"><i class="bi bi-ticket-perforated me-1"></i>Diskon</span>
                            <span class="small text-warning fw-bold" id="layarDiskon">- Rp 0</span>
                        </div>
                        
                        <!-- Total akhir -->
                        <h1 class="fw-bolder text-warning mt-3 mb-2" style="font-size: 2.8rem;" id="layarTotal">Rp 0</h1>

                        <!-- Estimasi selesai -->
                        <div id="barisEstimasi" class="mt-2 p-2 rounded-3" style="background:rgba(255,255,255,0.08); display:none;">
                            <div class="small text-secondary mb-1"><i class="bi bi-clock me-1"></i>Estimasi Selesai</div>
                            <div class="fw-bold text-info" id="layarEstimasi" style="font-size:13px;">—</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Panggil File Utama Javascript Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- ===============================================================================
         KUMPULAN LOGIKA JAVASCRIPT CUSTOM (Kasir Cerdas & Form Handling)
         =============================================================================== -->
    <script>
        // 1. PENCEGAH ERROR "CONFIRM FORM RESUBMISSION" (BFCache)
        // Jika kasir menekan tombol "Back" di browser setelah menyimpan data form, biasanya muncul error.
        // Baris kode ini menghapus jejak memori POST sehingga tombol Back/Kembali tetap aman digunakan.
        if ( window.history.replaceState ) {
            window.history.replaceState( null, null, window.location.href );
        }

        // 2. FUNGSI TOGGLE SWITCH (Mengatur Perubahan Tampilan Form Pelanggan Lama / Baru)
        function togglePelanggan() {
            let checkBox = document.getElementById("switchPelanggan");
            let blokLama = document.getElementById("blokPelangganLama");
            let blokBaru = document.getElementById("blokPelangganBaru");
            let inputJenis = document.getElementById("inputJenisPelanggan");
            
            let selLama = document.getElementById("selectPelanggan");
            let inNama  = document.getElementById("namaBaru");
            let inTelp  = document.getElementById("telpBaru");

            if (checkBox.checked == true) {
                blokBaru.classList.remove("d-none");
                blokLama.classList.add("d-none");
                inputJenis.value = "baru";
                selLama.removeAttribute("required");
                inNama.setAttribute("required", "true");
                inTelp.setAttribute("required", "true");
            } else {
                blokBaru.classList.add("d-none");
                blokLama.classList.remove("d-none");
                inputJenis.value = "lama";
                inNama.removeAttribute("required");
                inTelp.removeAttribute("required");
                selLama.setAttribute("required", "true");
            }
        }

        window.onload = function() {
            document.getElementById("selectPelanggan").setAttribute("required", "true");
        };

        // FUNGSI VALIDASI HP REAL-TIME
        // Timer untuk debounce cek HP ke server
        let hpTimer = null;

        function validasiHP(input) {
            let hp   = input.value.replace(/[^0-9]/g, '');
            let info = document.getElementById('infoHP');
            input.value = hp;

            // Normalisasi 628xx -> 08xx
            if (hp.startsWith('628')) hp = '0' + hp.substring(2);

            let validFormat = /^(08)[0-9]{8,11}$/.test(hp);

            if (hp.length === 0) {
                info.innerHTML = 'Format: 08xx, minimal 10 digit';
                info.className = 'text-muted mt-1 d-block small';
                input.classList.remove('is-valid', 'is-invalid');
                return;
            }

            if (!validFormat) {
                info.innerHTML = '<i class="bi bi-x-circle-fill me-1"></i>Format salah! Gunakan: 08xxxxxxxxxx (min. 10 digit)';
                info.className = 'text-danger mt-1 d-block small';
                input.classList.remove('is-valid');
                input.classList.add('is-invalid');
                return;
            }

            // Format valid — cek ke server apakah sudah terdaftar (debounce 600ms)
            info.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Memeriksa nomor...';
            info.className = 'text-muted mt-1 d-block small';
            input.classList.remove('is-valid', 'is-invalid');

            clearTimeout(hpTimer);
            hpTimer = setTimeout(() => {
                fetch('cek_hpkuy.php?hp=' + encodeURIComponent(hp))
                    .then(r => r.json())
                    .then(data => {
                        if (data.tersedia) {
                            info.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Nomor valid & tersedia ✓';
                            info.className = 'text-success mt-1 d-block small';
                            input.classList.remove('is-invalid');
                            input.classList.add('is-valid');
                        } else {
                            info.innerHTML = '<i class="bi bi-x-circle-fill me-1"></i>'
                                + (data.pesan || 'Nomor sudah terdaftar!')
                                + (data.nama ? ' — Gunakan <strong>Pelanggan Lama</strong>' : '');
                            info.className = 'text-danger mt-1 d-block small';
                            input.classList.remove('is-valid');
                            input.classList.add('is-invalid');
                        }
                    })
                    .catch(() => {
                        // Jika gagal koneksi ke server, tetap validasi format saja
                        info.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Format valid ✓';
                        info.className = 'text-success mt-1 d-block small';
                        input.classList.remove('is-invalid');
                        input.classList.add('is-valid');
                    });
            }, 600);
        }

        // 3. FUNGSI MENGHITUNG TOTAL HARGA (Kalkulator Real-Time)
        function hitungTotal() {
            let berat = document.getElementById("inputBerat").value;
            let selectLayanan = document.getElementById("selectLayanan");
            
            let hargaPerKilo = 0;
            let estimasiJam  = 0;
            if (selectLayanan.selectedIndex > 0) {
                hargaPerKilo = selectLayanan.options[selectLayanan.selectedIndex].getAttribute("data-harga");
                estimasiJam  = selectLayanan.options[selectLayanan.selectedIndex].getAttribute("data-estimasi") || 24;
            }

            let layar       = document.getElementById("layarTotal");
            let layarSub    = document.getElementById("layarSubtotal");
            let teksTarif   = document.getElementById("teksTarif");
            let barisEst    = document.getElementById("barisEstimasi");
            let layarEst    = document.getElementById("layarEstimasi");

            if (hargaPerKilo > 0) {
                teksTarif.innerHTML = "Tarif: Rp " + new Intl.NumberFormat('id-ID').format(hargaPerKilo) + " / Satuan";
            } else {
                teksTarif.innerHTML = "Tarif: Pilih layanan dulu";
            }

            let subtotal = 0;
            if (berat !== "" && berat > 0 && hargaPerKilo > 0) {
                subtotal = berat * hargaPerKilo;
                let fmt = new Intl.NumberFormat('id-ID', { style:'currency', currency:'IDR', minimumFractionDigits:0 });
                layarSub.textContent = fmt.format(subtotal);

                // Tampilkan estimasi
                if (estimasiJam > 0) {
                    let est = new Date(Date.now() + estimasiJam * 3600000);
                    layarEst.textContent = est.toLocaleDateString('id-ID', {day:'2-digit',month:'short',year:'numeric'})
                        + ', ' + est.toLocaleTimeString('id-ID', {hour:'2-digit',minute:'2-digit'});
                    barisEst.style.display = 'block';
                }
            } else {
                subtotal = 0;
                layarSub.textContent = 'Rp 0';
                barisEst.style.display = 'none';
            }

            hitungDiskon(subtotal);
        }

        // Data voucher yang valid (diisi oleh cekVoucher)
        let voucherAktif = null;

        function hitungDiskon(subtotal) {
            let layar    = document.getElementById("layarTotal");
            let barisDsk = document.getElementById("barisDiskon");
            let layarDsk = document.getElementById("layarDiskon");
            let fmt      = new Intl.NumberFormat('id-ID', { style:'currency', currency:'IDR', minimumFractionDigits:0 });

            let diskon = 0;
            if (voucherAktif && subtotal > 0) {
                if (voucherAktif.tipe === 'persen') {
                    diskon = subtotal * (voucherAktif.nilai / 100);
                    if (voucherAktif.max_diskon > 0 && diskon > voucherAktif.max_diskon) {
                        diskon = voucherAktif.max_diskon;
                    }
                } else {
                    diskon = voucherAktif.nilai;
                }
                diskon = Math.min(diskon, subtotal);
                barisDsk.style.display = 'flex';
                layarDsk.textContent   = '- ' + fmt.format(diskon);
            } else {
                barisDsk.style.display = 'none';
            }

            let total = subtotal - diskon;
            layar.innerHTML = total > 0 ? fmt.format(total) : 'Rp 0';
        }

        // Cek voucher ke server via AJAX (debounce 600ms)
        let voucherTimer = null;
        function cekVoucher() {
            clearTimeout(voucherTimer);
            let kode = document.getElementById("inputVoucher").value.trim();
            let info = document.getElementById("infoVoucher");

            if (kode.length < 3) {
                voucherAktif = null;
                info.innerHTML = '<i class="bi bi-ticket me-1"></i> Belum ada voucher';
                info.style.background = '#f8f9fa';
                info.style.borderColor = '#dee2e6';
                info.style.color = '#6c757d';
                // Hitung ulang tanpa diskon
                let berat = document.getElementById("inputBerat").value;
                let sel   = document.getElementById("selectLayanan");
                let harga = sel.selectedIndex > 0 ? sel.options[sel.selectedIndex].getAttribute("data-harga") : 0;
                hitungDiskon(berat > 0 && harga > 0 ? berat * harga : 0);
                return;
            }

            info.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Memeriksa...';
            voucherTimer = setTimeout(() => {
                fetch('cek_voucherkuy.php?kode=' + encodeURIComponent(kode))
                    .then(r => r.json())
                    .then(data => {
                        let berat = document.getElementById("inputBerat").value;
                        let sel   = document.getElementById("selectLayanan");
                        let harga = sel.selectedIndex > 0 ? sel.options[sel.selectedIndex].getAttribute("data-harga") : 0;
                        let subtotal = berat > 0 && harga > 0 ? berat * harga : 0;

                        if (data.valid) {
                            voucherAktif = data;
                            let nilaiTeks = data.tipe === 'persen'
                                ? data.nilai + '%'
                                : 'Rp ' + new Intl.NumberFormat('id-ID').format(data.nilai);
                            info.innerHTML = '<i class="bi bi-check-circle-fill text-success me-1"></i>'
                                + '<strong class="text-success">' + data.nama + '</strong>'
                                + '<br><small>Diskon ' + nilaiTeks + '</small>';
                            info.style.background = '#d1fae5';
                            info.style.borderColor = '#10b981';
                            info.style.color = '#065f46';
                        } else {
                            voucherAktif = null;
                            info.innerHTML = '<i class="bi bi-x-circle-fill text-danger me-1"></i>'
                                + '<span class="text-danger">' + (data.pesan || 'Voucher tidak valid') + '</span>';
                            info.style.background = '#fee2e2';
                            info.style.borderColor = '#dc3545';
                            info.style.color = '#991b1b';
                        }
                        hitungDiskon(subtotal);
                    })
                    .catch(() => {
                        info.innerHTML = '<i class="bi bi-wifi-off me-1"></i> Gagal memeriksa';
                    });
            }, 600);
        }

        // 4. MENCEGAT PENGIRIMAN FORM UNTUK MEMUNCULKAN SWEETALERT KONFIRMASI DULU
        document.getElementById('formTransaksi').addEventListener('submit', function(e) {
            e.preventDefault(); // Tahan dulu form-nya, jangan biarkan browser langsung mengirim ke PHP
            
            // Munculkan Pop-up Animasi Konfirmasi SweetAlert2
            Swal.fire({
                title: 'Buat Nota Transaksi?',
                text: "Pastikan data pelanggan dan jumlah cucian sudah benar. Data yang disimpan tidak bisa dihapus oleh kasir.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754', // Hijau (Warna tombol Success)
                cancelButtonColor: '#d33', // Merah (Warna tombol Cancel)
                confirmButtonText: 'Ya, Simpan!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                // Jika kasir menekan tombol "Ya, Simpan!"
                if (result.isConfirmed) {
                    // Buat elemen input tersembunyi (hidden input) via Javascript
                    let hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'btn_simpan'; // Ini yang akan ditangkap oleh logika isset($_POST['btn_simpan']) di PHP paling atas
                    hiddenInput.value = '1';
                    
                    // Masukkan hidden input itu ke dalam form, lalu paksa kirim (submit) form-nya ke server PHP
                    this.appendChild(hiddenInput);
                    this.submit(); 
                }
            })
        });
    </script>

    <!-- ===============================================================================
         MENAMPILKAN NOTIFIKASI BERHASIL / GAGAL DARI PHP (via SweetAlert2)
         Jika variabel PHP terisi, otomatis script ini dicetak ke HTML dan pop-up akan muncul
         =============================================================================== -->
    <?php if ($pesan_sukses != "") { ?>
    <script>
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?php echo addslashes(htmlspecialchars($pesan_sukses)); ?>', showConfirmButton: false, timer: 2500 });
    </script>
    <?php } ?>

    <?php if ($pesan_error != "") { ?>
    <script>
        Swal.fire({ icon: 'error', title: 'Oops...', text: '<?php echo addslashes(htmlspecialchars($pesan_error)); ?>' });
    </script>
    <?php } ?>

    </div><!-- end kuy-content -->
</main><!-- end kuy-main -->
</div><!-- end kuy-layout -->
</body>
</html>