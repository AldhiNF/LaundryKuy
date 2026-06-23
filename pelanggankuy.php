<?php
/**
 * =================================================================================
 * FILE: pelanggankuy.php
 * DESKRIPSI: Halaman Manajemen Data Pelanggan.
 * FUNGSI: Menampilkan tabel pelanggan, mengedit data, dan menghapus data pelanggan.
 * HAK AKSES: Admin (Warna Biru) & Owner (Warna Gelap).
 * =================================================================================
 */

// 1. MEMULAI SESSION
// session_start() wajib dipanggil paling atas sebelum ada kode HTML. 
// Fungsinya agar server mengingat identitas user (seperti ID, username, role) yang sedang login.
session_start();

$halaman_aktif = 'pelanggan';

// Memanggil file koneksi agar halaman ini bisa "berbicara" dengan database 'db_laundry'
include 'connectkuy.php';

// 2. SISTEM KEAMANAN (PROTEKSI HALAMAN)
// Jika variabel $_SESSION['role'] belum dibuat (artinya belum login sama sekali) 
// ATAU yang login BUKAN admin DAN BUKAN owner, maka:
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'kasir' && $_SESSION['role'] !== 'owner')) {
    header("Location: loginkuy.php"); // Tendang kembali ke halaman login
    exit(); // Hentikan semua proses PHP di bawah baris ini agar aman
}

// 3. PENGATURAN TAMPILAN DINAMIS (ADMIN vs OWNER)
// Kita menggunakan "Ternary Operator" (Kondisi ? Benar : Salah) agar kode lebih ringkas.
// Jika yang login Admin, tombol kembali akan mengarah ke dashboard_adminkuy, jika bukan (Owner) ke dashboard_ownerkuy.
$link_dashboard = ($_SESSION['role'] == 'kasir') ? 'dashboard_kasirkuy.php' : 'dashboard_ownerkuy.php';

// Menyesuaikan tulisan di pojok kiri atas (LaundryKuy Kasir / LaundryKuy Owner)
$teks_role      = ($_SESSION['role'] == 'kasir') ? 'Admin' : 'Owner';

// Menyesuaikan warna tema Navbar (Biru bg-primary untuk Admin, Hitam navbar-custom untuk Owner)
$tema_navbar    = ($_SESSION['role'] == 'kasir') ? 'bg-primary' : 'navbar-custom';

// Variabel penampung teks notifikasi (Awalnya kosong)
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
// LOGIKA BACKEND: PROSES EDIT DATA
// Akan dijalankan HANYA JIKA tombol dengan atribut name="btn_edit" ditekan (Submit Form Modal)
// =================================================================================
if (isset($_POST['btn_edit'])) {
    // Tangkap data dari form modal
    $id_pel = $_POST['id_pel']; // ID tidak perlu di-escape karena tidak diinput manual (hidden)
    
    // mysqli_real_escape_string digunakan untuk mensterilkan teks inputan dari simbol-simbol 
    // aneh (seperti tanda petik) yang berpotensi merusak struktur database atau disalahgunakan hacker.
    $nama   = mysqli_real_escape_string($koneksi, $_POST['nama']);
    $hp     = preg_replace('/[^0-9]/', '', $_POST['hp']); // Hanya angka
    $alamat = mysqli_real_escape_string($koneksi, $_POST['alamat']);

    // Validasi format HP Indonesia
    $valid_hp = preg_match('/^(08|628|62)[0-9]{7,12}$/', $hp);
    if (!$valid_hp) {
        $pesan_error = "Nomor HP tidak valid! Gunakan format: 08xx, 628xx (minimal 10 digit).";
    } else {
        // Normalisasi: 628xx -> 08xx
        if (substr($hp, 0, 3) === '628') $hp = '0' . substr($hp, 2);
        $hp = mysqli_real_escape_string($koneksi, $hp);

        // Cek duplikat HP — pastikan tidak ada pelanggan LAIN dengan HP yang sama
        $cek_hp = mysqli_prepare($koneksi, "SELECT id_pel, nama FROM t_pelanggan WHERE hp = ? AND id_pel != ?");
        mysqli_stmt_bind_param($cek_hp, "si", $hp, $id_pel);
        mysqli_stmt_execute($cek_hp);
        mysqli_stmt_store_result($cek_hp);
        if (mysqli_stmt_num_rows($cek_hp) > 0) {
            mysqli_stmt_bind_result($cek_hp, $dup_id, $dup_nama);
            mysqli_stmt_fetch($cek_hp);
            $pesan_error = "Nomor HP $hp sudah digunakan oleh pelanggan lain: $dup_nama!";
            mysqli_stmt_close($cek_hp);
        } else {
            mysqli_stmt_close($cek_hp);
            $query_edit = "UPDATE t_pelanggan SET nama='$nama', hp='$hp', alamat='$alamat' WHERE id_pel='$id_pel'";
            if (mysqli_query($koneksi, $query_edit)) {
                $pesan_sukses = "Data pelanggan berhasil diperbarui!";
            } else {
                $pesan_error = "Gagal memperbarui data: " . mysqli_error($koneksi);
            }
        }
    } // end validasi HP
}

