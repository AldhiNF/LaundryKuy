<?php
/**
 * =================================================================================
 * FILE: layanankuy.php
 * DESKRIPSI: Halaman Manajemen Paket Layanan & Harga.
 * FUNGSI: Menambah, mengubah harga, dan menghapus paket laundry.
 * HAK AKSES: KHUSUS OWNER (Admin dilarang masuk untuk mencegah kecurangan harga).
 * =================================================================================
 */

session_start();

$halaman_aktif = 'layanan';
include 'connectkuy.php';

// PROTEKSI HALAMAN KHUSUS OWNER
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    // Jika admin atau orang tidak dikenal mencoba mengakses, tendang ke login
    header("Location: loginkuy.php");
    exit();
}

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
// LOGIKA BACKEND: TAMBAH PAKET BARU
// =================================================================================
if (isset($_POST['btn_tambah'])) {
    $nama_paket   = trim($_POST['nama_paket']);
    $harga_fix    = (int) $_POST['harga_fix'];
    $estimasi_jam = (int) ($_POST['estimasi_jam'] ?? 24);

    $stmt = mysqli_prepare($koneksi, "INSERT INTO t_layanan (nama_paket, harga_fix, estimasi_jam) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "sii", $nama_paket, $harga_fix, $estimasi_jam);
    if (mysqli_stmt_execute($stmt)) {
        $pesan_sukses = "Paket layanan baru berhasil ditambahkan!";
    } else {
        $pesan_error = "Gagal menambah paket: " . mysqli_error($koneksi);
    }
    mysqli_stmt_close($stmt);
}

// =================================================================================
// LOGIKA BACKEND: EDIT HARGA / NAMA PAKET
// =================================================================================
if (isset($_POST['btn_edit'])) {
    $id_layanan   = (int) $_POST['id_layanan'];
    $nama_paket   = trim($_POST['nama_paket']);
    $harga_fix    = (int) $_POST['harga_fix'];
    $estimasi_jam = (int) ($_POST['estimasi_jam'] ?? 24);

    $stmt = mysqli_prepare($koneksi, "UPDATE t_layanan SET nama_paket=?, harga_fix=?, estimasi_jam=? WHERE id_layanan=?");
    mysqli_stmt_bind_param($stmt, "siii", $nama_paket, $harga_fix, $estimasi_jam, $id_layanan);
    if (mysqli_stmt_execute($stmt)) {
        $pesan_sukses = "Data paket & harga berhasil diperbarui!";
    } else {
        $pesan_error = "Gagal memperbarui harga: " . mysqli_error($koneksi);
    }
    mysqli_stmt_close($stmt);
}

// =================================================================================
// LOGIKA BACKEND: HAPUS PAKET
// =================================================================================
if (isset($_POST['btn_hapus'])) {
    $id_layanan = (int) $_POST['id_layanan'];

    $stmt = mysqli_prepare($koneksi, "DELETE FROM t_layanan WHERE id_layanan=?");
    mysqli_stmt_bind_param($stmt, "i", $id_layanan);
    if (mysqli_stmt_execute($stmt)) {
        $pesan_sukses = "Paket layanan berhasil dihapus!";
    } else {
        $pesan_error = "Paket tidak bisa dihapus karena sudah ada di dalam riwayat transaksi! Silakan edit namanya saja jika ingin dinonaktifkan.";
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
    <title>Manajemen Layanan - LaundryKuy Owner</title>
    
    <!-- Library SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* Navbar Khusus Owner (Gelap Elegan) */
        .table-hover tbody tr:hover { background-color: rgba(13, 110, 253, 0.05); transition: 0.3s; }
        .modal-header { background-color: #0f172a; color: white; }
    </style>
</head>
<body>
<div class="kuy-layout">
<?php $halaman_aktif = 'layanan'; include 'sidebarkuy.php'; ?>
<main class="kuy-main" id="kuyMain">
    <div class="kuy-topbar no-print">
        <span class="kuy-topbar-title">Harga Paket</span>
        <div class="kuy-topbar-right">
            <span class="kuy-topbar-user">
                <i class="bi bi-person-circle me-1"></i>
                <?php echo ucwords($_SESSION['username']); ?>
            </span>
        </div>
    </div>
    <div class="kuy-content">


    <!-- NAVBAR KHUSUS OWNER -->
    <!-- KONTEN UTAMA -->
    <div class="container">
        
        <div class="row mb-4 align-items-center">
            <div class="col-md-8">
                <h2 class="fw-bolder text-dark"><i class="bi bi-tags text-primary me-2"></i>Manajemen Paket & Harga</h2>
                <p class="text-muted fw-semibold">Atur tarif laundry Anda. Data ini akan menjadi acuan hitung otomatis bagi kasir.</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <!-- Tombol Buka Modal Tambah Paket -->
                <button class="btn btn-primary btn-lg fw-bold shadow-sm rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalTambah">
                    <i class="bi bi-plus-circle-fill me-2"></i> Tambah Paket Baru
                </button>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card border-0 shadow-lg rounded-4 p-4 bg-white">
                    
                    <!-- TABEL LAYANAN -->
                    <div class="table-responsive mt-2">
                        <table class="table table-hover align-middle border">
                            <thead class="table-dark">
                                <tr>
                                    <th class="py-3 px-3 text-center" width="8%">No</th>
                                    <th class="py-3 px-3" width="35%">Nama Paket / Layanan</th>
                                    <th class="py-3 px-3" width="22%">Tarif Tetap (Rp)</th>
                                    <th class="py-3 px-3 text-center" width="15%">Estimasi</th>
                                    <th class="py-3 px-3 text-center" width="20%">Aksi Kendali</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $query_layanan = "SELECT * FROM t_layanan ORDER BY harga_fix ASC";
                                $hasil_layanan = mysqli_query($koneksi, $query_layanan);
                                $no = 1;
                                
                                if (mysqli_num_rows($hasil_layanan) > 0) {
                                    while ($r = mysqli_fetch_assoc($hasil_layanan)) {
                                ?>
                                <tr>
                                    <td class="text-center fw-bold text-muted"><?php echo $no++; ?></td>
                                    
                                    <td class="fw-bolder text-primary fs-5">
                                        <i class="bi bi-tag-fill me-2 text-secondary"></i><?php echo ucwords($r['nama_paket']); ?>
                                    </td>
                                    
                                    <td class="fw-bold text-success fs-5">
                                        Rp <?php echo number_format($r['harga_fix'], 0, ',', '.'); ?> <span class="text-muted fs-6 fw-normal">/ Satuan</span>
                                    </td>

                                    <!-- Kolom Estimasi -->
                                    <td class="text-center">
                                        <?php
                                        $jam = (int)($r['estimasi_jam'] ?? 24);
                                        if ($jam < 24) {
                                            echo '<span class="badge rounded-pill" style="background:#dbeafe;color:#1e40af;">' . $jam . ' Jam</span>';
                                        } else {
                                            $hari = round($jam / 24, 1);
                                            echo '<span class="badge rounded-pill" style="background:#f5edd8;color:#7a92a8;">' . $hari . ' Hari</span>';
                                        }
                                        ?>
                                    </td>
                                    
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-2">
                                            
                                            <!-- Tombol Edit (Memicu Modal Edit) -->
                                            <button type="button" class="btn btn-sm btn-warning fw-bold shadow-sm text-dark px-3" data-bs-toggle="modal" data-bs-target="#modalEdit<?php echo $r['id_layanan']; ?>">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </button>
                                            
                                            <!-- Tombol Hapus (Memicu SweetAlert) -->
                                            <form method="POST" action="">
                                                <input type="hidden" name="id_layanan" value="<?php echo $r['id_layanan']; ?>">
                                                <button type="button" class="btn btn-sm btn-outline-danger fw-bold shadow-sm" onclick="konfirmasiHapus(this.form)">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                <!-- MODAL EDIT HARGA -->
                                <div class="modal fade" id="modalEdit<?php echo $r['id_layanan']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content rounded-4 border-0 shadow">
                                            <div class="modal-header">
                                                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Ubah Paket Layanan</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="POST" action="">
                                                <div class="modal-body p-4">
                                                    <input type="hidden" name="id_layanan" value="<?php echo $r['id_layanan']; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold text-dark">Nama Paket</label>
                                                        <input type="text" class="form-control form-control-lg" name="nama_paket" value="<?php echo $r['nama_paket']; ?>" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold text-dark">Harga (Rp)</label>
                                                        <div class="input-group input-group-lg">
                                                            <span class="input-group-text bg-light">Rp</span>
                                                            <input type="number" class="form-control" name="harga_fix" value="<?php echo $r['harga_fix']; ?>" min="1000" required>
                                                        </div>
                                                        <small class="text-danger mt-1 d-block"><i class="bi bi-exclamation-circle me-1"></i>Perubahan harga berlaku pada nota kasir berikutnya.</small>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold text-dark">Estimasi Selesai (Jam)</label>
                                                        <div class="input-group input-group-lg">
                                                            <input type="number" class="form-control" name="estimasi_jam" value="<?php echo $r['estimasi_jam'] ?? 24; ?>" min="1" required>
                                                            <span class="input-group-text bg-light">Jam</span>
                                                        </div>
                                                        <small class="text-muted mt-1 d-block">Cth: 3 = 3 jam, 24 = 1 hari, 48 = 2 hari</small>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light rounded-bottom-4">
                                                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Batal</button>
                                                    <button type="button" class="btn btn-warning fw-bold text-dark" onclick="konfirmasiEdit(this.form)">
                                                        <i class="bi bi-save me-1"></i> Simpan Perubahan
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <!-- END MODAL EDIT -->

                                <?php 
                                    } 
                                } else {
                                ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted fw-bold">
                                            <i class="bi bi-tags fs-2 d-block mb-2"></i>
                                            Belum ada paket layanan. Silakan tambah baru.
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
         MODAL TAMBAH PAKET BARU
         =============================================================================== -->
    <div class="modal fade" id="modalTambah" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Tambah Paket Laundry</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Nama Paket Baru</label>
                            <input type="text" class="form-control form-control-lg" name="nama_paket" placeholder="Cth: Cuci Selimut Besar" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Tarif Harga (Rp)</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light">Rp</span>
                                <input type="number" class="form-control" name="harga_fix" placeholder="15000" min="500" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Estimasi Selesai (Jam)</label>
                            <div class="input-group input-group-lg">
                                <input type="number" class="form-control" name="estimasi_jam" placeholder="24" value="24" min="1" required>
                                <span class="input-group-text bg-light">Jam</span>
                            </div>
                            <small class="text-muted mt-1 d-block">Cth: 3 = 3 jam, 24 = 1 hari, 48 = 2 hari</small>
                        </div>
                    </div>
                    <div class="modal-footer bg-light rounded-bottom-4">
                        <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="btn_tambah" class="btn btn-primary fw-bold">
                            <i class="bi bi-save me-1"></i> Simpan Paket
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Mencegah double submit saat reload
        if ( window.history.replaceState ) { window.history.replaceState( null, null, window.location.href ); }

        // Konfirmasi Hapus
        function konfirmasiHapus(form) {
            Swal.fire({
                title: 'Hapus Paket Ini?',
                text: "Jika paket ini sudah pernah dipesan pelanggan, sistem akan menolak untuk menghapusnya demi menjaga data laporan.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Coba Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    let hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden'; hiddenInput.name = 'btn_hapus'; hiddenInput.value = '1';
                    form.appendChild(hiddenInput); form.submit();
                }
            });
        }

        // Konfirmasi Ubah Harga (Opsional untuk keamanan Owner)
        function konfirmasiEdit(form) {
            if(!form.checkValidity()) { form.reportValidity(); return; }
            Swal.fire({
                title: 'Yakin Ubah Harga?',
                text: "Harga baru akan berlaku untuk setiap transaksi yang dibuat kasir mulai saat ini.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ubah Sekarang',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    let hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden'; hiddenInput.name = 'btn_edit'; hiddenInput.value = '1';
                    form.appendChild(hiddenInput); form.submit();
                }
            });
        }
    </script>

    <?php if ($pesan_sukses != "") { ?>
    <script>Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?php echo addslashes(htmlspecialchars($pesan_sukses)); ?>', showConfirmButton: false, timer: 2000 });</script>
    <?php } ?>
    <?php if ($pesan_error != "") { ?>
    <script>Swal.fire({ icon: 'error', title: 'Oops...', text: '<?php echo addslashes(htmlspecialchars($pesan_error)); ?>' });</script>
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