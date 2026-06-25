<?php
/**
 * =================================================================================
 * FILE: laporankuy.php
 * DESKRIPSI: Halaman Laporan Akuntansi Lengkap khusus Owner.
 * FITUR: Laba Rugi, Arus Kas, Rekap Transaksi, Analisis Layanan, Piutang.
 * FILTER: Per Bulan & Tahun.
 * EXPORT: Excel & PDF.
 * HAK AKSES: KHUSUS OWNER.
 * =================================================================================
 */

session_start();

$halaman_aktif = 'laporan';
include 'connectkuy.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    header("Location: loginkuy.php");
    exit();
}

$pesan_sukses_pw = "";
$pesan_error_pw  = "";

// =================================================================================
// GANTI PASSWORD OWNER (Modal Navbar)
// =================================================================================
if (isset($_POST['btn_ganti_pw_owner'])) {
    $pw_lama    = $_POST['pw_lama'];
    $pw_baru    = $_POST['pw_baru_owner'];
    $pw_konfirm = $_POST['pw_konfirm_owner'];
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
        if (mysqli_stmt_execute($stmt_up)) { $pesan_sukses_pw = "Password berhasil diubah!"; }
        else { $pesan_error_pw = "Gagal mengubah password."; }
        mysqli_stmt_close($stmt_up);
    }
}

// =================================================================================
// FILTER BULAN & TAHUN
// =================================================================================
$bulan_ini  = date('m');
$tahun_ini  = date('Y');
$f_bulan    = isset($_GET['bulan']) ? (int)$_GET['bulan'] : (int)$bulan_ini;
$f_tahun    = isset($_GET['tahun']) ? (int)$_GET['tahun'] : (int)$tahun_ini;

// Validasi range
if ($f_bulan < 1 || $f_bulan > 12) $f_bulan = (int)$bulan_ini;
if ($f_tahun < 2020 || $f_tahun > 2099) $f_tahun = (int)$tahun_ini;

$periode_awal  = sprintf('%04d-%02d-01', $f_tahun, $f_bulan);
$periode_akhir = date('Y-m-t', strtotime($periode_awal)); // t = hari terakhir bulan
$label_periode = date('F Y', strtotime($periode_awal));

// Periode bulan sebelumnya (untuk perbandingan)
$bln_lalu_ts    = strtotime('-1 month', strtotime($periode_awal));
$bln_lalu_awal  = date('Y-m-01', $bln_lalu_ts);
$bln_lalu_akhir = date('Y-m-t', $bln_lalu_ts);

// =================================================================================
// SECTION 1: LABA RUGI
// =================================================================================

// Pendapatan lunas bulan ini
$q = mysqli_prepare($koneksi, "SELECT COALESCE(SUM(total),0) as val FROM t_transaksi WHERE st_bayar='lunas' AND DATE(tgl) BETWEEN ? AND ?");
mysqli_stmt_bind_param($q, "ss", $periode_awal, $periode_akhir);
mysqli_stmt_execute($q);
$pendapatan_lunas = mysqli_fetch_assoc(mysqli_stmt_get_result($q))['val'];
mysqli_stmt_close($q);

// Pendapatan belum lunas (piutang) bulan ini
$q = mysqli_prepare($koneksi, "SELECT COALESCE(SUM(total),0) as val FROM t_transaksi WHERE st_bayar != 'lunas' AND DATE(tgl) BETWEEN ? AND ?");
mysqli_stmt_bind_param($q, "ss", $periode_awal, $periode_akhir);
mysqli_stmt_execute($q);
$pendapatan_piutang = mysqli_fetch_assoc(mysqli_stmt_get_result($q))['val'];
mysqli_stmt_close($q);

// Total pengeluaran bulan ini
$q = mysqli_prepare($koneksi, "SELECT COALESCE(SUM(jumlah),0) as val FROM t_biaya_op WHERE DATE(tgl) BETWEEN ? AND ?");
mysqli_stmt_bind_param($q, "ss", $periode_awal, $periode_akhir);
mysqli_stmt_execute($q);
$total_pengeluaran = mysqli_fetch_assoc(mysqli_stmt_get_result($q))['val'];
mysqli_stmt_close($q);

// Pengeluaran per kategori
$q = mysqli_prepare($koneksi, "SELECT kategori, COALESCE(SUM(jumlah),0) as total FROM t_biaya_op WHERE DATE(tgl) BETWEEN ? AND ? GROUP BY kategori ORDER BY total DESC");
mysqli_stmt_bind_param($q, "ss", $periode_awal, $periode_akhir);
mysqli_stmt_execute($q);
$pengeluaran_per_kategori = mysqli_stmt_get_result($q);
$data_kategori = [];
while ($r = mysqli_fetch_assoc($pengeluaran_per_kategori)) $data_kategori[] = $r;
mysqli_stmt_close($q);

$laba_bersih   = $pendapatan_lunas - $total_pengeluaran;
$margin_persen = $pendapatan_lunas > 0 ? round(($laba_bersih / $pendapatan_lunas) * 100, 1) : 0;

// Pendapatan bulan lalu (untuk perbandingan)
$q = mysqli_prepare($koneksi, "SELECT COALESCE(SUM(total),0) as val FROM t_transaksi WHERE st_bayar='lunas' AND DATE(tgl) BETWEEN ? AND ?");
mysqli_stmt_bind_param($q, "ss", $bln_lalu_awal, $bln_lalu_akhir);
mysqli_stmt_execute($q);
$pendapatan_bln_lalu = mysqli_fetch_assoc(mysqli_stmt_get_result($q))['val'];
mysqli_stmt_close($q);

$selisih_persen = $pendapatan_bln_lalu > 0
    ? round((($pendapatan_lunas - $pendapatan_bln_lalu) / $pendapatan_bln_lalu) * 100, 1)
    : 0;

// =================================================================================
// SECTION 1B: AKUMULASI LABA/RUGI (SALDO BERJALAN ANTAR BULAN)
// -----------------------------------------------------------------------------
// Konsep: Laba Rugi bulanan di atas TETAP independen per periode (agar performa
// bulan ini tidak "tertutupi" oleh bulan lalu). Tapi di sisi lain, owner juga
// perlu tahu posisi keuangan KUMULATIF -> kalau bulan lalu rugi, defisit itu
// tidak hilang, melainkan "dibawa" dan harus ditutup oleh laba bulan berikutnya.
// Ini sama seperti konsep "Laba Ditahan / Retained Earnings" dalam akuntansi.
// =================================================================================

// A. Cari tanggal data paling awal (transaksi lunas / pengeluaran) untuk label
//    "Akumulasi dihitung sejak bulan ..."
$q = mysqli_prepare($koneksi, "SELECT MIN(DATE(tgl)) as val FROM t_transaksi WHERE st_bayar='lunas'");
mysqli_stmt_execute($q);
$tgl_awal_trx = mysqli_fetch_assoc(mysqli_stmt_get_result($q))['val'];
mysqli_stmt_close($q);

$q = mysqli_prepare($koneksi, "SELECT MIN(tgl) as val FROM t_biaya_op");
mysqli_stmt_execute($q);
$tgl_awal_biaya = mysqli_fetch_assoc(mysqli_stmt_get_result($q))['val'];
mysqli_stmt_close($q);

$tgl_awal_kandidat   = array_filter([$tgl_awal_trx, $tgl_awal_biaya], fn($v) => $v !== null);
$tgl_awal_data       = !empty($tgl_awal_kandidat) ? min($tgl_awal_kandidat) : $periode_awal;
$label_awal_akumulasi = date('F Y', strtotime($tgl_awal_data));

// B. Hitung akumulasi pendapatan & pengeluaran SEBELUM periode yang dipilih
//    (ini menjadi "Saldo Awal" / saldo bawaan dari bulan-bulan sebelumnya)
$q = mysqli_prepare($koneksi, "SELECT COALESCE(SUM(total),0) as val FROM t_transaksi WHERE st_bayar='lunas' AND DATE(tgl) < ?");
mysqli_stmt_bind_param($q, "s", $periode_awal);
mysqli_stmt_execute($q);
$pendapatan_sebelum = mysqli_fetch_assoc(mysqli_stmt_get_result($q))['val'];
mysqli_stmt_close($q);

$q = mysqli_prepare($koneksi, "SELECT COALESCE(SUM(jumlah),0) as val FROM t_biaya_op WHERE tgl < ?");
mysqli_stmt_bind_param($q, "s", $periode_awal);
mysqli_stmt_execute($q);
$pengeluaran_sebelum = mysqli_fetch_assoc(mysqli_stmt_get_result($q))['val'];
mysqli_stmt_close($q);

// Saldo Awal = akumulasi laba/rugi dari SEBELUM bulan yang dipilih.
// Bisa negatif jika akumulasi bulan-bulan sebelumnya masih defisit (rugi berjalan).
$akumulasi_awal  = $pendapatan_sebelum - $pengeluaran_sebelum;

// Saldo Akhir = Saldo Awal + Laba/Rugi bulan ini.
// Jika bulan ini untung, sebagian/seluruh defisit bulan lalu bisa "tertutup".
// Jika bulan ini rugi lagi, defisit makin bertambah dan akan "dibawa" ke bulan depan.
$akumulasi_akhir = $akumulasi_awal + $laba_bersih;

// =================================================================================
// SECTION 2: ARUS KAS (Per Minggu dalam bulan)
// =================================================================================
$arus_kas_labels  = [];
$arus_kas_masuk   = [];
$arus_kas_keluar  = [];