// =================================================================================
// LOGIKA BACKEND: PROSES HAPUS DATA
// Akan dijalankan HANYA JIKA tombol SweetAlert Hapus (name="btn_hapus") mengirim sinyal
// =================================================================================
if (isset($_POST['btn_hapus'])) {
    $id_pel = (int) $_POST['id_pel'];

    $stmt = mysqli_prepare($koneksi, "DELETE FROM t_pelanggan WHERE id_pel = ?");
    mysqli_stmt_bind_param($stmt, "i", $id_pel);
    if (mysqli_stmt_execute($stmt)) {
        $pesan_sukses = "Data pelanggan berhasil dihapus!";
    } else {
        $pesan_error = "Pelanggan tidak bisa dihapus karena memiliki riwayat transaksi!";
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
    <title>Data Pelanggan - LaundryKuy</title>
    
    <!-- Memanggil framework CSS Bootstrap untuk desain rapi dan responsif -->
    <!-- Memanggil ikon dari Bootstrap (untuk ikon panah, orang, tempat sampah, dll) -->
    <!-- Memanggil library Javascript SweetAlert2 untuk membuat pop-up notifikasi yang elegan -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* Desain Latar Belakang (Gambar Unsplash dilapis filter gelap transparan 85%) */
        
        /* CSS Khusus untuk memberikan efek degradasi warna (Hitam-Biru Tua) pada Navbar Owner */

        /* Memberikan efek transisi lembut (0.3 detik) saat kursor diarahkan ke baris tabel */
        .table-hover tbody tr:hover {
            background-color: rgba(13, 110, 253, 0.05); /* Sedikit warna biru transparan */
            transition: 0.3s;
        }
        
        /* Mempercantik bagian atas Modal (Pop-up Form Edit) dengan warna biru */
        .modal-header {
            background-color: #0d6efd;
            color: white;
        }
    </style>
</head>
<body>
<div class="kuy-layout">
<?php $halaman_aktif = 'pelanggan'; include 'sidebarkuy.php'; ?>
<main class="kuy-main" id="kuyMain">
    <div class="kuy-topbar no-print">
        <span class="kuy-topbar-title">Data Pelanggan</span>
        <div class="kuy-topbar-right">
            <span class="kuy-topbar-user">
                <i class="bi bi-person-circle me-1"></i>
                <?php echo ucwords($_SESSION['username']); ?>
            </span>
        </div>
    </div>
    <div class="kuy-content">


    <!-- ===============================================================================
         NAVBAR DINAMIS (Warna & Menu Berubah Tergantung Siapa yang Login)
         =============================================================================== -->
    <!-- ===============================================================================
         KONTEN UTAMA HALAMAN
         =============================================================================== -->
    <div class="container-fluid">

        <!-- Judul Halaman -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1" style="color:var(--text-dark);">
                    <i class="bi bi-people-fill me-2" style="color:var(--gold);"></i>Manajemen Pelanggan
                </h4>
                <p class="text-muted small mb-0">Kelola data pelanggan laundry Anda.</p>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="kuy-card p-4">
                    
                    <div class="table-responsive mt-2">
                        <table class="table table-hover align-middle border">
                            <!-- Kepala Tabel (Tabel Header) -->
                            <thead class="table-dark">
                                <tr>
                                    <th class="py-3 px-3 text-center" width="5%">No</th>
                                    <th class="py-3 px-3" width="25%">Nama Pelanggan</th>
                                    <th class="py-3 px-3" width="20%">No. WhatsApp / HP</th>
                                    <th class="py-3 px-3" width="35%">Alamat</th>
                                    <th class="py-3 px-3 text-center" width="15%">Aksi</th>
                                </tr>
                            </thead>
                            <!-- Badan Tabel (Tabel Body) -->
                            <tbody>
                                <?php
                                // Ambil seluruh data dari tabel Pelanggan, diurutkan dari ID terbesar (Paling Baru)
                                $query_pel = "SELECT * FROM t_pelanggan ORDER BY id_pel DESC";
                                $hasil_pel = mysqli_query($koneksi, $query_pel);
                                $no = 1; // Variabel untuk penomoran urut
                                
                                // Cek apakah ada datanya (jumlah baris > 0)
                                if (mysqli_num_rows($hasil_pel) > 0) {
                                    // Ulangi / Looping proses di bawah ini untuk setiap baris data yang ditemukan
                                    while ($r = mysqli_fetch_assoc($hasil_pel)) {
                                ?>
                                <tr>
                                    <!-- Menampilkan Nomor Urut (Maju 1 setiap loop dengan operator ++) -->
                                    <td class="text-center fw-bold"><?php echo $no++; ?></td>
                                    
                                    <!-- Menampilkan Nama -->
                                    <td class="fw-bold text-dark"><?php echo $r['nama']; ?></td>
                                    
                                    <!-- Menampilkan Nomor HP sekaligus dijadikan Link WhatsApp -->
                                    <td>
                                        <!-- Fungsi preg_replace('/^0/', '62', text) digunakan untuk: 
                                             Mencari Angka '0' di AWAL teks, lalu mengubahnya jadi '62' (Kode negara Indonesia) -->
                                        <a href="https://wa.me/<?php echo preg_replace('/^0/', '62', $r['hp']); ?>" target="_blank" class="text-decoration-none text-success fw-semibold">
                                            <i class="bi bi-whatsapp me-1"></i><?php echo $r['hp']; ?>
                                        </a>
                                    </td>
                                    
                                    <!-- Menampilkan Alamat. Jika kosong, tampilkan tanda '-' -->
                                    <td class="text-muted"><?php echo $r['alamat'] != '' ? $r['alamat'] : '-'; ?></td>
                                    
                                    <!-- Kumpulan Tombol Aksi -->
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-2">
                                            
                                            <!-- Tombol Edit: Atribut data-bs-target akan memicu pop-up (Modal) terbuka sesuai dengan ID Pelanggan tersebut -->
                                            <button type="button" class="btn btn-sm btn-warning fw-bold shadow-sm text-dark" data-bs-toggle="modal" data-bs-target="#modalEdit<?php echo $r['id_pel']; ?>" title="Edit Data">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </button>
                                            
                                            <!-- Tombol Hapus: Menggunakan form tersembunyi agar aman. onclick akan memanggil fungsi Javascript konfirmasiHapus -->
                                            <form method="POST" action="">
                                                <input type="hidden" name="id_pel" value="<?php echo $r['id_pel']; ?>">
                                                <button type="button" class="btn btn-sm btn-danger fw-bold shadow-sm" title="Hapus Data" onclick="konfirmasiHapus(this.form)">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                <!-- ===============================================================================
                                     MODAL BOOTSTRAP (Pop-up Form Edit Data)
                                     Catatan Penting: ID Modal harus unik (ditambah id_pel) agar form tidak salah mengedit data pelanggan lain.
                                     =============================================================================== -->
                                <div class="modal fade" id="modalEdit<?php echo $r['id_pel']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content rounded-4 border-0 shadow">
                                            <div class="modal-header">
                                                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Pelanggan</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <!-- Form yang akan dikirim ke Server PHP di bagian atas file ini -->
                                            <form method="POST" action="">
                                                <div class="modal-body p-4">
                                                    
                                                    <!-- ID dikirim sembunyi-sembunyi sebagai penanda siapa yang diedit -->
                                                    <input type="hidden" name="id_pel" value="<?php echo $r['id_pel']; ?>">
                                                    
                                                    <!-- value="<?php echo $r['kolom']; ?>" berguna untuk menaruh data lama ke dalam form -->
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Nama Lengkap</label>
                                                        <input type="text" class="form-control" name="nama" value="<?php echo $r['nama']; ?>" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">No. HP / WhatsApp</label>
                                                        <div class="input-group">
                                                            <span class="input-group-text bg-light">
                                                                <i class="bi bi-whatsapp text-success"></i>
                                                            </span>
                                                            <input type="tel" class="form-control" name="hp"
                                                                id="editHp<?php echo $r['id_pel']; ?>"
                                                                value="<?php echo $r['hp']; ?>"
                                                                placeholder="08xxxxxxxxxx"
                                                                oninput="validasiHPEdit(this, 'infoHpEdit<?php echo $r['id_pel']; ?>')"
                                                                required>
                                                        </div>
                                                        <small id="infoHpEdit<?php echo $r['id_pel']; ?>" class="text-muted small mt-1 d-block">
                                                            Format: 08xx, minimal 10 digit
                                                        </small>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold">Alamat</label>
                                                        <textarea class="form-control" name="alamat" rows="3"><?php echo $r['alamat']; ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light rounded-bottom-4">
                                                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="btn_edit" class="btn btn-primary fw-bold"><i class="bi bi-save me-1"></i> Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <!-- END MODAL -->

                                <?php 
                                    } // Akhir dari perulangan While
                                } else { 
                                    // Jika database pelanggan kosong, tampilkan desain kosong (empty state)
                                ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted fw-bold">
                                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                            Belum ada data pelanggan yang terdaftar.
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

    <!-- Panggil File Utama Javascript Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- KUMPULAN LOGIKA JAVASCRIPT CUSTOM -->
    <script>
        // 1. PENCEGAH ERROR "CONFIRM FORM RESUBMISSION"
        // Saat user menekan F5/Refresh, browser suka mengirim ulang data POST (menyebabkan pesan error muncul).
        // Baris ini menghapus memori POST dari history browser tanpa memuat ulang layar.
        if ( window.history.replaceState ) {
            window.history.replaceState( null, null, window.location.href );
        }
        
        // VALIDASI HP REALTIME DI MODAL EDIT
        function validasiHPEdit(input, infoId) {
            let hp   = input.value.replace(/[^0-9]/g, '');
            let info = document.getElementById(infoId);
            input.value = hp;
            if (hp.startsWith('628')) hp = '0' + hp.substring(2);
            let valid = /^(08)[0-9]{7,12}$/.test(hp);
            if (hp.length === 0) {
                info.innerHTML = 'Format: 08xx, minimal 10 digit';
                info.className = 'text-muted small mt-1 d-block';
                input.classList.remove('is-valid','is-invalid');
            } else if (valid) {
                info.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>Nomor valid ✓';
                info.className = 'text-success small mt-1 d-block';
                input.classList.remove('is-invalid'); input.classList.add('is-valid');
            } else {
                info.innerHTML = '<i class="bi bi-x-circle-fill me-1"></i>Format salah! Gunakan: 08xxxxxxxxxx (min. 10 digit)';
                info.className = 'text-danger small mt-1 d-block';
                input.classList.remove('is-valid'); input.classList.add('is-invalid');
            }
        }

        // 2. FUNGSI SWEET ALERT UNTUK KONFIRMASI HAPUS
        function konfirmasiHapus(form) {
            Swal.fire({
                title: 'Hapus Pelanggan?',
                text: "Data ini tidak dapat dikembalikan! Pelanggan dengan riwayat transaksi tidak bisa dihapus.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                cancelButtonText: 'Batal',
                confirmButtonText: 'Ya, Hapus!'
            }).then((result) => {
                // Jika tombol "Ya, Hapus!" ditekan
                if (result.isConfirmed) {
                    // Buat elemen input hidden secara gaib melalui Javascript
                    let hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'btn_hapus';
                    hiddenInput.value = '1';
                    
                    // Tempelkan input itu ke dalam form hapus, dan paksa jalankan form-nya
                    form.appendChild(hiddenInput);
                    form.submit();
                }
            });
        }
    </script>

    <!-- MENAMPILKAN NOTIFIKASI SUCCESS/ERROR DARI PHP (Di-trigger oleh variabel PHP yang terisi) -->
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