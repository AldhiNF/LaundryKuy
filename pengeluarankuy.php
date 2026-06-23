<?php
/**
 * =================================================================================
 * FILE: pengeluarankuy.php
 * DESKRIPSI: Halaman Pencatatan Biaya Operasional (Pengeluaran).
 * FUNGSI: Mencatat uang keluar (sabun, listrik, gaji) untuk memotong pendapatan kotor.
 * HAK AKSES: KHUSUS OWNER (Admin dilarang masuk agar tidak bisa memanipulasi pengeluaran).
 * =================================================================================
 */

// 1. MEMULAI SESSION
session_start();

$halaman_aktif = 'pengeluaran';

// Menghubungkan ke database db_laundry
include 'connectkuy.php';

// 2. SISTEM KEAMANAN (PROTEKSI AKSES)
// Jika belum login, ATAU role-nya BUKAN owner, tendang kembali ke halaman login.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header("Location: loginkuy.php");
    exit();
}

// Variabel untuk menampung pesan pop-up SweetAlert
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
// LOGIKA BACKEND: MENYIMPAN PENGELUARAN BARU
// Akan dijalankan saat Owner mengisi form dan menekan "Simpan Pengeluaran"
// =================================================================================
if (isset($_POST['btn_simpan_biaya'])) {
    $tgl      = trim($_POST['tgl']);
    $kategori = trim($_POST['kategori']);
    $ket      = trim($_POST['ket']);
    $jumlah   = (int) $_POST['jumlah'];

    $stmt = mysqli_prepare($koneksi, "INSERT INTO t_biaya_op (tgl, ket, kategori, jumlah) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "sssi", $tgl, $ket, $kategori, $jumlah);
    if (mysqli_stmt_execute($stmt)) {
        $pesan_sukses = "Biaya operasional berhasil dicatat!";
    } else {
        $pesan_error = "Gagal mencatat pengeluaran: " . mysqli_error($koneksi);
    }
    mysqli_stmt_close($stmt);
}

// =================================================================================
// LOGIKA BACKEND: MENGHAPUS PENGELUARAN (JIKA SALAH CATAT)
// =================================================================================
if (isset($_POST['btn_hapus_biaya'])) {
    $id_biaya = (int) $_POST['id_biaya'];

    $stmt = mysqli_prepare($koneksi, "DELETE FROM t_biaya_op WHERE id_biaya = ?");
    mysqli_stmt_bind_param($stmt, "i", $id_biaya);
    if (mysqli_stmt_execute($stmt)) {
        $pesan_sukses = "Data pengeluaran berhasil dihapus (Laba Bersih akan dikalkulasi ulang).";
    } else {
        $pesan_error = "Gagal menghapus data pengeluaran.";
    }
    mysqli_stmt_close($stmt);
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
    <title>Biaya Operasional - LaundryKuy</title>
    
    <!-- Library SweetAlert2 untuk notifikasi elegan -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* Desain Background: Gelap elegan khusus untuk tampilan Owner */
        
        /* CSS Khusus untuk Warna Navbar Owner (Gradasi Hitam ke Biru Dongker) */
        
        /* Efek hover tabel: Jika disentuh akan memunculkan warna merah tipis karena ini uang keluar */
        .table-hover tbody tr:hover { background-color: rgba(220, 53, 69, 0.05); transition: 0.3s; }
    </style>
</head>
<body>
<div class="kuy-layout">
<?php $halaman_aktif = 'pengeluaran'; include 'sidebarkuy.php'; ?>
<main class="kuy-main" id="kuyMain">
    <div class="kuy-topbar no-print">
        <span class="kuy-topbar-title">Biaya Operasional</span>
        <div class="kuy-topbar-right">
            <span class="kuy-topbar-user">
                <i class="bi bi-person-circle me-1"></i>
                <?php echo ucwords($_SESSION['username']); ?>
            </span>
        </div>
    </div>
    <div class="kuy-content">


    <!-- ===============================================================================
         NAVBAR KHUSUS OWNER (MENU SUDAH SINKRON LENGKAP)
         =============================================================================== -->
    <!-- ===============================================================================
         KONTEN UTAMA HALAMAN
         =============================================================================== -->
    <div class="container">
        
        <div class="row mb-4">
            <div class="col">
                <h2 class="fw-bolder text-dark"><i class="bi bi-cart-dash text-danger me-2"></i>Biaya Operasional</h2>
                <p class="text-muted fw-semibold">Catat pengeluaran laundry agar sistem dapat menghitung Laba Bersih secara akurat.</p>
            </div>
        </div>

        <div class="row">
            
            <!-- BAGIAN KIRI: FORM INPUT PENGELUARAN -->
            <div class="col-lg-4 mb-4">
                <div class="card border-0 shadow-lg rounded-4 p-4 bg-white h-100">
                    <h5 class="fw-bold mb-4 border-bottom pb-2 text-primary">Catat Biaya Baru</h5>
                    
                    <form id="formBiaya" method="POST" action="">
                        
                        <!-- Input Tanggal (Otomatis terisi tanggal hari ini) -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Tanggal</label>
                            <input type="date" class="form-control" name="tgl" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <!-- Input Kategori (Dibatasi pakai Select agar rapi di laporan) -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Kategori Pengeluaran</label>
                            <select class="form-select" name="kategori" required>
                                <option value="" disabled selected>-- Pilih Kategori --</option>
                                <option value="Bahan Baku">Bahan Baku (Deterjen, Pewangi, Plastik)</option>
                                <option value="Listrik & Air">Listrik & Air</option>
                                <option value="Gaji Karyawan">Gaji Karyawan / Admin</option>
                                <option value="Aset">Aset (Mesin Cuci, Timbangan, Rak)</option>
                                <option value="Lain-lain">Lain-lain (Sewa Tempat, Bensin, dll)</option>
                            </select>
                        </div>
                        
                        <!-- Input Keterangan Detail -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">Keterangan / Detail</label>
                            <textarea class="form-control" name="ket" rows="2" placeholder="Cth: Beli Rinso Cair 5 Liter..." required></textarea>
                        </div>
                        
                        <!-- Input Nominal Uang -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Nominal (Rp)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">Rp</span>
                                <input type="number" class="form-control border-start-0" name="jumlah" min="1" placeholder="Contoh: 150000" required>
                            </div>
                        </div>
                        
                        <!-- Tombol Submit (Memicu JS SweetAlert) -->
                        <div class="d-grid">
                            <button type="button" class="btn btn-danger fw-bold py-2 shadow-sm" onclick="konfirmasiSimpan(this.form)">
                                <i class="bi bi-save me-2"></i> SIMPAN PENGELUARAN
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- BAGIAN KANAN: TABEL RIWAYAT PENGELUARAN -->
            <div class="col-lg-8 mb-4">
                <div class="card border-0 shadow-lg rounded-4 p-4 bg-white h-100">
                    <h5 class="fw-bold mb-4 border-bottom pb-2 text-dark">Riwayat Uang Keluar</h5>
                    
                    <div class="table-responsive">
                        <table class="table table-hover align-middle border">
                            <thead class="table-dark">
                                <tr>
                                    <th class="py-3 px-3 text-center">Tgl</th>
                                    <th class="py-3 px-3">Kategori</th>
                                    <th class="py-3 px-3">Keterangan</th>
                                    <th class="py-3 px-3 text-end">Nominal</th>
                                    <th class="py-3 px-3 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                // Ambil 100 data pengeluaran terakhir dari database
                                $query_biaya = "SELECT * FROM t_biaya_op ORDER BY tgl DESC, id_biaya DESC LIMIT 100";
                                $hasil_biaya = mysqli_query($koneksi, $query_biaya);
                                
                                // Jika data ada
                                if (mysqli_num_rows($hasil_biaya) > 0) {
                                    while ($r = mysqli_fetch_assoc($hasil_biaya)) {
                                ?>
                                <tr>
                                    <!-- Tanggal diubah formatnya menjadi misal: 26 May 2026 -->
                                    <td class="text-center text-muted"><?php echo date('d M Y', strtotime($r['tgl'])); ?></td>
                                    
                                    <!-- Label Kategori -->
                                    <td>
                                        <span class="badge bg-secondary rounded-pill px-3 py-2 fw-normal">
                                            <i class="bi bi-tag me-1"></i><?php echo $r['kategori']; ?>
                                        </span>
                                    </td>
                                    
                                    <td class="fw-semibold text-dark"><?php echo ucwords($r['ket']); ?></td>
                                    
                                    <!-- Nominal diberi warna merah karena mengurangi pendapatan -->
                                    <td class="fw-bold text-danger text-end">- Rp <?php echo number_format($r['jumlah'], 0, ',', '.'); ?></td>
                                    
                                    <td class="text-center">
                                        <!-- Form Tersembunyi untuk menghapus data (Jika ditarik/salah input) -->
                                        <form method="POST" action="">
                                            <input type="hidden" name="id_biaya" value="<?php echo $r['id_biaya']; ?>">
                                            <button type="button" class="btn btn-sm btn-outline-danger shadow-sm" title="Hapus Data (Batal)" onclick="konfirmasiHapus(this.form)">
                                                <i class="bi bi-trash3"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php 
                                    } 
                                } else {
                                    // Tampilan Empty State jika belum ada pengeluaran sama sekali
                                ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted fw-bold">
                                            <i class="bi bi-wallet2 fs-2 d-block mb-2"></i>
                                            Belum ada catatan pengeluaran.
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- ===============================================================================
         KUMPULAN LOGIKA JAVASCRIPT CUSTOM
         =============================================================================== -->
    <script>
        // 1. Mencegah Form terkirim ganda saat halaman di-refresh
        if ( window.history.replaceState ) {
            window.history.replaceState( null, null, window.location.href );
        }

        // 2. Fungsi SweetAlert: Konfirmasi sebelum Uang Dicatat
        function konfirmasiSimpan(form) {
            // Cek dulu apakah semua input wajib sudah diisi
            if(!form.checkValidity()) { 
                form.reportValidity(); 
                return; 
            }

            Swal.fire({
                title: 'Catat Pengeluaran?',
                text: "Data ini akan otomatis memotong pendapatan kotor laundry Anda di laporan.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#dc3545', // Tombol Merah karena berhubungan dengan uang keluar
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Simpan!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Buat pemicu input hidden agar terbaca oleh isset($_POST['btn_simpan_biaya']) di PHP atas
                    let hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden'; 
                    hiddenInput.name = 'btn_simpan_biaya'; 
                    hiddenInput.value = '1';
                    
                    form.appendChild(hiddenInput);
                    form.submit(); // Kirim ke server
                }
            });
        }

        // 3. Fungsi SweetAlert: Konfirmasi Hapus Uang Keluar
        function konfirmasiHapus(form) {
            Swal.fire({
                title: 'Hapus Catatan Ini?',
                text: "Laba bersih Anda akan dihitung ulang secara otomatis jika data ini dihapus.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    let hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden'; 
                    hiddenInput.name = 'btn_hapus_biaya'; 
                    hiddenInput.value = '1';
                    
                    form.appendChild(hiddenInput);
                    form.submit();
                }
            });
        }
    </script>

    <!-- MENAMPILKAN PESAN NOTIFIKASI DARI PHP -->
    <?php if ($pesan_sukses != "") { ?>
    <script>
        Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?php echo addslashes(htmlspecialchars($pesan_sukses)); ?>', showConfirmButton: false, timer: 2000 });
    </script>
    <?php } ?>

    <?php if ($pesan_error != "") { ?>
    <script>
        Swal.fire({ icon: 'error', title: 'Oops...', text: '<?php echo addslashes(htmlspecialchars($pesan_error)); ?>' });
    </script>
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
    <script>Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?php echo addslashes(htmlspecialchars($pesan_sukses_pw)); ?>', showConfirmButton: false, timer: 2000 });</script>
    <?php endif; ?>
    <?php if (!empty($pesan_error_pw)): ?>
    <script>Swal.fire({ icon: 'error', title: 'Gagal!', text: '<?php echo addslashes($pesan_error_pw); ?>' });</script>
    <?php endif; ?>

    </div><!-- end kuy-content -->
</main><!-- end kuy-main -->
</div><!-- end kuy-layout -->
</body>
</html>