$total_hari  = (int)date('t', strtotime($periode_awal));
$minggu_ke   = 1;
$hari_start  = 1;

while ($hari_start <= $total_hari) {
    $hari_end  = min($hari_start + 6, $total_hari);
    $tgl_start = sprintf('%04d-%02d-%02d', $f_tahun, $f_bulan, $hari_start);
    $tgl_end   = sprintf('%04d-%02d-%02d', $f_tahun, $f_bulan, $hari_end);

    $arus_kas_labels[] = "Minggu $minggu_ke";

    $q = mysqli_prepare($koneksi, "SELECT COALESCE(SUM(total),0) as val FROM t_transaksi WHERE st_bayar='lunas' AND DATE(tgl) BETWEEN ? AND ?");
    mysqli_stmt_bind_param($q, "ss", $tgl_start, $tgl_end);
    mysqli_stmt_execute($q);
    $arus_kas_masuk[] = (float)mysqli_fetch_assoc(mysqli_stmt_get_result($q))['val'];
    mysqli_stmt_close($q);

    $q = mysqli_prepare($koneksi, "SELECT COALESCE(SUM(jumlah),0) as val FROM t_biaya_op WHERE DATE(tgl) BETWEEN ? AND ?");
    mysqli_stmt_bind_param($q, "ss", $tgl_start, $tgl_end);
    mysqli_stmt_execute($q);
    $arus_kas_keluar[] = (float)mysqli_fetch_assoc(mysqli_stmt_get_result($q))['val'];
    mysqli_stmt_close($q);

    $hari_start += 7;
    $minggu_ke++;
}

