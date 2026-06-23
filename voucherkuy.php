<?php
/**
 * FILE: voucherkuy.php
 * DESKRIPSI: Halaman Manajemen Voucher Diskon khusus Owner.
 * FUNGSI: Tambah, aktif/nonaktif, dan hapus voucher diskon.
 */

session_start();
$halaman_aktif = 'voucher';
include 'connectkuy.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header("Location: loginkuy.php");
    exit();
}

$pesan_sukses_pw = "";
$pesan_error_pw  = "";
$pesan_sukses    = "";
$pesan_error     = "";

// ── GANTI PASSWORD OWNER ──────────────────────────────────────────────
if (isset($_POST['btn_ganti_pw_owner'])) {
    $pw_lama = $_POST['pw_lama']; $pw_baru = $_POST['pw_baru_owner']; $pw_konfirm = $_POST['pw_konfirm_owner'];
    $stmt = mysqli_prepare($koneksi, "SELECT password FROM t_user WHERE id_user = ?");
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['id_user']); mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
    if (!password_verify($pw_lama, $row['password']))   { $pesan_error_pw = "Password lama salah!"; }
    elseif ($pw_baru !== $pw_konfirm)                   { $pesan_error_pw = "Konfirmasi tidak cocok!"; }
    elseif (strlen($pw_baru) < 6)                       { $pesan_error_pw = "Minimal 6 karakter!"; }
    else {
        $hash = password_hash($pw_baru, PASSWORD_BCRYPT);
        $stmt = mysqli_prepare($koneksi, "UPDATE t_user SET password=? WHERE id_user=?");
        mysqli_stmt_bind_param($stmt, "si", $hash, $_SESSION['id_user']);
        $pesan_sukses_pw = mysqli_stmt_execute($stmt) ? "Password berhasil diubah!" : "Gagal.";
        mysqli_stmt_close($stmt);
    }
}

