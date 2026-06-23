<?php
/**
 * =================================================================================
 * FILE: userkuy.php
 * DESKRIPSI: Halaman Manajemen Akun Karyawan.
 * FUNGSI: Owner bisa tambah, hapus, reset password, dan aktif/nonaktifkan akun kasir.
 * HAK AKSES: KHUSUS OWNER.
 *
 * CATATAN SETUP DATABASE (jalankan sekali di phpMyAdmin):
 * ALTER TABLE t_user ADD COLUMN status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif' AFTER role;
 * =================================================================================
 */

session_start();

$halaman_aktif = 'user';
include 'connectkuy.php';

// PROTEKSI: Hanya Owner yang boleh masuk
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header("Location: loginkuy.php");
    exit();
}

$pesan_sukses = "";
$pesan_error  = "";
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
// LOGIKA BACKEND: TAMBAH USER BARU
// =================================================================================
if (isset($_POST['btn_tambah'])) {
    $username_baru = trim($_POST['username_baru']);
    $password_baru = $_POST['password_baru'];
    $konfirmasi    = $_POST['konfirmasi_password'];

    // Validasi password cocok
    if ($password_baru !== $konfirmasi) {
        $pesan_error = "Password dan konfirmasi password tidak cocok!";
    } elseif (strlen($password_baru) < 6) {
        $pesan_error = "Password minimal 6 karakter!";
    } else {
        // Cek apakah username sudah dipakai (pakai Prepared Statement)
        $cek = mysqli_prepare($koneksi, "SELECT id_user FROM t_user WHERE username = ?");
        mysqli_stmt_bind_param($cek, "s", $username_baru);
        mysqli_stmt_execute($cek);
        mysqli_stmt_store_result($cek);

        if (mysqli_stmt_num_rows($cek) > 0) {
            $pesan_error = "Username '$username_baru' sudah digunakan! Pilih username lain.";
        } else {
            // Hash password sebelum disimpan
            $password_hash = password_hash($password_baru, PASSWORD_BCRYPT);
            // Role selalu 'kasir' (kasir), hanya owner yang bisa buat akun
            $role_baru = 'kasir';

            $stmt = mysqli_prepare($koneksi, "INSERT INTO t_user (username, password, role, status) VALUES (?, ?, ?, 'aktif')");
            mysqli_stmt_bind_param($stmt, "sss", $username_baru, $password_hash, $role_baru);

            if (mysqli_stmt_execute($stmt)) {
                $pesan_sukses = "Akun kasir '$username_baru' berhasil dibuat!";
            } else {
                $pesan_error = "Gagal membuat akun: " . mysqli_error($koneksi);
            }
            mysqli_stmt_close($stmt);
        }
        mysqli_stmt_close($cek);
    }
}

// =================================================================================
// LOGIKA BACKEND: RESET PASSWORD
// =================================================================================
if (isset($_POST['btn_reset_password'])) {
    $id_user       = (int) $_POST['id_user'];
    $password_baru = $_POST['password_reset'];
    $konfirmasi    = $_POST['konfirmasi_reset'];

    // Cegah owner mereset password dirinya sendiri dari sini
    if ($id_user == $_SESSION['id_user']) {
        $pesan_error = "Tidak bisa mereset password akun Anda sendiri dari sini!";
    } elseif ($password_baru !== $konfirmasi) {
        $pesan_error = "Password baru dan konfirmasi tidak cocok!";
    } elseif (strlen($password_baru) < 6) {
        $pesan_error = "Password minimal 6 karakter!";
    } else {
        $password_hash = password_hash($password_baru, PASSWORD_BCRYPT);

        $stmt = mysqli_prepare($koneksi, "UPDATE t_user SET password = ? WHERE id_user = ? AND role = 'kasir'");
        mysqli_stmt_bind_param($stmt, "si", $password_hash, $id_user);

        if (mysqli_stmt_execute($stmt)) {
            $pesan_sukses = "Password berhasil direset!";
        } else {
            $pesan_error = "Gagal mereset password.";
        }
        mysqli_stmt_close($stmt);
    }
}

