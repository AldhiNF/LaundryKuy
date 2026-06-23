<?php
/**
 * FILE: cek_voucherkuy.php
 * FUNGSI: Endpoint AJAX untuk validasi kode voucher secara real-time di halaman kasir.
 * Dipanggil oleh JavaScript di transaksikuy.php
 */

session_start();
include 'connectkuy.php';

// Hanya bisa diakses oleh admin yang sudah login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'kasir') {
    echo json_encode(['valid' => false, 'pesan' => 'Akses ditolak']);
    exit();
}

header('Content-Type: application/json');

$kode  = strtoupper(trim($_GET['kode'] ?? ''));
$today = date('Y-m-d');

if (empty($kode)) {
    echo json_encode(['valid' => false, 'pesan' => 'Kode kosong']);
    exit();
}

$stmt = mysqli_prepare($koneksi,
    "SELECT * FROM t_voucher
     WHERE kode = ? AND aktif = 1
     AND (tgl_mulai IS NULL OR tgl_mulai <= ?)
     AND (tgl_selesai IS NULL OR tgl_selesai >= ?)");
mysqli_stmt_bind_param($stmt, "sss", $kode, $today, $today);
mysqli_stmt_execute($stmt);
$voucher = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$voucher) {
    echo json_encode(['valid' => false, 'pesan' => 'Voucher tidak ditemukan atau sudah kadaluarsa']);
    exit();
}

if ($voucher['kuota'] > 0 && $voucher['terpakai'] >= $voucher['kuota']) {
    echo json_encode(['valid' => false, 'pesan' => 'Kuota voucher sudah habis']);
    exit();
}

// Voucher valid — kirim detail ke kasir
echo json_encode([
    'valid'         => true,
    'id_voucher'    => $voucher['id_voucher'],
    'kode'          => $voucher['kode'],
    'nama'          => $voucher['nama'],
    'tipe'          => $voucher['tipe'],
    'nilai'         => (float)$voucher['nilai'],
    'max_diskon'    => (float)$voucher['max_diskon'],
    'min_transaksi' => (float)$voucher['min_transaksi'],
    'sisa_kuota'    => $voucher['kuota'] > 0 ? ($voucher['kuota'] - $voucher['terpakai']) : null,
]);