// ── TAMBAH VOUCHER ────────────────────────────────────────────────────
if (isset($_POST['btn_tambah_voucher'])) {
    $kode         = strtoupper(trim(mysqli_real_escape_string($koneksi, $_POST['kode'])));
    $nama         = mysqli_real_escape_string($koneksi, $_POST['nama']);
    $tipe         = $_POST['tipe'];
    $nilai        = (float) $_POST['nilai'];
    $min_trx      = (float) ($_POST['min_transaksi'] ?? 0);
    $max_diskon   = (float) ($_POST['max_diskon'] ?? 0);
    $kuota        = (int)   ($_POST['kuota'] ?? 0);
    
    // PERBAIKAN: Cek apakah input tanggal kosong. Jika kosong (""), jadikan NULL.
    $tgl_mulai    = !empty($_POST['tgl_mulai']) ? $_POST['tgl_mulai'] : null;
    $tgl_selesai  = !empty($_POST['tgl_selesai']) ? $_POST['tgl_selesai'] : null;

    // Validasi persen tidak boleh lebih dari 100
    if ($tipe === 'persen' && $nilai > 100) {
        $pesan_error = "Diskon persen tidak boleh lebih dari 100%!";
    } else {
        $stmt = mysqli_prepare($koneksi,
            "INSERT INTO t_voucher (kode, nama, tipe, nilai, min_transaksi, max_diskon, kuota, tgl_mulai, tgl_selesai)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // PERBAIKAN: bind param diubah dari "sssdddisd" menjadi "sssdddiss" (2 parameter terakhir adalah string/date)
        mysqli_stmt_bind_param($stmt, "sssdddiss",
            $kode, $nama, $tipe, $nilai, $min_trx, $max_diskon, $kuota, $tgl_mulai, $tgl_selesai);
            
        if (mysqli_stmt_execute($stmt)) {
            $pesan_sukses = "Voucher $kode berhasil dibuat!";
        } else {
            $pesan_error = mysqli_errno($koneksi) == 1062
                ? "Kode voucher <strong>$kode</strong> sudah digunakan!"
                : "Gagal membuat voucher: " . mysqli_error($koneksi);
        }
        mysqli_stmt_close($stmt);
    }
}

// ── TOGGLE AKTIF / NONAKTIF ───────────────────────────────────────────
if (isset($_POST['btn_toggle_voucher'])) {
    $id     = (int) $_POST['id_voucher'];
    $aktif  = (int) $_POST['aktif_skrng'];
    $baru   = $aktif == 1 ? 0 : 1;
    $stmt   = mysqli_prepare($koneksi, "UPDATE t_voucher SET aktif=? WHERE id_voucher=?");
    mysqli_stmt_bind_param($stmt, "ii", $baru, $id);
    $label  = $baru ? 'diaktifkan' : 'dinonaktifkan';
    $pesan_sukses = mysqli_stmt_execute($stmt) ? "Voucher berhasil $label!" : "Gagal mengubah status.";
    mysqli_stmt_close($stmt);
}

// ── HAPUS VOUCHER ─────────────────────────────────────────────────────
if (isset($_POST['btn_hapus_voucher'])) {
    $id   = (int) $_POST['id_voucher'];
    $stmt = mysqli_prepare($koneksi, "DELETE FROM t_voucher WHERE id_voucher=?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    $pesan_sukses = mysqli_stmt_execute($stmt) ? "Voucher berhasil dihapus!" : "Gagal menghapus.";
    mysqli_stmt_close($stmt);
}

// ── AMBIL DATA VOUCHER ────────────────────────────────────────────────
$hasil_voucher = mysqli_query($koneksi, "SELECT * FROM t_voucher ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Voucher - LaundryKuy</title>
    <link rel="icon" type="image/png" href="assets/icontab.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="kuy-layout">
<?php include 'sidebarkuy.php'; ?>
<main class="kuy-main" id="kuyMain">
    <div class="kuy-topbar">
        <span class="kuy-topbar-title">Kelola Voucher</span>
        <div class="kuy-topbar-right">
            <span class="kuy-topbar-user d-none d-md-flex">
                <i class="bi bi-person-circle me-1"></i><?php echo ucwords($_SESSION['username']); ?>
            </span>
        </div>
    </div>
    <div class="kuy-content">

        <!-- Judul + Tombol Tambah -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="fw-bold mb-1" style="color:var(--text-dark,#1e2d40);">
                    <i class="bi bi-ticket-perforated-fill me-2" style="color:var(--gold,#c9a96e);"></i>Manajemen Voucher Diskon
                </h4>
                <p class="text-muted small mb-0">Buat dan kelola kode voucher untuk pelanggan setia.</p>
            </div>
            <button class="kuy-btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambahVoucher">
                <i class="bi bi-plus-circle-fill"></i> Buat Voucher Baru
            </button>
        </div>

        <!-- TABEL VOUCHER -->
        <div class="kuy-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="kuy-table">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama Voucher</th>
                            <th class="text-center">Tipe</th>
                            <th class="text-center">Nilai Diskon</th>
                            <th class="text-center">Min. Transaksi</th>
                            <th class="text-center">Maks. Diskon</th>
                            <th class="text-center">Kuota</th>
                            <th class="text-center">Terpakai</th>
                            <th class="text-center">Berlaku</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($hasil_voucher) > 0):
                            while ($v = mysqli_fetch_assoc($hasil_voucher)):
                                $today     = date('Y-m-d');
                                $expired   = $v['tgl_selesai'] && $v['tgl_selesai'] < $today;
                                $belum     = $v['tgl_mulai']   && $v['tgl_mulai']   > $today;
                                $habis     = $v['kuota'] > 0   && $v['terpakai'] >= $v['kuota'];
                        ?>
                        <tr class="<?php echo (!$v['aktif'] || $expired || $habis) ? 'opacity-50' : ''; ?>">
                            <td>
                                <span class="fw-bold" style="color:var(--navy-800,#1e2d40); font-family:monospace; font-size:14px; letter-spacing:1px;">
                                    <?php echo htmlspecialchars($v['kode']); ?>
                                </span>
                            </td>
                            <td class="fw-semibold"><?php echo htmlspecialchars($v['nama']); ?></td>
                            <td class="text-center">
                                <?php if ($v['tipe'] == 'persen'): ?>
                                    <span class="kuy-badge" style="background:#dbeafe;color:#1e40af;">% Persen</span>
                                <?php else: ?>
                                    <span class="kuy-badge" style="background:#fef9c3;color:#854d0e;">Rp Nominal</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center fw-bold" style="color:#166534;">
                                <?php echo $v['tipe']=='persen'
                                    ? $v['nilai'].'%'
                                    : 'Rp '.number_format($v['nilai'],0,',','.'); ?>
                            </td>
                            <td class="text-center text-muted small">
                                <?php echo $v['min_transaksi'] > 0
                                    ? 'Rp '.number_format($v['min_transaksi'],0,',','.')
                                    : '<span class="text-muted">—</span>'; ?>
                            </td>
                            <td class="text-center text-muted small">
                                <?php echo $v['max_diskon'] > 0
                                    ? 'Rp '.number_format($v['max_diskon'],0,',','.')
                                    : '<span class="text-muted">—</span>'; ?>
                            </td>
                            <td class="text-center">
                                <?php echo $v['kuota'] > 0
                                    ? '<span class="fw-semibold">'.$v['kuota'].'x</span>'
                                    : '<span class="kuy-badge kuy-badge-gray">∞ Bebas</span>'; ?>
                            </td>
                            <td class="text-center">
                                <span class="fw-semibold <?php echo $habis ? 'text-danger' : ''; ?>">
                                    <?php echo $v['terpakai']; ?>x
                                </span>
                            </td>
                            <td class="text-center" style="font-size:11px;">
                                <?php
                                if ($v['tgl_mulai'] || $v['tgl_selesai']) {
                                    echo ($v['tgl_mulai'] ? date('d/m/Y',strtotime($v['tgl_mulai'])) : '∞');
                                    echo ' — ';
                                    echo ($v['tgl_selesai'] ? date('d/m/Y',strtotime($v['tgl_selesai'])) : '∞');
                                } else {
                                    echo '<span class="kuy-badge kuy-badge-gray">Selamanya</span>';
                                }
                                ?>
                            </td>
                            <td class="text-center">
                                <?php
                                if ($expired)        echo '<span class="kuy-badge kuy-badge-danger">Kadaluarsa</span>';
                                elseif ($belum)      echo '<span class="kuy-badge kuy-badge-warning">Belum Aktif</span>';
                                elseif ($habis)      echo '<span class="kuy-badge kuy-badge-danger">Kuota Habis</span>';
                                elseif ($v['aktif']) echo '<span class="kuy-badge kuy-badge-success">Aktif</span>';
                                else                 echo '<span class="kuy-badge kuy-badge-gray">Nonaktif</span>';
                                ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <!-- Toggle Aktif -->
                                    <form method="POST">
                                        <input type="hidden" name="id_voucher"   value="<?php echo $v['id_voucher']; ?>">
                                        <input type="hidden" name="aktif_skrng"  value="<?php echo $v['aktif']; ?>">
                                        <button type="button"
                                            class="btn btn-sm fw-bold <?php echo $v['aktif'] ? 'btn-outline-warning' : 'btn-outline-success'; ?>"
                                            style="font-size:11px;"
                                            onclick="konfirmasiToggle(this.form, '<?php echo $v['aktif'] ? 'nonaktifkan' : 'aktifkan'; ?>')">
                                            <i class="bi <?php echo $v['aktif'] ? 'bi-pause-circle' : 'bi-play-circle'; ?>"></i>
                                        </button>
                                    </form>
                                    <!-- Hapus -->
                                    <form method="POST">
                                        <input type="hidden" name="id_voucher" value="<?php echo $v['id_voucher']; ?>">
                                        <button type="button" class="btn btn-sm btn-outline-danger fw-bold"
                                            style="font-size:11px;"
                                            onclick="konfirmasiHapus(this.form)">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="11" class="text-center py-5 text-muted">
                                <i class="bi bi-ticket-perforated fs-2 d-block mb-2"></i>
                                Belum ada voucher. Buat voucher pertama Anda!
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Info cara pakai -->
        <div class="kuy-alert-info mt-3">
            <i class="bi bi-info-circle-fill me-2"></i>
            Kode voucher dimasukkan oleh kasir saat membuat nota di halaman <strong>Kasir</strong>.
            Sistem akan otomatis menghitung diskon dan memotong total tagihan.
        </div>

    </div><!-- end kuy-content -->
</main>
</div><!-- end kuy-layout -->

<!-- ── MODAL TAMBAH VOUCHER ──────────────────────────────────────────── -->
<div class="modal fade" id="modalTambahVoucher" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header rounded-top-4" style="background:linear-gradient(to right,#1e2d40,#2e4d6e); color:white;">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-ticket-perforated-fill me-2" style="color:var(--gold,#c9a96e);"></i>Buat Voucher Baru
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body p-4">
                    <div class="row g-3">

                        <!-- Kode Voucher -->
                        <div class="col-md-4">
                            <label class="kuy-form-label">Kode Voucher <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="text" class="form-control text-uppercase fw-bold"
                                    name="kode" placeholder="LAUNDRY10"
                                    style="font-family:monospace; letter-spacing:1px;"
                                    oninput="this.value=this.value.toUpperCase()" required maxlength="20">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="generateKode()" title="Generate Kode Acak">
                                    <i class="bi bi-shuffle"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Nama Voucher -->
                        <div class="col-md-8">
                            <label class="kuy-form-label">Nama / Deskripsi Voucher <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nama"
                                placeholder="Cth: Diskon Pelanggan Baru, Promo Hari Jadi" required>
                        </div>

                        <!-- Tipe & Nilai -->
                        <div class="col-md-4">
                            <label class="kuy-form-label">Tipe Diskon <span class="text-danger">*</span></label>
                            <select class="form-select" name="tipe" id="tipeDiskon" onchange="toggleTipe()" required>
                                <option value="persen">% Persen</option>
                                <option value="nominal">Rp Nominal</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="kuy-form-label" id="labelNilai">Nilai Diskon (%) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="nilai" id="inputNilai"
                                    placeholder="10" min="1" step="0.01" required>
                                <span class="input-group-text" id="satuanNilai">%</span>
                            </div>
                        </div>
                        <div class="col-md-4" id="kolom_max_diskon">
                            <label class="kuy-form-label">Maks. Potongan (Rp)</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control" name="max_diskon"
                                    placeholder="0 = tidak terbatas" min="0">
                            </div>
                            <small class="text-muted">Isi 0 jika tidak ada batas</small>
                        </div>

                        <!-- Min Transaksi & Kuota -->
                        <div class="col-md-6">
                            <label class="kuy-form-label">Minimum Transaksi (Rp)</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" class="form-control" name="min_transaksi"
                                    placeholder="0 = tidak ada minimum" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="kuy-form-label">Kuota Penggunaan</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="kuota"
                                    placeholder="0 = tidak terbatas" min="0">
                                <span class="input-group-text">kali</span>
                            </div>
                        </div>

                        <!-- Tanggal -->
                        <div class="col-md-6">
                            <label class="kuy-form-label">Berlaku Mulai</label>
                            <input type="date" class="form-control" name="tgl_mulai">
                            <small class="text-muted">Kosongkan jika berlaku langsung</small>
                        </div>
                        <div class="col-md-6">
                            <label class="kuy-form-label">Berlaku Sampai</label>
                            <input type="date" class="form-control" name="tgl_selesai">
                            <small class="text-muted">Kosongkan jika tidak ada batas</small>
                        </div>

                    </div>
                </div>
                <div class="modal-footer bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary btn-sm fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="btn_tambah_voucher"
                        class="btn btn-sm fw-bold px-4"
                        style="background:var(--navy-800,#1e2d40);color:var(--cream-200,#e8d5b7);border:none;border-radius:8px;">
                        <i class="bi bi-save me-1"></i> Simpan Voucher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

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
                    <?php foreach(['pw_lama'=>'Password Lama','pw_baru_owner'=>'Password Baru','pw_konfirm_owner'=>'Konfirmasi'] as $nm=>$lb): ?>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
if (window.history.replaceState) window.history.replaceState(null, null, window.location.href);

// Generate kode voucher acak
function generateKode() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let kode = 'KUY';
    for (let i = 0; i < 5; i++) kode += chars.charAt(Math.floor(Math.random() * chars.length));
    document.querySelector('input[name="kode"]').value = kode;
}

// Toggle tampilan field berdasarkan tipe diskon
function toggleTipe() {
    const tipe    = document.getElementById('tipeDiskon').value;
    const label   = document.getElementById('labelNilai');
    const satuan  = document.getElementById('satuanNilai');
    const kolom   = document.getElementById('kolom_max_diskon');
    if (tipe === 'persen') {
        label.innerHTML  = 'Nilai Diskon (%) <span class="text-danger">*</span>';
        satuan.textContent = '%';
        kolom.style.display = '';
    } else {
        label.innerHTML  = 'Nilai Diskon (Rp) <span class="text-danger">*</span>';
        satuan.textContent = 'Rp';
        kolom.style.display = 'none';
    }
}

// Konfirmasi toggle aktif/nonaktif
function konfirmasiToggle(form, aksi) {
    Swal.fire({
        title: aksi.charAt(0).toUpperCase() + aksi.slice(1) + ' voucher?',
        icon: 'question', showCancelButton: true,
        confirmButtonColor: aksi === 'nonaktifkan' ? '#f59e0b' : '#10b981',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya!', cancelButtonText: 'Batal'
    }).then(r => {
        if (r.isConfirmed) {
            let i = document.createElement('input');
            i.type='hidden'; i.name='btn_toggle_voucher'; i.value='1';
            form.appendChild(i); form.submit();
        }
    });
}

// Konfirmasi hapus
function konfirmasiHapus(form) {
    Swal.fire({
        title: 'Hapus Voucher?',
        text: 'Voucher yang sudah terpakai di transaksi tetap tercatat.',
        icon: 'warning', showCancelButton: true,
        confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal'
    }).then(r => {
        if (r.isConfirmed) {
            let i = document.createElement('input');
            i.type='hidden'; i.name='btn_hapus_voucher'; i.value='1';
            form.appendChild(i); form.submit();
        }
    });
}

function togglePwOwner(id,el){const i=document.getElementById(id);if(i.type==='password'){i.type='text';el.classList.replace('bi-eye','bi-eye-slash');}else{i.type='password';el.classList.replace('bi-eye-slash','bi-eye');}}
function konfirmasiGantiPw(){const f=document.getElementById('formGantiPwOwner');if(!f.checkValidity()){f.reportValidity();return;}Swal.fire({title:'Ganti Password?',icon:'question',showCancelButton:true,confirmButtonColor:'#1e2d40',cancelButtonColor:'#6c757d',confirmButtonText:'Ya, Simpan!',cancelButtonText:'Batal'}).then(r=>{if(r.isConfirmed){let i=document.createElement('input');i.type='hidden';i.name='btn_ganti_pw_owner';i.value='1';f.appendChild(i);f.submit();}});}
</script>

<?php if ($pesan_sukses != ""): ?>
<script>Swal.fire({icon:'success',title:'Berhasil!',html:'<?php echo $pesan_sukses; ?>',showConfirmButton:false,timer:2500});</script>
<?php endif; ?>
<?php if ($pesan_error != ""): ?>
<script>Swal.fire({icon:'error',title:'Oops...',html:'<?php echo $pesan_error; ?>'});</script>
<?php endif; ?>
<?php if (!empty($pesan_sukses_pw)): ?>
<script>Swal.fire({icon:'success',title:'Berhasil!',text:'<?php echo $pesan_sukses_pw; ?>',showConfirmButton:false,timer:2000});</script>
<?php endif; ?>
<?php if (!empty($pesan_error_pw)): ?>
<script>Swal.fire({icon:'error',title:'Gagal!',text:'<?php echo addslashes($pesan_error_pw); ?>'});</script>
<?php endif; ?>

</body>
</html>