// =================================================================================
// SECTION 3: REKAP TRANSAKSI
// =================================================================================
$q = mysqli_prepare($koneksi,
    "SELECT t.*, p.nama, p.hp, l.nama_paket, u.username as kasir
     FROM t_transaksi t
     JOIN t_pelanggan p ON t.id_pel = p.id_pel
     LEFT JOIN t_layanan l ON t.id_layanan = l.id_layanan
     LEFT JOIN t_user u ON t.id_user = u.id_user
     WHERE DATE(t.tgl) BETWEEN ? AND ?
     ORDER BY t.id_trans DESC");
mysqli_stmt_bind_param($q, "ss", $periode_awal, $periode_akhir);
mysqli_stmt_execute($q);
$hasil_transaksi = mysqli_stmt_get_result($q);
$data_transaksi  = [];
$total_berat_all = 0;
$total_omset_all = 0;
while ($r = mysqli_fetch_assoc($hasil_transaksi)) {
    $data_transaksi[]  = $r;
    $total_berat_all  += $r['berat'];
    $total_omset_all  += $r['total'];
}
mysqli_stmt_close($q);
$jml_transaksi   = count($data_transaksi);
$rata_nilai_trx  = $jml_transaksi > 0 ? round($total_omset_all / $jml_transaksi) : 0;

// =================================================================================
// SECTION 4: ANALISIS LAYANAN (Paket Terlaris)
// =================================================================================
$q = mysqli_prepare($koneksi,
    "SELECT l.nama_paket, COUNT(t.id_trans) as jumlah, SUM(t.total) as omset, SUM(t.berat) as total_berat
     FROM t_transaksi t
     LEFT JOIN t_layanan l ON t.id_layanan = l.id_layanan
     WHERE DATE(t.tgl) BETWEEN ? AND ?
     GROUP BY t.id_layanan, l.nama_paket
     ORDER BY jumlah DESC");
mysqli_stmt_bind_param($q, "ss", $periode_awal, $periode_akhir);
mysqli_stmt_execute($q);
$hasil_layanan = mysqli_stmt_get_result($q);
$data_layanan  = [];
while ($r = mysqli_fetch_assoc($hasil_layanan)) $data_layanan[] = $r;
mysqli_stmt_close($q);

// =================================================================================
// SECTION 5: PIUTANG (Belum Lunas)
// =================================================================================
$q = mysqli_prepare($koneksi,
    "SELECT t.*, p.nama, p.hp, DATEDIFF(CURDATE(), DATE(t.tgl)) as hari_tertunggak
     FROM t_transaksi t
     JOIN t_pelanggan p ON t.id_pel = p.id_pel
     WHERE t.st_bayar != 'lunas'
     ORDER BY hari_tertunggak DESC");
mysqli_stmt_execute($q);
$hasil_piutang   = mysqli_stmt_get_result($q);
$data_piutang    = [];
$total_piutang   = 0;
while ($r = mysqli_fetch_assoc($hasil_piutang)) {
    $data_piutang[] = $r;
    $total_piutang += $r['total'];
}
mysqli_stmt_close($q);

// =================================================================================
// HANDLE EXPORT EXCEL — XML SpreadsheetML Multi-Sheet Profesional
// =================================================================================
$mode_export = isset($_GET['export']) ? $_GET['export'] : '';

if ($mode_export === 'excel') {

    // ── Helper functions ──────────────────────────────────────────────
    // Cell XML dengan tipe data yang benar agar Excel bisa menghitung
    $xmlStr = fn($v) => '<Data ss:Type="String">' . htmlspecialchars((string)$v, ENT_XML1) . '</Data>';
    $xmlNum = fn($v) => '<Data ss:Type="Number">' . (float)$v . '</Data>';

    // Style ID references (didefinisikan di <Styles>)
    // ID:  1=judul, 2=subjudul, 3=header_col, 4=label, 5=val_teks, 6=val_angka,
    //      7=val_center, 8=total_gelap, 9=total_angka_gelap, 10=laba, 11=laba_angka,
    //      12=rugi, 13=rugi_angka, 14=lunas, 15=belum, 16=warn, 17=danger,
    //      18=header_merah, 19=zebra_genap, 20=zebra_ganjil, 21=ttl_merah, 22=ttl_merah_angka
    //      23=header_teal, 24=ttl_teal, 25=info_kecil, 26=medal

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="Laporan_LaundryKuy_' . $label_periode . '.xls"');
    header('Cache-Control: max-age=0');
    header('Pragma: no-cache');

    // Mulai output XML
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
               xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
               xmlns:o="urn:schemas-microsoft-com:office:office"
               xmlns:x="urn:schemas-microsoft-com:office:excel">' . "\n";

    // ── STYLES ────────────────────────────────────────────────────────
    echo <<<'XMLSTYLES'
<Styles>
  <Style ss:ID="s1">
    <Alignment ss:Horizontal="Left" ss:Vertical="Center" ss:WrapText="0"/>
    <Font ss:FontName="Calibri" ss:Size="14" ss:Bold="1" ss:Color="#FFFFFF"/>
    <Interior ss:Color="#1e2d40" ss:Pattern="Solid"/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#c9a96e"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1e2d40"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1e2d40"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1e2d40"/></Borders>
  </Style>
  <Style ss:ID="s2">
    <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>
    <Interior ss:Color="#2e4d6e" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="s3">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="0"/>
    <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/>
    <Interior ss:Color="#1e2d40" ss:Pattern="Solid"/>
    <Borders>
      <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#c9a96e"/>
      <Border ss:Position="Top"    ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1e2d40"/>
      <Border ss:Position="Left"   ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#3d6491"/>
      <Border ss:Position="Right"  ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#3d6491"/>
    </Borders>
  </Style>
  <Style ss:ID="s4">
    <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#1e2d40"/>
    <Interior ss:Color="#f5edd8" ss:Pattern="Solid"/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/></Borders>
  </Style>
  <Style ss:ID="s5">
    <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1e2d40"/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/></Borders>
  </Style>
  <Style ss:ID="s6">
    <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1e2d40"/>
    <NumberFormat ss:Format='&quot;Rp &quot;#,##0'/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/></Borders>
  </Style>
  <Style ss:ID="s7">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1e2d40"/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/></Borders>
  </Style>
  <Style ss:ID="s8">
    <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/>
    <Interior ss:Color="#1e2d40" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="s9">
    <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#e8d5b7"/>
    <Interior ss:Color="#1e2d40" ss:Pattern="Solid"/>
    <NumberFormat ss:Format='&quot;Rp &quot;#,##0'/>
  </Style>
  <Style ss:ID="s10">
    <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>
    <Interior ss:Color="#166534" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="s11">
    <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>
    <Interior ss:Color="#166534" ss:Pattern="Solid"/>
    <NumberFormat ss:Format='&quot;Rp &quot;#,##0'/>
  </Style>
  <Style ss:ID="s12">
    <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>
    <Interior ss:Color="#991b1b" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="s13">
    <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>
    <Interior ss:Color="#991b1b" ss:Pattern="Solid"/>
    <NumberFormat ss:Format='&quot;Rp &quot;#,##0'/>
  </Style>
  <Style ss:ID="s14">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#166534"/>
    <Interior ss:Color="#dcfce7" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="s15">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#991b1b"/>
    <Interior ss:Color="#fee2e2" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="s16">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#854d0e"/>
    <Interior ss:Color="#fef9c3" ss:Pattern="Solid"/>
  </Style>

  <!-- 17: Piutang danger (>7 hari) -->
  <Style ss:ID="s17">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#991b1b"/>
    <Interior ss:Color="#fee2e2" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="s18">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/>
    <Interior ss:Color="#991b1b" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="s19">
    <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1e2d40"/>
    <Interior ss:Color="#fdf8f0" ss:Pattern="Solid"/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/></Borders>
  </Style>
  <Style ss:ID="s20">
    <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1e2d40"/>
    <Interior ss:Color="#fdf8f0" ss:Pattern="Solid"/>
    <NumberFormat ss:Format='&quot;Rp &quot;#,##0'/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/></Borders>
  </Style>
  <Style ss:ID="s21">
    <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/>
    <Interior ss:Color="#991b1b" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="s22">
    <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/>
    <Interior ss:Color="#991b1b" ss:Pattern="Solid"/>
    <NumberFormat ss:Format='&quot;Rp &quot;#,##0'/>
  </Style>
  <Style ss:ID="s23">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/>
    <Interior ss:Color="#0d9488" ss:Pattern="Solid"/>
  </Style>
  <Style ss:ID="s24">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1e2d40"/>
    <NumberFormat ss:Format='0&quot; hari&quot;'/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/></Borders>
  </Style>
  <Style ss:ID="s25">
    <Alignment ss:Horizontal="Left" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="9" ss:Italic="1" ss:Color="#7a92a8"/>
  </Style>
  <Style ss:ID="s26">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="14"/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/></Borders>
  </Style>
  <Style ss:ID="s27">
    <Alignment ss:Horizontal="Right" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1e2d40"/>
    <NumberFormat ss:Format='&quot;Rp &quot;#,##0'/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/></Borders>
  </Style>
  <Style ss:ID="s28">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1e2d40"/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/></Borders>
  </Style>
  <Style ss:ID="s29">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1e2d40"/>
    <Interior ss:Color="#fdf8f0" ss:Pattern="Solid"/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/></Borders>
  </Style>
  <Style ss:ID="s30">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1e2d40"/>
    <NumberFormat ss:Format='0.0&quot; Kg&quot;'/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/></Borders>
  </Style>
  <Style ss:ID="s31">
    <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
    <Font ss:FontName="Calibri" ss:Size="10" ss:Color="#1e2d40"/>
    <Interior ss:Color="#fdf8f0" ss:Pattern="Solid"/>
    <NumberFormat ss:Format='0.0&quot; Kg&quot;'/>
    <Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#e8d5b7"/></Borders>
  </Style>

</Styles>

XMLSTYLES;

    // ════════════════════════════════════════════════════════════════════
    // SHEET 1: LAPORAN LABA RUGI
    // ════════════════════════════════════════════════════════════════════
    echo '<Worksheet ss:Name="Laba Rugi">
<Table ss:DefaultRowHeight="18">
  <Column ss:Width="220"/>
  <Column ss:Width="180"/>
';

    $row = function($cells, $height = 18) {
        $out = '<Row ss:Height="' . $height . '">';
        foreach ($cells as $c) {
            $style = $c['style'] ?? 's5';
            $merge = isset($c['merge']) ? ' ss:MergeAcross="' . $c['merge'] . '"' : '';
            $data  = $c['data'] ?? '';
            $out  .= '<Cell ss:StyleID="' . $style . '"' . $merge . '>' . $data . '</Cell>';
        }
        $out .= '</Row>';
        return $out;
    };

    // Judul
    echo $row([
        ['style'=>'s1','merge'=>1,'data'=>'<Data ss:Type="String">📊 LAPORAN LABA RUGI — ' . strtoupper($label_periode) . '</Data>']
    ], 30);
    echo $row([['style'=>'s25','merge'=>1,'data'=>'<Data ss:Type="String">Dicetak: ' . date('d F Y, H:i') . ' WIB  |  Sistem LaundryKuy</Data>']]);
    echo '<Row ss:Height="8"/>';

    // Info Periode
    echo $row([['style'=>'s2','merge'=>1,'data'=>'<Data ss:Type="String">  INFORMASI PERIODE</Data>']], 22);
    echo $row([['style'=>'s4','data'=>'<Data ss:Type="String">Periode Laporan</Data>'],   ['style'=>'s5','data'=>'<Data ss:Type="String">' . $label_periode . '</Data>']]);
    echo $row([['style'=>'s4','data'=>'<Data ss:Type="String">Tanggal Awal</Data>'],      ['style'=>'s5','data'=>'<Data ss:Type="String">' . date('d F Y', strtotime($periode_awal)) . '</Data>']]);
    echo $row([['style'=>'s4','data'=>'<Data ss:Type="String">Tanggal Akhir</Data>'],     ['style'=>'s5','data'=>'<Data ss:Type="String">' . date('d F Y', strtotime($periode_akhir)) . '</Data>']]);
    echo '<Row ss:Height="8"/>';

    // PENDAPATAN
    echo $row([['style'=>'s2','merge'=>1,'data'=>'<Data ss:Type="String">  PENDAPATAN</Data>']], 22);
    echo $row([['style'=>'s4','data'=>'<Data ss:Type="String">Pendapatan Lunas</Data>'],           ['style'=>'s6','data'=>'<Data ss:Type="Number">' . (float)$pendapatan_lunas . '</Data>']]);
    echo $row([['style'=>'s4','data'=>'<Data ss:Type="String">Piutang (Belum Dibayar)</Data>'],    ['style'=>'s6','data'=>'<Data ss:Type="Number">' . (float)$pendapatan_piutang . '</Data>']]);
    echo $row([['style'=>'s8','data'=>'<Data ss:Type="String">Total Potensi Pendapatan</Data>'],   ['style'=>'s9','data'=>'<Data ss:Type="Number">' . (float)($pendapatan_lunas + $pendapatan_piutang) . '</Data>']]);
    echo '<Row ss:Height="8"/>';

    // PENGELUARAN
    echo $row([['style'=>'s2','merge'=>1,'data'=>'<Data ss:Type="String">  PENGELUARAN PER KATEGORI</Data>']], 22);
    if (count($data_kategori) > 0) {
        foreach ($data_kategori as $idx => $kat) {
            $persen = $total_pengeluaran > 0 ? round(($kat['total'] / $total_pengeluaran) * 100) : 0;
            $st  = ($idx % 2 == 0) ? 's5' : 's19';
            $sta = ($idx % 2 == 0) ? 's6' : 's20';
            echo $row([
                ['style'=>$st,  'data'=>'<Data ss:Type="String">' . htmlspecialchars($kat['kategori']) . ' (' . $persen . '%)</Data>'],
                ['style'=>$sta, 'data'=>'<Data ss:Type="Number">' . (float)$kat['total'] . '</Data>']
            ]);
        }
    } else {
        echo $row([['style'=>'s5','merge'=>1,'data'=>'<Data ss:Type="String">Tidak ada pengeluaran di periode ini</Data>']]);
    }
    echo $row([['style'=>'s8','data'=>'<Data ss:Type="String">Total Pengeluaran</Data>'], ['style'=>'s9','data'=>'<Data ss:Type="Number">' . (float)$total_pengeluaran . '</Data>']]);
    echo '<Row ss:Height="8"/>';

    // LABA BERSIH
    $st_laba = $laba_bersih >= 0 ? 's10' : 's12';
    $st_rp   = $laba_bersih >= 0 ? 's11' : 's13';
    $label_lb = $laba_bersih >= 0 ? '✅ LABA BERSIH' : '⚠️ RUGI BERSIH';
    echo $row([
        ['style'=>$st_laba,'data'=>'<Data ss:Type="String">' . $label_lb . '</Data>'],
        ['style'=>$st_rp,  'data'=>'<Data ss:Type="Number">' . (float)abs($laba_bersih) . '</Data>']
    ], 24);
    echo $row([['style'=>'s4','data'=>'<Data ss:Type="String">Margin Keuntungan</Data>'], ['style'=>'s7','data'=>'<Data ss:Type="String">' . $margin_persen . '%</Data>']]);
    echo '<Row ss:Height="8"/>';

    // PERBANDINGAN
    echo $row([['style'=>'s2','merge'=>1,'data'=>'<Data ss:Type="String">  PERBANDINGAN BULAN LALU</Data>']], 22);
    echo $row([['style'=>'s4','data'=>'<Data ss:Type="String">Pendapatan Bulan Lalu</Data>'],  ['style'=>'s6','data'=>'<Data ss:Type="Number">' . (float)$pendapatan_bln_lalu . '</Data>']]);
    $tanda = $selisih_persen >= 0 ? '+' : '';
    echo $row([['style'=>'s4','data'=>'<Data ss:Type="String">Pertumbuhan</Data>'],           ['style'=>'s7','data'=>'<Data ss:Type="String">' . $tanda . $selisih_persen . '%</Data>']]);

    echo '</Table>
<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
  <FreezePanes/>
  <FrozenNoSplit/>
  <SplitHorizontal>3</SplitHorizontal>
  <TopRowBottomPane>3</TopRowBottomPane>
  <ActivePane>2</ActivePane>
  <Selected/>
</WorksheetOptions>
</Worksheet>';

    // ════════════════════════════════════════════════════════════════════
    // SHEET 2: REKAP TRANSAKSI
    // ════════════════════════════════════════════════════════════════════
    echo '<Worksheet ss:Name="Rekap Transaksi">
<Table ss:DefaultRowHeight="18">
  <Column ss:Width="80"/>
  <Column ss:Width="90"/>
  <Column ss:Width="150"/>
  <Column ss:Width="120"/>
  <Column ss:Width="130"/>
  <Column ss:Width="80"/>
  <Column ss:Width="130"/>
  <Column ss:Width="90"/>
  <Column ss:Width="100"/>
';
    // Judul
    echo '<Row ss:Height="30"><Cell ss:StyleID="s1" ss:MergeAcross="8"><Data ss:Type="String">🧾 REKAP TRANSAKSI — ' . strtoupper($label_periode) . '</Data></Cell></Row>';
    echo '<Row ss:Height="16"><Cell ss:StyleID="s25" ss:MergeAcross="8"><Data ss:Type="String">' . $jml_transaksi . ' nota  |  Total berat ' . $total_berat_all . ' Kg  |  Rata-rata Rp ' . number_format($rata_nilai_trx,0,',','.') . '/nota  |  Dicetak: ' . date('d F Y H:i') . '</Data></Cell></Row>';
    echo '<Row ss:Height="6"/>';

    // Header
    echo '<Row ss:Height="22">
      <Cell ss:StyleID="s3"><Data ss:Type="String">No. Nota</Data></Cell>
      <Cell ss:StyleID="s3"><Data ss:Type="String">Tanggal</Data></Cell>
      <Cell ss:StyleID="s3"><Data ss:Type="String">Pelanggan</Data></Cell>
      <Cell ss:StyleID="s3"><Data ss:Type="String">No. HP</Data></Cell>
      <Cell ss:StyleID="s3"><Data ss:Type="String">Paket Layanan</Data></Cell>
      <Cell ss:StyleID="s3"><Data ss:Type="String">Berat (Kg)</Data></Cell>
      <Cell ss:StyleID="s3"><Data ss:Type="String">Total Tagihan</Data></Cell>
      <Cell ss:StyleID="s3"><Data ss:Type="String">Status Bayar</Data></Cell>
      <Cell ss:StyleID="s3"><Data ss:Type="String">Kasir</Data></Cell>
    </Row>';

    if (count($data_transaksi) > 0) {
        foreach ($data_transaksi as $idx => $t) {
            $ganjil   = ($idx % 2 == 0);
            $st_t     = $ganjil ? 's5'  : 's19';
            $st_c     = $ganjil ? 's7'  : 's29';
            $st_rp    = $ganjil ? 's6'  : 's20';
            $st_kg    = $ganjil ? 's30' : 's31';
            $st_bayar = $t['st_bayar'] == 'lunas' ? 's14' : 's15';
            $l_bayar  = $t['st_bayar'] == 'lunas' ? '✔ Lunas' : '✘ Belum';

            echo '<Row ss:Height="18">
              <Cell ss:StyleID="s7"><Data ss:Type="String">KUY-' . sprintf("%02d",$t['id_trans']) . '</Data></Cell>
              <Cell ss:StyleID="' . $st_c . '"><Data ss:Type="String">' . date('d/m/Y',strtotime($t['tgl'])) . '</Data></Cell>
              <Cell ss:StyleID="' . $st_t . '"><Data ss:Type="String">' . htmlspecialchars($t['nama']) . '</Data></Cell>
              <Cell ss:StyleID="' . $st_t . '"><Data ss:Type="String">' . $t['hp'] . '</Data></Cell>
              <Cell ss:StyleID="' . $st_t . '"><Data ss:Type="String">' . htmlspecialchars(ucwords($t['nama_paket'] ?? 'Terhapus')) . '</Data></Cell>
              <Cell ss:StyleID="' . $st_kg  . '"><Data ss:Type="Number">' . (float)$t['berat'] . '</Data></Cell>
              <Cell ss:StyleID="' . $st_rp  . '"><Data ss:Type="Number">' . (float)$t['total'] . '</Data></Cell>
              <Cell ss:StyleID="' . $st_bayar . '"><Data ss:Type="String">' . $l_bayar . '</Data></Cell>
              <Cell ss:StyleID="' . $st_t . '"><Data ss:Type="String">' . htmlspecialchars($t['kasir'] ?? '-') . '</Data></Cell>
            </Row>';
        }
    } else {
        echo '<Row><Cell ss:StyleID="s25" ss:MergeAcross="8"><Data ss:Type="String">Tidak ada transaksi di periode ini</Data></Cell></Row>';
    }

    // Baris total
    echo '<Row ss:Height="22">
      <Cell ss:StyleID="s8" ss:MergeAcross="4"><Data ss:Type="String">TOTAL ' . $jml_transaksi . ' NOTA</Data></Cell>
      <Cell ss:StyleID="s9"><Data ss:Type="Number">' . (float)$total_berat_all . '</Data></Cell>
      <Cell ss:StyleID="s9"><Data ss:Type="Number">' . (float)$total_omset_all . '</Data></Cell>
      <Cell ss:StyleID="s8" ss:MergeAcross="1"></Cell>
    </Row>';

    echo '</Table>
<WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
  <FreezePanes/>
  <FrozenNoSplit/>
  <SplitHorizontal>4</SplitHorizontal>
  <TopRowBottomPane>4</TopRowBottomPane>
  <ActivePane>2</ActivePane>
</WorksheetOptions>
</Worksheet>';

    // ════════════════════════════════════════════════════════════════════
    // SHEET 3: PIUTANG
    // ════════════════════════════════════════════════════════════════════
    echo '<Worksheet ss:Name="Piutang">
<Table ss:DefaultRowHeight="18">
  <Column ss:Width="80"/>
  <Column ss:Width="90"/>
  <Column ss:Width="150"/>
  <Column ss:Width="130"/>
  <Column ss:Width="130"/>
  <Column ss:Width="110"/>
';
    echo '<Row ss:Height="30"><Cell ss:StyleID="s21" ss:MergeAcross="5"><Data ss:Type="String">⚠️ LAPORAN PIUTANG — SEMUA PERIODE BELUM LUNAS</Data></Cell></Row>';
    echo '<Row ss:Height="16"><Cell ss:StyleID="s25" ss:MergeAcross="5"><Data ss:Type="String">' . count($data_piutang) . ' tagihan tertunggak  |  Total Rp ' . number_format($total_piutang,0,',','.') . '  |  Dicetak: ' . date('d F Y H:i') . '</Data></Cell></Row>';
    echo '<Row ss:Height="6"/>';

    if (count($data_piutang) > 0) {
        echo '<Row ss:Height="22">
          <Cell ss:StyleID="s18"><Data ss:Type="String">No. Nota</Data></Cell>
          <Cell ss:StyleID="s18"><Data ss:Type="String">Tanggal</Data></Cell>
          <Cell ss:StyleID="s18"><Data ss:Type="String">Pelanggan</Data></Cell>
          <Cell ss:StyleID="s18"><Data ss:Type="String">No. HP</Data></Cell>
          <Cell ss:StyleID="s18"><Data ss:Type="String">Total Tagihan</Data></Cell>
          <Cell ss:StyleID="s18"><Data ss:Type="String">Hari Tertunggak</Data></Cell>
        </Row>';

        foreach ($data_piutang as $p) {
            $hari = (int)$p['hari_tertunggak'];
            $st_hari = $hari <= 3 ? 's7' : ($hari <= 7 ? 's16' : 's17');
            echo '<Row ss:Height="18">
              <Cell ss:StyleID="s7"><Data ss:Type="String">KUY-' . sprintf("%02d",$p['id_trans']) . '</Data></Cell>
              <Cell ss:StyleID="s7"><Data ss:Type="String">' . date('d/m/Y',strtotime($p['tgl'])) . '</Data></Cell>
              <Cell ss:StyleID="s5"><Data ss:Type="String">' . htmlspecialchars($p['nama']) . '</Data></Cell>
              <Cell ss:StyleID="s5"><Data ss:Type="String">' . $p['hp'] . '</Data></Cell>
              <Cell ss:StyleID="s6"><Data ss:Type="Number">' . (float)$p['total'] . '</Data></Cell>
              <Cell ss:StyleID="' . $st_hari . '"><Data ss:Type="Number">' . $hari . '</Data></Cell>
            </Row>';
        }
        echo '<Row ss:Height="22">
          <Cell ss:StyleID="s21" ss:MergeAcross="3"><Data ss:Type="String">TOTAL PIUTANG</Data></Cell>
          <Cell ss:StyleID="s22"><Data ss:Type="Number">' . (float)$total_piutang . '</Data></Cell>
          <Cell ss:StyleID="s21"></Cell>
        </Row>';
    } else {
        echo '<Row><Cell ss:StyleID="s14" ss:MergeAcross="5"><Data ss:Type="String">✅ Semua tagihan sudah lunas!</Data></Cell></Row>';
    }

    echo '</Table></Worksheet>';

    // ════════════════════════════════════════════════════════════════════
    // SHEET 4: ANALISIS LAYANAN
    // ════════════════════════════════════════════════════════════════════
    echo '<Worksheet ss:Name="Analisis Layanan">
<Table ss:DefaultRowHeight="18">
  <Column ss:Width="60"/>
  <Column ss:Width="180"/>
  <Column ss:Width="110"/>
  <Column ss:Width="110"/>
  <Column ss:Width="130"/>
';
    echo '<Row ss:Height="30"><Cell ss:StyleID="s1" ss:MergeAcross="4"><Data ss:Type="String">🏆 ANALISIS PERFORMA LAYANAN — ' . strtoupper($label_periode) . '</Data></Cell></Row>';
    echo '<Row ss:Height="16"><Cell ss:StyleID="s25" ss:MergeAcross="4"><Data ss:Type="String">Ranking berdasarkan jumlah order terbanyak  |  Dicetak: ' . date('d F Y H:i') . '</Data></Cell></Row>';
    echo '<Row ss:Height="6"/>';

    if (count($data_layanan) > 0) {
        echo '<Row ss:Height="22">
          <Cell ss:StyleID="s23"><Data ss:Type="String">Rank</Data></Cell>
          <Cell ss:StyleID="s23"><Data ss:Type="String">Nama Paket Layanan</Data></Cell>
          <Cell ss:StyleID="s23"><Data ss:Type="String">Jumlah Order</Data></Cell>
          <Cell ss:StyleID="s23"><Data ss:Type="String">Total Berat (Kg)</Data></Cell>
          <Cell ss:StyleID="s23"><Data ss:Type="String">Total Omset</Data></Cell>
        </Row>';

        $medals = ['🥇', '🥈', '🥉'];
        foreach ($data_layanan as $idx => $lay) {
            $medal  = $medals[$idx] ?? ($idx + 1) . '.';
            $bg     = $idx === 0 ? 's26' : 's7';
            $st_t   = $idx === 0 ? 's4'  : 's5';
            $st_rp  = 's6';
            $st_n   = 's7';
            echo '<Row ss:Height="22">
              <Cell ss:StyleID="' . $bg  . '"><Data ss:Type="String">' . $medal . '</Data></Cell>
              <Cell ss:StyleID="' . $st_t . '"><Data ss:Type="String">' . htmlspecialchars(ucwords($lay['nama_paket'] ?? 'Terhapus')) . '</Data></Cell>
              <Cell ss:StyleID="' . $st_n . '"><Data ss:Type="Number">' . (int)$lay['jumlah'] . '</Data></Cell>
              <Cell ss:StyleID="' . $st_n . '"><Data ss:Type="Number">' . (float)$lay['total_berat'] . '</Data></Cell>
              <Cell ss:StyleID="' . $st_rp . '"><Data ss:Type="Number">' . (float)$lay['omset'] . '</Data></Cell>
            </Row>';
        }
    } else {
        echo '<Row><Cell ss:StyleID="s25" ss:MergeAcross="4"><Data ss:Type="String">Tidak ada data layanan di periode ini</Data></Cell></Row>';
    }

    echo '</Table></Worksheet>';

    // ════════════════════════════════════════════════════════════════════
    // SHEET 5: ARUS KAS MINGGUAN
    // ════════════════════════════════════════════════════════════════════
    echo '<Worksheet ss:Name="Arus Kas">
<Table ss:DefaultRowHeight="18">
  <Column ss:Width="100"/>
  <Column ss:Width="150"/>
  <Column ss:Width="150"/>
  <Column ss:Width="150"/>
';
    echo '<Row ss:Height="30"><Cell ss:StyleID="s1" ss:MergeAcross="3"><Data ss:Type="String">📈 ARUS KAS MINGGUAN — ' . strtoupper($label_periode) . '</Data></Cell></Row>';
    echo '<Row ss:Height="16"><Cell ss:StyleID="s25" ss:MergeAcross="3"><Data ss:Type="String">Perbandingan uang masuk vs keluar per minggu  |  Dicetak: ' . date('d F Y H:i') . '</Data></Cell></Row>';
    echo '<Row ss:Height="6"/>';
    echo '<Row ss:Height="22">
      <Cell ss:StyleID="s3"><Data ss:Type="String">Periode</Data></Cell>
      <Cell ss:StyleID="s3"><Data ss:Type="String">Uang Masuk (Rp)</Data></Cell>
      <Cell ss:StyleID="s3"><Data ss:Type="String">Uang Keluar (Rp)</Data></Cell>
      <Cell ss:StyleID="s3"><Data ss:Type="String">Saldo Bersih (Rp)</Data></Cell>
    </Row>';

    $total_m = 0; $total_k = 0;
    foreach ($arus_kas_labels as $idx => $lbl) {
        $masuk  = (float)($arus_kas_masuk[$idx]  ?? 0);
        $keluar = (float)($arus_kas_keluar[$idx] ?? 0);
        $saldo  = $masuk - $keluar;
        $total_m += $masuk; $total_k += $keluar;
        $ganjil = ($idx % 2 == 0);
        $st_rp  = $ganjil ? 's6' : 's20';
        $st_s   = $saldo >= 0 ? 's10' : 's12';
        echo '<Row ss:Height="18">
          <Cell ss:StyleID="' . ($ganjil ? 's7' : 's29') . '"><Data ss:Type="String">' . $lbl . '</Data></Cell>
          <Cell ss:StyleID="' . $st_rp . '"><Data ss:Type="Number">' . $masuk . '</Data></Cell>
          <Cell ss:StyleID="' . $st_rp . '"><Data ss:Type="Number">' . $keluar . '</Data></Cell>
          <Cell ss:StyleID="' . ($saldo >= 0 ? 's11' : 's13') . '"><Data ss:Type="Number">' . $saldo . '</Data></Cell>
        </Row>';
    }
    $saldo_total = $total_m - $total_k;
    $st_total_s  = $saldo_total >= 0 ? 's10' : 's12';
    echo '<Row ss:Height="22">
      <Cell ss:StyleID="s8"><Data ss:Type="String">TOTAL BULAN INI</Data></Cell>
      <Cell ss:StyleID="s9"><Data ss:Type="Number">' . $total_m . '</Data></Cell>
      <Cell ss:StyleID="s9"><Data ss:Type="Number">' . $total_k . '</Data></Cell>
      <Cell ss:StyleID="' . ($saldo_total >= 0 ? 's11' : 's13') . '"><Data ss:Type="Number">' . $saldo_total . '</Data></Cell>
    </Row>';

    echo '</Table></Worksheet>';

    echo '</Workbook>';
    exit();
}

// Mode PDF — print-friendly
$is_pdf = ($mode_export === 'pdf');
?>
<!DOCTYPE html>
<html lang="id">
<head>
        <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/icontab.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <title>Laporan <?php echo $label_periode; ?> - LaundryKuy</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f1f5f9; font-family: 'Segoe UI', sans-serif; }

        /* Kartu section laporan */
        .card-laporan {
            border: none;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            margin-bottom: 28px;
        }
        .card-laporan .card-header-laporan {
            border-radius: 16px 16px 0 0;
            padding: 18px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card-laporan .card-body { padding: 24px; }

        /* Stat boxes di Laba Rugi */
        .stat-box {
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        .stat-box .stat-label { font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8; }
        .stat-box .stat-value { font-size: 1.6rem; font-weight: 900; margin-top: 6px; }
        .stat-box .stat-sub   { font-size: 0.75rem; margin-top: 4px; opacity: 0.75; }

        /* Badge piutang hari */
        .badge-hari-ok      { background: #dcfce7; color: #166534; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
        .badge-hari-warn    { background: #fef9c3; color: #854d0e; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
        .badge-hari-danger  { background: #fee2e2; color: #991b1b; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }

        /* Chart container */
        .chart-wrap { position: relative; height: 280px; }

        /* Progress bar paket layanan */
        .progress { height: 10px; border-radius: 10px; }

        /* Tabel dalam laporan */
        .tbl-laporan { font-size: 0.88rem; }
        .tbl-laporan thead th { background: #0f172a; color: white; font-weight: 700; padding: 10px 12px; }
        .tbl-laporan tbody td { padding: 9px 12px; vertical-align: middle; }
        .tbl-laporan tfoot td { background: #f1f5f9; font-weight: 700; padding: 10px 12px; }

        /* CSS Print / PDF */
        @media print {
            /* Sembunyikan sidebar, topbar, tombol */
            .no-print,
            .navbar,
            nav,
            .kuy-sidebar,
            .kuy-topbar,
            .kuy-mobile-toggle,
            .kuy-overlay { display: none !important; }

            /* Layout: hapus margin sidebar */
            .kuy-layout { display: block !important; }
            .kuy-main   {
                margin-left: 0 !important;
                width: 100% !important;
            }
            .kuy-content { padding: 0 !important; }

            /* Paksa warna background tercetak */
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }

            /* Body & page */
            body {
                background: white !important;
                margin: 0 !important;
                padding: 0 !important;
                font-size: 11px !important;
            }

            /* Container fit A4 */
            .container-fluid {
                padding: 4px 8px !important;
                max-width: 100% !important;
                width: 100% !important;
            }

            /* Row grid paksa full width */
            .row { margin: 0 !important; }
            .col-md-6, .col-lg-7, .col-lg-5,
            .col-md-3, .col-4 {
                width: 100% !important;
                max-width: 100% !important;
                flex: 0 0 100% !important;
            }

            /* Stat boxes: tampilkan inline 2 kolom */
            .row.g-3 .col-6,
            .row.g-3 .col-md-3 {
                width: 50% !important;
                max-width: 50% !important;
                flex: 0 0 50% !important;
                display: inline-block !important;
                vertical-align: top !important;
            }

            /* Kartu laporan */
            .card-laporan {
                box-shadow: none !important;
                border: 1px solid #cbd5e1 !important;
                border-radius: 6px !important;
                margin-bottom: 14px !important;
                break-inside: avoid;
                page-break-inside: avoid;
                width: 100% !important;
            }

            /* Header kartu */
            .card-header-laporan {
                border-radius: 6px 6px 0 0 !important;
                padding: 10px 14px !important;
            }
            .card-laporan .card-body { padding: 12px !important; }

            /* Stat box ukuran lebih kecil */
            .stat-box {
                border: 1px solid #e2e8f0 !important;
                padding: 10px !important;
            }
            .stat-box .stat-value { font-size: 1.2rem !important; }

            /* Tabel lebih kompak */
            .tbl-laporan { font-size: 0.75rem !important; }
            .tbl-laporan thead th { padding: 6px 8px !important; }
            .tbl-laporan tbody td { padding: 5px 8px !important; }

            /* Chart: sembunyikan (tidak bisa print canvas dengan baik) */
            .chart-wrap { display: none !important; }

            /* Page break antar section */
            .page-break {
                page-break-before: always !important;
                break-before: always !important;
            }

            /* Header print muncul */
            .d-print-block { display: block !important; }

            /* Progress bar ranking layanan tetap terlihat */
            .progress { height: 8px !important; }
        }

        /* Ukuran kertas A4 landscape untuk laporan yang lebar */
        @page { size: A4 portrait; margin: 10mm 8mm; }
    </style>
</head>
<body>

<div class="kuy-layout">
<?php $halaman_aktif = 'laporan'; include 'sidebarkuy.php'; ?>
<main class="kuy-main" id="kuyMain">
    <div class="kuy-topbar no-print">
        <span class="kuy-topbar-title">Laporan Akuntansi</span>
        <div class="kuy-topbar-right">
            <span class="kuy-topbar-user">
                <i class="bi bi-person-circle me-1"></i>
                <?php echo ucwords($_SESSION['username']); ?>
            </span>
        </div>
    </div>
    <div class="kuy-content">

<?php if (!$is_pdf): ?>
<!-- Filter & konten laporan -->
<?php endif; ?>

<div class="container-fluid px-4 pb-5">

    <!-- ============================================================
         HEADER + FILTER + TOMBOL EXPORT
         ============================================================ -->
    <div class="row mb-4 align-items-center no-print">
        <div class="col-md-6">
            <h2 class="fw-bolder text-dark mb-1">
                <i class="bi bi-file-earmark-bar-graph-fill text-primary me-2"></i>Laporan Akuntansi
            </h2>
            <p class="text-muted fw-semibold mb-0">Periode: <strong class="text-dark"><?php echo $label_periode; ?></strong></p>
        </div>
        <div class="col-md-6 mt-3 mt-md-0">
            <div class="d-flex gap-2 justify-content-md-end flex-wrap">

                <!-- Form Filter Bulan & Tahun -->
                <form method="GET" action="" class="d-flex gap-2 align-items-center">
                    <select name="bulan" class="form-select form-select-sm border-2 fw-bold" style="width:140px;">
                        <?php
                        $nama_bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                        for ($i = 1; $i <= 12; $i++) {
                            $sel = ($i == $f_bulan) ? 'selected' : '';
                            echo "<option value='$i' $sel>{$nama_bulan[$i-1]}</option>";
                        }
                        ?>
                    </select>
                    <select name="tahun" class="form-select form-select-sm border-2 fw-bold" style="width:100px;">
                        <?php for ($y = 2024; $y <= 2030; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $f_tahun ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm fw-bold px-3">
                        <i class="bi bi-funnel-fill me-1"></i>Tampilkan
                    </button>
                </form>

                <!-- Tombol Export -->
                <a href="?bulan=<?php echo $f_bulan; ?>&tahun=<?php echo $f_tahun; ?>&export=excel"
                   class="btn btn-success btn-sm fw-bold px-3">
                    <i class="bi bi-file-earmark-excel-fill me-1"></i>Excel
                </a>
                <button onclick="cetakPDF()" class="btn btn-danger btn-sm fw-bold px-3">
                    <i class="bi bi-file-earmark-pdf-fill me-1"></i>PDF
                </button>

            </div>
        </div>
    </div>

    <!-- Header Print (hanya muncul saat print/PDF) -->
    <div class="d-none d-print-block mb-4 text-center border-bottom pb-3">
        <h3 class="fw-bolder mb-1">🫧 LaundryKuy — Laporan Akuntansi</h3>
        <p class="text-muted mb-0">Periode: <strong><?php echo $label_periode; ?></strong> &nbsp;|&nbsp; Dicetak: <?php echo date('d F Y, H:i'); ?></p>
    </div>


    <!-- ============================================================
         SECTION 1: LABA RUGI
         ============================================================ -->
    <div class="card-laporan bg-white">
        <div class="card-header-laporan" style="background: linear-gradient(to right, #0f172a, #1e293b); color:white;">
            <div>
                <h5 class="fw-bolder mb-0"><i class="bi bi-bar-chart-line-fill me-2 text-warning"></i>Laporan Laba Rugi</h5>
                <small class="opacity-75">Ringkasan keuangan periode <?php echo $label_periode; ?></small>
            </div>
        </div>
        <div class="card-body">

            <!-- Stat boxes baris atas -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="stat-box" style="background:#dbeafe;">
                        <div class="stat-label text-primary">Pendapatan Lunas</div>
                        <div class="stat-value text-primary">Rp <?php echo number_format($pendapatan_lunas,0,',','.'); ?></div>
                        <div class="stat-sub text-primary">
                            <?php if ($selisih_persen > 0): ?>
                                <i class="bi bi-arrow-up-right"></i> +<?php echo $selisih_persen; ?>% vs bulan lalu
                            <?php elseif ($selisih_persen < 0): ?>
                                <i class="bi bi-arrow-down-right"></i> <?php echo $selisih_persen; ?>% vs bulan lalu
                            <?php else: ?>
                                — Sama dengan bulan lalu
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-box" style="background:#fef9c3;">
                        <div class="stat-label" style="color:#854d0e;">Piutang</div>
                        <div class="stat-value" style="color:#854d0e;">Rp <?php echo number_format($pendapatan_piutang,0,',','.'); ?></div>
                        <div class="stat-sub" style="color:#854d0e;">Belum dibayar pelanggan</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-box" style="background:#fee2e2;">
                        <div class="stat-label text-danger">Total Pengeluaran</div>
                        <div class="stat-value text-danger">Rp <?php echo number_format($total_pengeluaran,0,',','.'); ?></div>
                        <div class="stat-sub text-danger"><?php echo count($data_kategori); ?> kategori pengeluaran</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-box" style="background:<?php echo $laba_bersih >= 0 ? '#dcfce7' : '#fee2e2'; ?>;">
                        <div class="stat-label" style="color:<?php echo $laba_bersih >= 0 ? '#166534' : '#991b1b'; ?>;">Laba Bersih</div>
                        <div class="stat-value" style="color:<?php echo $laba_bersih >= 0 ? '#166534' : '#991b1b'; ?>;">
                            Rp <?php echo number_format(abs($laba_bersih),0,',','.'); ?>
                        </div>
                        <div class="stat-sub" style="color:<?php echo $laba_bersih >= 0 ? '#166534' : '#991b1b'; ?>;">
                            Margin <?php echo $margin_persen; ?>%
                            <?php echo $laba_bersih < 0 ? '⚠️ Rugi' : '✅ Untung'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================================
                 AKUMULASI LABA/RUGI (SALDO BERJALAN ANTAR BULAN)
                 Laba Rugi di atas tetap per bulan (independen). Banner ini
                 menunjukkan posisi KUMULATIF: Saldo Awal (bawaan bulan lalu)
                 + Laba/Rugi bulan ini = Saldo Akhir yang akan dibawa ke bulan
                 berikutnya. Jika masih minus, artinya usaha masih dalam
                 proses menutup defisit dari bulan-bulan sebelumnya.
                 ============================================================ -->
            <div class="p-3 rounded-3 mb-4" style="background:#f8f9fc; border:1px dashed #c9a96e;">
                <div class="row g-3 align-items-center text-center">
                    <div class="col-12 col-md-3">
                        <div class="small text-muted fw-semibold mb-1">Saldo Awal (s.d. akhir bulan lalu)</div>
                        <div class="fw-bolder fs-5" style="color:<?php echo $akumulasi_awal >= 0 ? '#166534' : '#991b1b'; ?>;">
                            <?php echo $akumulasi_awal < 0 ? '-' : ''; ?>Rp <?php echo number_format(abs($akumulasi_awal),0,',','.'); ?>
                        </div>
                    </div>
                    <div class="col-auto d-none d-md-block text-muted fs-4">
                        <i class="bi bi-plus-slash-minus"></i>
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="small text-muted fw-semibold mb-1">Laba/Rugi <?php echo $label_periode; ?></div>
                        <div class="fw-bolder fs-5" style="color:<?php echo $laba_bersih >= 0 ? '#166534' : '#991b1b'; ?>;">
                            <?php echo $laba_bersih < 0 ? '-' : ''; ?>Rp <?php echo number_format(abs($laba_bersih),0,',','.'); ?>
                        </div>
                    </div>
                    <div class="col-auto d-none d-md-block text-muted fs-4">
                        <i class="bi bi-arrow-right"></i>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="small text-muted fw-semibold mb-1">Akumulasi s.d. <?php echo $label_periode; ?></div>
                        <div class="fw-bolder fs-4" style="color:<?php echo $akumulasi_akhir >= 0 ? '#166534' : '#991b1b'; ?>;">
                            <?php echo $akumulasi_akhir < 0 ? '-' : ''; ?>Rp <?php echo number_format(abs($akumulasi_akhir),0,',','.'); ?>
                        </div>
                        <?php if ($akumulasi_akhir < 0): ?>
                            <div class="small mt-1" style="color:#991b1b;">
                                <i class="bi bi-exclamation-triangle me-1"></i>Masih menutup defisit sejak <?php echo $label_awal_akumulasi; ?>
                            </div>
                        <?php else: ?>
                            <div class="small mt-1" style="color:#166534;">
                                <i class="bi bi-check-circle me-1"></i>Surplus terakumulasi sejak <?php echo $label_awal_akumulasi; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- [FIX] Rincian pengeluaran per kategori: progress bar -> diagram batang Chart.js -->
            <?php if (count($data_kategori) > 0): ?>
            <h6 class="fw-bold text-muted mb-3"><i class="bi bi-bar-chart-fill me-1"></i>Pengeluaran per Kategori</h6>
            <div style="position:relative; height:<?php echo min(40 + count($data_kategori)*44, 320); ?>px;">
                <canvas id="chartKategoriPengeluaran"></canvas>
            </div>
            <!-- Tabel ringkasan di bawah grafik -->
            <div class="table-responsive mt-3">
                <table class="table table-sm table-borderless mb-0">
                    <thead><tr class="text-muted small">
                        <th>Kategori</th><th class="text-end">Jumlah</th><th class="text-end">Porsi</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($data_kategori as $kat):
                        $persen_kat = $total_pengeluaran > 0 ? round(($kat['total']/$total_pengeluaran)*100) : 0;
                    ?>
                    <tr>
                        <td class="fw-semibold small"><?php echo htmlspecialchars($kat['kategori']); ?></td>
                        <td class="text-end small text-danger">Rp <?php echo number_format($kat['total'],0,',','.'); ?></td>
                        <td class="text-end small text-muted"><?php echo $persen_kat; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <script>
            (function(){
                var katLabels = <?php echo json_encode(array_column($data_kategori,'kategori')); ?>;
                var katData   = <?php echo json_encode(array_map(function($k){return (float)$k['total'];}, $data_kategori)); ?>;
                var katColors = [
                    '#dc3545','#e85d04','#f48c06','#2d6a4f','#1e3a5f',
                    '#6a0572','#0077b6','#555555','#c9a96e','#2e86ab'
                ];
                function fmtRp(v){
                    return 'Rp ' + v.toLocaleString('id-ID');
                }
                new Chart(document.getElementById('chartKategoriPengeluaran'), {
                    type: 'bar',
                    data: {
                        labels: katLabels,
                        datasets: [{
                            label: 'Pengeluaran',
                            data: katData,
                            backgroundColor: katColors.slice(0, katLabels.length),
                            borderRadius: 6,
                            borderSkipped: false,
                        }]
                    },
                    options: {
                        indexAxis: 'y', // horizontal bar agar label kategori terbaca jelas
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: ctx => ' ' + fmtRp(ctx.raw)
                                }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: { callback: v => fmtRp(v), font: { size: 10 } },
                                grid: { color: 'rgba(0,0,0,0.05)' }
                            },
                            y: {
                                ticks: { font: { size: 11 }, color: '#374151' },
                                grid: { display: false }
                            }
                        }
                    }
                });
            })();
            </script>
            <?php else: ?>
            <p class="text-muted text-center py-3"><i class="bi bi-inbox me-2"></i>Tidak ada pengeluaran di periode ini.</p>
            <?php endif; ?>

        </div>
    </div>


    <!-- ============================================================
         SECTION 2: ARUS KAS
         ============================================================ -->
    <div class="card-laporan bg-white page-break">
        <div class="card-header-laporan" style="background: linear-gradient(135deg,#0369a1,#0ea5e9); color:white;">
            <div>
                <h5 class="fw-bolder mb-0"><i class="bi bi-graph-up-arrow me-2"></i>Arus Kas Mingguan</h5>
                <small class="opacity-75">Perbandingan uang masuk vs keluar per minggu — <?php echo $label_periode; ?></small>
            </div>
        </div>
        <div class="card-body">
            <div class="chart-wrap">
                <canvas id="chartArusKas"></canvas>
            </div>
            <!-- Summary bawah grafik -->
            <div class="row g-3 mt-3">
                <?php
                $total_masuk_kas  = array_sum($arus_kas_masuk);
                $total_keluar_kas = array_sum($arus_kas_keluar);
                $saldo_kas        = $total_masuk_kas - $total_keluar_kas;
                ?>
                <div class="col-4 text-center">
                    <div class="fw-bold text-success">Rp <?php echo number_format($total_masuk_kas,0,',','.'); ?></div>
                    <div class="small text-muted">Total Masuk</div>
                </div>
                <div class="col-4 text-center">
                    <div class="fw-bold text-danger">Rp <?php echo number_format($total_keluar_kas,0,',','.'); ?></div>
                    <div class="small text-muted">Total Keluar</div>
                </div>
                <div class="col-4 text-center">
                    <div class="fw-bold" style="color:<?php echo $saldo_kas >= 0 ? '#166534' : '#991b1b'; ?>;">
                        Rp <?php echo number_format(abs($saldo_kas),0,',','.'); ?>
                    </div>
                    <div class="small text-muted">Saldo Bersih <?php echo $saldo_kas < 0 ? '(Minus)' : ''; ?></div>
                </div>
            </div>
        </div>
    </div>


    <!-- ============================================================
         SECTION 3: REKAP TRANSAKSI
         ============================================================ -->
    <div class="card-laporan bg-white page-break">
        <div class="card-header-laporan" style="background: linear-gradient(135deg,#4f46e5,#7c3aed); color:white;">
            <div>
                <h5 class="fw-bolder mb-0"><i class="bi bi-receipt me-2"></i>Rekap Transaksi</h5>
                <small class="opacity-75"><?php echo $jml_transaksi; ?> nota · Total berat <?php echo $total_berat_all; ?> Kg · Rata-rata Rp <?php echo number_format($rata_nilai_trx,0,',','.'); ?>/nota</small>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if ($jml_transaksi > 0): ?>
            <div class="table-responsive">
                <table class="table tbl-laporan table-hover mb-0">
                    <thead>
                        <tr>
                            <th>No. Nota</th>
                            <th>Tanggal</th>
                            <th>Pelanggan</th>
                            <th>Paket</th>
                            <th class="text-end">Berat</th>
                            <th class="text-end">Total</th>
                            <th class="text-center">Bayar</th>
                            <th class="text-center">Cucian</th>
                            <th>Kasir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data_transaksi as $t): ?>
                        <tr>
                            <td class="fw-bold text-primary">KUY-<?php echo sprintf("%02d", $t['id_trans']); ?></td>
                            <td><?php echo date('d M Y', strtotime($t['tgl'])); ?></td>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($t['nama']); ?></div>
                                <div class="small text-muted"><?php echo $t['hp']; ?></div>
                            </td>
                            <td><?php echo htmlspecialchars(ucwords($t['nama_paket'] ?? 'Terhapus')); ?></td>
                            <td class="text-end"><?php echo $t['berat']; ?> Kg</td>
                            <td class="text-end fw-bold">Rp <?php echo number_format($t['total'],0,',','.'); ?></td>
                            <td class="text-center">
                                <?php if ($t['st_bayar'] == 'lunas'): ?>
                                    <span class="badge bg-success rounded-pill">Lunas</span>
                                <?php else: ?>
                                    <span class="badge bg-danger rounded-pill">Belum</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php
                                $warna_cuci = ['proses'=>'warning','selesai'=>'info','diambil'=>'secondary'];
                                $label_cuci = ['proses'=>'Proses','selesai'=>'Selesai','diambil'=>'Diambil'];
                                $st = $t['st_cuci'];
                                echo '<span class="badge bg-' . ($warna_cuci[$st] ?? 'secondary') . ' text-dark rounded-pill">' . ($label_cuci[$st] ?? $st) . '</span>';
                                ?>
                            </td>
                            <td class="text-muted small"><?php echo htmlspecialchars($t['kasir'] ?? '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="fw-bold">TOTAL PERIODE</td>
                            <td class="text-end fw-bold"><?php echo $total_berat_all; ?> Kg</td>
                            <td class="text-end fw-bold text-success">Rp <?php echo number_format($total_omset_all,0,',','.'); ?></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-2 d-block mb-2"></i>Tidak ada transaksi di periode ini.
            </div>
            <?php endif; ?>
        </div>
    </div>


    <!-- ============================================================
         SECTION 4: ANALISIS LAYANAN
         ============================================================ -->
    <div class="card-laporan bg-white page-break">
        <div class="card-header-laporan" style="background: linear-gradient(135deg,#0d9488,#059669); color:white;">
            <div>
                <h5 class="fw-bolder mb-0"><i class="bi bi-pie-chart-fill me-2"></i>Analisis Performa Layanan</h5>
                <small class="opacity-75">Paket terlaris dan kontribusi omset — <?php echo $label_periode; ?></small>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($data_layanan) > 0):
                $max_jml = max(array_column($data_layanan, 'jumlah'));
            ?>
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="chart-wrap">
                        <canvas id="chartLayanan"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-bold text-muted mb-3">Ranking Paket</h6>
                    <?php foreach ($data_layanan as $idx => $lay):
                        $persen_lay = $max_jml > 0 ? round(($lay['jumlah'] / $max_jml) * 100) : 0;
                        $warna_lay  = ['#0d6efd','#0d9488','#7c3aed','#dc2626','#f59e0b'];
                        $wl         = $warna_lay[$idx % count($warna_lay)];
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="fw-semibold small"><?php echo htmlspecialchars(ucwords($lay['nama_paket'] ?? 'Terhapus')); ?></span>
                            <span class="small text-muted"><?php echo $lay['jumlah']; ?> order · Rp <?php echo number_format($lay['omset'],0,',','.'); ?></span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" style="width:<?php echo $persen_lay; ?>%; background:<?php echo $wl; ?>;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="alert alert-light border rounded-3 small mt-3 mb-0">
                        <i class="bi bi-lightbulb-fill text-warning me-1"></i>
                        <strong>Paket terlaris:</strong>
                        <?php echo htmlspecialchars(ucwords($data_layanan[0]['nama_paket'] ?? 'N/A')); ?>
                        dengan <?php echo $data_layanan[0]['jumlah']; ?> order
                        (<?php echo $data_layanan[0]['total_berat']; ?> Kg total).
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-2 d-block mb-2"></i>Tidak ada data layanan di periode ini.
            </div>
            <?php endif; ?>
        </div>
    </div>


    <!-- ============================================================
         SECTION 5: PIUTANG
         ============================================================ -->
    <div class="card-laporan bg-white page-break">
        <div class="card-header-laporan" style="background: linear-gradient(135deg,#dc2626,#b91c1c); color:white;">
            <div>
                <h5 class="fw-bolder mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Laporan Piutang</h5>
                <small class="opacity-75">
                    <?php echo count($data_piutang); ?> transaksi belum lunas ·
                    Total Rp <?php echo number_format($total_piutang,0,',','.'); ?>
                    <?php if (count($data_piutang) == 0) echo '✅ Semua lunas!'; ?>
                </small>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (count($data_piutang) > 0): ?>
            <div class="table-responsive">
                <table class="table tbl-laporan table-hover mb-0">
                    <thead>
                        <tr>
                            <th>No. Nota</th>
                            <th>Tanggal</th>
                            <th>Pelanggan</th>
                            <th>No. HP</th>
                            <th class="text-end">Total Tagihan</th>
                            <th class="text-center">Hari Tertunggak</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data_piutang as $p):
                            $hari = $p['hari_tertunggak'];
                            if ($hari <= 3)      { $badge_hari = 'badge-hari-ok';     $label_hari = '≤ 3 hari'; }
                            elseif ($hari <= 7)  { $badge_hari = 'badge-hari-warn';   $label_hari = '4-7 hari'; }
                            else                 { $badge_hari = 'badge-hari-danger';  $label_hari = '> 7 hari ⚠️'; }
                        ?>
                        <tr class="<?php echo $hari > 7 ? 'table-danger' : ''; ?>">
                            <td class="fw-bold text-primary">KUY-<?php echo sprintf("%02d", $p['id_trans']); ?></td>
                            <td><?php echo date('d M Y', strtotime($p['tgl'])); ?></td>
                            <td class="fw-semibold"><?php echo htmlspecialchars($p['nama']); ?></td>
                            <td>
                                <a href="https://wa.me/<?php echo preg_replace('/^0/', '62', $p['hp']); ?>"
                                   target="_blank" class="text-success text-decoration-none small fw-semibold">
                                    <i class="bi bi-whatsapp me-1"></i><?php echo $p['hp']; ?>
                                </a>
                            </td>
                            <td class="text-end fw-bold text-danger">Rp <?php echo number_format($p['total'],0,',','.'); ?></td>
                            <td class="text-center">
                                <span class="<?php echo $badge_hari; ?>"><?php echo $hari; ?> hari</span>
                            </td>
                            <td class="text-center"><span class="badge bg-danger rounded-pill">Belum Lunas</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="fw-bold">TOTAL PIUTANG</td>
                            <td class="text-end fw-bold text-danger">Rp <?php echo number_format($total_piutang,0,',','.'); ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-check-circle-fill text-success fs-2 d-block mb-2"></i>
                <span class="fw-bold text-success">Semua tagihan sudah lunas!</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div><!-- end container -->

<!-- =============== MODAL GANTI PASSWORD OWNER =============== -->
<div class="modal fade" id="modalGantiPwOwner" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header text-white rounded-top-4" style="background: linear-gradient(to right, #0f172a, #1e293b);">
                <h5 class="modal-title fw-bold"><i class="bi bi-key-fill me-2 text-warning"></i>Ganti Password Saya</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="formGantiPwOwner">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Password Lama</label>
                        <div class="position-relative">
                            <input type="password" class="form-control form-control-lg pe-5" name="pw_lama" id="pw_lama_owner" placeholder="Password saat ini" required>
                            <i class="bi bi-eye position-absolute top-50 end-0 translate-middle-y me-3" style="cursor:pointer;color:#6c757d;" onclick="togglePwOwner('pw_lama_owner',this)"></i>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Password Baru</label>
                        <div class="position-relative">
                            <input type="password" class="form-control form-control-lg pe-5" name="pw_baru_owner" id="pw_baru_owner" placeholder="Minimal 6 karakter" required minlength="6">
                            <i class="bi bi-eye position-absolute top-50 end-0 translate-middle-y me-3" style="cursor:pointer;color:#6c757d;" onclick="togglePwOwner('pw_baru_owner',this)"></i>
                        </div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label fw-bold">Konfirmasi Password Baru</label>
                        <div class="position-relative">
                            <input type="password" class="form-control form-control-lg pe-5" name="pw_konfirm_owner" id="pw_konfirm_owner" placeholder="Ulangi password baru" required>
                            <i class="bi bi-eye position-absolute top-50 end-0 translate-middle-y me-3" style="cursor:pointer;color:#6c757d;" onclick="togglePwOwner('pw_konfirm_owner',this)"></i>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-dark fw-bold px-4" onclick="konfirmasiGantiPw()">
                        <i class="bi bi-save me-1"></i>Simpan Password Baru
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ── Grafik Arus Kas ──────────────────────────────────────────────
    const ctxKas = document.getElementById('chartArusKas');
    if (ctxKas) {
        new Chart(ctxKas, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($arus_kas_labels); ?>,
                datasets: [
                    {
                        label: 'Uang Masuk',
                        data: <?php echo json_encode($arus_kas_masuk); ?>,
                        backgroundColor: '#22c55e',
                        borderRadius: 6,
                    },
                    {
                        label: 'Uang Keluar',
                        data: <?php echo json_encode($arus_kas_keluar); ?>,
                        backgroundColor: '#ef4444',
                        borderRadius: 6,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: v => 'Rp ' + new Intl.NumberFormat('id-ID').format(v)
                        }
                    }
                }
            }
        });
    }

    // ── Grafik Layanan (Donut) ───────────────────────────────────────
    const ctxLayanan = document.getElementById('chartLayanan');
    if (ctxLayanan) {
        const labelsLayanan = <?php echo json_encode(array_map(fn($l) => ucwords($l['nama_paket'] ?? 'Terhapus'), $data_layanan)); ?>;
        const dataLayanan   = <?php echo json_encode(array_column($data_layanan, 'jumlah')); ?>;
        new Chart(ctxLayanan, {
            type: 'doughnut',
            data: {
                labels: labelsLayanan,
                datasets: [{
                    data: dataLayanan,
                    backgroundColor: ['#0d6efd','#0d9488','#7c3aed','#dc2626','#f59e0b','#64748b'],
                    borderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 12 } } }
                }
            }
        });
    }

    // ── Toggle password ──────────────────────────────────────────────
    function togglePwOwner(id, el) {
        const inp = document.getElementById(id);
        if (inp.type === 'password') { inp.type = 'text'; el.classList.replace('bi-eye','bi-eye-slash'); }
        else { inp.type = 'password'; el.classList.replace('bi-eye-slash','bi-eye'); }
    }

    // ── Konfirmasi ganti password ────────────────────────────────────
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
        }).then(r => {
            if (r.isConfirmed) {
                let inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = 'btn_ganti_pw_owner'; inp.value = '1';
                form.appendChild(inp); form.submit();
            }
        });
    }

    // ── Cetak PDF ────────────────────────────────────────────────────
    function cetakPDF() {
        Swal.fire({
            title: 'Cetak / Simpan PDF?',
            html: `Pastikan di dialog cetak browser:<br>
                   <strong>1.</strong> Pilih printer <strong>"Save as PDF"</strong><br>
                   <strong>2.</strong> Centang <strong>"Background graphics"</strong> agar warna ikut tercetak`,
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<i class="bi bi-printer-fill me-1"></i>Cetak Sekarang',
            cancelButtonText: 'Batal'
        }).then(r => {
            if (r.isConfirmed) {
                // Tunggu 500ms agar semua grafik Chart.js selesai render sebelum print
                setTimeout(() => { window.print(); }, 500);
            }
        });
    }

    // ── Mencegah double submit ───────────────────────────────────────
    if (window.history.replaceState) window.history.replaceState(null, null, window.location.href);
</script>

<?php if (!empty($pesan_sukses_pw)): ?>
<script>Swal.fire({ icon:'success', title:'Berhasil!', text:'<?php echo $pesan_sukses_pw; ?>', showConfirmButton:false, timer:2000 });</script>
<?php endif; ?>
<?php if (!empty($pesan_error_pw)): ?>
<script>Swal.fire({ icon:'error', title:'Gagal!', text:'<?php echo addslashes($pesan_error_pw); ?>' });</script>
<?php endif; ?>

    </div><!-- end kuy-content -->
</main><!-- end kuy-main -->
</div><!-- end kuy-layout -->
</body>
</html>