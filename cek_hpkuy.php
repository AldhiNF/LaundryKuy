<?php
/**
 * FILE: cek_hpkuy.php
 * FUNGSI: Endpoint AJAX untuk cek duplikat nomor HP pelanggan secara realtime.
 * Dipanggil oleh JavaScript di transaksikuy.php saat kasir ketik nomor HP.
 */

session_start();
include 'connectkuy.php';

// Hanya kasir yang login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'kasir') {
    echo json_encode(['tersedia' => false, 'pesan' => 'Akses ditolak']);
    exit();
}

header('Content-Type: application/json');

$hp_raw = preg_replace('/[^0-9]/', '', $_GET['hp'] ?? '');

// Normalisasi +62 -> 08
if (substr($hp_raw, 0, 2) === '62') $hp_raw = '0' . substr($hp_raw, 2);

// Validasi format
if (!preg_match('/^08[0-9]{8,11}$/', $hp_raw)) {
    echo json_encode(['tersedia' => false, 'valid_format' => false, 'pesan' => 'Format tidak valid']);
    exit();
}

// Cek di database
$stmt = mysqli_prepare($koneksi, "SELECT id_pel, nama FROM t_pelanggan WHERE hp = ?");
mysqli_stmt_bind_param($stmt, "s", $hp_raw);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row    = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if ($row) {
    echo json_encode([
        'tersedia'    => false,
        'valid_format'=> true,
        'pesan'       => 'Nomor sudah terdaftar atas nama: ' . $row['nama'],
        'nama'        => $row['nama'],
        'id_pel'      => $row['id_pel'],
    ]);
} else {
    echo json_encode([
        'tersedia'    => true,
        'valid_format'=> true,
        'pesan'       => 'Nomor tersedia',
    ]);
}