// =================================================================================
// LOGIKA BACKEND: TOGGLE STATUS AKTIF / NONAKTIF
// =================================================================================
if (isset($_POST['btn_toggle_status'])) {
    $id_user       = (int) $_POST['id_user'];
    $status_skrng  = $_POST['status_sekarang'];

    // Cegah owner menonaktifkan dirinya sendiri
    if ($id_user == $_SESSION['id_user']) {
        $pesan_error = "Tidak bisa menonaktifkan akun Anda sendiri!";
    } else {
        $status_baru = ($status_skrng == 'aktif') ? 'nonaktif' : 'aktif';

        $stmt = mysqli_prepare($koneksi, "UPDATE t_user SET status = ? WHERE id_user = ? AND role = 'kasir'");
        mysqli_stmt_bind_param($stmt, "si", $status_baru, $id_user);

        if (mysqli_stmt_execute($stmt)) {
            $label = ($status_baru == 'aktif') ? 'diaktifkan' : 'dinonaktifkan';
            $pesan_sukses = "Akun berhasil $label!";
        } else {
            $pesan_error = "Gagal mengubah status akun.";
        }
        mysqli_stmt_close($stmt);
    }
}

// =================================================================================
// LOGIKA BACKEND: HAPUS USER
// =================================================================================
if (isset($_POST['btn_hapus'])) {
    $id_user = (int) $_POST['id_user'];

    if ($id_user == $_SESSION['id_user']) {
        $pesan_error = "Tidak bisa menghapus akun Anda sendiri!";
    } else {
        // Set id_user ke NULL di t_transaksi agar riwayat tetap tersimpan
        // tapi foreign key constraint tidak menghalangi penghapusan
        $stmt_null = mysqli_prepare($koneksi, "UPDATE t_transaksi SET id_user = NULL WHERE id_user = ?");
        mysqli_stmt_bind_param($stmt_null, "i", $id_user);
        mysqli_stmt_execute($stmt_null);
        mysqli_stmt_close($stmt_null);

        // Baru hapus user
        $stmt = mysqli_prepare($koneksi, "DELETE FROM t_user WHERE id_user = ? AND role = 'kasir'");
        mysqli_stmt_bind_param($stmt, "i", $id_user);

        if (mysqli_stmt_execute($stmt)) {
            $pesan_sukses = "Akun karyawan berhasil dihapus! Riwayat transaksinya tetap tersimpan.";
        } else {
            $pesan_error = "Gagal menghapus akun: " . mysqli_error($koneksi);
        }
        mysqli_stmt_close($stmt);
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
    <title>Manajemen User - LaundryKuy Owner</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background-color: #f8f9fc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card-user {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.07);
            transition: 0.3s;
        }
        .card-user:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        .avatar-circle {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 900;
            flex-shrink: 0;
        }
        .badge-aktif    { background-color: #d1fae5; color: #065f46; font-size: 0.78rem; padding: 4px 12px; border-radius: 20px; font-weight: 700; }
        .badge-nonaktif { background-color: #fee2e2; color: #991b1b; font-size: 0.78rem; padding: 4px 12px; border-radius: 20px; font-weight: 700; }
        .modal-header-dark { background: linear-gradient(to right, #0f172a, #1e293b); color: white; }
        .input-password-wrap { position: relative; }
        .input-password-wrap .toggle-pw {
            position: absolute; right: 12px; top: 50%;
            transform: translateY(-50%);
            cursor: pointer; color: #6c757d;
        }
    </style>
</head>
<body>
<div class="kuy-layout">
<?php $halaman_aktif = 'user'; include 'sidebarkuy.php'; ?>
<main class="kuy-main" id="kuyMain">
    <div class="kuy-topbar no-print">
        <span class="kuy-topbar-title">Kelola User</span>
        <div class="kuy-topbar-right">
            <span class="kuy-topbar-user">
                <i class="bi bi-person-circle me-1"></i>
                <?php echo ucwords($_SESSION['username']); ?>
            </span>
        </div>
    </div>
    <div class="kuy-content">


    <!-- =============== NAVBAR OWNER =============== -->
    <!-- =============== KONTEN UTAMA =============== -->
    <div class="container mb-5">

        <!-- Header -->
        <div class="row mb-4 align-items-center">
            <div class="col-md-8">
                <a href="dashboard_ownerkuy.php" class="btn btn-outline-secondary mb-3 rounded-pill fw-bold border-2 btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>
                <h2 class="fw-bolder text-dark"><i class="bi bi-people-gear text-primary me-2"></i>Manajemen Akun Karyawan</h2>
                <p class="text-muted fw-semibold">Kelola siapa saja yang bisa mengakses sistem kasir LaundryKuy.</p>
            </div>
            <div class="col-md-4 text-md-end mt-2 mt-md-0">
                <button class="btn btn-primary btn-lg fw-bold shadow-sm rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#modalTambahUser">
                    <i class="bi bi-person-plus-fill me-2"></i> Tambah Kasir Baru
                </button>
            </div>
        </div>

        <!-- Info Box -->
        <div class="alert alert-info border-0 rounded-3 shadow-sm mb-4 d-flex align-items-start gap-3">
            <i class="bi bi-info-circle-fill fs-4 mt-1 flex-shrink-0"></i>
            <div>
                <strong>Tentang Halaman Ini:</strong> Hanya Owner yang bisa mengakses halaman ini.
                Akun yang <span class="badge-nonaktif px-2 py-1 rounded">Nonaktif</span> tidak bisa login ke sistem meskipun passwordnya benar.
                Akun Owner tidak bisa diubah atau dihapus dari sini.
            </div>
        </div>

        <!-- =============== DAFTAR KARTU USER =============== -->
        <div class="row g-4">
            <?php
            // Ambil semua user — owner ditampilkan duluan, lalu admin urut nama
            $query_users = mysqli_query($koneksi,
                "SELECT * FROM t_user ORDER BY FIELD(role,'owner','kasir'), username ASC"
            );

            if (mysqli_num_rows($query_users) > 0):
                while ($u = mysqli_fetch_assoc($query_users)):
                    $is_owner    = ($u['role'] == 'owner');
                    $is_self     = ($u['id_user'] == $_SESSION['id_user']);
                    $is_aktif    = (!isset($u['status']) || $u['status'] == 'aktif');
                    $inisial     = strtoupper(substr($u['username'], 0, 1));
                    $warna_avatar = $is_owner ? '#0f172a' : ($is_aktif ? '#0d6efd' : '#9ca3af');
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card card-user p-4 h-100 <?php echo !$is_aktif ? 'opacity-75' : ''; ?>">

                    <!-- Baris atas: avatar + nama + badge -->
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="avatar-circle text-white" style="background-color: <?php echo $warna_avatar; ?>">
                            <?php echo $inisial; ?>
                        </div>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="fw-bolder text-dark fs-5 text-truncate">
                                <?php echo htmlspecialchars(ucwords($u['username'])); ?>
                                <?php if ($is_self): ?>
                                    <span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem;">Anda</span>
                                <?php endif; ?>
                            </div>
                            <div class="small text-muted">
                                <?php if ($is_owner): ?>
                                    <i class="bi bi-shield-fill-check text-warning me-1"></i>Owner
                                <?php else: ?>
                                    <i class="bi bi-person-badge me-1 text-primary"></i>Kasir
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- Badge Status -->
                        <?php if (!$is_owner): ?>
                            <span class="<?php echo $is_aktif ? 'badge-aktif' : 'badge-nonaktif'; ?>">
                                <i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>
                                <?php echo $is_aktif ? 'Aktif' : 'Nonaktif'; ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <hr class="my-2">

                    <!-- Tombol Aksi -->
                    <?php if ($is_owner): ?>
                        <!-- Akun Owner: tidak ada tombol aksi -->
                        <p class="text-muted small text-center mb-0 mt-2">
                            <i class="bi bi-lock-fill me-1"></i>Akun ini dilindungi, tidak bisa diubah dari sini.
                        </p>
                    <?php else: ?>
                        <div class="d-flex flex-wrap gap-2 mt-2">

                            <!-- Tombol Reset Password -->
                            <button class="btn btn-sm btn-outline-primary fw-bold flex-fill"
                                data-bs-toggle="modal"
                                data-bs-target="#modalReset<?php echo $u['id_user']; ?>">
                                <i class="bi bi-key-fill me-1"></i> Reset PW
                            </button>

                            <!-- Tombol Aktif/Nonaktif -->
                            <form method="POST" class="flex-fill">
                                <input type="hidden" name="id_user" value="<?php echo $u['id_user']; ?>">
                                <input type="hidden" name="status_sekarang" value="<?php echo $u['status'] ?? 'aktif'; ?>">
                                <button type="button"
                                    class="btn btn-sm w-100 fw-bold <?php echo $is_aktif ? 'btn-outline-warning' : 'btn-outline-success'; ?>"
                                    onclick="konfirmasiToggle(this.form, '<?php echo $u['username']; ?>', '<?php echo $is_aktif ? 'nonaktifkan' : 'aktifkan'; ?>')">
                                    <i class="bi <?php echo $is_aktif ? 'bi-pause-circle' : 'bi-play-circle'; ?> me-1"></i>
                                    <?php echo $is_aktif ? 'Nonaktifkan' : 'Aktifkan'; ?>
                                </button>
                            </form>

                            <!-- Tombol Hapus -->
                            <form method="POST" class="flex-fill">
                                <input type="hidden" name="id_user" value="<?php echo $u['id_user']; ?>">
                                <button type="button"
                                    class="btn btn-sm btn-outline-danger fw-bold w-100"
                                    onclick="konfirmasiHapus(this.form, '<?php echo $u['username']; ?>')">
                                    <i class="bi bi-trash3 me-1"></i> Hapus
                                </button>
                            </form>

                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- =============== MODAL RESET PASSWORD (per user) =============== -->
            <?php if (!$is_owner): ?>
            <div class="modal fade" id="modalReset<?php echo $u['id_user']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg rounded-4">
                        <div class="modal-header modal-header-dark rounded-top-4">
                            <h5 class="modal-title fw-bold">
                                <i class="bi bi-key-fill me-2"></i>Reset Password — <?php echo htmlspecialchars(ucwords($u['username'])); ?>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST" action="">
                            <div class="modal-body p-4">
                                <input type="hidden" name="id_user" value="<?php echo $u['id_user']; ?>">

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Password Baru</label>
                                    <div class="input-password-wrap">
                                        <input type="password" class="form-control form-control-lg pe-5"
                                            name="password_reset" id="pw_reset_<?php echo $u['id_user']; ?>"
                                            placeholder="Minimal 6 karakter" required minlength="6">
                                        <i class="bi bi-eye toggle-pw"
                                            onclick="toggleVisibility('pw_reset_<?php echo $u['id_user']; ?>', this)"></i>
                                    </div>
                                </div>

                                <div class="mb-1">
                                    <label class="form-label fw-bold">Konfirmasi Password Baru</label>
                                    <div class="input-password-wrap">
                                        <input type="password" class="form-control form-control-lg pe-5"
                                            name="konfirmasi_reset" id="pw_konfirm_<?php echo $u['id_user']; ?>"
                                            placeholder="Ulangi password baru" required>
                                        <i class="bi bi-eye toggle-pw"
                                            onclick="toggleVisibility('pw_konfirm_<?php echo $u['id_user']; ?>', this)"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer bg-light rounded-bottom-4">
                                <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Batal</button>
                                <button type="submit" name="btn_reset_password" class="btn btn-primary fw-bold">
                                    <i class="bi bi-save me-1"></i> Simpan Password Baru
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php endwhile; else: ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm rounded-4 p-5 text-center text-muted">
                    <i class="bi bi-people fs-1 d-block mb-3"></i>
                    <p class="fw-bold mb-0">Belum ada akun karyawan terdaftar.</p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <!-- END DAFTAR KARTU USER -->

    </div>

    <!-- =============== MODAL TAMBAH USER BARU =============== -->
    <div class="modal fade" id="modalTambahUser" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header modal-header-dark rounded-top-4">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i>Tambah Akun Kasir Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body p-4">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Username</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light"><i class="bi bi-person-fill text-primary"></i></span>
                                <input type="text" class="form-control" name="username_baru"
                                    placeholder="Cth: kasir1 atau budi" required>
                            </div>
                            <small class="text-muted">Gunakan huruf kecil tanpa spasi.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Password</label>
                            <div class="input-password-wrap">
                                <input type="password" class="form-control form-control-lg pe-5"
                                    name="password_baru" id="pw_baru_tambah"
                                    placeholder="Minimal 6 karakter" required minlength="6">
                                <i class="bi bi-eye toggle-pw" onclick="toggleVisibility('pw_baru_tambah', this)"></i>
                            </div>
                        </div>

                        <div class="mb-1">
                            <label class="form-label fw-bold">Konfirmasi Password</label>
                            <div class="input-password-wrap">
                                <input type="password" class="form-control form-control-lg pe-5"
                                    name="konfirmasi_password" id="pw_konfirm_tambah"
                                    placeholder="Ulangi password" required>
                                <i class="bi bi-eye toggle-pw" onclick="toggleVisibility('pw_konfirm_tambah', this)"></i>
                            </div>
                        </div>

                        <div class="alert alert-light border mt-3 rounded-3 small">
                            <i class="bi bi-info-circle me-1 text-primary"></i>
                            Akun baru otomatis berstatus <strong>Aktif</strong> dengan role <strong>Kasir</strong>.
                        </div>
                    </div>
                    <div class="modal-footer bg-light rounded-bottom-4">
                        <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="btn_tambah" class="btn btn-primary fw-bold px-4">
                            <i class="bi bi-person-check-fill me-1"></i> Buat Akun
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mencegah double submit saat reload
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Toggle visibilitas password (show/hide)
        function toggleVisibility(inputId, iconEl) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                iconEl.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                input.type = 'password';
                iconEl.classList.replace('bi-eye-slash', 'bi-eye');
            }
        }

        // Konfirmasi SweetAlert: Toggle Aktif/Nonaktif
        function konfirmasiToggle(form, username, aksi) {
            const warna   = aksi === 'nonaktifkan' ? '#f59e0b' : '#10b981';
            const icon    = aksi === 'nonaktifkan' ? 'warning' : 'question';
            const pesanTambahan = aksi === 'nonaktifkan'
                ? 'Akun ini tidak akan bisa login ke sistem setelah dinonaktifkan.'
                : 'Akun ini akan bisa login kembali ke sistem.';

            Swal.fire({
                title: `${aksi.charAt(0).toUpperCase() + aksi.slice(1)} akun ini?`,
                text: `${pesanTambahan}`,
                icon: icon,
                showCancelButton: true,
                confirmButtonColor: warna,
                cancelButtonColor: '#6c757d',
                confirmButtonText: `Ya, ${aksi}!`,
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    let input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'btn_toggle_status';
                    input.value = '1';
                    form.appendChild(input);
                    form.submit();
                }
            });
        }

        // Konfirmasi SweetAlert: Hapus User
        function konfirmasiHapus(form, username) {
            Swal.fire({
                title: 'Hapus Akun Ini?',
                html: `Akun <strong>${username}</strong> akan dihapus permanen.<br>Riwayat transaksinya tetap tersimpan.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    let input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'btn_hapus';
                    input.value = '1';
                    form.appendChild(input);
                    form.submit();
                }
            });
        }
    </script>

    <!-- Notifikasi SweetAlert dari PHP -->
    <?php if ($pesan_sukses != ""): ?>
    <script>Swal.fire({ icon: 'success', title: 'Berhasil!', text: '<?php echo addslashes(htmlspecialchars($pesan_sukses)); ?>', showConfirmButton: false, timer: 2000 });</script>
    <?php endif; ?>
    <?php if ($pesan_error != ""): ?>
    <script>Swal.fire({ icon: 'error', title: 'Oops...', text: '<?php echo addslashes($pesan_error); ?>' });</script>
    <?php endif; ?>

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